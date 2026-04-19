<?php
session_start();
require_once "db.php";

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: index.php");
    exit;
}

// Check if announcements table exists
$create_table_sql = "CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT 1,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
)";
mysqli_query($conn, $create_table_sql);
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Announcements</title>
    <link href="https://use.fontawesome.com/releases/v6.5.0/css/all.css" rel="stylesheet" crossorigin="anonymous">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
    <link href="css/styles.css" rel="stylesheet" />
    <style>
        body {
            background:
                linear-gradient(rgba(24, 10, 42, 0.76), rgba(10, 12, 24, 0.82)),
                url("assets/img/header-bg.png") center/cover fixed no-repeat;
            color: #f5f1ff;
        }

        .announcement-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }

        .form-section {
            background: rgba(29, 17, 47, 0.9);
            padding: 28px;
            border: 3px solid rgba(216, 200, 255, 0.82);
            box-shadow:
                0 0 0 2px rgba(58, 42, 82, 0.9),
                0 0 20px rgba(184, 151, 255, 0.18);
            margin-bottom: 30px;
            font-family: 'Press Start 2P', monospace;
        }

        .form-section h2 {
            margin-top: 0;
            color: #e6d9ff;
            font-family: 'Press Start 2P', cursive;
            font-size: 18px;
            margin-bottom: 20px;
            text-shadow: 2px 2px 0 rgba(58, 42, 82, 0.8);
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #f0dcff;
            font-size: 12px;
            font-family: 'Press Start 2P', monospace;
        }

        .form-group input[type="text"],
        .form-group input[type="datetime-local"],
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid rgba(216, 200, 255, 0.6);
            background: #1a1330;
            color: #fff;
            font-family: 'Press Start 2P', monospace;
            font-size: 11px;
            box-sizing: border-box;
            box-shadow: inset 0 0 0 2px rgba(58, 42, 82, 0.7);
        }

        .form-group input[type="text"]:focus,
        .form-group input[type="datetime-local"]:focus,
        .form-group textarea:focus {
            border-color: #d8c8ff;
            box-shadow: inset 0 0 0 2px rgba(58, 42, 82, 0.7), 0 0 0 3px rgba(216, 200, 255, 0.24);
            outline: none;
        }

        .form-group input[type="datetime-local"]::placeholder {
            color: rgba(255, 255, 255, 0.4);
        }

        .form-group input[type="datetime-local"]::-webkit-calendar-picker-indicator {
            filter: invert(0.8) brightness(1.2);
            cursor: pointer;
        }

        .form-group textarea {
            min-height: 150px;
            resize: vertical;
        }

        .btn {
            padding: 12px 16px;
            border: 2px solid #e6d9ff;
            cursor: pointer;
            font-weight: bold;
            font-size: 11px;
            transition: all 0.2s;
            font-family: 'Press Start 2P', monospace;
        }

        .btn-primary {
            background: linear-gradient(180deg, #c3a6ff 0%, #9f7ae8 100%);
            color: #fff;
            box-shadow: 0 0 10px rgba(184, 151, 255, 0.3), 3px 3px 0 #3a2a52;
        }

        .btn-primary:hover {
            transform: translate(2px, 2px);
            box-shadow: 0 0 6px rgba(184, 151, 255, 0.22), 1px 1px 0 #3a2a52;
        }

        .btn-danger {
            background: linear-gradient(180deg, #ff4f4f 0%, #d44e4e 100%);
            color: white;
            border: 2px solid #ffb3b3;
            box-shadow: 0 0 10px rgba(255, 79, 79, 0.3), 3px 3px 0 #3a2a52;
        }

        .btn-danger:hover {
            transform: translate(2px, 2px);
            box-shadow: 0 0 6px rgba(255, 79, 79, 0.22), 1px 1px 0 #3a2a52;
        }

        .btn-warning {
            background: linear-gradient(180deg, #2ec4b6 0%, #1f9d93 100%);
            color: #08141f;
            border: 2px solid #b8fff7;
            box-shadow: 0 0 10px rgba(46, 196, 182, 0.24), 3px 3px 0 #3a2a52;
        }

        .btn-warning:hover {
            transform: translate(2px, 2px);
            box-shadow: 0 0 6px rgba(46, 196, 182, 0.22), 1px 1px 0 #3a2a52;
        }

        .announcements-list {
            background: rgba(29, 17, 47, 0.9);
            border: 3px solid rgba(216, 200, 255, 0.82);
            box-shadow:
                0 0 0 2px rgba(58, 42, 82, 0.9),
                0 0 20px rgba(184, 151, 255, 0.18);
            overflow: hidden;
        }

        .announcement-item {
            border-bottom: 2px solid rgba(216, 200, 255, 0.4);
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background-color 0.3s;
        }

        .announcement-item:hover {
            background-color: rgba(184, 151, 255, 0.1);
        }

        .announcement-item:last-child {
            border-bottom: none;
        }

        .announcement-info h3 {
            margin: 0 0 10px 0;
            color: #e6d9ff;
            font-size: 14px;
            font-family: 'Press Start 2P', monospace;
        }

        .announcement-info p {
            margin: 5px 0;
            color: #b8a8d8;
            font-size: 11px;
            font-family: 'Press Start 2P', monospace;
        }

        .announcement-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border: 2px solid;
            font-size: 10px;
            font-weight: bold;
            font-family: 'Press Start 2P', monospace;
        }

        .status-active {
            background: linear-gradient(180deg, #2ec4b6 0%, #1f9d93 100%);
            color: #08141f;
            border-color: #b8fff7;
            box-shadow: 0 0 8px rgba(46, 196, 182, 0.3), 2px 2px 0 rgba(58, 42, 82, 0.4);
        }

        .status-inactive {
            background: linear-gradient(180deg, #8b6e88 0%, #6b5471 100%);
            color: #f0dcff;
            border-color: #b8a8d8;
            box-shadow: 0 0 8px rgba(139, 110, 136, 0.3), 2px 2px 0 rgba(58, 42, 82, 0.4);
        }

        .empty-message {
            text-align: center;
            padding: 40px;
            color: #b8a8d8;
            font-family: 'Press Start 2P', monospace;
        }

        .message {
            display: none;
        }

        .message.success {
            background: linear-gradient(180deg, #2ec4b6 0%, #1f9d93 100%);
            color: #08141f;
            border-color: #b8fff7;
        }

        .message.error {
            background: linear-gradient(180deg, #ff4f4f 0%, #d44e4e 100%);
            color: white;
            border-color: #ffb3b3;
        }

        .loading {
            text-align: center;
            padding: 20px;
            color: #b8a8d8;
            font-family: 'Press Start 2P', monospace;
        }


        @media (max-width: 768px) {
            .announcement-item {
                flex-direction: column;
            }

            .announcement-actions {
                margin-top: 15px;
                width: 100%;
            }

            .announcement-actions button {
                flex: 1;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <div class="announcement-container">
        <a class="pixel-link" href="index.php">← Back</a>

        <div class="form-section">
            <h2>New Announcement</h2>
            <form id="announcementForm">
                <div class="form-group">
                    <label for="title">Title *</label>
                    <input type="text" id="title" name="title" required placeholder="Enter Announcement Title" />
                </div>

                <div class="form-group">
                    <label for="content">Content *</label>
                    <textarea id="content" name="content" required placeholder="Enter announcement content"></textarea>
                </div>

                <div class="form-group">
                    <label for="end_time">End Time (Optional)</label>
                    <input type="datetime-local" id="end_time" name="end_time" />
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i> Publish
                </button>
                <button type="button" class="btn btn-warning" id="cancelEditBtn" style="display: none; margin-left: 10px;">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </form>

            <div id="formMessage"></div>
        </div>

        <div class="announcements-list">
            <div style="padding: 20px; border-bottom: 1px solid #eee;">
                <h2 style="margin: 0; font-family: 'Press Start 2P', cursive; font-size: 18px; color: #e6d9ff;">Announcement List</h2>
            </div>

            <div id="announcementsList" class="loading">
                <i class="fas fa-spinner fa-spin"></i> Loading...
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Load Announcements
        async function loadAnnouncements() {
            try {
                const response = await fetch('announcement_api.php?action=get_all_admin');
                const result = await response.json();

                const container = document.getElementById('announcementsList');

                if (!result.success) {
                    container.innerHTML = '<div class="empty-message">Failed to Load Announcements</div>';
                    return;
                }

                if (result.data.length === 0) {
                    container.innerHTML = '<div class="empty-message">No Announcements Available</div>';
                    return;
                }

                container.innerHTML = result.data.map(announcement => `
                    <div class="announcement-item">
                        <div class="announcement-info">
                            <h3>${escapeHtml(announcement.title)}</h3>
                            <p>${escapeHtml(announcement.content.substring(0, 100))}${announcement.content.length > 100 ? '...' : ''}</p>
                            <p>
                                Publisher: ${announcement.username || 'Unknown'} |
                                Created: ${new Date(announcement.created_at).toLocaleString('zh-CN')}
                            </p>
                            <p>End Time: ${announcement.end_time ? new Date(announcement.end_time).toLocaleString('zh-CN') : '--'}</p>
                            <p>
                                <span class="status-badge ${parseInt(announcement.is_active) === 1 ? 'status-active' : 'status-inactive'}">
                                    ${parseInt(announcement.is_active) === 1 ? 'Active' : 'Inactive'}
                                </span>
                            </p>
                        </div>
                        <div class="announcement-actions">
                            <button class="btn btn-warning" onclick="editAnnouncement(${announcement.id})">
                                Edit
                            </button>
                            <button class="btn btn-warning" onclick="toggleStatus(${announcement.id})">
                                Toggle
                            </button>
                            <button class="btn btn-danger" onclick="deleteAnnouncement(${announcement.id})">
                                Delete
                            </button>
                        </div>
                    </div>
                `).join('');
            } catch (error) {
                console.error('Error loading announcements:', error);
                document.getElementById('announcementsList').innerHTML = '<div class="empty-message">Failed to load announcements</div>';
            }
        }

        // Delete Announcement
        async function deleteAnnouncement(id) {
            if (!confirm('You sure you want to delete this announcement?')) return;

            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('id', id);

            try {
                const response = await fetch('announcement_api.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    showMessage(result.message, 'success');
                    loadAnnouncements();
                } else {
                    showMessage(result.message, 'error');
                }
            } catch (error) {
                showMessage('Failed to delete announcement', 'error');
            }
        }

        // Toggle Announcement Status
        async function toggleStatus(id) {
            const formData = new FormData();
            formData.append('action', 'toggle_active');
            formData.append('id', id);

            try {
                const response = await fetch('announcement_api.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    showMessage(result.message, 'success');
                    loadAnnouncements();
                } else {
                    showMessage(result.message, 'error');
                }
            } catch (error) {
                showMessage('Failed to toggle announcement status', 'error');
            }
        }

        // Edit Announcement
        async function editAnnouncement(id) {
            // Find the announcement to edit
            const formData = new FormData();
            formData.append('action', 'get_all_admin');

            try {
                const response = await fetch('announcement_api.php?action=get_all_admin');
                const result = await response.json();

                if (!result.success) {
                    showMessage('Failed to load announcement', 'error');
                    return;
                }

                const announcement = result.data.find(a => a.id == id);
                if (!announcement) {
                    showMessage('Announcement not found', 'error');
                    return;
                }

                // Populate the form with current values
                document.getElementById('title').value = announcement.title;
                document.getElementById('content').value = announcement.content;
                document.getElementById('announcementForm').dataset.editId = id;

                // Change button text and form title
                const form = document.getElementById('announcementForm');
                const submitBtn = form.querySelector('button[type="submit"]');
                const cancelBtn = document.getElementById('cancelEditBtn');
                const formTitle = form.parentElement.querySelector('h2');

                formTitle.textContent = 'Edit Announcement';
                submitBtn.innerHTML = '<i class="fas fa-save"></i> Update';
                cancelBtn.style.display = 'inline-block';

                // Scroll to form
                form.parentElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
            } catch (error) {
                showMessage('Failed to load announcement for editing', 'error');
            }
        }

        // Show Message as Toast
        function showMessage(message, type) {
            // Remove existing toast if any
            const existingToast = document.querySelector('.toast-notification');
            if (existingToast) {
                existingToast.remove();
            }

            const toast = document.createElement('div');
            toast.className = `toast-notification toast-${type}`;
            toast.textContent = message;
            document.body.appendChild(toast);

            setTimeout(() => {
                toast.classList.add('show');
            }, 10);

            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => {
                    toast.remove();
                }, 300);
            }, 2000);
        }

        // HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Cancel edit
        document.getElementById('cancelEditBtn').addEventListener('click', () => {
            const form = document.getElementById('announcementForm');
            form.reset();
            delete form.dataset.editId;

            const formTitle = form.parentElement.querySelector('h2');
            const submitBtn = form.querySelector('button[type="submit"]');
            const cancelBtn = document.getElementById('cancelEditBtn');

            formTitle.textContent = 'New Announcement';
            submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Publish';
            cancelBtn.style.display = 'none';
        });

        // Publish or Update
        document.getElementById('announcementForm').addEventListener('submit', async (e) => {
            e.preventDefault();

            const form = document.getElementById('announcementForm');
            const editId = form.dataset.editId;
            const isEditing = editId && editId !== '';

            const formData = new FormData();
            formData.append('action', isEditing ? 'update' : 'create');

            if (isEditing) {
                formData.append('id', editId);
            }

            formData.append('title', document.getElementById('title').value);
            formData.append('content', document.getElementById('content').value);
            if (!isEditing) {
                formData.append('end_time', document.getElementById('end_time').value || '');
            }

            try {
                const response = await fetch('announcement_api.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    showMessage(result.message, 'success');
                    document.getElementById('announcementForm').reset();
                    delete form.dataset.editId;

                    // Reset form title and button
                    const formTitle = form.parentElement.querySelector('h2');
                    const submitBtn = form.querySelector('button[type="submit"]');
                    formTitle.textContent = 'New Announcement';
                    submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Publish';

                    loadAnnouncements();
                } else {
                    showMessage(result.message, 'error');
                }
            } catch (error) {
                showMessage(isEditing ? 'Failed to update announcement' : 'Failed to create announcement', 'error');
            }
        });

        // Load announcements on page load
        loadAnnouncements();
    </script>

    <style>
        @media (max-width: 768px) {
            .announcement-item {
                flex-direction: column;
            }

            .announcement-actions {
                margin-top: 15px;
                width: 100%;
            }

            .announcement-actions button {
                flex: 1;
            }
        }

        /* Toast Notification Styles */
        .toast-notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 10px 16px;
            border: 2px solid;
            border-radius: 4px;
            font-family: 'Press Start 2P', monospace;
            font-size: 8px;
            max-width: 200px;
            word-wrap: break-word;
            z-index: 9999;
            opacity: 0;
            transition: opacity 0.3s ease;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.3), 3px 3px 0 rgba(58, 42, 82, 0.4);
        }

        .toast-notification.show {
            opacity: 1;
        }

        .toast-success {
            background: linear-gradient(180deg, #2ec4b6 0%, #1f9d93 100%);
            color: #08141f;
            border-color: #b8fff7;
        }

        .toast-error {
            background: linear-gradient(180deg, #ff4f4f 0%, #d44e4e 100%);
            color: white;
            border-color: #ffb3b3;
        }
    </style>
</body>
</html>
