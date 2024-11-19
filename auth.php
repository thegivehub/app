<?php

$req = json_encode($_REQUEST, JSON_PRETTY_PRINT);
file_put_contents("google-auth.log", $req."\n", FILE_APPEND);

require __DIR__ . '/lib/Auth.php';
require __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/User.php';

use League\OAuth2\Client\Provider\Google;

$auth = new Auth();

session_start(); // Remove if session.auto_start=1 in php.ini
$config = [];
$lines = preg_split("/\n/", file_get_contents(".env"));
foreach ($lines as $line) {
    [$key, $val] = preg_split("/\=/", $line, 2);
    if ($key && $val) $config[$key] = $val;
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
        
        $out = ["firstName"=>$ownerDetails->getFirstName(), "lastName"=>$ownerDetails->getLastName(), "email"=>$ownerDetails->getEmail(), "avatar"=>$ownerDetails->getAvatar(), "language"=>"en", "password"=>$token->getToken(), "googleToken"=>$token->getToken(), "verified"=>true ];

        $_SESSION['googleProfile'] = $out;
        $_SESSION['token'] = $token->getToken();

        $db = new User();
        $id = $db->findId(["email"=>$out['email']]);

        $user = $db->get($id);
        $_SESSION['user'] = $user;

        if ($token->getExpires() < time()) {
            $token->getRefreshToken();
        }

        // Use these details to create a new profile
        //header("Location: /index.html");

    } catch (Exception $e) {

        // Failed to get user details
        exit('Something went wrong: ' . $e->getMessage());

    }

    // Use this to interact with an API on the users behalf
    //echo $token->getToken();

    // Use this to get a new access token if the old one expires
    //echo $token->getRefreshToken();

    // Unix timestamp at which the access token expires
    //echo $token->getExpires();
}
