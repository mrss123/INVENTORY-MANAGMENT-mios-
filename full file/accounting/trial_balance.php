<?php include_once("../includes/db_connect.php"); 
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../auth/login.php");
    exit();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Trial Balance</title>
    <link rel="stylesheet" href="../css/base.css">
    <link rel="stylesheet" href="../css/buttons.css">
    <link rel="stylesheet" href="../css/forms.css">
    <link rel="stylesheet" href="../css/tables.css">
    <link rel="stylesheet" href="../css/nav.css">
    <link rel="stylesheet" href="../css/messages.css">
</head>
<body>
    <h2>Trial Balance</h2>
     <div class="container">
          <?php
    $query = "SELECT AccountID, AccountName FROM mn.Accounts ORDER BY AccountName";
    $stmt = sqlsrv_query($conn, $query);

    if (!$stmt) {
        echo "<p style='color:red; text-align:center;'>Error fetching accounts: " . print_r(sqlsrv_errors(), true) . "</p>";
        exit;
    }

    $totalDebit = 0;
    $totalCredit = 0;

    echo "<table>";
    echo "<thead><tr><th>Account Name</th><th>Debit</th><th>Credit</th></tr></thead>";
    echo "<tbody>";

    while ($account = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $accountID = $account['AccountID'];
        $accountName = $account['AccountName'];

        $query = "
            SELECT 
                SUM(ISNULL(Debit, 0)) AS TotalDebit,
                SUM(ISNULL(Credit, 0)) AS TotalCredit
            FROM mn.Ledger
            WHERE AccountID = ?
        ";

        $ledgerStmt = sqlsrv_query($conn, $query, [$accountID]);
        $debit = 0;
        $credit = 0;

        if ($ledgerStmt && $row = sqlsrv_fetch_array($ledgerStmt, SQLSRV_FETCH_ASSOC)) {
            $net = $row['TotalDebit'] - $row['TotalCredit'];

            if ($net > 0) {
                $debit = $net;
                $totalDebit += $debit;
            } elseif ($net < 0) {
                $credit = abs($net);
                $totalCredit += $credit;
            }
        }

        echo "<tr>";
        echo "<td style='text-align:left;'>".htmlspecialchars($accountName)."</td>";
        echo "<td>" . ($debit > 0 ? number_format($debit, 2) : '') . "</td>";
        echo "<td>" . ($credit > 0 ? number_format($credit, 2) : '') . "</td>";
        echo "</tr>";
    }

    echo "<tr>";
    echo "<td>Total</td>";
    echo "<td>" . number_format($totalDebit, 2) . "</td>";
    echo "<td>" . number_format($totalCredit, 2) . "</td>";
    echo "</tr>";

    echo "</tbody></table>";
    ?>

     <a href="../dashboard.php" class="btn back-btn-fixed">← Back to Dashboard</a>
     </div>
   
</body>
</html>
