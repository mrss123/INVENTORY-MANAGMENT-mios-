<?php
session_start();
include_once(__DIR__ . "/../includes/db_connect.php");

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = sqlsrv_query($conn, "SELECT * FROM mn.Users WHERE Username = ?", [$username]);
    $user = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

    if ($user && password_verify($password, $user['PasswordHash'])) {
        $_SESSION['user'] = $user['Username'];
        header("Location: ../dashboard.php");
        exit();
    } else {
        $error = "❌ Invalid username or password.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <link rel="stylesheet" href="../css/base.css">
    <link rel="stylesheet" href="../css/buttons.css">
    <link rel="stylesheet" href="../css/forms.css">
    <link rel="stylesheet" href="../css/tables.css">
    <link rel="stylesheet" href="../css/nav.css">
    <link rel="stylesheet" href="../css/messages.css">
</head>
<body class="bg-light">
<div class="container py-5">
    <h2 class="mb-4">🔐 Login to SP1996</h2>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Username</label>
            <input type="text" name="username" required class="form-control" style="border-radius: 10px;">
        </div>
        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" required class="form-control" style="border-radius: 10px; padding: 8px">
        </div>
        <button type="submit" class="btn btn-primary">Login</button>
    </form>
</div>
</body>
</html>
