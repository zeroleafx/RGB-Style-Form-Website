<?php
session_start();
require_once "db.php";
require_once "helpers.php";

// Close any expired quests
close_expired_quests($conn);

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

if (($_SESSION['role'] ?? '') !== 'admin' && ($_SESSION['member_group'] ?? '') !== 'client') {
    die("你沒有權限查看此頁面");
}

$user_id = (int)$_SESSION['user_id'];
$filter = $_GET['filter'] ?? 'all';

$sql = "SELECT q.id, q.title, q.description, q.status, q.created_at, q.reward, q.difficulty, q.level_required
        FROM quests q
        WHERE q.created_by = ?";

$params = [$user_id];
$types = 'i';

if ($filter === 'draft') {
    $sql .= " AND q.status = 'draft'";
} elseif ($filter === 'published') {
    $sql .= " AND q.status = 'published'";
} elseif ($filter === 'closed') {
    $sql .= " AND q.status = 'closed'";
}

$sql .= " ORDER BY q.created_at DESC";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$quests = [];

while ($row = mysqli_fetch_assoc($result)) {
    $quests[] = $row;
}

mysqli_stmt_close($stmt);
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Quests</title>
    <link href="css/styles.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
    <link href="https://use.fontawesome.com/releases/v6.5.0/css/all.css" rel="stylesheet" crossorigin="anonymous">
    <style>
        body {
            background:
                linear-gradient(rgba(24, 10, 42, 0.76), rgba(10, 12, 24, 0.82)),
                url("assets/img/quest-bg.png") center/cover fixed no-repeat;
            color: #f5f1ff;
            padding: 20px;
        }

        .quests-container {
            max-width: 1000px;
            margin: 0 auto;
        }

        .back-link {
            margin-bottom: 20px;
        }

        .back-link a {
            color: #08141f;
            text-decoration: none;
            font-weight: bold;
            font-family: 'Press Start 2P', monospace;
            font-size: 12px;
            display: inline-block;
            padding: 10px 14px;
            background: linear-gradient(180deg, #2ec4b6 0%, #1f9d93 100%);
            border: 2px solid #b8fff7;
            box-shadow: 0 0 10px rgba(46, 196, 182, 0.24), 3px 3px 0 #3a2a52;
            color: #08141f;
            transition: all 0.2s;
        }

        .back-link a:hover {
            transform: translate(2px, 2px);
            box-shadow: 0 0 6px rgba(46, 196, 182, 0.22), 1px 1px 0 #3a2a52;
        }

        .page-title {
            color: #e6d9ff;
            font-family: 'Press Start 2P', monospace;
            font-size: 35px;
            margin: 20px 0;
            text-shadow: 2px 2px 0 rgba(58, 42, 82, 0.8);
        }

        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .filter-tab {
            padding: 8px 16px;
            border: 2px solid rgba(216, 200, 255, 0.6);
            background: #1a1330;
            color: #b8a8d8;
            font-family: 'Press Start 2P', monospace;
            font-size: 11px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
        }

        .filter-tab:hover, .filter-tab.active {
            background: linear-gradient(180deg, #c3a6ff 0%, #9f7ae8 100%);
            color: #fff;
            border-color: #d8c8ff;
        }

        .quests-list {
            background: rgba(29, 17, 47, 0.9);
            border: 3px solid rgba(216, 200, 255, 0.82);
            box-shadow:
                0 0 0 2px rgba(58, 42, 82, 0.9),
                0 0 20px rgba(184, 151, 255, 0.18);
            overflow: hidden;
        }

        .quest-item {
            border-bottom: 2px solid rgba(216, 200, 255, 0.4);
            padding: 20px;
            display: block;
            transition: background-color 0.3s;
        }

        .quest-item:hover {
            background-color: rgba(184, 151, 255, 0.1);
        }

        .quest-item:last-child {
            border-bottom: none;
        }

        .quest-info {
            margin-bottom: 15px;
        }

        .quest-title-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 10px;
        }

        .quest-info h3 {
            margin: 0;
            color: #e6d9ff;
            font-size: 14px;
            font-family: 'Press Start 2P', monospace;
            flex: 1;
        }

        .quest-info p {
            margin: 5px 0;
            color: #b8a8d8;
            font-size: 11px;
            font-family: 'Press Start 2P', monospace;
        }

        .quest-actions {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border: 2px solid;
            font-size: 10px;
            font-weight: bold;
            font-family: 'Press Start 2P', monospace;
            white-space: nowrap;
        }

        .status-draft {
            background: linear-gradient(180deg, #8b6e88 0%, #6b5471 100%);
            color: #f0dcff;
            border-color: #b8a8d8;
            box-shadow: 0 0 8px rgba(139, 110, 136, 0.3), 2px 2px 0 rgba(58, 42, 82, 0.4);
        }

        .status-published {
            background: linear-gradient(180deg, #2ec4b6 0%, #1f9d93 100%);
            color: #08141f;
            border-color: #b8fff7;
            box-shadow: 0 0 8px rgba(46, 196, 182, 0.3), 2px 2px 0 rgba(58, 42, 82, 0.4);
        }

        .status-closed {
            background: linear-gradient(180deg, #666 0%, #555 100%);
            color: #ccc;
            border-color: #888;
            box-shadow: 0 0 8px rgba(102, 102, 102, 0.3), 2px 2px 0 rgba(58, 42, 82, 0.4);
        }

        .empty-message {
            text-align: center;
            padding: 40px;
            color: #b8a8d8;
            font-family: 'Press Start 2P', monospace;
        }

        @media (max-width: 768px) {
            .quest-item {
                flex-direction: column;
                align-items: flex-start;
            }

            .quest-actions {
                margin-top: 15px;
                width: 100%;
            }

            .quest-actions button {
                flex: 1;
            }
        }
    </style>
</head>
<body>
    <div class="quests-container">
        <div class="back-link">
            <a href="index.php">← Home</a>
        </div>

        <h1 class="page-title">My Quests</h1>

        <div class="filter-tabs">
            <a href="my_quests.php?filter=all" class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">All</a>
            <a href="my_quests.php?filter=draft" class="filter-tab <?php echo $filter === 'draft' ? 'active' : ''; ?>">Drafts</a>
            <a href="my_quests.php?filter=published" class="filter-tab <?php echo $filter === 'published' ? 'active' : ''; ?>">Published</a>
            <a href="my_quests.php?filter=closed" class="filter-tab <?php echo $filter === 'closed' ? 'active' : ''; ?>">Closed</a>
        </div>

        <div class="quests-list">
            <?php if (empty($quests)): ?>
                <div class="empty-message">No quests found</div>
            <?php else: ?>
                <?php foreach ($quests as $quest): ?>
                    <div class="quest-item">
                        <div class="quest-info">
                            <div class="quest-title-row">
                                <h3><?php echo htmlspecialchars($quest['title']); ?></h3>
                                <span class="status-badge status-<?php echo $quest['status']; ?>">
                                    <?php echo ucfirst($quest['status']); ?>
                                </span>
                            </div>
                            <p><?php echo htmlspecialchars(substr($quest['description'], 0, 80)); ?><?php echo strlen($quest['description']) > 80 ? '...' : ''; ?></p>
                            <p>Created: <?php echo date('Y-m-d H:i', strtotime($quest['created_at'])); ?> | Reward: <?php echo (int)$quest['reward']; ?> | Level: <?php echo (int)$quest['level_required']; ?></p>
                        </div>
                        <div class="quest-actions">
                            <a href="quest_detail.php?id=<?php echo (int)$quest['id']; ?>&from=my_quests">View</a>
                            <?php if ($quest['status'] === 'draft'): ?>
                                <a href="edit_quest.php?id=<?php echo (int)$quest['id']; ?>&from=my_quests">Edit</a>
                                <a href="publish_quest_action.php?id=<?php echo (int)$quest['id']; ?>" onclick="return confirm('Publish this quest?')">Publish</a>
                                <a href="delete_quest.php?id=<?php echo (int)$quest['id']; ?>" onclick="return confirm('Delete this quest?')">Delete</a>
                            <?php elseif ($quest['status'] === 'published'): ?>
                                <a href="edit_quest.php?id=<?php echo (int)$quest['id']; ?>&from=my_quests">Edit</a>
                                <a href="manage_applications.php?quest_id=<?php echo (int)$quest['id']; ?>&from=my_quests">Applicants</a>
                                <a href="delete_quest.php?id=<?php echo (int)$quest['id']; ?>" onclick="return confirm('Delete this quest?')">Delete</a>
                            <?php elseif ($quest['status'] === 'closed'): ?>
                                <a href="delete_quest.php?id=<?php echo (int)$quest['id']; ?>" onclick="return confirm('Delete this quest?')">Delete</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
