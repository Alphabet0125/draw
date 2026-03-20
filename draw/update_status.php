<?php
require_once '../config.php';
requireLogin();
require_once 'log_helper.php';

header('Content-Type: application/json; charset=utf-8');

$input  = json_decode(file_get_contents('php://input'), true);
$fileId = (int) ($input['id'] ?? 0);
$status = trim($input['status'] ?? '');
$userId = $_SESSION['user_id'];

$allowedStatuses = ['รอตรวจ', 'กำลังตรวจ', 'ผ่าน', 'ไม่ผ่าน'];

if (!$fileId || !$status) {
    echo json_encode(['success' => false, 'error' => 'ข้อมูลไม่ครบ']);
    exit;
}

if (!in_array($status, $allowedStatuses)) {
    echo json_encode(['success' => false, 'error' => 'สถานะไม่ถูกต้อง']);
    exit;
}

try {
    // ★★★ ตรวจสอบสิทธิ์: เจ้าของไฟล์ หรือ ผู้ตรวจงาน ★★★
    $ownerStmt = $pdo->prepare("SELECT user_id, file_name FROM uploads WHERE id = :id");
    $ownerStmt->execute([':id' => $fileId]);
    $fileRow = $ownerStmt->fetch();

    if (!$fileRow) {
        echo json_encode(['success' => false, 'error' => 'ไม่พบไฟล์']);
        exit;
    }

    $isOwner = ((int)$fileRow['user_id'] === $userId);

    $rvStmt = $pdo->prepare("SELECT COUNT(*) FROM file_reviewers WHERE upload_id = :uid AND user_id = :rid");
    $rvStmt->execute([':uid' => $fileId, ':rid' => $userId]);
    $isReviewer = $rvStmt->fetchColumn() > 0;

    if (!$isOwner && !$isReviewer) {
        echo json_encode(['success' => false, 'error' => 'คุณไม่มีสิทธิ์เปลี่ยนสถานะไฟล์นี้']);
        exit;
    }

    // อัปเดตสถานะ (ไม่จำกัด user_id ใน WHERE เพราะตรวจสิทธิ์แล้ว)
    $stmt = $pdo->prepare("UPDATE uploads SET status = :status WHERE id = :id");
    $stmt->execute([':status' => $status, ':id' => $fileId]);

    if ($stmt->rowCount() > 0) {
        // ★ Log: เปลี่ยนสถานะ
        $fnName = $fileRow['file_name'];
        logActivity($pdo, 'status_change', 'เปลี่ยนสถานะเป็น "' . $status . '" ไฟล์ #' . $fileId, $fileId, $fnName);

        echo json_encode(['success' => true, 'status' => $status]);
    } else {
        echo json_encode(['success' => false, 'error' => 'ไม่มีการเปลี่ยนแปลง']);
    }

} catch (PDOException $e) {
    error_log("Update status error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'เกิดข้อผิดพลาด']);
}