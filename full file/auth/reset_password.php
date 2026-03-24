<?php
include_once("../includes/db_connect.php");

$username = 'admin';
$newPassword = 'admin123'; // You can change this
$hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);

// Update the password in the database
$sql = "UPDATE mn.Users SET PasswordHash = ? WHERE Username = ?";
$params = [$hashedPassword, $username];

$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt) {
    echo "✅ Password for '$username' reset to '$newPassword'";
} else {
    echo "❌ Error: ";
    print_r(sqlsrv_errors());
}
?>
<?php
include_once("../includes/db_connect.php");

$username = 'admin';
$newPassword = 'admin123'; // You can change this
$hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);

// Update the password in the database
$sql = "UPDATE mn.Users SET PasswordHash = ? WHERE Username = ?";
$params = [$hashedPassword, $username];

$stmt = sqlsrv_query($conn, $sql, $params);

if ($stmt) {
    echo "✅ Password for '$username' reset to '$newPassword'";
} else {
    echo "❌ Error: ";
    print_r(sqlsrv_errors());
}
?>
