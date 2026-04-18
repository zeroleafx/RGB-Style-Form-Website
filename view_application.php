<?php
session_start();
require_once "db.php";

if (!isset($_SESSION['user_id'])) {
    exit("請先登入");
}

$response_id_raw = $_GET['response_id'] ?? '';
$response_id = (int)$response_id_raw;

if ($response_id <= 0) {
    exit("無效的申請 ID，收到的 response_id = " . htmlspecialchars((string)$response_id_raw));
}

// 抓 response + quest + user
$sql = "SELECT qr.*, q.title AS quest_title, q.created_by, u.username
        FROM quest_responses qr
        INNER JOIN quests q ON qr.quest_id = q.id
        LEFT JOIN users u ON qr.user_id = u.id
        WHERE qr.id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $response_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$response = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$response) {
    exit("找不到申請資料");
}

$is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
$is_owner = (
    (($_SESSION['member_group'] ?? '') === 'client') &&
    ((int)$response['created_by'] === (int)$_SESSION['user_id'])
);

if (!$is_admin && !$is_owner) {
    exit("沒有權限查看此申請");
}

// 讀答案
$sql = "SELECT qa.*, qf.*
        FROM quest_answers qa
        LEFT JOIN quest_fields qf ON qa.field_id = qf.id
        WHERE qa.response_id = ?
        ORDER BY qf.sort_order ASC, qf.id ASC, qa.id ASC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $response_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>Application Detail</title>
    <link href="css/styles.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
</head>
<body class="pixel-purple-body">
    <div class="pixel-container">
        <a class="pixel-link" href="manage_applications.php?quest_id=<?php echo (int)$response['quest_id']; ?>">← Back to Applications</a>
        <h1 class="pixel-title">Application Detail</h1>

        <p class="pixel-label">Quest: <?php echo htmlspecialchars($response['quest_title']); ?></p>
        <p class="pixel-label">Applicant: <?php echo htmlspecialchars($response['username'] ?? 'Unknown'); ?></p>
        <p class="pixel-label">Status: <?php echo htmlspecialchars($response['status'] ?? 'pending'); ?></p>

        <?php while ($row = mysqli_fetch_assoc($result)): ?>
            <?php
                $question = $row['question_text'] ?? $row['label'] ?? $row['field_label'] ?? 'Question';
                $answer = $row['answer_text'] ?? $row['answer'] ?? '';
            ?>
            <div class="pixel-field-box">
                <p class="pixel-label"><?php echo htmlspecialchars($question); ?></p>
                <div class="pixel-textarea" style="min-height:auto;">
                    <?php echo nl2br(htmlspecialchars($answer)); ?>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
</body>
</html>