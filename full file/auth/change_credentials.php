<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

include_once(__DIR__ . "/../includes/db_connect.php");

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentUser = $_SESSION['user'];
    $currentPassword = $_POST['current_password'] ?? '';
    $newUsername = trim($_POST['username'] ?? '');
    $newPassword = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Fetch existing user
    $stmt = sqlsrv_query($conn, "SELECT * FROM mn.Users WHERE Username = ?", [$currentUser]);
    $user = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);

    // Check current password
    if (!$user || !password_verify($currentPassword, $user['PasswordHash'])) {
        $error = "❌ Current password is incorrect.";
    }
    // Validate new fields
    elseif ($newUsername === '' || $newPassword === '') {
        $error = "❌ Username and password cannot be empty.";
    }
    elseif ($newPassword !== $confirmPassword) {
        $error = "❌ New password and confirmation do not match.";
    }
    elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $newPassword)) {
        $error = "❌ Password must be at least 8 characters long and include uppercase, lowercase, and a number.";
    }
    else {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $update = sqlsrv_query($conn, "UPDATE mn.Users SET Username = ?, PasswordHash = ? WHERE Username = ?", [$newUsername, $hash, $currentUser]);

        if ($update) {
            $_SESSION['user'] = $newUsername;
            $success = "✅ Credentials updated successfully!";
        } else {
            $error = "❌ Failed to update credentials.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Change Username & Password</title>
     <link rel="stylesheet" href="../css/base.css">
    <link rel="stylesheet" href="../css/buttons.css">
    <link rel="stylesheet" href="../css/forms.css">
    <link rel="stylesheet" href="../css/tables.css">
    <link rel="stylesheet" href="../css/nav.css">
    <link rel="stylesheet" href="../css/messages.css">
</head>
<body class="bg-light">
<div class="container py-5">
    <h2 class="mb-4">🔑 Change Username and Password</h2>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php elseif ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Current Password</label>
            <input type="password" name="current_password" required class="form-control">
        </div>
        <div class="form-group">
            <label>New Username</label>
            <input type="text" name="username" required class="form-control" value="<?= htmlspecialchars($_SESSION['user']) ?>">
        </div>
        <div class="form-group">
            <label>New Password</label>
            <input type="password" name="password" required class="form-control">
        </div>
        <div class="form-group">
            <label>Confirm New Password</label>
            <input type="password" name="confirm_password" required class="form-control">
        </div>
        <button type="submit" class="btn btn-primary">Update</button>
        <a href="../dashboard.php" class="btn back-btn-fixed">← Back to Dashboard</a>
    </form>
</div>
</body>
</html>
