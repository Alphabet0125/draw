<?php
/**
 * CONFIG - รองรับ Multi-tenant (lolane.com + srcgroupth.com)
 */

// ===== Session Settings (24 ชั่วโมง) =====
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure', 0);
ini_set('session.cookie_lifetime', 86400);     // cookie หมดอายุใน 24 ชั่วโมง
ini_set('session.gc_maxlifetime', 86400);      // session หมดอายุใน 24 ชั่วโมง

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ================================
   MICROSOFT OAUTH CONFIG
   ★ เปลี่ยนจาก tenant เฉพาะ → organizations (รองรับหลาย tenant)
================================ */
$clientId     = "dccdca9b-5ca4-45dc-b9ce-1f3017052035";
$clientSecret = "ROS8Q~U-Mi4fgoi5e-jDKDw4Cdk-oN18naqNsbmY";
$redirectUri  = "http://localhost/draw/callback.php";

// ★★★ เปลี่ยนจาก tenant ID เฉพาะ → "organizations" ★★★
// "organizations" = อนุญาตทุก Azure AD tenant (แต่ไม่รวม personal Microsoft accounts)
// "common"        = อนุญาตทั้ง Azure AD + personal accounts
$authority = "organizations"; // ← หรือใช้ "common" ก็ได้

$authorizeUrl = "https://login.microsoftonline.com/{$authority}/oauth2/v2.0/authorize";
$tokenUrl     = "https://login.microsoftonline.com/{$authority}/oauth2/v2.0/token";

$scope = implode(' ', [
    'openid',
    'profile',
    'email',
    'offline_access',
    'https://graph.microsoft.com/User.Read',
    'https://graph.microsoft.com/Mail.Send',
]);

$d365BaseUrl = "https://lolane.crm5.dynamics.com";
$d365Scope   = "https://lolane.crm5.dynamics.com/.default";

// ★★★ โดเมนที่อนุญาต ★★★
$allowedDomains = [
    'lolane.com',
    'lolane.co.th',
    'srcgroupth.com',
];

/* ================================
   DATABASE CONFIG
================================ */
$servername = "localhost";
$username   = "root";
$password   = "System@min2024";
$dbname     = "draw1";

try {
    $pdo = new PDO(
        "mysql:host={$servername};dbname={$dbname};charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}

/* ================================
   HELPER FUNCTIONS
================================ */

function generateState(): string
{
    $state = bin2hex(random_bytes(32));

    setcookie('oauth2_state', $state, [
        'expires'  => time() + 600,
        'path'     => '/',
        'httponly'  => true,
        'samesite' => 'Lax',
        'secure'   => false,
    ]);

    $_SESSION['oauth2_state'] = $state;

    return $state;
}

function verifyState(string $state): bool
{
    if (isset($_COOKIE['oauth2_state'])) {
        $valid = hash_equals($_COOKIE['oauth2_state'], $state);
        if ($valid) {
            setcookie('oauth2_state', '', time() - 3600, '/');
            unset($_SESSION['oauth2_state']);
            return true;
        }
    }

    if (isset($_SESSION['oauth2_state'])) {
        $valid = hash_equals($_SESSION['oauth2_state'], $state);
        if ($valid) {
            unset($_SESSION['oauth2_state']);
            return true;
        }
    }

    return false;
}

// ★★★ เปลี่ยนจากเช็คแค่ lolane → เช็คจาก $allowedDomains ★★★
function isAllowedDomain(string $email): bool
{
    global $allowedDomains;

    if (empty($email) || strpos($email, '@') === false) {
        return false;
    }
    $domain = strtolower(substr(strrchr($email, "@"), 1));
    return in_array($domain, $allowedDomains);
}

// ★ เก็บ function เดิมไว้เพื่อ backward compatible
function isLolaneDomain(string $email): bool
{
    return isAllowedDomain($email);
}

// ★ Helper: ดึงโดเมนจาก email
function getEmailDomain(string $email): string
{
    if (empty($email) || strpos($email, '@') === false) {
        return '';
    }
    return strtolower(substr(strrchr($email, "@"), 1));
}

// ★ Helper: ดึงชื่อองค์กรจากโดเมน
function getOrganizationName(string $email): string
{
    $domain = getEmailDomain($email);
    $orgMap = [
        'lolane.com'      => 'Lolane',
        'lolane.co.th'    => 'Lolane',
        'srcgroupth.com'  => 'SRC Group',
    ];
    return $orgMap[$domain] ?? $domain;
}

function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']) && !empty($_SESSION['access_token']);
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}