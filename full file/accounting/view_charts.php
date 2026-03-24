<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../auth/login.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>📈 Chart Menu - SP1996</title>
    <link rel="stylesheet" href="../css/base.css">
    <link rel="stylesheet" href="../css/buttons.css">
    <link rel="stylesheet" href="../css/forms.css">
    <link rel="stylesheet" href="../css/tables.css">
    <link rel="stylesheet" href="../css/nav.css">
    <link rel="stylesheet" href="../css/messages.css">
    <link rel="stylesheet" href="../css/dashboard.css">
</head>
<body>

  <h2>📈 View Accounting Charts</h2>

<div class="tab-content active">
    <ul>
        <li><a href="sales_chart.php">📊 View Monthly Sales Chart</a></li>
        <li><a href="assets_chart.php">📊 View Assets Chart</a></li>
        <!-- More chart links will be added here in the future -->
    </ul>
</div>

<a href="../dashboard.php" class="btn back-btn-fixed">← Back to Dashboard</a>


  

</body>
</html>
