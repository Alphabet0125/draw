<?php
/**
 * OAuth Callback
 * ★ รองรับ Multi-tenant (lolane.com + srcgroupth.com) ★
 */

$debugMode = true;
$logFile   = __DIR__ . '/debug_oauth.log';

function debugLog(string $msg): void
{
    global $debugMode, $logFile;
    if ($debugMode) {
        file_put_contents($logFile, date('[Y-m-d H:i:s] ') . $msg . "\n", FILE_APPEND);
    }
}

debugLog("========== CALLBACK START ==========");
debugLog("GET: " . json_encode($_GET));
debugLog("COOKIE: " . json_encode($_COOKIE));

require_once 'config.php';

debugLog("Session ID: " . session_id());
debugLog("Session data: " . json_encode($_SESSION));

// ===== 1. Error =====
if (isset($_GET['error'])) {
    die("❌ Microsoft Error: " . htmlspecialchars($_GET['error_description'] ?? $_GET['error']));
}

// ===== 2. No code =====
if (!isset($_GET['code'])) {
    die("❌ No authorization code");
}

// ===== 3. ★ State check (Cookie + Session + Fallback) ★ =====
if (isset($_GET['state'])) {

    $urlState = $_GET['state'];
    $verified = false;

    $verified = verifyState($urlState);

    if (!$verified) {
        debugLog("State mismatch! URL: {$urlState}");
        debugLog("Cookie state: " . ($_COOKIE['oauth2_state'] ?? 'NOT SET'));
        debugLog("Session state: " . ($_SESSION['oauth2_state'] ?? 'NOT SET'));

        if ($debugMode) {
            echo "<h2>⚠️ State Mismatch Debug</h2>";
            echo "<pre>";
            echo "State from URL:     " . htmlspecialchars($urlState) . "\n";
            echo "State from Cookie:  " . htmlspecialchars($_COOKIE['oauth2_state'] ?? 'NOT SET') . "\n";
            echo "State from Session: " . htmlspecialchars($_SESSION['oauth2_state'] ?? 'NOT SET') . "\n";
            echo "Session ID:         " . session_id() . "\n\n";
            echo "Cookie SameSite:    " . ini_get('session.cookie_samesite') . "\n";
            echo "Cookie Secure:      " . ini_get('session.cookie_secure') . "\n";
            echo "</pre>";

            echo "<p><strong>ต้องการข้าม state check เพื่อทดสอบ?</strong></p>";
            echo "<a href='callback.php?code=" . urlencode($_GET['code'])
               . "&skip_state=1' "
               . "style='background:#0078d4;color:white;padding:10px 20px;"
               . "border-radius:8px;text-decoration:none;'>"
               . "✅ ข้าม State Check แล้วดำเนินการต่อ</a>";
            die();
        }

        header('Location: login.php?error=invalid_state');
        exit;
    }
} else {
    if (!isset($_GET['skip_state'])) {
        die("❌ No state parameter");
    }
}

debugLog("State verified OK (or skipped)");

// ===== 4. Token Exchange (SSL fix) =====
$postData = [
    'client_id'     => $clientId,
    'client_secret' => $clientSecret,
    'code'          => $_GET['code'],
    'redirect_uri'  => $redirectUri,
    'grant_type'    => 'authorization_code',
    'scope'         => $scope,
];

$ch = curl_init($tokenUrl);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query($postData),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
]);

$tokenResponse = curl_exec($ch);
$tokenHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError     = curl_error($ch);
curl_close($ch);

debugLog("Token HTTP: {$tokenHttpCode}");

if ($curlError) {
    die("❌ CURL Error: " . htmlspecialchars($curlError));
}

if ($tokenHttpCode !== 200) {
    $errorData = json_decode($tokenResponse, true);
    echo "<h2>❌ Token Failed (HTTP {$tokenHttpCode})</h2><pre>";
    echo "Error: " . htmlspecialchars($errorData['error'] ?? 'unknown') . "\n";
    echo "Detail: " . htmlspecialchars($errorData['error_description'] ?? $tokenResponse) . "\n";
    echo "</pre>";
    die();
}

