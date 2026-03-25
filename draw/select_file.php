<?php
require_once '../config.php';
requireLogin();

require_once '../get_avatar.php';
require_once 'log_helper.php';

$displayName = htmlspecialchars($_SESSION['display_name'] ?? 'User');
$email       = htmlspecialchars($_SESSION['email'] ?? '');
$initial     = mb_substr($displayName, 0, 1);
$userId      = $_SESSION['user_id'];

// ===== DELETE =====
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delId = (int) $_GET['delete'];
    try {
        // ★ ดึงชื่อไฟล์ก่อนลบ เพื่อเก็บ log
        $fnStmt = $pdo->prepare("SELECT file_name FROM uploads WHERE id = :id AND user_id = :uid");
        $fnStmt->execute([':id' => $delId, ':uid' => $userId]);
        $delFile = $fnStmt->fetch();
        $delFileName = $delFile ? $delFile['file_name'] : 'ไม่ทราบชื่อไฟล์';

        $stmt = $pdo->prepare("DELETE FROM uploads WHERE id = :id AND user_id = :uid");
        $stmt->execute([':id' => $delId, ':uid' => $userId]);

        if ($stmt->rowCount() > 0) {
            logActivity($pdo, 'delete', 'ลบไฟล์ "' . $delFileName . '"', $delId, $delFileName);
        }
    } catch (PDOException $e) { /* skip */ }
    header('Location: select_file.php');
    exit;
}

// ===== ดึงรายการไฟล์ =====
$stmt = $pdo->query(
    "SELECT u.id, u.file_name, u.file_type, u.file_mime, u.file_size,
            u.description, u.status, u.uploaded_at, u.user_id,
            usr.display_name AS uploader_name, usr.email AS uploader_email
     FROM uploads u
     JOIN users usr ON u.user_id = usr.id
     ORDER BY u.uploaded_at DESC"
);
$files = $stmt->fetchAll();

$countAll   = count($files);
$countPdf   = 0;
$countImage = 0;
foreach ($files as $f) {
    if ($f['file_type'] === 'pdf') $countPdf++;
    else $countImage++;
}

function formatFileSize(int $bytes): string {
    if ($bytes === 0) return '0 B';
    $units = ['B','KB','MB','GB'];
    $i = floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), 1) . ' ' . $units[$i];
}

function getStatusClass(string $status): string {
    switch ($status) {
        case 'กำลังตรวจ': return 'reviewing';
        case 'ผ่าน':     return 'approved';
        case 'ไม่ผ่าน':   return 'rejected';
        default:         return 'pending';
    }
}

