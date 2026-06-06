<?php
require_once "db.php";

$username = $_POST['username'];

if ($username != '' && strlen($username) < 3) {
    echo "❌ Username must be at least 3 characters";
    exit;
}

$sql = "SELECT * FROM users WHERE username = ?";
$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    echo "❌ Unable to check username";
    exit;
}

mysqli_stmt_bind_param($stmt, "s", $username);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) > 0) {
    echo "❌ Username is taken";
} else {
    if ($username != '' && strlen($username) >= 3)
        echo "✅ Username Available";
    else
        echo "❌ Username must be at least 3 characters";
}

mysqli_stmt_close($stmt);
?>