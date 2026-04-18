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
    // Step 1: Delete ALL quest_answers that belong to ANY responses from ANY user for this user's quests
    $sql = "DELETE qa FROM quest_answers qa
            INNER JOIN quest_fields qf ON qa.field_id = qf.id
            INNER JOIN quests q ON qf.quest_id = q.id
            WHERE q.created_by = ?";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) throw new Exception("刪除quest_answers(user quests) SQL準備失敗：" . mysqli_error($conn));
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    if (!mysqli_stmt_execute($stmt)) throw new Exception("刪除quest_answers(user quests)失敗：" . mysqli_stmt_error($stmt));
    mysqli_stmt_close($stmt);

    // Step 2: Delete quest_answers from user's own quest applications
    $sql = "DELETE qa FROM quest_answers qa
            INNER JOIN quest_responses qr ON qa.response_id = qr.id
            WHERE qr.user_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) throw new Exception("刪除quest_answers(user responses) SQL準備失敗：" . mysqli_error($conn));
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    if (!mysqli_stmt_execute($stmt)) throw new Exception("刪除quest_answers(user responses)失敗：" . mysqli_stmt_error($stmt));
    mysqli_stmt_close($stmt);

    // Step 3: Delete quest_field_options from user's quests
    $sql = "DELETE qfo FROM quest_field_options qfo
            INNER JOIN quest_fields qf ON qfo.field_id = qf.id
            INNER JOIN quests q ON qf.quest_id = q.id
            WHERE q.created_by = ?";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) throw new Exception("刪除quest_field_options SQL準備失敗：" . mysqli_error($conn));
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    if (!mysqli_stmt_execute($stmt)) throw new Exception("刪除quest_field_options失敗：" . mysqli_stmt_error($stmt));
    mysqli_stmt_close($stmt);

    // Step 4: Delete quest_fields from user's quests
    $sql = "DELETE qf FROM quest_fields qf
            INNER JOIN quests q ON qf.quest_id = q.id
            WHERE q.created_by = ?";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) throw new Exception("刪除quest_fields SQL準備失敗：" . mysqli_error($conn));
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    if (!mysqli_stmt_execute($stmt)) throw new Exception("刪除quest_fields失敗：" . mysqli_stmt_error($stmt));
    mysqli_stmt_close($stmt);

    // Step 5: Delete quest_responses for user's quests (from other users)
    $sql = "DELETE qr FROM quest_responses qr
            INNER JOIN quests q ON qr.quest_id = q.id
            WHERE q.created_by = ?";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) throw new Exception("刪除quest_responses(for quests) SQL準備失敗：" . mysqli_error($conn));
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    if (!mysqli_stmt_execute($stmt)) throw new Exception("刪除quest_responses(for quests)失敗：" . mysqli_stmt_error($stmt));
    mysqli_stmt_close($stmt);

    // Step 6: Delete quest_responses from user's own applications
    $sql = "DELETE FROM quest_responses WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) throw new Exception("刪除quest_responses(user) SQL準備失敗：" . mysqli_error($conn));
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    if (!mysqli_stmt_execute($stmt)) throw new Exception("刪除quest_responses(user)失敗：" . mysqli_stmt_error($stmt));
    mysqli_stmt_close($stmt);

    // Step 7: Delete quests created by user
    $sql = "DELETE FROM quests WHERE created_by = ?";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) throw new Exception("刪除quests SQL準備失敗：" . mysqli_error($conn));
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    if (!mysqli_stmt_execute($stmt)) throw new Exception("刪除quests失敗：" . mysqli_stmt_error($stmt));
    mysqli_stmt_close($stmt);

    // Step 8: Finally delete user
    $sql = "DELETE FROM users WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) throw new Exception("刪除user SQL準備失敗：" . mysqli_error($conn));
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    if (!mysqli_stmt_execute($stmt)) throw new Exception("刪除user失敗：" . mysqli_stmt_error($stmt));
    mysqli_stmt_close($stmt);

    mysqli_commit($conn);
    header("Location: manage_users.php?msg=deleted");
    exit;

} catch (Throwable $e) {
    mysqli_rollback($conn);
    error_log("DELETE_USER_ERROR: " . $e->getMessage());
    header("Location: manage_users.php?msg=error&detail=" . urlencode($e->getMessage()));
    exit;
}
?>
