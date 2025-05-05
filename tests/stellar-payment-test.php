<?php
require("vendor/autoload.php");
require("lib/autoload.php");

use DateTime;
use phpseclib3\Math\BigInteger;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Soneso\StellarSDK\Asset;
use Soneso\StellarSDK\AssetTypeCreditAlphanum4;
use Soneso\StellarSDK\ChangeTrustOperationBuilder;
use Soneso\StellarSDK\CreateAccountOperationBuilder;
use Soneso\StellarSDK\Crypto\KeyPair;
use Soneso\StellarSDK\LedgerBounds;
use Soneso\StellarSDK\ManageDataOperationBuilder;
use Soneso\StellarSDK\ManageSellOfferOperationBuilder;
use Soneso\StellarSDK\MuxedAccount;
use Soneso\StellarSDK\Network;
use Soneso\StellarSDK\PathPaymentStrictReceiveOperationBuilder;
use Soneso\StellarSDK\PathPaymentStrictSendOperationBuilder;
use Soneso\StellarSDK\PaymentOperationBuilder;
use Soneso\StellarSDK\Responses\Operations\CreateAccountOperationResponse;
use Soneso\StellarSDK\Responses\Operations\PaymentOperationResponse;
use Soneso\StellarSDK\StellarSDK;
use Soneso\StellarSDK\TimeBounds;
use Soneso\StellarSDK\Transaction;
use Soneso\StellarSDK\TransactionBuilder;
use Soneso\StellarSDK\TransactionPreconditions;
use Soneso\StellarSDK\Util\FriendBot;

$sdk = StellarSDK::getTestNetInstance();

// First create the sender key pair from the secret seed of the sender so we can use it later for signing.
$senderKeyPair = KeyPair::fromSeed("SA2UCTWIBMJABPYLBR4YOX7ENJQM6XEKRXVTAOYDWAQDXHA6BKIU5OM7");

// Next, we need the account id of the receiver so that we can use to as a destination of our payment.
$destination = "GD7LKMPY76XFFGXWX2ASBSCPJLZPHA3UMJIZAPM6N5EYFDH45CSLY6XS";

// Load sender's account data from the stellar network. It contains the current sequence number.
$sender = $sdk->requestAccount($senderKeyPair->getAccountId());

// Build the transaction to send 100 XLM native payment from sender to destination
$paymentOperation = (new PaymentOperationBuilder($destination, Asset::native(), "200"))->build();
$transaction = (new TransactionBuilder($sender))->addOperation($paymentOperation)->build();

// Sign the transaction with the sender's key pair.
$transaction->sign($senderKeyPair, Network::testnet());

// Submit the transaction to the stellar network.
$response = $sdk->submitTransaction($transaction);
if ($response->isSuccessful()) {
    print(PHP_EOL."Payment sent");
}
