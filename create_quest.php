<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

if (($_SESSION['role'] ?? '') !== 'admin' && ($_SESSION['member_group'] ?? '') !== 'client') {
    die("你沒有權限發委託");
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Quest</title>
    <link href="css/styles.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
</head>
<body class="pixel-purple-body">
    <div class="pixel-container builder-box pixel-builder-box">
        <h1 class="pixel-title">Create Quest</h1>

        <form action="create_quest_action.php" method="POST" id="questForm">
            <h2 class="pixel-subtitle">Information</h2>

            <label class="pixel-label">Title</label>
            <input type="text" name="title" class="pixel-input" required>

            <label class="pixel-label">Context</label>
            <textarea name="description" class="pixel-textarea" required></textarea>

            <label class="pixel-label">Level required</label>
            <input type="number" name="level_required" class="pixel-input" min="1" max="99" value="1" required>

            <label class="pixel-label">Difficulty</label>
            <input type="number" name="difficulty" class="pixel-input" min="1" max="10" value="1" required>

            <label class="pixel-label">Reward</label>
            <input type="number" name="reward" class="pixel-input" min="0" value="0" required>

            <label class="pixel-label">Start Time</label>
            <input type="datetime-local" name="start_date" class="pixel-input">

            <label class="pixel-label">End Time</label>
            <input type="datetime-local" name="end_date" class="pixel-input">

            <br><br>

            <label class="pixel-label">
                <input type="checkbox" name="is_repeatable" value="1">
                Repeatable
            </label>

            <hr class="pixel-divider">

            <h2 class="pixel-subtitle">Custom Field</h2>
            <div id="questionContainer"></div>

            <button type="button" id="addQuestionBtn" class="pixel-btn">+ Add Options</button>
            <br><br>

            <button type="submit" class="pixel-btn">Publish</button>
        </form>
    </div>

    <script>
        let questionIndex = 0;

        document.getElementById("addQuestionBtn").addEventListener("click", function () {
            addQuestionBlock();
        });

        function addQuestionBlock() {
            const container = document.getElementById("questionContainer");

            const block = document.createElement("div");
            block.className = "question-block pixel-field-box";
            block.setAttribute("data-index", questionIndex);

            block.innerHTML = `
                <label class="pixel-label">Question</label>
                <input type="text" name="questions[${questionIndex}][question_text]" class="pixel-input" required>

                <label class="pixel-label">Type</label>
                <select name="questions[${questionIndex}][field_type]" class="pixel-select" onchange="toggleOptions(${questionIndex}, this.value)">
                    <option value="text">Short Answer</option>
                    <option value="textarea">Paragraph</option>
                    <option value="radio">Multiple Choice</option>
                    <option value="checkbox">Checkboxes</option>
                </select>

                <label class="pixel-checkbox-label">
                    <input type="checkbox" name="questions[${questionIndex}][is_required]" class="pixel-checkbox" value="1" checked>
                    Required
                </label>

                <div id="options-box-${questionIndex}" class="pixel-options-box create-options-box" style="display:none;">
                    <label class="pixel-label">Options</label>
                    <div class="options-list" id="options-list-${questionIndex}"></div>
                    <button type="button" class="pixel-small-btn" onclick="addOption(${questionIndex})">+ Add Options</button>
                </div>

                <button type="button" class="pixel-small-btn remove-question-btn" onclick="removeQuestion(this)">Delete Field</button>
            `;

            container.appendChild(block);
            questionIndex++;
        }

        function toggleOptions(index, type) {
            const box = document.getElementById(`options-box-${index}`);
            const list = document.getElementById(`options-list-${index}`);

            if (type === "radio" || type === "checkbox") {
                box.style.display = "block";

                if (list.children.length === 0) {
                    addOption(index);
                    addOption(index);
                }
            } else {
                box.style.display = "none";
                list.innerHTML = "";
            }
        }

        function addOption(index) {
            const list = document.getElementById(`options-list-${index}`);

            const div = document.createElement("div");
            div.className = "option-input create-option-row";
            div.innerHTML = `
                <input type="text" name="questions[${index}][options][]" class="pixel-input create-option-input" placeholder="Context" required>
                <button type="button" class="pixel-small-btn remove-option-btn" onclick="this.parentElement.remove()">Delete</button>
            `;
            list.appendChild(div);
        }

        function removeQuestion(button) {
            button.parentElement.remove();
        }
    </script>
</body>
</html>