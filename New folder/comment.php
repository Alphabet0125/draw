<?php

// รับ file_id และ file_name จาก URL
$fileId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$fileName = isset($_GET['name']) ? $_GET['name'] : 'ไฟล์';
$fileType = isset($_GET['type']) ? $_GET['type'] : 'image';

if ($fileId <= 0) {
    die('ไม่พบไฟล์');
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไขไฟล์ - <?php echo htmlspecialchars($fileName); ?></title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="drawing-tools.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- PDF.js Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script>
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    </script>
    
    <style>
        /* Full Page Editor Style */
        body {
            margin: 0;
            padding: 0;
            overflow: hidden;
            background: var(--bg-color);
        }
        
        .editor-page {
            width: 100vw;
            height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .editor-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 30px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            z-index: 100;
            flex-shrink: 0;
        }
        
        .editor-header h1 {
            margin: 0;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .editor-header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-back {
            padding: 10px 20px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            text-decoration: none;
        }
        
        .btn-back:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }
        
        .editor-container {
            display: flex;
            flex: 1;
            overflow: hidden;
            min-height: 0;
        }
        
        .drawing-toolbar {
            width: 280px;
            background: var(--card-bg);
            border-right: 2px solid var(--border-color);
            overflow-y: auto;
            padding: 15px;
            flex-shrink: 0;
        }
        
        .canvas-area {
            flex: 1;
            background: #e0e0e0;
            background-image: 
                linear-gradient(45deg, #d0d0d0 25%, transparent 25%),
                linear-gradient(-45deg, #d0d0d0 25%, transparent 25%),
                linear-gradient(45deg, transparent 75%, #d0d0d0 75%),
                linear-gradient(-45deg, transparent 75%, #d0d0d0 75%);
            background-size: 20px 20px;
            background-position: 0 0, 0 10px, 10px -10px, -10px 0px;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            overflow: auto;
            padding: 80px 30px 30px 30px;
        }
        
        [data-theme="dark"] .canvas-area {
            background: #2a2a2a;
            background-image: 
                linear-gradient(45deg, #3a3a3a 25%, transparent 25%),
                linear-gradient(-45deg, #3a3a3a 25%, transparent 25%),
                linear-gradient(45deg, transparent 75%, #3a3a3a 75%),
                linear-gradient(-45deg, transparent 75%, #3a3a3a 75%);
            background-size: 20px 20px;
            background-position: 0 0, 0 10px, 10px -10px, -10px 0px;
        }
        
        .canvas-wrapper {
            background: white;
            box-shadow: 
                0 10px 40px rgba(0, 0, 0, 0.3),
                inset 0 0 0 1px rgba(0, 0, 0, 0.05);
            border-radius: 8px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            max-width: calc(100% - 60px);
            max-height: calc(100% - 110px);
            margin-top: 10px;
        }
        
        .canvas-wrapper::before {
            content: '';
            position: absolute;
            top: -4px;
            left: -4px;
            right: -4px;
            bottom: -4px;
            pointer-events: none;
            border: 4px solid rgba(255, 107, 53, 0.4);
            border-radius: 12px;
            box-shadow: 0 0 20px rgba(255, 107, 53, 0.2);
        }
        
        #drawingCanvas {
            display: block;
            border-radius: 4px;
            max-width: 100%;
            max-height: 100%;
            height: auto;
            width: auto;
            background: white;
            box-shadow: 
                0 4px 12px rgba(0, 0, 0, 0.15),
                0 0 0 1px rgba(0, 0, 0, 0.05);
            margin: 0 auto;
            transition: transform 0.3s ease;
        }
        
        @media (max-width: 992px) {
            .editor-container {
                flex-direction: column;
            }
            
            .drawing-toolbar {
                width: 100%;
                max-height: 250px;
                border-right: none;
                border-bottom: 2px solid var(--border-color);
                display: flex;
                flex-direction: row;
                overflow-x: auto;
                overflow-y: hidden;
            }
            
            .toolbar-section {
                min-width: 200px;
                margin-right: 15px;
                border-right: 1px solid var(--border-color);
                padding-right: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="editor-page">
        <!-- Header -->
        <div class="editor-header">
            <h1>
                <i class="fas fa-paint-brush"></i> 
                แก้ไขไฟล์ - <span id="currentFileName"><?php echo htmlspecialchars($fileName); ?></span>
            </h1>
            <div class="editor-header-actions">
                <a href="files.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i> กลับ
                </a>
            </div>
        </div>
        
        <!-- Editor Container -->
        <div class="editor-container">
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

                <!-- Padding Control Section -->
                <div class="toolbar-section" id="paddingControlSection">
                    <h4><i class="fas fa-expand-arrows-alt"></i> เพิ่มพื้นที่รอบๆ</h4>
                    
                    <div class="padding-card">
                        <div class="padding-icon-wrapper">
                            <i class="fas fa-border-all"></i>
                        </div>
                        <div class="padding-info-text">
                            <span class="padding-label">พื้นที่ปัจจุบัน</span>
                            <span class="padding-value" id="paddingSizeDisplay">50px</span>
                        </div>
                    </div>
                    
                    <button class="btn-increase-padding" id="increasePaddingBtn" title="กดเพื่อเพิ่มพื้นที่ 100px รอบๆ รูป">
                        <span class="btn-icon">
                            <i class="fas fa-plus-circle"></i>
                        </span>
                        <span class="btn-text">
                            <strong>เพิ่มพื้นที่</strong>
                            <small>+100px รอบๆ</small>
                        </span>
                        <span class="btn-arrow">
                            <i class="fas fa-chevron-right"></i>
                        </span>
                    </button>
                    
                    <div class="padding-tip">
                        <i class="fas fa-lightbulb"></i>
                        <span>กดปุ่มเพื่อเพิ่มพื้นที่วาดรอบรูป</span>
                    </div>
                </div>

                <!-- Actions Section -->
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
                        
                        <button class="action-btn btn-save-main" id="saveDrawingBtn" title="บันทึกรูปภาพ">
                            <i class="fas fa-save"></i> บันทึก
                        </button>
                        <button class="action-btn btn-cancel-main" onclick="window.location.href='files.php'" title="ยกเลิก">
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

    <script src="script.js"></script>
    <script src="drawing-editor.js"></script>
    <script src="pdf-editor.js"></script>
    <script src="zoom-controls.js"></script>
    
    <script>
        // Auto load file when page loads
        document.addEventListener('DOMContentLoaded', function() {
            const fileId = <?php echo $fileId; ?>;
            const fileName = '<?php echo addslashes($fileName); ?>';
            const fileType = '<?php echo $fileType; ?>';
            
            // Set current file info
            currentFileId = fileId;
            currentFileName = fileName;
            
            // Load file based on type
            if (fileType === 'pdf') {
                // Hide padding control for PDF
                const paddingSection = document.getElementById('paddingControlSection');
                if (paddingSection) paddingSection.style.display = 'none';
                
                // Load PDF
                setTimeout(() => {
                    loadPDFDocument(fileId);
                }, 100);
            } else {
                // Show padding control for images
                const paddingSection = document.getElementById('paddingControlSection');
                if (paddingSection) paddingSection.style.display = 'block';
                
                // Load image
                setTimeout(() => {
                    initializeCanvas(fileId);
                }, 100);
            }
        });
    </script>
</body>
</html>