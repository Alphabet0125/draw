<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>อัปโหลดไฟล์ - Orange Theme</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container">
   
        <!-- Header พร้อม Theme Toggle -->
        <header>
            <h1><i class="fas fa-cloud-upload-alt"></i> ระบบอัปโหลดไฟล์</h1>
            <div class="header-actions">
                <a href="files.php" class="btn-view-files">
                    <i class="fas fa-folder-open"></i> ดูไฟล์ทั้งหมด
                </a>
                <button class="theme-toggle" id="themeToggle">
                    <i class="fas fa-moon"></i>
                </button>
            </div>
        </header>

        <!-- Upload Form -->
        <div class="upload-section">
            <div class="upload-header">
                <h2><i class="fas fa-upload"></i> อัปโหลดไฟล์ของคุณ</h2>
                <p class="upload-subtitle">รองรับไฟล์ทุกประเภท ขนาดสูงสุด 50MB</p>
            </div>

            <form action="upload.php" method="post" enctype="multipart/form-data" id="uploadForm">
                <!-- Drag & Drop Zone -->
                <div class="drop-zone" id="dropZone">
                    <div class="drop-zone-icon">
                        <i class="fas fa-cloud-upload-alt"></i>
                    </div>
                    <h3>ลากและวางไฟล์ที่นี่</h3>
                    <p>หรือ</p>
                    <label for="fileToUpload" class="btn-select-file">
                        <i class="fas fa-folder-open"></i> เลือกไฟล์
                    </label>
                    <input type="file" name="fileToUpload" id="fileToUpload" required>
                    <div class="selected-file" id="selectedFile"></div>
                </div>

                <!-- Description -->
                <div class="form-group">
                    <label for="description">
                        <i class="fas fa-comment-dots"></i> คำอธิบาย (ไม่บังคับ)
                    </label>
                    <textarea name="description" id="description" rows="4" placeholder="เพิ่มคำอธิบายหรือรายละเอียดเกี่ยวกับไฟล์นี้..."></textarea>
                </div>

                <!-- Upload Progress -->
                <div class="upload-progress" id="uploadProgress" style="display: none;">
                    <div class="progress-bar">
                        <div class="progress-fill" id="progressFill"></div>
                    </div>
                    <p class="progress-text" id="progressText">0%</p>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn-upload">
                    <i class="fas fa-upload"></i> อัปโหลดไฟล์เลย
                </button>
            </form>

            <!-- Upload Stats -->
            <div class="upload-stats">
                <div class="stat-item">
                    <i class="fas fa-file"></i>
                    <div>
                        <strong>รองรับทุกไฟล์</strong>
                        <span>PDF, รูปภาพ, เอกสาร, วิดีโอ</span>
                    </div>
                </div>
                <div class="stat-item">
                    <i class="fas fa-lock"></i>
                    <div>
                        <strong>ปลอดภัย 100%</strong>
                        <span>เข้ารหัสและจัดเก็บอย่างปลอดภัย</span>
                    </div>
                </div>
                <div class="stat-item">
                    <i class="fas fa-rocket"></i>
                    <div>
                        <strong>รวดเร็ว</strong>
                        <span>อัปโหลดไวภายในไม่กี่วินาที</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Access -->
        <div class="quick-access">
            <h3><i class="fas fa-history"></i> การดำเนินการ</h3>
            <div class="action-cards">
                <a href="files.php" class="action-card">
                    <i class="fas fa-folder"></i>
                    <h4>ไฟล์ทั้งหมด</h4>
                    <p>ดูและจัดการไฟล์</p>
                </a>
                <a href="files.php?sort=recent" class="action-card">
                    <i class="fas fa-clock"></i>
                    <h4>ไฟล์ล่าสุด</h4>
                    <p>ไฟล์ที่เพิ่งอัปโหลด</p>
                </a>
                <a href="#uploadForm" class="action-card">
                    <i class="fas fa-plus-circle"></i>
                    <h4>อัปโหลดเพิ่ม</h4>
                    <p>เพิ่มไฟล์ใหม่</p>
                </a>
            </div>
        </div>
    </div><script>
const params = new URLSearchParams(window.location.search);

const CURRENT_USER = {
  name: params.get("name"),
  email: params.get("email"),
  department: params.get("department"),
  jobTitle: params.get("jobTitle"),
  manager: params.get("manager")
};

console.log(CURRENT_USER);
console.log(CURRENT_USER.name);
console.log(CURRENT_USER.jobTitle);
</script>


    <script src="script.js"></script>
</body>
</html>