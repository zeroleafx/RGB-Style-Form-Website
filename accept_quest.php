<?php
session_start();
require_once "db.php";

if (!isset($_SESSION['user_id'])) {
    exit("請先登入");
}

/* Check if user is banned */
$ban_check_sql = "SELECT banned_until, is_permanent_ban FROM users WHERE id = ?";
$ban_check_stmt = mysqli_prepare($conn, $ban_check_sql);
if (!$ban_check_stmt) {
    exit("Ban檢查失敗：" . mysqli_error($conn));
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
        exit("您已被永久封禁，無法申請委託");
    }

    if ($banned_until !== null && strtotime($banned_until) > time()) {
        exit("您正被禁用，無法申請委託。解禁時間：" . $banned_until);
    }
}

if (($_SESSION['role'] ?? '') !== 'admin' && ($_SESSION['member_group'] ?? '') !== 'adventurer') {
    exit("只有冒險家可以申請委託");
}

$quest_id = (int)($_POST['quest_id'] ?? ($_GET['id'] ?? 0));
$user_id = (int)$_SESSION['user_id'];
$answers = $_POST['answers'] ?? [];

if ($quest_id <= 0) {
    exit("無效的委託 ID");
}

/* 1. 查詢委託資料 */
$sqlQuest = "SELECT * FROM quests WHERE id = ?";
$stmtQuest = mysqli_prepare($conn, $sqlQuest);

if (!$stmtQuest) {
    exit("委託查詢準備失敗：" . mysqli_error($conn));
}

mysqli_stmt_bind_param($stmtQuest, "i", $quest_id);
mysqli_stmt_execute($stmtQuest);
$resultQuest = mysqli_stmt_get_result($stmtQuest);

if (!$resultQuest || mysqli_num_rows($resultQuest) === 0) {
    exit("找不到此委託");
}

$quest = mysqli_fetch_assoc($resultQuest);
mysqli_stmt_close($stmtQuest);

/* 2. 只允許 published 委託申請 */
if (($quest['status'] ?? '') !== 'published' && (($_SESSION['role'] ?? '') !== 'admin')) {
    exit("此委託目前不可申請");
}

/* 2b. 報名時間（與 quest_detail 一致；管理員略過） */
if (($_SESSION['role'] ?? '') !== 'admin') {
    $app_timezone = new DateTimeZone('Asia/Taipei');
    $now_dt = new DateTime('now', $app_timezone);
    $start_dt = !empty($quest['start_date'])
        ? DateTime::createFromFormat('Y-m-d H:i:s', $quest['start_date'], $app_timezone)
        : null;
    $end_dt = !empty($quest['end_date'])
        ? DateTime::createFromFormat('Y-m-d H:i:s', $quest['end_date'], $app_timezone)
        : null;
    if ($start_dt instanceof DateTime && $now_dt < $start_dt) {
        exit("此委託尚未開放申請");
    }
    if ($end_dt instanceof DateTime && $now_dt > $end_dt) {
        exit("此委託已截止申請");
    }
}

/* 3. 等級需求檢查 */
$user_level = (int)($_SESSION['level'] ?? 1);
if (($_SESSION['role'] ?? '') !== 'admin' && $user_level < (int)($quest['level_required'] ?? 1)) {
    exit("你的等級不足，無法申請此委託");
}

