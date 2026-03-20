<?php
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

// ★ เฉพาะ POST เท่านั้น
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// ★ ตรวจสอบสิทธิ์ — เฉพาะแผนก IT และ HR
$currentUserStmt = $pdo->prepare("SELECT department FROM users WHERE id = :id");
$currentUserStmt->execute([':id' => $_SESSION['user_id']]);
$currentUser = $currentUserStmt->fetch();
$myDepartment = trim($currentUser['department'] ?? '');

$allowedDepts = ['IT', 'it', 'HR', 'hr', 'Information Technology', 'Human Resources', 'ไอที', 'ฝ่ายบุคคล'];
$hasAccess = false;
foreach ($allowedDepts as $dept) {
    if (stripos($myDepartment, $dept) !== false) {
        $hasAccess = true;
        break;
    }
}

if (!$hasAccess) {
    echo json_encode(['success' => false, 'error' => 'คุณไม่มีสิทธิ์ลบผู้ใช้']);
    exit;
}

// ★ รับ user_id จาก request body (JSON)
$input = json_decode(file_get_contents('php://input'), true);
$targetUserId = intval($input['user_id'] ?? 0);

if ($targetUserId <= 0) {
    echo json_encode(['success' => false, 'error' => 'ไม่พบ user_id']);
    exit;
}

// ★ ห้ามลบตัวเอง
if ($targetUserId === (int)$_SESSION['user_id']) {
    echo json_encode(['success' => false, 'error' => 'ไม่สามารถลบบัญชีตัวเองได้']);
    exit;
}

// ★ ตรวจว่า user มีอยู่จริง
$checkStmt = $pdo->prepare("SELECT id, display_name FROM users WHERE id = :id");
$checkStmt->execute([':id' => $targetUserId]);
$targetUser = $checkStmt->fetch();

if (!$targetUser) {
    echo json_encode(['success' => false, 'error' => 'ไม่พบผู้ใช้นี้ในระบบ']);
    exit;
}

try {
    $pdo->beginTransaction();

    // ลบข้อมูลที่เกี่ยวข้อง
    $pdo->prepare("DELETE FROM login_logs WHERE user_id = :id")->execute([':id' => $targetUserId]);
    $pdo->prepare("DELETE FROM activity_logs WHERE user_id = :id")->execute([':id' => $targetUserId]);
    $pdo->prepare("DELETE FROM file_reviewers WHERE user_id = :id OR assigned_by = :id2")->execute([':id' => $targetUserId, ':id2' => $targetUserId]);

    // ลบ user
    $pdo->prepare("DELETE FROM users WHERE id = :id")->execute([':id' => $targetUserId]);

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'ลบผู้ใช้ "' . $targetUser['display_name'] . '" สำเร็จ']);

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Delete user error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'เกิดข้อผิดพลาดในการลบ: ' . $e->getMessage()]);
}
