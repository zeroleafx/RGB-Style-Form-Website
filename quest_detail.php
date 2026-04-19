<?php
session_start();
require_once("db.php");
require_once("helpers.php");

// Close any expired quests
close_expired_quests($conn);

$search = trim($_GET['search']??'');
$sort = $_GET['sort']??'newest';

$sort_map = ['newest' => 'q.created_at DESC', 
            'oldest' => 'q.created_at ASC', 
            'level_high' => 'q.level_required DESC', 
            'level_low' => 'q.level_required ASC', 
            'difficulty_high' => 'q.difficulty DESC', 
            'difficulty_low' => 'q.difficulty ASC', 
            'highest_reward' => 'q.reward DESC', 
            'lowest_reward' => 'q.reward ASC'
        ];

if(!isset($sort_map[$sort])){
    $sort = 'newest';
}

$quest_id = (int)($_GET['id']??0);

if($quest_id <= 0){
    die("找不到任務");
}

$sqlQuest = "SELECT q.*, u.username AS creator_name,
        (SELECT COUNT(*) FROM quest_responses qr WHERE qr.quest_id = q.id) AS apply_count,
        (SELECT COUNT(*) FROM quest_responses qr2
         WHERE qr2.quest_id = q.id AND LOWER(TRIM(COALESCE(qr2.status, ''))) = 'completed') AS completed_response_count
        FROM quests q
        LEFT JOIN users u ON q.created_by = u.id WHERE q.id = ?";
$stmtQuest = $conn->prepare($sqlQuest);

if (!$stmtQuest) {
    die("委託查詢準備失敗：" . mysqli_error($conn));
}

mysqli_stmt_bind_param($stmtQuest,"i", $quest_id);
$stmtQuest->execute();
$resultQuest = $stmtQuest->get_result();

if (!$resultQuest || mysqli_num_rows($resultQuest) === 0) {
    die("找不到此委託");
}

$quest =$resultQuest->fetch_assoc();

$sqlField = "SELECT * FROM quest_fields WHERE quest_id = ? 
ORDER BY sort_order ASC, id ASC";
$stmtField = $conn->prepare($sqlField);
if (!$stmtField) {
    die("題目查詢準備失敗：". mysqli_error($conn));
}
$stmtField->bind_param("i", $quest_id);
$stmtField->execute();
$resultField = $stmtField->get_result();

$fields = [];
while ($field = $resultField->fetch_assoc()) {
    $field['options']=[];
    $fields[$field['id']] = $field;
}

if(!empty($fields)){
    $field_ids = implode(",", array_map('intval', array_keys($fields)));

    $sqlOption = "SELECT * FROM quest_field_options 
    WHERE field_id IN ($field_ids) ORDER BY sort_order ASC, id ASC";

    $resultOptions = $conn->query($sqlOption);

    if($resultOptions){
        while ($option = $resultOptions->fetch_assoc()){
            $fields[$option['field_id']]['options'][] = $option;
        }
    }
}

$is_logged_in = isset($_SESSION['user_id']);
$is_adventurer = $is_logged_in && (($_SESSION['member_group'] ?? '') === 'adventurer');
$is_admin = $is_logged_in && (($_SESSION['role'] ?? '') === 'admin');
$is_client_owner = $is_logged_in
    && (($_SESSION['member_group'] ?? '') === 'client')
    && ((int)$quest['created_by'] === (int)$_SESSION['user_id']);

$user_level = (int)($_SESSION['level'] ?? 1);
$required_level = (int)$quest['level_required'];
$level_locked = $user_level < $required_level;

$app_timezone = new DateTimeZone('Asia/Taipei');
$now_dt = new DateTime('now', $app_timezone);

$start_dt = !empty($quest['start_date'])
    ? DateTime::createFromFormat('Y-m-d H:i:s', $quest['start_date'], $app_timezone)
    : null;
$end_dt = !empty($quest['end_date'])
    ? DateTime::createFromFormat('Y-m-d H:i:s', $quest['end_date'], $app_timezone)
    : null;

$time_not_started = $start_dt instanceof DateTime && $now_dt < $start_dt;
$time_ended = $end_dt instanceof DateTime && $now_dt > $end_dt;
$time_locked = $time_not_started || $time_ended;
$can_participate = ($is_adventurer);

$already_applied = false;
$is_repeatable = (int)($quest['is_repeatable'] ?? 0);

if ($is_adventurer && $is_repeatable === 0) {
    $sqlCheck = "SELECT id FROM quest_responses 
                 WHERE quest_id = ? AND user_id = ?";
    $stmtCheck = mysqli_prepare($conn, $sqlCheck);
    mysqli_stmt_bind_param($stmtCheck, "ii", $quest['id'], $_SESSION['user_id']);
    mysqli_stmt_execute($stmtCheck);
    $resultCheck = mysqli_stmt_get_result($stmtCheck);

    $already_applied = ($resultCheck && mysqli_num_rows($resultCheck) > 0);

    mysqli_stmt_close($stmtCheck);
}

