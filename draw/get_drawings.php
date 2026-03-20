<?php
require_once '../config.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

$fileId = (int) ($_GET['id'] ?? 0);
$userId = $_SESSION['user_id'];

if (!$fileId) {
    echo json_encode(['success' => false, 'error' => 'ไม่ได้ระบุ file id']);
    exit;
}

try {
    $stmt = $pdo->prepare(
        "SELECT d.page_number, d.drawing_data, d.updated_at, u.display_name AS drawer_name
         FROM drawings d
         LEFT JOIN users u ON d.user_id = u.id
         WHERE d.upload_id = :fid
         ORDER BY d.page_number ASC"
    );
    $stmt->execute([':fid' => $fileId]);
    $rows = $stmt->fetchAll();

    $drawings = [];
    $timestamps = [];
    $drawerNames = [];
    foreach ($rows as $row) {
        $page = (int)$row['page_number'];
        $drawings[$page] = $row['drawing_data'];
        $timestamps[$page] = $row['updated_at'];
        $drawerNames[$page] = $row['drawer_name'] ?? '';
    }

    // ดึง strokes แต่ละเส้นพร้อมชื่อผู้วาด
    $strokesByPage = [];
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS drawing_strokes (
                id           INT AUTO_INCREMENT PRIMARY KEY,
                upload_id    INT NOT NULL,
                page_number  INT NOT NULL DEFAULT 1,
                user_id      INT NOT NULL,
                stroke_json  MEDIUMTEXT NOT NULL,
                created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_up_pg (upload_id, page_number)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $sStmt = $pdo->prepare(
            "SELECT ds.page_number, ds.user_id, ds.stroke_json, ds.created_at,
                    u.display_name AS user_name
             FROM drawing_strokes ds
             LEFT JOIN users u ON ds.user_id = u.id
             WHERE ds.upload_id = :fid
             ORDER BY ds.page_number ASC, ds.created_at ASC"
        );
        $sStmt->execute([':fid' => $fileId]);
        foreach ($sStmt->fetchAll() as $sRow) {
            $pg = (int)$sRow['page_number'];
            if (!isset($strokesByPage[$pg])) $strokesByPage[$pg] = [];
            $strokesByPage[$pg][] = [
                'user_id'    => (int)$sRow['user_id'],
                'user_name'  => $sRow['user_name'] ?? '',
                'created_at' => $sRow['created_at'],
                'stroke'     => json_decode($sRow['stroke_json'], true),
            ];
        }
    } catch (PDOException $se) {
        // ถ้าตารางยังไม่มี ก็ส่ง strokes ว่างไปแทน
    }

    echo json_encode([
        'success'      => true,
        'drawings'     => (object)$drawings,
        'timestamps'   => (object)$timestamps,
        'drawer_names' => (object)$drawerNames,
        'strokes'      => (object)$strokesByPage,
    ]);

} catch (PDOException $e) {
    error_log("Get drawings error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}