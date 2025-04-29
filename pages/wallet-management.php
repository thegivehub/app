<?php
/**
 * User Wallet Management Page
 * Allows users to view and manage their Stellar wallet
 */
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/Wallet.php';

// Ensure user is logged in
Auth::requireLogin();
$userId = Auth::getUserId();

// Initialize wallet service
$wallet = new Wallet(true); // true for testnet

// Handle form submissions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_wallet':
            $result = $wallet->createWallet($userId);
            if ($result['success']) {
                $message = 'Wallet created successfully!';
            } else {
                $error = $result['error'];
            }
            break;
            
        case 'fund_testnet':
            $publicKey = $_POST['public_key'] ?? '';
            $result = $wallet->fundTestnetAccount($publicKey);
            if ($result['success']) {
                $message = 'Account funded successfully with test XLM!';
            } else {
                $error = $result['error'];
            }
            break;
    }
}

// Get current wallet info
$walletInfo = $wallet->getWallet($userId);
$hasWallet = $walletInfo['success'];

// Get transaction history if wallet exists
$transactions = [];
if ($hasWallet) {
    $page = $_GET['page'] ?? 1;
    $limit = $_GET['limit'] ?? 10;
    $txResult = $wallet->getTransactions($userId, [
        'page' => $page,
        'limit' => $limit
    ]);
    if ($txResult['success']) {
        $transactions = $txResult['transactions'];
        $pagination = $txResult['pagination'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wallet Management - The Give Hub</title>
    <link rel="stylesheet" href="/assets/css/styles.css">
    <style>
        .wallet-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .wallet-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .balance {
            font-size: 24px;
            font-weight: bold;
            color: #2196F3;
            margin: 10px 0;
        }
        
        .address {
            word-break: break-all;
            font-family: monospace;
            padding: 10px;
            background: #f5f5f5;
            border-radius: 4px;
            margin: 10px 0;
        }
        
        .transaction-list {
            margin-top: 20px;
        }
        
        .transaction-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 15px;
            align-items: center;
        }
        
        .network-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
            background: #FFC107;
            color: black;
        }
        
        .message {
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
        }
        
        .success {
            background: #4CAF50;
            color: white;
        }
        
        .error {
            background: #f44336;
            color: white;
        }
        
        .button {
            background: #2196F3;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
        }
        
        .button:hover {
            background: #1976D2;
        }
    </style>
</head>
<body>
    <div class="wallet-container">
        <h1>Wallet Management</h1>
        
        <?php if ($message): ?>
            <div class="message success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="wallet-card">
            <div class="network-badge">TESTNET</div>
            
            <?php if ($hasWallet): ?>
                <h2>Your Wallet</h2>
                <div class="balance">
                    <?php echo number_format($walletInfo['wallet']['balance'], 7); ?> XLM
                </div>
                
                <h3>Public Key</h3>
                <div class="address">
                    <?php echo htmlspecialchars($walletInfo['wallet']['publicKey']); ?>
                </div>
                
                <form method="post" style="margin-top: 20px;">
                    <input type="hidden" name="action" value="fund_testnet">
                    <input type="hidden" name="public_key" value="<?php echo htmlspecialchars($walletInfo['wallet']['publicKey']); ?>">
                    <button type="submit" class="button">Fund Testnet Account</button>
                </form>
            <?php else: ?>
                <p>You don't have a wallet yet.</p>
                <form method="post">
                    <input type="hidden" name="action" value="create_wallet">
                    <button type="submit" class="button">Create Wallet</button>
                </form>
            <?php endif; ?>
        </div>
        
        <?php if ($hasWallet && !empty($transactions)): ?>
            <div class="wallet-card">
                <h2>Transaction History</h2>
                <div class="transaction-list">
                    <?php foreach ($transactions as $tx): ?>
                        <div class="transaction-item">
                            <div class="transaction-icon">
                                <?php echo $tx['successful'] ? '✅' : '❌'; ?>
                            </div>
                            <div class="transaction-details">
                                <div>Hash: <?php echo htmlspecialchars($tx['hash']); ?></div>
                                <div>Date: <?php echo date('Y-m-d H:i:s', strtotime($tx['createdAt'])); ?></div>
                                <?php if ($tx['memo']): ?>
                                    <div>Memo: <?php echo htmlspecialchars($tx['memo']); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="transaction-amount">
                                <?php echo number_format($tx['fee'], 7); ?> XLM (fee)
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if (isset($pagination) && $pagination['hasMore']): ?>
                    <div style="text-align: center; margin-top: 20px;">
                        <a href="?page=<?php echo $page + 1; ?>" class="button">Load More</a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        // Add any client-side functionality here
        document.addEventListener('DOMContentLoaded', function() {
            // Copy address to clipboard functionality
            const addressDiv = document.querySelector('.address');
            if (addressDiv) {
                addressDiv.addEventListener('click', function() {
                    const text = this.textContent.trim();
                    navigator.clipboard.writeText(text).then(function() {
                        alert('Address copied to clipboard!');
                    });
                });
            }
        });
    </script>
</body>
</html> 