<?php
// lib/Auth.php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

require_once "db.php";
require_once "Mailer.php";

class Auth {
    private $db;
    private $mail;
    private $config;

    public function __construct() {
        $this->db = new Database();
        $this->db->users = $this->db->getCollection('users');
        // Initialize mailer
        $this->mail = new Mailer();
        
        // Load config
        $this->config = [
            'jwt_secret' => '6ABD1CF21B5743C99A283D9184AB6F1A15E8FC1F141C749E39B49B6FD3E9D705',
            'jwt_expire' => 3600 * 24, // 24 hours
            'verification_expire' => 3600, // 1 hour
            'upload_dir' => __DIR__ . '/../img/avatars',
            'avatar_max_size' => 5 * 1024 * 1024 // 5MB
        ];
    }

    public function register($data) {
        try {
            // Validate required fields
            $requiredFields = ['email', 'username', 'password'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field])) {
                    throw new Exception("Missing required field: $field");
                }
            }

            // Check if email or username exists
            $exists = $this->db->users->findOne([
                '$or' => [
                    ['email' => $data['email']],
                    ['username' => $data['username']]
                ]
            ]);

            if ($exists) {
                throw new Exception('Email or username already exists');
            }

            // Generate verification code
            $verificationCode = random_int(100000, 999999);
            $verificationExpires = new MongoDB\BSON\UTCDateTime((time() + $this->config['verification_expire']) * 1000);
/*
  "type": "donor",
  "status": "active",
  "personalInfo": {
    "firstName": "Sarah",
    "lastName": "Chen",
    "email": "sarah.chen@email.com",
    "phone": "+1-415-555-0101",
    "timezone": "America/Los_Angeles",
    "language": "en"
            },
 */
            $user = $this->db->users->findOne([ 'email' => $data['email']]);

             // Create user document
                $result = $this->db->users->updateOne(['_id'=>$user['_id']],
                [
                    '$set' => [
                    'email' => $data['email'],
                    'username' => $data['username'],
                    'personalInfo' => [
                        'firstName' => $data['firstName'],
                        'lastName' => $data['lastName'],
                        'email' => $data['email'],
                        'language' => $data['lang']
                    ],
                    'auth' => [
                        'passwordHash' => password_hash($data['password'], PASSWORD_DEFAULT),
                        'verificationCode' => $verificationCode,
                        'verificationExpires' => $verificationExpires,
                        'googleToken' => (isset($data['googleToken'])) ? $data['googleToken'] : '',
                        'verified' => (isset($data['verified']))? $data['verified']:false,
                        'twoFactorEnabled' => false
                    ],
                    'profile' => array_merge($data['profile'], [
                        'avatar' => null
                    ]),
                    'status' => 'pending',
                    'roles' => ['user'],
                    'created' => new MongoDB\BSON\UTCDateTime(),
                    'updated' => new MongoDB\BSON\UTCDateTime()
                    ]
                ]
                );

            // Insert user
            //$result = $this->db->users->insertOne($user);

            if (!$result->getModifiedCount()) {
                throw new Exception('Failed to create user');
            }

            // Send verification email
            $this->mail->sendVerification($data['email'], $verificationCode);

            return [
                'success' => true,
                'message' => 'Registration successful. Please check your email for verification code.',
                'userId' => (string)$result->getInsertedId()
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    function sendVerification($data) {
        $verificationCode = random_int(100000, 999999);
        $verificationExpires = new MongoDB\BSON\UTCDateTime((time() + $this->config['verification_expire']) * 1000);
        
        $user = [
            'email' => $data['email'],
            'personalInfo' => [
                'email' => $data['email']
            ],
            'auth' => [
                'verificationCode' => $verificationCode,
                'verificationExpires' => $verificationExpires,
                'verified' => false,
                'twoFactorEnabled' => false
            ]
        ];

        $result = $this->db->users->insertOne($user);
        
        $this->mail->sendVerification($data['email'], $verificationCode);
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
                throw new Exception('User not found');
            }

            // Verify password
            if (!password_verify($data['password'], $user['auth']['passwordHash'])) {
                throw new Exception('Invalid password');
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
        global $_GET;

        $file = $_FILES['avatar'];
        file_put_contents("x.log", json_encode($data, JSON_PRETTY_PRINT)."\n===\n".json_encode($file, JSON_PRETTY_PRINT)."\n===\n", FILE_APPEND);
        try {
            // Validate file
            if (!isset($file['tmp_name'])) {
                throw new Exception('No file uploaded');
            }

            // Check file size
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

            // Generate filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = $userId . '_' . time() . '.' . $extension;
            $filepath = $this->config['upload_dir'] . '/' . $filename;

            // Create directory if it doesn't exist
            if (!is_dir($this->config['upload_dir'])) {
                mkdir($this->config['upload_dir'], 0755, true);
            }

            // Move file
            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                throw new Exception('Failed to save file');
            }

            // Update user
            $result = $this->db->users->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($userId)],
                [
                    '$set' => [
                        'profile.avatar' => $filename
                    ]
                ]
            );

            if (!$result->getModifiedCount()) {
                throw new Exception('Failed to update user');
            }

            return [
                'success' => true,
                'filename' => $filename
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
}


