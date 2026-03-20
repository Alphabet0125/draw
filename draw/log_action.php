<?php
/**
 * API: บันทึก activity log จาก JavaScript (POST)
 * ใช้สำหรับ action ที่เกิดจากฝั่ง client เช่น download, clear_drawing, tool_use
 */
require_once '../config.php';
requireLogin();
require_once 'log_helper.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'POST only']);
    exit;
}

// รองรับทั้ง FormData และ JSON
$input = $_POST;
if (empty($input['action_type'])) {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
}

$actionType   = trim($input['action_type'] ?? '');
$actionDetail = trim($input['action_detail'] ?? '');
$fileId       = !empty($input['file_id']) ? (int)$input['file_id'] : null;
$fileName     = trim($input['file_name'] ?? '');

$allowedTypes = ['upload', 'delete', 'save_drawing', 'clear_drawing', 'status_change', 'download', 'view', 'tool_use', 'comment'];

if (!$actionType || !in_array($actionType, $allowedTypes)) {
    echo json_encode(['success' => false, 'error' => 'invalid action_type']);
    exit;
}

// ถ้าไม่มี file_name ลองดึงจาก DB
if ($fileId && !$fileName) {
    try {
        $stmt = $pdo->prepare("SELECT file_name FROM uploads WHERE id = :id");
        $stmt->execute([':id' => $fileId]);
        $row = $stmt->fetch();
        $fileName = $row ? $row['file_name'] : '';
    } catch (PDOException $e) {
        $fileName = '';
    }
}

$result = logActivity($pdo, $actionType, $actionDetail, $fileId, $fileName ?: null);

echo json_encode(['success' => $result]);
