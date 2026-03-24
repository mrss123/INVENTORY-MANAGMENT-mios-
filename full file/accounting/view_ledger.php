<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../auth/login.php");
    exit();
}

include_once("../includes/db_connect.php");

// Handle filter
$filterAccountID = $_GET['account_id'] ?? '';
$accounts = [];

// Get all accounts WITH NORMALSIDE
$accountQuery = "SELECT AccountID, AccountName, AccountType, NormalSide FROM mn.Accounts ORDER BY AccountName";
$accountStmt = sqlsrv_query($conn, $accountQuery);
while ($row = sqlsrv_fetch_array($accountStmt, SQLSRV_FETCH_ASSOC)) {
    $accounts[$row['AccountID']] = [
        'name' => $row['AccountName'],
        'type' => $row['AccountType'],
        'normal_side' => $row['NormalSide'], // ADD THIS LINE
        'entries' => []
    ];
}

// Get ledger entries
$ledgerQuery = "
    SELECT l.EntryID, l.AccountID, je.EntryDate, je.Description, l.Debit, l.Credit
    FROM mn.Ledger l
    INNER JOIN mn.JournalEntries je ON je.EntryID = l.EntryID
    WHERE (? = '' OR l.AccountID = ?)
    ORDER BY l.AccountID, je.EntryDate, l.EntryID
";
$ledgerStmt = sqlsrv_query($conn, $ledgerQuery, [$filterAccountID, $filterAccountID]);

while ($row = sqlsrv_fetch_array($ledgerStmt, SQLSRV_FETCH_ASSOC)) {
    $accounts[$row['AccountID']]['entries'][] = $row;
}
?>

<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="../css/base.css">
    <link rel="stylesheet" href="../css/buttons.css">
    <link rel="stylesheet" href="../css/forms.css">
    <link rel="stylesheet" href="../css/tables.css">
    <link rel="stylesheet" href="../css/nav.css">
    <link rel="stylesheet" href="../css/messages.css">
   
</head>
<body>
<div class="container">
    <h2>📒 Ledger View</h2>

    <form method="get">
        <div class="form-group">
            <label>Filter by Account:</label>
            <select name="account_id">
                <option value="">-- All Accounts --</option>
                <?php foreach ($accounts as $id => $account): ?>
                    <option value="<?= $id ?>" <?= ($id == $filterAccountID) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($account['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group">
            <button type="submit">Filter</button>
        </div>
    </form>

    <!-- Export Buttons -->
    <div class="form_group">
        <a href="view_ledger_pdf.php?account_id=<?= urlencode($filterAccountID) ?>" class="btn">📄 Export to PDF</a>
        <a href="view_ledger_excel.php?account_id=<?= urlencode($filterAccountID) ?>" class="btn">📊 Export to Excel</a>
    </div>

    <?php foreach ($accounts as $accountID => $account): ?>
        <?php if (empty($account['entries'])) continue; ?>
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
                foreach ($account['entries'] as $entry):
                    $debit = (float)$entry['Debit'];
                    $credit = (float)$entry['Credit'];

                    // USE NORMALSIDE INSTEAD OF ACCOUNT TYPE
                    $isNormalDebit = ($account['normal_side'] === 'Debit');

                    if ($isNormalDebit) {
                        $balance += $debit - $credit;
                    } else {
                        $balance += $credit - $debit;
                    }
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
</div>

<a href="../dashboard.php" class="btn back-btn-fixed">← Back to Dashboard</a>
</body>
</html>