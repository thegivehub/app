<?php
require_once 'config.php';

class DeveloperRegistration {
    private $db;
    
    public function __construct() {
        $this->db = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
            DB_USER,
            DB_PASS
        );
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    
    public function register($data) {
        try {
            // Validate input
            $errors = $this->validateInput($data);
            if (!empty($errors)) {
                return [
                    'success' => false,
                    'errors' => $errors
                ];
            }
            
            // Start transaction
            $this->db->beginTransaction();
            
            // Create developer account
            $accountId = $this->createAccount($data);
            
            // Generate and store API keys
            $apiKeys = $this->generateApiKeys($accountId);
            
            // Store integration types
            $this->storeIntegrationTypes($accountId, $data['integrationType']);
            
            // Log the registration
            $this->logRegistration($accountId, $data);
            
            // Notify admins
            $this->notifyAdmins($data);
            
            // Commit transaction
            $this->db->commit();
            
            // Send welcome email
            $this->sendWelcomeEmail($data['email'], $data['firstName']);
            
            return [
                'success' => true,
                'apiKey' => $apiKeys['api_key'],
                'apiSecret' => $apiKeys['api_secret']
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Developer registration failed: " . $e->getMessage());
            return [
                'success' => false,
                'errors' => ['general' => 'Registration failed. Please try again.']
            ];
        }
    }
    
    private function validateInput($data) {
        $errors = [];
        
        if (empty($data['firstName'])) {
            $errors['firstName'] = 'First name is required';
        }
        
        if (empty($data['lastName'])) {
            $errors['lastName'] = 'Last name is required';
        }
        
        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Valid email is required';
        }
        
        if (empty($data['password']) || strlen($data['password']) < 8) {
            $errors['password'] = 'Password must be at least 8 characters';
        }
        
        if (empty($data['orgName'])) {
            $errors['orgName'] = 'Organization name is required';
        }
        
        if (!empty($data['website']) && !filter_var($data['website'], FILTER_VALIDATE_URL)) {
            $errors['website'] = 'Invalid website URL';
        }
        
        if (empty($data['appDescription'])) {
            $errors['appDescription'] = 'Application description is required';
        }

        // Check password strength
        if (!empty($data['password'])) {
            if (!preg_match('/[A-Z]/', $data['password'])) {
                $errors['password'] = 'Password must contain at least one uppercase letter';
            }
            if (!preg_match('/[0-9]/', $data['password'])) {
                $errors['password'] = 'Password must contain at least one number';
            }
            if (!preg_match('/[^A-Za-z0-9]/', $data['password'])) {
                $errors['password'] = 'Password must contain at least one special character';
            }
        }
        
        // Check if email already exists
        $stmt = $this->db->prepare("SELECT id FROM developer_accounts WHERE email = ?");
        $stmt->execute([$data['email']]);
        if ($stmt->fetch()) {
            $errors['email'] = 'Email already registered';
        }
        
        return $errors;
    }
    
