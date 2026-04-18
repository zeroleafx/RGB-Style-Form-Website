<?php
session_start();
require_once "db.php";

if (!isset($_SESSION['user_id'])) {
    exit("請先登入");
}

$quest_id = (int)($_GET['id'] ?? 0);
if ($quest_id <= 0) {
    exit("無效的委託 ID");
}

// 讀 quest
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
    exit("沒有權限編輯此委託");
}

// 讀 quest_fields
$fields = [];
$sql = "SELECT * FROM quest_fields WHERE quest_id = ? ORDER BY id ASC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $quest_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {
    $fieldLabel = $row['question_text'] ?? $row['label'] ?? $row['field_label'] ?? '';
    $fieldType = $row['field_type'] ?? $row['type'] ?? 'text';
    $fieldRequired = $row['is_required'] ?? $row['required'] ?? 0;

    $normalized = [
        'id' => $row['id'],
        'question_text' => $fieldLabel,
        'field_type' => $fieldType,
        'is_required' => $fieldRequired,
        'options' => []
    ];

    if (in_array($fieldType, ['radio', 'checkbox'], true)) {
        $sql2 = "SELECT * FROM quest_field_options WHERE field_id = ? ORDER BY id ASC";
        $stmt2 = mysqli_prepare($conn, $sql2);
        mysqli_stmt_bind_param($stmt2, "i", $row['id']);
        mysqli_stmt_execute($stmt2);
        $result2 = mysqli_stmt_get_result($stmt2);

        while ($opt = mysqli_fetch_assoc($result2)) {
            $normalized['options'][] = $opt['option_text'] ?? $opt['value'] ?? $opt['option_value'] ?? '';
        }
        mysqli_stmt_close($stmt2);
    }

    $fields[] = $normalized;
}

mysqli_stmt_close($stmt);

/** `datetime-local` 用：DB `Y-m-d H:i:s` → `Y-m-d\TH:i` */
$start_local = '';
$end_local = '';
if (!empty($quest['start_date'])) {
    $sd = DateTime::createFromFormat('Y-m-d H:i:s', $quest['start_date']);
    if ($sd) {
        $start_local = $sd->format('Y-m-d\TH:i');
    }
}
if (!empty($quest['end_date'])) {
    $ed = DateTime::createFromFormat('Y-m-d H:i:s', $quest['end_date']);
    if ($ed) {
        $end_local = $ed->format('Y-m-d\TH:i');
    }
}
$is_repeatable_checked = ((int)($quest['is_repeatable'] ?? 0) === 1);
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>Edit Quest</title>
    <link href="css/styles.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
