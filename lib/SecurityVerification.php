<?php
/**
 * SecurityVerification - Comprehensive security verification for mainnet deployment
 */
class SecurityVerification {
    private $db;
    private $logger;
    private $securityChecks;

    public function __construct() {
        $this->db = new Database();
        $this->logger = new Logger('security_verification');
        $this->initializeSecurityChecks();
    }

    private function initializeSecurityChecks() {
        $this->securityChecks = [
            'authentication' => [
                'name' => 'Authentication Security',
                'checks' => [
                    'jwt_secret_strength' => 'JWT secret key strength',
                    'password_hashing' => 'Password hashing verification',
                    'session_security' => 'Session configuration',
                    'csrf_protection' => 'CSRF protection enabled',
                    'rate_limiting' => 'Rate limiting active'
                ]
            ],
            'encryption' => [
                'name' => 'Encryption & Data Protection',
                'checks' => [
                    'ssl_configuration' => 'SSL/TLS configuration',
                    'database_encryption' => 'Database encryption at rest',
                    'api_encryption' => 'API communication encryption',
                    'private_key_security' => 'Private key protection',
                    'sensitive_data_handling' => 'Sensitive data handling'
                ]
            ],
            'blockchain' => [
                'name' => 'Blockchain Security',
                'checks' => [
                    'wallet_security' => 'Wallet security measures',
                    'transaction_signing' => 'Transaction signing security',
                    'multi_signature' => 'Multi-signature implementation',
                    'network_security' => 'Network connection security',
                    'contract_security' => 'Smart contract security'
                ]
            ],
            'infrastructure' => [
                'name' => 'Infrastructure Security',
                'checks' => [
                    'file_permissions' => 'File system permissions',
                    'directory_security' => 'Directory access control',
                    'log_security' => 'Log file security',
                    'backup_security' => 'Backup encryption',
                    'dependency_security' => 'Dependency vulnerabilities'
                ]
            ],
            'compliance' => [
                'name' => 'Compliance & Monitoring',
                'checks' => [
                    'kyc_security' => 'KYC data protection',
                    'audit_logging' => 'Audit trail completeness',
                    'data_retention' => 'Data retention policies',
                    'incident_response' => 'Incident response procedures',
                    'monitoring_systems' => 'Security monitoring systems'
                ]
            ]
        ];
    }

    /**
     * Perform comprehensive security verification
     * @return array Complete security verification results
     */
    public function performVerification() {
        $this->logger->info("Starting comprehensive security verification");
        
        $verificationResults = [
            'timestamp' => date('Y-m-d H:i:s'),
            'overall_status' => 'pending',
            'security_score' => 0,
            'categories' => [],
            'critical_issues' => [],
            'recommendations' => [],
            'summary' => []
        ];

        $totalChecks = 0;
        $passedChecks = 0;
        $criticalIssues = [];

        foreach ($this->securityChecks as $categoryKey => $category) {
            $this->logger->info("Verifying category: {$category['name']}");
            
            $categoryResults = [
                'name' => $category['name'],
                'status' => 'pending',
                'score' => 0,
                'checks' => [],
                'issues' => [],
                'recommendations' => []
            ];

            $categoryPassed = 0;
            $categoryTotal = count($category['checks']);

            foreach ($category['checks'] as $checkKey => $checkName) {
                $totalChecks++;
                
                try {
                    $checkResult = $this->performSecurityCheck($categoryKey, $checkKey);
                    
                    $categoryResults['checks'][$checkKey] = [
                        'name' => $checkName,
                        'status' => $checkResult['status'],
                        'message' => $checkResult['message'],
                        'details' => $checkResult['details'] ?? null,
                        'severity' => $checkResult['severity'] ?? 'medium'
                    ];

                    if ($checkResult['status'] === 'pass') {
                        $passedChecks++;
                        $categoryPassed++;
                    } else {
                        if ($checkResult['severity'] === 'critical') {
                            $criticalIssues[] = [
                                'category' => $category['name'],
                                'check' => $checkName,
                                'issue' => $checkResult['message']
                            ];
                        }
                        
                        $categoryResults['issues'][] = $checkResult['message'];
                        
                        if (!empty($checkResult['recommendation'])) {
                            $categoryResults['recommendations'][] = $checkResult['recommendation'];
                        }
                    }

                } catch (Exception $e) {
                    $this->logger->error("Security check failed: $checkKey - " . $e->getMessage());
                    
                    $categoryResults['checks'][$checkKey] = [
                        'name' => $checkName,
                        'status' => 'error',
                        'message' => 'Check failed: ' . $e->getMessage(),
                        'severity' => 'high'
                    ];
                }
            }

            $categoryResults['score'] = round(($categoryPassed / $categoryTotal) * 100, 2);
            $categoryResults['status'] = $categoryPassed === $categoryTotal ? 'pass' : 'fail';
            
            $verificationResults['categories'][$categoryKey] = $categoryResults;
        }

        // Calculate overall security score
        $verificationResults['security_score'] = round(($passedChecks / $totalChecks) * 100, 2);
        $verificationResults['overall_status'] = $passedChecks === $totalChecks ? 'pass' : 'fail';
        $verificationResults['critical_issues'] = $criticalIssues;

        // Generate summary
        $verificationResults['summary'] = [
            'total_checks' => $totalChecks,
            'passed_checks' => $passedChecks,
            'failed_checks' => $totalChecks - $passedChecks,
            'critical_issues_count' => count($criticalIssues),
            'security_level' => $this->getSecurityLevel($verificationResults['security_score'])
        ];

        // Generate recommendations
        $verificationResults['recommendations'] = $this->generateRecommendations($verificationResults);

        $this->logger->info("Security verification completed. Score: {$verificationResults['security_score']}%");
        
        // Save results to database
        $this->saveVerificationResults($verificationResults);

        return $verificationResults;
    }

