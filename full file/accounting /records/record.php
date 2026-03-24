<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../../auth/login.php");
    exit();
}

include_once("../../includes/db_connect.php");

$customerID = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;
$selectedAccountID = isset($_GET['account_id']) ? intval($_GET['account_id']) : 0;
$errors = [];

if ($customerID <= 0) {
    $errors[] = "Invalid customer selected.";
}

// Fetch customer name
$customerName = "";
if (!$errors) {
    $custQuery = "SELECT FirstName, LastName FROM mn.Customers WHERE CustomerID = ?";
    $custStmt = sqlsrv_query($conn, $custQuery, [$customerID]);
    if ($custStmt && $row = sqlsrv_fetch_array($custStmt, SQLSRV_FETCH_ASSOC)) {
        $customerName = $row['FirstName'] . ' ' . $row['LastName'];
    } else {
        $errors[] = "Customer not found.";
    }
}

// Fetch account and ledger entries
$accounts = [];
if (!$errors) {
    $accountQuery = "
        SELECT DISTINCT a.AccountID, a.AccountName, a.AccountType
        FROM mn.Accounts a
        INNER JOIN mn.Ledger l ON l.AccountID = a.AccountID
        INNER JOIN mn.JournalEntries je ON je.EntryID = l.EntryID
        WHERE je.CustomerID = ?
        ORDER BY a.AccountName
    ";
    $accountStmt = sqlsrv_query($conn, $accountQuery, [$customerID]);

    while ($row = sqlsrv_fetch_array($accountStmt, SQLSRV_FETCH_ASSOC)) {
        $accounts[$row['AccountID']] = [
            'name' => $row['AccountName'],
            'type' => $row['AccountType'],
            'entries' => []
        ];
    }

    if ($accounts) {
        $accountIDs = array_keys($accounts);
        $placeholders = implode(',', array_fill(0, count($accountIDs), '?'));
        $params = array_merge([$customerID], $accountIDs);

        $ledgerQuery = "
            SELECT l.EntryID, l.AccountID, je.EntryDate, je.Description, l.Debit, l.Credit
            FROM mn.Ledger l
            INNER JOIN mn.JournalEntries je ON je.EntryID = l.EntryID
            WHERE je.CustomerID = ?
            AND l.AccountID IN ($placeholders)
            ORDER BY l.AccountID, je.EntryDate, l.EntryID
        ";
        $ledgerStmt = sqlsrv_query($conn, $ledgerQuery, $params);

        while ($entry = sqlsrv_fetch_array($ledgerStmt, SQLSRV_FETCH_ASSOC)) {
            $accounts[$entry['AccountID']]['entries'][] = $entry;
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8" />
    <title>Customer Ledger - <?= htmlspecialchars($customerName) ?></title>
    <link rel="stylesheet" href="../../css/base.css">
    <link rel="stylesheet" href="../../css/buttons.css">
    <link rel="stylesheet" href="../../css/forms.css">
    <link rel="stylesheet" href="../../css/tables.css">
    <link rel="stylesheet" href="../../css/nav.css">
    <link rel="stylesheet" href="../../css/messages.css">
</head>
<body>
<div class="container">
    <h2>📒 Ledger for Customer: <?= htmlspecialchars($customerName) ?></h2>

    <?php if ($errors): ?>
        <div class="error">
            <?php foreach ($errors as $err): ?>
                <?= htmlspecialchars($err) ?><br>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <?php if (!empty($accounts)): ?>
            <form method="GET" class="form-inline mb-3">
                <input type="hidden" name="customer_id" value="<?= $customerID ?>">
                <div class="form-group">
                    <label for="account_id">Filter by Account:</label>
                    <select name="account_id" id="account_id" class="form-control">
                        <option value="0">-- All Accounts --</option>
                        <?php foreach ($accounts as $id => $acc): ?>
                            <option value="<?= $id ?>" <?= $id == $selectedAccountID ? 'selected' : '' ?>>
                                <?= htmlspecialchars($acc['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-group">
                            <button type="submit" class="btn btn-primary ml-2">Filter</button>
                    <a href="record.php?customer_id=<?= $customerID ?>" class="btn btn-secondary ml-1">Clear</a>
                    </div>
                    
                </div>
            </form>
        <?php endif; ?>

        <?php if (empty($accounts)): ?>
            <p>No ledger records found for this customer.</p>
        <?php else: ?>
            <?php foreach ($accounts as $accountID => $account): ?>
                <?php
                if (empty($account['entries'])) continue;
                if ($selectedAccountID && $selectedAccountID != $accountID) continue;
                ?>
                <div class="ledger">
                    <h3><?= htmlspecialchars($account['name']) ?> (<?= $account['type'] ?>)</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Description</th>
                                <th>Debit</th>
                                <th>Credit</th>
                                <th>Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $balance = 0;
                            $isNormalDebit = in_array($account['type'], ['Asset', 'Expense']);

                            foreach ($account['entries'] as $entry):
                                $debit = (float)$entry['Debit'];
                                $credit = (float)$entry['Credit'];
                                $balance += $isNormalDebit ? ($debit - $credit) : ($credit - $debit);
                            ?>
                            <tr>
                                <td><?= date_format($entry['EntryDate'], 'Y-m-d') ?></td>
                                <td><?= htmlspecialchars($entry['Description']) ?></td>
                                <td><?= number_format($debit, 2) ?></td>
                                <td><?= number_format($credit, 2) ?></td>
                                <td><?= number_format($balance, 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>

<a href="../../information/customers.php" class="btn back-btn-fixed">← Back to Customers</a>
</body>
</html>
