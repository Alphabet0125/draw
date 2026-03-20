<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Microsoft Login</title>

<style>
:root {
    --primary: #ff7a00;
    --primary-dark: #e66900;
}

/* LIGHT THEME */
body.light {
    --bg: #ffffff;
    --bg-soft: #fff3e6;
    --text: #333;
    --box: #ffffff;
}

/* DARK THEME */
body.dark {
    --bg: #1e1e1e;
    --bg-soft: #2a2a2a;
    --text: #f5f5f5;
    --box: #2d2d2d;
}

body {
    margin:0;
    font-family: 'Segoe UI', Arial, sans-serif;
    background: linear-gradient(135deg, var(--bg), var(--bg-soft));
    color: var(--text);
    display:flex;
    justify-content:center;
    align-items:center;
    height:100vh;
    transition: 0.3s;
}

.login-container {
    background: var(--box);
    padding:40px;
    border-radius:16px;
    box-shadow:0 15px 35px rgba(0,0,0,0.15);
    width:360px;
    text-align:center;
    position:relative;
}

.logo {
    font-size:22px;
    font-weight:bold;
    color:var(--primary);
    margin-bottom:25px;
}

.btn-login {
    width:100%;
    padding:12px;
    border:none;
    border-radius:8px;
    font-size:16px;
    cursor:pointer;
    background:var(--primary);
    color:white;
    transition:0.3s;
}

.btn-login:hover {
    background:var(--primary-dark);
}

.theme-toggle {
    position:absolute;
    top:15px;
    right:15px;
    cursor:pointer;
    font-size:14px;
    padding:6px 10px;
    border-radius:20px;
    background:var(--primary);
    color:white;
    border:none;
}

.footer {
    margin-top:20px;
    font-size:12px;
    opacity:0.7;
}
</style>
</head>

<body class="light">

<div class="login-container">
    <button class="theme-toggle" onclick="toggleTheme()">🌙</button>

    <div class="logo">Microsoft Secure Login</div>

    <a href="<?php echo $authorizeUrl; ?>?
        client_id=<?php echo $clientId; ?>
        &response_type=code
        &redirect_uri=<?php echo urlencode($redirectUri); ?>
        &response_mode=query
        &scope=<?php echo urlencode($scope); ?>
        &state=secure123">

        <button class="btn-login">
            Sign in with Microsoft
        </button>
    </a>

    <div class="footer">
        Login ด้วย Microsoft Account เท่านั้น
    </div>
</div>

<script>
function toggleTheme() {
    const body = document.body;
    body.classList.toggle("dark");
    body.classList.toggle("light");

    localStorage.setItem("theme", body.classList.contains("dark") ? "dark" : "light");
}

window.onload = function() {
    const savedTheme = localStorage.getItem("theme");
    if (savedTheme) {
        document.body.className = savedTheme;
    }
}
</script>

</body>
</html>