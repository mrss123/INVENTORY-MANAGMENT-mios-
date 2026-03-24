<?php
// You may pass data from session or DB before generating the receipt
session_start();
include_once("../includes/db_connect.php");

// Example data placeholders – replace with real logic
$receiptId = $_SESSION['last_receipt_id'] ?? 'R' . date("YmdHis");
$customerName = $_SESSION['last_customer_name'] ?? 'Walk-in Customer';
$issueDate = $_SESSION['last_issue_date'] ?? date("Y-m-d H:i");
$items = $_SESSION['last_issued_items'] ?? []; // each item: ['part_number', 'description', 'quantity', 'unit_price', 'total_price']
$totalAmount = $_SESSION['last_total_amount'] ?? 0.00;
$preparedBy = $_SESSION['user']['username'] ?? 'System';

?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Spare Part Receipt - <?php echo $receiptId; ?></title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            font-size: 12px;
            margin: 20px;
        }

        .receipt-box {
            max-width: 700px;
            margin: auto;
            padding: 20px;
            border: 1px solid #ddd;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        h2 {
            margin-bottom: 0;
        }

        .details, .footer {
            margin-top: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        table th, table td {
            border: 1px solid #ccc;
            padding: 6px 8px;
            text-align: left;
        }

        table th {
            background-color: #f0f0f0;
        }

        .total {
            font-weight: bold;
            text-align: right;
            margin-top: 10px;
        }

        .footer {
            text-align: center;
            font-size: 11px;
            margin-top: 40px;
            color: #888;
        }
    </style>
</head>
<body>
    <div class="receipt-box">
        <div class="header">
            <h2>NY PLC - Spare Part Receipt</h2>
            <p><strong>Receipt ID:</strong> <?php echo $receiptId; ?><br>
            <strong>Date:</strong> <?php echo $issueDate; ?><br>
            <strong>Customer:</strong> <?php echo htmlspecialchars($customerName); ?></p>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Part Number</th>
                    <th>Description</th>
                    <th>Qty</th>
                    <th>Unit Price</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['part_number']); ?></td>
                    <td><?php echo htmlspecialchars($item['description']); ?></td>
                    <td><?php echo $item['quantity']; ?></td>
                    <td><?php echo number_format($item['unit_price'], 2); ?></td>
                    <td><?php echo number_format($item['total_price'], 2); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <p class="total">Total Amount: <?php echo number_format($totalAmount, 2); ?> ETB</p>

        <div class="details">
            <p><strong>Prepared By:</strong> <?php echo htmlspecialchars($preparedBy); ?></p>
        </div>

        <div class="footer">
            Thank you for your business!<br>
            NY PLC - Akaki Kaliti, Around Maru Metals, Ethiopia<br>
            Tel: 0939887556 / 0920146879 | Email: nyplc@gmail.com
        </div>
    </div>
</body>
</html>
