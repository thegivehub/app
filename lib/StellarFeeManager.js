// StellarFeeManager.js
// A utility class for managing Stellar transaction fees dynamically

import StellarSdk from 'stellar-sdk';

class StellarFeeManager {
  constructor(config = {}) {
    // Default base fee (in stroops, 1 XLM = 10,000,000 stroops)
    this.defaultBaseFee = config.defaultBaseFee || StellarSdk.BASE_FEE;
    
    // Fee multipliers for different network conditions
    this.feeMultipliers = {
      low: config.lowMultiplier || 1.0,      // Normal network conditions
      medium: config.mediumMultiplier || 1.5, // Moderate congestion
      high: config.highMultiplier || 2.0,     // High congestion
      critical: config.criticalMultiplier || 3.0 // Severe congestion
    };
    
    // Cache duration in milliseconds
    this.cacheDuration = config.cacheDuration || 60000; // 1 minute default
    
    // Initialize cache for fee stats
    this.feeStatsCache = {
      timestamp: 0,
      data: null
    };
    
    // Initialize Horizon server connection
    this.horizonUrl = config.horizonUrl || 
      (config.useTestnet !== false ? 
        'https://horizon-testnet.stellar.org' : 
        'https://horizon.stellar.org');
    
    this.server = new StellarSdk.Server(this.horizonUrl);

    // Short cache for the optimized-fee fee_stats lookups so we don't hammer Horizon
    this.optimizedCacheDuration = config.optimizedCacheDuration || 10000; // 10s default
    this.optimizedStatsCache = {
      timestamp: 0,
      data: null
    };

    // Hard cap (in stroops) on the per-operation fee to avoid overpaying. 100000 = 0.01 XLM.
    this.maxFeePerOp = config.maxFeePerOp || 100000;

    // HTTP timeout (ms) for direct Horizon fee_stats calls used by getOptimizedFee
    this.feeStatsHttpTimeout = config.feeStatsHttpTimeout || 3000;

    // Set up logging
    this.enableLogging = config.enableLogging || false;
  }

  /**
   * Log messages if logging is enabled
   * @param {string} message - Message to log
   * @param {*} data - Optional data to log
   */
  log(message, data = null) {
    if (this.enableLogging) {
      if (data) {
        console.log(`[StellarFeeManager] ${message}`, data);
      } else {
        console.log(`[StellarFeeManager] ${message}`);
      }
    }
  }

  /**
   * Get current fee stats from Horizon
   * @param {boolean} forceRefresh - Force refresh the cache
   * @returns {Promise<Object>} - Fee statistics
   */
  async getFeeStats(forceRefresh = false) {
    const now = Date.now();
    
    // Check if we have a cached value that's still valid
    if (!forceRefresh && 
        this.feeStatsCache.data && 
        now - this.feeStatsCache.timestamp < this.cacheDuration) {
      this.log('Using cached fee stats');
      return this.feeStatsCache.data;
    }
    
    try {
      this.log('Fetching fee stats from Horizon');
      const feeStats = await this.server.feeStats();
      
      // Update cache
      this.feeStatsCache = {
        timestamp: now,
        data: feeStats
      };
      
      return feeStats;
    } catch (error) {
      this.log('Error fetching fee stats', error);
      
      // If we have cached data, return it despite being expired
      if (this.feeStatsCache.data) {
        this.log('Using expired cached fee stats due to error');
        return this.feeStatsCache.data;
      }
      
      // Otherwise, return a default structure
      return {
        fee_charged: {
          max: this.defaultBaseFee * 2,
          min: this.defaultBaseFee,
          mode: this.defaultBaseFee,
          p10: this.defaultBaseFee,
          p50: this.defaultBaseFee,
          p90: this.defaultBaseFee * 2,
          p95: this.defaultBaseFee * 2,
          p99: this.defaultBaseFee * 3
        }
      };
    }
  }

  /**
   * Analyze network congestion based on fee stats
   * @param {Object} feeStats - Fee statistics from Horizon
   * @returns {string} - Congestion level: 'low', 'medium', 'high', or 'critical'
   */
  analyzeCongestion(feeStats) {
    // If no fee stats, assume low congestion
    if (!feeStats || !feeStats.fee_charged) {
      return 'low';
    }
    
    const p10 = parseInt(feeStats.fee_charged.p10);
    const p50 = parseInt(feeStats.fee_charged.p50);
    const p90 = parseInt(feeStats.fee_charged.p90);
    
    // Calculate congestion based on fee percentiles
    if (p90 > p10 * 5) {
      // Severe congestion - p90 is 5x+ higher than p10
      return 'critical';
    } else if (p90 > p10 * 3) {
      // High congestion - p90 is 3-5x higher than p10
      return 'high';
    } else if (p90 > p10 * 1.5) {
      // Moderate congestion - p90 is 1.5-3x higher than p10
      return 'medium';
    } else {
      // Low congestion - fees are relatively uniform
      return 'low';
    }
  }

