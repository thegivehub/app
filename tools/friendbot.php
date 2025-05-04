<?php

require("lib/autoload.php");
require("vendor/autoload.php");
require_once("vendor/soneso/stellar-php-sdk/Soneso/StellarSDK/StellarSDK.php");

use Soneso\StellarSDK\Crypto\StrKey;
use Soneso\StellarSDK\Crypto\KeyPair;
use Soneso\StellarSDK\Exceptions\HorizonRequestException;
use Soneso\StellarSDK\ManageDataOperationBuilder;
use Soneso\StellarSDK\Memo;
use Soneso\StellarSDK\MuxedAccount;
use Soneso\StellarSDK\Network;
use Soneso\StellarSDK\SetOptionsOperation;
use Soneso\StellarSDK\SetOptionsOperationBuilder;
use Soneso\StellarSDK\StellarSDK;
use Soneso\StellarSDK\TransactionBuilder;
use Soneso\StellarSDK\Util\FriendBot;
use Soneso\StellarSDK\Xdr\XdrSignerKey;
use Soneso\StellarSDK\Xdr\XdrSignerKeyType;
use Soneso\StellarSDK\Asset;
use Soneso\StellarSDK\PaymentOperation;
use Soneso\StellarSDK\CreateAccountOperation;


array_shift($argv);
$accid = array_shift($argv);

// Account ID
print("Account ID: ". $accid);


$sdk = StellarSDK::getTestNetInstance();

/**
 * Create an account
 *
 * We have an address but it is not activated until it has at least 1 lumen
 * so we ask friendbot to fund us
 **/

$funded = FriendBot::fundTestAccount($accid);
print ($funded ? "account funded" : "account not funded");
print "\n";

/**
 * Basic Info
 *
 **/
$accountId = $accid;

// Request the account data.
$account = $sdk->requestAccount($accountId);

// You can check the `balance`, `sequence`, `flags`, `signers`, `data` etc.
foreach ($account->getBalances() as $balance) {
    switch ($balance->getAssetType()) {
        case Asset::TYPE_NATIVE:
            printf (PHP_EOL."Balance: %s XLM", $balance->getBalance() );
            break;
        default:
            printf(PHP_EOL."Balance: %s %s Issuer: %s",
                $balance->getBalance(), $balance->getAssetCode(),
                $balance->getAssetIssuer());
    }
}

print(PHP_EOL."Sequence number: ".$account->getSequenceNumber());

foreach ($account->getSigners() as $signer) {
    print(PHP_EOL."Signer public key: ".$signer->getKey());
}