    private function performSecurityCheck($category, $check) {
        $methodName = "check" . ucfirst($category) . ucfirst(str_replace('_', '', ucwords($check, '_')));
        
        if (method_exists($this, $methodName)) {
            return $this->$methodName();
        }

        // Default implementation for undefined checks
        return [
            'status' => 'warning',
            'message' => 'Security check not implemented',
            'severity' => 'medium',
            'recommendation' => 'Implement security check for ' . $check
        ];
    }

    // Authentication Security Checks
    private function checkAuthenticationJwtSecretStrength() {
        $auth = new Auth();
        $secret = $auth->getJwtSecret();
        
        if (strlen($secret) < 32) {
            return [
                'status' => 'fail',
                'message' => 'JWT secret key is too short (minimum 32 characters)',
                'severity' => 'critical',
                'recommendation' => 'Generate a stronger JWT secret key with at least 64 characters'
            ];
        }

        if (preg_match('/^[0-9A-F]+$/i', $secret) && strlen($secret) >= 64) {
            return [
                'status' => 'pass',
                'message' => 'JWT secret key meets security requirements',
                'details' => 'Key length: ' . strlen($secret) . ' characters'
            ];
        }

        return [
            'status' => 'warning',
            'message' => 'JWT secret key should be stronger',
            'severity' => 'medium',
            'recommendation' => 'Use a cryptographically secure random key'
        ];
    }

    private function checkAuthenticationPasswordHashing() {
        // Test password hashing functionality
        $testPassword = 'TestPassword123!';
        $hash = password_hash($testPassword, PASSWORD_DEFAULT);
        
        if (password_verify($testPassword, $hash)) {
            return [
                'status' => 'pass',
                'message' => 'Password hashing is working correctly',
                'details' => 'Using PHP password_hash with DEFAULT algorithm'
            ];
        }

        return [
            'status' => 'fail',
            'message' => 'Password hashing verification failed',
            'severity' => 'critical'
        ];
    }

    private function checkAuthenticationCsrfProtection() {
        if (file_exists(__DIR__ . '/../csrf_token.php')) {
            return [
                'status' => 'pass',
                'message' => 'CSRF protection is implemented',
                'details' => 'CSRF token endpoint available'
            ];
        }

        return [
            'status' => 'fail',
            'message' => 'CSRF protection not found',
            'severity' => 'high'
        ];
    }

