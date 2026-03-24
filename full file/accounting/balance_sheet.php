<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../auth/login.php");
    exit();
}

include_once("../includes/db_connect.php");

$from_date = $_GET['from_date'] ?? date('Y-m-01');
$to_date = $_GET['to_date'] ?? date('Y-m-d');

function fetchAccountsAmounts($conn, $type, $from, $to) {
    if ($type === 'Asset') {
        // Assets: Debit - Credit
        $sql = "
            SELECT a.AccountName,
                SUM(COALESCE(l.Debit, 0)) - SUM(COALESCE(l.Credit, 0)) AS Amount
            FROM mn.JournalEntries je
            INNER JOIN mn.ledger l ON je.EntryID = l.EntryID
            INNER JOIN mn.Accounts a ON l.AccountID = a.AccountID
            WHERE a.AccountType = ? AND je.EntryDate BETWEEN ? AND ?
            GROUP BY a.AccountName
            ORDER BY a.AccountName
        ";
    } else {
        // Liabilities and Equity: Credit - Debit
        $sql = "
            SELECT a.AccountName,
                SUM(COALESCE(l.Credit, 0)) - SUM(COALESCE(l.Debit, 0)) AS Amount
            FROM mn.JournalEntries je
            INNER JOIN mn.ledger l ON je.EntryID = l.EntryID
            INNER JOIN mn.Accounts a ON l.AccountID = a.AccountID
            WHERE a.AccountType = ? AND je.EntryDate BETWEEN ? AND ?
            GROUP BY a.AccountName
            ORDER BY a.AccountName
        ";
    }

    $params = array($type, $from, $to);

    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        die(print_r(sqlsrv_errors(), true));
    }

    $results = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $results[] = $row;
    }
    sqlsrv_free_stmt($stmt);
    return $results;
}

function fetchRetainedEarnings($conn, $from, $to) {
    $sql = "
        SELECT
            ISNULL((
                SELECT SUM(l.Credit) - SUM(l.Debit)
                FROM mn.ledger l
                INNER JOIN mn.JournalEntries je ON l.EntryID = je.EntryID
                INNER JOIN mn.Accounts a ON l.AccountID = a.AccountID
                WHERE a.AccountType = 'Revenue' AND je.EntryDate BETWEEN ? AND ?
            ), 0) -
            ISNULL((
                SELECT SUM(l.Debit) - SUM(l.Credit)
                FROM mn.ledger l
                INNER JOIN mn.JournalEntries je ON l.EntryID = je.EntryID
                INNER JOIN mn.Accounts a ON l.AccountID = a.AccountID
                WHERE a.AccountType = 'Expense' AND je.EntryDate BETWEEN ? AND ?
            ), 0) AS RetainedEarnings
    ";

    $params = array($from, $to, $from, $to);

    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        die(print_r(sqlsrv_errors(), true));
    }

    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    sqlsrv_free_stmt($stmt);

    return $row['RetainedEarnings'] ?? 0;
}

function formatETB($amount) {
    return number_format($amount, 2) . " ETB";
}

$assets = fetchAccountsAmounts($conn, 'Asset', $from_date, $to_date);
$liabilities = fetchAccountsAmounts($conn, 'Liability', $from_date, $to_date);
$equity = fetchAccountsAmounts($conn, 'Equity', $from_date, $to_date);

$total_assets = 0;
foreach ($assets as $a) {
    $total_assets += $a['Amount'];
}

$total_liabilities = 0;
foreach ($liabilities as $l) {
    $total_liabilities += $l['Amount'];
}

$total_equity = 0;
foreach ($equity as $e) {
    $total_equity += $e['Amount'];
}

// Fetch retained earnings and add to equity if non-zero
$retained_earnings = fetchRetainedEarnings($conn, $from_date, $to_date);

if ($retained_earnings != 0) {
    $equity[] = [
        'AccountName' => 'Retained Earnings',
        'Amount' => $retained_earnings
    ];
    $total_equity += $retained_earnings;
}

$total_liabilities_equity = $total_liabilities + $total_equity;
?>

<!DOCTYPE html>
<html>
<head>
    <title>Balance Sheet</title>
     <link rel="stylesheet" href="../css/base.css">
    <link rel="stylesheet" href="../css/buttons.css">
    <link rel="stylesheet" href="../css/forms.css">
    <link rel="stylesheet" href="../css/tables.css">
    <link rel="stylesheet" href="../css/nav.css">
    <link rel="stylesheet" href="../css/messages.css">
</head>
<body>
<div class="container">
    <h2>Balance Sheet</h2>

<form method="get">
    <label>From: <input type="date" name="from_date" value="<?= htmlspecialchars($from_date) ?>"></label>
    <label>To: <input type="date" name="to_date" value="<?= htmlspecialchars($to_date) ?>"></label>
    <button type="submit">View</button>
</form>

<h3>Assets</h3>
<table>
    <tr>
        <th>Account</th>
        <th>Amount</th>
    </tr>
    <?php foreach ($assets as $a): ?>
    <tr>
        <td><?= htmlspecialchars($a['AccountName']) ?></td>
        <td><?= formatETB($a['Amount']) ?></td>
    </tr>
    <?php endforeach; ?>
    <tr class="total-row total-row-green">
        <td><strong>Total Assets</strong></td>
        <td><strong><?= formatETB($total_assets) ?></strong></td>
    </tr>
</table>

<h3>Liabilities</h3>
<table>
    <tr>
        <th>Account</th>
        <th>Amount</th>
    </tr>
    <?php foreach ($liabilities as $l): ?>
    <tr>
        <td><?= htmlspecialchars($l['AccountName']) ?></td>
        <td><?= formatETB($l['Amount']) ?></td>
    </tr>
    <?php endforeach; ?>
    <tr class="total-row">
        <td>Total Liabilities</td>
        <td><?= formatETB($total_liabilities) ?></td>
    </tr>
</table>

<h3>Equity</h3>
<table>
    <tr>
        <th>Account</th>
        <th>Amount</th>
    </tr>
    <?php foreach ($equity as $e): ?>
    <tr>
        <td><?= htmlspecialchars($e['AccountName']) ?></td>
        <td><?= formatETB($e['Amount']) ?></td>
    </tr>
    <?php endforeach; ?>
    <tr class="total-row">
        <td>Total Equity</td>
        <td><?= formatETB($total_equity) ?></td>
    </tr>
</table>

<table>
    <tr class="total-row total-row-green">
        <td><strong>Total Liabilities + Equity</strong></td>
        <td><strong><?= formatETB($total_liabilities_equity) ?></strong></td>
    </tr>
</table>

 <a href="../dashboard.php" class="btn back-btn-fixed">← Back to Dashboard</a>
</div>

</div>

</body>
</html>
