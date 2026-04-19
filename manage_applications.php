<?php
session_start();
require_once "db.php";

if (!isset($_SESSION['user_id'])) {
    exit("請先登入");
}

$quest_id = (int)($_GET['quest_id'] ?? 0);
$from = $_GET['from'] ?? 'quest_list';

// Validate 'from' parameter
if ($from !== 'my_quests') {
    $from = 'quest_list';
}

if ($quest_id <= 0) {
    exit("無效的委託 ID");
}

// 讀 quest
$sql = "SELECT * FROM quests WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    exit("Quest 查詢失敗：" . mysqli_error($conn));
}
mysqli_stmt_bind_param($stmt, "i", $quest_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$quest = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$quest) {
    exit("找不到委託");
}

$is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
$is_owner = (
    (($_SESSION['member_group'] ?? '') === 'client') &&
    ((int)$quest['created_by'] === (int)$_SESSION['user_id'])
);

if (!$is_admin && !$is_owner) {
    exit("沒有權限查看此委託的申請");
}

$sqlCount = "SELECT COUNT(*) AS total FROM quest_responses WHERE quest_id = ?";
$stmtCount = mysqli_prepare($conn, $sqlCount);
mysqli_stmt_bind_param($stmtCount, "i", $quest_id);
mysqli_stmt_execute($stmtCount);
$resCount = mysqli_stmt_get_result($stmtCount);
$apply_count_row = mysqli_fetch_assoc($resCount);
mysqli_stmt_close($stmtCount);
$apply_count = (int)($apply_count_row['total'] ?? 0);

// 抓申請列表
$sql = "SELECT 
            qr.id AS response_id,
            qr.submitted_at,
            qr.*,
            u.username
        FROM quest_responses qr
        LEFT JOIN users u ON qr.user_id = u.id
        WHERE qr.quest_id = ?
        ORDER BY qr.id DESC";

$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    exit("Applications 查詢失敗：" . mysqli_error($conn));
}
mysqli_stmt_bind_param($stmt, "i", $quest_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>Applications</title>
    <link href="css/styles.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
</head>
<body class="pixel-purple-body">
<div class="pixel-container">
    <a class="pixel-link" href="<?php echo ($from === 'my_quests') ? 'my_quests.php' : 'quest_list.php'; ?>">← Back</a>
    <h1 class="pixel-title">Applications</h1>

    <p class="pixel-label">
        Quest: <?php echo htmlspecialchars($quest['title']); ?>
    </p>

    <p class="pixel-label">
        Apply count: <?php echo $apply_count; ?>
    </p>

    <?php if ($result && mysqli_num_rows($result) > 0): ?>
        <?php while ($row = mysqli_fetch_assoc($result)): ?>
            <?php
                $status = strtolower(trim((string)($row['status'] ?? '')));
                if ($status === '') {
                    $status = 'pending';
                }
            ?>            
            <div class="pixel-field-box">

                <p class="pixel-label">
                    Applicant: <?php echo htmlspecialchars($row['username'] ?? 'Unknown'); ?>
                </p>

                <p class="pixel-label">
                    Submitted At: 
                    <?php echo htmlspecialchars($row['submitted_at'] ?? 'N/A'); ?>
                </p>

                <p class="pixel-label">
                    Status: <?php echo htmlspecialchars($status); ?>
                </p>

                <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:12px;">
                    <a class="pixel-link" 
                       href="view_application.php?response_id=<?php echo (int)$row['response_id']; ?>">
                        View Answers
                    </a>

                    <?php if ($status === 'pending'): ?>
                        <form method="POST" action="update_application_status.php" style="margin:0;">
                            <input type="hidden" name="response_id"
                                   value="<?php echo (int)$row['response_id']; ?>">
                            <input type="hidden" name="action" value="approve">
                            <button type="submit" class="pixel-small-btn">
                                Approve
                            </button>                        
                        </form>

                        <form method="POST" action="update_application_status.php" style="margin:0;">
                            <input type="hidden" name="response_id"
                                   value="<?php echo (int)$row['response_id']; ?>">
                            <input type="hidden" name="action" value="reject">
                            <button type="submit" class="pixel-small-btn reject-btn">
                                Reject
                            </button>
                        </form>

                    <?php elseif ($status === 'approved'): ?>
                        <form method="POST" action="update_application_status.php" style="margin:0;">
                            <input type="hidden" name="response_id"
                                   value="<?php echo (int)$row['response_id']; ?>">
                            <input type="hidden" name="action" value="complete">
                            <button type="submit" class="pixel-btn">
                                Mark Completed
                            </button>
                        </form>

                    <?php elseif ($status === 'completed'): ?>
                        <span class="pixel-label">✅ Completed</span>

                    <?php elseif ($status === 'rejected'): ?>
                        <span class="pixel-label">❌ Rejected</span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p class="pixel-label">No applicants yet.</p>
    <?php endif; ?>

    <?php mysqli_stmt_close($stmt); ?>
</div>
</body>
</html>