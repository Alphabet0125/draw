<?php
require_once '../config.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

$fileId = (int) ($_GET['id'] ?? 0);
$userId = $_SESSION['user_id'];

if (!$fileId) {
    echo json_encode(['success' => false, 'error' => 'ไม่ได้ระบุไฟล์']);
    exit;
}

// ตรวจสอบสิทธิ์
$check = $pdo->prepare("SELECT id FROM uploads WHERE id = :id AND user_id = :uid");
$check->execute([':id' => $fileId, ':uid' => $userId]);
if (!$check->fetch()) {
    echo json_encode(['success' => false, 'error' => 'ไม่พบไฟล์']);
    exit;
}

try {
    // ดึง log ล่าสุดของแต่ละหน้า
    $stmt = $pdo->prepare(
        "SELECT dl.page_num, dl.display_name, dl.saved_at,
                (SELECT COUNT(*) FROM drawing_logs dl2
                 WHERE dl2.upload_id = dl.upload_id AND dl2.page_num = dl.page_num) AS edit_count
         FROM drawing_logs dl
         WHERE dl.upload_id = :id
         AND dl.id = (
             SELECT MAX(dl3.id) FROM drawing_logs dl3
             WHERE dl3.upload_id = dl.upload_id AND dl3.page_num = dl.page_num
         )
         ORDER BY dl.page_num ASC"
    );
    $stmt->execute([':id' => $fileId]);
    $rows = $stmt->fetchAll();

    $logs = new stdClass();
    foreach ($rows as $row) {
        $key = (string) $row['page_num'];
        $logs->$key = [
            'display_name' => $row['display_name'],
            'saved_at'     => $row['saved_at'],
            'edit_count'   => (int) $row['edit_count'],
        ];
    }

    echo json_encode(['success' => true, 'logs' => $logs]);

} catch (PDOException $e) {
    error_log("Get drawing logs error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'เกิดข้อผิดพลาด']);
}