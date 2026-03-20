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

// จัดการการเรียงลำดับ
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'recent';
$orderBy = "upload_date DESC";

switch($sort) {
    case 'name':
        $orderBy = "file_name ASC";
        break;
    case 'size':
        $orderBy = "file_size DESC";
        break;
    case 'type':
        $orderBy = "file_type ASC";
        break;
    default:
        $orderBy = "upload_date DESC";
}

// จัดการการค้นหา
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
$whereClause = "";
if (!empty($searchTerm)) {
    $searchTerm = $conn->real_escape_string($searchTerm);
    $whereClause = "WHERE file_name LIKE '%$searchTerm%' OR description LIKE '%$searchTerm%'";
}

// นับจำนวนไฟล์ทั้งหมด
$countSql = "SELECT COUNT(*) as total FROM uploaded_files $whereClause";
$countResult = $conn->query($countSql);
$totalFiles = $countResult->fetch_assoc()['total'];

// นับจำนวนไฟล์ base64
$base64CountSql = "SELECT COUNT(*) as total FROM uploaded_files WHERE storage_type = 'base64' " . 
                  (!empty($whereClause) ? "AND (" . str_replace("WHERE ", "", $whereClause) . ")" : "");
$base64CountResult = $conn->query($base64CountSql);
$base64Files = $base64CountResult->fetch_assoc()['total'];

// นับขนาดรวม
$sizeSql = "SELECT SUM(file_size) as total_size FROM uploaded_files $whereClause";
$sizeResult = $conn->query($sizeSql);
$totalSize = $sizeResult->fetch_assoc()['total_size'];

