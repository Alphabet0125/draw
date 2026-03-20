<?php
session_start();

if (!isset($_SESSION['access_token']) || !isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$user = $_SESSION['user'];
$name = $user['displayName'] ?? 'Unknown';
$email = $user['userPrincipalName'] ?? 'No Email';
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Test Page</title>

<style>
:root {
    --primary: #ff7a00;
}

/* LIGHT */
body.light {
    --bg: #ffffff;
    --text: #333;
    --nav: #ffffff;
}

/* DARK */
body.dark {
    --bg: #1e1e1e;
    --text: #f5f5f5;
    --nav: #2a2a2a;
}

body {
    margin:0;
    font-family: 'Segoe UI', Arial;
    background: var(--bg);
    color: var(--text);
    transition:0.3s;
}

/* NAVBAR */
.navbar {
    background: var(--nav);
    padding:15px 25px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    box-shadow:0 2px 10px rgba(0,0,0,0.08);
}

.brand {
    font-weight:bold;
    color:var(--primary);
    font-size:18px;
}

.profile {
    text-align:right;
}

.profile-name {
    font-weight:600;
}

.profile-email {
    font-size:12px;
    opacity:0.7;
}

.theme-toggle {
    margin-left:15px;
    padding:6px 10px;
    border:none;
    border-radius:20px;
    cursor:pointer;
    background:var(--primary);
    color:white;
}

/* CONTENT */
.content {
    padding:40px;
}
</style>
</head>

<body class="light">

<div class="navbar">
    <div class="brand">My System</div>

    <div style="display:flex; align-items:center;">
        <div class="profile">
            <div class="profile-name"><?php echo htmlspecialchars($name); ?></div>
            <div class="profile-email"><?php echo htmlspecialchars($email); ?></div>
        </div>

        <button class="theme-toggle" onclick="toggleTheme()">🌙</button>
    </div>
</div>

<div class="content">
    <h2>Welcome to Test Page</h2>
    <p>คุณ Login ด้วย Microsoft Account สำเร็จแล้ว</p>
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