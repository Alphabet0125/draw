<?php
/**
 * หน้าทดสอบ - ตรวจสอบว่าทุกอย่างพร้อมทำงาน
 * ⚠️ ลบไฟล์นี้ออกตอน production
 */
require_once 'config.php';

echo "<h1>🔍 Lolane OAuth Debug Test</h1>";
echo "<pre>";

// 1. ตรวจสอบ PHP Version
echo "1. PHP Version: " . phpversion() . "\n";
echo "   ✅ ต้อง >= 7.4\n\n";

// 2. ตรวจสอบ Extensions
$extensions = ['curl', 'json', 'pdo', 'pdo_mysql', 'openssl', 'mbstring'];
echo "2. PHP Extensions:\n";
foreach ($extensions as $ext) {
    $loaded = extension_loaded($ext);
    echo "   " . ($loaded ? '✅' : '❌') . " {$ext}\n";
}
echo "\n";

// 3. ตรวจสอบ Session
echo "3. Session Test:\n";
echo "   Session status: " . session_status() . " (2 = active)\n";
echo "   Session ID: " . session_id() . "\n";
echo "   Session save path: " . session_save_path() . "\n";
$_SESSION['test_value'] = 'hello_' . time();
echo "   Session writable: ✅ wrote '{$_SESSION['test_value']}'\n";
echo "   Cookie params: " . print_r(session_get_cookie_params(), true);
echo "\n";

// 4. ตรวจสอบ OAuth Config
echo "4. OAuth Config:\n";
echo "   Tenant ID: " . (empty($tenantId) || $tenantId === 'YOUR_LOLANE_TENANT_ID' ? '❌ NOT SET' : '✅ ' . substr($tenantId, 0, 8) . '...') . "\n";
echo "   Client ID: " . (empty($clientId) || $clientId === 'YOUR_CLIENT_ID' ? '❌ NOT SET' : '✅ ' . substr($clientId, 0, 8) . '...') . "\n";
echo "   Client Secret: " . (empty($clientSecret) || $clientSecret === 'YOUR_CLIENT_SECRET' ? '❌ NOT SET' : '✅ SET (hidden)') . "\n";
echo "   Redirect URI: " . $redirectUri . "\n";
echo "   Current URL: https://" . ($_SERVER['HTTP_HOST'] ?? 'unknown') . ($_SERVER['REQUEST_URI'] ?? '') . "\n";

// ตรวจสอบ redirect URI ตรงกับ host ปัจจุบันไหม
$currentHost = $_SERVER['HTTP_HOST'] ?? '';
if (strpos($redirectUri, $currentHost) === false) {
    echo "   ⚠️ WARNING: Redirect URI host ไม่ตรงกับ current host!\n";
} else {
    echo "   ✅ Redirect URI host matches\n";
}
echo "\n";

// 5. ตรวจสอบ Database
echo "5. Database Connection:\n";
try {
    $testStmt = $pdo->query("SELECT 1");
    echo "   ✅ Connected successfully\n";

    // ตรวจสอบตาราง users
    $tables = $pdo->query("SHOW TABLES LIKE 'users'")->fetchAll();
    if (count($tables) > 0) {
        echo "   ✅ Table 'users' exists\n";
        $cols = $pdo->query("DESCRIBE users")->fetchAll();
        echo "   Columns: " . implode(', ', array_column($cols, 'Field')) . "\n";
    } else {
        echo "   ❌ Table 'users' NOT FOUND - กรุณารัน db_setup.sql\n";
    }

    $tables2 = $pdo->query("SHOW TABLES LIKE 'login_logs'")->fetchAll();
    echo "   " . (count($tables2) > 0 ? '✅' : '❌') . " Table 'login_logs'\n";
} catch (PDOException $e) {
    echo "   ❌ Connection failed: " . $e->getMessage() . "\n";
}
echo "\n";

// 6. ตรวจสอบ CURL ว่าเชื่อมต่อ Microsoft ได้
echo "6. Microsoft Connectivity:\n";
$ch = curl_init("https://login.microsoftonline.com/{$tenantId}/v2.0/.well-known/openid-configuration");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$msResponse = curl_exec($ch);
$msHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$msError = curl_error($ch);
curl_close($ch);

if ($msHttpCode === 200) {
    echo "   ✅ Can reach Microsoft login endpoint\n";
} else {
    echo "   ❌ Cannot reach Microsoft (HTTP {$msHttpCode})\n";
    echo "   CURL Error: {$msError}\n";
    echo "   สาเหตุ: เซิร์ฟเวอร์ block outbound HTTPS หรือ tenant ID ผิด\n";
}
echo "\n";

// 7. ตรวจสอบ HTTPS
echo "7. HTTPS:\n";
$isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
echo "   " . ($isHttps ? '✅ HTTPS active' : '⚠️ NOT HTTPS - OAuth ต้องใช้ HTTPS!') . "\n";
echo "\n";

// 8. ตรวจสอบ current session data
echo "8. Current Session Data:\n";
echo print_r($_SESSION, true);

echo "</pre>";

echo "<hr>";
echo "<h3>🔗 Quick Links</h3>";
echo "<ul>";
echo "<li><a href='login.php'>→ Go to Login Page</a></li>";
echo "<li><a href='login.php?action=login'>→ Start OAuth Flow</a></li>";
echo "<li><a href='dashboard.php'>→ Go to Dashboard (requires login)</a></li>";
echo "</ul>";