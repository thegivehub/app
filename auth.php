<?php

$req = json_encode($_REQUEST, JSON_PRETTY_PRINT);
file_put_contents("google-auth.log", $req."\n", FILE_APPEND);

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/lib/Auth.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/User.php';

use League\OAuth2\Client\Provider\Google;

$auth = new Auth();

session_start(); // Remove if session.auto_start=1 in php.ini
$config = [];
$lines = preg_split("/\n/", file_get_contents(".env"));
foreach ($lines as $line) {
    $line = preg_replace("/\#.*/", '', $line);
    if ($line) {
        [$key, $val] = preg_split("/\=/", $line, 2);
        if ($key && $val) $config[$key] = $val;
    }
}

$provider = new Google([
    'clientId'     => $config['GOOGLE_CLIENT_ID'],
    'clientSecret' => $config['GOOGLE_CLIENT_SECRET'],
    'redirectUri'  => 'https://app.thegivehub.com/auth.php',
    //'hostedDomain' => 'app.thegivehub.com', // optional; used to restrict access to users on your G Suite/Google Apps for Business accounts
]);

if (!empty($_GET['error'])) {

    // Got an error, probably user denied access
    exit('Got error: ' . htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8'));

} elseif (empty($_GET['code'])) {

    // If we don't have an authorization code then get one
    $authUrl = $provider->getAuthorizationUrl();
    $_SESSION['oauth2state'] = $provider->getState();
    header('Location: ' . $authUrl);
    exit;

} elseif (empty($_GET['state']) || ($_GET['state'] !== $_SESSION['oauth2state'])) {

    // State is invalid, possible CSRF attack in progress
    unset($_SESSION['oauth2state']);
    exit('Invalid state');

} else {

    // Try to get an access token (using the authorization code grant)
    $token = $provider->getAccessToken('authorization_code', [
        'code' => $_GET['code']
    ]);

    // Optional: Now you have a token you can look up a users profile data
    try {

        // We got an access token, let's now get the owner details
        $ownerDetails = $provider->getResourceOwner($token);

        $email = $ownerDetails->getEmail();
        $firstName = $ownerDetails->getFirstName();
        $lastName = $ownerDetails->getLastName();
        $googleId = $ownerDetails->getId();

        // Get the users collection from the Auth class
        $usersCollection = $auth->getUsersCollection();

        // Check if user already exists by email
        $existingUser = $usersCollection->findOne(['email' => $email]);

        if ($existingUser) {
            // User exists - update their Google token and last login
            $userId = $existingUser['_id'];

            $usersCollection->updateOne(
                ['_id' => $userId],
                [
                    '$set' => [
                        'auth.googleId' => $googleId,
                        'auth.googleToken' => $token->getToken(),
                        'auth.lastLogin' => new MongoDB\BSON\UTCDateTime(),
                        'auth.verified' => true,
                        'status' => 'active',
                        'updatedAt' => new MongoDB\BSON\UTCDateTime()
                    ]
                ]
            );
        } else {
            // Create new user with Google credentials
            $username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $firstName . $lastName)) . rand(100, 999);

            $newUserData = [
                'email' => $email,
                'username' => $username,
                'type' => 'donor',
                'status' => 'active',
                'personalInfo' => [
                    'firstName' => $firstName,
                    'lastName' => $lastName,
                    'email' => $email,
                    'language' => 'en'
                ],
                'auth' => [
                    'passwordHash' => password_hash($token->getToken() . $googleId, PASSWORD_DEFAULT),
                    'googleId' => $googleId,
                    'googleToken' => $token->getToken(),
                    'verified' => true,
                    'twoFactorEnabled' => false,
                    'lastLogin' => new MongoDB\BSON\UTCDateTime()
                ],
                'profile' => [
                    'avatar' => null,
                    'bio' => '',
                    'preferences' => [
                        'emailNotifications' => true,
                        'currency' => 'USD'
                    ]
                ],
                'roles' => ['user'],
                'createdAt' => new MongoDB\BSON\UTCDateTime(),
                'updatedAt' => new MongoDB\BSON\UTCDateTime()
            ];

            $result = $usersCollection->insertOne($newUserData);

            if (!$result['success']) {
                throw new Exception('Failed to create user account');
            }

            $userId = new MongoDB\BSON\ObjectId($result['id']);
        }

        // Generate JWT tokens using the Auth class method (via reflection since it's private)
        $issuedAt = time();
        $expire = $issuedAt + $auth->config['jwt_expire'];

        $payload = [
            'iat' => $issuedAt,
            'exp' => $expire,
            'sub' => (string)$userId
        ];

        $jwt = \Firebase\JWT\JWT::encode($payload, $auth->config['jwt_secret'], 'HS256');
        $refreshToken = bin2hex(random_bytes(32));

        // Store refresh token in database
        $usersCollection->updateOne(
            ['_id' => $userId],
            [
                '$set' => [
                    'auth.refreshToken' => $refreshToken,
                    'updatedAt' => new MongoDB\BSON\UTCDateTime()
                ]
            ]
        );

        // Store in session for backwards compatibility
        $_SESSION['user'] = [
            'firstName' => $firstName,
            'lastName' => $lastName,
            'email' => $email,
            'language' => 'en',
            'googleToken' => $token->getToken(),
            'verified' => true
        ];
        $_SESSION['token'] = $token->getToken();

        // Redirect to a page that will store the tokens in localStorage
        // We need to pass tokens via a secure intermediate page
        $tokenData = urlencode(base64_encode(json_encode([
            'accessToken' => $jwt,
            'refreshToken' => $refreshToken,
            'expires' => $expire
        ])));

        header("Location: /google-auth-complete.html?t=" . $tokenData);

    } catch (Exception $e) {

        // Failed to get user details
        error_log("Google Auth Error: " . $e->getMessage());
        exit('Something went wrong: ' . $e->getMessage());

    }
}
