<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../auth/login.php");
    exit();
}
include_once("../includes/db_connect.php");

$labels = [];
$values = [];

$sql = "
SELECT a.AccountName,
       SUM(ISNULL(l.Debit,0)) - SUM(ISNULL(l.Credit,0)) AS Balance
FROM mn.Accounts a
LEFT JOIN mn.Ledger l ON a.AccountID = l.AccountID
WHERE a.AccountType = 'Asset'
GROUP BY a.AccountName
ORDER BY a.AccountName;
";

$result = sqlsrv_query($conn, $sql);
if ($result) {
    while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
        $labels[] = $row['AccountName'];
        $values[] = round($row['Balance'], 2);
    }
} else {
    echo "<p style='color:red;'>❌ SQL Error:</p><pre>";
    print_r(sqlsrv_errors());
    echo "</pre>";
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Asset Charts</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
   
    <link rel="stylesheet" href="../css/buttons.css">
    <link rel="stylesheet" href="../css/chart.css">

    
</head>
<body>

    <!-- Fixed Back to Dashboard button -->
    <a href="../dashboard.php" class="btn back-btn-fixed">← Back to Dashboard</a>


    <div class="chart-container">
        <h2>📊 Asset Distribution - Bar Chart</h2>
        <canvas id="barChart" height="100"></canvas>
    </div>

    <div class="chart-container">
        <h2>🥧 Asset Distribution - Pie Chart</h2>
        <canvas id="pieChart" height="100"></canvas>
    </div>

    <script>
        const labels = <?php echo json_encode($labels); ?>;
        const data = <?php echo json_encode($values); ?>;

        const barCtx = document.getElementById('barChart').getContext('2d');
        const barChart = new Chart(barCtx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'ETB',
                    data: data,
                    backgroundColor: '#007BFF'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: ctx => `ETB ${ctx.parsed.y}` } }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: value => `ETB ${value}`
                        }
                    }
                }
            }
        });

        const pieCtx = document.getElementById('pieChart').getContext('2d');
        const pieChart = new Chart(pieCtx, {
            type: 'pie',
            data: {
                labels: labels,
                datasets: [{
                    label: 'ETB',
                    data: data,
                    backgroundColor: [
                        '#007BFF', '#28a745', '#ffc107', '#dc3545',
                        '#17a2b8', '#6f42c1', '#fd7e14', '#20c997'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    tooltip: { callbacks: { label: ctx => `${ctx.label}: ETB ${ctx.parsed}` } }
                }
            }
        });
    </script>

</body>
</html>
