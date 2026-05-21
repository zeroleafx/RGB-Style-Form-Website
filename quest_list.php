<?php
session_start();
require_once "db.php";
require_once "helpers.php";

// Close any expired quests
close_expired_quests($conn);

$search = trim($_GET['search'] ?? '');
$sort = $_GET['sort'] ?? 'newest';
$user_level = (int)($_SESSION['level'] ?? 1);

$sort_map = [
    'newest' => 'q.created_at DESC',
    'oldest' => 'q.created_at ASC',
    'reward_high' => 'q.reward DESC',
    'reward_low' => 'q.reward ASC',
    'difficulty_high' => 'q.difficulty DESC',
    'difficulty_low' => 'q.difficulty ASC'
];

if(!isset($sort_map[$sort])){
    $sort = 'newest';
}

$sql = "SELECT q.*, 
                u.username AS creator_name,
                (SELECT COUNT(*) FROM quest_responses qr WHERE qr.quest_id = q.id) AS apply_count,
                (SELECT COUNT(*) FROM quest_responses qrc
                 WHERE qrc.quest_id = q.id AND LOWER(TRIM(COALESCE(qrc.status, ''))) = 'completed') AS completed_response_count,
                CASE
                    WHEN q.level_required > ?
                        OR (q.start_date IS NOT NULL AND NOW() < q.start_date)
                        OR (q.end_date IS NOT NULL AND NOW() > q.end_date)
                    THEN 1
                    ELSE 0
                END AS locked
        FROM quests q
        LEFT JOIN users u ON q.created_by = u.id
        WHERE q.status IN ('published', 'closed')";


$types = 'i';
$params = [$user_level];

if($search!== ''){
    $sql .= " AND (q.title LIKE ? OR q.description LIKE ? OR u.username LIKE ?)";
    $keyword = "%".$search."%";
    $types .= 'sss';
    $params[] = $keyword;
    $params[] = $keyword;
    $params[] = $keyword;
}

$sql .= " ORDER BY " . $sort_map[$sort];
$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    die("查詢失敗：" . mysqli_error($conn));
}

mysqli_stmt_bind_param($stmt, $types, ...$params);

if(!mysqli_stmt_execute($stmt)){
    die("查詢失敗: ". mysqli_stmt_error($stmt));
}

$result = mysqli_stmt_get_result($stmt);
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quest List</title>
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
    <link href="css/styles.css" rel="stylesheet" />
    <style>
        body, html { height: 100%; }
        body {
            background-image: url("./assets/img/quest-bg.png");
            background-position: center;
            background-repeat: no-repeat;
            background-size: cover;
            background-attachment: fixed;
        }

        body::before{
            content: "";
            position: fixed;
            inset: 0;

            background: rgba(15, 8, 30, 0.65);  /* 遮罩顏色 */

            pointer-events: none;
            z-index: 0;
        }

        body > *{
            position: relative;
            z-index: 1;
        }
    </style>
