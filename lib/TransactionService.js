// TransactionService.js
// Service to manage blockchain transactions for The Give Hub

import StellarTransactionBuilder from './StellarTransactionBuilder.js';

class TransactionService {
  constructor(dbClient, config = {}) {
    // Initialize the Stellar transaction builder
    this.stellarBuilder = new StellarTransactionBuilder({
      useTestnet: config.useTestnet !== false,
      enableLogging: config.enableLogging || false
    });
    
    // Database client for storing transaction data
    this.db = dbClient;
    
    // Defaults
    this.defaultAsset = config.defaultAsset || 'XLM'; // Native Stellar asset
    
    // Initialize error handling and retry settings
    this.maxRetries = config.maxRetries || 3;
    this.retryDelay = config.retryDelay || 2000; // ms
  }

  /**
   * Process a donation to a campaign
   * @param {Object} params - Donation parameters
   * @returns {Promise<Object>} - Donation result
   */
  async processDonation(params) {
    try {
      const { 
        donorId, 
        campaignId,
        amount,
        sourceSecret, // Donor's private key
        isAnonymous = false,
        message = "",
        recurring = false,
        recurringFrequency = "monthly"
      } = params;
      
      // Validate required parameters
      if (!donorId || !campaignId || !amount || !sourceSecret) {
        throw new Error('Missing required parameters for donation');
      }
      
      // Get campaign details from database
      const campaign = await this.db.collection('campaigns').findOne({ _id: campaignId });
      if (!campaign) {
        throw new Error(`Campaign not found: ${campaignId}`);
      }
      
      const campaignAddress = campaign.stellarAddress;
      if (!campaignAddress) {
        throw new Error(`Campaign does not have a Stellar address: ${campaignId}`);
      }
      
      // Create a transaction record
      const transactionRecord = {
        userId: donorId,
        campaignId: campaignId,
        amount: {
          value: parseFloat(amount),
          currency: this.defaultAsset
        },
        transaction: {
          status: 'pending'
        },
        type: recurring ? 'recurring' : 'one-time',
        status: 'pending',
        visibility: isAnonymous ? 'anonymous' : 'public',
        created: new Date(),
        updated: new Date()
      };
      
      // Handle recurring donations
      if (recurring) {
        // Set up recurring donation
        const recurringResult = await this.stellarBuilder.createRecurringDonationSetup({
          sourceSecret,
          campaignAddress,
          amount,
          campaignId,
          donorId,
          frequency: recurringFrequency
        });
        
        // Save additional recurring details
        transactionRecord.recurringDetails = {
          frequency: recurringFrequency,
          startDate: new Date(),
          status: 'active',
          totalProcessed: 1
        };
        
        // Store the recurring metadata
        transactionRecord.recurringDetails = {
          ...transactionRecord.recurringDetails,
          ...recurringResult.recurringMetadata
        };
        
        // Submit the transaction
        const submissionResult = await this.stellarBuilder.submitTransaction(recurringResult.transaction);
        
        if (submissionResult.success) {
          // Update transaction record with success details
          transactionRecord.transaction.txHash = submissionResult.result.hash;
          transactionRecord.transaction.stellarAddress = campaignAddress;
          transactionRecord.transaction.status = 'completed';
          transactionRecord.transaction.timestamp = new Date();
          transactionRecord.status = 'completed';
          
          // Calculate next payment date
          const nextPaymentDate = this.stellarBuilder.calculateNextPaymentDate(
            new Date(), 
            recurringFrequency
          );
          transactionRecord.recurringDetails.nextProcessing = new Date(nextPaymentDate);
          
          // Update donor record with recurring donation info
          await this.updateDonorWithDonation(donorId, transactionRecord, true);
        } else {
          // Update transaction record with failure details
          transactionRecord.transaction.status = 'failed';
          transactionRecord.transaction.error = submissionResult.error;
          transactionRecord.transaction.errorDetail = submissionResult.errorDetail;
          transactionRecord.status = 'failed';
        }
      } else {
        // Process one-time donation
        const transaction = await this.stellarBuilder.createDonation({
          sourceSecret,
          campaignAddress,
          amount,
          campaignId,
          donorId,
          isAnonymous,
          message
        });
        
        // Submit the transaction
        const submissionResult = await this.stellarBuilder.submitTransaction(transaction);
        
        if (submissionResult.success) {
          // Update transaction record with success details
          transactionRecord.transaction.txHash = submissionResult.result.hash;
          transactionRecord.transaction.stellarAddress = campaignAddress;
          transactionRecord.transaction.status = 'completed';
          transactionRecord.transaction.timestamp = new Date();
          transactionRecord.status = 'completed';
          
          // Update donor with donation info
          await this.updateDonorWithDonation(donorId, transactionRecord, false);
        } else {
          // Update transaction record with failure details
          transactionRecord.transaction.status = 'failed';
          transactionRecord.transaction.error = submissionResult.error;
          transactionRecord.transaction.errorDetail = submissionResult.errorDetail;
          transactionRecord.status = 'failed';
        }
      }
      
      // Save the transaction record
      const savedRecord = await this.db.collection('donations').insertOne(transactionRecord);
      
      // Update campaign funding stats
      if (transactionRecord.status === 'completed') {
        await this.updateCampaignFunding(campaignId, parseFloat(amount));
      }
      
      return {
        success: transactionRecord.status === 'completed',
        transactionRecord: {
          ...transactionRecord,
          _id: savedRecord.insertedId
        }
      };
    } catch (error) {
      console.error('Error processing donation:', error);
      return {
        success: false,
        error: error.message
      };
    }
  }

