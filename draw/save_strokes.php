<?php
require_once '../config.php';
requireLogin();
require_once 'log_helper.php';

header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'error' => 'JSON decode failed']);
    exit;
}

$fileId  = (int) ($input['id']      ?? 0);
$page    = (int) ($input['page']    ?? 1);
$strokes = $input['strokes'] ?? [];
$userId  = $_SESSION['user_id'];

if (!$fileId || !is_array($strokes)) {
    echo json_encode(['success' => false, 'error' => 'ข้อมูลไม่ครบ']);
    exit;
}

try {
    // สร้างตารางถ้ายังไม่มี
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

    // ★ ลบ textbox และ shape overlay strokes เก่าของหน้านี้ก่อนเสมอ แล้วค่อย insert ชุดปัจจุบัน
    // (เพื่อให้การลบ textbox/shape ถูกบันทึกอย่างถูกต้อง)
    $delStmt = $pdo->prepare(
        "DELETE FROM drawing_strokes
         WHERE upload_id = :fid AND page_number = :page
         AND (
             JSON_UNQUOTE(JSON_EXTRACT(stroke_json, '\$.type')) = 'textbox'
             OR JSON_UNQUOTE(JSON_EXTRACT(stroke_json, '\$.sovType')) IS NOT NULL
         )"
    );
    $delStmt->execute([':fid' => $fileId, ':page' => $page]);

    if (count($strokes) > 0) {
        $stmt = $pdo->prepare(
            "INSERT INTO drawing_strokes (upload_id, page_number, user_id, stroke_json, created_at)
             VALUES (:fid, :page, :uid, :json, NOW())"
        );

        foreach ($strokes as $stroke) {
            $json = json_encode($stroke, JSON_UNESCAPED_UNICODE);
            $stmt->execute([
                ':fid'  => $fileId,
                ':page' => $page,
                ':uid'  => $userId,
                ':json' => $json,
            ]);
        }
    }

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    error_log("Save strokes error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
