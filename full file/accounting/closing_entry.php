<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../auth/login.php");
    exit();
}

include_once("../includes/db_connect.php");

$incomeSummaryID = 10; // Income Summary AccountID
$retainedEarningsID = 20; // Retained Earnings AccountID

// Use all transactions up to today
$fromDate = '1900-01-01';
$toDate = date('Y-m-d');

sqlsrv_begin_transaction($conn);

try {
    // 1. Clear Revenue accounts
    $revQuery = "
        SELECT a.AccountID, SUM(ISNULL(l.Credit,0)) - SUM(ISNULL(l.Debit,0)) AS Balance
        FROM mn.Accounts a
        JOIN mn.Ledger l ON a.AccountID = l.AccountID
        JOIN mn.JournalEntries je ON l.EntryID = je.EntryID
        WHERE a.AccountType = 'Revenue' AND je.EntryDate BETWEEN ? AND ?
        GROUP BY a.AccountID
    ";
    $revStmt = sqlsrv_query($conn, $revQuery, [$fromDate, $toDate]);
    if (!$revStmt) throw new Exception("Error reading revenue accounts: " . print_r(sqlsrv_errors(), true));

    // 2. Clear Expense accounts
    $expQuery = "
        SELECT a.AccountID, SUM(ISNULL(l.Debit,0)) - SUM(ISNULL(l.Credit,0)) AS Balance
        FROM mn.Accounts a
        JOIN mn.Ledger l ON a.AccountID = l.AccountID
        JOIN mn.JournalEntries je ON l.EntryID = je.EntryID
        WHERE a.AccountType = 'Expense' AND je.EntryDate BETWEEN ? AND ?
        GROUP BY a.AccountID
    ";
    $expStmt = sqlsrv_query($conn, $expQuery, [$fromDate, $toDate]);
    if (!$expStmt) throw new Exception("Error reading expense accounts: " . print_r(sqlsrv_errors(), true));

    // 3. Create journal entry
    $desc = "Closing Entry: Revenue and Expense accounts to Income Summary";
    $insertEntry = sqlsrv_query($conn, "INSERT INTO mn.JournalEntries (Description) OUTPUT INSERTED.EntryID VALUES (?)", [$desc]);
    if (!$insertEntry) throw new Exception("Could not insert journal entry.");
    $entryRow = sqlsrv_fetch_array($insertEntry, SQLSRV_FETCH_ASSOC);
    $entryID = $entryRow['EntryID'];

    $netIncome = 0;

    // 4. Zero out revenue accounts (debit them, credit income summary)
    while ($r = sqlsrv_fetch_array($revStmt, SQLSRV_FETCH_ASSOC)) {
        $accID = $r['AccountID'];
        $bal = $r['Balance'];
        if ($bal > 0) {
            $netIncome += $bal;
            sqlsrv_query($conn, "INSERT INTO mn.Ledger (EntryID, AccountID, Debit) VALUES (?, ?, ?)", [$entryID, $accID, $bal]);
            sqlsrv_query($conn, "INSERT INTO mn.Ledger (EntryID, AccountID, Credit) VALUES (?, ?, ?)", [$entryID, $incomeSummaryID, $bal]);
        }
    }

    // 5. Zero out expense accounts (credit them, debit income summary)
    while ($e = sqlsrv_fetch_array($expStmt, SQLSRV_FETCH_ASSOC)) {
        $accID = $e['AccountID'];
        $bal = $e['Balance'];
        if ($bal > 0) {
            $netIncome -= $bal;
            sqlsrv_query($conn, "INSERT INTO mn.Ledger (EntryID, AccountID, Credit) VALUES (?, ?, ?)", [$entryID, $accID, $bal]);
            sqlsrv_query($conn, "INSERT INTO mn.Ledger (EntryID, AccountID, Debit) VALUES (?, ?, ?)", [$entryID, $incomeSummaryID, $bal]);
        }
    }

    // 6. Move Income Summary to Retained Earnings
    $desc2 = "Transfer Net Income to Retained Earnings";
    $stmt2 = sqlsrv_query($conn, "INSERT INTO mn.JournalEntries (Description) OUTPUT INSERTED.EntryID VALUES (?)", [$desc2]);
    if (!$stmt2) throw new Exception("Could not insert final transfer entry.");
    $entry2 = sqlsrv_fetch_array($stmt2, SQLSRV_FETCH_ASSOC);
    $entryID2 = $entry2['EntryID'];

    if ($netIncome >= 0) {
        // Net Profit
        sqlsrv_query($conn, "INSERT INTO mn.Ledger (EntryID, AccountID, Debit) VALUES (?, ?, ?)", [$entryID2, $incomeSummaryID, $netIncome]);
        sqlsrv_query($conn, "INSERT INTO mn.Ledger (EntryID, AccountID, Credit) VALUES (?, ?, ?)", [$entryID2, $retainedEarningsID, $netIncome]);
    } else {
        // Net Loss
        $loss = abs($netIncome);
        sqlsrv_query($conn, "INSERT INTO mn.Ledger (EntryID, AccountID, Debit) VALUES (?, ?, ?)", [$entryID2, $retainedEarningsID, $loss]);
        sqlsrv_query($conn, "INSERT INTO mn.Ledger (EntryID, AccountID, Credit) VALUES (?, ?, ?)", [$entryID2, $incomeSummaryID, $loss]);
    }

    sqlsrv_commit($conn);
    header("Location: journal_entry.php?success=closing_done");
    exit();

} catch (Exception $ex) {
    sqlsrv_rollback($conn);
    echo "<h3>Closing failed:</h3><pre>" . htmlspecialchars($ex->getMessage()) . "</pre>";
}