  /**
   * Get recommended fee based on network conditions
   * @param {Object} options - Optional parameters
   * @param {boolean} options.forceRefresh - Force refresh the fee stats cache
   * @param {string} options.priorityLevel - Transaction priority: 'low', 'medium', 'high'
   * @returns {Promise<number>} - Recommended fee in stroops
   */
  async getRecommendedFee(options = {}) {
    const { forceRefresh = false, priorityLevel = 'medium' } = options;
    
    try {
      // Get current fee stats
      const feeStats = await this.getFeeStats(forceRefresh);
      
      // Analyze network congestion
      const congestion = this.analyzeCongestion(feeStats);
      this.log(`Network congestion level: ${congestion}`);
      
      // Get base fee based on priority and congestion
      let baseFee;
      
      switch (priorityLevel) {
        case 'low':
          // Use p10 (lower percentile) for low priority
          baseFee = parseInt(feeStats.fee_charged.p10);
          break;
        case 'high':
          // Use p90 (higher percentile) for high priority
          baseFee = parseInt(feeStats.fee_charged.p90);
          break;
        case 'medium':
        default:
          // Use p50 (median) for medium priority
          baseFee = parseInt(feeStats.fee_charged.p50);
          break;
      }
      
      // Apply multiplier based on congestion
      const multiplier = this.feeMultipliers[congestion];
      let recommendedFee = Math.ceil(baseFee * multiplier);
      
      // Ensure fee is at least the minimum base fee
      recommendedFee = Math.max(recommendedFee, this.defaultBaseFee);
      
      this.log(`Recommended fee: ${recommendedFee} stroops (priority: ${priorityLevel}, congestion: ${congestion})`);
      return recommendedFee;
    } catch (error) {
      this.log('Error getting recommended fee', error);
      
      // In case of error, use default base fee with priority multiplier
      const priorityMultipliers = {
        low: 1.0,
        medium: 1.5,
        high: 2.0
      };
      
      const multiplier = priorityMultipliers[priorityLevel] || 1.5;
      return Math.ceil(this.defaultBaseFee * multiplier);
    }
  }

  /**
   * Create a fee bump transaction to increase the fee on a pending transaction
   * @param {string} sourceSecret - Secret key of the fee source account
   * @param {Transaction} innerTransaction - Original transaction to bump
   * @returns {Promise<Transaction>} - Fee bump transaction
   */
  async createFeeBumpTransaction(sourceSecret, innerTransaction) {
    try {
      const sourceKeypair = StellarSdk.Keypair.fromSecret(sourceSecret);
      
      // Get a high priority fee as we're bumping an existing transaction
      const bumpFee = await this.getRecommendedFee({ priorityLevel: 'high', forceRefresh: true });
      
      this.log(`Creating fee bump transaction with fee: ${bumpFee} stroops`);
      
      // Define the network passphrase based on the server URL
      const networkPassphrase = this.horizonUrl.includes('testnet') ? 
        StellarSdk.Networks.TESTNET : 
        StellarSdk.Networks.PUBLIC;
      
      // Create and sign the fee bump transaction
      const feeBumpTransaction = StellarSdk.TransactionBuilder.buildFeeBumpTransaction(
        sourceKeypair,
        bumpFee,
        innerTransaction,
        networkPassphrase
      );
      
      feeBumpTransaction.sign(sourceKeypair);
      
      return feeBumpTransaction;
    } catch (error) {
      this.log('Error creating fee bump transaction', error);
      throw error;
    }
  }

  /**
   * Estimate the total fee for a transaction with a given number of operations
   * @param {number} operationCount - Number of operations in the transaction
   * @param {Object} options - Optional parameters (same as getRecommendedFee)
   * @returns {Promise<number>} - Estimated total fee in stroops
   */
  async estimateTransactionFee(operationCount, options = {}) {
    const baseFee = await this.getRecommendedFee(options);
    return baseFee * Math.max(1, operationCount);
  }

  /**
   * Fetch raw fee stats directly from the Horizon /fee_stats endpoint.
   *
   * Unlike getFeeStats() (which uses the SDK and exposes fee_charged
   * percentiles), this reads the full payload including last_ledger_base_fee
   * and ledger_capacity_usage, and is cached on a short window so the optimizer
   * can be called frequently without hammering Horizon.
   *
   * @param {boolean} forceRefresh - Force refresh the short-lived cache
   * @returns {Promise<Object|null>} - Decoded fee_stats payload, or null on failure
   */
  async fetchRawFeeStats(forceRefresh = false) {
    const now = Date.now();

    if (!forceRefresh &&
        this.optimizedStatsCache.data &&
        now - this.optimizedStatsCache.timestamp < this.optimizedCacheDuration) {
      this.log('Using cached raw fee stats');
      return this.optimizedStatsCache.data;
    }

    const url = `${this.horizonUrl.replace(/\/+$/, '')}/fee_stats`;

    try {
      const controller = new AbortController();
      const timer = setTimeout(() => controller.abort(), this.feeStatsHttpTimeout);

      let data;
      try {
        const response = await fetch(url, {
          headers: { Accept: 'application/json' },
          signal: controller.signal
        });
        if (!response.ok) {
          throw new Error(`fee_stats request failed (HTTP ${response.status})`);
        }
        data = await response.json();
      } finally {
        clearTimeout(timer);
      }

      if (!data || !data.fee_charged) {
        throw new Error('Unexpected fee_stats payload');
      }

      this.optimizedStatsCache = { timestamp: now, data };
      return data;
    } catch (error) {
      this.log('Error fetching raw fee stats', error);

      // Return expired cache if available rather than failing hard
      if (this.optimizedStatsCache.data) {
        this.log('Using expired raw fee stats due to error');
        return this.optimizedStatsCache.data;
      }

      return null;
    }
  }

