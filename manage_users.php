<?php
session_start();
require_once "db.php";

// Check admin permission
$is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
if (!$is_admin) {
    exit("只有管理員可以進行此操作");
}

$user_id = (int)$_SESSION['user_id'];
$search = trim($_GET['search'] ?? '');
$filter_group = $_GET['filter_group'] ?? '';

// Build query
$sql = "SELECT id, username, member_group, role, level, exp, banned_until, is_permanent_ban, ban_reason
        FROM users
        WHERE 1=1";

$params = [];
$types = '';

if (!empty($search)) {
    $sql .= " AND username LIKE ?";
    $search_term = "%$search%";
    $params[] = $search_term;
    $types .= 's';
}

if (!empty($filter_group)) {
    if ($filter_group === 'admin') {
        $sql .= " AND role = 'admin'";
    } else {
        $sql .= " AND member_group = ?";
        $params[] = $filter_group;
        $types .= 's';
    }
}

$sql .= " ORDER BY username ASC";

$stmt = mysqli_prepare($conn, $sql);
if ($stmt && !empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
if ($stmt) {
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $users = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $users[] = $row;
    }
    mysqli_stmt_close($stmt);
}

$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>Manage Users - Admin Panel</title>
    <link href="css/styles.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
</head>
<body class="pixel-purple-body">
<div class="pixel-container">
    <a class="pixel-link" href="index.php">← Back</a>
    <h1 class="pixel-title">User Management</h1>

    <?php if ($msg === 'banned'): ?>
        <p class="pixel-label" style="color: #90EE90;">✓ User has been banned successfully</p>
    <?php elseif ($msg === 'unbanned'): ?>
        <p class="pixel-label" style="color: #90EE90;">✓ User ban has been removed</p>
    <?php elseif ($msg === 'deleted'): ?>
        <p class="pixel-label" style="color: #90EE90;">✓ User has been deleted successfully</p>
    <?php elseif ($msg === 'modified'): ?>
        <p class="pixel-label" style="color: #90EE90;">✓ User has been modified successfully</p>
    <?php elseif ($msg === 'error'): ?>
        <p class="pixel-label" style="color: #FF6B6B;">✗ An error occurred</p>
    <?php endif; ?>

    <!-- Filter Section -->
    <div class="pixel-field-box">
        <form method="GET" style="display: flex; gap: 10px; flex-wrap: wrap;">
            <input type="text" name="search" placeholder="Search username..." value="<?php echo htmlspecialchars($search); ?>" class="pixel-input" style="flex: 1; min-width: 200px;">
            <select name="filter_group" class="pixel-select" style="flex: 0 1 150px;">
                <option value="">All</option>
                <option value="adventurer" <?php echo $filter_group === 'adventurer' ? 'selected' : ''; ?>>Adventurer</option>
                <option value="client" <?php echo $filter_group === 'client' ? 'selected' : ''; ?>>Client</option>
                <option value="admin" <?php echo $filter_group === 'admin' ? 'selected' : ''; ?>>Admin</option>
            </select>
            <button type="submit" class="pixel-btn">Search</button>
            <a href="manage_users.php" class="pixel-btn" style="text-decoration: none; text-align: center;">Clear</a>
        </form>
    </div>

    <!-- Users List -->
    <?php if (!empty($users)): ?>
        <?php foreach ($users as $user): ?>
            <?php
            $ban_status = '✓ ACTIVE';
            $ban_color = '#90EE90';

            if ($user['is_permanent_ban'] === 1) {
                $ban_status = '⛔ PERMANENT BAN';
                $ban_color = '#FF6B6B';
            } elseif ($user['banned_until']) {
                $ban_until = strtotime($user['banned_until']);
                if ($ban_until > time()) {
                    $ban_date = date('Y-m-d H:i', $ban_until);
                    $ban_status = "⏳ Until $ban_date";
                    $ban_color = '#FFD700';
                }
            }
            ?>
            <div class="pixel-field-box">
                <p class="pixel-label">
                    Username: <?php echo htmlspecialchars($user['username']); ?>
                </p>

                <p class="pixel-label">
                    Group: <?php echo htmlspecialchars($user['member_group'] ?? 'N/A'); ?> |
                    Role: <?php echo htmlspecialchars($user['role'] ?? 'user'); ?> |
                    Level: <?php echo (int)$user['level']; ?> |
                    EXP: <?php echo (int)$user['exp']; ?>
                </p>

                <p class="pixel-label" style="color: <?php echo $ban_color; ?>;">
                    Status: <?php echo $ban_status; ?>
                </p>

                <?php if ($user['ban_reason']): ?>
                    <p class="pixel-label" style="color: #FFB3BA; font-size: 11px;">
                        Reason: <?php echo htmlspecialchars($user['ban_reason']); ?>
                    </p>
                <?php endif; ?>

                <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 12px;">
                    <form method="POST" action="modify_user.php" style="margin: 0;">
                        <input type="hidden" name="user_id" value="<?php echo (int)$user['id']; ?>">
                        <button type="submit" class="pixel-small-btn">Edit</button>
                    </form>

                    <?php if ($user['is_permanent_ban'] === 1 || $user['banned_until']): ?>
                        <form method="POST" action="ban_user.php" style="margin: 0;">
                            <input type="hidden" name="action" value="unban">
                            <input type="hidden" name="user_id" value="<?php echo (int)$user['id']; ?>">
                            <button type="submit" class="pixel-small-btn" onclick="return confirm('Unban this user?');">Unban</button>
                        </form>
                    <?php else: ?>
                        <button type="button" class="pixel-small-btn reject-btn" onclick="showBanModal(<?php echo (int)$user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>');">Ban</button>
                    <?php endif; ?>

                    <form method="POST" action="delete_user.php" style="margin: 0;">
                        <input type="hidden" name="user_id" value="<?php echo (int)$user['id']; ?>">
                        <button type="submit" class="pixel-small-btn reject-btn" onclick="return confirm('Delete this user and all their data? This cannot be undone!');">Delete</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="pixel-field-box">
            <p class="pixel-label" style="text-align: center; color: #999;">No users found</p>
        </div>
    <?php endif; ?>