// ดึงข้อมูลไฟล์
$sql = "SELECT id, file_name, file_type, file_size, upload_date, description, storage_type FROM uploaded_files $whereClause ORDER BY $orderBy";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ไฟล์ทั้งหมด - Orange Theme</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="drawing-tools.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- PDF.js Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script>
        // ตั้งค่า workerSrc สำหรับ PDF.js
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    </script>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <header>
            <h1><i class="fas fa-folder-open"></i> ไฟล์ของฉัน</h1>
            <div class="header-actions">
                <a href="index.php" class="btn-upload-new">
                    <i class="fas fa-plus"></i> อัปโหลดไฟล์ใหม่
                </a>
                <button class="theme-toggle" id="themeToggle">
                    <i class="fas fa-moon"></i>
                </button>
            </div>
        </header>

        <!-- Statistics -->
        <div class="stats-container">
            <div class="stat-box">
                <div class="stat-icon">
                    <i class="fas fa-file"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($totalFiles); ?></h3>
                    <p>ไฟล์ทั้งหมด</p>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon">
                    <i class="fas fa-lock"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($base64Files); ?></h3>
                    <p>Base64 Encoded</p>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon">
                    <i class="fas fa-hdd"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($totalSize / (1024 * 1024), 2); ?> MB</h3>
                    <p>พื้นที่ใช้งาน</p>
                </div>
            </div>
        </div>

        <!-- Search & Filter -->
        <div class="toolbar">
            <div class="search-box">
                <form action="files.php" method="get" class="search-form">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="ค้นหาไฟล์..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                    <?php if (!empty($searchTerm)): ?>
                        <a href="files.php" class="clear-search"><i class="fas fa-times"></i></a>
                    <?php endif; ?>
                </form>
            </div>
            
            <div class="filter-box">
                <label><i class="fas fa-sort"></i> เรียงตาม:</label>
                <select id="sortSelect" onchange="window.location.href='files.php?sort=' + this.value + '<?php echo !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : ''; ?>'">
                    <option value="recent" <?php echo $sort === 'recent' ? 'selected' : ''; ?>>ล่าสุด</option>
                    <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>ชื่อ A-Z</option>
                    <option value="size" <?php echo $sort === 'size' ? 'selected' : ''; ?>>ขนาดใหญ่สุด</option>
                    <option value="type" <?php echo $sort === 'type' ? 'selected' : ''; ?>>ประเภท</option>
                </select>
            </div>
        </div>

        <!-- Files List -->
        <div class="files-section">
            <?php if ($result && $result->num_rows > 0): ?>
                <div class="files-grid">
                    <?php while($row = $result->fetch_assoc()): ?>
                        <div class="file-card" data-file-id="<?php echo $row['id']; ?>">
                            <!-- Storage Badge -->
                            <div class="storage-badge <?php echo $row['storage_type']; ?>">
                                <?php if ($row['storage_type'] === 'base64'): ?>
                                    <i class="fas fa-shield-alt"></i> Base64
                                <?php else: ?>
                                    <i class="fas fa-database"></i> Binary
                                <?php endif; ?>
                            </div>

                            <div class="file-icon-preview">
                                <?php
                                // แสดงตัวอย่างรูปภาพถ้าเป็น base64
                                $isImage = strpos($row['file_type'], 'image') !== false;
                                $isPDF = strpos($row['file_type'], 'pdf') !== false;
                                
                                if ($isImage && $row['storage_type'] === 'base64'): ?>
                                    <div class="image-preview" onclick="openDrawingEditor(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['file_name']); ?>')">
                                        <img src="preview.php?id=<?php echo $row['id']; ?>" alt="<?php echo htmlspecialchars($row['file_name']); ?>">
                                        <div class="preview-overlay">
                                            <i class="fas fa-paint-brush"></i>
                                            <p>คลิกเพื่อวาด</p>
                                        </div>
                                    </div>
                                <?php elseif ($isPDF): ?>
                                    <div class="file-icon pdf-icon" onclick="openPDFEditor(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['file_name']); ?>')">
                                        <i class="fas fa-file-pdf"></i>
                                        <span class="preview-text">คลิกเพื่อวาด</span>
                                    </div>
                                <?php else:
                                    // แสดงไอคอนตามประเภทไฟล์
                                    $icon = 'fa-file';
                                    $iconColor = '';
                                    if (strpos($row['file_type'], 'word') !== false) {
                                        $icon = 'fa-file-word';
                                        $iconColor = 'icon-word';
                                    } elseif (strpos($row['file_type'], 'excel') !== false || strpos($row['file_type'], 'sheet') !== false) {
                                        $icon = 'fa-file-excel';
                                        $iconColor = 'icon-excel';
                                    } elseif (strpos($row['file_type'], 'zip') !== false || strpos($row['file_type'], 'rar') !== false) {
                                        $icon = 'fa-file-archive';
                                        $iconColor = 'icon-archive';
                                    } elseif (strpos($row['file_type'], 'video') !== false) {
                                        $icon = 'fa-file-video';
                                        $iconColor = 'icon-video';
                                    } elseif (strpos($row['file_type'], 'audio') !== false) {
                                        $icon = 'fa-file-audio';
                                        $iconColor = 'icon-audio';
                                    }
                                    ?>
                                    <div class="file-icon <?php echo $iconColor; ?>">
                                        <i class="fas <?php echo $icon; ?>"></i>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="file-info">
                                <h3 title="<?php echo htmlspecialchars($row['file_name']); ?>">
                                    <?php echo htmlspecialchars($row['file_name']); ?>
                                </h3>
                                <p class="file-meta">
                                    <span><i class="fas fa-hdd"></i> <?php echo number_format($row['file_size'] / 1024, 2); ?> KB</span>
                                    <span><i class="fas fa-clock"></i> <?php echo date('d/m/Y H:i', strtotime($row['upload_date'])); ?></span>
                                </p>
                                <?php if ($row['description']): ?>
                                    <p class="file-desc"><?php echo htmlspecialchars($row['description']); ?></p>
                                <?php endif; ?>
                            </div>

                            <div class="file-actions">
                                <?php if ($isImage): ?>
                                    <button class="btn-draw" onclick="openDrawingEditor(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['file_name']); ?>')" title="เปิดเครื่องมือวาด">
                                        <i class="fas fa-paint-brush"></i>
                                    </button>
                                <?php elseif ($isPDF): ?>
                                    <button class="btn-draw" onclick="openPDFEditor(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['file_name']); ?>')" title="เปิดเครื่องมือวาด PDF">
                                        <i class="fas fa-paint-brush"></i>
                                    </button>
                                <?php endif; ?>
                                <a href="download.php?id=<?php echo $row['id']; ?>" class="btn-download" title="ดาวน์โหลด">
                                    <i class="fas fa-download"></i>
                                </a>
                                <a href="delete.php?id=<?php echo $row['id']; ?>" class="btn-delete" title="ลบ" onclick="return confirm('คุณต้องการลบไฟล์ <?php echo htmlspecialchars($row['file_name']); ?> หรือไม่?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-folder-open"></i>
                    <h3>ไม่พบไฟล์</h3>
                    <p>
                        <?php if (!empty($searchTerm)): ?>
                            ไม่พบไฟล์ที่ตรงกับคำค้นหา "<?php echo htmlspecialchars($searchTerm); ?>"
                        <?php else: ?>
                            ยังไม่มีไฟล์ที่อัปโหลด
                        <?php endif; ?>
                    </p>
                    <a href="index.php" class="btn-empty-action">
                        <i class="fas fa-plus"></i> อัปโหลดไฟล์แรกของคุณ
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal สำหรับ Drawing Editor -->
    <div id="drawingModal" class="modal">
        <div class="modal-content drawing-modal">
            <div class="drawing-header">
                <h2><i class="fas fa-paint-brush"></i> เครื่องมือวาดภาพ - <span id="currentFileName"></span></h2>
                <button class="modal-close" onclick="closeDrawingModal()">&times;</button>
            </div>
            
            <div class="drawing-container">
                <!-- Toolbar -->
                <div class="drawing-toolbar">
                    <!-- Tools Section -->
                    <div class="toolbar-section">
                        <h4><i class="fas fa-tools"></i> เครื่องมือ</h4>
                        <div class="tool-buttons">
                            <button class="tool-btn active" data-tool="brush" title="พู่กัน">
                                <i class="fas fa-paintbrush"></i>
                                <span>พู่กัน</span>
                            </button>
                            <button class="tool-btn" data-tool="pencil" title="ดินสอ">
                                <i class="fas fa-pencil-alt"></i>
                                <span>ดินสอ</span>
                            </button>
                            <button class="tool-btn" data-tool="marker" title="มาร์กเกอร์">
                                <i class="fas fa-highlighter"></i>
                                <span>มาร์กเกอร์</span>
                            </button>
                            <button class="tool-btn" data-tool="eraser" title="ยางลบ">
                                <i class="fas fa-eraser"></i>
                                <span>ยางลบ</span>
                            </button>
                            <button class="tool-btn" data-tool="line" title="เส้นตรง">
                                <i class="fas fa-minus"></i>
                                <span>เส้นตรง</span>
                            </button>
                            <button class="tool-btn" data-tool="rectangle" title="สี่เหลี่ยม">
                                <i class="far fa-square"></i>
                                <span>สี่เหลี่ยม</span>
                            </button>
                            <button class="tool-btn" data-tool="circle" title="วงกลม">
                                <i class="far fa-circle"></i>
                                <span>วงกลม</span>
                            </button>
                            <button class="tool-btn" data-tool="arrow" title="ลูกศร">
                                <i class="fas fa-arrow-right"></i>
                                <span>ลูกศร</span>
                            </button>
                            <button class="tool-btn" data-tool="text" title="ข้อความ">
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
                                <button class="color-preset" style="background: #000000;" data-color="#000000"></button>
                                <button class="color-preset" style="background: #ffffff; border: 1px solid #ccc;" data-color="#ffffff"></button>
                                <button class="color-preset active" style="background: #ff6b35;" data-color="#ff6b35"></button>
                                <button class="color-preset" style="background: #f7931e;" data-color="#f7931e"></button>
                                <button class="color-preset" style="background: #e74c3c;" data-color="#e74c3c"></button>
                                <button class="color-preset" style="background: #3498db;" data-color="#3498db"></button>
                                <button class="color-preset" style="background: #2ecc71;" data-color="#2ecc71"></button>
                                <button class="color-preset" style="background: #9b59b6;" data-color="#9b59b6"></button>
                                <button class="color-preset" style="background: #f39c12;" data-color="#f39c12"></button>
                                <button class="color-preset" style="background: #1abc9c;" data-color="#1abc9c"></button>
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

                    <!-- Zoom Controls Section -->
                    <div class="toolbar-section">
                        <h4><i class="fas fa-search-plus"></i> ซูม</h4>
                        <div class="zoom-controls">
                            <button class="zoom-btn" id="zoomOutBtn" title="ซูมออก (Ctrl + -)">
                                <i class="fas fa-search-minus"></i>
                            </button>
                            <div class="zoom-display" id="zoomDisplay">100%</div>
                            <button class="zoom-btn" id="zoomInBtn" title="ซูมเข้า (Ctrl + +)">
                                <i class="fas fa-search-plus"></i>
                            </button>
                        </div>
                        <div class="zoom-slider-container">
                            <input type="range" id="zoomSlider" min="25" max="300" value="100" step="25">
                        </div>
                        <div class="zoom-actions">
                            <button class="zoom-action-btn" id="zoomFitBtn" title="พอดีหน้าจอ">
                                <i class="fas fa-expand"></i> พอดี
                            </button>
                            <button class="zoom-action-btn" id="zoomResetBtn" title="รีเซ็ต 100%">
                                <i class="fas fa-undo"></i> รีเซ็ต
                            </button>
                        </div>
                        <div class="zoom-info">
                            <small><i class="fas fa-mouse"></i> ใช้ Mouse Wheel ซูมได้</small>
                        </div>
                    </div>

                    <!-- Canvas Info Section -->
                    <div class="toolbar-section">
                        <h4><i class="fas fa-info-circle"></i> ข้อมูลรูปภาพ</h4>
                        <div class="canvas-info" id="canvasInfo">
                            <i class="fas fa-spinner fa-spin"></i> กำลังโหลด...
                        </div>
                    </div>

                    <!-- Padding Control Section (เพิ่ม id) -->
                    <div class="toolbar-section" id="paddingControlSection">
                        <h4><i class="fas fa-expand-arrows-alt"></i> เพิ่มพื้นที่รอบๆ</h4>
                        
                        <!-- Padding Display Card -->
                        <div class="padding-card">
                            <div class="padding-icon-wrapper">
                                <i class="fas fa-border-all"></i>
                            </div>
                            <div class="padding-info-text">
                                <span class="padding-label">พื้นที่ปัจจุบัน</span>
                                <span class="padding-value" id="paddingSizeDisplay">50px</span>
                            </div>
                        </div>
                        
                        <!-- Increase Padding Button -->
                        <button class="btn-increase-padding" id="increasePaddingBtn" title="กดเพื่อเพิ่มพื้นที่ 100px รอบๆ รูป">
                            <span class="btn-icon">
                                <i class="fas fa-plus-circle"></i>
                            </span>
                            <span class="btn-text">
                                <strong>เพิ่มพื้นที่</strong>
                                <small>+50px รอบๆ</small>
                            </span>
                            <span class="btn-arrow">
                                <i class="fas fa-chevron-right"></i>
                            </span>
                        </button>
                        
                        <!-- Info Tip -->
                        <div class="padding-tip">
                            <i class="fas fa-lightbulb"></i>
                            <span>กดปุ่มเพื่อเพิ่มพื้นที่วาดรอบรูป</span>
                        </div>
                    </div>

                    <!-- Actions Section (รวมปุ่มทั้งหมด) -->
                    <div class="toolbar-section">
                        <h4><i class="fas fa-magic"></i> การดำเนินการ</h4>
                        <div class="action-buttons">
                            <button class="action-btn" id="undoBtn" title="ย้อนกลับ">
                                <i class="fas fa-undo"></i> Undo
                            </button>
                            <button class="action-btn" id="redoBtn" title="ทำซ้ำ">
                                <i class="fas fa-redo"></i> Redo
                            </button>
                            <button class="action-btn" id="clearBtn" title="ล้างทั้งหมด">
                                <i class="fas fa-trash"></i> ล้าง
                            </button>
                            
                            <!-- ปุ่มบันทึกและยกเลิก -->
                            <button class="action-btn btn-save-main" id="saveDrawingBtn" title="บันทึกรูปภาพ">
                                <i class="fas fa-save"></i> บันทึก
                            </button>
                            <button class="action-btn btn-cancel-main" onclick="closeDrawingModal()" title="ยกเลิก">
                                <i class="fas fa-times"></i> ยกเลิก
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Canvas Area -->
                <div class="canvas-area">
                    <div class="canvas-wrapper" id="canvasWrapper">
                        <canvas id="drawingCanvas"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

        <script src="script.js"></script>
        <script src="files.js"></script>
        <script src="drawing-editor.js"></script>
        <script src="pdf-editor.js"></script>
        <script src="zoom-controls.js"></script>
</body>
</html>

<?php
$conn->close();
?>