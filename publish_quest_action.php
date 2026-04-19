<?php
session_start();
require_once "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$quest_id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$user_id = (int)$_SESSION['user_id'];

if (empty($quest_id)) {
    die("Quest ID required");
}

// Check if quest exists and belongs to user
$check_sql = "SELECT id, status FROM quests WHERE id = ? AND created_by = ?";
$check_stmt = mysqli_prepare($conn, $check_sql);
mysqli_stmt_bind_param($check_stmt, "ii", $quest_id, $user_id);
mysqli_stmt_execute($check_stmt);
$check_result = mysqli_stmt_get_result($check_stmt);

if (mysqli_num_rows($check_result) === 0) {
    mysqli_stmt_close($check_stmt);
    die("Quest not found or you do not have permission");
}

$quest = mysqli_fetch_assoc($check_result);
mysqli_stmt_close($check_stmt);

if ($quest['status'] !== 'draft') {
    die("Only draft quests can be published");
}

// Update quest status to published
$update_sql = "UPDATE quests SET status = 'published' WHERE id = ?";
$update_stmt = mysqli_prepare($conn, $update_sql);
mysqli_stmt_bind_param($update_stmt, "i", $quest_id);

if (mysqli_stmt_execute($update_stmt)) {
    mysqli_stmt_close($update_stmt);
    header("Location: my_quests.php?msg=published");
} else {
    mysqli_stmt_close($update_stmt);
    die("Failed to publish quest");
}
mysqli_close($conn);
?>