</head>
<body class="pixel-purple-body">
<div class="pixel-container">
    <a class="pixel-link" href="quest_list.php">← Back to Quest List</a>
    <h1 class="pixel-title">Edit Quest</h1>

    <form method="POST" action="update_quest.php">
        <input type="hidden" name="quest_id" value="<?php echo (int)$quest['id']; ?>">

        <label class="pixel-label">Title</label>
        <input
            type="text"
            name="title"
            class="pixel-input"
            required
            value="<?php echo htmlspecialchars($quest['title']); ?>"
        >

        <label class="pixel-label">Description</label>
        <textarea
            name="description"
            rows="5"
            class="pixel-textarea"
            required
        ><?php echo htmlspecialchars($quest['description']); ?></textarea>

        <div class="pixel-row">
            <div>
                <label class="pixel-label">Level Required</label>
                <input
                    type="number"
                    name="level_required"
                    class="pixel-input"
                    min="1"
                    required
                    value="<?php echo (int)$quest['level_required']; ?>"
                >
            </div>
            <div>
                <label class="pixel-label">Difficulty</label>
                <input
                    type="number"
                    name="difficulty"
                    class="pixel-input"
                    min="1"
                    required
                    value="<?php echo (int)$quest['difficulty']; ?>"
                >
            </div>
            <div>
                <label class="pixel-label">Reward</label>
                <input
                    type="number"
                    name="reward"
                    class="pixel-input"
                    min="0"
                    required
                    value="<?php echo (int)$quest['reward']; ?>"
                >
            </div>
        </div>

        <label class="pixel-label">Start Time</label>
        <input
            type="datetime-local"
            name="start_date"
            class="pixel-input"
            value="<?php echo htmlspecialchars($start_local); ?>"
        >

        <label class="pixel-label">End Time</label>
        <input
            type="datetime-local"
            name="end_date"
            class="pixel-input"
            value="<?php echo htmlspecialchars($end_local); ?>"
        >

        <label class="pixel-label">
            <input
                type="checkbox"
                name="is_repeatable"
                value="1"
                class="pixel-checkbox"
                <?php echo $is_repeatable_checked ? 'checked' : ''; ?>
            >
            Repeatable
        </label>

        <label class="pixel-label">Status</label>
        <select name="status" class="pixel-select">
            <option value="published" <?php echo $quest['status'] === 'published' ? 'selected' : ''; ?>>published</option>
            <option value="draft" <?php echo $quest['status'] === 'draft' ? 'selected' : ''; ?>>draft</option>
            <option value="closed" <?php echo $quest['status'] === 'closed' ? 'selected' : ''; ?>>closed</option>
        </select>

        <hr class="pixel-divider">
        <h2 class="pixel-subtitle">Quest Fields</h2>

        <div id="fields-container">
            <?php foreach ($fields as $index => $field): ?>
                <?php
                    $fieldLabel = $field['question_text'] ?? '';
                    $fieldType = $field['field_type'] ?? 'text';
                    $fieldRequired = $field['is_required'] ?? 0;
                    $fieldOptions = $field['options'] ?? [];
                ?>
                <div class="pixel-field-box field-box">
                    <label class="pixel-label">Field Label</label>
                    <input
                        type="text"
                        name="field_label[]"
                        class="pixel-input"
                        required
                        value="<?php echo htmlspecialchars($fieldLabel); ?>"
                    >

                    <label class="pixel-label">Field Type</label>
                    <select
                        name="field_type[]"
                        class="pixel-select field-type-select"
                        onchange="toggleOptions(this)"
                    >
                        <option value="text" <?php echo $fieldType === 'text' ? 'selected' : ''; ?>>Short Answer</option>
                        <option value="textarea" <?php echo $fieldType === 'textarea' ? 'selected' : ''; ?>>Paragraph</option>
                        <option value="radio" <?php echo $fieldType === 'radio' ? 'selected' : ''; ?>>Multiple Choice</option>
                        <option value="checkbox" <?php echo $fieldType === 'checkbox' ? 'selected' : ''; ?>>Checkboxes</option>
                    </select>

                    <label class="pixel-checkbox-label">
                        <input
                            type="checkbox"
                            name="field_required[<?php echo $index; ?>]"
                            class="pixel-checkbox"
                            value="1"
                            <?php echo $fieldRequired ? 'checked' : ''; ?>
                        >
                        Required
                    </label>

                    <div
                        class="pixel-options-box options-box"
                        style="<?php echo in_array($fieldType, ['radio', 'checkbox'], true) ? '' : 'display:none;'; ?>"
                    >
                        <label class="pixel-label">Options (one per line)</label>
                        <textarea
                            name="field_options[]"
                            rows="4"
                            class="pixel-textarea"
                        ><?php echo htmlspecialchars(implode("\n", $fieldOptions)); ?></textarea>
                    </div>

                    <button
                        type="button"
                        class="pixel-small-btn remove-question-btn"
                        onclick="removeField(this)"
                    >
                        Remove Field
                    </button>
                </div>
            <?php endforeach; ?>
        </div>

        <button type="button" class="pixel-btn" onclick="addField()">+ Add Field</button>
        <br><br>
        <button type="submit" class="pixel-btn">Save Changes</button>
    </form>
</div>

<script>
function toggleOptions(selectEl) {
    const box = selectEl.closest('.field-box').querySelector('.options-box');
    if (selectEl.value === 'radio' || selectEl.value === 'checkbox') {
        box.style.display = 'block';
    } else {
        box.style.display = 'none';
    }
}

function removeField(btn) {
    btn.closest('.field-box').remove();
}

function addField() {
    const container = document.getElementById('fields-container');
    const index = container.querySelectorAll('.field-box').length;

    const div = document.createElement('div');
    div.className = 'pixel-field-box field-box';
    div.innerHTML = `
        <label class="pixel-label">Field Label</label>
        <input type="text" name="field_label[]" class="pixel-input" required>

        <label class="pixel-label">Field Type</label>
        <select name="field_type[]" class="pixel-select field-type-select" onchange="toggleOptions(this)">
            <option value="text">Short Answer</option>
            <option value="textarea">Paragraph</option>
            <option value="radio">Multiple Choice</option>
            <option value="checkbox">Checkboxes</option>
        </select>

        <label class="pixel-checkbox-label">
            <input type="checkbox" name="field_required[${index}]" class="pixel-checkbox" value="1">
            Required
        </label>

        <div class="pixel-options-box options-box" style="display:none;">
            <label class="pixel-label">Options (one per line)</label>
            <textarea name="field_options[]" rows="4" class="pixel-textarea"></textarea>
        </div>

        <button type="button" class="pixel-small-btn remove-question-btn" onclick="removeField(this)">
            Remove Field
        </button>
    `;
    container.appendChild(div);
}
</script>
</body>
</html>