<?php
session_start();
require_once "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

if (($_SESSION['role'] ?? '') !== 'admin' && ($_SESSION['member_group'] ?? '') !== 'client') {
    die("你沒有權限發委託");
}

/* Check if user is banned */
$ban_check_sql = "SELECT banned_until, is_permanent_ban FROM users WHERE id = ?";
$ban_check_stmt = mysqli_prepare($conn, $ban_check_sql);
if (!$ban_check_stmt) {
    die("Ban檢查失敗：" . mysqli_error($conn));
}

mysqli_stmt_bind_param($ban_check_stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($ban_check_stmt);
$ban_result = mysqli_stmt_get_result($ban_check_stmt);
$ban_data = mysqli_fetch_assoc($ban_result);
mysqli_stmt_close($ban_check_stmt);

if ($ban_data) {
    $is_permanent_ban = (int)($ban_data['is_permanent_ban'] ?? 0);
    $banned_until = $ban_data['banned_until'] ?? null;

    if ($is_permanent_ban === 1) {
        die("您已被永久封禁，無法發佈委託");
    }

    if ($banned_until !== null && strtotime($banned_until) > time()) {
        die("您正被禁用，無法發佈委託。解禁時間：" . $banned_until);
    }
}

$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$level_required = (int)($_POST['level_required'] ?? 1);
$difficulty = (int)($_POST['difficulty'] ?? 1);
$reward = (int)($_POST['reward'] ?? 0);
$start_date = $_POST['start_date'] ?? null;
$end_date = $_POST['end_date'] ?? null;
$is_repeatable = isset($_POST['is_repeatable']) ? 1 : 0;

if ($start_date === '') {
    $start_date = null;
} else {
    $start_date = str_replace('T', ' ', $start_date) . ':00';
}

if ($end_date === '') {
    $end_date = null;
} else {
    $end_date = str_replace('T', ' ', $end_date) . ':00';
}

$exp_reward = $difficulty * 100;
$created_by = (int)$_SESSION['user_id'];
$questions = $_POST['questions'] ?? [];

if ($title === '' || $description === '') {
    die("標題與內容不可空白");
}

mysqli_begin_transaction($conn);

try {
    // 1. 新增 quests
    $sqlQuest = "INSERT INTO quests
        (title, description, level_required, difficulty, reward, exp_reward, created_by, status, created_at, start_date, end_date, is_repeatable)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'published', NOW(), ?, ?, ?)";

    $stmtQuest = mysqli_prepare($conn, $sqlQuest);

    if (!$stmtQuest) {
        throw new Exception("委託 SQL 準備失敗：" . mysqli_error($conn));
    }

    mysqli_stmt_bind_param(
        $stmtQuest,
        "ssiiiiissi",
        $title,
        $description,
        $level_required,
        $difficulty,
        $reward,
        $exp_reward,
        $created_by,
        $start_date,
        $end_date,
        $is_repeatable
    );

    if (!mysqli_stmt_execute($stmtQuest)) {
        throw new Exception("委託新增失敗：" . mysqli_stmt_error($stmtQuest));
    }

    $quest_id = mysqli_insert_id($conn);
    mysqli_stmt_close($stmtQuest);

    // 2. 新增題目與選項
    if (!empty($questions)) {
        $sqlField = "INSERT INTO quest_fields
            (quest_id, question_text, field_type, is_required, sort_order)
            VALUES (?, ?, ?, ?, ?)";
        $stmtField = mysqli_prepare($conn, $sqlField);

        if (!$stmtField) {
            throw new Exception("題目 SQL 準備失敗：" . mysqli_error($conn));
        }

        $sqlOption = "INSERT INTO quest_field_options
            (field_id, option_text, sort_order)
            VALUES (?, ?, ?)";
        $stmtOption = mysqli_prepare($conn, $sqlOption);

        if (!$stmtOption) {
            throw new Exception("選項 SQL 準備失敗：" . mysqli_error($conn));
        }

        $sort_order = 1;

        foreach ($questions as $question) {
            $question_text = trim($question['question_text'] ?? '');
            $field_type = $question['field_type'] ?? 'text';
            $is_required_field = isset($question['is_required']) ? 1 : 0;

            if ($question_text === '') {
                continue;
            }

            mysqli_stmt_bind_param(
                $stmtField,
                "issii",
                $quest_id,
                $question_text,
                $field_type,
                $is_required_field,
                $sort_order
            );

            if (!mysqli_stmt_execute($stmtField)) {
                throw new Exception("題目新增失敗：" . mysqli_stmt_error($stmtField));
            }

            $field_id = mysqli_insert_id($conn);

            if (($field_type === 'radio' || $field_type === 'checkbox') && !empty($question['options'])) {
                $option_order = 1;

                foreach ($question['options'] as $option_text) {
                    $option_text = trim($option_text);

                    if ($option_text === '') {
                        continue;
                    }

                    mysqli_stmt_bind_param($stmtOption, "isi", $field_id, $option_text, $option_order);

                    if (!mysqli_stmt_execute($stmtOption)) {
                        throw new Exception("選項新增失敗：" . mysqli_stmt_error($stmtOption));
                    }

                    $option_order++;
                }
            }

            $sort_order++;
        }

        mysqli_stmt_close($stmtField);
        mysqli_stmt_close($stmtOption);
    }

    mysqli_commit($conn);
    
    echo "<script>
    window.location.href = 'quest_list.php';
    alert('委託新增成功');
    </script>";
    exit;

} catch (Exception $e) {
    mysqli_rollback($conn);
    echo "新增失敗：" . $e->getMessage();
}
?>