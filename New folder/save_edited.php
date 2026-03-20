<?php
header('Content-Type: application/json');

// ตั้งค่าการเชื่อมต่อฐานข้อมูล
$servername = "localhost";
$username = "root";
$password = "System@min2024";
$dbname = "draw";

// สร้างการเชื่อมต่อ
$conn = new mysqli($servername, $username, $password, $dbname);

// ตรวจสอบการเชื่อมต่อ
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Connection failed: ' . $conn->connect_error]);
    exit;
}

// รับข้อมูล JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!isset($data['fileId']) || !isset($data['imageData'])) {
    echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ครบถ้วน']);
    exit;
}

$fileId = intval($data['fileId']);
$imageData = $data['imageData'];
$fileName = isset($data['fileName']) ? $data['fileName'] : 'edited_image.png';

// ลบ header ของ base64
$imageData = str_replace('data:image/png;base64,', '', $imageData);
$imageData = str_replace(' ', '+', $imageData);

// ตรวจสอบว่าเป็น base64 ที่ถูกต้อง
if (!base64_decode($imageData, true)) {
    echo json_encode(['success' => false, 'message' => 'ข้อมูลรูปภาพไม่ถูกต้อง']);
    exit;
}

// คำนวณขนาดไฟล์ใหม่
$decodedData = base64_decode($imageData);
$newFileSize = strlen($decodedData);

// อัปเดตข้อมูลในฐานข้อมูล
$sql = "UPDATE uploaded_files SET 
        file_data = ?, 
        file_size = ?,
        file_type = 'image/png',
        storage_type = 'base64'
        WHERE id = ?";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในการเตรียม statement: ' . $conn->error]);
    exit;
}

$stmt->bind_param("sii", $imageData, $newFileSize, $fileId);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode([
            'success' => true, 
            'message' => 'บันทึกรูปภาพสำเร็จ',
            'fileId' => $fileId,
            'fileSize' => number_format($newFileSize / 1024, 2) . ' KB'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'ไม่พบไฟล์ที่ต้องการอัปเดต']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>