<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../auth/login.php");
    exit();
}

require_once '../reports/pdf_template.php'; // Correct path to pdf_template.php
include_once("../includes/db_connect.php");

// Get filter
$filterAccountID = $_GET['account_id'] ?? '';
$accounts = [];

// Fetch accounts WITH NormalSide
$accountQuery = "SELECT AccountID, AccountName, AccountType, NormalSide FROM mn.Accounts ORDER BY AccountName";
$accountStmt = sqlsrv_query($conn, $accountQuery);
while ($row = sqlsrv_fetch_array($accountStmt, SQLSRV_FETCH_ASSOC)) {
    $accounts[$row['AccountID']] = [
        'name' => $row['AccountName'],
        'type' => $row['AccountType'],
        'normal_side' => $row['NormalSide'], // Add NormalSide to account data
        'entries' => []
    ];
}

// Fetch ledger entries
$ledgerQuery = "
    SELECT l.EntryID, l.AccountID, je.EntryDate, je.Description, l.Debit, l.Credit
    FROM mn.Ledger l
    INNER JOIN mn.JournalEntries je ON je.EntryID = l.EntryID
    WHERE (? = '' OR l.AccountID = ?)
    ORDER BY l.AccountID, je.EntryDate, l.EntryID
";
$ledgerStmt = sqlsrv_query($conn, $ledgerQuery, [$filterAccountID, $filterAccountID]);

if ($ledgerStmt) {
    while ($row = sqlsrv_fetch_array($ledgerStmt, SQLSRV_FETCH_ASSOC)) {
        $accounts[$row['AccountID']]['entries'][] = $row;
    }
}

// Build table HTML for the template
$tableHTML = '';

foreach ($accounts as $accountID => $account) {
    if (empty($account['entries'])) continue;

    // Add account header
    $tableHTML .= "<h3 style='margin-top: 20px; margin-bottom: 10px; color: #198754;'>" . 
                  htmlspecialchars($account['name']) . " (" . $account['type'] . ")</h3>";
    
    $tableHTML .= "<table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Description</th>
                <th>Debit (ETB)</th>
                <th>Credit (ETB)</th>
                <th>Balance (ETB)</th>
            </tr>
        </thead>
        <tbody>";

    $balance = 0;
    foreach ($account['entries'] as $entry) {
        $debit = (float)$entry['Debit'];
        $credit = (float)$entry['Credit'];
        
        // USE NormalSide INSTEAD OF AccountType for balance calculation
        $isNormalDebit = ($account['normal_side'] === 'Debit');

        if ($isNormalDebit) {
            $balance += $debit - $credit;
        } else {
            $balance += $credit - $debit;
        }

        $tableHTML .= "<tr>
            <td>" . date_format($entry['EntryDate'], 'Y-m-d') . "</td>
            <td>" . htmlspecialchars($entry['Description']) . "</td>
            <td style='text-align:right;'>" . ($debit > 0 ? number_format($debit, 2) : '') . "</td>
            <td style='text-align:right;'>" . ($credit > 0 ? number_format($credit, 2) : '') . "</td>
            <td style='text-align:right;'>" . number_format($balance, 2) . "</td>
        </tr>";
    }

    // Add account summary row
    $tableHTML .= "<tr style='background-color: #d1e7dd; font-weight: bold;'>
        <td colspan='4' style='text-align:left;'>Final Balance</td>
        <td style='text-align:right;'>" . number_format($balance, 2) . "</td>
    </tr>";
    
    $tableHTML .= "</tbody></table><br>";
}

// If no accounts with entries found
if (empty($tableHTML)) {
    $tableHTML = "<p style='text-align:center; color:#6c757d;'>No ledger entries found for the selected criteria.</p>";
}

// Generate title based on filter
$title = "Ledger Report";
if ($filterAccountID && isset($accounts[$filterAccountID])) {
    $title .= " - " . htmlspecialchars($accounts[$filterAccountID]['name']);
}

// Use the shared template function to generate the PDF
generatePDF($title, $tableHTML);
?>