</head>
<body>
    <div class="quest-container">
        <div class="top-bar">
            <a href="index.php">← Back to Home</a>
        </div>

        <h1 class="quest-board-title">Quest Board</h1>

        <form method="GET" action="quest_list.php" class="quest-search">

            <input
                type="text"
                class="quest-search-input"
                name="search"
                placeholder="Search title / description / publisher"
                value="<?php echo htmlspecialchars($search); ?>"
            >
            <select name="sort" class="quest-search-select">
                <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest</option>
                <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest</option>
                <option value="reward_high" <?php echo $sort === 'reward_high' ? 'selected' : ''; ?>>Reward High to Low</option>
                <option value="reward_low" <?php echo $sort === 'reward_low' ? 'selected' : ''; ?>>Reward Low to High</option>
                <option value="difficulty_high" <?php echo $sort === 'difficulty_high' ? 'selected' : ''; ?>>Difficulty High to Low</option>
                <option value="difficulty_low" <?php echo $sort === 'difficulty_low' ? 'selected' : ''; ?>>Difficulty Low to High</option>
            </select>
            <button type="submit" class="quest-search-btn">Apply</button>
            <a href="quest_list.php" class="quest-search-reset">Reset</a>
        </form>

        <div class="quest-positon">

            <?php if (mysqli_num_rows($result) > 0): ?>
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <div class="quest-card">
                        <h2>
                            <?php echo htmlspecialchars($row['title']); ?>
                        </h2>
                        <div class="quest-meta">
                            <strong>Publisher:</strong>
                            <?php echo htmlspecialchars($row['creator_name'] ?? 'Unknown'); ?>
                        </div>

                        <div class="quest-meta">
                            <strong>Level Required:</strong> <?php echo (int)$row['level_required']; ?>
                            |
                            <strong>Difficulty:</strong> <?php echo (int)$row['difficulty']; ?>
                            |
                            <strong>Reward:</strong> <?php echo (int)$row['reward']; ?>
                            |
                            <strong>EXP:</strong> <?php echo (int)$row['exp_reward']; ?>
                        </div>

                        <div class="quest-meta">
                            <strong>Created At:</strong>
                            <?php echo htmlspecialchars($row['created_at']); ?>
                        </div>

                        <div class="quest-meta">
                            <strong>Apply Period:</strong>
                            <?php
                            $start = !empty($row['start_date']) ? $row['start_date'] : 'No limit';
                            $end = !empty($row['end_date']) ? $row['end_date'] : 'No limit';
                            echo htmlspecialchars($start . " ~ " . $end);
                            ?>
                        </div>

                        <div class="quest-meta">
                            <strong>Repeatable:</strong>
                            <?php echo ((int)$row['is_repeatable'] === 1) ? 'Yes' : 'No'; ?>
                            |
                            <strong>Apply Count:</strong>
                            <?php echo (int)($row['apply_count'] ?? 0); ?>
                        </div>

                        <div class="quest-desc">
                            <?php echo nl2br(htmlspecialchars($row['description'])); ?>
                        </div>

                        <div class="quest-actions">
                            <?php if (isset($_SESSION['user_id']) && ($_SESSION['member_group'] ?? '') === 'adventurer'): ?>
                                <?php
                                $app_timezone = new DateTimeZone('Asia/Taipei');
                                $now_dt = new DateTime('now', $app_timezone);
                                $start_dt = !empty($row['start_date'])
                                    ? DateTime::createFromFormat('Y-m-d H:i:s', $row['start_date'], $app_timezone)
                                    : null;
                                $end_dt = !empty($row['end_date'])
                                    ? DateTime::createFromFormat('Y-m-d H:i:s', $row['end_date'], $app_timezone)
                                    : null;

                                $not_started = $start_dt instanceof DateTime && $now_dt < $start_dt;
                                $ended = $end_dt instanceof DateTime && $now_dt > $end_dt;
                                $level_locked = (int)$row['level_required'] > $user_level;
                                $quest_completed_closed = (int)($row['completed_response_count'] ?? 0) > 0;
                                ?>

                                <?php if ($not_started): ?>
                                    <a href="quest_detail.php?id=<?php echo (int)$row['id']; ?>&from=quest_list">View Detail</a>
                                    <span class="locked-text">Not yet open</span>

                                <?php elseif ($ended): ?>
                                    <a href="quest_detail.php?id=<?php echo (int)$row['id']; ?>&from=quest_list">View Detail</a>
                                    <span class="locked-text">Closed</span>

                                <?php elseif ($level_locked): ?>
                                    <a href="quest_detail.php?id=<?php echo (int)$row['id']; ?>&from=quest_list">View Detail</a>
                                    <span class="locked-text">Requires Level <?php echo (int)$row['level_required']; ?></span>

                                <?php elseif ($quest_completed_closed): ?>
                                    <a href="quest_detail.php?id=<?php echo (int)$row['id']; ?>&from=quest_list">View Detail</a>
                                    <span class="locked-text">Completed</span>

                                <?php else: ?>
                                    <a href="quest_detail.php?id=<?php echo (int)$row['id']; ?>&from=quest_list">Apply Now</a>
                                <?php endif; ?>

                            <?php else: ?>
                                <a href="quest_detail.php?id=<?php echo (int)$row['id']; ?>&from=quest_list">View Detail</a>
                            <?php endif; ?>

                            <?php if (isset($_SESSION['user_id']) && (($_SESSION['member_group'] ?? '') === 'client') && ((int)$row['created_by'] === (int)$_SESSION['user_id'])): ?>
                                <a href="edit_quest.php?id=<?php echo (int)$row['id']; ?>&from=quest_list">Edit</a>
                                <a href="delete_quest.php?id=<?php echo (int)$row['id']; ?>" onclick="return confirm('確定要刪除這個委託嗎？')">Delete</a>
                                <a href="manage_applications.php?quest_id=<?php echo (int)$row['id']; ?>">Applications</a>
                            <?php endif; ?>

                            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                                <a href="edit_quest.php?id=<?php echo (int)$row['id']; ?>&from=quest_list">Admin Edit</a>
                                <a href="delete_quest.php?id=<?php echo (int)$row['id']; ?>" onclick="return confirm('管理員確定要刪除這個委託嗎？')">Admin Delete</a>
                                <a href="manage_applications.php?quest_id=<?php echo (int)$row['id']; ?>">Applications</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p class="empty-text">No Quests Available</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>