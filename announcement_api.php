<?php
session_start();
require_once "db.php";
header('Content-Type: application/json');

// Check if table exists and add end_time column if needed
$create_table_sql = "CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT 1,
    end_time DATETIME,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
)";
mysqli_query($conn, $create_table_sql);

// Add end_time column if it doesn't exist
$check_column = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='announcements' AND COLUMN_NAME='end_time'";
$result = mysqli_query($conn, $check_column);
if (mysqli_num_rows($result) === 0) {
    mysqli_query($conn, "ALTER TABLE announcements ADD COLUMN end_time DATETIME");
}

// Create user_announcement_closed table to track closed announcements per user
$create_closed_table = "CREATE TABLE IF NOT EXISTS user_announcement_closed (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    announcement_id INT NOT NULL,
    closed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_announcement (user_id, announcement_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE
)";
mysqli_query($conn, $create_closed_table);

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Get all active announcements (only for logged-in users)
if ($action === 'get_all') {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => true, 'data' => []]);
        exit;
    }

    $user_id = $_SESSION['user_id'];
    $sql = "SELECT a.id, a.title, a.content, a.created_at, a.end_time
            FROM announcements a
            LEFT JOIN user_announcement_closed uac ON a.id = uac.announcement_id AND uac.user_id = ?
            WHERE a.is_active = 1
            AND (a.end_time IS NULL OR a.end_time > NOW())
            AND uac.id IS NULL
            ORDER BY a.created_at DESC";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $announcements = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $announcements[] = $row;
    }

    mysqli_stmt_close($stmt);
    echo json_encode(['success' => true, 'data' => $announcements]);
    exit;
}

// Mark announcement as closed for user
if ($action === 'close') {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'User not logged in']);
        exit;
    }

    $user_id = $_SESSION['user_id'];
    $announcement_id = (int)$_POST['announcement_id'] ?? 0;

    if (empty($announcement_id)) {
        echo json_encode(['success' => false, 'message' => 'Announcement ID required']);
        exit;
    }

    $sql = "INSERT IGNORE INTO user_announcement_closed (user_id, announcement_id) VALUES (?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $user_id, $announcement_id);

    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'Announcement closed']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to close']);
    }
    mysqli_stmt_close($stmt);
    exit;
}

if (($_SESSION['role'] ?? '') !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Only admins can manage announcements']);
    exit;
}

// Create announcement
if ($action === 'create') {
    $title = $_POST['title'] ?? '';
    $content = $_POST['content'] ?? '';
    $end_time = $_POST['end_time'] ?? '';
    $user_id = $_SESSION['user_id'] ?? 0;

    if (empty($title) || empty($content)) {
        echo json_encode(['success' => false, 'message' => 'Title and content cannot be empty']);
        exit;
    }

    $title = mysqli_real_escape_string($conn, $title);
    $content = mysqli_real_escape_string($conn, $content);

    // Convert datetime-local format (2026-04-19T14:30) to MySQL format (2026-04-19 14:30)
    if (!empty($end_time)) {
        $end_time = str_replace('T', ' ', $end_time);
    } else {
        $end_time = null;
    }

    $sql = "INSERT INTO announcements (title, content, created_by, end_time) VALUES (?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
        exit;
    }

    mysqli_stmt_bind_param($stmt, "ssis", $title, $content, $user_id, $end_time);

    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'Announcement created successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create: ' . mysqli_stmt_error($stmt)]);
    }
    mysqli_stmt_close($stmt);
    exit;
}

// Update announcement
if ($action === 'update') {
    $id = (int)$_POST['id'] ?? 0;
    $title = $_POST['title'] ?? '';
    $content = $_POST['content'] ?? '';

    if (empty($id) || empty($title) || empty($content)) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        exit;
    }

    $title = mysqli_real_escape_string($conn, $title);
    $content = mysqli_real_escape_string($conn, $content);

    $sql = "UPDATE announcements SET title = ?, content = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ssi", $title, $content, $id);

    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'Announcement updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update']);
    }
    mysqli_stmt_close($stmt);
    exit;
}

// Delete announcement
if ($action === 'delete') {
    $id = (int)$_POST['id'] ?? 0;

    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'Announcement ID cannot be empty']);
        exit;
    }

    $sql = "DELETE FROM announcements WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);

    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'Announcement deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete']);
    }
    mysqli_stmt_close($stmt);
    exit;
}

// Get all announcements for admin view
if ($action === 'get_all_admin') {
    $sql = "SELECT a.id, a.title, a.content, a.created_at, a.end_time, a.is_active, u.username FROM announcements a LEFT JOIN users u ON a.created_by = u.id ORDER BY a.created_at DESC";
    $result = mysqli_query($conn, $sql);
    $announcements = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $announcements[] = $row;
    }

    echo json_encode(['success' => true, 'data' => $announcements]);
    exit;
}

// Toggle announcement active status
if ($action === 'toggle_active') {
    $id = (int)$_POST['id'] ?? 0;

    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'Announcement ID cannot be empty']);
        exit;
    }

    $sql = "UPDATE announcements SET is_active = NOT is_active WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);

    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success' => true, 'message' => 'Updated']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update']);
    }
    mysqli_stmt_close($stmt);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
mysqli_close($conn);
?>