  /**
   * Update donor record with new donation information
   * @param {string} donorId - ID of the donor
   * @param {Object} transaction - Transaction record
   * @param {boolean} isRecurring - Whether the donation is recurring
   */
  async updateDonorWithDonation(donorId, transaction, isRecurring = false) {
    try {
      // Get the donor record
      const donor = await this.db.collection('donors').findOne({ _id: donorId });
      
      if (!donor) {
        console.warn(`Donor not found for update: ${donorId}`);
        return;
      }
      
      // Prepare the update
      const update = {
        $inc: { totalDonated: transaction.amount.value },
        $set: { 
          lastDonation: new Date(),
          lastActive: new Date(),
          status: 'active'
        },
        $push: {
          donationHistory: {
            amount: transaction.amount.value,
            date: new Date(),
            campaignId: transaction.campaignId,
            recurring: isRecurring
          }
        }
      };
      
      // Add recurring details if applicable
      if (isRecurring) {
        update.$set.donationType = 'recurring';
        update.$set.recurringDetails = {
          amount: transaction.amount.value,
          frequency: transaction.recurringDetails.frequency,
          startDate: transaction.recurringDetails.startDate,
          nextDonation: transaction.recurringDetails.nextProcessing,
          status: 'active'
        };
      }
      
      // Update the donor record
      await this.db.collection('donors').updateOne({ _id: donorId }, update);
      
      console.log(`Donor ${donorId} updated with new donation`);
    } catch (error) {
      console.error('Error updating donor record:', error);
      // Continue execution even if donor update fails
    }
  }

  /**
   * Update campaign funding statistics
   * @param {string} campaignId - ID of the campaign 
   * @param {number} amount - Donation amount
   */
  async updateCampaignFunding(campaignId, amount) {
    try {
      // Update campaign funding stats
      const updateResult = await this.db.collection('campaigns').updateOne(
        { _id: campaignId },
        { 
          $inc: { 
            'funding.raisedAmount': amount,
            'funding.donorCount': 1
          },
          $set: { 
            'updated': new Date() 
          }
        }
      );
      
      console.log(`Campaign ${campaignId} funding updated: ${amount}`);
      
      // Check if any milestones should be updated based on the new funding amount
      const campaign = await this.db.collection('campaigns').findOne({ _id: campaignId });
      
      if (campaign && campaign.timeline && campaign.timeline.milestones) {
        // Check each milestone to see if it should be updated
        for (const milestone of campaign.timeline.milestones) {
          if (milestone.status === 'pending' && 
              milestone.fundingTarget && 
              campaign.funding.raisedAmount >= milestone.fundingTarget) {
            
            // Update milestone status
            await this.db.collection('campaigns').updateOne(
              { 
                _id: campaignId, 
                'timeline.milestones._id': milestone._id 
              },
              { 
                $set: { 
                  'timeline.milestones.$.status': 'active',
                  'timeline.milestones.$.activatedDate': new Date()
                }
                  }
            );

            // Create notification for milestone activation
            await this.createMilestoneNotification(campaignId, milestone._id);
          }
        }
      }
    } catch (error) {
      console.error('Error updating campaign funding:', error);
      // Continue execution even if campaign update fails
    }
  }

