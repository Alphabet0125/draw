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

// รับ file ID
$fileId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($fileId <= 0) {
    header("Location: files.php");
    exit;
}

// ดึงข้อมูลไฟล์
$sql = "SELECT id, file_name, file_type, file_size, storage_type FROM uploaded_files WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $fileId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "<script>alert('ไม่พบไฟล์'); window.location.href='files.php';</script>";
    exit;
}

$file = $result->fetch_assoc();

// ตรวจสอบว่าเป็นรูปภาพหรือไม่
$isImage = strpos($file['file_type'], 'image') !== false;

if (!$isImage) {
    echo "<script>alert('ไฟล์นี้ไม่ใช่รูปภาพ'); window.location.href='files.php';</script>";
    exit;
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เครื่องมือวาดภาพ - <?php echo htmlspecialchars($file['file_name']); ?></title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="view-drawing.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="drawing-page">
    <!-- Header -->
    <div class="view-header">
        <div class="header-left">
            <a href="files.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> กลับ
            </a>
            <h1>
                <i class="fas fa-paint-brush"></i> 
                <span id="currentFileName"><?php echo htmlspecialchars($file['file_name']); ?></span>
            </h1>
        </div>
        <div class="header-right">
            <button class="theme-toggle" id="themeToggle">
                <i class="fas fa-moon"></i>
            </button>
        </div>
    </div>

    <!-- Main Content -->
    <div class="view-container">
        <!-- Toolbar -->
        <div class="view-toolbar">
            <!-- Tools Section -->
            <div class="toolbar-section">
                <h4><i class="fas fa-tools"></i> เครื่องมือ</h4>
                <div class="tool-buttons">
                    <button class="tool-btn active" data-tool="brush" title="พู่กัน (B)">
                        <i class="fas fa-paintbrush"></i>
                        <span>พู่กัน</span>
                    </button>
                    <button class="tool-btn" data-tool="pencil" title="ดินสอ (P)">
                        <i class="fas fa-pencil-alt"></i>
                        <span>ดินสอ</span>
                    </button>
                    <button class="tool-btn" data-tool="marker" title="ปากกาเน้นข้อความ (M)">
                        <i class="fas fa-highlighter"></i>
                        <span>ปากกา</span>
                    </button>
                    <button class="tool-btn" data-tool="eraser" title="ยางลบ (E)">
                        <i class="fas fa-eraser"></i>
                        <span>ยางลบ</span>
                    </button>
                    <button class="tool-btn" data-tool="line" title="เส้นตรง (L)">
                        <i class="fas fa-minus"></i>
                        <span>เส้นตรง</span>
                    </button>
                    <button class="tool-btn" data-tool="rectangle" title="สี่เหลี่ยม (R)">
                        <i class="far fa-square"></i>
                        <span>สี่เหลี่ยม</span>
                    </button>
                    <button class="tool-btn" data-tool="circle" title="วงกลม (C)">
                        <i class="far fa-circle"></i>
                        <span>วงกลม</span>
                    </button>
                    <button class="tool-btn" data-tool="arrow" title="ลูกศร (A)">
                        <i class="fas fa-arrow-right"></i>
                        <span>ลูกศร</span>
                    </button>
                    <button class="tool-btn" data-tool="text" title="ข้อความ (T)">
                        <i class="fas fa-font"></i>
                        <span>ข้อความ</span>
                    </button>
                </div>
            </div>

            <!-- Color Section -->
            <div class="toolbar-section">
                <h4><i class="fas fa-palette"></i> สี</h4>
                <div class="color-picker-container">
                    <input type="color" id="colorPicker" value="#ff6b35">
                    <div class="preset-colors">
                        <button class="color-preset" style="background: #000000;" data-color="#000000" title="ดำ"></button>
                        <button class="color-preset" style="background: #ffffff; border: 1px solid #ccc;" data-color="#ffffff" title="ขาว"></button>
                        <button class="color-preset active" style="background: #ff6b35;" data-color="#ff6b35" title="ส้ม"></button>
                        <button class="color-preset" style="background: #f7931e;" data-color="#f7931e" title="ส้มทอง"></button>
                        <button class="color-preset" style="background: #e74c3c;" data-color="#e74c3c" title="แดง"></button>
                        <button class="color-preset" style="background: #3498db;" data-color="#3498db" title="ฟ้า"></button>
                        <button class="color-preset" style="background: #2ecc71;" data-color="#2ecc71" title="เขียว"></button>
                        <button class="color-preset" style="background: #9b59b6;" data-color="#9b59b6" title="ม่วง"></button>
                        <button class="color-preset" style="background: #f39c12;" data-color="#f39c12" title="ทอง"></button>
                        <button class="color-preset" style="background: #1abc9c;" data-color="#1abc9c" title="เขียวมิ้นท์"></button>
                    </div>
                </div>
            </div>

            <!-- Size Section -->
            <div class="toolbar-section">
                <h4><i class="fas fa-text-height"></i> ขนาด</h4>
                <div class="size-control">
                    <input type="range" id="brushSize" min="1" max="50" value="5">
                    <span id="brushSizeValue">5px</span>
                </div>
                <div class="size-preview">
                    <div id="sizePreviewDot"></div>
                </div>
            </div>

            <!-- Opacity Section -->
            <div class="toolbar-section">
                <h4><i class="fas fa-adjust"></i> ความโปร่งใส</h4>
                <div class="size-control">
                    <input type="range" id="opacitySlider" min="0" max="100" value="100">
                    <span id="opacityValue">100%</span>
                </div>
            </div>

            <!-- Actions Section -->
            <div class="toolbar-section">
                <h4><i class="fas fa-magic"></i> การดำเนินการ</h4>
                <div class="action-buttons">
                    <button class="action-btn" id="undoBtn" title="ย้อนกลับ (Ctrl+Z)">
                        <i class="fas fa-undo"></i> Undo
                    </button>
                    <button class="action-btn" id="redoBtn" title="ทำซ้ำ (Ctrl+Y)">
                        <i class="fas fa-redo"></i> Redo
                    </button>
                    <button class="action-btn" id="clearBtn" title="ล้างทั้งหมด">
                        <i class="fas fa-trash"></i> ล้าง
                    </button>
                </div>
            </div>

            <!-- File Info Section -->
            <div class="toolbar-section">
                <h4><i class="fas fa-info-circle"></i> ข้อมูลไฟล์</h4>
                <div class="file-info-box">
                    <p><strong>ชื่อไฟล์:</strong><br><?php echo htmlspecialchars($file['file_name']); ?></p>
                    <p><strong>ขนาด:</strong> <?php echo number_format($file['file_size'] / 1024, 2); ?> KB</p>
                    <p><strong>ประเภท:</strong> <?php echo htmlspecialchars($file['file_type']); ?></p>
                    <p><strong>เก็บข้อมูล:</strong> 
                        <?php if ($file['storage_type'] === 'base64'): ?>
                            <span class="badge-base64"><i class="fas fa-shield-alt"></i> Base64</span>
                        <?php else: ?>
                            <span class="badge-blob"><i class="fas fa-database"></i> Binary</span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Canvas Area -->
        <div class="view-canvas-area">
            <div class="canvas-info" id="canvasInfo">
                <i class="fas fa-spinner fa-spin"></i> กำลังโหลดรูปภาพ...
            </div>
            <div class="canvas-wrapper" id="canvasWrapper">
                <canvas id="drawingCanvas"></canvas>
            </div>
        </div>
    </div>

    <!-- Footer Actions -->
    <div class="view-footer">
        <div class="footer-left">
            <a href="files.php" class="btn-cancel">
                <i class="fas fa-times"></i> ยกเลิก
            </a>
        </div>
        <div class="footer-center">
            <span class="canvas-size-info" id="canvasSizeInfo"></span>
        </div>
        <div class="footer-right">
            <a href="download.php?id=<?php echo $fileId; ?>" class="btn-download-footer">
                <i class="fas fa-download"></i> ดาวน์โหลดต้นฉบับ
            </a>
            <button class="btn-save-drawing" id="saveDrawingBtn" title="บันทึก (Ctrl+S)">
                <i class="fas fa-save"></i> บันทึกรูปภาพ
            </button>
        </div>
    </div>

    <!-- Hidden data -->
    <input type="hidden" id="fileId" value="<?php echo $fileId; ?>">
    <input type="hidden" id="fileName" value="<?php echo htmlspecialchars($file['file_name']); ?>">

    <script src="script.js"></script>
    <script src="view-drawing.js"></script>
</body>
</html>