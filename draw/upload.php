<?php
require_once '../config.php';
requireLogin();

// ★ โหลด avatar
require_once '../get_avatar.php';
require_once 'log_helper.php';

$displayName = htmlspecialchars($_SESSION['display_name'] ?? 'User');
$email       = htmlspecialchars($_SESSION['email'] ?? '');
$initial     = mb_substr($displayName, 0, 1);
$userId      = $_SESSION['user_id'];

$maxFileSize    = 500 * 1024 * 1024;
$allowedTypes   = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$allowedExts    = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp'];
$alertMessage   = '';
$alertType      = '';

// ★ Auto-migrate: เพิ่ม column file_path ถ้ายังไม่มีใน DB
try {
    $pdo->exec("ALTER TABLE uploads ADD COLUMN file_path VARCHAR(255) NOT NULL DEFAULT '' AFTER file_size");
} catch (PDOException $alterEx) { /* column มีแล้ว */ }

// ===== HANDLE UPLOAD =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['upload_file'])) {
    $file        = $_FILES['upload_file'];
    $description = trim($_POST['description'] ?? '');

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $alertMessage = '❌ เกิดข้อผิดพลาดในการอัปโหลด (Error code: ' . $file['error'] . ')';
        $alertType = 'error';
    }
    elseif ($file['size'] > $maxFileSize) {
        $alertMessage = '❌ ไฟล์มีขนาดเกิน 500MB';
        $alertType = 'error';
    }
    elseif (!in_array($file['type'], $allowedTypes)) {
        $alertMessage = '❌ ประเภทไฟล์ไม่อนุญาต (รับเฉพาะ PDF, JPG, PNG, GIF, WEBP)';
        $alertType = 'error';
    }
    elseif (!in_array(strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)), $allowedExts)) {
        $alertMessage = '❌ นามสกุลไฟล์ไม่อนุญาต';
        $alertType = 'error';
    }
    else {
        $fileName    = $file['name'];
        $fileMime    = $file['type'];
        $fileSize    = $file['size'];
        $ext         = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $fileType    = ($ext === 'pdf') ? 'pdf' : 'image';

        // ★ บันทึกไฟล์ลง disk แทน DB
        $uploadDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'fileuploads' . DIRECTORY_SEPARATOR;
        if (!is_dir($uploadDir)) { mkdir($uploadDir, 0755, true); }
        $uniqueName = uniqid('f_', true) . '.' . $ext;
        $destPath   = $uploadDir . $uniqueName;

        set_time_limit(300); // เผื่อเวลาสำหรับไฟล์ใหญ่

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            $alertMessage = '❌ บันทึกไฟล์ลง server ไม่สำเร็จ';
            $alertType = 'error';
        } else {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO uploads (user_id, file_name, file_type, file_mime, file_size, file_path, file_data, description)
                 VALUES (:uid, :fname, :ftype, :fmime, :fsize, :fpath, '', :desc)"
            );
            $stmt->execute([
                ':uid'   => $userId,
                ':fname' => $fileName,
                ':ftype' => $fileType,
                ':fmime' => $fileMime,
                ':fsize' => $fileSize,
                ':fpath' => $uniqueName,
                ':desc'  => $description,
            ]);

            $alertMessage = '✅ อัปโหลดไฟล์ "' . htmlspecialchars($fileName) . '" สำเร็จ!';
            $alertType = 'success';

            // ★ Log: อัปโหลดไฟล์
            $newFileId = $pdo->lastInsertId();
            logActivity($pdo, 'upload', 'อัปโหลดไฟล์ "' . $fileName . '" (' . round($fileSize/1024, 1) . ' KB)', $newFileId, $fileName);

            // ★ บันทึกผู้ตรวจงาน
            $reviewerIds = isset($_POST['reviewer_ids']) ? $_POST['reviewer_ids'] : [];
            if (!empty($reviewerIds)) {
                $insReviewer = $pdo->prepare(
                    "INSERT INTO file_reviewers (upload_id, user_id, assigned_by) VALUES (:uid, :rid, :aid)"
                );
                foreach ($reviewerIds as $rid) {
                    $rid = intval($rid);
                    if ($rid > 0) {
                        try {
                            $insReviewer->execute([':uid' => $newFileId, ':rid' => $rid, ':aid' => $userId]);
                        } catch (PDOException $e2) { /* skip duplicates */ }
                    }
                }
                $alertMessage .= ' (มอบหมายผู้ตรวจ ' . count($reviewerIds) . ' คน)';
            }

        } catch (PDOException $e) {
            error_log("Upload DB error: " . $e->getMessage());
            @unlink($destPath); // ลบไฟล์ถ้า DB ผิดพลาด
            $alertMessage = '❌ บันทึกไฟล์ลงฐานข้อมูลไม่สำเร็จ';
            $alertType = 'error';
        }
        } // end move_uploaded_file success
    }
}

