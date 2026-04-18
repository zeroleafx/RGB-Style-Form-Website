<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
    <link href="css/styles.css" rel="stylesheet" />
</head>
<body class="auth-page">

<main class="auth-card">
    <a class="auth-back-link" href="index.php">← Back to Home</a>
    <h1 class="auth-title">Register</h1>

    <form id="registerForm" class="auth-form">
        <label class="auth-label">Group</label>
        <div class="auth-radio-group">
            <label class="auth-radio-item">
                <input type="radio" name="member_group" value="adventurer" checked>
                <span>冒險家</span>
            </label>

            <label class="auth-radio-item">
                <input type="radio" name="member_group" value="client">
                <span>委託人</span>
            </label>
        </div>

        <label class="auth-label" for="username">Username</label>
        <input class="auth-input" type="text" name="username" id="username" placeholder="帳號" required>

        <label class="auth-label" for="register_password">Password</label>
        <input class="auth-input" id="register_password" type="password" name="password" placeholder="密碼" required>

        <label class="auth-label" for="register_confirm_password">Confirm Password</label>
        <input class="auth-input" id="register_confirm_password" type="password" name="confirm_password" placeholder="確認密碼" required>

        <label class="auth-checkbox-row">
            <input type="checkbox" name="agree_terms" value="1" required>
            我同意條款
        </label>

        <button class="auth-btn" type="submit">註冊</button>
    </form>

    <div id="msg" class="auth-message"></div>
</main>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
$("#username").on("blur", function () {
    let username = $(this).val();

    $.ajax({
        url: "check_user.php",
        type: "POST",
        data: { username: username },
        success: function (res) {
            $("#msg").html(res);
        }
    });
});

$("#registerForm").on("submit", function (e) {
    e.preventDefault();

    console.log($(this).serialize());

    $.ajax({
        url: "register_action.php",
        type: "POST",
        data: $(this).serialize(),
        success: function (res) {
            $("#msg").html(res);
        }
    });
});
</script>
</body>
</html>