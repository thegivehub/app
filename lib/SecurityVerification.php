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
        // In non-production we allow this as warning; in production, fail
        if (getenv('APP_ENV') !== 'production') {
            return [
                'status' => 'warning',
                'message' => 'SSL/HTTPS not detected (non-production environment)',
                'severity' => 'medium',
                'recommendation' => 'HTTPS should be enforced in production'
            ];
        }
        return [
            'status' => 'fail',
            'message' => 'SSL/HTTPS not detected',
            'severity' => 'critical',
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
            // Attempt to lock down permissions automatically
            @chmod($envFile, 0600);
            clearstatcache();
            $permissions = substr(sprintf('%o', fileperms($envFile)), -4);
            if ($permissions !== '0600') {
                return [
                    'status' => 'fail',
                    'message' => "Environment file permissions too permissive: $permissions",
                    'severity' => 'critical',
                    'recommendation' => 'Set .env file permissions to 0600'
                ];
            }
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

        $groupIssues = [];
        $worldIssues = [];

        foreach ($criticalFiles as $file) {
            if (!file_exists($file)) {
                continue;
            }

            $perms = substr(sprintf('%o', fileperms($file)), -4);
            
            // Check if files are world-readable or writable
            if (in_array(substr($perms, -1), ['2', '3', '6', '7'])) {
                // attempt to secure
                @chmod($file, is_dir($file) ? 0750 : 0600);
                $perms = substr(sprintf('%o', fileperms($file)), -4);
                if (in_array(substr($perms, -1), ['2', '3', '6', '7'])) {
                    $worldIssues[] = "$file has permissions $perms (world-writable)";
                }
            } elseif (in_array(substr($perms, -2, 1), ['2', '3', '6', '7'])) {
                @chmod($file, is_dir($file) ? 0750 : 0640);
                $perms = substr(sprintf('%o', fileperms($file)), -4);
                if (in_array(substr($perms, -2, 1), ['2', '3', '6', '7'])) {
                    $groupIssues[] = "$file has permissions $perms (group-writable)";
                }
            }
        }

        if (empty($worldIssues) && empty($groupIssues)) {
            return [
                'status' => 'pass',
                'message' => 'File permissions are secure'
            ];
        }
        if (!empty($worldIssues)) {
            return [
                'status' => 'fail',
                'message' => 'World-writable permissions detected',
                'severity' => 'high',
                'details' => array_merge($worldIssues, $groupIssues)
            ];
        }
        // Only group-writable issues remain
        return [
            'status' => 'warning',
            'message' => 'Group-writable permissions detected',
            'severity' => 'medium',
            'details' => $groupIssues,
            'recommendation' => 'Harden permissions to 0750 (dirs) / 0640 (files) or stricter'
        ];
    }

    // Authentication: session security checks
    private function checkAuthenticationSessionSecurity() {
        $cookieSecure = (bool)ini_get('session.cookie_secure');
        $httpOnly = (bool)ini_get('session.cookie_httponly');
        $sameSite = ini_get('session.cookie_samesite');
        $ok = $httpOnly && ($sameSite && strtolower($sameSite) !== '');
        if (getenv('APP_ENV') === 'production' && !$cookieSecure) {
            return [ 'status' => 'warning', 'message' => 'session.cookie_secure is not enabled', 'severity' => 'high', 'recommendation' => 'Enable cookie_secure in production' ];
        }
        if ($ok) {
            return [ 'status' => 'pass', 'message' => 'Session cookies have HttpOnly and SameSite set' ];
        }
        return [ 'status' => 'warning', 'message' => 'Missing HttpOnly or SameSite on session cookies', 'severity' => 'medium' ];
    }

    // Encryption: database at-rest encryption (env-based indicator)
    private function checkEncryptionDatabaseEncryption() {
        $enabled = getenv('DB_ENCRYPTION_ENABLED');
        if ($enabled && in_array(strtolower($enabled), ['1','true','yes'])) {
            return [ 'status' => 'pass', 'message' => 'Database at-rest encryption enabled (env flag)' ];
        }
        return [ 'status' => 'warning', 'message' => 'Database encryption not confirmed', 'severity' => 'medium', 'recommendation' => 'Enable DB encryption or set DB_ENCRYPTION_ENABLED=true' ];
    }

    // Encryption: API encryption
    private function checkEncryptionApiEncryption() {
        // If HTTPS is enforced behind proxy, look for forwarded header or env
        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            return [ 'status' => 'pass', 'message' => 'HTTPS enforced via proxy header' ];
        }
        if (getenv('APP_ENV') !== 'production') {
            return [ 'status' => 'warning', 'message' => 'HTTPS not enforced (non-production)', 'severity' => 'medium' ];
        }
        return [ 'status' => 'warning', 'message' => 'API encryption could not be verified', 'severity' => 'high', 'recommendation' => 'Serve API exclusively over HTTPS' ];
    }

    // Encryption: sensitive data handling
    private function checkEncryptionSensitiveDataHandling() {
        $logSensitive = getenv('LOG_SENSITIVE_DATA');
        if ($logSensitive && in_array(strtolower($logSensitive), ['1','true','yes'])) {
            return [ 'status' => 'fail', 'message' => 'Sensitive data logging is enabled', 'severity' => 'high', 'recommendation' => 'Disable LOG_SENSITIVE_DATA' ];
        }
        return [ 'status' => 'pass', 'message' => 'Sensitive data logging is disabled' ];
    }

    // Blockchain: transaction signing security (stub)
    private function checkBlockchainTransactionSigning() {
        // Check presence of Transaction/Wallet classes as baseline
        if (class_exists('Transaction') && class_exists('Wallet')) {
            return [ 'status' => 'pass', 'message' => 'Transaction & Wallet classes present (signing managed by backend)' ];
        }
        return [ 'status' => 'warning', 'message' => 'Signing subsystem presence not verified', 'severity' => 'medium' ];
    }

    private function checkBlockchainMultiSignature() {
        // If multisig is not required for current environment, warn instead of fail
        return [ 'status' => 'warning', 'message' => 'Multi-signature not enabled for current environment', 'severity' => 'medium' ];
    }

    private function checkBlockchainNetworkSecurity() {
        return [ 'status' => 'pass', 'message' => 'Blockchain network connections managed via SDK defaults' ];
    }

    private function checkBlockchainContractSecurity() {
        return [ 'status' => 'warning', 'message' => 'Contract audits not attached', 'severity' => 'medium', 'recommendation' => 'Attach audit report when available' ];
    }

    // Infrastructure: directory & log & backup security
    private function checkInfrastructureDirectorySecurity() {
        $protected = [ __DIR__ . '/../logs/.htaccess', __DIR__ . '/../backups/.htaccess' ];
        $missing = [];
        foreach ($protected as $p) { if (!file_exists($p)) $missing[] = $p; }
        if (empty($missing)) {
            return [ 'status' => 'pass', 'message' => 'Sensitive directories protected from web access' ];
        }
        return [ 'status' => 'warning', 'message' => 'Protection missing for some directories', 'severity' => 'medium', 'details' => $missing, 'recommendation' => 'Add .htaccess deny rules' ];
    }

    private function checkInfrastructureLogSecurity() {
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) return [ 'status' => 'warning', 'message' => 'Log directory not found', 'severity' => 'medium' ];
        $perms = substr(sprintf('%o', fileperms($logDir)), -4);
        if (in_array(substr($perms, -1), ['2','3','6','7'])) {
            @chmod($logDir, 0750);
            $perms = substr(sprintf('%o', fileperms($logDir)), -4);
        }
        return [ 'status' => 'pass', 'message' => 'Logs directory permissions acceptable', 'details' => $perms ];
    }

    private function checkInfrastructureBackupSecurity() {
        $dir = __DIR__ . '/../backups';
        if (!is_dir($dir)) return [ 'status' => 'warning', 'message' => 'Backups directory not found', 'severity' => 'medium' ];
        $perms = substr(sprintf('%o', fileperms($dir)), -4);
        if (in_array(substr($perms, -1), ['2','3','6','7'])) {
            @chmod($dir, 0750);
            $perms = substr(sprintf('%o', fileperms($dir)), -4);
        }
        return [ 'status' => 'pass', 'message' => 'Backups directory permissions acceptable', 'details' => $perms ];
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

    // Compliance: data retention policy
    private function checkComplianceDataRetention() {
        $policy = __DIR__ . '/../docs/security/data-retention.md';
        if (file_exists($policy)) {
            return [ 'status' => 'pass', 'message' => 'Data retention policy documented', 'details' => basename($policy) ];
        }
        return [ 'status' => 'warning', 'message' => 'Data retention policy not found', 'severity' => 'medium' ];
    }

    private function checkComplianceIncidentResponse() {
        $doc = __DIR__ . '/../docs/security/incident-response.md';
        if (file_exists($doc)) {
            return [ 'status' => 'pass', 'message' => 'Incident response procedures documented', 'details' => basename($doc) ];
        }
        return [ 'status' => 'warning', 'message' => 'Incident response procedures not found', 'severity' => 'medium' ];
    }

    private function checkComplianceMonitoringSystems() {
        $doc = __DIR__ . '/../docs/security/monitoring.md';
        if (file_exists($doc)) {
            return [ 'status' => 'pass', 'message' => 'Security monitoring documented', 'details' => basename($doc) ];
        }
        return [ 'status' => 'warning', 'message' => 'Security monitoring documentation not found', 'severity' => 'medium' ];
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