  /**
   * Create notification for milestone activation
   * @param {string} campaignId - ID of the campaign
   * @param {string} milestoneId - ID of the milestone
   */
  async createMilestoneNotification(campaignId, milestoneId) {
    try {
      // Get campaign details
      const campaign = await this.db.collection('campaigns').findOne({ _id: campaignId });
      if (!campaign) return;

      // Get milestone details
      const milestone = campaign.timeline.milestones.find(m => m._id.toString() === milestoneId.toString());
      if (!milestone) return;

      // Create notification
      const notification = {
        campaignId,
        userId: campaign.creator,
        type: 'milestone_activated',
        title: 'Milestone Activated',
        message: `Milestone "${milestone.title}" has been activated in your campaign "${campaign.title}"`,
        data: {
          milestoneId,
          milestoneTitle: milestone.title,
          campaignTitle: campaign.title
        },
        read: false,
        created: new Date()
      };

      await this.db.collection('notifications').insertOne(notification);

      console.log(`Milestone notification created for ${campaignId}/${milestoneId}`);
    } catch (error) {
      console.error('Error creating milestone notification:', error);
    }
  }

  /**
   * Process milestone funding release
   * @param {Object} params - Release parameters
   * @returns {Promise<Object>} - Release result
   */
  async releaseMilestoneFunding(params) {
    try {
      const {
        campaignId,
        milestoneId,
        authorizedBy,
        amount
      } = params;

      // Validate required parameters
      if (!campaignId || !milestoneId || !authorizedBy) {
        throw new Error('Missing required parameters for milestone release');
      }

      // Get campaign details
      const campaign = await this.db.collection('campaigns').findOne({ _id: campaignId });
      if (!campaign) {
        throw new Error(`Campaign not found: ${campaignId}`);
      }

      // Verify milestone exists
      const milestone = campaign.timeline.milestones.find(m => m._id.toString() === milestoneId.toString());
      if (!milestone) {
        throw new Error(`Milestone not found: ${milestoneId}`);
      }

      // Verify milestone status is active
      if (milestone.status !== 'active') {
        throw new Error(`Milestone is not active: ${milestoneId} (${milestone.status})`);
      }

      // Verify user has authorization
      const isCreator = campaign.creator.toString() === authorizedBy.toString();
      const isAdmin = await this.isUserAdmin(authorizedBy);

      if (!isCreator && !isAdmin) {
        throw new Error(`User not authorized to release milestone funds: ${authorizedBy}`);
      }

      // Get escrow details
      const escrow = await this.db.collection('escrows').findOne({
        campaignId,
        'milestones.id': milestoneId
      });

      if (!escrow) {
        throw new Error(`Escrow not found for milestone: ${milestoneId}`);
      }

      const escrowMilestone = escrow.milestones.find(m => m.id === milestoneId);
      if (!escrowMilestone) {
        throw new Error(`Milestone not found in escrow: ${milestoneId}`);
      }

      // Validate amount
      const releaseAmount = amount || escrowMilestone.amount;
      if (parseFloat(releaseAmount) <= 0) {
        throw new Error(`Invalid release amount: ${releaseAmount}`);
      }

      // Release funds from escrow
      const releaseResult = await this.stellarBuilder.releaseMilestoneFunds({
        escrowSecret: escrow.escrowSecretKey,
        destinationAddress: campaign.stellarAddress,
        amount: releaseAmount.toString(),
        milestoneId,
        campaignId
      });

      if (!releaseResult.success) {
        throw new Error(`Failed to release milestone funds: ${releaseResult.error}`);
      }

      // Update milestone status
      await this.db.collection('campaigns').updateOne(
        { _id: campaignId, 'timeline.milestones._id': milestoneId },
        {
          $set: {
            'timeline.milestones.$.status': 'completed',
            'timeline.milestones.$.completedDate': new Date()
          }
        }
      );

      // Update escrow milestone status
      await this.db.collection('escrows').updateOne(
        { _id: escrow._id, 'milestones.id': milestoneId },
        {
          $set: {
            'milestones.$.status': 'completed',
            'milestones.$.releasedDate': new Date(),
            'milestones.$.releasedBy': authorizedBy,
            'milestones.$.transactionHash': releaseResult.result.hash
          }
        }
      );

      // Create transaction record
      const transactionRecord = {
        campaignId,
        milestoneId,
        amount: {
          value: parseFloat(releaseAmount),
          currency: this.defaultAsset
        },
        transaction: {
          txHash: releaseResult.result.hash,
          stellarAddress: campaign.stellarAddress,
          status: 'completed',
          timestamp: new Date()
        },
        type: 'milestone',
        status: 'completed',
        authorizedBy,
        created: new Date(),
        updated: new Date()
      };

      await this.db.collection('transactions').insertOne(transactionRecord);

      // Create notification for milestone completion
      await this.createMilestoneCompletionNotification(campaignId, milestoneId);

      return {
        success: true,
        transactionHash: releaseResult.result.hash,
        milestone: {
          id: milestoneId,
          status: 'completed',
          releasedAmount: parseFloat(releaseAmount)
        }
      };
    } catch (error) {
      console.error('Error releasing milestone funding:', error);
      return {
        success: false,
        error: error.message
      };
    }
  }