    private function checkAuthenticationRateLimiting() {
        if (class_exists('Security') && method_exists('Security', 'rateLimit')) {
            return [
                'status' => 'pass',
                'message' => 'Rate limiting is implemented',
                'details' => 'Security::rateLimit method available'
            ];
        }

        return [
            'status' => 'fail',
            'message' => 'Rate limiting not implemented',
            'severity' => 'high'
        ];
    }

    // Encryption Security Checks
    private function checkEncryptionSslConfiguration() {
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            return [
                'status' => 'pass',
                'message' => 'SSL/HTTPS is enabled',
                'details' => 'Secure connection detected'
            ];
        }

        return [
            'status' => 'warning',
            'message' => 'SSL/HTTPS not detected',
            'severity' => 'high',
            'recommendation' => 'Enable HTTPS for all communications'
        ];
    }

    private function checkEncryptionPrivateKeySecurity() {
        $envFile = __DIR__ . '/../.env';
        if (!file_exists($envFile)) {
            return [
                'status' => 'fail',
                'message' => 'Environment file not found',
                'severity' => 'critical'
            ];
        }

        $envContent = file_get_contents($envFile);
        $permissions = substr(sprintf('%o', fileperms($envFile)), -4);
        
        if ($permissions !== '0600') {
            return [
                'status' => 'fail',
                'message' => "Environment file permissions too permissive: $permissions",
                'severity' => 'critical',
                'recommendation' => 'Set .env file permissions to 0600'
            ];
        }

        return [
            'status' => 'pass',
            'message' => 'Environment file permissions are secure',
            'details' => 'Permissions: ' . $permissions
        ];
    }

    // Blockchain Security Checks
    private function checkBlockchainWalletSecurity() {
        $wallets = $this->db->getCollection('users')->find([
            'blockchain.wallet_address' => ['$exists' => true]
        ]);

        $secureWallets = 0;
        $totalWallets = 0;

        foreach ($wallets as $wallet) {
            $totalWallets++;
            
            // Check if private key is properly encrypted/protected
            if (!isset($wallet['blockchain']['private_key']) || 
                (isset($wallet['blockchain']['encrypted']) && $wallet['blockchain']['encrypted'])) {
                $secureWallets++;
            }
        }

        if ($totalWallets === 0) {
            return [
                'status' => 'pass',
                'message' => 'No wallets found to verify'
            ];
        }

        $securityRatio = $secureWallets / $totalWallets;

        if ($securityRatio === 1.0) {
            return [
                'status' => 'pass',
                'message' => 'All wallets are properly secured',
                'details' => "$secureWallets/$totalWallets wallets secure"
            ];
        }

        return [
            'status' => 'fail',
            'message' => 'Some wallets may have security issues',
            'severity' => 'critical',
            'details' => "$secureWallets/$totalWallets wallets secure"
        ];
    }

    // Infrastructure Security Checks
    private function checkInfrastructureFilePermissions() {
        $criticalFiles = [
            __DIR__ . '/../.env',
            __DIR__ . '/../config/',
            __DIR__ . '/../lib/',
            __DIR__ . '/../logs/'
        ];

        $issues = [];

        foreach ($criticalFiles as $file) {
            if (!file_exists($file)) {
                continue;
            }

            $perms = substr(sprintf('%o', fileperms($file)), -4);
            
            // Check if files are world-readable or writable
            if (in_array(substr($perms, -1), ['2', '3', '6', '7'])) {
                $issues[] = "$file has permissions $perms (world-writable)";
            } elseif (in_array(substr($perms, -2, 1), ['2', '3', '6', '7'])) {
                $issues[] = "$file has permissions $perms (group-writable)";
            }
        }

        if (empty($issues)) {
            return [
                'status' => 'pass',
                'message' => 'File permissions are secure'
            ];
        }

        return [
            'status' => 'fail',
            'message' => 'Insecure file permissions detected',
            'severity' => 'high',
            'details' => $issues
        ];
    }

    private function checkInfrastructureDependencySecurity() {
        $composerFile = __DIR__ . '/../composer.json';
        if (!file_exists($composerFile)) {
            return [
                'status' => 'warning',
                'message' => 'composer.json not found',
                'severity' => 'medium'
            ];
        }

        // Check for known vulnerable packages (simplified check)
        $composerContent = file_get_contents($composerFile);
        $composer = json_decode($composerContent, true);

        $vulnerablePackages = [
            'monolog/monolog' => '< 1.25.2',
            'symfony/http-foundation' => '< 4.4.7'
        ];

        $issues = [];
        foreach ($vulnerablePackages as $package => $version) {
            if (isset($composer['require'][$package])) {
                $issues[] = "Potentially vulnerable package: $package";
            }
        }

        if (empty($issues)) {
            return [
                'status' => 'pass',
                'message' => 'No known vulnerable dependencies detected'
            ];
        }

        return [
            'status' => 'warning',
            'message' => 'Potential dependency vulnerabilities',
            'severity' => 'medium',
            'details' => $issues
        ];
    }

    // Compliance Security Checks
    private function checkComplianceKycSecurity() {
        if (class_exists('KycController')) {
            return [
                'status' => 'pass',
                'message' => 'KYC system is implemented',
                'details' => 'KycController class available'
            ];
        }

        return [
            'status' => 'fail',
            'message' => 'KYC system not found',
            'severity' => 'high'
        ];
    }

    private function checkComplianceAuditLogging() {
        $logDir = __DIR__ . '/../logs/';
        if (!is_dir($logDir)) {
            return [
                'status' => 'fail',
                'message' => 'Log directory not found',
                'severity' => 'high'
            ];
        }

        $logFiles = glob($logDir . '*.log');
        if (empty($logFiles)) {
            return [
                'status' => 'warning',
                'message' => 'No log files found',
                'severity' => 'medium'
            ];
        }

        return [
            'status' => 'pass',
            'message' => 'Audit logging is active',
            'details' => count($logFiles) . ' log files found'
        ];
    }

    private function getSecurityLevel($score) {
        if ($score >= 90) return 'Excellent';
        if ($score >= 80) return 'Good';
        if ($score >= 70) return 'Acceptable';
        if ($score >= 60) return 'Needs Improvement';
        return 'Critical';
    }

    private function generateRecommendations($results) {
        $recommendations = [];

        if ($results['security_score'] < 80) {
            $recommendations[] = 'Overall security score is below recommended threshold (80%)';
        }

        if (!empty($results['critical_issues'])) {
            $recommendations[] = 'Address all critical security issues before mainnet deployment';
        }

        foreach ($results['categories'] as $category) {
            if ($category['score'] < 90) {
                $recommendations[] = "Improve security in category: {$category['name']}";
            }
            
            $recommendations = array_merge($recommendations, $category['recommendations']);
        }

        return array_unique($recommendations);
    }

    private function saveVerificationResults($results) {
        try {
            $this->db->getCollection('security_verifications')->insertOne([
                'results' => $results,
                'timestamp' => new MongoDB\BSON\UTCDateTime(),
                'version' => '1.0'
            ]);
        } catch (Exception $e) {
            $this->logger->error("Failed to save verification results: " . $e->getMessage());
        }
    }

    /**
     * Get latest security verification status
     * @return array Latest verification results
     */
    public function getStatus() {
        $latest = $this->db->getCollection('security_verifications')
            ->findOne([], ['sort' => ['timestamp' => -1]]);

        if (!$latest) {
            return [
                'success' => false,
                'error' => 'No security verification found',
                'recommendation' => 'Run security verification first'
            ];
        }

        return [
            'success' => true,
            'last_verification' => $latest['timestamp']->toDateTime()->format('Y-m-d H:i:s'),
            'security_score' => $latest['results']['security_score'],
            'overall_status' => $latest['results']['overall_status'],
            'critical_issues' => count($latest['results']['critical_issues']),
            'artifacts' => [
                'verification_class' => '/lib/SecurityVerification.php',
                'api_endpoint' => '/api.php/SecurityVerification/performVerification',
                'status_endpoint' => '/api.php/SecurityVerification/getStatus',
                'security_logs' => '/logs/security.log',
                'verification_collection' => 'security_verifications'
            ]
        ];
    }
}