<?php
require_once '../config.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

// ——— รับข้อมูล ———
$input   = json_decode(file_get_contents('php://input'), true);
$to      = trim($input['to']      ?? '');
$subject = trim($input['subject'] ?? '');
$body    = trim($input['body']    ?? '');
$fileId  = (int)($input['file_id'] ?? 0);

if (!$to || !$subject) {
    echo json_encode(['success' => false, 'error' => 'กรุณากรอกผู้รับและหัวข้ออีเมล']);
    exit;
}

// รองรับหลายที่อยู่ คั่นด้วย , หรือ ;
$recipients = array_values(array_filter(array_map('trim', preg_split('/[,;]+/', $to))));
if (empty($recipients)) {
    echo json_encode(['success' => false, 'error' => 'ไม่พบที่อยู่อีเมลผู้รับ']);
    exit;
}

// ตรวจสอบ format email
foreach ($recipients as $addr) {
    if (!filter_var($addr, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'error' => 'อีเมล "' . htmlspecialchars($addr) . '" ไม่ถูกต้อง']);
        exit;
    }
}

// ——— ดึง access_token จาก session ———
$accessToken = $_SESSION['access_token'] ?? null;
if (!$accessToken) {
    echo json_encode(['success' => false, 'error' => 'session_expired']);
    exit;
}

// ——— ดึงชื่อไฟล์เพื่อแนบใน body (ถ้ามี) ———
$fileLink = '';
if ($fileId) {
    $fst = $pdo->prepare("SELECT file_name FROM uploads WHERE id = :id");
    $fst->execute([':id' => $fileId]);
    $fname = $fst->fetchColumn();
    if ($fname) {
        $fileLink = '<p style="margin-top:16px;font-size:12px;color:#888;">📎 ไฟล์: <strong>' . htmlspecialchars($fname) . '</strong><br>'
                  . 'ดูไฟล์: <a href="http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/sitti/draw/view_file.php?id=' . $fileId . '">'
                  . 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/sitti/draw/view_file.php?id=' . $fileId . '</a></p>';
    }
}

$senderName = $_SESSION['display_name'] ?? 'Lolane Portal';

$htmlBody = '<!DOCTYPE html><html><body style="font-family:\'Kanit\',Arial,sans-serif;color:#333;max-width:600px;margin:0 auto">'
          . '<div style="background:#ff6b35;color:#fff;padding:16px 24px;border-radius:8px 8px 0 0">'
          . '<h2 style="margin:0">📧 ' . htmlspecialchars($subject) . '</h2></div>'
          . '<div style="background:#fff;border:1px solid #eee;border-radius:0 0 8px 8px;padding:24px">'
          . '<p style="margin:0 0 12px">ส่งโดย: <strong>' . htmlspecialchars($senderName) . '</strong></p>'
          . '<div style="white-space:pre-wrap;line-height:1.7">' . nl2br(htmlspecialchars($body)) . '</div>'
          . $fileLink
          . '</div></body></html>';

// ——— สร้าง toRecipients array ———
$toRecipients = array_map(function($addr) {
    return ['emailAddress' => ['address' => $addr]];
}, $recipients);

$payload = [
    'message' => [
        'subject' => $subject,
        'body'    => [
            'contentType' => 'HTML',
            'content'     => $htmlBody,
        ],
        'toRecipients' => $toRecipients,
    ],
    'saveToSentItems' => true,
];

// ——— เรียก Microsoft Graph API ———
$ch = curl_init('https://graph.microsoft.com/v1.0/me/sendMail');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_SSL_VERIFYPEER => false,
]);
$response   = curl_exec($ch);
$httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpStatus === 202) {
    // log activity
    require_once 'log_helper.php';
    logActivity($pdo, 'email', 'ส่งอีเมล "' . $subject . '" ไปยัง ' . implode(', ', $recipients), $fileId, null);
    echo json_encode(['success' => true]);
} elseif ($httpStatus === 401) {
    echo json_encode(['success' => false, 'error' => 'session_expired']);
} elseif ($httpStatus === 403) {
    echo json_encode(['success' => false, 'error' => 'no_permission']);
} else {
    $errMsg = '';
    $errData = json_decode($response, true);
    if (isset($errData['error']['message'])) $errMsg = $errData['error']['message'];
    echo json_encode(['success' => false, 'error' => 'ส่งไม่สำเร็จ (HTTP ' . $httpStatus . ')' . ($errMsg ? ': ' . $errMsg : '')]);
}