  /**
   * Check if a user has admin privileges
   * @param {string} userId - User ID to check
   * @returns {Promise<boolean>} - Whether the user is an admin
   */
  async isUserAdmin(userId) {
    try {
      const user = await this.db.collection('users').findOne({ _id: userId });
      return user && user.roles && user.roles.includes('admin');
    } catch (error) {
      console.error('Error checking admin status:', error);
      return false;
    }
  }

  /**
   * Create notification for milestone completion
   * @param {string} campaignId - Campaign ID
   * @param {string} milestoneId - Milestone ID
   */
  async createMilestoneCompletionNotification(campaignId, milestoneId) {
    try {
      // Get campaign details
      const campaign = await this.db.collection('campaigns').findOne({ _id: campaignId });
      if (!campaign) return;

      // Get milestone details
      const milestone = campaign.timeline.milestones.find(m => m._id.toString() === milestoneId.toString());
      if (!milestone) return;

      // Create notification
      const notification = {
        campaignId,
        userId: campaign.creator,
        type: 'milestone_completed',
        title: 'Milestone Completed',
        message: `Milestone "${milestone.title}" has been completed in your campaign "${campaign.title}"`,
        data: {
          milestoneId,
          milestoneTitle: milestone.title,
          campaignTitle: campaign.title
        },
        read: false,
        created: new Date()
      };

      await this.db.collection('notifications').insertOne(notification);

      // Notify all donors who contributed to this campaign
      const donors = await this.db.collection('donations')
        .distinct('userId', {
          campaignId,
          status: 'completed',
          visibility: 'public'  // Only notify donors who made public donations
        });

      for (const donorId of donors) {
        // Skip campaign creator, they've already been notified
        if (donorId.toString() === campaign.creator.toString()) continue;

        const donorNotification = {
          campaignId,
          userId: donorId,
          type: 'milestone_completed',
          title: 'Milestone Completed',
          message: `A milestone "${milestone.title}" has been completed in campaign "${campaign.title}" that you supported`,
          data: {
            milestoneId,
            milestoneTitle: milestone.title,
            campaignTitle: campaign.title
          },
          read: false,
          created: new Date()
        };

        await this.db.collection('notifications').insertOne(donorNotification);
      }

      console.log(`Milestone completion notifications created for ${campaignId}/${milestoneId}`);
    } catch (error) {
      console.error('Error creating milestone completion notification:', error);
    }
  }

