<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../auth/login.php");
    exit();
}

include_once("../includes/db_connect.php");

$auto = $_GET['auto'] ?? '';
$prefillPart = $_GET['part'] ?? '';
$prefillDesc = $_GET['desc'] ?? '';
$salesAmount = isset($_GET['sales']) ? floatval($_GET['sales']) : 0;
$cogsAmount = isset($_GET['cogs']) ? floatval($_GET['cogs']) : 0;
$purchaseAmount = isset($_GET['amount']) ? floatval($_GET['amount']) : 0;
$customerId = isset($_GET['cust']) ? intval($_GET['cust']) : null;

// FIX: Get phone and name from GET parameters (redirect from issue_part.php)
$prefillPhone = isset($_GET['phone']) ? clean_str($_GET['phone']) : '';
$prefillName  = isset($_GET['name']) ? clean_str($_GET['name']) : '';

$errors = [];
$warnings = [];
$successMessage = "";
$debitLines = [];
$creditLines = [];

/**
 * Helper: sanitize string (trim & collapse spaces)
 */
function clean_str($s) {
    return preg_replace('/\s+/', ' ', trim((string)$s));
}

/**
 * Helper: get account normal side
 */
function getAccountNormalSide($accountId, $conn) {
    $stmt = sqlsrv_query($conn, "SELECT NormalSide FROM mn.Accounts WHERE AccountID = ?", [$accountId]);
    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        return $row['NormalSide'];
    }
    return 'Credit'; // default fallback
}

// Account ID constants
define('INVENTORY_ID', 4);
define('CASH_ID', 1);
define('ACCOUNTS_PAYABLE_ID', 5);
define('SALES_REVENUE_ID', 20);
define('ACCOUNTS_RECEIVABLE_ID', 6);
define('COGS_ID', 24);
define('INCOME_SUMMARY_ID', 41);
define('RETAINED_EARNINGS_ID', 40);

// ===================== AUTOFILL LOGIC =====================
if ($auto === '1') { // Purchase from add_spare_part.php
    $prefillDesc = "Purchase of $prefillPart";
    $debitLines = [['account' => INVENTORY_ID, 'amount' => round($purchaseAmount, 2)]];
    $creditLines = [['account' => CASH_ID, 'amount' => round($purchaseAmount, 2)]];
}
elseif ($auto === '2') { // Sale from issue_part.php
    // FIX: Use the name and phone from URL parameters first
    $customerName = $prefillName;
    
    // Only query database if we don't have the name from URL AND we have customerId
    if (!$customerName && $customerId) {
        $custStmt = sqlsrv_query($conn, "SELECT FirstName, LastName FROM mn.Customers WHERE CustomerID = ?", [$customerId]);
        if ($custStmt && $custRow = sqlsrv_fetch_array($custStmt, SQLSRV_FETCH_ASSOC)) {
            $customerName = trim($custRow['FirstName'] . ' ' . $custRow['LastName']);
        }
    }
    
    // Final fallback if we still don't have a name
    if (!$customerName) {
        $customerName = "Customer";
    }

    if (isset($_GET['multi']) && $_GET['multi'] == '1' && isset($_GET['payload'])) {
        $items = json_decode(base64_decode($_GET['payload']), true);
        $parts = array_column($items, 'part');
        $partList = implode(', ', $parts);
        $prefillDesc = "Sale of $partList to $customerName";

        $totalSales = 0;
        $totalCogs = 0;
        foreach ($items as $item) {
            $totalSales += $item['sales'];
            $totalCogs  += $item['cogs'];
        }
        $debitLines  = [
            ['account' => CASH_ID, 'amount' => round($totalSales, 2)],
            ['account' => COGS_ID, 'amount' => round($totalCogs, 2)]
        ];
        $creditLines = [
            ['account' => SALES_REVENUE_ID, 'amount' => round($totalSales, 2)],
            ['account' => INVENTORY_ID, 'amount' => round($totalCogs, 2)]
        ];
    } elseif ($salesAmount && $cogsAmount) {
        $prefillDesc = "Sale of $prefillPart to $customerName";
        $debitLines  = [
            ['account' => CASH_ID, 'amount' => round($salesAmount, 2)],
            ['account' => COGS_ID, 'amount' => round($cogsAmount, 2)]
        ];
        $creditLines = [
            ['account' => SALES_REVENUE_ID, 'amount' => round($salesAmount, 2)],
            ['account' => INVENTORY_ID, 'amount' => round($cogsAmount, 2)]
        ];
    }
}

