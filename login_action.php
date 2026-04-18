<?php
session_start();
require_once "db.php";

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        "status" => "error",
        "message" => "非法請求"
    ]);
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

if ($username === '' || $password === '') {
    echo json_encode([
        "status" => "error",
        "message" => "請輸入帳號與密碼"
    ]);
    exit;
}

$sql = "SELECT * FROM users WHERE username = ? AND password = ?";
$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    echo json_encode([
        "status" => "error",
        "message" => "SQL準備失敗：" . mysqli_error($conn)
    ]);
    exit;
}

mysqli_stmt_bind_param($stmt, "ss", $username, $password);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($result && mysqli_num_rows($result) === 1) {
    $user = mysqli_fetch_assoc($result);

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['member_group'] = $user['member_group'];
    $_SESSION['level'] = $user['level'];
    $_SESSION['exp'] = $user['exp'];

    echo json_encode([
        "status" => "success",
        "message" => "Login Successfully",
        "redirect" => "index.php"
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Incorrect username or password"
    ]);
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>