$tokenData    = json_decode($tokenResponse, true);
$accessToken  = $tokenData['access_token'];
$refreshToken = $tokenData['refresh_token'] ?? null;
$expiresIn    = $tokenData['expires_in'] ?? 3600;

debugLog("Token OK!");

// ===== 5. ดึง User Profile (SSL fix) =====
$graphUrl = 'https://graph.microsoft.com/v1.0/me?' . http_build_query([
    '$select' => 'id,displayName,givenName,surname,mail,userPrincipalName,'
               . 'jobTitle,department,officeLocation,mobilePhone,businessPhones'
]);

$ch = curl_init($graphUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        "Authorization: Bearer {$accessToken}",
        'Content-Type: application/json',
    ],
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
]);

$graphResponse = curl_exec($ch);
$graphHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($graphHttpCode !== 200) {
    die("❌ Graph API Error (HTTP {$graphHttpCode}): " . htmlspecialchars($graphResponse));
}

$userProfile = json_decode($graphResponse, true);
$email = $userProfile['mail'] ?? $userProfile['userPrincipalName'] ?? '';

debugLog("User: {$email} ({$userProfile['displayName']})");

// ===== 6. ★★★ ตรวจโดเมน — ร��งรับหลายโดเมน ★★★ =====
if (!isAllowedDomain($email)) {
    $domain = getEmailDomain($email);
    $allowed = implode(', ', $allowedDomains);

    debugLog("REJECTED: {$email} (domain: {$domain})");

    echo "<!DOCTYPE html><html><head><meta charset='utf-8'>"
       . "<style>body{font-family:sans-serif;display:flex;justify-content:center;align-items:center;min-height:100vh;background:#1a1208;color:#ffecd2;}"
       . ".box{background:#2a1f10;border:1px solid #3d2e18;border-radius:16px;padding:40px;max-width:500px;text-align:center;}"
       . "h2{color:#e74c3c;}p{color:#c4a882;line-height:1.6;}.domain{color:#ff6b35;font-weight:bold;}"
       . "a{display:inline-block;margin-top:16px;background:#ff6b35;color:#fff;padding:10px 24px;border-radius:10px;text-decoration:none;}"
       . "</style></head><body><div class='box'>"
       . "<h2>❌ ไม่มีสิทธิ์เข้าใช้งาน</h2>"
       . "<p>อีเมล <strong>" . htmlspecialchars($email) . "</strong> ไม่ได้รับอนุญาต</p>"
       . "<p>ระบบอนุญาตเฉพาะโดเมน:<br><span class='domain'>" . htmlspecialchars($allowed) . "</span></p>"
       . "<a href='login.php'>← กลับหน้า Login</a>"
       . "</div></body></html>";
    exit;
}

// ★ ดึงข้อมูลองค์กรจากโดเมน
$userDomain = getEmailDomain($email);
$userOrg    = getOrganizationName($email);

debugLog("Domain: {$userDomain} | Org: {$userOrg}");