// ===================== CLOSING ENTRY AUTOFILL =====================
if ($auto === 'closing') {
    // Revenue accounts - close to Income Summary (debit revenue, credit income summary)
    $revSql = "
        SELECT a.AccountID, SUM(ISNULL(l.Credit,0)) - SUM(ISNULL(l.Debit,0)) AS Balance
        FROM mn.Ledger l
        JOIN mn.Accounts a ON a.AccountID = l.AccountID
        WHERE LTRIM(RTRIM(a.AccountType)) = 'Revenue'
        GROUP BY a.AccountID
        HAVING SUM(ISNULL(l.Credit,0)) - SUM(ISNULL(l.Debit,0)) > 0
    ";
    $revStmt = sqlsrv_query($conn, $revSql);
    $closingDebitLines = [];
    $closingCreditLines = [];
    $totalRevenue = 0;
    while ($row = sqlsrv_fetch_array($revStmt, SQLSRV_FETCH_ASSOC)) {
        $closingDebitLines[] = ['account' => $row['AccountID'], 'amount' => round($row['Balance'], 2)];
        $totalRevenue += $row['Balance'];
    }
    if ($totalRevenue > 0) $closingCreditLines[] = ['account' => INCOME_SUMMARY_ID, 'amount' => round($totalRevenue, 2)];

    // Expense accounts - close to Income Summary (credit expense, debit income summary)
    $expSql = "
        SELECT a.AccountID, SUM(ISNULL(l.Debit,0)) - SUM(ISNULL(l.Credit,0)) AS Balance
        FROM mn.Ledger l
        JOIN mn.Accounts a ON a.AccountID = l.AccountID
        WHERE LTRIM(RTRIM(a.AccountType)) = 'Expense'
        GROUP BY a.AccountID
        HAVING SUM(ISNULL(l.Debit,0)) - SUM(ISNULL(l.Credit,0)) > 0
    ";
    $expStmt = sqlsrv_query($conn, $expSql);
    $totalExpense = 0;
    while ($row = sqlsrv_fetch_array($expStmt, SQLSRV_FETCH_ASSOC)) {
        $closingCreditLines[] = ['account' => $row['AccountID'], 'amount' => round($row['Balance'], 2)];
        $totalExpense += $row['Balance'];
    }
    if ($totalExpense > 0) $closingDebitLines[] = ['account' => INCOME_SUMMARY_ID, 'amount' => round($totalExpense, 2)];

    // Owner's Drawing account - close to Retained Earnings (respect NormalSide)
    $drawingSql = "
        SELECT a.AccountID, a.NormalSide, 
               SUM(ISNULL(l.Debit,0)) as TotalDebit, 
               SUM(ISNULL(l.Credit,0)) as TotalCredit
        FROM mn.Ledger l
        JOIN mn.Accounts a ON a.AccountID = l.AccountID
        WHERE LTRIM(RTRIM(a.AccountType)) = 'Equity' 
        AND LTRIM(RTRIM(a.AccountName)) LIKE '%drawing%'
        GROUP BY a.AccountID, a.NormalSide
    ";
    $drawingStmt = sqlsrv_query($conn, $drawingSql);
    $totalDrawing = 0;

    while ($row = sqlsrv_fetch_array($drawingStmt, SQLSRV_FETCH_ASSOC)) {
        $balance = $row['TotalDebit'] - $row['TotalCredit'];
        
        if ($balance != 0) {
            if ($row['NormalSide'] === 'Debit') {
                // Debit-normal account: close by CREDITING it
                $closingCreditLines[] = ['account' => $row['AccountID'], 'amount' => abs($balance)];
                $closingDebitLines[] = ['account' => RETAINED_EARNINGS_ID, 'amount' => abs($balance)];
            } else {
                // Credit-normal account: close by DEBITING it
                $closingDebitLines[] = ['account' => $row['AccountID'], 'amount' => abs($balance)];
                $closingCreditLines[] = ['account' => RETAINED_EARNINGS_ID, 'amount' => abs($balance)];
            }
            $totalDrawing += abs($balance);
        }
    }

    // Close Income Summary to Retained Earnings
    $netIncome = $totalRevenue - $totalExpense;
    if ($netIncome > 0) {
        // Net Income: Debit Income Summary, Credit Retained Earnings
        $closingDebitLines[] = ['account' => INCOME_SUMMARY_ID, 'amount' => round($netIncome, 2)];
        $closingCreditLines[] = ['account' => RETAINED_EARNINGS_ID, 'amount' => round($netIncome, 2)];
    } elseif ($netIncome < 0) {
        // Net Loss: Credit Income Summary, Debit Retained Earnings
        $loss = abs($netIncome);
        $closingCreditLines[] = ['account' => INCOME_SUMMARY_ID, 'amount' => round($loss, 2)];
        $closingDebitLines[] = ['account' => RETAINED_EARNINGS_ID, 'amount' => round($loss, 2)];
    }
   
    $prefillDesc = "Closing Entry: Revenue, Expense, and Drawing accounts to Income Summary and Retained Earnings";
    $_SESSION['closing_debits'] = $closingDebitLines;
    $_SESSION['closing_credits'] = $closingCreditLines;
}

