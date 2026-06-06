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
$error_detail = $_GET['detail'] ?? '';
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
    <?php elseif ($msg === 'added'): ?>
        <p class="pixel-label" style="color: #90EE90;">✓ User has been added successfully</p>
    <?php elseif ($msg === 'error'): ?>
        <p class="pixel-label" style="color: #FF6B6B;">✗ An error occurred</p>
        <?php if ($error_detail): ?>
            <p class="pixel-label" style="color: #FFB3BA; font-size: 11px;">Error: <?php echo htmlspecialchars($error_detail); ?></p>
        <?php endif; ?>
    <?php endif; ?>

    <div style="display: flex; justify-content: flex-end; margin-bottom: 12px;">
        <button type="button" class="pixel-btn" onclick="openAddModal()">Add Member</button>
    </div>

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
            $is_permanent_ban = ((int)($user['is_permanent_ban'] ?? 0) === 1);
            $ban_until = !empty($user['banned_until']) ? strtotime($user['banned_until']) : false;
            $is_temp_ban_active = ($ban_until !== false && $ban_until > time());
            $is_active_ban = $is_permanent_ban || $is_temp_ban_active;
            $show_ban_reason = $is_active_ban && !empty($user['ban_reason']);

            if ($is_permanent_ban) {
                $ban_status = '⛔ PERMANENT BAN';
                $ban_color = '#FF6B6B';
            } elseif ($is_temp_ban_active) {
                $ban_date = date('Y-m-d H:i', $ban_until);
                $ban_status = "⏳ Until $ban_date";
                $ban_color = '#FFD700';
            } elseif ($ban_until !== false && $ban_until <= time()) {
                    $clear_sql = "UPDATE users SET banned_until = NULL, is_permanent_ban = 0, ban_reason = NULL WHERE id = ?";
                    $clear_stmt = mysqli_prepare($conn, $clear_sql);
                    if ($clear_stmt) {
                        mysqli_stmt_bind_param($clear_stmt, "i", $user['id']);
                        mysqli_stmt_execute($clear_stmt);
                        mysqli_stmt_close($clear_stmt);
                    }
                    $user['banned_until'] = null;
                    $user['is_permanent_ban'] = 0;
                    $user['ban_reason'] = null;
                    $show_ban_reason = false;
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

                <?php if ($show_ban_reason): ?>
                    <p class="pixel-label" style="color: #FFB3BA; font-size: 11px;">
                        Reason: <?php echo htmlspecialchars($user['ban_reason']); ?>
                    </p>
                <?php endif; ?>

                <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 12px;">
                    <form method="POST" action="modify_user.php" style="margin: 0;">
                        <input type="hidden" name="user_id" value="<?php echo (int)$user['id']; ?>">
                        <button type="submit" class="pixel-small-btn">Edit</button>
                    </form>

                    <?php if ($is_active_ban): ?>
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
<div id="banModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 100; justify-content: center; align-items: center;">
    <div class="pixel-container" style="max-width: 500px; width: 100%;">
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

<!-- Add Member Modal -->
<div id="addModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 100; justify-content: center; align-items: center;">
    <div class="pixel-container" style="max-width: 500px; width: 100%;">
        <h2 class="pixel-title">Add Member</h2>
        <p id="addUserMessage" class="pixel-label" style="display: none; color: #FFB3BA; font-size: 11px;"></p>
        <form id="addUserForm">
            <label class="pixel-label">Member Group:</label>
            <select name="member_group" id="add_member_group" class="pixel-select" style="margin-bottom:12px;">
                <option value="adventurer">Adventurer</option>
                <option value="client">Client</option>
                <option value="admin">Admin</option>
            </select>

            <label class="pixel-label">Username:</label>
            <input type="text" name="username" id="add_username" class="pixel-input" required style="margin-bottom:12px;">

            <label class="pixel-label">Password:</label>
            <input type="password" name="password" id="add_password" class="pixel-input" required style="margin-bottom:12px;">

            <label class="pixel-label">Confirm Password:</label>
            <input type="password" name="confirm_password" id="add_confirm_password" class="pixel-input" required style="margin-bottom:12px;">

            <div style="display: flex; gap: 10px;">
                <button type="submit" class="pixel-btn" style="flex: 1;">Create</button>
                <button type="button" class="pixel-btn" onclick="closeAddModal()" style="flex: 1;">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
    function setAddUserMessage(message, isError) {
        const messageBox = document.getElementById('addUserMessage');
        messageBox.textContent = message;
        messageBox.style.display = 'block';
        messageBox.style.color = isError ? '#FFB3BA' : '#90EE90';
    }

    function openAddModal() {
        document.getElementById('addUserMessage').style.display = 'none';
        document.getElementById('addModal').style.display = 'flex';
    }

    function closeAddModal() {
        document.getElementById('addUserMessage').style.display = 'none';
        document.getElementById('addModal').style.display = 'none';
    }

    document.getElementById('addUserForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const pw = document.getElementById('add_password').value;
        const cpw = document.getElementById('add_confirm_password').value;
        if (pw.length < 8) {
            alert('Password must be at least 8 characters long.');
            return;
        }
        if (pw !== cpw) {
            alert('Passwords do not match.');
            return;
        }

        const form = document.getElementById('addUserForm');
        const formData = new FormData(form);
        formData.append('agree_terms', '1');

        fetch('register_action.php', { method: 'POST', body: formData })
            .then(res => res.text())
            .then(text => {
                if (text && /(successful|成功)/i.test(text)) {
                    closeAddModal();
                    alert('新增成功');
                    window.location.reload();
                } else {
                    setAddUserMessage(text || 'Create failed.', true);
                }
            })
            .catch(err => {
                setAddUserMessage('Create failed: ' + err, true);
            });
    });

    window.addEventListener('click', function(event) {
        const modal = document.getElementById('addModal');
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
</script>
</body>
</html>
