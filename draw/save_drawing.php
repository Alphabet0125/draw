<?php
require_once '../config.php';
requireLogin();
require_once 'log_helper.php';

header('Content-Type: application/json; charset=utf-8');

// ★ เพิ่ม limit สำหรับรับ data ขนาดใหญ่
ini_set('memory_limit', '256M');
ini_set('max_input_vars', 10000);

$input  = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'error' => 'ไม่สามารถอ่านข้อมูลได้ (JSON decode failed)', 'detail' => json_last_error_msg()]);
    exit;
}

$fileId = (int) ($input['id'] ?? 0);
$page   = (int) ($input['page'] ?? 1);
$data   = $input['data'] ?? '';
$userId = $_SESSION['user_id'];

if (!$fileId) {
    echo json_encode(['success' => false, 'error' => 'ไม่ได้ระบุ file id']);
    exit;
}

if (!$data) {
    echo json_encode(['success' => false, 'error' => 'ไม่มีข้อมูล drawing (data ว่าง)']);
    exit;
}

// ★ ตรวจสอบว่าไฟล์มีอยู่จริง
try {
    $checkStmt = $pdo->prepare("SELECT id FROM uploads WHERE id = :id");
    $checkStmt->execute([':id' => $fileId]);
    if (!$checkStmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'ไม่พบไฟล์']);
        exit;
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'ตรวจสอบไฟล์ไม่สำเร็จ', 'detail' => $e->getMessage()]);
    exit;
}

try {
    // ★ เช็คว่ามี drawing ของหน้านี้อยู่แล้วหรือไม่
    $existStmt = $pdo->prepare(
        "SELECT id FROM drawings WHERE upload_id = :uid AND page_number = :page"
    );
    $existStmt->execute([':uid' => $fileId, ':page' => $page]);
    $existing = $existStmt->fetch();

    if ($existing) {
        // UPDATE — ไม่เปลี่ยน user_id เพื่อรักษาชื่อผู้วาดเดิม
        $stmt = $pdo->prepare(
            "UPDATE drawings SET drawing_data = :data, updated_at = NOW() WHERE id = :id"
        );
        $stmt->execute([':data' => $data, ':id' => $existing['id']]);
    } else {
        // INSERT
        $stmt = $pdo->prepare(
            "INSERT INTO drawings (upload_id, user_id, page_number, drawing_data, created_at, updated_at)
             VALUES (:fid, :uid, :page, :data, NOW(), NOW())"
        );
        $stmt->execute([
            ':fid'  => $fileId,
            ':uid'  => $userId,
            ':page' => $page,
            ':data' => $data,
        ]);
    }

    // ★ Log: บันทึก drawing
    $fnStmt = $pdo->prepare("SELECT file_name FROM uploads WHERE id = :id");
    $fnStmt->execute([':id' => $fileId]);
    $fnRow = $fnStmt->fetch();
    $fnName = $fnRow ? $fnRow['file_name'] : '';
    logActivity($pdo, 'save_drawing', 'บันทึก drawing หน้า ' . $page . ' ของไฟล์ #' . $fileId, $fileId, $fnName);

    echo json_encode(['success' => true, 'page' => $page]);

} catch (PDOException $e) {
    error_log("Save drawing error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error'   => 'บันทึกไม่สำเร็จ',
        'detail'  => $e->getMessage(),
        'data_size' => strlen($data)
    ]);
}