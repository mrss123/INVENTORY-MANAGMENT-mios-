<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../auth/login.php");
    exit();
}
include_once("../includes/db_connect.php");

$labels = [];
$revenues = [];

$sql = "
    SELECT 
        FORMAT(DateIssued, 'yyyy-MM') AS Month,
        SUM(QuantityIssued * SellingPrice) AS TotalRevenue
    FROM mn.IssuedParts
    GROUP BY FORMAT(DateIssued, 'yyyy-MM')
    ORDER BY Month;
";

$result = sqlsrv_query($conn, $sql);
if ($result) {
    while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) {
        $labels[] = $row['Month'];
        $revenues[] = round($row['TotalRevenue'], 2);
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
    <title>Sales Chart</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
   
    <link rel="stylesheet" href="../css/buttons.css">
        <link rel="stylesheet" href="../css/chart.css">

</head>
<body>
    <div class="chart-container">
        <h2>📈 Monthly Sales Revenue Chart</h2>
        <canvas id="salesChart" height="100"></canvas>
    </div>

    <div class="back-link">
        <a href="../dashboard.php" class="btn back-btn-fixed">← Back to Dashboard</a>
    </div>

    <script>
        const labels = <?php echo json_encode($labels); ?>;
        const data = <?php echo json_encode($revenues); ?>;

        const ctx = document.getElementById('salesChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Revenue (ETB)',
                    data: data,
                    backgroundColor: '#28a745'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: context => `ETB ${context.parsed.y}`
                        }
                    },
                    legend: { display: false }
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
    </script>
</body>
</html>