// นับจำนวนไฟล์ที่อัปโหลด
$countStmt = $pdo->query("SELECT COUNT(*) FROM uploads");
$fileCount = $countStmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="th" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Files - Lolane Portal</title>

    <link rel="icon" type="image/jpeg" href="../img/logo.jpg">
    <link rel="shortcut icon" type="image/jpeg" href="../img/logo.jpg">
    <link rel="apple-touch-icon" href="../img/logo.jpg">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="../css/upload.css">

    <style>
        /* ★★★ REVIEWER PICKER ★★★ */
        .reviewer-section { margin-top: 4px; }
        .reviewer-section > label { display: block; margin-bottom: 8px; font-weight: 500; font-size: 14px; }
        .reviewer-hint { font-weight: 300; font-size: 12px; color: var(--text-muted); }
        .reviewer-picker {
            display: flex; flex-wrap: wrap; align-items: center; gap: 6px;
            padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 12px;
            background: var(--bg-primary); min-height: 46px; cursor: text;
            transition: border-color 0.2s;
        }
        .reviewer-picker:focus-within { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(255,107,53,0.1); }
        .reviewer-search-wrap { flex: 1; min-width: 120px; }
        .reviewer-search {
            width: 100%; border: none; background: transparent; outline: none;
            font-family: 'Kanit', sans-serif; font-size: 13px; color: var(--text-primary);
            padding: 4px 0;
        }
        .reviewer-search::placeholder { color: var(--text-muted); font-weight: 300; }
        .reviewer-tag {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 4px 10px 4px 6px; border-radius: 20px;
            background: var(--accent-light); border: 1px solid var(--border-color);
            font-size: 12px; font-weight: 400; animation: tagIn 0.2s ease;
        }
        @keyframes tagIn { from { opacity:0; transform:scale(0.8); } to { opacity:1; transform:scale(1); } }
        .reviewer-tag-avatar {
            width: 22px; height: 22px; border-radius: 50%;
            background: var(--accent); color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-size: 10px; font-weight: 600; flex-shrink: 0;
        }
        .reviewer-tag-name { max-width: 120px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .reviewer-tag-remove {
            width: 16px; height: 16px; border-radius: 50%; background: none;
            border: none; color: var(--text-muted); cursor: pointer;
            font-size: 14px; line-height: 1; display: flex; align-items: center;
            justify-content: center; transition: all 0.15s;
        }
        .reviewer-tag-remove:hover { background: #e74c3c; color: #fff; }
        .reviewer-dropdown {
            display: none; position: relative; margin-top: 4px;
            background: var(--bg-card); border: 1px solid var(--border-color);
            border-radius: 12px; box-shadow: var(--shadow-lg); max-height: 220px;
            overflow-y: auto; z-index: 100;
        }
        .reviewer-dropdown.open { display: block; }
        .reviewer-dropdown-item {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 14px; cursor: pointer; transition: background 0.12s;
        }
        .reviewer-dropdown-item:hover { background: var(--bg-hover); }
        .reviewer-dropdown-item.selected { background: var(--accent-light); }
        .rdi-avatar {
            width: 32px; height: 32px; border-radius: 50%;
            background: var(--accent-light); border: 1px solid var(--border-color);
            display: flex; align-items: center; justify-content: center;
            font-size: 13px; font-weight: 600; color: var(--accent); flex-shrink: 0;
        }
        .rdi-info { flex: 1; min-width: 0; }
        .rdi-name { font-size: 13px; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .rdi-meta { font-size: 11px; color: var(--text-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .rdi-check { font-size: 16px; color: var(--accent); opacity: 0; transition: opacity 0.15s; flex-shrink: 0; }
        .reviewer-dropdown-item.selected .rdi-check { opacity: 1; }
        .reviewer-dropdown-empty {
            padding: 20px; text-align: center; color: var(--text-muted); font-size: 13px;
        }

        /* ★ Avatar ใน Navbar ★ */
        .profile-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: rgba(255,255,255,0.25);
            border: 2px solid rgba(255,255,255,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
            overflow: hidden;
            flex-shrink: 0;
        }
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
    </style>
</head>
<body>

    <div class="dropdown-overlay" id="dropdownOverlay"></div>

    <!-- NAVBAR -->
    <nav class="navbar">
        <div class="nav-left">
            <a href="../index.php" class="nav-logo">
                <img src="../img/logo.jpg" alt="Lolane" class="nav-logo-img">
                Lolane Portal
            </a>
            <div class="nav-links">
                <a href="../index.php" class="nav-link">🏠 หน้าหลัก</a>
                <a href="upload.php" class="nav-link">📁 อัปโหลด</a>
                <?php if (in_array(trim($_SESSION['department'] ?? ''), ['IT','HR'], true)): ?>
                <a href="logdraw.php" class="nav-link active">📜 ประวัติการใช้งาน</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="nav-right">
            <label class="theme-toggle" title="สลับธีม">
                <input type="checkbox" id="themeToggle">
                <span class="toggle-slider"></span>
            </label>

            <div class="profile-wrapper" id="profileWrapper">
                <button class="profile-trigger" id="profileTrigger">
                    <!-- ★ แสดงรูปโปรไฟล์ ★ -->
                    <div class="profile-avatar">
                        <?php if ($userAvatar): ?>
                            <img src="data:image/jpeg;base64,<?= $userAvatar ?>" alt="avatar">
                        <?php else: ?>
                            <?= $initial ?>
                        <?php endif; ?>
                    </div>
                    <div class="profile-info">
                        <div class="profile-name"><?= $displayName ?></div>
                        <div class="profile-email"><?= $email ?></div>
                    </div>
                    <span class="profile-arrow">▼</span>
                </button>

                <div class="profile-dropdown">
                    <div class="dropdown-menu">
                        <a href="../profile.php" class="dropdown-item">
                            <span class="icon">👤</span> โปรไฟล์ของฉัน
                        </a>
                        <button class="dropdown-item" onclick="toggleThemeFromMenu()">
                            <span class="icon" id="themeMenuIcon">🌙</span>
                            <span id="themeMenuText">Dark Mode</span>
                        </button>
                        <div class="dropdown-divider"></div>
                        <a href="../logout.php" class="dropdown-item danger">
                            <span class="icon">🚪</span> ออกจากระบบ
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- CONTENT -->
    <div class="container">

        <div class="page-header">
            <h2>📁 อัปโหลดไฟล์</h2>
            <p>อัปโหลดไฟล์ PDF และรูปภาพ (JPG, PNG, GIF, WEBP) ขนาดไม่เกิน 500MB</p>
        </div>

        <?php if ($alertMessage): ?>
            <div class="alert alert-<?= $alertType ?>">
                <?= $alertMessage ?>
            </div>
        <?php endif; ?>

        <!-- Upload Form -->
        <div class="upload-card">
            <form id="uploadForm" method="POST" enctype="multipart/form-data">

                <div class="drop-zone" id="dropZone">
                    <span class="drop-icon">📂</span>
                    <h3>ลากไฟล์มาวางที่นี่ หรือ คลิกเพื่อเลือกไฟล์</h3>
                    <p>รองรับ PDF, JPG, PNG, GIF, WEBP (ไม่เกิน 500MB)</p>
                    <input type="file" name="upload_file" id="fileInput"
                           accept=".pdf,.jpg,.jpeg,.png,.gif,.webp">
                </div>

                <div class="form-group">
                    <label for="description">📝 คำอธิบายไฟล์ (ไม่จำเป็น)</label>
                    <input type="text" name="description" id="description"
                           placeholder="เช่น ใบเสร็จ, รูปสินค้า, เอกสาร..."
                           maxlength="500">
                </div>

                <!-- ★★★ เลือกผู้ตรวจงาน ★★★ -->
                <div class="form-group reviewer-section">
                    <label>👥 ผู้ตรวจงาน <span class="reviewer-hint">(เลือกได้หลายคน - ผู้ตรวจจะสามารถเปลี่ยนสถานะและใช้งานไฟล์ได้)</span></label>
                    <div class="reviewer-picker" id="reviewerPicker">
                        <div class="reviewer-selected" id="reviewerSelected"></div>
                        <div class="reviewer-search-wrap">
                            <input type="text" class="reviewer-search" id="reviewerSearch"
                                   placeholder="🔍 พิมพ์ชื่อหรืออีเมลเพื่อค้นหา..." autocomplete="off">
                        </div>
                    </div>
                    <div class="reviewer-dropdown" id="reviewerDropdown"></div>
                    <div id="reviewerHiddenInputs"></div>
                </div>

                <div class="preview-area" id="previewArea">
                    <div class="preview-header">
                        <h3>👁️ ตัวอย่างไฟล์</h3>
                        <button type="button" class="btn-clear" onclick="clearFile()">✕ ล้าง</button>
                    </div>
                    <div class="preview-file-info" id="fileInfo"></div>
                    <div id="previewContent"></div>
                </div>

                <div class="upload-actions">
                    <button type="submit" class="btn-upload" id="btnUpload">
                        ☁️ อัปโหลด
                    </button>
                </div>
                <div id="uploadProgress" style="display:none;margin-top:10px;font-size:13px;color:var(--text-muted);"></div>
            </form>
        </div>

        <!-- ★ ปุ่มไปหน้าไฟล์ที่อัปโหลดแล้ว ★ -->
        <a href="select_file.php" class="btn-view-files">
            <span class="btn-view-files-icon">📋</span>
            <div class="btn-view-files-info">
                <span class="btn-view-files-title">ไฟล์ที่อัปโหลดแล้ว</span>
                <span class="btn-view-files-count"><?= $fileCount ?> ไฟล์</span>
            </div>
            <span class="btn-view-files-arrow">→</span>
        </a>

    </div>

    <div class="footer">
        © 2026 Lolane Co., Ltd. | Powered by <span class="footer-brand">ALPHABET</span>
    </div>

    <script>
    // File Preview
    const fileInput      = document.getElementById('fileInput');
    const dropZone       = document.getElementById('dropZone');
    const previewArea    = document.getElementById('previewArea');
    const previewContent = document.getElementById('previewContent');
    const fileInfo       = document.getElementById('fileInfo');
    const btnUpload      = document.getElementById('btnUpload');
    const uploadForm     = document.getElementById('uploadForm');
    const uploadProgress = document.getElementById('uploadProgress');

    const CHUNK_SIZE = 10 * 1024 * 1024; // 10MB per chunk
    let isUploading = false;

    fileInput.addEventListener('change', handleFile);

    dropZone.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.classList.add('drag-over'); });
    dropZone.addEventListener('dragleave', () => { dropZone.classList.remove('drag-over'); });
    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('drag-over');
        if (e.dataTransfer.files.length > 0) {
            fileInput.files = e.dataTransfer.files;
            handleFile();
        }
    });

    function handleFile() {
        const file = fileInput.files[0];
        if (!file) return;
        const maxSize = 500 * 1024 * 1024;
        const allowed = ['application/pdf','image/jpeg','image/png','image/gif','image/webp'];
        if (!allowed.includes(file.type)) { alert('❌ ประเภทไฟล์ไม่อนุญาต'); clearFile(); return; }
        if (file.size > maxSize) { alert('❌ ไฟล์เกิน 500MB'); clearFile(); return; }

        const isPDF = file.type === 'application/pdf';
        fileInfo.innerHTML = `
            <span class="preview-file-icon">${isPDF ? '📄' : '🖼️'}</span>
            <div class="preview-file-details">
                <div class="preview-file-name">${escapeHtml(file.name)}</div>
                <div class="preview-file-meta">${formatSize(file.size)} · ${file.type}</div>
            </div>`;

        const reader = new FileReader();
        reader.onload = (e) => {
            previewContent.innerHTML = isPDF
                ? `<div class="preview-pdf-wrapper"><iframe src="${e.target.result}"></iframe></div>`
                : `<div class="preview-image-wrapper"><img src="${e.target.result}" alt="Preview"></div>`;
        };
        reader.readAsDataURL(file);
        previewArea.classList.add('active');
        btnUpload.classList.add('active');
    }

    function clearFile() {
        fileInput.value = '';
        previewArea.classList.remove('active');
        btnUpload.classList.remove('active');
        previewContent.innerHTML = '';
        fileInfo.innerHTML = '';
        uploadProgress.style.display = 'none';
        uploadProgress.textContent = '';
    }

    function formatSize(bytes) {
        if (bytes === 0) return '0 B';
        const k = 1024, s = ['B','KB','MB','GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + s[i];
    }
    function escapeHtml(t) { const d = document.createElement('div'); d.textContent = t; return d.innerHTML; }

    function setUploadProgress(message) {
        uploadProgress.style.display = 'block';
        uploadProgress.textContent = message;
    }

    function setUploadingState(uploading) {
        isUploading = uploading;
        btnUpload.disabled = uploading;
        btnUpload.style.opacity = uploading ? '0.7' : '';
        btnUpload.textContent = uploading ? '⏳ กำลังอัปโหลด...' : '☁️ อัปโหลด';
    }

    async function parseApiResponse(resp, stepName) {
        const rawText = await resp.text();
        if (!rawText || rawText.trim() === '') {
            throw new Error(stepName + ' ไม่สำเร็จ: เซิร์ฟเวอร์ตอบกลับว่างเปล่า (HTTP ' + resp.status + ')');
        }

        let data;
        try {
            data = JSON.parse(rawText);
        } catch (e) {
            const snippet = rawText.replace(/\s+/g, ' ').trim().slice(0, 180);
            throw new Error(stepName + ' ไม่สำเร็จ: เซิร์ฟเวอร์ตอบกลับไม่ใช่ JSON (HTTP ' + resp.status + ') ' + snippet);
        }

        if (!resp.ok || !data.success) {
            throw new Error(data.message || (stepName + ' ไม่สำเร็จ (HTTP ' + resp.status + ')'));
        }

        return data;
    }

    async function uploadFileInChunks(file) {
        const totalChunks = Math.ceil(file.size / CHUNK_SIZE);
        const uploadId = Date.now().toString() + '_' + Math.random().toString(36).slice(2, 10);

        for (let index = 0; index < totalChunks; index++) {
            const start = index * CHUNK_SIZE;
            const end = Math.min(start + CHUNK_SIZE, file.size);
            const chunkBlob = file.slice(start, end);

            const chunkForm = new FormData();
            chunkForm.append('action', 'upload_chunk');
            chunkForm.append('upload_id', uploadId);
            chunkForm.append('chunk_index', String(index));
            chunkForm.append('total_chunks', String(totalChunks));
            chunkForm.append('chunk', chunkBlob, file.name + '.part' + index);

            const chunkResp = await fetch('chunk_upload.php', {
                method: 'POST',
                body: chunkForm,
                credentials: 'same-origin'
            });
            await parseApiResponse(chunkResp, 'อัปโหลด chunk #' + (index + 1));

            const percent = Math.round(((index + 1) / totalChunks) * 100);
            setUploadProgress('กำลังอัปโหลด: ' + (index + 1) + '/' + totalChunks + ' (' + percent + '%)');
        }

        const finalizeForm = new FormData();
        finalizeForm.append('action', 'finalize_upload');
        finalizeForm.append('upload_id', uploadId);
        finalizeForm.append('total_chunks', String(totalChunks));
        finalizeForm.append('file_name', file.name);
        finalizeForm.append('file_mime', file.type || 'application/octet-stream');
        finalizeForm.append('file_size', String(file.size));
        finalizeForm.append('description', document.getElementById('description').value || '');

        document.querySelectorAll('input[name="reviewer_ids[]"]').forEach((input) => {
            finalizeForm.append('reviewer_ids[]', input.value);
        });

        setUploadProgress('กำลังประกอบไฟล์และบันทึกข้อมูล...');
        const finalizeResp = await fetch('chunk_upload.php', {
            method: 'POST',
            body: finalizeForm,
            credentials: 'same-origin'
        });
        const finalizeJson = await parseApiResponse(finalizeResp, 'บันทึกไฟล์');

        return finalizeJson;
    }

    uploadForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (isUploading) {
            return;
        }

        const file = fileInput.files[0];
        if (!file) {
            alert('❌ กรุณาเลือกไฟล์ก่อนอัปโหลด');
            return;
        }

        const maxSize = 500 * 1024 * 1024;
        const allowed = ['application/pdf','image/jpeg','image/png','image/gif','image/webp'];
        if (!allowed.includes(file.type)) {
            alert('❌ ประเภทไฟล์ไม่อนุญาต');
            return;
        }
        if (file.size > maxSize) {
            alert('❌ ไฟล์เกิน 500MB');
            return;
        }

        try {
            setUploadingState(true);
            const result = await uploadFileInChunks(file);
            const assigned = Number(result.assigned_count || 0);
            const assignedMsg = assigned > 0 ? ' (มอบหมายผู้ตรวจ ' + assigned + ' คน)' : '';
            setUploadProgress('✅ อัปโหลดสำเร็จ' + assignedMsg + ' กำลังรีเฟรชหน้า...');
            setTimeout(() => { window.location.reload(); }, 1000);
        } catch (err) {
            const msg = (err && err.message) ? err.message : 'เกิดข้อผิดพลาดระหว่างอัปโหลด';
            setUploadProgress('❌ ' + msg);
            alert('❌ ' + msg);
            setUploadingState(false);
        }
    });

    // Profile Dropdown
    const profileWrapper = document.getElementById('profileWrapper');
    const profileTrigger = document.getElementById('profileTrigger');
    const overlay        = document.getElementById('dropdownOverlay');
    profileTrigger.addEventListener('click', (e) => {
        e.stopPropagation();
        profileWrapper.classList.toggle('open');
        overlay.classList.toggle('active');
    });
    overlay.addEventListener('click', closeDropdown);
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeDropdown(); });
    function closeDropdown() {
        profileWrapper.classList.remove('open');
        overlay.classList.remove('active');
    }

    // Theme
    const themeToggle   = document.getElementById('themeToggle');
    const themeMenuIcon = document.getElementById('themeMenuIcon');
    const themeMenuText = document.getElementById('themeMenuText');
    const html          = document.documentElement;
    function loadTheme() { applyTheme(localStorage.getItem('lolane_theme') || 'light'); }
    function applyTheme(theme) {
        html.setAttribute('data-theme', theme);
        localStorage.setItem('lolane_theme', theme);
        themeToggle.checked = (theme === 'dark');
        themeMenuIcon.textContent = theme === 'dark' ? '☀️' : '🌙';
        themeMenuText.textContent = theme === 'dark' ? 'Light Mode' : 'Dark Mode';
    }
    themeToggle.addEventListener('change', () => { applyTheme(themeToggle.checked ? 'dark' : 'light'); });
    function toggleThemeFromMenu() { applyTheme(html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark'); }
    loadTheme();

    // ★★★ REVIEWER PICKER ★★★
    (function(){
        const searchInput  = document.getElementById('reviewerSearch');
        const dropdown     = document.getElementById('reviewerDropdown');
        const selectedWrap = document.getElementById('reviewerSelected');
        const hiddenWrap   = document.getElementById('reviewerHiddenInputs');
        const picker       = document.getElementById('reviewerPicker');
        let selected = []; // [{id, display_name, initial}]
        let debounceTimer = null;
        let allUsers = null; // cache

        // focus search when clicking picker
        picker.addEventListener('click', () => searchInput.focus());

        searchInput.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            const q = searchInput.value.trim();
            if (q.length === 0) {
                dropdown.classList.remove('open');
                dropdown.innerHTML = '';
                return;
            }
            debounceTimer = setTimeout(() => fetchUsers(q), 250);
        });

        document.addEventListener('click', function(e) {
            if (!picker.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.classList.remove('open');
            }
        });

        function fetchUsers(q) {
            if (!q) { dropdown.classList.remove('open'); return; }
            fetch('reviewer_api.php?action=users&q=' + encodeURIComponent(q))
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;
                allUsers = data.users;
                renderDropdown(data.users);
                if (data.users.length > 0) dropdown.classList.add('open');
                else dropdown.classList.add('open'); // show "not found"
            });
        }

        function renderDropdown(users) {
            const selIds = selected.map(s => s.id);
            const filtered = users.filter(u => true); // show all, mark selected
            if (filtered.length === 0) {
                dropdown.innerHTML = '<div class="reviewer-dropdown-empty">ไม่พบผู้ใช้</div>';
                return;
            }
            dropdown.innerHTML = filtered.map(u => {
                const isSel = selIds.includes(u.id);
                return `<div class="reviewer-dropdown-item ${isSel ? 'selected' : ''}" data-id="${u.id}" data-name="${escapeAttr(u.display_name)}" data-initial="${escapeAttr(u.initial)}">
                    <div class="rdi-avatar">${escapeHtml(u.initial)}</div>
                    <div class="rdi-info">
                        <div class="rdi-name">${escapeHtml(u.display_name)}</div>
                        <div class="rdi-meta">${escapeHtml(u.email)}${u.department ? ' · ' + escapeHtml(u.department) : ''}</div>
                    </div>
                    <span class="rdi-check">✓</span>
                </div>`;
            }).join('');

            dropdown.querySelectorAll('.reviewer-dropdown-item').forEach(item => {
                item.addEventListener('click', () => toggleUser(item));
            });
        }

        function toggleUser(item) {
            const uid  = parseInt(item.dataset.id);
            const name = item.dataset.name;
            const init = item.dataset.initial;
            const idx  = selected.findIndex(s => s.id === uid);
            if (idx >= 0) {
                selected.splice(idx, 1);
                item.classList.remove('selected');
            } else {
                selected.push({ id: uid, display_name: name, initial: init });
                item.classList.add('selected');
            }
            renderSelected();
            searchInput.value = '';
            dropdown.classList.remove('open');
            dropdown.innerHTML = '';
            searchInput.focus();
        }

        function removeUser(uid) {
            selected = selected.filter(s => s.id !== uid);
            renderSelected();
            // update dropdown if open
            dropdown.querySelectorAll('.reviewer-dropdown-item').forEach(item => {
                if (parseInt(item.dataset.id) === uid) item.classList.remove('selected');
            });
        }

        function renderSelected() {
            selectedWrap.innerHTML = selected.map(s =>
                `<span class="reviewer-tag" data-id="${s.id}">
                    <span class="reviewer-tag-avatar">${escapeHtml(s.initial)}</span>
                    <span class="reviewer-tag-name">${escapeHtml(s.display_name)}</span>
                    <button type="button" class="reviewer-tag-remove" data-id="${s.id}">&times;</button>
                </span>`
            ).join('');

            hiddenWrap.innerHTML = selected.map(s =>
                `<input type="hidden" name="reviewer_ids[]" value="${s.id}">`
            ).join('');

            selectedWrap.querySelectorAll('.reviewer-tag-remove').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    removeUser(parseInt(btn.dataset.id));
                });
            });
        }

        function escapeAttr(s) { return (s||'').replace(/"/g, '&quot;').replace(/'/g, '&#39;'); }
    })();
    </script>
</body>
</html>