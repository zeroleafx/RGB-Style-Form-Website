<?php
session_start();
require_once "db.php";

// Check admin permission
$is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
if (!$is_admin) {
    exit("只有管理員可以進行此操作");
}

$user_id = (int)($_POST['user_id'] ?? 0);

if ($user_id <= 0) {
    header("Location: manage_users.php?msg=error");
    exit;
}

// Verify user exists and is not current admin (safety check)
if ($user_id === $_SESSION['user_id']) {
    header("Location: manage_users.php?msg=error");
    exit;
}

mysqli_begin_transaction($conn);

try {
    // Get user's quest IDs first
    $quest_sql = "SELECT id FROM quests WHERE created_by = ?";
    $quest_stmt = mysqli_prepare($conn, $quest_sql);
    if (!$quest_stmt) throw new Exception("Quest查詢造備失敗：" . mysqli_error($conn));
    mysqli_stmt_bind_param($quest_stmt, "i", $user_id);
    mysqli_stmt_execute($quest_stmt);
    $quest_result = mysqli_stmt_get_result($quest_stmt);
    $quest_ids = [];
    while ($row = mysqli_fetch_assoc($quest_result)) {
        $quest_ids[] = (int)$row['id'];
    }
    mysqli_stmt_close($quest_stmt);

    // Delete in order: quest_answers -> quest_responses -> quest_field_options -> quest_fields -> quests -> users

    // 1. Delete quest_answers (from responses for this user's quests)
    if (!empty($quest_ids)) {
        $placeholders = implode(',', $quest_ids);
        $sql = "DELETE FROM quest_answers
                WHERE response_id IN (
                    SELECT id FROM quest_responses WHERE quest_id IN ($placeholders)
                )";
        if (!mysqli_query($conn, $sql)) throw new Exception("刪除quest_answers失敗：" . mysqli_error($conn));
    }

    // 2. Delete quest_responses for this user
    $sql = "DELETE FROM quest_responses WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) throw new Exception("SQL準備失敗：" . mysqli_error($conn));
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    if (!mysqli_stmt_execute($stmt)) throw new Exception("刪除quest_responses失敗：" . mysqli_stmt_error($stmt));
    mysqli_stmt_close($stmt);

    // 3. Delete quest_field_options (from fields in user's quests)
    if (!empty($quest_ids)) {
        $placeholders = implode(',', $quest_ids);
        $sql = "DELETE FROM quest_field_options
                WHERE field_id IN (
                    SELECT id FROM quest_fields WHERE quest_id IN ($placeholders)
                )";
        if (!mysqli_query($conn, $sql)) throw new Exception("刪除quest_field_options失敗：" . mysqli_error($conn));
    }

    // 4. Delete quest_fields (from user's quests)
    if (!empty($quest_ids)) {
        $placeholders = implode(',', $quest_ids);
        $sql = "DELETE FROM quest_fields WHERE quest_id IN ($placeholders)";
        if (!mysqli_query($conn, $sql)) throw new Exception("刪除quest_fields失敗：" . mysqli_error($conn));
    }

    // 5. Delete quests (created by user)
    $sql = "DELETE FROM quests WHERE created_by = ?";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) throw new Exception("SQL準備失敗：" . mysqli_error($conn));
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    if (!mysqli_stmt_execute($stmt)) throw new Exception("刪除quests失敗：" . mysqli_stmt_error($stmt));
    mysqli_stmt_close($stmt);

    // 6. Delete from quest_responses where user_id (adventurer's applications)
    $sql = "DELETE FROM quest_responses WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) throw new Exception("SQL準備失敗：" . mysqli_error($conn));
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    if (!mysqli_stmt_execute($stmt)) throw new Exception("刪除quest_responses失敗：" . mysqli_stmt_error($stmt));
    mysqli_stmt_close($stmt);

    // 7. Finally delete user
    $sql = "DELETE FROM users WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) throw new Exception("SQL準備失敗：" . mysqli_error($conn));
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    if (!mysqli_stmt_execute($stmt)) throw new Exception("刪除user失敗：" . mysqli_stmt_error($stmt));
    mysqli_stmt_close($stmt);

    mysqli_commit($conn);
    header("Location: manage_users.php?msg=deleted");
    exit;

} catch (Throwable $e) {
    mysqli_rollback($conn);
    error_log("DELETE USER ERROR: " . $e->getMessage());
    header("Location: manage_users.php?msg=error");
    exit;
}
?>