// ===================== FORM SUBMISSION =====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $description = trim($_POST['description'] ?? '');
    $debitAccounts = $_POST['debit_account'] ?? [];
    $debitAmounts = $_POST['debit_amount'] ?? [];
    $creditAccounts = $_POST['credit_account'] ?? [];
    $creditAmounts = $_POST['credit_amount'] ?? [];

    // FIX: Use posted customer_phone, fallback to prefill from GET (which now includes phone from issue_part.php)
    $customerPhone = trim($_POST['customer_phone'] ?? $prefillPhone);
    $customerName  = trim($_POST['customer_name'] ?? $prefillName);

    // If phone provided, try to resolve or create customer so we have CustomerID ready
    if ($customerPhone !== '') {
        $phoneQuery = "SELECT CustomerID FROM mn.Customers WHERE PhoneNumber = ?";
        $phoneStmt = sqlsrv_query($conn, $phoneQuery, [$customerPhone]);
        if ($phoneStmt && $phoneRow = sqlsrv_fetch_array($phoneStmt, SQLSRV_FETCH_ASSOC)) {
            $customerId = $phoneRow['CustomerID'];
        } else {
            // Create new customer and get CustomerID reliably using OUTPUT INSERTED
            // Try to split name into first/last if available
            $firstName = '';
            $lastName = '';
            if ($customerName !== '') {
                $parts = preg_split('/\s+/', $customerName, 2);
                $firstName = $parts[0] ?? $customerName;
                $lastName  = $parts[1] ?? '';
            } else {
                // fallback to posted fields if present
                $firstName = trim($_POST['customer_first'] ?? 'Customer');
                $lastName  = trim($_POST['customer_last'] ?? '');
            }

            $insertCust = "INSERT INTO mn.Customers (FirstName, LastName, PhoneNumber) OUTPUT INSERTED.CustomerID VALUES (?, ?, ?)";
            $custStmt = sqlsrv_query($conn, $insertCust, [$firstName, $lastName, $customerPhone]);
            if ($custStmt && sqlsrv_fetch($custStmt)) {
                // get inserted CustomerID reliably
                $customerId = sqlsrv_get_field($custStmt, 0);
            } else {
                $errors[] = "Failed to create or retrieve new customer.";
            }
        }
    }

    $debitLines = [];
    for ($i = 0; $i < count($debitAccounts); $i++) {
        if ($debitAccounts[$i] !== '' && $debitAmounts[$i] !== '') {
            $debitLines[] = ['account' => intval($debitAccounts[$i]), 'amount' => floatval($debitAmounts[$i])];
        }
    }

    $creditLines = [];
    for ($i = 0; $i < count($creditAccounts); $i++) {
        if ($creditAccounts[$i] !== '' && $creditAmounts[$i] !== '') {
            $creditLines[] = ['account' => intval($creditAccounts[$i]), 'amount' => floatval($creditAmounts[$i])];
        }
    }

    $totalDebit = array_sum(array_column($debitLines, 'amount'));
    $totalCredit = array_sum(array_column($creditLines, 'amount'));

    if (empty($description)) $errors[] = "Description is required.";
    if (empty($debitLines)) $errors[] = "At least one debit line is required.";
    if (empty($creditLines)) $errors[] = "At least one credit line is required.";
    if (abs($totalDebit - $totalCredit) > 0.01) $errors[] = "Debits and credits must balance.";

    // ========== UPDATED VALIDATION: ALLOW DECREASING ENTRIES ==========
    // Skip validation for closing entries since they follow special accounting rules
    if ($auto !== 'closing') {
        foreach ($debitLines as $line) {
            $normalSide = getAccountNormalSide($line['account'], $conn);
            if ($normalSide === 'Credit') {
                $warnings[] = "Debiting a credit-normal-balance account (Account ID: " . $line['account'] . ") - This decreases the account balance";
            }
        }

        foreach ($creditLines as $line) {
            $normalSide = getAccountNormalSide($line['account'], $conn);
            if ($normalSide === 'Debit') {
                $warnings[] = "Crediting a debit-normal-balance account (Account ID: " . $line['account'] . ") - This decreases the account balance";
            }
        }
        
        // Warnings are collected but don't prevent submission
        // They'll be displayed to the user as informational messages
    }
    // ========== END OF UPDATED VALIDATION ==========

    if (empty($errors)) {
        sqlsrv_begin_transaction($conn);

        // Insert journal entry and retrieve EntryID reliably using OUTPUT + sqlsrv_fetch/sqlsrv_get_field
        if ($customerId !== null) {
            $insertEntry = "INSERT INTO mn.JournalEntries (Description, CustomerID) OUTPUT INSERTED.EntryID VALUES (?, ?)";
            $stmt = sqlsrv_query($conn, $insertEntry, [$description, $customerId]);
        } else {
            $insertEntry = "INSERT INTO mn.JournalEntries (Description) OUTPUT INSERTED.EntryID VALUES (?)";
            $stmt = sqlsrv_query($conn, $insertEntry, [$description]);
        }

        if ($stmt && sqlsrv_fetch($stmt)) {
            $entryID = sqlsrv_get_field($stmt, 0);

            $success = true;
            foreach ($debitLines as $line) {
                $ok = sqlsrv_query($conn, "INSERT INTO mn.Ledger (EntryID, AccountID, Debit) VALUES (?, ?, ?)", [$entryID, $line['account'], $line['amount']]);
                if (!$ok) $success = false;
            }
            foreach ($creditLines as $line) {
                $ok = sqlsrv_query($conn, "INSERT INTO mn.Ledger (EntryID, AccountID, Credit) VALUES (?, ?, ?)", [$entryID, $line['account'], $line['amount']]);
                if (!$ok) $success = false;
            }

            if ($success) {
                sqlsrv_commit($conn);
                $successMessage = "✅ Journal entry posted successfully!";
                $auto = '';
                $_SESSION['closing_debits'] = [];
                $_SESSION['closing_credits'] = [];
                // Clear form
                $prefillDesc = '';
                $debitLines = [['account' => '', 'amount' => '']];
                $creditLines = [['account' => '', 'amount' => '']];
                $totalDebit = 0;
                $totalCredit = 0;
                // Also clear customer prefill data
                $prefillPhone = '';
                $prefillName = '';
                // Clear warnings on successful submission
                $warnings = [];
            } else {
                sqlsrv_rollback($conn);
                $errors[] = "Failed to insert ledger lines.";
            }
        } else {
            sqlsrv_rollback($conn);
            $errors[] = "Failed to insert journal entry.";
        }
    }
}