$my_submissions = [];
if ($is_logged_in && ($is_adventurer || $is_admin)) {
    $sqlMy = "SELECT id, status, submitted_at
              FROM quest_responses
              WHERE quest_id = ? AND user_id = ?
              ORDER BY submitted_at ASC, id ASC";
    $stmtMy = mysqli_prepare($conn, $sqlMy);
    if ($stmtMy) {
        mysqli_stmt_bind_param($stmtMy, "ii", $quest['id'], $_SESSION['user_id']);
        mysqli_stmt_execute($stmtMy);
        $resMy = mysqli_stmt_get_result($stmtMy);
        if ($resMy) {
            while ($r = mysqli_fetch_assoc($resMy)) {
                $my_submissions[] = $r;
            }
        }
        mysqli_stmt_close($stmtMy);
    }
}

$quest_closed_by_completion = (int)($quest['completed_response_count'] ?? 0) > 0;
$admin_bypass_completion_lock = (($_SESSION['role'] ?? '') === 'admin');

$can_submit = $can_participate
    && !$time_locked
    && !$level_locked
    && !$already_applied
    && (!$quest_closed_by_completion || $admin_bypass_completion_lock);

?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quest Detail</title>
    <link href="css/styles.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
    <style>
        h1{font-size: 25px;}
        body, html {
            height: 100%;
        }

        body {
        background-image: url("./assets/img/quest-bg.png");
        height: 100%;
        background-position: center;             /* Center the image within the viewport */
        background-repeat: no-repeat;            /* Prevent the image from repeating */
        background-size: cover;                  /* Scale the image to cover the entire container */
        background-attachment: fixed;       
        }

        body::before{
            content: "";
            position: fixed;
            inset: 0;

            background: rgba(15, 8, 30, 0.65);  /* 遮罩顏色 */

            pointer-events: none;
            z-index: 0;
        }

        body > *{
            position: relative;
            z-index: 1;
        }
    </style>
</head>

