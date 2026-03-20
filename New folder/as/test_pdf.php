<?php
// ตั้งค่าการเชื่อมต่อฐานข้อมูล
$servername = "localhost";
$username = "root";
$password = "System@min2024";
$dbname = "draw";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ค้นหาไฟล์ PDF
$sql = "SELECT id, file_name, file_type, file_size, storage_type FROM uploaded_files WHERE file_type LIKE '%pdf%'";
$result = $conn->query($sql);

echo "<h2>PDF Files in Database:</h2>";

if ($result && $result->num_rows > 0) {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>ID</th><th>File Name</th><th>Type</th><th>Size</th><th>Storage</th><th>Test</th></tr>";
    
    while($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . htmlspecialchars($row['file_name']) . "</td>";
        echo "<td>" . $row['file_type'] . "</td>";
        echo "<td>" . number_format($row['file_size'] / 1024, 2) . " KB</td>";
        echo "<td>" . $row['storage_type'] . "</td>";
        echo "<td><a href='preview_pdf.php?id=" . $row['id'] . "' target='_blank'>Test Preview</a></td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<p style='color: red;'>❌ No PDF files found in database!</p>";
    echo "<p>Please upload a PDF file first.</p>";
}

$conn->close();
?>