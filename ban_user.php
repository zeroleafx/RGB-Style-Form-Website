<?php
session_start();
require_once "db.php";

// Check admin permission
$is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
if (!$is_admin) {
    exit("只有管理員可以進行此操作");
}

$action = trim($_POST['action'] ?? '');
$user_id = (int)($_POST['user_id'] ?? 0);
$ban_type = $_POST['ban_type'] ?? 'temporary';
$days = (int)($_POST['days'] ?? 1);
$reason = trim($_POST['reason'] ?? '');

if ($user_id <= 0) {
    header("Location: manage_users.php?msg=error");
    exit;
}

// Verify user exists
$check_sql = "SELECT id FROM users WHERE id = ?";
$check_stmt = mysqli_prepare($conn, $check_sql);
mysqli_stmt_bind_param($check_stmt, "i", $user_id);
mysqli_stmt_execute($check_stmt);
$check_result = mysqli_stmt_get_result($check_stmt);

if (mysqli_num_rows($check_result) === 0) {
    mysqli_stmt_close($check_stmt);
    header("Location: manage_users.php?msg=error");
    exit;
}
mysqli_stmt_close($check_stmt);

mysqli_begin_transaction($conn);

try {
    if ($action === 'ban') {
        if ($ban_type === 'permanent') {
            // Permanent ban
            $sql = "UPDATE users SET is_permanent_ban = 1, ban_reason = ? WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            if (!$stmt) throw new Exception("SQL準備失敗：" . mysqli_error($conn));
            mysqli_stmt_bind_param($stmt, "si", $reason, $user_id);
            if (!mysqli_stmt_execute($stmt)) throw new Exception("ban失敗：" . mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
        } else {
            // Temporary ban
            if ($days <= 0) $days = 1;
            $banned_until = date('Y-m-d H:i:s', strtotime("+$days days"));

            $sql = "UPDATE users SET banned_until = ?, is_permanent_ban = 0, ban_reason = ? WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            if (!$stmt) throw new Exception("SQL準備失敗：" . mysqli_error($conn));
            mysqli_stmt_bind_param($stmt, "ssi", $banned_until, $reason, $user_id);
            if (!mysqli_stmt_execute($stmt)) throw new Exception("ban失敗：" . mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
        }
        $msg = 'banned';
    } elseif ($action === 'unban') {
        // Remove ban
        $sql = "UPDATE users SET banned_until = NULL, is_permanent_ban = 0, ban_reason = NULL WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) throw new Exception("SQL準備失敗：" . mysqli_error($conn));
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        if (!mysqli_stmt_execute($stmt)) throw new Exception("unban失敗：" . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        $msg = 'unbanned';
    } else {
        throw new Exception("無效的操作");
    }

    mysqli_commit($conn);
    header("Location: manage_users.php?msg=$msg");
    exit;

} catch (Throwable $e) {
    mysqli_rollback($conn);
    error_log("BAN_USER_ERROR: " . $e->getMessage());
    header("Location: manage_users.php?msg=error");
    exit;
}
?>