/* 4. 是否可重複申請 */
$is_repeatable = (int)($quest['is_repeatable'] ?? 0);
if ($is_repeatable === 0) {
    $sqlCheck = "SELECT id FROM quest_responses WHERE quest_id = ? AND user_id = ?";
    $stmtCheck = mysqli_prepare($conn, $sqlCheck);

    if (!$stmtCheck) {
        exit("重複申請檢查失敗：" . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($stmtCheck, "ii", $quest_id, $user_id);
    mysqli_stmt_execute($stmtCheck);
    $resultCheck = mysqli_stmt_get_result($stmtCheck);

    if ($resultCheck && mysqli_num_rows($resultCheck) > 0) {
        mysqli_stmt_close($stmtCheck);
        exit("你已經申請過這個委託了");
    }
    mysqli_stmt_close($stmtCheck);
}

/* 4b. 此任務已有任何人 completed 則全員不可再送（與 quest_detail 一致；管理員略過） */
if (($_SESSION['role'] ?? '') !== 'admin') {
    $sqlDone = "SELECT id FROM quest_responses
                WHERE quest_id = ?
                  AND LOWER(TRIM(COALESCE(status, ''))) = 'completed'
                LIMIT 1";
    $stmtDone = mysqli_prepare($conn, $sqlDone);
    if (!$stmtDone) {
        exit("完成狀態檢查失敗：" . mysqli_error($conn));
    }
    mysqli_stmt_bind_param($stmtDone, "i", $quest_id);
    mysqli_stmt_execute($stmtDone);
    $resDone = mysqli_stmt_get_result($stmtDone);
    if ($resDone && mysqli_num_rows($resDone) > 0) {
        mysqli_stmt_close($stmtDone);
        exit("此委託已有完成紀錄，已不再開放提交");
    }
    mysqli_stmt_close($stmtDone);
}

/* 5. 自動偵測 quest_fields 真實欄位 */
$field_columns = [];
$col_result = mysqli_query($conn, "SHOW COLUMNS FROM quest_fields");
while ($col = mysqli_fetch_assoc($col_result)) {
    $field_columns[] = $col['Field'];
}

$question_col = null;
$type_col = null;
$required_col = null;
$sort_col = null;

if (in_array('question_text', $field_columns, true)) {
    $question_col = 'question_text';
} elseif (in_array('label', $field_columns, true)) {
    $question_col = 'label';
} elseif (in_array('field_label', $field_columns, true)) {
    $question_col = 'field_label';
}

if (in_array('field_type', $field_columns, true)) {
    $type_col = 'field_type';
} elseif (in_array('type', $field_columns, true)) {
    $type_col = 'type';
}

if (in_array('is_required', $field_columns, true)) {
    $required_col = 'is_required';
} elseif (in_array('required', $field_columns, true)) {
    $required_col = 'required';
}

if (in_array('sort_order', $field_columns, true)) {
    $sort_col = 'sort_order';
}

if (!$question_col || !$type_col || !$required_col) {
    exit("quest_fields 欄位結構無法辨識");
}

/* 6. 讀取此委託所有題目 */
$order_sql = $sort_col ? "ORDER BY `$sort_col` ASC, id ASC" : "ORDER BY id ASC";
$sqlFields = "SELECT * FROM quest_fields WHERE quest_id = ? $order_sql";
$stmtFields = mysqli_prepare($conn, $sqlFields);

if (!$stmtFields) {
    exit("題目查詢準備失敗：" . mysqli_error($conn));
}

mysqli_stmt_bind_param($stmtFields, "i", $quest_id);
mysqli_stmt_execute($stmtFields);
$resultFields = mysqli_stmt_get_result($stmtFields);

$field_list = [];
while ($field = mysqli_fetch_assoc($resultFields)) {
    $field_list[$field['id']] = [
        'id' => (int)$field['id'],
        'question' => $field[$question_col] ?? '',
        'type' => $field[$type_col] ?? 'text',
        'required' => (int)($field[$required_col] ?? 0),
    ];
}
mysqli_stmt_close($stmtFields);

/* 7. 必填題目驗證 */
foreach ($field_list as $field_id => $field) {
    if ($field['required'] === 1) {
        if ($field['type'] === 'checkbox') {
            if (!isset($answers[$field_id]) || !is_array($answers[$field_id]) || count($answers[$field_id]) === 0) {
                exit("必填題目未完成：" . htmlspecialchars($field['question']));
            }
        } else {
            if (!isset($answers[$field_id]) || trim((string)$answers[$field_id]) === '') {
                exit("必填題目未完成：" . htmlspecialchars($field['question']));
            }
        }
    }
}

/* 8. 開始交易 */
mysqli_begin_transaction($conn);

try {
    /* 新增申請紀錄 quest_responses */
    $status = 'pending';
    $reward_earned = 0;
    $exp_earned = 0;

    $sqlResponse = "INSERT INTO quest_responses 
        (quest_id, user_id, status, reward_earned, exp_earned, submitted_at, completed_at)
        VALUES (?, ?, ?, ?, ?, NOW(), NULL)";

    $stmtResponse = mysqli_prepare($conn, $sqlResponse);

    if (!$stmtResponse) {
        throw new Exception("申請紀錄 SQL 準備失敗：" . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($stmtResponse, "iisii", $quest_id, $user_id, $status, $reward_earned, $exp_earned);

    if (!mysqli_stmt_execute($stmtResponse)) {
        throw new Exception("申請紀錄新增失敗：" . mysqli_stmt_error($stmtResponse));
    }

    $response_id = mysqli_insert_id($conn);
    mysqli_stmt_close($stmtResponse);

    /* 新增答案 quest_answers */
    if (!empty($field_list)) {
        $sqlAnswer = "INSERT INTO quest_answers (response_id, field_id, answer_text)
                      VALUES (?, ?, ?)";
        $stmtAnswer = mysqli_prepare($conn, $sqlAnswer);

        if (!$stmtAnswer) {
            throw new Exception("答案 SQL 準備失敗：" . mysqli_error($conn));
        }

        foreach ($field_list as $field_id => $field) {
            if (!isset($answers[$field_id])) {
                continue;
            }

            if ($field['type'] === 'checkbox') {
                $value = is_array($answers[$field_id]) ? implode(", ", $answers[$field_id]) : '';
            } else {
                $value = trim((string)$answers[$field_id]);
            }

            mysqli_stmt_bind_param($stmtAnswer, "iis", $response_id, $field_id, $value);

            if (!mysqli_stmt_execute($stmtAnswer)) {
                throw new Exception("答案新增失敗：" . mysqli_stmt_error($stmtAnswer));
            }
        }

        mysqli_stmt_close($stmtAnswer);
    }

    mysqli_commit($conn);
    header("Location: quest_detail.php?id=" . $quest_id . "&msg=applied");
    exit;

} catch (Throwable $e) {
    mysqli_rollback($conn);
    exit("申請失敗：" . $e->getMessage());
}
?>