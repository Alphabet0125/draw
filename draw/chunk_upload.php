<?php
require_once '../config.php';
requireLogin();
require_once 'log_helper.php';

ini_set('display_errors', '0');
header('Content-Type: application/json; charset=UTF-8');

register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=UTF-8');
        }
        echo json_encode([
            'success' => false,
            'message' => 'เกิดข้อผิดพลาดภายในเซิร์ฟเวอร์ขณะอัปโหลดไฟล์'
        ], JSON_UNESCAPED_UNICODE);
    }
});

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

$maxFileSize  = 500 * 1024 * 1024;
$allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$allowedExts  = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp'];

function jsonFail(string $message, int $status = 400): void
{
    http_response_code($status);
    echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function removeDirRecursive(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $items = scandir($dir);
    if ($items === false) {
        return;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            removeDirRecursive($path);
        } elseif (is_file($path)) {
            @unlink($path);
        }
    }
    @rmdir($dir);
}

function cleanOldChunkDirs(string $baseDir, int $maxAgeSeconds = 86400): void
{
    if (!is_dir($baseDir)) {
        return;
    }
    $now = time();
    $entries = scandir($baseDir);
    if ($entries === false) {
        return;
    }
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $path = $baseDir . DIRECTORY_SEPARATOR . $entry;
        if (!is_dir($path)) {
            continue;
        }
        $mtime = @filemtime($path);
        if ($mtime !== false && ($now - $mtime) > $maxAgeSeconds) {
            removeDirRecursive($path);
        }
    }
}

$action = $_POST['action'] ?? '';
if ($action === '') {
    jsonFail('ไม่พบ action');
}

$rawUploadId = (string)($_POST['upload_id'] ?? '');
if ($rawUploadId === '' || !preg_match('/^[a-zA-Z0-9_-]{8,80}$/', $rawUploadId)) {
    jsonFail('รูปแบบ upload_id ไม่ถูกต้อง');
}

$baseChunksRoot = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'sitti_chunks';
$userChunkRoot  = $baseChunksRoot . DIRECTORY_SEPARATOR . 'u' . $userId;

// ★ Auto-migrate: เพิ่ม column file_path ถ้ายังไม่มีใน DB
try {
    $pdo->exec("ALTER TABLE uploads ADD COLUMN file_path VARCHAR(255) NOT NULL DEFAULT '' AFTER file_size");
} catch (PDOException $alterEx) { /* column มีแล้ว */ }
$chunkDir       = $userChunkRoot . DIRECTORY_SEPARATOR . $rawUploadId;

if (!is_dir($chunkDir) && !@mkdir($chunkDir, 0755, true) && !is_dir($chunkDir)) {
    jsonFail('ไม่สามารถสร้างพื้นที่จัดเก็บชั่วคราวได้', 500);
}

cleanOldChunkDirs($userChunkRoot);

