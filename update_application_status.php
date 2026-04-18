<?php
session_start();
require_once "db.php";

if (!isset($_SESSION['user_id'])) {
    exit("請先登入");
}

$response_id_raw = $_POST['response_id'] ?? '';
$action = trim($_POST['action'] ?? '');
$response_id = (int)$response_id_raw;

if ($response_id <= 0) {
    exit("無效的申請 ID，收到的 response_id = " . htmlspecialchars((string)$response_id_raw));
}

$allowed = ['approve', 'reject', 'complete'];
if (!in_array($action, $allowed, true)) {
    exit("無效的操作：" . htmlspecialchars($action));
}

// 抓 response + quest
$sql = "SELECT qr.id, qr.quest_id, qr.user_id, qr.status, q.created_by, q.reward, q.exp_reward, q.title,
               q.is_repeatable
        FROM quest_responses qr
        INNER JOIN quests q ON qr.quest_id = q.id
        WHERE qr.id = ?";
$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    exit("SQL 準備失敗：" . mysqli_error($conn));
}

mysqli_stmt_bind_param($stmt, "i", $response_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$data = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$data) {
    exit("找不到申請資料，response_id = " . $response_id);
}

$is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
$is_owner = (
    (($_SESSION['member_group'] ?? '') === 'client') &&
    ((int)$data['created_by'] === (int)$_SESSION['user_id'])
);

if (!$is_admin && !$is_owner) {
    exit("沒有權限操作此申請");
}

$current_status = strtolower(trim((string)($data['status'] ?? '')));
if ($current_status === '') {
    $current_status = 'pending';
}

mysqli_begin_transaction($conn);

try {
    if ($action === 'approve') {
        $sql = "UPDATE quest_responses SET status = 'approved' WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) throw new Exception("Approve SQL 準備失敗：" . mysqli_error($conn));
        mysqli_stmt_bind_param($stmt, "i", $response_id);
        if (!mysqli_stmt_execute($stmt)) throw new Exception("Approve 失敗：" . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);

        /* 僅「不可重複」委託：核准一人時才拒絕其餘 pending（與 quest 可多人申請的設定一致） */
        if ((int)($data['is_repeatable'] ?? 0) === 0) {
            $sql = "UPDATE quest_responses
                    SET status = 'rejected'
                    WHERE quest_id = ? AND id <> ? AND (status = 'pending' OR status IS NULL OR status = '')";
            $stmt = mysqli_prepare($conn, $sql);
            if (!$stmt) throw new Exception("Reject others SQL 準備失敗：" . mysqli_error($conn));
            mysqli_stmt_bind_param($stmt, "ii", $data['quest_id'], $response_id);
            if (!mysqli_stmt_execute($stmt)) throw new Exception("Reject others 失敗：" . mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
        }

        mysqli_commit($conn);
        header("Location: manage_applications.php?quest_id=" . (int)$data['quest_id'] . "&msg=approved");
        exit;
    }

    if ($action === 'reject') {
        $sql = "UPDATE quest_responses SET status = 'rejected' WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) throw new Exception("Reject SQL 準備失敗：" . mysqli_error($conn));
        mysqli_stmt_bind_param($stmt, "i", $response_id);
        if (!mysqli_stmt_execute($stmt)) throw new Exception("Reject 失敗：" . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);

        mysqli_commit($conn);
        header("Location: manage_applications.php?quest_id=" . (int)$data['quest_id'] . "&msg=rejected");
        exit;
    }

    if ($action === 'complete') {
        if ($current_status !== 'approved') {
            throw new Exception("只有 approved 的申請才能標記完成，目前狀態是：" . $current_status);
        }

        $sql = "UPDATE quest_responses
                SET status = 'completed',
                    reward_earned = ?,
                    exp_earned = ?,
                    completed_at = NOW()
                WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) throw new Exception("Complete SQL 準備失敗：" . mysqli_error($conn));
        mysqli_stmt_bind_param($stmt, "iii", $data['reward'], $data['exp_reward'], $response_id);
        if (!mysqli_stmt_execute($stmt)) throw new Exception("Complete 失敗：" . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);

        $sql = "UPDATE users SET exp = exp + ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) throw new Exception("EXP SQL 準備失敗：" . mysqli_error($conn));
        mysqli_stmt_bind_param($stmt, "ii", $data['exp_reward'], $data['user_id']);
        if (!mysqli_stmt_execute($stmt)) throw new Exception("EXP 更新失敗：" . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);

        $sql = "SELECT exp FROM users WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) throw new Exception("讀取 EXP SQL 準備失敗：" . mysqli_error($conn));
        mysqli_stmt_bind_param($stmt, "i", $data['user_id']);
        mysqli_stmt_execute($stmt);
        $res2 = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($res2);
        mysqli_stmt_close($stmt);

        $new_exp = (int)($user['exp'] ?? 0);
        $new_level = floor($new_exp / 1000) + 1;

        $sql = "UPDATE users SET level = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) throw new Exception("更新 level SQL 準備失敗：" . mysqli_error($conn));
        mysqli_stmt_bind_param($stmt, "ii", $new_level, $data['user_id']);
        if (!mysqli_stmt_execute($stmt)) throw new Exception("更新 level 失敗：" . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);

        mysqli_commit($conn);
        header("Location: manage_applications.php?quest_id=" . (int)$data['quest_id'] . "&msg=completed");
        exit;
    }

    throw new Exception("未知操作");

} catch (Throwable $e) {
    mysqli_rollback($conn);
    exit("操作失敗：" . $e->getMessage());
}
?>