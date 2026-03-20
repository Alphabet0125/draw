<?php
// ปิด error แสดงออกมาตรงๆ
error_reporting(0);
ini_set('display_errors', 0);

// เพิ่ม limits
ini_set('memory_limit', '1024M');
ini_set('max_execution_time', '600');
ini_set('post_max_size', '200M');
ini_set('upload_max_filesize', '200M');

// เริ่ม output buffering
ob_start();

header('Content-Type: application/json; charset=utf-8');

try {
    // ตรวจสอบ method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('ต้องใช้ POST method เท่านั้น');
    }
    
    // รับข้อมูลจาก FormData
    if (!isset($_POST['fileId']) || !isset($_POST['imageData'])) {
        throw new Exception('ไม่พบข้อมูล fileId หรือ imageData');
    }
    
    $fileId = intval($_POST['fileId']);
    $imageData = $_POST['imageData'];
    $fileName = isset($_POST['fileName']) ? $_POST['fileName'] : 'edited_image.png';
    
    // ลบ data URL prefix
    if (strpos($imageData, 'data:image') === 0) {
        $imageData = preg_replace('/^data:image\/\w+;base64,/', '', $imageData);
    }
    
    // ทำความสะอาด base64
    $imageData = str_replace([' ', "\n", "\r", "\t"], '', $imageData);
    $imageData = str_replace('-', '+', $imageData);
    $imageData = str_replace('_', '/', $imageData);
    
    // เพิ่ม padding ถ้าจำเป็น
    $mod = strlen($imageData) % 4;
    if ($mod) {
        $imageData .= substr('====', $mod);
    }
    
    // Decode
    $decodedData = base64_decode($imageData, true);
    
    if ($decodedData === false) {
        throw new Exception('ไม่สามารถ decode base64 ได้');
    }
    
    $newFileSize = strlen($decodedData);
    
    // ตรวจสอบว่าเป็นรูปภาพจริง
    $imageInfo = @getimagesizefromstring($decodedData);
    if ($imageInfo === false) {
        throw new Exception('ข้อมูลไม่ใช่รูปภาพที่ถูกต้อง');
    }
    
    $mimeType = $imageInfo['mime'];
    
    // เชื่อมต่อฐานข้อมูล
    $servername = "localhost";
    $username = "root";
    $password = "System@min2024";
    $dbname = "draw";
    
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        throw new Exception('ไม่สามารถเชื่อมต่อฐานข้อมูลได้');
    }
    
    // ตั้งค่า charset
    $conn->set_charset("utf8mb4");
    
    // เพิ่ม packet size
    $conn->query("SET GLOBAL max_allowed_packet=209715200"); // 200MB
    
    // บันทึกลงฐานข้อมูล
    $sql = "UPDATE uploaded_files SET 
            file_data = ?, 
            file_size = ?,
            file_type = ?,
            storage_type = 'base64'
            WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    $stmt->bind_param("sisi", $imageData, $newFileSize, $mimeType, $fileId);
    
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }
    
    if ($stmt->affected_rows === 0) {
        throw new Exception('ไม่พบไฟล์ที่ต้องการอัปเดต');
    }
    
    $stmt->close();
    $conn->close();
    
    // ทำความสะอาด output buffer
    ob_end_clean();
    
    // ส่งผลลัพธ์
    echo json_encode([
        'success' => true,
        'message' => 'บันทึกสำเร็จ',
        'fileId' => $fileId,
        'fileSize' => number_format($newFileSize / 1024, 2) . ' KB',
        'fileSizeMB' => number_format($newFileSize / (1024 * 1024), 2) . ' MB',
        'mimeType' => $mimeType,
        'dimensions' => $imageInfo[0] . 'x' . $imageInfo[1]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    ob_end_clean();
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

exit;
?>