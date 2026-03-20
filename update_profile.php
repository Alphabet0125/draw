<?php
require_once 'config.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

$input  = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$userId = $_SESSION['user_id'];

if (!$action) {
    echo json_encode(['success' => false, 'error' => 'ไม่ได้ระบุ action']);
    exit;
}

try {
    // ★ อัปโหลดรูปโปรไฟล์
    if ($action === 'avatar') {
        $avatar = $input['avatar'] ?? '';
        if (!$avatar) {
            echo json_encode(['success' => false, 'error' => 'ไม่มีข้อมูลรูปภาพ']);
            exit;
        }

        // ตรวจสอบขนาด base64 (2MB ≈ 2.7MB base64)
        if (strlen($avatar) > 3 * 1024 * 1024) {
            echo json_encode(['success' => false, 'error' => 'รูปภาพมีขนาดใหญ่เกินไป']);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE users SET avatar = :avatar WHERE id = :id");
        $stmt->execute([':avatar' => $avatar, ':id' => $userId]);

        echo json_encode(['success' => true]);
        exit;
    }

    // ★ อัปเดตข้อมูลส่วนตัว
    if ($action === 'info') {
        $name       = trim($input['display_name'] ?? '');
        $phone      = trim($input['phone'] ?? '');
        $department = trim($input['department'] ?? '');
        $position   = trim($input['position'] ?? '');
        $bio        = trim($input['bio'] ?? '');

        if (!$name) {
            echo json_encode(['success' => false, 'error' => 'กรุณากรอกชื่อที่แสดง']);
            exit;
        }

        if (mb_strlen($name) > 100) $name = mb_substr($name, 0, 100);
        if (mb_strlen($phone) > 20) $phone = mb_substr($phone, 0, 20);
        if (mb_strlen($department) > 100) $department = mb_substr($department, 0, 100);
        if (mb_strlen($position) > 100) $position = mb_substr($position, 0, 100);
        if (mb_strlen($bio) > 500) $bio = mb_substr($bio, 0, 500);

        $stmt = $pdo->prepare(
            "UPDATE users SET
                display_name = :name,
                phone = :phone,
                department = :dept,
                position = :pos,
                bio = :bio
             WHERE id = :id"
        );
        $stmt->execute([
            ':name' => $name,
            ':phone' => $phone,
            ':dept' => $department,
            ':pos' => $position,
            ':bio' => $bio,
            ':id' => $userId,
        ]);

        // อัปเดต session
        $_SESSION['display_name'] = $name;

        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'action ไม่ถูกต้อง']);

} catch (PDOException $e) {
    error_log("Update profile error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'เกิดข้อผิดพลาดในการบันทึก']);
}