<body>
<div class="quest-detail-page">
    <div class="quest-detail-container">
        <div class="action-row">
            <a class="btn-link" href="<?php echo (($_GET['from'] ?? 'quest_list') === 'my_quests') ? 'my_quests.php' : 'quest_list.php'; ?>">← Back</a>
        </div>

        <div class="quest-panel">
            <h1><?php echo htmlspecialchars($quest['title']); ?></h1>

            <div class="meta-line">
                <strong>Publisher:</strong>
                <?php echo htmlspecialchars($quest['creator_name'] ?? 'Unknown'); ?>
            </div>

            <div class="meta-line">
                <strong>Level Required:</strong>
                <?php echo (int)$quest['level_required']; ?>
                |
                <strong>Difficulty:</strong>
                <?php echo (int)$quest['difficulty']; ?>
                |
                <strong>Reward:</strong>
                <?php echo (int)$quest['reward']; ?>
                |
                <strong>EXP:</strong>
                <?php echo (int)$quest['exp_reward']; ?>
            </div>

            <div class="meta-line">
                <strong>Status:</strong>
                <?php echo htmlspecialchars($quest['status']); ?>
            </div>

            <div class="meta-line">
                <strong>Apply Period:</strong>
                <?php
                $start_text = !empty($quest['start_date']) ? htmlspecialchars($quest['start_date']) : 'No limit';
                $end_text = !empty($quest['end_date']) ? htmlspecialchars($quest['end_date']) : 'No limit';
                echo $start_text . " ~ " . $end_text;
                ?>
            </div>

            <div class="meta-line">
                <strong>Repeatable:</strong>
                <?php echo ((int)($quest['is_repeatable'] ?? 0) === 1) ? 'Yes' : 'No'; ?>
                |
                <strong>Apply Count:</strong>
                <?php echo (int)($quest['apply_count'] ?? 0); ?>
            </div>

            <div class="quest-description">
                <?php echo nl2br(htmlspecialchars($quest['description'])); ?>
            </div>

            <?php if ($can_participate && $is_repeatable === 1): ?>
                <div class="notice my-submissions-summary">
                    <strong>Your submission count:</strong>
                    <?php echo count($my_submissions); ?>

                    <?php if (!empty($my_submissions)): ?>
                        <ul class="my-submissions-list">
                            <?php foreach ($my_submissions as $idx => $sub): ?>
                                <?php
                                $nth = $idx + 1;
                                $st = strtolower(trim((string)($sub['status'] ?? '')));
                                if ($st === '') {
                                    $st = 'pending';
                                }
                                ?>
                                <li>
                                    <strong>#<?php echo $nth; ?></strong>
                                    — <?php echo htmlspecialchars($st); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

            <?php elseif ($can_participate && $is_repeatable === 0 && !empty($my_submissions)): ?>
                <div class="notice my-submissions-summary">
                    <?php
                    $st = strtolower(trim((string)($my_submissions[0]['status'] ?? '')));
                    if ($st === '') {
                        $st = 'pending';
                    }
                    ?>
                    <strong>Status -</strong> <?php echo htmlspecialchars($st); ?>
                </div>
            <?php endif; ?>

            <?php if ($quest_closed_by_completion && $can_participate && !$admin_bypass_completion_lock): ?>
                <div class="notice quest-completion-lock-notice">
                    <strong>Applications closed:</strong>
                    this quest already has a completed submission. No one can submit again.
                </div>
            <?php endif; ?>

            <?php if (!$is_logged_in): ?>
                <div class="notice">Please login to apply for this quest.</div>
            <?php elseif (!$can_participate && !$is_client_owner && !$is_admin): ?>
                <div class="notice">View only.</div>
            <?php endif; ?>

            <?php if (!empty($fields)): ?>
                <form action="accept_quest.php" method="POST">
                    <input type="hidden" name="quest_id" value="<?php echo (int)$quest['id']; ?>">

                    <?php foreach ($fields as $field): ?>
                        <div class="field-block">
                            <div class="field-title">
                                <?php echo htmlspecialchars($field['question_text']); ?>
                                <?php if ((int)$field['is_required'] === 1): ?>
                                    <span class="required-mark">*</span>
                                <?php endif; ?>
                            </div>

                            <?php
                            $required = ((int)$field['is_required'] === 1) ? 'required' : '';
                            $field_id = (int)$field['id'];
                            $field_type = $field['field_type'];
                            ?>

                            <?php if ($field_type === 'text'): ?>
                                <input
                                    type="text"
                                    name="answers[<?php echo $field_id; ?>]"
                                    <?php echo $required; ?>
                                    <?php echo !$can_submit ? 'disabled' : ''; ?>
                                >

                            <?php elseif ($field_type === 'textarea'): ?>
                                <textarea
                                    name="answers[<?php echo $field_id; ?>]"
                                    <?php echo $required; ?>
                                    <?php echo !$can_submit ? 'disabled' : ''; ?>
                                ></textarea>

                            <?php elseif ($field_type === 'radio'): ?>
                                <?php foreach ($field['options'] as $option): ?>
                                    <div class="option-item">
                                        <label>
                                            <input
                                                type="radio"
                                                name="answers[<?php echo $field_id; ?>]"
                                                value="<?php echo htmlspecialchars($option['option_text']); ?>"
                                                <?php echo $required; ?>
                                                <?php echo !$can_submit ? 'disabled' : ''; ?>
                                            >
                                            <?php echo htmlspecialchars($option['option_text']); ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>

                            <?php elseif ($field_type === 'checkbox'): ?>
                                <?php foreach ($field['options'] as $option): ?>
                                    <div class="option-item">
                                        <label>
                                            <input
                                                type="checkbox"
                                                name="answers[<?php echo $field_id; ?>][]"
                                                value="<?php echo htmlspecialchars($option['option_text']); ?>"
                                                <?php echo !$can_submit ? 'disabled' : ''; ?>
                                            >
                                            <?php echo htmlspecialchars($option['option_text']); ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                                <?php if ((int)$field['is_required'] === 1): ?>
                                    <small>This field is required.</small>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                    <?php if ($can_participate): ?>
                        <div class="action-row">

                            <?php if ($time_not_started): ?>
                                <div class="btn-locked">
                                    ⏳ Not yet open
                                </div>

                            <?php elseif ($time_ended): ?>
                                <div class="btn-locked">
                                    ⌛ Application closed
                                </div>

                            <?php elseif ($level_locked): ?>
                                <div class="btn-locked">
                                    🔒 Requires Level <?php echo $required_level; ?>
                                </div>

                            <?php elseif ($quest_closed_by_completion && !$admin_bypass_completion_lock): ?>
                                <div class="btn-applied">
                                    ✅ Closed
                                </div>

                            <?php elseif ($already_applied): ?>
                                <div class="btn-applied">
                                    ✔ Already Applied
                                </div>

                            <?php else: ?>
                                <button class="btn-submit" type="submit">
                                    Apply / Submit
                                </button>
                            <?php endif; ?>

                        </div>
                    <?php endif; ?>
                </form>
            <?php else: ?>
                <div class="notice">No custom fields</div>
                <?php if ($can_participate): ?>
                    <div class="action-row">

                        <?php if ($time_not_started): ?>
                            <div class="btn-locked">
                                ⏳ Not yet open
                            </div>

                        <?php elseif ($time_ended): ?>
                            <div class="btn-locked">
                                ⌛ Application closed
                            </div>

                        <?php elseif ($level_locked): ?>
                            <div class="btn-locked">
                                🔒 Requires Level <?php echo $required_level; ?>
                            </div>

                        <?php elseif ($quest_closed_by_completion && !$admin_bypass_completion_lock): ?>
                            <div class="btn-applied">
                                ✅ Closed
                            </div>

                        <?php elseif ($already_applied): ?>
                            <div class="btn-applied">
                                ✔ Already Applied
                            </div>

                        <?php else: ?>
                            <form action="accept_quest.php" method="POST">
                                <input type="hidden" name="quest_id" value="<?php echo (int)$quest['id']; ?>">
                                <button class="btn-submit" type="submit">
                                    Apply Quest
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>