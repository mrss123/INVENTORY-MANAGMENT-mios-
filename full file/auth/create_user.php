<?php
// Correct relative path from /auth to /includes
include_once(__DIR__ . "/../includes/db_connect.php");

// Test user credentials
$username = 'admin';
$password = 'admin123';
$hash = password_hash($password, PASSWORD_DEFAULT);

// Insert into mn.Users
$stmt = sqlsrv_query($conn, "INSERT INTO mn.Users (Username, PasswordHash) VALUES (?, ?)", [$username, $hash]);

if ($stmt) {
    echo "✅ User 'admin' created successfully.";
} else {
    echo "❌ Error:<br>";
    print_r(sqlsrv_errors());
}
?>
