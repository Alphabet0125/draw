<?php
// ตั้งค่าการเชื่อมต่อฐานข้อมูล
$servername = "localhost";
$username = "root";
$password = "System@min2024";
$dbname = "draw";

// สร้างการเชื่อมต่อ
$conn = new mysqli($servername, $username, $password, $dbname);

// ตรวจสอบการเชื่อมต่อ
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    $sql = "SELECT file_name, file_type, file_data, storage_type FROM uploaded_files WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows > 0) {
        $stmt->bind_result($fileName, $fileType, $fileData, $storageType);
        $stmt->fetch();
        
        // ถอดรหัส base64 ถ้าเก็บแบบ base64
        if ($storageType === 'base64') {
            $fileContent = base64_decode($fileData);
        } else {
            $fileContent = $fileData;
        }
        
        // ล้าง output buffer
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // ส่ง header สำหรับดาวน์โหลด
        header("Content-Type: " . $fileType);
        header("Content-Disposition: attachment; filename=\"" . $fileName . "\"");
        header("Content-Length: " . strlen($fileContent));
        header("Cache-Control: private, max-age=0, must-revalidate");
        header("Pragma: public");
        
        echo $fileContent;
        exit;
    } else {
        echo "ไม่พบไฟล์";
    }
    
    $stmt->close();
} else {
    header("Location: files.php");
}

$conn->close();
?>