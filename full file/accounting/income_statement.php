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
    if ($type === 'Revenue') {
        // For revenue accounts, calculate Credit - Debit
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
    } else {
        // For expense accounts, Debit - Credit
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

function formatETB($amount) {
    return number_format($amount, 2) . " ETB";
}

$revenues = fetchAccountsAmounts($conn, 'Revenue', $from_date, $to_date);
$expenses = fetchAccountsAmounts($conn, 'Expense', $from_date, $to_date);

$total_revenue = 0;
foreach ($revenues as $rev) {
    $total_revenue += $rev['Amount'];
}

$total_expenses = 0;
foreach ($expenses as $exp) {
    $total_expenses += $exp['Amount'];
}

$net_profit = $total_revenue - $total_expenses;

?>

<!DOCTYPE html>
<html>
<head>
    <title>Income Statement</title>
    <link rel="stylesheet" href="../css/base.css">
    <link rel="stylesheet" href="../css/buttons.css">
    <link rel="stylesheet" href="../css/forms.css">
    <link rel="stylesheet" href="../css/tables.css">
    <link rel="stylesheet" href="../css/nav.css">
    <link rel="stylesheet" href="../css/messages.css">
</head>
<body>
<div class="container">
    <h2>Income Statement</h2>

<form method="get">
    <label>From: <input type="date" name="from_date" value="<?= htmlspecialchars($from_date) ?>"></label>
    <label>To: <input type="date" name="to_date" value="<?= htmlspecialchars($to_date) ?>"></label>
    <button type="submit">View</button>
</form>

<h3>Revenues</h3>
<table>
    <tr>
        <th>Account</th>
        <th>Amount</th>
    </tr>
    <?php foreach ($revenues as $rev): ?>
        <tr>
            <td><?= htmlspecialchars($rev['AccountName']) ?></td>
            <td><?= formatETB($rev['Amount']) ?></td>
        </tr>
    <?php endforeach; ?>
    <tr class="total-row">
        <td><strong>Total Revenue</strong></td>
        <td><strong><?= formatETB($total_revenue) ?></strong></td>
    </tr>
</table>

<h3>Expenses</h3>
<table>
    <tr>
        <th>Account</th>
        <th>Amount</th>
    </tr>
    <?php foreach ($expenses as $exp): ?>
        <tr>
            <td><?= htmlspecialchars($exp['AccountName']) ?></td>
            <td><?= formatETB($exp['Amount']) ?></td>
        </tr>
    <?php endforeach; ?>
    <tr class="total-row">
        <td><strong>Total Expenses</strong></td>
        <td><strong><?= formatETB($total_expenses) ?></strong></td>
    </tr>
</table>

<table>
    <tr class="net-profit-row">
        <td><strong>Net Profit</strong></td>
        <td><strong><?= formatETB($net_profit) ?></strong></td>
    </tr>
</table>

<div style="text-align: center; margin-top: 30px;">
    <a href="../dashboard.php" class="btn back-btn-fixed">← Back to Dashboard</a>

</div>
</div>


</body>
</html>
