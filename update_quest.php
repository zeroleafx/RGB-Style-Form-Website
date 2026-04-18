<?php
session_start();
require_once "db.php";

if (!isset($_SESSION['user_id'])) {
    exit("請先登入");
}

$quest_id = (int)($_POST['quest_id'] ?? 0);
if ($quest_id <= 0) {
    exit("無效的委託 ID");
}

// 先抓 quest
$sql = "SELECT * FROM quests WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $quest_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$quest = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$quest) {
    exit("找不到委託");
}

// 權限檢查
$is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
$is_owner = (
    (($_SESSION['member_group'] ?? '') === 'client') &&
    ((int)$quest['created_by'] === (int)$_SESSION['user_id'])
);

if (!$is_admin && !$is_owner) {
    exit("沒有權限修改此委託");
}

// 讀表單
$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$level_required = (int)($_POST['level_required'] ?? 1);
$difficulty = (int)($_POST['difficulty'] ?? 1);
$reward = (int)($_POST['reward'] ?? 0);
$status = trim($_POST['status'] ?? 'published');
$is_repeatable = isset($_POST['is_repeatable']) ? 1 : 0;

$start_date = $_POST['start_date'] ?? null;
$end_date = $_POST['end_date'] ?? null;

if ($start_date === '' || $start_date === null) {
    $start_date = null;
} else {
    $start_date = str_replace('T', ' ', (string)$start_date) . ':00';
}

if ($end_date === '' || $end_date === null) {
    $end_date = null;
} else {
    $end_date = str_replace('T', ' ', (string)$end_date) . ':00';
}

$field_labels = $_POST['field_label'] ?? [];
$field_types = $_POST['field_type'] ?? [];
$field_required = $_POST['field_required'] ?? [];
$field_options = $_POST['field_options'] ?? [];

if ($title === '' || $description === '') {
    exit("Title 與 Description 不可為空");
}

if ($level_required < 1) $level_required = 1;
if ($difficulty < 1) $difficulty = 1;
if ($reward < 0) $reward = 0;

$allowed_status = ['published', 'draft', 'closed'];
if (!in_array($status, $allowed_status, true)) {
    $status = 'published';
}

$exp_reward = $difficulty * 100;

/*
|--------------------------------------------------
| 自動偵測 quest_fields 真實欄位名
|--------------------------------------------------
*/
$field_columns = [];
$col_result = mysqli_query($conn, "SHOW COLUMNS FROM quest_fields");
while ($col = mysqli_fetch_assoc($col_result)) {
    $field_columns[] = $col['Field'];
}

$label_col = null;
$type_col = null;
$required_col = null;

if (in_array('question_text', $field_columns, true)) {
    $label_col = 'question_text';
} elseif (in_array('label', $field_columns, true)) {
    $label_col = 'label';
} elseif (in_array('field_label', $field_columns, true)) {
    $label_col = 'field_label';
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

if (!$label_col || !$type_col || !$required_col) {
    exit("quest_fields 欄位名稱無法辨識，請檢查資料表結構");
}

/*
|--------------------------------------------------
| 自動偵測 quest_field_options 真實欄位名
|--------------------------------------------------
*/
$option_columns = [];
$opt_col_result = mysqli_query($conn, "SHOW COLUMNS FROM quest_field_options");
while ($col = mysqli_fetch_assoc($opt_col_result)) {
    $option_columns[] = $col['Field'];
}

$option_text_col = null;

if (in_array('option_text', $option_columns, true)) {
    $option_text_col = 'option_text';
} elseif (in_array('option_value', $option_columns, true)) {
    $option_text_col = 'option_value';
} elseif (in_array('value', $option_columns, true)) {
    $option_text_col = 'value';
}

if (!$option_text_col) {
    exit("quest_field_options 欄位名稱無法辨識，請檢查資料表結構");
}

mysqli_begin_transaction($conn);

try {
    // 1. 更新主 quest
    $sql = "UPDATE quests
            SET title = ?, description = ?, level_required = ?, difficulty = ?, reward = ?, exp_reward = ?, status = ?,
                start_date = ?, end_date = ?, is_repeatable = ?
            WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param(
        $stmt,
        "ssiiiisssii",
        $title,
        $description,
        $level_required,
        $difficulty,
        $reward,
        $exp_reward,
        $status,
        $start_date,
        $end_date,
        $is_repeatable,
        $quest_id
    );
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // 2. 刪除舊題目的作答（quest_answers.field_id → quest_fields，不先刪會觸發 FK）
    $sql = "DELETE qa
            FROM quest_answers qa
            INNER JOIN quest_fields qf ON qa.field_id = qf.id
            WHERE qf.quest_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $quest_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // 3. 先刪掉舊 options
    $sql = "DELETE qfo
            FROM quest_field_options qfo
            INNER JOIN quest_fields qf ON qfo.field_id = qf.id
            WHERE qf.quest_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $quest_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // 4. 再刪掉舊 fields
    $sql = "DELETE FROM quest_fields WHERE quest_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $quest_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // 5. 重建 fields + options
    foreach ($field_labels as $i => $label) {
        $label = trim($label);
        $type = trim($field_types[$i] ?? 'text');
        $required = isset($field_required[$i]) ? 1 : 0;
        $options_text = trim($field_options[$i] ?? '');

        if ($label === '') {
            continue;
        }

        if (!in_array($type, ['text', 'textarea', 'radio', 'checkbox'], true)) {
            $type = 'text';
        }

        $sql = "INSERT INTO quest_fields (quest_id, `$label_col`, `$type_col`, `$required_col`)
                VALUES (?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "issi", $quest_id, $label, $type, $required);
        mysqli_stmt_execute($stmt);
        $field_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        if (in_array($type, ['radio', 'checkbox'], true) && $options_text !== '') {
            $lines = preg_split('/\r\n|\r|\n/', $options_text);

            foreach ($lines as $opt) {
                $opt = trim($opt);
                if ($opt === '') continue;

                $sql = "INSERT INTO quest_field_options (field_id, `$option_text_col`)
                        VALUES (?, ?)";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "is", $field_id, $opt);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }
    }

    mysqli_commit($conn);
    header("Location: quest_detail.php?id=" . $quest_id . "&msg=updated");
    exit;

} catch (Throwable $e) {
    mysqli_rollback($conn);
    exit("更新失敗：" . $e->getMessage());
}
?>