function getStatusIcon(string $status): string {
    switch ($status) {
        case 'กำลังตรวจ': return '🔄';
        case 'ผ่าน':     return '✅';
        case 'ไม่ผ่าน':   return '❌';
        default:         return '⏳';
    }
}
?>
<!DOCTYPE html>
<html lang="th" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ไฟล์ที่อัปโหลด - Lolane Portal</title>

    <link rel="icon" type="image/jpeg" href="../img/logo.jpg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="../css/select_file.css">

    <style>
        /* ================================
           FILTER BAR
        ================================ */
        .filter-bar {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 14px;
            padding: 18px 24px;
            margin-bottom: 20px;
            box-shadow: var(--shadow);
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .filter-row {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }
        .search-wrapper {
            flex: 1;
            min-width: 200px;
            position: relative;
        }
        .search-wrapper .search-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 16px;
            color: var(--text-muted);
            pointer-events: none;
        }
        .search-input {
            width: 100%;
            padding: 10px 14px 10px 42px;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            font-family: 'Kanit', sans-serif;
            font-size: 14px;
            background: var(--bg-primary);
            color: var(--text-primary);
            transition: border-color 0.2s;
        }
        .search-input:focus { outline: none; border-color: var(--accent); }
        .search-input::placeholder { color: var(--text-muted); }
        .filter-tabs { display: flex; gap: 8px; flex-wrap: wrap; }
        .filter-tab {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 16px; border: 1px solid var(--border-color); border-radius: 20px;
            background: var(--bg-primary); color: var(--text-primary);
            font-family: 'Kanit', sans-serif; font-size: 13px; cursor: pointer; transition: all 0.2s; white-space: nowrap;
        }
        .filter-tab:hover { border-color: var(--accent); color: var(--accent); }
        .filter-tab.active { background: var(--accent); color: #fff; border-color: var(--accent); }
        .filter-tab .tab-count { background: rgba(0,0,0,0.1); padding: 1px 8px; border-radius: 10px; font-size: 11px; font-weight: 500; }
        .filter-tab.active .tab-count { background: rgba(255,255,255,0.25); }
        .filter-select, .sort-select {
            padding: 8px 14px; border: 1px solid var(--border-color); border-radius: 10px;
            font-family: 'Kanit', sans-serif; font-size: 13px;
            background: var(--bg-primary); color: var(--text-primary); cursor: pointer; min-width: 140px;
        }
        .filter-select:focus, .sort-select:focus { outline: none; border-color: var(--accent); }
        .sort-select { min-width: 150px; }
        .filter-result { font-size: 13px; color: var(--text-muted); font-weight: 300; padding: 4px 0; }
        .filter-result strong { color: var(--accent); font-weight: 600; }
        .no-result { text-align: center; padding: 48px 24px; color: var(--text-muted); }
        .no-result .no-result-icon { font-size: 48px; margin-bottom: 12px; }
        .no-result h3 { font-size: 16px; font-weight: 500; margin-bottom: 6px; color: var(--text-primary); }
        .no-result p { font-size: 13px; font-weight: 300; }
        .btn-clear-filter {
            display: inline-block; margin-top: 12px; padding: 8px 20px;
            border: 1px solid var(--accent); border-radius: 8px; color: var(--accent);
            font-family: 'Kanit', sans-serif; font-size: 13px; cursor: pointer; background: none; transition: all 0.2s;
        }
        .btn-clear-filter:hover { background: var(--accent); color: #fff; }
        .file-card { transition: all 0.3s ease; }
        .file-card.hidden { display: none !important; }

        /* ★ Avatar ใน Navbar ★ */
        .profile-avatar {
            width: 36px; height: 36px; border-radius: 50%;
            background: rgba(255,255,255,0.25); border: 2px solid rgba(255,255,255,0.5);
            display: flex; align-items: center; justify-content: center;
            font-weight: 600; font-size: 14px; overflow: hidden; flex-shrink: 0;
        }
        .profile-avatar img { width: 100%; height: 100%; object-fit: cover; }

        /* ================================
           ★★★ FILE THUMBNAIL PREVIEW ★★★
        ================================ */
        .file-card-thumbnail {
            width: 100%;
            height: 180px;
            background: var(--bg-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
            border-bottom: 1px solid var(--border-color);
        }
        .file-card-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s ease;
        }
        .file-card:hover .file-card-thumbnail img {
            transform: scale(1.05);
        }
        .file-card-thumbnail .thumb-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, transparent 50%, rgba(0,0,0,0.4) 100%);
            opacity: 0;
            transition: opacity 0.3s;
            display: flex;
            align-items: flex-end;
            justify-content: center;
            padding: 16px;
        }
        .file-card:hover .thumb-overlay {
            opacity: 1;
        }
        .thumb-overlay .thumb-view-btn {
            background: rgba(255,255,255,0.95);
            color: var(--accent);
            padding: 6px 16px;
            border-radius: 20px;
            font-family: 'Kanit', sans-serif;
            font-size: 12px;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }
        .thumb-overlay .thumb-view-btn:hover {
            background: var(--accent);
            color: #fff;
        }

        /* PDF Thumbnail */
        .file-card-thumbnail.pdf-thumb {
            background: linear-gradient(135deg, #ffebee, #fff5f5);
        }
        [data-theme="dark"] .file-card-thumbnail.pdf-thumb {
            background: linear-gradient(135deg, #3d1b1b, #2a1208);
        }
        .pdf-thumb-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
        }
        .pdf-thumb-icon {
            font-size: 48px;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));
        }
        .pdf-thumb-label {
            font-size: 14px;
            font-weight: 600;
            color: #c62828;
            letter-spacing: 2px;
            text-transform: uppercase;
        }
        [data-theme="dark"] .pdf-thumb-label {
            color: #ef9a9a;
        }
        .pdf-thumb-pages {
            font-size: 11px;
            color: var(--text-muted);
            font-weight: 300;
        }

        /* ★ Status badge ย้ายไปอยู่มุมบนขวาของ thumbnail */
        .file-card-thumbnail .status-float {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 5;
        }

        /* ================================
           ★★★ STATUS BADGE — BLINKING ★★★
        ================================ */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            white-space: nowrap;
            position: relative;
        }
        .status-badge .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        /* Pending — กระพริบเหลือง */
        .status-badge.pending {
            background: #fff8e1; color: #f57f17; border: 1px solid #ffe082;
        }
        .status-badge.pending .status-dot {
            background: #f57f17;
            animation: blink-pulse 1.5s infinite;
        }

        /* Reviewing — กระพริบน้ำเงิน */
        .status-badge.reviewing {
            background: #e3f2fd; color: #1565c0; border: 1px solid #90caf9;
        }
        .status-badge.reviewing .status-dot {
            background: #1565c0;
            animation: blink-pulse 1.2s infinite;
        }

        /* Approved — กระพริบเขียว */
        .status-badge.approved {
            background: #e8f5e9; color: #2e7d32; border: 1px solid #a5d6a7;
        }
        .status-badge.approved .status-dot {
            background: #2e7d32;
            animation: blink-glow 2s infinite;
        }

        /* Rejected — กระพริบแดง */
        .status-badge.rejected {
            background: #ffebee; color: #c62828; border: 1px solid #ef9a9a;
        }
        .status-badge.rejected .status-dot {
            background: #c62828;
            animation: blink-pulse 1s infinite;
        }

        /* Dark theme */
        [data-theme="dark"] .status-badge.pending { background: #3d2e0a; color: #ffd54f; border-color: #5e4a1a; }
        [data-theme="dark"] .status-badge.reviewing { background: #0a2a3d; color: #64b5f6; border-color: #1a3d5e; }
        [data-theme="dark"] .status-badge.approved { background: #1b3d1f; color: #81c784; border-color: #2e5e32; }
        [data-theme="dark"] .status-badge.rejected { background: #3d1b1b; color: #ef9a9a; border-color: #5e2e2e; }

        /* ★★★ Blink Animations ★★★ */
        @keyframes blink-pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.3; transform: scale(0.8); }
        }

        @keyframes blink-glow {
            0%, 100% { opacity: 1; box-shadow: 0 0 0 0 currentColor; }
            50% { opacity: 0.7; box-shadow: 0 0 8px 2px currentColor; }
        }

        /* ★ File card header ปรับใหม่ (ไม่มี status แล้ว ย้ายไปอยู่ thumbnail) */
        .file-card-header-new {
            padding: 14px 20px 10px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .file-card-icon-sm {
            width: 36px; height: 36px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; flex-shrink: 0;
        }
        .file-card-icon-sm.pdf { background: #ffebee; }
        .file-card-icon-sm.image { background: #e8f5e9; }
        [data-theme="dark"] .file-card-icon-sm.pdf { background: #3d1b1b; }
        [data-theme="dark"] .file-card-icon-sm.image { background: #1b3d1f; }

        .file-card-title-area { flex: 1; overflow: hidden; }
        .file-card-name-new {
            font-size: 14px; font-weight: 500;
            color: var(--text-primary);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .file-card-badge-new {
            display: inline-block; padding: 1px 8px; border-radius: 4px;
            font-size: 10px; font-weight: 500; text-transform: uppercase; margin-top: 2px;
        }
        .file-card-badge-new.pdf { background: #ffebee; color: #c62828; }
        .file-card-badge-new.image { background: #e8f5e9; color: #2e7d32; }
        [data-theme="dark"] .file-card-badge-new.pdf { background: #3d1b1b; color: #ef9a9a; }
        [data-theme="dark"] .file-card-badge-new.image { background: #1b3d1f; color: #81c784; }

        /* ★ File meta ปรับให้กระชับ */
        .file-meta-compact {
            padding: 8px 20px 14px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px 16px;
            font-size: 12px;
        }
        .meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .meta-item .meta-icon { font-size: 13px; flex-shrink: 0; }
        .meta-item .meta-label { color: var(--text-muted); font-weight: 300; }
        .meta-item .meta-value { color: var(--text-primary); font-weight: 500; }

        .file-description-new {
            margin: 0 20px 12px;
            padding: 8px 12px;
            background: var(--bg-primary);
            border-radius: 8px;
            font-size: 12px;
            color: var(--text-secondary);
            font-weight: 300;
            line-height: 1.5;
        }

        @media (max-width: 768px) {
            .filter-row { flex-direction: column; }
            .search-wrapper { min-width: 100%; }
            .filter-tabs { width: 100%; }
            .filter-tab { flex: 1; justify-content: center; }
            .file-card-thumbnail { height: 150px; }
            .file-meta-compact { grid-template-columns: 1fr; }
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
                Lolane Draw
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
            <div class="page-header-left">
                <h2>📋 ไฟล์ที่อัปโหลดแล้ว (<?= $countAll ?> ไฟล์)</h2>
                <p>แสดงรายการไฟล์ทั้งหมดที่คุณได้อัปโหลดไว้</p>
            </div>
            <a href="upload.php" class="btn-back">📁 อัปโหลดไฟล์ใหม่</a>
        </div>

        <?php if (!empty($files)): ?>
        <div class="filter-bar">
            <div class="filter-row">
                <div class="search-wrapper">
                    <span class="search-icon">🔍</span>
                    <input type="text" class="search-input" id="searchInput" placeholder="ค้นหาชื่อไฟล์, คำอธิบาย...">
                </div>
                <select class="filter-select" id="statusFilter">
                    <option value="all">📌 สถานะทั้งหมด</option>
                    <option value="รอตรวจ">⏳ รอตรวจ</option>
                    <option value="กำลังตรวจ">🔄 กำลังตรวจ</option>
                    <option value="ผ่าน">✅ ผ่าน</option>
                    <option value="ไม่ผ่าน">❌ ไม่ผ่าน</option>
                </select>
                <select class="sort-select" id="sortFilter">
                    <option value="newest">📅 ใหม่สุดก่อน</option>
                    <option value="oldest">📅 เก่าสุดก่อน</option>
                    <option value="name-asc">🔤 ชื่อ A-Z</option>
                    <option value="name-desc">🔤 ชื่อ Z-A</option>
                    <option value="size-desc">📦 ใหญ่สุดก่อน</option>
                    <option value="size-asc">📦 เล็กสุดก่อน</option>
                </select>
            </div>
            <div class="filter-row">
                <div class="filter-tabs" id="typeTabs">
                    <button class="filter-tab active" data-type="all">📂 ทั้งหมด <span class="tab-count"><?= $countAll ?></span></button>
                    <button class="filter-tab" data-type="pdf">📄 PDF <span class="tab-count"><?= $countPdf ?></span></button>
                    <button class="filter-tab" data-type="image">🖼️ รูปภาพ <span class="tab-count"><?= $countImage ?></span></button>
                </div>
                <div class="filter-result" id="filterResult">แสดง <strong><?= $countAll ?></strong> จาก <?= $countAll ?> ไฟล์</div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (empty($files)): ?>
            <div class="no-files">
                <div class="no-files-icon">📭</div>
                <h3>ยังไม่มีไฟล์ที่อัปโหลด</h3>
                <p>กดปุ่ม "อัปโหลดไฟล์ใหม่" เพื่อเริ่มต้นอัปโหลด</p>
            </div>
        <?php else: ?>
            <div class="no-result" id="noResult" style="display:none;">
                <div class="no-result-icon">🔍</div>
                <h3>ไม่พบไฟล์ที่ตรงกับการค้นหา</h3>
                <p>ลองเปลี่ยนคำค้นหาหรือตัวกรอง</p>
                <button class="btn-clear-filter" onclick="clearAllFilters()">🗑️ ล้างตัวกรองทั้งหมด</button>
            </div>

            <div class="files-grid" id="filesGrid">
                <?php foreach ($files as $f): ?>
                <div class="file-card"
                     data-type="<?= htmlspecialchars($f['file_type']) ?>"
                     data-status="<?= htmlspecialchars($f['status']) ?>"
                     data-name="<?= htmlspecialchars(strtolower($f['file_name'])) ?>"
                     data-desc="<?= htmlspecialchars(strtolower($f['description'] ?? '')) ?>"
                     data-date="<?= $f['uploaded_at'] ?>"
                     data-size="<?= $f['file_size'] ?>">

                    <!-- ★★★ THUMBNAIL PREVIEW ★★★ -->
                    <div class="file-card-thumbnail <?= $f['file_type'] === 'pdf' ? 'pdf-thumb' : '' ?>">

                        <!-- ★ Status ลอยมุมบนขวา + กระพริบ -->
                        <div class="status-float">
                            <div class="status-badge <?= getStatusClass($f['status']) ?>">
                                <span class="status-dot"></span>
                                <?= getStatusIcon($f['status']) ?> <?= htmlspecialchars($f['status']) ?>
                            </div>
                        </div>

                        <?php if ($f['file_type'] === 'pdf'): ?>
                            <!-- ★ PDF Preview — canvas rendered with PDF.js -->
                            <canvas class="pdf-preview-canvas" data-pdf-id="<?= $f['id'] ?>" style="width:100%;height:100%;object-fit:contain;display:none;"></canvas>
                            <div class="pdf-thumb-content pdf-thumb-loading" id="pdfload-<?= $f['id'] ?>">
                                <span class="pdf-thumb-icon" style="animation:spin 1s linear infinite;display:inline-block;">⏳</span>
                                <span class="pdf-thumb-label" style="font-size:11px;">กำลังโหลด...</span>
                            </div>
                        <?php else: ?>
                            <!-- ★ Image Preview — โหลดจาก get_thumbnail.php (ทุก user เห็นได้) -->
                            <img src="get_thumbnail.php?id=<?= $f['id'] ?>"
                                 alt="<?= htmlspecialchars($f['file_name']) ?>"
                                 loading="lazy"
                                 onerror="this.parentElement.innerHTML='<div class=\'pdf-thumb-content\'><span class=\'pdf-thumb-icon\'>🖼️</span><span class=\'pdf-thumb-label\'>IMAGE</span></div>'">
                        <?php endif; ?>

                        <!-- Hover overlay -->
                        <div class="thumb-overlay">
                            <a href="view_file.php?id=<?= $f['id'] ?>" class="thumb-view-btn">👁️ ดูไฟล์</a>
                        </div>
                    </div>

                    <!-- ★ Header (ชื่อไฟล์ + badge) -->
                    <div class="file-card-header-new">
                        <div class="file-card-icon-sm <?= $f['file_type'] ?>">
                            <?= $f['file_type'] === 'pdf' ? '📄' : '🖼️' ?>
                        </div>
                        <div class="file-card-title-area">
                            <div class="file-card-name-new" title="<?= htmlspecialchars($f['file_name']) ?>">
                                <?= htmlspecialchars($f['file_name']) ?>
                            </div>
                            <span class="file-card-badge-new <?= $f['file_type'] ?>">
                                <?= $f['file_type'] === 'pdf' ? 'PDF' : strtoupper(pathinfo($f['file_name'], PATHINFO_EXTENSION)) ?>
                            </span>
                        </div>
                    </div>

                    <!-- ★ Meta Info (2 คอลัมน์) -->
                    <div class="file-meta-compact">
                        <div class="meta-item">
                            <span class="meta-icon">👤</span>
                            <span class="meta-value"><?= htmlspecialchars($f['uploader_name']) ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-icon">📦</span>
                            <span class="meta-value"><?= formatFileSize($f['file_size']) ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-icon">📅</span>
                            <span class="meta-value"><?= date('d/m/Y', strtotime($f['uploaded_at'])) ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-icon">🕐</span>
                            <span class="meta-value"><?= date('H:i น.', strtotime($f['uploaded_at'])) ?></span>
                        </div>
                    </div>

                    <?php if (!empty($f['description'])): ?>
                        <div class="file-description-new">📝 <?= htmlspecialchars($f['description']) ?></div>
                    <?php endif; ?>

                    <!-- Actions -->
                    <div class="file-card-actions">
                        <a href="view_file.php?id=<?= $f['id'] ?>" class="btn-action view">👁️ ดูไฟล์</a>
                        <?php if (($f['user_id'] ?? null) == $userId): ?>
                        <a href="select_file.php?delete=<?= $f['id'] ?>" class="btn-action delete"
                           onclick="return confirm('ต้องการลบไฟล์ <?= htmlspecialchars($f['file_name'], ENT_QUOTES) ?> ?')">🗑️ ลบ</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>

    <div class="footer">© 2026 Lolane Co., Ltd. | Powered by <span class="footer-brand">ALPHABET</span></div>

    <!-- PDF.js สำหรับ render thumbnail หน้าแรกของ PDF -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script>pdfjsLib.GlobalWorkerOptions.workerSrc='https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';</script>

    <script>
    // ★★★ PDF THUMBNAIL RENDERER ★★★
    (function(){
        var canvases = document.querySelectorAll('.pdf-preview-canvas');
        if (!canvases.length || typeof pdfjsLib === 'undefined') return;

        // ใช้ IntersectionObserver โหลดเฉพาะที่อยู่ใน viewport (lazy)
        var observer = new IntersectionObserver(function(entries){
            entries.forEach(function(entry){
                if (!entry.isIntersecting) return;
                observer.unobserve(entry.target);
                renderPdfThumb(entry.target);
            });
        }, { rootMargin: '200px' });

        canvases.forEach(function(canvas){ observer.observe(canvas); });

        function renderPdfThumb(canvas) {
            var fileId = canvas.dataset.pdfId;
            var loadingEl = document.getElementById('pdfload-' + fileId);

            pdfjsLib.getDocument({
                url: 'serve_file.php?id=' + fileId,
                rangeChunkSize: 65536,
                withCredentials: true
            }).promise.then(function(pdf){
                return pdf.getPage(1);
            }).then(function(page){
                var container = canvas.parentElement;
                var containerW = container.offsetWidth || 300;
                var containerH = container.offsetHeight || 180;
                var viewport = page.getViewport({scale: 1});
                // scale ให้พอดีกับ container
                var scale = Math.min(containerW / viewport.width, containerH / viewport.height);
                var scaledVP = page.getViewport({scale: scale});
                canvas.width  = scaledVP.width;
                canvas.height = scaledVP.height;
                return page.render({ canvasContext: canvas.getContext('2d'), viewport: scaledVP }).promise.then(function(){
                    canvas.style.display = 'block';
                    if (loadingEl) loadingEl.style.display = 'none';
                });
            }).catch(function(){
                // fallback: แสดง icon PDF
                if (loadingEl) {
                    loadingEl.innerHTML = '<span class="pdf-thumb-icon">📄</span><span class="pdf-thumb-label">PDF</span>';
                }
            });
        }
    })();
    </script>

    <style>@keyframes spin { from{transform:rotate(0deg)} to{transform:rotate(360deg)} }</style>

    <script>
    // FILTER ENGINE
    (function(){
        var searchInput  = document.getElementById('searchInput');
        var statusFilter = document.getElementById('statusFilter');
        var sortFilter   = document.getElementById('sortFilter');
        var typeTabs     = document.getElementById('typeTabs');
        var filesGrid    = document.getElementById('filesGrid');
        var noResult     = document.getElementById('noResult');
        var filterResult = document.getElementById('filterResult');
        if (!filesGrid) return;
        var allCards = Array.from(filesGrid.querySelectorAll('.file-card'));
        var totalFiles = allCards.length;
        var activeType = 'all';

        typeTabs.addEventListener('click', function(e) {
            var tab = e.target.closest('.filter-tab');
            if (!tab) return;
            typeTabs.querySelectorAll('.filter-tab').forEach(function(t) { t.classList.remove('active'); });
            tab.classList.add('active');
            activeType = tab.dataset.type;
            applyFilters();
        });
        searchInput.addEventListener('input', debounce(applyFilters, 200));
        statusFilter.addEventListener('change', applyFilters);
        sortFilter.addEventListener('change', function() { sortCards(); applyFilters(); });

        function applyFilters() {
            var query = searchInput.value.trim().toLowerCase();
            var status = statusFilter.value;
            var visibleCount = 0;
            allCards.forEach(function(card) {
                var matchType = (activeType === 'all') || (card.dataset.type === activeType);
                var matchStatus = (status === 'all') || (card.dataset.status === status);
                var matchSearch = true;
                if (query) {
                    var name = card.dataset.name || '';
                    var desc = card.dataset.desc || '';
                    matchSearch = name.indexOf(query) >= 0 || desc.indexOf(query) >= 0;
                }
                if (matchType && matchStatus && matchSearch) { card.classList.remove('hidden'); visibleCount++; }
                else { card.classList.add('hidden'); }
            });
            filterResult.innerHTML = 'แสดง <strong>' + visibleCount + '</strong> จาก ' + totalFiles + ' ไฟล์';
            if (visibleCount === 0) { noResult.style.display = 'block'; filesGrid.style.display = 'none'; }
            else { noResult.style.display = 'none'; filesGrid.style.display = ''; }
        }
        function sortCards() {
            var sortBy = sortFilter.value;
            allCards.sort(function(a, b) {
                switch (sortBy) {
                    case 'newest': return (b.dataset.date||'').localeCompare(a.dataset.date||'');
                    case 'oldest': return (a.dataset.date||'').localeCompare(b.dataset.date||'');
                    case 'name-asc': return (a.dataset.name||'').localeCompare(b.dataset.name||'');
                    case 'name-desc': return (b.dataset.name||'').localeCompare(a.dataset.name||'');
                    case 'size-desc': return parseInt(b.dataset.size||0) - parseInt(a.dataset.size||0);
                    case 'size-asc': return parseInt(a.dataset.size||0) - parseInt(b.dataset.size||0);
                    default: return 0;
                }
            });
            allCards.forEach(function(card) { filesGrid.appendChild(card); });
        }
        function debounce(fn, delay) { var timer; return function() { clearTimeout(timer); timer = setTimeout(fn, delay); }; }
        window.clearAllFilters = function() {
            searchInput.value = ''; statusFilter.value = 'all'; sortFilter.value = 'newest'; activeType = 'all';
            typeTabs.querySelectorAll('.filter-tab').forEach(function(t) { t.classList.toggle('active', t.dataset.type === 'all'); });
            sortCards(); applyFilters();
        };
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') { e.preventDefault(); searchInput.focus(); searchInput.select(); }
            if (e.key === 'Escape' && document.activeElement === searchInput) { searchInput.value = ''; searchInput.blur(); applyFilters(); }
        });
    })();

    // PROFILE / THEME
    var profileWrapper = document.getElementById('profileWrapper');
    var profileTrigger = document.getElementById('profileTrigger');
    var overlay = document.getElementById('dropdownOverlay');
    profileTrigger.addEventListener('click', function(e) { e.stopPropagation(); profileWrapper.classList.toggle('open'); overlay.classList.toggle('active'); });
    overlay.addEventListener('click', closeDD);
    document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeDD(); });
    function closeDD() { profileWrapper.classList.remove('open'); overlay.classList.remove('active'); }
    var themeToggle = document.getElementById('themeToggle');
    var themeMenuIcon = document.getElementById('themeMenuIcon');
    var themeMenuText = document.getElementById('themeMenuText');
    var html = document.documentElement;
    function loadTheme() { applyTheme(localStorage.getItem('lolane_theme') || 'light'); }
    function applyTheme(t) { html.setAttribute('data-theme', t); localStorage.setItem('lolane_theme', t); themeToggle.checked = (t === 'dark'); themeMenuIcon.textContent = t === 'dark' ? '☀️' : '🌙'; themeMenuText.textContent = t === 'dark' ? 'Light Mode' : 'Dark Mode'; }
    themeToggle.addEventListener('change', function() { applyTheme(themeToggle.checked ? 'dark' : 'light'); });
    function toggleThemeFromMenu() { applyTheme(html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark'); }
    loadTheme();
    </script>
</body>
</html>