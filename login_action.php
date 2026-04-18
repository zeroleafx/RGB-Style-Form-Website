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

    // Check if user is banned
    $banned_until = $user['banned_until'] ?? null;
    $is_permanent_ban = (int)($user['is_permanent_ban'] ?? 0);

    if ($is_permanent_ban === 1) {
        $msg = "Permanent Ban";
        if (!empty($user['ban_reason'])) {
            $msg .= " - " . htmlspecialchars($user['ban_reason']);
        }
        echo json_encode([
            "status" => "error",
            "message" => $msg
        ]);
        mysqli_stmt_close($stmt);
        mysqli_close($conn);
        exit;
    }

    if ($banned_until !== null) {
        $ban_time = strtotime($banned_until);
        if ($ban_time > time()) {
            $msg = "You have been banned until " . date('Y-m-d H:i', $ban_time);
            if (!empty($user['ban_reason'])) {
                $msg .= " - " . htmlspecialchars($user['ban_reason']);
            }
            echo json_encode([
                "status" => "error",
                "message" => $msg
            ]);
            mysqli_stmt_close($stmt);
            mysqli_close($conn);
            exit;
        } else {
            // Auto-unban expired temporary ban
            $unban_sql = "UPDATE users SET banned_until = NULL WHERE id = ?";
            $unban_stmt = mysqli_prepare($conn, $unban_sql);
            if ($unban_stmt) {
                mysqli_stmt_bind_param($unban_stmt, "i", $user['id']);
                mysqli_stmt_execute($unban_stmt);
                mysqli_stmt_close($unban_stmt);
            }
        }
    }

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