</div>

<!-- Ban Modal -->
<div id="banModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 1000; justify-content: center; align-items: center;">
    <div class="pixel-container" style="max-width: 400px;">
        <h2 class="pixel-title">Ban User</h2>
        <form method="POST" action="ban_user.php">
            <input type="hidden" name="action" value="ban">
            <input type="hidden" name="user_id" id="ban_user_id">

            <p class="pixel-label">
                Username: <span id="ban_username"></span>
            </p>

            <p class="pixel-label">Ban Type:</p>
            <div style="margin: 12px 0;">
                <label style="display: block; margin: 8px 0; color: #f0dcff;">
                    <input type="radio" name="ban_type" value="temporary" checked> Temporary Ban
                </label>
            </div>

            <label class="pixel-label" for="days">Days:</label>
            <input type="number" id="days" name="days" value="1" min="1" max="365" class="pixel-input" style="margin-bottom: 12px;">

            <div style="margin: 12px 0;">
                <label style="display: block; margin: 8px 0; color: #f0dcff;">
                    <input type="radio" name="ban_type" value="permanent"> Permanent Ban
                </label>
            </div>

            <label class="pixel-label" for="reason">Reason (optional):</label>
            <textarea id="reason" name="reason" class="pixel-textarea" style="margin-bottom: 12px;"></textarea>

            <div style="display: flex; gap: 10px;">
                <button type="submit" class="pixel-btn" style="flex: 1;">Ban User</button>
                <button type="button" class="pixel-btn" onclick="closeBanModal()" style="flex: 1;">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
    function showBanModal(userId, username) {
        document.getElementById('ban_user_id').value = userId;
        document.getElementById('ban_username').textContent = username;
        const modal = document.getElementById('banModal');
        modal.style.display = 'flex';
    }

    function closeBanModal() {
        document.getElementById('banModal').style.display = 'none';
    }

    window.onclick = function(event) {
        const modal = document.getElementById('banModal');
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    }
</script>
</body>
</html>
