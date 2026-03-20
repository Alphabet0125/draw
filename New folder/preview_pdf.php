<?php
// เปิด error reporting เพื่อ debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Log ทุก error
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

// ตั้งค่าการเชื่อมต่อฐานข้อมูล
$servername = "localhost";
$username = "root";
$password = "System@min2024";  // ⚠️ เปลี่ยนตามของ server
$dbname = "draw";

try {
    // สร้างการเชื่อมต่อ
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    // ตรวจสอบการเชื่อมต่อ
    if ($conn->connect_error) {
        error_log("Connection failed: " . $conn->connect_error);
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
    
    // ตั้งค่า charset
    $conn->set_charset("utf8mb4");
    
    if (!isset($_GET['id'])) {
        throw new Exception("No ID specified");
    }
    
    $id = intval($_GET['id']);
    error_log("PDF Preview Request - ID: " . $id);
    
    $sql = "SELECT file_name, file_type, file_data, storage_type, file_size FROM uploaded_files WHERE id = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->store_result();
    
    if ($stmt->num_rows == 0) {
        throw new Exception("PDF not found. ID: " . $id);
    }
    
    $stmt->bind_result($fileName, $fileType, $fileData, $storageType, $fileSize);
    $stmt->fetch();
    
    error_log("File found: " . $fileName . " (Type: " . $fileType . ", Storage: " . $storageType . ", Size: " . $fileSize . ")");
    
    // ตรวจสอบว่าเป็น PDF จริง
    if (stripos($fileType, 'pdf') === false) {
        throw new Exception("File is not a PDF. Type: " . $fileType);
    }
    
    // ถอดรหัส base64 ถ้าเก็บแบบ base64
    if ($storageType === 'base64') {
        error_log("Decoding base64 data...");
        $fileContent = base64_decode($fileData);
        
        if ($fileContent === false) {
            throw new Exception("Failed to decode base64 data");
        }
    } else {
        $fileContent = $fileData;
    }
    
    // ตรวจสอบว่ามีข้อมูล
    if (empty($fileContent)) {
        throw new Exception("PDF content is empty");
    }
    
    // ตรวจสอบ PDF signature
    $pdfSignature = substr($fileContent, 0, 5);
    if ($pdfSignature !== '%PDF-') {
        error_log("Invalid PDF signature: " . bin2hex($pdfSignature));
        throw new Exception("Invalid PDF format. Signature: " . bin2hex(substr($fileContent, 0, 10)));
    }
    
    // ล้าง output buffer ทั้งหมด
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // ส่ง header สำหรับ PDF
    header('Content-Type: application/pdf');
    header('Content-Length: ' . strlen($fileContent));
    header('Content-Disposition: inline; filename="' . basename($fileName) . '"');
    header('Cache-Control: public, max-age=3600');
    header('Accept-Ranges: bytes');
    header('X-Content-Type-Options: nosniff');
    header('Access-Control-Allow-Origin: *');
    
    error_log("PDF sent successfully. Size: " . strlen($fileContent) . " bytes");
    
    // ส่งข้อมูล PDF
    echo $fileContent;
    exit;
    
} catch (Exception $e) {
    // Log error
    error_log("PDF Preview Error: " . $e->getMessage());
    
    // ส่ง error response
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    
    echo "❌ Error: " . $e->getMessage() . "\n\n";
    echo "Debug Info:\n";
    echo "- Request ID: " . (isset($_GET['id']) ? $_GET['id'] : 'Not provided') . "\n";
    echo "- Server Time: " . date('Y-m-d H:i:s') . "\n";
    echo "- Check php_errors.log for details\n";
    
    exit;
} finally {
    if (isset($stmt)) $stmt->close();
    if (isset($conn)) $conn->close();
}
?>