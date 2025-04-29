<?php
require_once __DIR__ . '/../lib/TransactionProcessor.php';
$processor = new TransactionProcessor(true); // true = testnet

$result = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        switch ($action) {
            case 'donation':
                $result = $processor->processDonation([
                    'donorId' => $_POST['donorId'],
                    'campaignId' => $_POST['campaignId'],
                    'amount' => $_POST['amount'],
                    'sourceSecret' => $_POST['sourceSecret'],
                    'isAnonymous' => !empty($_POST['isAnonymous']),
                    'message' => $_POST['message'] ?? '',
                    'recurring' => false
                ]);
                break;
            case 'recurring':
                $result = $processor->processDonation([
                    'donorId' => $_POST['donorId'],
                    'campaignId' => $_POST['campaignId'],
                    'amount' => $_POST['amount'],
                    'sourceSecret' => $_POST['sourceSecret'],
                    'isAnonymous' => !empty($_POST['isAnonymous']),
                    'message' => $_POST['message'] ?? '',
                    'recurring' => true,
                    'frequency' => $_POST['frequency'] ?? 'monthly'
                ]);
                break;
            case 'milestone_escrow':
                $milestones = [];
                for ($i = 1; $i <= 3; $i++) {
                    if (!empty($_POST["milestone{$i}_title"]) && !empty($_POST["milestone{$i}_amount"])) {
                        $milestones[] = [
                            'title' => $_POST["milestone{$i}_title"],
                            'amount' => $_POST["milestone{$i}_amount"],
                            'releaseDays' => $_POST["milestone{$i}_releaseDays"] ?? null
                        ];
                    }
                }
                $result = $processor->createMilestoneEscrow([
                    'campaignId' => $_POST['campaignId'],
                    'sourceSecret' => $_POST['sourceSecret'],
                    'milestones' => $milestones,
                    'initialFunding' => $_POST['initialFunding'] ?? "1"
                ]);
                break;
            case 'milestone_release':
                $result = $processor->releaseMilestoneFunding([
                    'campaignId' => $_POST['campaignId'],
                    'milestoneId' => $_POST['milestoneId'],
                    'authorizedBy' => $_POST['authorizedBy'],
                    'amount' => $_POST['amount']
                ]);
                break;
            default:
                $error = "Unknown action";
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>TransactionProcessor Demo</title>
    <style>
        body { font-family: Arial; margin: 40px; background: #f0f2f5; }
        .demo-container { max-width: 900px; margin: 0 auto; background: #fff; border-radius: 8px; box-shadow: 0 2px 8px #ccc; padding: 30px; }
        h1 { color: #1a237e; }
        h2 { color: #333; border-bottom: 1px solid #eee; padding-bottom: 5px; }
        form { margin-bottom: 30px; }
        label { display: block; margin-top: 10px; }
        input, select, textarea { width: 100%; padding: 8px; margin-top: 2px; border-radius: 4px; border: 1px solid #ccc; }
        button { margin-top: 15px; padding: 10px 20px; background: #1a237e; color: #fff; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #3949ab; }
        .result, .error { margin-top: 20px; padding: 15px; border-radius: 5px; }
        .result { background: #e8f5e9; color: #2e7d32; }
        .error { background: #ffebee; color: #c62828; }
        .section { margin-bottom: 40px; }
        .milestone-group { background: #f5f5f5; padding: 10px; border-radius: 5px; margin-bottom: 10px; }
    </style>
</head>
<body>
<div class="demo-container">
    <h1>TransactionProcessor Demo</h1>
    <p>Test all major transaction types supported by <code>TransactionProcessor.php</code>.<br>
    <b>Note:</b> Use testnet keys and IDs for safety.</p>

    <div class="section">
        <h2>One-Time Donation</h2>
        <form method="post">
            <input type="hidden" name="action" value="donation">
            <label>Donor ID: <input type="text" name="donorId" required></label>
            <label>Campaign ID: <input type="text" name="campaignId" required></label>
            <label>Amount (XLM): <input type="text" name="amount" required></label>
            <label>Source Secret: <input type="text" name="sourceSecret" required></label>
            <label>Anonymous? <input type="checkbox" name="isAnonymous"></label>
            <label>Message: <input type="text" name="message"></label>
            <button type="submit">Submit Donation</button>
        </form>
    </div>

    <div class="section">
        <h2>Recurring Donation</h2>
        <form method="post">
            <input type="hidden" name="action" value="recurring">
            <label>Donor ID: <input type="text" name="donorId" required></label>
            <label>Campaign ID: <input type="text" name="campaignId" required></label>
            <label>Amount (XLM): <input type="text" name="amount" required></label>
            <label>Source Secret: <input type="text" name="sourceSecret" required></label>
            <label>Anonymous? <input type="checkbox" name="isAnonymous"></label>
            <label>Message: <input type="text" name="message"></label>
            <label>Frequency:
                <select name="frequency">
                    <option value="monthly">Monthly</option>
                    <option value="weekly">Weekly</option>
                    <option value="quarterly">Quarterly</option>
                    <option value="annually">Annually</option>
                </select>
            </label>
            <button type="submit">Setup Recurring Donation</button>
        </form>
    </div>

    <div class="section">
        <h2>Milestone Escrow Creation</h2>
        <form method="post">
            <input type="hidden" name="action" value="milestone_escrow">
            <label>Campaign ID: <input type="text" name="campaignId" required></label>
            <label>Source Secret: <input type="text" name="sourceSecret" required></label>
            <label>Initial Funding (XLM): <input type="text" name="initialFunding" value="1"></label>
            <div class="milestone-group">
                <b>Milestone 1</b>
                <label>Title: <input type="text" name="milestone1_title"></label>
                <label>Amount (XLM): <input type="text" name="milestone1_amount"></label>
                <label>Release Days from Now: <input type="number" name="milestone1_releaseDays"></label>
            </div>
            <div class="milestone-group">
                <b>Milestone 2</b>
                <label>Title: <input type="text" name="milestone2_title"></label>
                <label>Amount (XLM): <input type="text" name="milestone2_amount"></label>
                <label>Release Days from Now: <input type="number" name="milestone2_releaseDays"></label>
            </div>
            <div class="milestone-group">
                <b>Milestone 3</b>
                <label>Title: <input type="text" name="milestone3_title"></label>
                <label>Amount (XLM): <input type="text" name="milestone3_amount"></label>
                <label>Release Days from Now: <input type="number" name="milestone3_releaseDays"></label>
            </div>
            <button type="submit">Create Escrow</button>
        </form>
    </div>

    <div class="section">
        <h2>Milestone Fund Release</h2>
        <form method="post">
            <input type="hidden" name="action" value="milestone_release">
            <label>Campaign ID: <input type="text" name="campaignId" required></label>
            <label>Milestone ID: <input type="text" name="milestoneId" required></label>
            <label>Authorized By (User ID): <input type="text" name="authorizedBy" required></label>
            <label>Amount (XLM): <input type="text" name="amount" required></label>
            <button type="submit">Release Funds</button>
        </form>
    </div>

    <?php if ($result): ?>
        <div class="result">
            <h3>Result:</h3>
            <pre><?php echo htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT)); ?></pre>
        </div>
    <?php elseif ($error): ?>
        <div class="error">
            <h3>Error:</h3>
            <pre><?php echo htmlspecialchars($error); ?></pre>
        </div>
    <?php endif; ?>
</div>
</body>
</html> 