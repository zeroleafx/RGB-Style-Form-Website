<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
    <link href="css/styles.css" rel="stylesheet" />
</head>
<body class="auth-page">

<main class="auth-card">
    <a class="auth-back-link" href="index.php">← Back to Home</a>
    <h1 class="auth-title">Login</h1>

    <form id="loginForm" class="auth-form">
        <label class="auth-label" for="login_username">Username</label>
        <input id="login_username" name="username" class="auth-input" placeholder="帳號" required>

        <label class="auth-label" for="login_password">Password</label>
        <input id="login_password" name="password" class="auth-input" type="password" placeholder="密碼" required>

        <button type="submit" class="auth-btn">登入</button>
    </form>

    <div id="message" class="auth-message"></div>
</main>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="js/Login.js"></script>

</body>
</html>