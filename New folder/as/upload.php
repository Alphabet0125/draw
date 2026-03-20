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

// ตั้งค่า character set
$conn->set_charset("utf8mb4");

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['fileToUpload'])) {
    $file = $_FILES['fileToUpload'];
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    
    // ตรวจสอบว่ามีข้อผิดพลาดในการอัปโหลดหรือไม่
    if ($file['error'] === UPLOAD_ERR_OK) {
        $fileName = $file['name'];
        $fileType = $file['type'];
        $fileSize = $file['size'];
        $fileTmpName = $file['tmp_name'];
        
        // จำกัดขนาดไฟล์ (50MB)
        $maxFileSize = 50 * 1024 * 1024;
        if ($fileSize > $maxFileSize) {
            echo "<script>alert('ไฟล์มีขนาดใหญ่เกินไป! (สูงสุด 50MB)'); window.location.href='index.php';</script>";
            exit;
        }
        
        // ตรวจสอบว่าเป็นรูปภาพหรือ PDF หรือไม่
        $isImageOrPDF = false;
        $allowedImageTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
        $allowedPDFTypes = ['application/pdf'];
        
        if (in_array($fileType, $allowedImageTypes) || in_array($fileType, $allowedPDFTypes)) {
            $isImageOrPDF = true;
        }
        
        // อ่านข้อมูลไฟล์
        $fileContent = file_get_contents($fileTmpName);
        
        // เข้ารหัสเป็น base64 สำหรับรูปภาพและ PDF
        if ($isImageOrPDF) {
            $fileData = base64_encode($fileContent);
            $storageType = 'base64';
            $storageIcon = '🔐';
            $storageText = 'Base64';
        } else {
            // ไฟล์ประเภทอื่นๆ ใช้ blob
            $fileData = $conn->real_escape_string($fileContent);
            $storageType = 'blob';
            $storageIcon = '📦';
            $storageText = 'Binary';
        }
        
        // เตรียม SQL statement
        $sql = "INSERT INTO uploaded_files (file_name, file_type, file_size, file_data, storage_type, description) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            echo "<script>alert('❌ เกิดข้อผิดพลาดในการเตรียม statement: " . $conn->error . "'); window.location.href='index.php';</script>";
            exit;
        }
        
        $stmt->bind_param("ssisss", $fileName, $fileType, $fileSize, $fileData, $storageType, $description);
        
        if ($stmt->execute()) {
            $fileTypeDisplay = $isImageOrPDF ? ($fileType == 'application/pdf' ? 'PDF' : 'รูปภาพ') : 'ไฟล์';
            echo "<script>
                    alert('✅ อัปโหลด{$fileTypeDisplay}สำเร็จ!\\n\\n📁 ไฟล์: " . addslashes($fileName) . "\\n💾 รูปแบบ: {$storageIcon} {$storageText}\\n📊 ขนาด: " . number_format($fileSize / 1024, 2) . " KB');
                    window.location.href='files.php';
                  </script>";
        } else {
            echo "<script>alert('❌ เกิดข้อผิดพลาด: " . addslashes($stmt->error) . "'); window.location.href='index.php';</script>";
        }
        
        $stmt->close();
    } else {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'ไฟล์มีขนาดใหญ่เกินที่กำหนดใน php.ini',
            UPLOAD_ERR_FORM_SIZE => 'ไฟล์มีขนาดใหญ่เกินที่กำหนดในฟอร์ม',
            UPLOAD_ERR_PARTIAL => 'ไฟล์ถูกอัปโหลดเพียงบางส่วน',
            UPLOAD_ERR_NO_FILE => 'ไม่มีไฟล์ถูกอัปโหลด',
            UPLOAD_ERR_NO_TMP_DIR => 'ไม่พบโฟลเดอร์ชั่วคราว',
            UPLOAD_ERR_CANT_WRITE => 'ไม่สามารถเขียนไฟล์ลงดิสก์',
            UPLOAD_ERR_EXTENSION => 'การอัปโหลดถูกหยุดโดย extension'
        ];
        
        $errorMsg = isset($errorMessages[$file['error']]) ? $errorMessages[$file['error']] : 'เกิดข้อผิดพลาดที่ไม่ทราบสาเหตุ';
        echo "<script>alert('❌ " . $errorMsg . "'); window.location.href='index.php';</script>";
    }
} else {
    header("Location: index.php");
}

$conn->close();
?>