  /**
   * Process recurring donations that are due
   * @returns {Promise<Object>} - Processing results
   */
  async processRecurringDonations() {
    try {
      const now = new Date();

      // Find all due recurring donations
      const dueRecurringDonations = await this.db.collection('donors')
        .find({
          'donationType': 'recurring',
          'recurringDetails.status': 'active',
          'recurringDetails.nextDonation': { $lte: now }
        })
        .toArray();

      console.log(`Found ${dueRecurringDonations.length} due recurring donations`);

      const results = {
        processed: 0,
        successful: 0,
        failed: 0,
        skipped: 0,
        errors: []
      };

      // Process each due donation
      for (const donor of dueRecurringDonations) {
        try {
          results.processed++;

          // Get donor wallet details
          const donorWallet = await this.db.collection('wallets').findOne({ userId: donor._id });

          if (!donorWallet || !donorWallet.secretKey) {
            console.warn(`No wallet found for donor ${donor._id}, skipping recurring donation`);
            results.skipped++;
            continue;
          }

          // Get campaign details from the last donation
          const lastDonation = donor.donationHistory[donor.donationHistory.length - 1];
          if (!lastDonation || !lastDonation.campaignId) {
            console.warn(`No last donation found for donor ${donor._id}, skipping recurring donation`);
            results.skipped++;
            continue;
          }

          const campaign = await this.db.collection('campaigns').findOne({
            _id: lastDonation.campaignId,
            status: 'active' // Only process for active campaigns
          });

          if (!campaign) {
            console.warn(`Campaign ${lastDonation.campaignId} not found or not active, skipping recurring donation`);
            results.skipped++;
            continue;
          }

          // Process the recurring donation
          const donationResult = await this.processDonation({
            donorId: donor._id,
            campaignId: campaign._id,
            amount: donor.recurringDetails.amount.toString(),
            sourceSecret: donorWallet.secretKey,
            isAnonymous: donor.preferences?.anonymousDonations || false,
            recurring: true,
            recurringFrequency: donor.recurringDetails.frequency
          });

          if (donationResult.success) {
            results.successful++;
          } else {
            results.failed++;
            results.errors.push({
              donorId: donor._id,
              error: donationResult.error
            });
          }
        } catch (error) {
          console.error(`Error processing recurring donation for donor ${donor._id}:`, error);
          results.failed++;
          results.errors.push({
            donorId: donor._id,
            error: error.message
          });
        }
      }

      return {
        success: true,
        results
      };
    } catch (error) {
      console.error('Error processing recurring donations:', error);
      return {
        success: false,
        error: error.message
      };
    }
  }

  /**
   * Cancel a recurring donation
   * @param {Object} params - Cancellation parameters
   * @returns {Promise<Object>} - Cancellation result
   */
  async cancelRecurringDonation(params) {
    try {
      const { donorId, campaignId, userId } = params;

      // Verify user authorization (must be the donor or an admin)
      if (donorId.toString() !== userId.toString()) {
        const isAdmin = await this.isUserAdmin(userId);
        if (!isAdmin) {
          throw new Error('Not authorized to cancel this recurring donation');
        }
      }

      // Get donor record
      const donor = await this.db.collection('donors').findOne({ _id: donorId });
      if (!donor) {
        throw new Error(`Donor not found: ${donorId}`);
      }

      // Verify donor has an active recurring donation
      if (donor.donationType !== 'recurring' ||
          !donor.recurringDetails ||
          donor.recurringDetails.status !== 'active') {
        throw new Error('No active recurring donation found');
      }

      // Get donor wallet
      const donorWallet = await this.db.collection('wallets').findOne({ userId: donorId });
      if (!donorWallet || !donorWallet.secretKey) {
        throw new Error('Donor wallet not found');
      }

      // Get campaign
      const campaign = await this.db.collection('campaigns').findOne({ _id: campaignId });
      if (!campaign) {
        throw new Error(`Campaign not found: ${campaignId}`);
      }

      // Create cancellation transaction
      const transaction = await this.stellarBuilder.createRecurringDonationCancellation({
        sourceSecret: donorWallet.secretKey,
        campaignAddress: campaign.stellarAddress,
        campaignId,
        donorId
      });

      // Submit the transaction
      const result = await this.stellarBuilder.submitTransaction(transaction);

      if (!result.success) {
        throw new Error(`Failed to submit cancellation transaction: ${result.error}`);
      }

      // Update donor record
      await this.db.collection('donors').updateOne(
        { _id: donorId },
        {
          $set: {
            'recurringDetails.status': 'cancelled',
            'recurringDetails.cancelledDate': new Date(),
            'recurringDetails.cancelledBy': userId
          }
        }
      );

      // Create cancellation record
      const cancellationRecord = {
        donorId,
        campaignId,
        userId,
        transactionHash: result.result.hash,
        date: new Date(),
        previousSettings: donor.recurringDetails
      };

      await this.db.collection('recurringCancellations').insertOne(cancellationRecord);

      // Create notification
      const notification = {
        userId: donorId,
        type: 'recurring_cancelled',
        title: 'Recurring Donation Cancelled',
        message: `Your recurring donation to "${campaign.title}" has been cancelled`,
        data: {
          campaignId,
          campaignTitle: campaign.title
        },
        read: false,
        created: new Date()
      };

      await this.db.collection('notifications').insertOne(notification);

      return {
        success: true,
        transactionHash: result.result.hash
      };
    } catch (error) {
      console.error('Error cancelling recurring donation:', error);
      return {
        success: false,
        error: error.message
      };
    }
  }