// ===================== FINAL PREPARE LINES =====================
if ($auto === 'closing') {
    $debitLines = $_SESSION['closing_debits'] ?? [];
    $creditLines = $_SESSION['closing_credits'] ?? [];
} else {
    if (empty($debitLines)) $debitLines = [['account' => '', 'amount' => '']];
    if (empty($creditLines)) $creditLines = [['account' => '', 'amount' => '']];
}

// Load accounts
$accounts = [];
$q = sqlsrv_query($conn, "SELECT AccountID, AccountName FROM mn.Accounts ORDER BY AccountName");
while ($row = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) {
    $accounts[] = $row;
}
// Recent 5 entries
$whereClause = ''; // no filter
$params = [];
$summary = sqlsrv_query($conn, "
    SELECT TOP 5 je.EntryID, je.Description, FORMAT(je.EntryDate, 'yyyy-MM-dd') AS EntryDate,
           SUM(ISNULL(l.Debit,0)) AS TotalDebit, SUM(ISNULL(l.Credit,0)) AS TotalCredit
    FROM mn.JournalEntries je
    LEFT JOIN mn.Ledger l ON je.EntryID = l.EntryID
    $whereClause
    GROUP BY je.EntryID, je.Description, je.EntryDate
    ORDER BY je.EntryDate DESC
", $params);

?>

<!DOCTYPE html>
<html>
<head>
    <title>Journal Entry</title>
    <link rel="stylesheet" href="../css/base.css">
    <link rel="stylesheet" href="../css/buttons.css">
    <link rel="stylesheet" href="../css/forms.css">
    <link rel="stylesheet" href="../css/tables.css">
    <link rel="stylesheet" href="../css/nav.css">
    <link rel="stylesheet" href="../css/messages.css">
</head>
<body class="bg-light">
<div class="container">
    <h2 class="mb-4">📘 Journal Entry</h2>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul><?php foreach ($errors as $e) echo "<li>$e</li>"; ?></ul>
        </div>
    <?php endif; ?>

    <?php if (!empty($warnings)): ?>
        <div class="alert alert-warning">
            <strong>Note:</strong> This entry includes decreasing account balances:
            <ul><?php foreach ($warnings as $w) echo "<li>$w</li>"; ?></ul>
        </div>
    <?php endif; ?>

    <?php if ($successMessage): ?>
        <div class="alert alert-success"><?= $successMessage ?></div>
    <?php endif; ?>

    <!-- Added Closing Entry button here -->
    <div class="form-group">
        <a href="journal_entry.php?auto=closing" class="btn btn-warning">🧾 Create Closing Entry</a>
    </div>

   <form method="POST" class="journal-form">
    <div class="form-group">
        <label for="desc">💬 Description</label><br>
        <input type="text" id="desc" name="description" required value="<?= htmlspecialchars($prefillDesc) ?>" >
    </div>

    <!-- === CUSTOMER PHONE INPUT ADDED HERE === -->
    <div class="form-group">
        <label for="customer_phone">📞</label><br>
       <input type="text" id="customer_phone" name="customer_phone" placeholder="Enter customer phone number to link" value="<?= htmlspecialchars($_POST['customer_phone'] ?? $prefillPhone) ?>" >
    </div>

    <div class="journal-entry-section">
        <!-- Debit Section -->
        <div class="journal-block">
            <h4>📥 Debit Entries</h4>
            <button type="button" id="addDebit" style="margin-bottom: 10px; padding: 6px 12px; background-color: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer;">+ Add Debit</button>
            <div id="debitLines">
                <?php foreach ($debitLines as $line): ?>
                <div class="input-group mb-2">
                    <select name="debit_account[]" required style="padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
                        <option value="">Select Account</option>
                        <?php foreach ($accounts as $acc): ?>
                            <option value="<?= $acc['AccountID'] ?>" <?= ($acc['AccountID'] == $line['account']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($acc['AccountName']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="number" step="0.01" name="debit_amount[]" placeholder="Amount" value="<?= htmlspecialchars($line['amount']) ?>" required style="padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
                    <span class="remove-line-btn">×</span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Credit Section -->
        <div class="journal-block">
            <h4>📤 Credit Entries</h4>
            <button type="button" id="addCredit" style="margin-bottom: 10px; padding: 6px 12px; background-color: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer;">+ Add Credit</button>
            <div id="creditLines">
                <?php foreach ($creditLines as $line): ?>
                <div class="input-group mb-2">
                    <select name="credit_account[]" required style="padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
                        <option value="">Select Account</option>
                        <?php foreach ($accounts as $acc): ?>
                            <option value="<?= $acc['AccountID'] ?>" <?= ($acc['AccountID'] == $line['account']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($acc['AccountName']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="number" step="0.01" name="credit_amount[]" placeholder="Amount" value="<?= htmlspecialchars($line['amount']) ?>" required style="padding: 6px; border: 1px solid #ccc; border-radius: 4px;">
                    <span class="remove-line-btn">×</span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div style="text-align: center; margin-top: 30px;">
        <button type="submit" style="padding: 12px 24px; background-color: #007bff; color: white; border: none; border-radius: 6px; font-size: 16px;">✅ Post Journal Entry</button>
    </div>
</form>

    <hr class="my-5">

    <h4>Recent Journal Entries</h4>
    <table class="table table-bordered">
        <thead>
        <tr><th>ID</th><th>Description</th><th>Date</th><th>Total Debit</th><th>Total Credit</th></tr>
        </thead>
        <tbody>
        <?php if (isset($summary) && $summary): while ($r = sqlsrv_fetch_array($summary, SQLSRV_FETCH_ASSOC)): ?>
            <tr>
                <td><?= $r['EntryID'] ?></td>
                <td><?= htmlspecialchars($r['Description']) ?></td>
                <td><?= $r['EntryDate'] ?></td>
                <td><?= number_format($r['TotalDebit'], 2) ?></td>
                <td><?= number_format($r['TotalCredit'], 2) ?></td>
            </tr>
        <?php endwhile; else: ?>
            <tr><td colspan="5">No entries found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>

   <div class="bottom-actions">
   <a href="../dashboard.php" class="btn back-btn-fixed">← Back to Dashboard</a>
   <a href="../inventory/add_spare_part.php" class="btn btn-success">➕ Add Spare Part</a>
   <a href="../inventory/issue_part.php" class="btn btn-success">📤 Issue Spare Part</a>
</div>

</div>

<script>
    const debitContainer = document.getElementById('debitLines');
    const creditContainer = document.getElementById('creditLines');

    function createLine(type) {
        const div = document.createElement('div');
        div.className = 'input-group mb-2';

        const accountSelect = document.createElement('select');
        accountSelect.name = `${type}_account[]`;
        accountSelect.className = 'form-select';
        accountSelect.required = true;
        accountSelect.innerHTML = `<option value="">Select Account</option><?php
            foreach ($accounts as $a) {
                echo "<option value='{$a['AccountID']}'>" . htmlspecialchars($a['AccountName']) . "</option>";
            }
        ?>`;
        div.appendChild(accountSelect);

        const amountInput = document.createElement('input');
        amountInput.type = 'number';
        amountInput.step = '0.01';
        amountInput.name = `${type}_amount[]`;
        amountInput.className = 'form-control';
        amountInput.placeholder = 'Amount';
        amountInput.required = true;
        div.appendChild(amountInput);

        const removeSpan = document.createElement('span');
        removeSpan.className = 'remove-line-btn';
        removeSpan.textContent = '×';
        removeSpan.onclick = () => div.remove();
        div.appendChild(removeSpan);

        return div;
    }

    document.getElementById('addDebit').onclick = () => debitContainer.appendChild(createLine('debit'));
    document.getElementById('addCredit').onclick = () => creditContainer.appendChild(createLine('credit'));

    // Attach remove events to existing remove buttons
    document.querySelectorAll('.remove-line-btn').forEach(btn => btn.onclick = () => btn.parentElement.remove());
</script>

</body>
</html>