// ===== 7. บันทึก DB =====
try {
    $microsoftId = $userProfile['id'];
    $displayName = $userProfile['displayName'] ?? '';
    $firstName   = $userProfile['givenName'] ?? '';
    $lastName    = $userProfile['surname'] ?? '';
    $jobTitle    = $userProfile['jobTitle'] ?? '';
    $department  = $userProfile['department'] ?? '';
    $office      = $userProfile['officeLocation'] ?? '';
    $mobile      = $userProfile['mobilePhone'] ?? '';
    $phones      = json_encode($userProfile['businessPhones'] ?? []);
    $expiresAt   = date('Y-m-d H:i:s', time() + $expiresIn);

    $sql = "INSERT INTO users
                (microsoft_id, email, display_name, first_name, last_name,
                 job_title, department, office_location, mobile_phone,
                 business_phones, access_token, refresh_token, token_expires_at, last_login)
            VALUES
                (:microsoft_id, :email, :display_name, :first_name, :last_name,
                 :job_title, :department, :office_location, :mobile_phone,
                 :business_phones, :access_token, :refresh_token, :token_expires_at, NOW())
            ON DUPLICATE KEY UPDATE
                email = VALUES(email),
                display_name = VALUES(display_name),
                first_name = VALUES(first_name),
                last_name = VALUES(last_name),
                job_title = VALUES(job_title),
                department = VALUES(department),
                office_location = VALUES(office_location),
                mobile_phone = VALUES(mobile_phone),
                business_phones = VALUES(business_phones),
                access_token = VALUES(access_token),
                refresh_token = COALESCE(VALUES(refresh_token), refresh_token),
                token_expires_at = VALUES(token_expires_at),
                last_login = NOW()";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':microsoft_id'    => $microsoftId,
        ':email'           => $email,
        ':display_name'    => $displayName,
        ':first_name'      => $firstName,
        ':last_name'       => $lastName,
        ':job_title'       => $jobTitle,
        ':department'      => $department,
        ':office_location' => $office,
        ':mobile_phone'    => $mobile,
        ':business_phones' => $phones,
        ':access_token'    => $accessToken,
        ':refresh_token'   => $refreshToken,
        ':token_expires_at'=> $expiresAt,
    ]);

    $userId = $pdo->lastInsertId();
    if (!$userId) {
        $stmt2 = $pdo->prepare("SELECT id FROM users WHERE microsoft_id = :mid");
        $stmt2->execute([':mid' => $microsoftId]);
        $userId = $stmt2->fetchColumn();
    }

    debugLog("DB OK. User ID: {$userId}");

} catch (PDOException $e) {
    die("❌ Database Error: " . htmlspecialchars($e->getMessage()));
}

// ===== 8. Login Log =====
try {
    $pdo->prepare("INSERT INTO login_logs (user_id, ip_address, user_agent) VALUES (?, ?, ?)")
        ->execute([$userId, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
} catch (PDOException $e) { /* skip */ }

// ===== 8.5 ★ ดึงรูปโปรไฟล์จาก Microsoft 365 ★ =====
$avatarBase64 = '';
try {
    $photoUrl = 'https://graph.microsoft.com/v1.0/me/photo/$value';
    $ch = curl_init($photoUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$accessToken}"],
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ]);
    $photoData     = curl_exec($ch);
    $photoHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($photoHttpCode === 200 && !empty($photoData)) {
        $avatarBase64 = base64_encode($photoData);
        // บันทึกลง DB
        $stmtAvatar = $pdo->prepare("UPDATE users SET avatar = :avatar WHERE id = :id");
        $stmtAvatar->execute([':avatar' => $avatarBase64, ':id' => $userId]);
        debugLog("Avatar fetched from Microsoft 365 (" . strlen($photoData) . " bytes)");
    } else {
        debugLog("No Microsoft 365 avatar (HTTP {$photoHttpCode}) — skip");
    }
} catch (Exception $e) {
    debugLog("Avatar fetch error: " . $e->getMessage());
}

// ===== 9. ★ สร้าง Session ★ =====
session_regenerate_id(true);
$_SESSION['user_id']       = (int) $userId;
$_SESSION['microsoft_id']  = $microsoftId;
$_SESSION['email']         = $email;
$_SESSION['display_name']  = $displayName;
$_SESSION['access_token']  = $accessToken;
$_SESSION['refresh_token'] = $refreshToken;
$_SESSION['token_expires'] = time() + 86400;  // ★ session 24 ชั่วโมง
$_SESSION['department']    = $department;        // ★ เพิ่ม
$_SESSION['domain']        = $userDomain;       // ★ เพิ่ม
$_SESSION['organization']  = $userOrg;          // ★ เพิ่ม
if (!empty($avatarBase64)) {
    $_SESSION['avatar_cache'] = $avatarBase64;  // ★ cache avatar จาก Microsoft 365
}

debugLog("Session created. Domain: {$userDomain} | Org: {$userOrg}");

// ===== 10. ไป Dashboard! =====
header('Location: index.php');
exit;