  /**
   * Compute a network-aware, optimized total fee for a transaction.
   *
   * Reads recent ledger fee-charged percentiles from Horizon /fee_stats and
   * maps the requested priority to a percentile:
   *   - 'low'    -> p10  (cheapest; may take longer to confirm)
   *   - 'normal' -> p50  (median; balanced)
   *   - 'high'   -> p90  (priority; pay more to outbid congestion)
   * When the ledger is near capacity we nudge the chosen percentile up one
   * level so transactions still land during congestion.
   *
   * The chosen per-operation fee is clamped to [base fee, maxFeePerOp] and
   * multiplied by the operation count to produce the total fee. On any network
   * failure it falls back to the base fee (last_ledger_base_fee if known,
   * otherwise defaultBaseFee) so callers never block on Horizon.
   *
   * @param {number} opCount - Number of operations in the transaction (>= 1)
   * @param {string} priority - 'low' | 'normal' | 'high'
   * @returns {Promise<number>} - Total optimized fee in stroops
   */
  async getOptimizedFee(opCount = 1, priority = 'normal') {
    opCount = Math.max(1, parseInt(opCount) || 1);
    priority = String(priority).toLowerCase();

    // Map priority -> percentile key in the fee_charged object
    const percentileMap = { low: 'p10', normal: 'p50', high: 'p90' };
    let percentileKey = percentileMap[priority] || 'p50';

    const stats = await this.fetchRawFeeStats();

    // Determine the network base fee (sane minimum)
    let baseFee = parseInt(this.defaultBaseFee);
    if (stats && stats.last_ledger_base_fee) {
      baseFee = Math.max(baseFee, parseInt(stats.last_ledger_base_fee));
    }

    // Graceful fallback: no usable stats -> base fee per operation
    if (!stats || !stats.fee_charged || stats.fee_charged[percentileKey] === undefined) {
      this.log(`getOptimizedFee falling back to base fee (${baseFee} stroops/op)`);
      const perOp = Math.min(Math.max(baseFee, parseInt(this.defaultBaseFee)), this.maxFeePerOp);
      return perOp * opCount;
    }

    // Bump the percentile up one tier when the ledger is near capacity so we
    // don't underbid during congestion.
    const capacity = stats.ledger_capacity_usage !== undefined
      ? parseFloat(stats.ledger_capacity_usage)
      : 0;
    if (capacity >= 0.75) {
      const escalation = { p10: 'p50', p50: 'p90', p90: 'p90' };
      percentileKey = escalation[percentileKey];
      this.log(`Ledger near capacity (${capacity}); escalating to ${percentileKey}`);
    }

    let perOp = parseInt(stats.fee_charged[percentileKey]);

    // Clamp to [base fee, maxFeePerOp]
    perOp = Math.max(perOp, baseFee);
    perOp = Math.min(perOp, this.maxFeePerOp);

    const totalFee = perOp * opCount;

    this.log(`Optimized fee: ${totalFee} stroops (${perOp}/op x ${opCount}, priority: ${priority}, percentile: ${percentileKey}, capacity: ${capacity})`);
    return totalFee;
  }

  /**
   * Check if a transaction failed due to fee-related issues
   * @param {Object} error - Error object from transaction submission
   * @returns {boolean} - Whether the error is fee-related
   */
  isFeeRelatedError(error) {
    if (!error || !error.response || !error.response.data || !error.response.data.extras) {
      return false;
    }
    
    const resultCodes = error.response.data.extras.result_codes || {};
    const txResult = resultCodes.transaction || '';
    
    // Check for fee-related error codes
    return txResult === 'tx_insufficient_fee' || 
           txResult === 'tx_fee_bump_inner_failed' ||
           txResult === 'tx_too_late';
  }

  /**
   * Get fee statistics for reporting
   * @returns {Promise<Object>} - Fee statistics for reporting
   */
  async getFeeStatistics() {
    const feeStats = await this.getFeeStats(true);
    const congestion = this.analyzeCongestion(feeStats);
    
    return {
      timestamp: new Date().toISOString(),
      congestion,
      feeStats: {
        min: parseInt(feeStats.fee_charged.min),
        max: parseInt(feeStats.fee_charged.max),
        median: parseInt(feeStats.fee_charged.p50),
        p90: parseInt(feeStats.fee_charged.p90)
      },
      recommendedFees: {
        low: await this.getRecommendedFee({ priorityLevel: 'low' }),
        medium: await this.getRecommendedFee({ priorityLevel: 'medium' }),
        high: await this.getRecommendedFee({ priorityLevel: 'high' })
      }
    };
  }
}

export default StellarFeeManager;