if ($action === 'upload_chunk') {
    $chunkIndex = isset($_POST['chunk_index']) ? (int)$_POST['chunk_index'] : -1;
    $totalChunks = isset($_POST['total_chunks']) ? (int)$_POST['total_chunks'] : 0;

    if ($chunkIndex < 0 || $totalChunks <= 0 || $chunkIndex >= $totalChunks) {
        jsonFail('ข้อมูล chunk ไม่ถูกต้อง');
    }
    if (!isset($_FILES['chunk']) || $_FILES['chunk']['error'] !== UPLOAD_ERR_OK) {
        jsonFail('รับข้อมูล chunk ไม่สำเร็จ');
    }

    $partPath = $chunkDir . DIRECTORY_SEPARATOR . sprintf('%06d.part', $chunkIndex);
    if (!move_uploaded_file($_FILES['chunk']['tmp_name'], $partPath)) {
        jsonFail('บันทึก chunk ไม่สำเร็จ', 500);
    }

    echo json_encode(['success' => true, 'chunk_index' => $chunkIndex], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'finalize_upload') {
    $totalChunks = isset($_POST['total_chunks']) ? (int)$_POST['total_chunks'] : 0;
    $fileName = trim((string)($_POST['file_name'] ?? ''));
    $fileMime = trim((string)($_POST['file_mime'] ?? ''));
    $fileSize = isset($_POST['file_size']) ? (int)$_POST['file_size'] : 0;
    $description = trim((string)($_POST['description'] ?? ''));

    if ($totalChunks <= 0) {
        jsonFail('จำนวน chunk ไม่ถูกต้อง');
    }
    if ($fileName === '' || $fileSize <= 0) {
        jsonFail('ข้อมูลไฟล์ไม่ครบถ้วน');
    }
    if ($fileSize > $maxFileSize) {
        jsonFail('ไฟล์มีขนาดเกิน 500MB');
    }

    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if (!in_array($fileMime, $allowedTypes, true)) {
        jsonFail('ประเภทไฟล์ไม่อนุญาต');
    }
    if (!in_array($ext, $allowedExts, true)) {
        jsonFail('นามสกุลไฟล์ไม่อนุญาต');
    }

    for ($i = 0; $i < $totalChunks; $i++) {
        $partPath = $chunkDir . DIRECTORY_SEPARATOR . sprintf('%06d.part', $i);
        if (!is_file($partPath)) {
            jsonFail('ข้อมูลไฟล์ไม่ครบ: chunk ที่ ' . ($i + 1) . ' หายไป');
        }
    }

    $mergedPath = $chunkDir . DIRECTORY_SEPARATOR . 'merged.bin';
    $out = @fopen($mergedPath, 'wb');
    if ($out === false) {
        jsonFail('ไม่สามารถประกอบไฟล์ได้', 500);
    }

    set_time_limit(600);
    for ($i = 0; $i < $totalChunks; $i++) {
        $partPath = $chunkDir . DIRECTORY_SEPARATOR . sprintf('%06d.part', $i);
        $in = @fopen($partPath, 'rb');
        if ($in === false) {
            fclose($out);
            jsonFail('อ่าน chunk ไม่สำเร็จ', 500);
        }
        stream_copy_to_stream($in, $out);
        fclose($in);
    }
    fclose($out);

    $actualSize = @filesize($mergedPath);
    if ($actualSize === false || $actualSize !== $fileSize) {
        removeDirRecursive($chunkDir);
        jsonFail('ขนาดไฟล์หลังประกอบไม่ถูกต้อง', 400);
    }

    $uploadDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'fileuploads' . DIRECTORY_SEPARATOR;
    if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        removeDirRecursive($chunkDir);
        jsonFail('ไม่สามารถสร้างโฟลเดอร์จัดเก็บไฟล์ได้', 500);
    }

    $uniqueName = uniqid('f_', true) . '.' . $ext;
    $finalPath = $uploadDir . $uniqueName;
    if (!@rename($mergedPath, $finalPath)) {
        if (!@copy($mergedPath, $finalPath)) {
            removeDirRecursive($chunkDir);
            jsonFail('ย้ายไฟล์ไปยัง fileuploads ไม่สำเร็จ', 500);
        }
        @unlink($mergedPath);
    }

    $fileType = ($ext === 'pdf') ? 'pdf' : 'image';

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO uploads (user_id, file_name, file_type, file_mime, file_size, file_path, file_data, description)
             VALUES (:uid, :fname, :ftype, :fmime, :fsize, :fpath, '', :desc)"
        );
        $stmt->execute([
            ':uid' => $userId,
            ':fname' => $fileName,
            ':ftype' => $fileType,
            ':fmime' => $fileMime,
            ':fsize' => $fileSize,
            ':fpath' => $uniqueName,
            ':desc' => $description,
        ]);

        $newFileId = (int)$pdo->lastInsertId();
        logActivity($pdo, 'upload', 'อัปโหลดไฟล์ "' . $fileName . '" (' . round($fileSize / 1024, 1) . ' KB)', $newFileId, $fileName);

        $reviewerIds = $_POST['reviewer_ids'] ?? [];
        if (!is_array($reviewerIds)) {
            $reviewerIds = [$reviewerIds];
        }

        $assignedCount = 0;
        if (!empty($reviewerIds)) {
            $insReviewer = $pdo->prepare(
                "INSERT INTO file_reviewers (upload_id, user_id, assigned_by) VALUES (:uid, :rid, :aid)"
            );
            foreach ($reviewerIds as $rid) {
                $rid = (int)$rid;
                if ($rid <= 0) {
                    continue;
                }
                try {
                    $insReviewer->execute([':uid' => $newFileId, ':rid' => $rid, ':aid' => $userId]);
                    $assignedCount++;
                } catch (PDOException $e2) {
                    // ignore duplicate reviewer assignment
                }
            }
        }

        removeDirRecursive($chunkDir);

        echo json_encode([
            'success' => true,
            'message' => 'อัปโหลดสำเร็จ',
            'file_id' => $newFileId,
            'assigned_count' => $assignedCount,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (PDOException $e) {
        error_log('Chunk finalize DB error: ' . $e->getMessage());
        @unlink($finalPath);
        removeDirRecursive($chunkDir);
        jsonFail('บันทึกไฟล์ลงฐานข้อมูลไม่สำเร็จ', 500);
    }
}

jsonFail('action ไม่ถูกต้อง');
