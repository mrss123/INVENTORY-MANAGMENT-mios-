<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../auth/login.php");
    exit();
}

require_once("../vendor/autoload.php"); // PhpSpreadsheet autoload
include_once("../includes/db_connect.php");

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Get filter
$filterAccountID = $_GET['account_id'] ?? '';
$accounts = [];

// Fetch accounts
$accountQuery = "SELECT AccountID, AccountName, AccountType FROM mn.Accounts ORDER BY AccountName";
$accountStmt = sqlsrv_query($conn, $accountQuery);
while ($row = sqlsrv_fetch_array($accountStmt, SQLSRV_FETCH_ASSOC)) {
    $accounts[$row['AccountID']] = [
        'name' => $row['AccountName'],
        'type' => $row['AccountType'],
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
while ($row = sqlsrv_fetch_array($ledgerStmt, SQLSRV_FETCH_ASSOC)) {
    $accounts[$row['AccountID']]['entries'][] = $row;
}

// Create spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle("Ledger Report");

$rowNum = 1;
$sheet->setCellValue("A$rowNum", "📒 Ledger Report");
$sheet->mergeCells("A$rowNum:E$rowNum");
$sheet->getStyle("A$rowNum")->getFont()->setBold(true)->setSize(14);
$rowNum += 2;

foreach ($accounts as $accountID => $account) {
    if (empty($account['entries'])) continue;

    // Account title
    $sheet->setCellValue("A$rowNum", $account['name'] . " (" . $account['type'] . ")");
    $sheet->mergeCells("A$rowNum:E$rowNum");
    $sheet->getStyle("A$rowNum")->getFont()->setBold(true)->setSize(12);
    $rowNum++;

    // Headers
    $sheet->setCellValue("A$rowNum", "Date")
          ->setCellValue("B$rowNum", "Description")
          ->setCellValue("C$rowNum", "Debit")
          ->setCellValue("D$rowNum", "Credit")
          ->setCellValue("E$rowNum", "Balance");
    $sheet->getStyle("A$rowNum:E$rowNum")->getFont()->setBold(true);
    $rowNum++;

    // Entries
    $balance = 0;
    foreach ($account['entries'] as $entry) {
        $debit = (float)$entry['Debit'];
        $credit = (float)$entry['Credit'];
        $isNormalDebit = in_array($account['type'], ['Asset', 'Expense']);

        if ($isNormalDebit) {
            $balance += $debit - $credit;
        } else {
            $balance += $credit - $debit;
        }

        $sheet->setCellValue("A$rowNum", date_format($entry['EntryDate'], 'Y-m-d'))
              ->setCellValue("B$rowNum", $entry['Description'])
              ->setCellValue("C$rowNum", $debit)
              ->setCellValue("D$rowNum", $credit)
              ->setCellValue("E$rowNum", $balance);
        $rowNum++;
    }

    $rowNum += 2; // Space between accounts
}

// Auto-size columns
foreach (range("A", "E") as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Output Excel file
$filename = "ledger_report_" . date("Ymd_His") . ".xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment;filename=\"$filename\"");
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
