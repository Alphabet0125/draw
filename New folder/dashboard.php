<?php
session_start();

if (!isset($_SESSION['access_token'])) {
    header("Location: login.php");
    exit;
}

$user = $_SESSION['user'];
?>

<!DOCTYPE html>
<html>
<head>
<title>Dashboard</title>
<style>
body {
    font-family: Arial;
    background:#fffaf5;
    padding:40px;
}
.box {
    background:white;
    padding:30px;
    border-radius:10px;
    box-shadow:0 5px 15px rgba(0,0,0,0.1);
}
h2 { color:#ff7a00; }
.logout {
    margin-top:20px;
    display:inline-block;
    padding:10px 15px;
    background:#ff7a00;
    color:white;
    text-decoration:none;
    border-radius:6px;
}
</style>
</head>
<body>

<div class="box">
    <h2>Welcome</h2>
    <p>Name: <?php echo $user['displayName']; ?></p>
    <p>Email: <?php echo $user['userPrincipalName']; ?></p>

    <a href="logout.php" class="logout">Logout</a>
</div>

</body>
</html>