<?php
session_start();
require_once "db.php";

if (!isset($_SESSION['user_id'])) {
    exit("請先登入");
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    exit("無效的委託 ID");
}

// 檢查 quest 是否存在 + 權限
$sql = "SELECT created_by FROM quests WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$quest = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$quest) {
    exit("找不到委託");
}

$is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
$is_owner = (
    isset($_SESSION['member_group']) &&
    $_SESSION['member_group'] === 'client' &&
    (int)$quest['created_by'] === (int)$_SESSION['user_id']
);

if (!$is_admin && !$is_owner) {
    exit("沒有權限刪除此委託");
}

mysqli_begin_transaction($conn);

try {
    // 1. 刪除 quest_answers（透過 responses 找到）
    $sql = "DELETE qa
            FROM quest_answers qa
            INNER JOIN quest_responses qr ON qa.response_id = qr.id
            WHERE qr.quest_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // 2. 刪除 quest_responses
    $sql = "DELETE FROM quest_responses WHERE quest_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // 3. 刪除 quest_field_options（透過 quest_fields 找到）
    $sql = "DELETE qfo
            FROM quest_field_options qfo
            INNER JOIN quest_fields qf ON qfo.field_id = qf.id
            WHERE qf.quest_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // 4. 刪除 quest_fields
    $sql = "DELETE FROM quest_fields WHERE quest_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // 5. 最後刪除 quests
    $sql = "DELETE FROM quests WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    mysqli_commit($conn);
    header("Location: quest_list.php?msg=deleted");
    exit;

} catch (Throwable $e) {
    mysqli_rollback($conn);
    exit("刪除失敗：" . $e->getMessage());
}
?>