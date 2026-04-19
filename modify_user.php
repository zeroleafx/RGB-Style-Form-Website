<?php
session_start();
require_once "db.php";

// Check admin permission
$is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
if (!$is_admin) {
    exit("只有管理員可以進行此操作");
}

$user_id = (int)($_POST['user_id'] ?? $_GET['user_id'] ?? 0);

if ($user_id <= 0) {
    header("Location: manage_users.php");
    exit;
}

// Fetch user data
$sql = "SELECT id, username, member_group, role FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$user) {
    header("Location: manage_users.php");
    exit;
}

// Handle POST request (update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $new_username = trim($_POST['username'] ?? '');
    $new_role = trim($_POST['role'] ?? 'user');
    $new_member_group = trim($_POST['member_group'] ?? '');

    // Validate
    if (empty($new_username) || empty($new_member_group)) {
        $error = "Username and Member Group are required";
    } else {
        // Check if new username is unique (if changed)
        if ($new_username !== $user['username']) {
            $check_sql = "SELECT id FROM users WHERE username = ? AND id != ?";
            $check_stmt = mysqli_prepare($conn, $check_sql);
            mysqli_stmt_bind_param($check_stmt, "si", $new_username, $user_id);
            mysqli_stmt_execute($check_stmt);
            $check_result = mysqli_stmt_get_result($check_stmt);
            if (mysqli_num_rows($check_result) > 0) {
                $error = "Username already exists";
            }
            mysqli_stmt_close($check_stmt);
        }

        if (!isset($error)) {
            // Update user
            $update_sql = "UPDATE users SET username = ?, role = ?, member_group = ? WHERE id = ?";
            $update_stmt = mysqli_prepare($conn, $update_sql);

            if (!$update_stmt) {
                $error = "SQL準備失敗：" . mysqli_error($conn);
            } else {
                mysqli_stmt_bind_param($update_stmt, "sssi", $new_username, $new_role, $new_member_group, $user_id);

                if (!mysqli_stmt_execute($update_stmt)) {
                    $error = "更新失敗：" . mysqli_stmt_error($update_stmt);
                } else {
                    mysqli_stmt_close($update_stmt);
                    header("Location: manage_users.php?msg=modified");
                    exit;
                }
                mysqli_stmt_close($update_stmt);
            }
        }
    }
}

$error = $error ?? '';
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>Edit User - Admin Panel</title>
    <link href="css/styles.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
</head>
<body class="pixel-purple-body">
<div class="pixel-container">
    <a class="pixel-link" href="manage_users.php">← Back</a>
    <h1 class="pixel-title">Edit User</h1>

    <?php if (!empty($error)): ?>
        <div class="pixel-field-box">
            <p class="pixel-label" style="color: #FF6B6B;"><?php echo htmlspecialchars($error); ?></p>
        </div>
    <?php endif; ?>

    <div class="pixel-field-box">
        <form method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="user_id" value="<?php echo (int)$user['id']; ?>">

            <label class="pixel-label" for="username">Username</label>
            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" class="pixel-input" required style="margin-bottom: 16px;">

            <label class="pixel-label" for="role">Role</label>
            <select id="role" name="role" class="pixel-select" required style="margin-bottom: 16px;">
                <option value="user" <?php echo $user['role'] === 'user' || !$user['role'] ? 'selected' : ''; ?>>User</option>
                <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
            </select>

            <label class="pixel-label" for="member_group">Member Group</label>
            <select id="member_group" name="member_group" class="pixel-select" required style="margin-bottom: 20px;">
                <option value="adventurer" <?php echo $user['member_group'] === 'adventurer' ? 'selected' : ''; ?>>Adventurer</option>
                <option value="client" <?php echo $user['member_group'] === 'client' ? 'selected' : ''; ?>>Client</option>
            </select>

            <div style="display: flex; gap: 10px;">
                <button type="submit" class="pixel-btn" style="flex: 1;">💾 Save Changes</button>
                <a href="manage_users.php" class="pixel-btn" style="flex: 1; text-decoration: none; text-align: center;">Cancel</a>
            </div>
        </form>
    </div>
</div>
</body>
</html>