    private function createAccount($data) {
        $stmt = $this->db->prepare("
            INSERT INTO developer_accounts 
            (first_name, last_name, email, password_hash, org_name, website, app_description, api_usage, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        
        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
        
        $stmt->execute([
            $data['firstName'],
            $data['lastName'],
            $data['email'],
            $passwordHash,
            $data['orgName'],
            $data['website'] ?? null,
            $data['appDescription'],
            $data['apiUsage']
        ]);
        
        return $this->db->lastInsertId();
    }
    
    private function generateApiKeys($accountId) {
        // Generate a secure API key using various entropy sources
        $apiKey = $this->generateSecureKey('gh_live_');
        $apiSecret = $this->generateSecureKey('');  // No prefix for secret
        
        $stmt = $this->db->prepare("
            INSERT INTO developer_api_keys 
            (developer_id, api_key, api_secret, name, environment)
            VALUES (?, ?, ?, 'Default Key', 'sandbox')
        ");
        
        $stmt->execute([$accountId, $apiKey, $apiSecret]);
        
        return [
            'api_key' => $apiKey,
            'api_secret' => $apiSecret
        ];
    }
    
    private function generateSecureKey($prefix = '') {
        // Combine multiple sources of entropy
        $entropy = random_bytes(32);
        $entropy .= uniqid(mt_rand(), true);
        $entropy .= microtime();
        
        // Create a hash and format it
        $hash = hash('sha256', $entropy);
        $key = substr($hash, 0, 32);  // Take first 32 chars
        
        return $prefix . $this->formatKey($key);
    }
    
    private function formatKey($key) {
        // Format the key in groups of 8 characters
        $groups = str_split($key, 8);
        return implode('', $groups);
    }
    
    private function storeIntegrationTypes($accountId, $types) {
        if (!is_array($types)) {
            $types = [$types];
        }
        
        $stmt = $this->db->prepare("
            INSERT INTO developer_integration_types 
            (developer_id, integration_type)
            VALUES (?, ?)
        ");
        
        foreach ($types as $type) {
            if (in_array($type, ['web', 'mobile', 'server'])) {
                $stmt->execute([$accountId, $type]);
            }
        }
    }
    
    private function sendWelcomeEmail($email, $firstName) {
        $subject = "Welcome to The Give Hub Developer Platform";
        
        $message = "
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: #0d6efd; color: white; padding: 20px; text-align: center; }
                    .content { padding: 20px; }
                    .button { 
                        display: inline-block;
                        padding: 10px 20px;
                        background: #0d6efd;
                        color: white;
                        text-decoration: none;
                        border-radius: 5px;
                        margin: 20px 0;
                    }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>Welcome to The Give Hub!</h1>
                    </div>
                    <div class='content'>
                        <h2>Hi $firstName,</h2>
                        <p>Thank you for registering as a developer on The Give Hub platform. We're excited to see what you'll build!</p>
                        
                        <h3>Next Steps:</h3>
                        <ol>
                            <li>Complete your developer profile</li>
                            <li>Review our API documentation</li>
                            <li>Try our interactive API explorer</li>
                            <li>Join our developer community</li>
                        </ol>
                        
                        <p>Your account is currently under review, and we'll notify you once it's been activated.</p>
                        
                        <p>In the meantime, you can:</p>
                        <ul>
                            <li>Review our <a href='" . API_DOCS_URL . "'>API documentation</a></li>
                            <li>Join our <a href='https://discord.gg/givehub'>developer community</a></li>
                            <li>Check out example projects on <a href='https://github.com/thegivehub'>GitHub</a></li>
                        </ul>
                        
                        <a href='" . DEV_PORTAL_URL . "/getting-started' class='button'>Get Started</a>
                        
                        <p>If you have any questions, our developer support team is here to help!</p>
                        
                        <p>Best regards,<br>The Give Hub Team</p>
                    </div>
                </div>
            </body>
            </html>
        ";
        
        // Headers for HTML email
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=utf-8',
            'From: The Give Hub Developer Team <developers@givehub.org>',
            'Reply-To: developer-support@givehub.org',
            'X-Mailer: PHP/' . phpversion()
        ];
        
        // Send email
        return mail($email, $subject, $message, implode("\r\n", $headers));
    }
    
    private function logRegistration($accountId, $data) {
        $stmt = $this->db->prepare("
            INSERT INTO developer_registration_logs 
            (developer_id, ip_address, user_agent, timestamp)
            VALUES (?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $accountId,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    }
    
    private function notifyAdmins($data) {
        // Send notification to admin dashboard and optionally to Slack/Discord
        if (defined('SLACK_WEBHOOK_URL') && SLACK_WEBHOOK_URL) {
            $this->sendSlackNotification($data);
        }
        
        if (defined('DISCORD_WEBHOOK_URL') && DISCORD_WEBHOOK_URL) {
            $this->sendDiscordNotification($data);
        }
    }
    
    private function sendSlackNotification($data) {
        $message = [
            'text' => "New Developer Registration",
            'blocks' => [
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => "*New Developer Registration*\n" .
                                "Name: {$data['firstName']} {$data['lastName']}\n" .
                                "Organization: {$data['orgName']}\n" .
                                "Email: {$data['email']}"
                    ]
                ]
            ]
        ];
        
        $ch = curl_init(SLACK_WEBHOOK_URL);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_exec($ch);
        curl_close($ch);
    }
    
    private function sendDiscordNotification($data) {
        $message = [
            'content' => "New Developer Registration",
            'embeds' => [
                [
                    'title' => 'New Developer Registration',
                    'color' => 3447003,  // Blue color
                    'fields' => [
                        [
                            'name' => 'Developer',
                            'value' => "{$data['firstName']} {$data['lastName']}",
                            'inline' => true
                        ],
                        [
                            'name' => 'Organization',
                            'value' => $data['orgName'],
                            'inline' => true
                        ],
                        [
                            'name' => 'Email',
                            'value' => $data['email'],
                            'inline' => false
                        ]
                    ],
                    'timestamp' => date('c')
                ]
            ]
        ];
        
        $ch = curl_init(DISCORD_WEBHOOK_URL);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_exec($ch);
        curl_close($ch);
    }
}
