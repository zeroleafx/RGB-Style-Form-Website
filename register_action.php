<?php
require_once "db.php";

$member_group = trim($_POST['member_group'] ?? '');
$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');
$confirm_password = trim($_POST['confirm_password'] ?? '');
$agree_terms = isset($_POST['agree_terms']) ? $_POST['agree_terms'] : '';

if (!$member_group) {
    echo "Please select a member group.";
    exit;
}

if (strlen($username) < 3) {
    echo "Username must be at least 3 characters.";
    exit;
}

if (strlen($password) < 8) {
    echo "Password must be at least 8 characters long.";
    exit;
}

if ($password !== $confirm_password) {
    echo "Passwords do not match.";
    exit;
}

if ($agree_terms !== '1') {
    echo "Please agree to the terms and conditions.";
    exit;
}

$check_sql = "SELECT id FROM users WHERE username = ?";
$check_stmt = mysqli_prepare($conn, $check_sql);

if (!$check_stmt) {
    echo "Registration failed: " . mysqli_error($conn);
    exit;
}

mysqli_stmt_bind_param($check_stmt, "s", $username);
mysqli_stmt_execute($check_stmt);
$check_result = mysqli_stmt_get_result($check_stmt);

if ($check_result && mysqli_num_rows($check_result) > 0) {
    mysqli_stmt_close($check_stmt);
    echo "❌ Username is taken.";
    exit;
}

mysqli_stmt_close($check_stmt);

$sql = "INSERT INTO users (member_group, username, password) VALUES (?, ?, ?)";
$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    echo "Registration failed: " . mysqli_error($conn);
    exit;
}

mysqli_stmt_bind_param($stmt, "sss", $member_group, $username, $password);

if (mysqli_stmt_execute($stmt)) {
    echo "Registration successful.";
} else {
    echo "Registration failed: " . mysqli_error($conn);
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>