  /**
   * Get transaction history for a user
   * @param {string} userId - User ID
   * @param {Object} options - Pagination and filter options
   * @returns {Promise<Object>} - Transaction history
   */
  async getUserTransactionHistory(userId, options = {}) {
    try {
      const query = { userId };

      // Apply type filter
      if (options.type) {
        query.type = options.type;
      }

      // Apply status filter
      if (options.status) {
        query.status = options.status;
      }

      // Set up pagination
      const page = options.page || 1;
      const limit = options.limit || 10;
      const skip = (page - 1) * limit;

      // Get transactions with pagination
      const transactions = await this.db.collection('donations')
        .find(query)
        .sort({ created: -1 })
        .skip(skip)
        .limit(limit)
        .toArray();

      // Get total count
      const total = await this.db.collection('donations').countDocuments(query);

      // Enrich with campaign data
      for (const tx of transactions) {
        if (tx.campaignId) {
          const campaign = await this.db.collection('campaigns').findOne(
            { _id: tx.campaignId },
            { projection: { title: 1, image: 1 } }
          );

          if (campaign) {
            tx.campaign = {
              title: campaign.title,
              image: campaign.image
            };
          }
        }
      }

      return {
        success: true,
        transactions,
        pagination: {
          page,
          limit,
          total,
          pages: Math.ceil(total / limit)
        }
      };
    } catch (error) {
      console.error('Error fetching user transactions:', error);
      return {
        success: false,
        error: error.message
      };
    }
  }

  /**
   * Get transaction history for a campaign
   * @param {string} campaignId - Campaign ID
   * @param {Object} options - Pagination and filter options
   * @returns {Promise<Object>} - Transaction history
   */
  async getCampaignTransactionHistory(campaignId, options = {}) {
    try {
      const query = { campaignId };

      // Apply type filter
      if (options.type) {
        query.type = options.type;
      }

      // Apply status filter
      if (options.status) {
        query.status = options.status;
      }

      // Handle anonymous donations
      if (options.includeAnonymous === false) {
        query.visibility = { $ne: 'anonymous' };
      }

      // Set up pagination
      const page = options.page || 1;
      const limit = options.limit || 10;
      const skip = (page - 1) * limit;

      // Get transactions with pagination
      const transactions = await this.db.collection('donations')
        .find(query)
        .sort({ created: -1 })
        .skip(skip)
        .limit(limit)
        .toArray();

      // Get total count
      const total = await this.db.collection('donations').countDocuments(query);

      // Enrich with donor data (for non-anonymous donations)
      for (const tx of transactions) {
        if (tx.userId && tx.visibility !== 'anonymous') {
          const donor = await this.db.collection('donors').findOne(
            { _id: tx.userId },
            { projection: { name: 1, profile: 1 } }
          );

          if (donor) {
            tx.donor = {
              name: donor.name,
              profile: donor.profile
            };
          }
        }
      }

      return {
        success: true,
        transactions,
        pagination: {
          page,
          limit,
          total,
          pages: Math.ceil(total / limit)
        }
      };
    } catch (error) {
      console.error('Error fetching campaign transactions:', error);
      return {
        success: false,
        error: error.message
      };
    }
  }
}

export default TransactionService;
