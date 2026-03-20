<?php
/**
 * Logout - รองรับ Multi-tenant (lolane.com + srcgroupth.com)
 */
require_once 'config.php';

// ★ ล้าง Session
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// ★ Redirect ไป Microsoft logout endpoint
// ใช้ "common" เพื่อรองรับทุก tenant (ทั้ง lolane.com และ srcgroupth.com)
$postLogoutRedirect = urlencode("https://service.lolane.com/logout.php");
$logoutUrl = "https://login.microsoftonline.com/common/oauth2/v2.0/logout"
           . "?post_logout_redirect_uri={$postLogoutRedirect}";

header("Location: {$logoutUrl}");
exit;