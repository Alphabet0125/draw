<?php
/**
 * ดึง thumbnail ของไฟล์ (ขนาดเล็ก สำหรับ preview)
 * ใช้: get_thumbnail.php?id=123
 */
require_once '../config.php';
requireLogin();

$fileId = (int)($_GET['id'] ?? 0);
$userId = $_SESSION['user_id'];

if (!$fileId) {
    http_response_code(404);
    exit;
}

// ★ ไม่จำกัด user_id เพื่อให้ทุก user เห็น thumbnail ของไฟล์ทั้งหมดได้
$stmt = $pdo->prepare(
    "SELECT file_mime, file_type, file_path, file_data FROM uploads WHERE id = :id"
);
$stmt->execute([':id' => $fileId]);
$file = $stmt->fetch();

if (!$file || (empty($file['file_data']) && empty($file['file_path']))) {
    http_response_code(404);
    exit;
}

// ★ สำหรับรูปภาพ: ส่ง binary กลับไปเลย
// ★ สำหรับ PDF: ส่ง placeholder icon
if ($file['file_type'] === 'pdf') {
    // ส่ง SVG icon ของ PDF
    header('Content-Type: image/svg+xml');
    echo '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120" viewBox="0 0 120 120">
        <rect width="120" height="120" rx="12" fill="#ffebee"/>
        <text x="60" y="55" text-anchor="middle" font-size="40">📄</text>
        <text x="60" y="82" text-anchor="middle" font-family="sans-serif" font-size="16" font-weight="bold" fill="#c62828">PDF</text>
    </svg>';
    exit;
}

// ★ รูปภาพ: serve จาก disk หรือ DB
$mime = $file['file_mime'] ?: 'image/jpeg';
header('Content-Type: ' . $mime);
header('Cache-Control: public, max-age=86400');

if (!empty($file['file_path'])) {
    $fullPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'fileuploads' . DIRECTORY_SEPARATOR . basename($file['file_path']);
    if (!file_exists($fullPath)) { http_response_code(404); exit; }
    header('Content-Length: ' . filesize($fullPath));
    readfile($fullPath);
} else {
    $binary = base64_decode($file['file_data']);
    header('Content-Length: ' . strlen($binary));
    echo $binary;
}
exit;