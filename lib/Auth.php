<?php
// lib/Auth.php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

require_once "db.php";
require_once "Mailer.php";

class Auth {
    public $db;
    private $mail;
    public $config;

    public function __construct() {
        $this->db = new Database();
        $this->db->users = $this->db->getCollection('users');
        $this->mail = new Mailer();
        
        $this->config = [
            'jwt_secret' => '6ABD1CF21B5743C99A283D9184AB6F1A15E8FC1F141C749E39B49B6FD3E9D705',
            'jwt_expire' => 3600 * 24,
            'verification_expire' => 3600,
            'upload_dir' => __DIR__ . '/../img/avatars',
            'avatar_max_size' => 5 * 1024 * 1024
        ];
    }

    public function register($data) {
        try {
            // Debug incoming data
            error_log("Registration data: " . print_r($data, true));

            // Validate required fields
            $requiredFields = ['email', 'username', 'password'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field])) {
                    throw new Exception("Missing required field: $field");
                }
            }

            // Check for existing user
            $existingUser = $this->db->users->findOne([
                'email' => $data['email']
            ]);

            // Generate verification code
            $verificationCode = random_int(100000, 999999);
            $verificationExpires = new MongoDB\BSON\UTCDateTime((time() + $this->config['verification_expire']) * 1000);

            // Structure user document
            $userData = [
                'email' => $data['email'],
                'username' => $data['username'],
                'type' => $data['type'] ?? 'donor',
                'status' => 'pending',
                'personalInfo' => [
                    'firstName' => $data['firstName'] ?? '',
                    'lastName' => $data['lastName'] ?? '',
                    'email' => $data['email'],
                    'language' => $data['personalInfo']['language'] ?? 'en'
                ],
                'auth' => [
                    'passwordHash' => password_hash($data['password'], PASSWORD_DEFAULT),
                    'verificationCode' => $verificationCode,
                    'verificationExpires' => $verificationExpires,
                    'verified' => false,
                    'twoFactorEnabled' => false,
                    'lastLogin' => new MongoDB\BSON\UTCDateTime()
                ],
                'profile' => array_merge([
                    'avatar' => null,
                    'bio' => '',
                    'preferences' => [
                        'emailNotifications' => true,
                        'currency' => 'USD'
                    ]
                ], $data['profile'] ?? []),
                'roles' => ['user'],
                'updated' => new MongoDB\BSON\UTCDateTime()
            ];

            if ($existingUser) {
                // If user exists but isn't verified, update their info
                if ($existingUser['status'] === 'pending' || !isset($existingUser['auth']['verified']) || !$existingUser['auth']['verified']) {
                    $result = $this->db->users->updateOne(
                        ['email' => $data['email']],
                        [
                            '$set' => $userData,
                            '$setOnInsert' => [
                                'created' => new MongoDB\BSON\UTCDateTime()
                            ]
                        ],
                        ['upsert' => true]
                    );

                    if (!$result['success']) {
                        throw new Exception($result['error'] ?? 'Failed to update user');
                    }

                    $userId = $existingUser['_id'];
                } else {
                    throw new Exception('Email already registered');
                }
            } else {
                // New user
                $userData['created'] = new MongoDB\BSON\UTCDateTime();
                $result = $this->db->users->insertOne($userData);

                if (!$result['success']) {
                    throw new Exception($result['error'] ?? 'Failed to create user');
                }

                $userId = $result['id'];
            }

            // Send verification email
            $this->mail->sendVerification($data['email'], $verificationCode);

            return [
                'success' => true,
                'message' => 'Registration successful. Please check your email for verification code.',
                'userId' => $userId
            ];

        } catch (Exception $e) {
            error_log("Registration error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function sendVerification($data) {
        try {
            $verificationCode = random_int(100000, 999999);
            $verificationExpires = new MongoDB\BSON\UTCDateTime((time() + $this->config['verification_expire']) * 1000);
            

            $updateData = [
                'email' => $data['email'],
                'personalInfo.email' => $data['email'],
                'auth.verificationCode' => $verificationCode,
                'auth.verificationExpires' => $verificationExpires,
                'auth.verified' => false,
                'auth.twoFactorEnabled' => false
            ];

            file_put_contents("verify.log", date("Ymdhis") . "\nCREATING NEW RECORD\n-------\n".json_encode($userData)."\n", FILE_APPEND);

            $result = $this->db->users->updateOne(
                ['email' => $data['email']],
                [
                    '$set' => $updateData,
                    '$setOnInsert' => [
                        'status' => 'pending',
                        'created' => new MongoDB\BSON\UTCDateTime(),
                        'roles' => ['user']
                    ]
                ],
                ['upsert' => true]
            );

            if (!$result['success']) {
                throw new Exception($result['error'] ?? 'Failed to process verification');
            }

            $this->mail->sendVerification($data['email'], $verificationCode);
            
            return [
                'success' => true,
                'message' => 'Verification email sent successfully. Please check your email for verification code.'
            ];
        } catch (Exception $e) {
            error_log("Send verification error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function requestVerification($data) {
        return [ 'success' => true, 'message' => 'Email verified successfully' ];
        try {
            if (!isset($data['email']) || !isset($data['code'])) {
                throw new Exception('Email and verification code required');
            }

            // Find user with matching code
            $user = $this->db->users->findOne([
                'email' => $data['email'],
                'auth.verificationCode' => (int)$data['code'],
                'auth.verificationExpires' => [
                    '$gt' => new MongoDB\BSON\UTCDateTime()
                ]
            ]);

            if (!$user) {
                throw new Exception('Invalid or expired verification code');
            }

            // Update user status
            $result = $this->db->users->updateOne(
                ['_id' => $user['_id']],
                [
                    '$set' => [
                        'status' => 'active',
                        'auth.verified' => true
                    ],
                    '$unset' => [
                        'auth.verificationCode' => '',
                        'auth.verificationExpires' => ''
                    ]
                ]
            );

            if (!$result->getModifiedCount()) {
                throw new Exception('Failed to verify user');
            }

            return [
                'success' => true,
                'message' => 'Email verified successfully'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function login($data) {
        try {
            $badLogin = 'Invalid username or password';
            if (!isset($data['username']) || !isset($data['password'])) {
                throw new Exception('Username and password required');
            }

            // Find user
            $user = $this->db->users->findOne([
                '$or' => [
                    ['email' => $data['username']],
                    ['username' => $data['username']]
                ]
            ]);

            if (!$user) {
                throw new Exception($badLogin);
            }

            // Verify password
            if (!password_verify($data['password'], $user['auth']['passwordHash'])) {
                throw new Exception($badLogin);
            }

            // Check status
//            if ($user['status'] !== 'active') {
//                throw new Exception('Account is not active');
//            }

            // Generate tokens
            $tokens = $this->generateTokens($user['_id']);

            // Update last login
            $this->db->users->updateOne(
                ['_id' => $user['_id']],
                [
                    '$set' => [
                        'auth.lastLogin' => new MongoDB\BSON\UTCDateTime(),
                        'auth.refreshToken' => $tokens['refreshToken']
                    ]
                ]
            );

            // Remove sensitive data
            unset($user['auth']);

            return [
                'success' => true,
                'user' => $user,
                'tokens' => $tokens
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function uploadAvatar($data) {
        global $_FILES;
        
        try {
            $file = $_FILES['avatar'];
            
            // Validate file
            if (!isset($file['tmp_name'])) {
                throw new Exception('No file uploaded');
            }

            // Check file size (5MB limit)
            if ($file['size'] > $this->config['avatar_max_size']) {
                throw new Exception('File too large');
            }

            // Check file type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif'])) {
                throw new Exception('Invalid file type');
            }

            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '.' . $extension;
            $filepath = $this->config['upload_dir'] . '/' . $filename;

            // Create directory if it doesn't exist
            if (!is_dir($this->config['upload_dir'])) {
                mkdir($this->config['upload_dir'], 0755, true);
            }

            // Move file
            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                throw new Exception('Failed to save file');
            }

            // Update user if email is provided
            if (isset($data['email'])) {
                $result = $this->db->users->updateOne(
                    ['email' => $data['email']],
                    ['$set' => ['profile.avatar' => $filename]]
                );

                if (!$result->getModifiedCount()) {
                    // Don't throw an error as the user might not exist yet
                    // The avatar will be associated when they complete registration
                }
            }

            return [
                'success' => true,
                'filename' => $filename,
                'url' => '/img/avatars/' . $filename
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function generateTokens($userId) {
        $issuedAt = time();
        $expire = $issuedAt + $this->config['jwt_expire'];

        $payload = [
            'iat' => $issuedAt,
            'exp' => $expire,
            'sub' => (string)$userId
        ];

        $jwt = JWT::encode($payload, $this->config['jwt_secret'], 'HS256');
        $refreshToken = bin2hex(random_bytes(32));

        return [
            'accessToken' => $jwt,
            'refreshToken' => $refreshToken,
            'expires' => $expire
        ];
    }
    
    public function getJwtSecret() {
        return $this->config['jwt_secret'];
    }

    public function decodeToken($token) {
        try {
            return JWT::decode(
                $token,
                new Key($this->config['jwt_secret'], 'HS256')
            );
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Generates a password reset token and sends reset email
     */
    public function handleForgotPassword($email) {
        global $db;
        
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Invalid email format'];
        }
        
        // Check if user exists
        $stmt = $db->prepare("SELECT id, firstName FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            // Return success even if user doesn't exist for security
            return ['success' => true];
        }
        
        // Generate secure random token
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Store reset token in database
        $stmt = $db->prepare("INSERT INTO password_resets (user_id, token, expires) VALUES (?, ?, ?)");
        $stmt->execute([$user['id'], password_hash($token, PASSWORD_DEFAULT), $expires]);
        
        // Send reset email
        $resetLink = "https://app.thegivehub.com/reset-password.html?token=" . urlencode($token);
        
        $to = $email;
        $subject = "Reset Your Give Hub Password";
        
        $message = "
        <html>
        <head>
            <title>Reset Your Password</title>
        </head>
        <body>
            <p>Hi {$user['firstName']},</p>
            <p>We received a request to reset your password for your Give Hub account.</p>
            <p>To reset your password, click the link below (or copy and paste it into your browser):</p>
            <p><a href=\"{$resetLink}\">{$resetLink}</a></p>
            <p>This link will expire in 1 hour.</p>
            <p>If you didn't request this password reset, you can safely ignore this email.</p>
            <p>Best regards,<br>The Give Hub Team</p>
        </body>
        </html>
        ";
        
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=utf-8',
            'From: The Give Hub <noreply@thegivehub.com>',
            'Reply-To: support@thegivehub.com',
            'X-Mailer: PHP/' . phpversion()
        ];
        
        mail($to, $subject, $message, implode("\r\n", $headers));
        
        return ['success' => true];
    }
    
    /**
     * Validates reset token and updates password
     */
    public function resetPassword($token, $newPassword) {
        global $db;
        
        if (strlen($newPassword) < 8) {
            return ['success' => false, 'error' => 'Password must be at least 8 characters'];
        }
        
        // Find valid reset token
        $stmt = $db->prepare("
            SELECT pr.user_id, pr.token, u.email 
            FROM password_resets pr
            JOIN users u ON u.id = pr.user_id
            WHERE pr.expires > NOW()
            ORDER BY pr.created_at DESC 
            LIMIT 1
        ");
        $stmt->execute();
        $reset = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$reset || !password_verify($token, $reset['token'])) {
            return ['success' => false, 'error' => 'Invalid or expired reset token'];
        }
        
        // Update password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashedPassword, $reset['user_id']]);
        
        // Delete used reset token
        $stmt = $db->prepare("DELETE FROM password_resets WHERE user_id = ?");
        $stmt->execute([$reset['user_id']]);
        
        return ['success' => true];
    }
    public function getUserIdFromToken() {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';
        
        if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            throw new Exception('No bearer token found');
        }
        
        $token = $matches[1];
        try {
            // Verify and decode the JWT token
            $decoded = JWT::decode($token, new Key($this->config['jwt_secret'], 'HS256'));
            return $decoded->sub; // Get user ID from token
        } catch (Exception $e) {
            throw new Exception('Invalid token');
        }
    }

    public function getCurrentUser() {
        $userId = $this->getUserIdFromToken();
        return $this->db->users->findOne(['_id' => new MongoDB\BSON\ObjectId($userId)]);
    }
}


