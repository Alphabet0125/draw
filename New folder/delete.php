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
    
    $sql = "DELETE FROM uploaded_files WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        echo "<script>alert('🗑️ ลบไฟล์สำเร็จ!'); window.location.href='files.php';</script>";
    } else {
        echo "<script>alert('❌ เกิดข้อผิดพลาด!'); window.location.href='files.php';</script>";
    }
    
    $stmt->close();
} else {
    header("Location: files.php");
}

$conn->close();
?>