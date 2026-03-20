<?php
/**
 * serve_file.php — ส่งไฟล์ให้ browser
 * รองรับทั้งไฟล์ที่เก็บใน disk (file_path) และไฟล์เก่าที่เก็บใน DB (file_data)
 * รองรับ Range requests สำหรับ PDF streaming
 */
ini_set('display_errors', '0');
error_reporting(0);
ob_start(); // ป้องกัน output ขยะ (warnings/BOM) ก่อน binary response
require_once '../config.php';
requireLogin();

$fileId = (int)($_GET['id'] ?? 0);
if (!$fileId) { http_response_code(404); exit; }

// ★ Auto-migrate: เพิ่ม column file_path ถ้ายังไม่มีใน DB (schema เก่า)
try {
    $pdo->exec("ALTER TABLE uploads ADD COLUMN file_path VARCHAR(255) NOT NULL DEFAULT '' AFTER file_size");
} catch (PDOException $alterEx) {
    // column มีแล้ว หรือ DB version ไม่รองรับ — ไม่ต้องทำอะไร
}

// ★ สร้างโฟลเดอร์ fileuploads ถ้ายังไม่มี
$uploadDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'fileuploads' . DIRECTORY_SEPARATOR;
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0755, true);
}

try {
    $stmt = $pdo->prepare(
        "SELECT id, file_name, file_mime, file_type, file_size, file_path, file_data
         FROM uploads WHERE id = :id"
    );
    $stmt->execute([':id' => $fileId]);
    $file = $stmt->fetch();
} catch (PDOException $e) {
    // fallback: query โดยไม่มี file_path (สำหรับ DB schema เก่ามาก)
    try {
        $stmt = $pdo->prepare(
            "SELECT id, file_name, file_mime, file_type, file_size, file_data
             FROM uploads WHERE id = :id"
        );
        $stmt->execute([':id' => $fileId]);
        $row = $stmt->fetch();
        if ($row) { $row['file_path'] = ''; $file = $row; }
        else { $file = null; }
    } catch (PDOException $e2) {
        ob_end_clean();
        http_response_code(500);
        exit;
    }
}

if (!$file) { http_response_code(404); exit; }

$mime = $file['file_mime'] ?: 'application/octet-stream';
$encName = rawurlencode($file['file_name']);
$forceDownload = isset($_GET['dl']);

ob_end_clean(); // ทิ้ง output ที่อาจค้างอยู่ใน buffer ก่อนส่ง binary
header('Content-Type: ' . $mime);
$disposition = $forceDownload ? 'attachment' : 'inline';
header('Content-Disposition: ' . $disposition . '; filename="' . addslashes($file['file_name']) . '"; filename*=UTF-8\'\'' . $encName);
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');

// ★ ไฟล์ใหม่: อ่านจาก disk
if (!empty($file['file_path'])) {
    $fullPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'fileuploads' . DIRECTORY_SEPARATOR . basename($file['file_path']);
    if (!file_exists($fullPath)) { http_response_code(404); exit; }

    $fileSize = filesize($fullPath);
    header('Accept-Ranges: bytes');

    // Support Range requests (PDF streaming / seek)
    if (isset($_SERVER['HTTP_RANGE'])) {
        $range = $_SERVER['HTTP_RANGE'];
        if (preg_match('/bytes=(\d*)-(\d*)/', $range, $m)) {
            $start  = $m[1] !== '' ? (int)$m[1] : 0;
            $end    = $m[2] !== '' ? (int)$m[2] : $fileSize - 1;
            $end    = min($end, $fileSize - 1);
            $length = $end - $start + 1;
            http_response_code(206);
            header("Content-Range: bytes $start-$end/$fileSize");
            header("Content-Length: $length");
            $fp = fopen($fullPath, 'rb');
            fseek($fp, $start);
            $remaining = $length;
            while ($remaining > 0 && !feof($fp)) {
                $chunk = fread($fp, min(65536, $remaining));
                echo $chunk;
                $remaining -= strlen($chunk);
                ob_flush(); flush();
            }
            fclose($fp);
        } else {
            http_response_code(416);
        }
        exit;
    }

    header('Content-Length: ' . $fileSize);
    readfile($fullPath);
    exit;
}

// ★ ไฟล์เก่า: อ่านจาก DB (base64)
if (!empty($file['file_data'])) {
    $binary = base64_decode($file['file_data']);
    header('Content-Length: ' . strlen($binary));
    echo $binary;
    exit;
}

http_response_code(404);
exit;
