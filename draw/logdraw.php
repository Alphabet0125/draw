<?php
require_once '../config.php';
requireLogin();

// ★ จำกัดสิทธิ์: เฉพาะแผนก IT และ HR เท่านั้น
$allowedDepts = ['IT', 'HR'];
$userDept = trim($_SESSION['department'] ?? '');
if (!in_array($userDept, $allowedDepts, true)) {
    http_response_code(403);
    echo '<!DOCTYPE html><html lang="th"><head><meta charset="UTF-8"><title>403 Forbidden</title>';
    echo '<link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">';
    echo '<style>body{font-family:"Kanit",sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#f5f5f5;}';
    echo '.box{text-align:center;padding:40px;background:#fff;border-radius:16px;box-shadow:0 4px 20px rgba(0,0,0,0.08);}';
    echo '.box h1{font-size:48px;color:#e74c3c;margin:0 0 8px}.box p{color:#666;font-size:16px;margin:8px 0}';
    echo '.box a{display:inline-block;margin-top:20px;padding:10px 24px;background:#ff6b35;color:#fff;border-radius:8px;text-decoration:none;font-weight:500;transition:background 0.2s}';
    echo '.box a:hover{background:#e55a2b}</style></head><body>';
    echo '<div class="box"><h1>403</h1><p>⛔ คุณไม่มีสิทธิ์เข้าถึงหน้านี้</p><p>เฉพาะแผนก IT และ HR เท่านั้น</p>';
    echo '<a href="select_file.php">← กลับหน้าไฟล์</a></div></body></html>';
    exit;
}

require_once '../get_avatar.php';

$displayName = htmlspecialchars($_SESSION['display_name'] ?? 'User');
$email       = htmlspecialchars($_SESSION['email'] ?? '');
$initial     = mb_substr($displayName, 0, 1);
$userId      = $_SESSION['user_id'];

// ดึงสถิติรวม
$totalLogs  = (int)$pdo->query("SELECT COUNT(*) FROM activity_logs")->fetchColumn();

$statsStmt = $pdo->query("SELECT action_type, COUNT(*) as cnt FROM activity_logs GROUP BY action_type");
$statsMap  = [];
while ($row = $statsStmt->fetch()) {
    $statsMap[$row['action_type']] = (int)$row['cnt'];
}

// ดึงรายชื่อผู้ใช้ทั้งหมดที่มี log
$usersStmt = $pdo->query("SELECT DISTINCT al.user_id, al.display_name FROM activity_logs al ORDER BY al.display_name");
$logUsers  = $usersStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="th" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - Lolane Portal</title>

    <link rel="icon" type="image/jpeg" href="../img/logo.jpg">
    <link rel="shortcut icon" type="image/jpeg" href="../img/logo.jpg">
    <link rel="apple-touch-icon" href="../img/logo.jpg">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        /* ================================
           THEME VARIABLES
        ================================ */
        :root, [data-theme="light"] {
            --bg-primary: #fef7f0;
            --bg-secondary: #ffffff;
            --bg-navbar: linear-gradient(135deg, #ff8c42, #ff6b35);
            --bg-card: #ffffff;
            --bg-hover: #fff5ec;
            --bg-dropdown: #ffffff;
            --text-primary: #2d2d2d;
            --text-secondary: #777777;
            --text-muted: #b0885a;
            --border-color: #ffecd2;
            --shadow: 0 4px 20px rgba(255, 140, 66, 0.08);
            --shadow-lg: 0 10px 40px rgba(255, 140, 66, 0.15);
            --accent: #ff6b35;
            --accent-light: #fff0e5;
            --accent-hover: #e85d2c;
            --navbar-shadow: 0 4px 20px rgba(255, 107, 53, 0.3);
        }
        [data-theme="dark"] {
            --bg-primary: #1a1208;
            --bg-secondary: #261c0e;
            --bg-navbar: linear-gradient(135deg, #b85a1e, #8c3d0f);
            --bg-card: #2a1f10;
            --bg-hover: #332814;
            --bg-dropdown: #2a1f10;
            --text-primary: #ffecd2;
            --text-secondary: #c4a882;
            --text-muted: #8a7050;
            --border-color: #3d2e18;
            --shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            --shadow-lg: 0 10px 40px rgba(0, 0, 0, 0.5);
            --accent: #ff8c42;
            --accent-light: #3d2e18;
            --accent-hover: #ffa05c;
            --navbar-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
        }

        /* ================================
           BASE
        ================================ */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Kanit', sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            min-height: 100vh;
            transition: background 0.4s ease, color 0.4s ease;
        }
        a { color: inherit; text-decoration: none; }

        /* ================================
           NAVBAR
        ================================ */
        .navbar {
            background: var(--bg-navbar); color: white;
            padding: 0 32px; height: 64px;
            display: flex; justify-content: space-between; align-items: center;
            box-shadow: var(--navbar-shadow); position: sticky; top: 0; z-index: 1000;
        }
        .nav-left { display: flex; align-items: center; gap: 28px; }
        .nav-logo {
            display: flex; align-items: center; gap: 10px;
            font-size: 20px; font-weight: 600; color: white; text-decoration: none;
        }
        .nav-logo:hover { opacity: 0.9; }
        .nav-logo-img { width: 36px; height: 36px; border-radius: 8px; object-fit: cover; border: 2px solid rgba(255,255,255,0.4); }
        .nav-links { display: flex; gap: 4px; }
        .nav-link {
            color: rgba(255,255,255,0.8); padding: 8px 16px; border-radius: 8px;
            font-size: 14px; transition: all 0.2s;
        }
        .nav-link:hover, .nav-link.active { background: rgba(255,255,255,0.2); color: white; }
        .nav-right { display: flex; align-items: center; gap: 14px; }

        /* Theme Toggle */
        .theme-toggle { position: relative; width: 52px; height: 26px; cursor: pointer; }
        .theme-toggle input { display: none; }
        .toggle-slider {
            position: absolute; inset: 0; background: rgba(255,255,255,0.25);
            border-radius: 13px; transition: all 0.3s;
        }
        .toggle-slider::before {
            content: "☀️"; font-size: 12px; position: absolute; top: 2px; left: 3px;
            width: 22px; height: 22px; display: flex; align-items: center; justify-content: center;
            background: #fff; border-radius: 50%; transition: transform 0.3s;
        }
        .theme-toggle input:checked + .toggle-slider::before { content: "🌙"; transform: translateX(25px); background: #2a1f10; }

        /* Profile */
        .profile-wrapper { position: relative; }
        .profile-trigger {
            display: flex; align-items: center; gap: 10px; cursor: pointer;
            padding: 5px 12px; border-radius: 12px; transition: background 0.2s;
            border: none; background: none; color: white; font-family: 'Kanit', sans-serif;
        }
        .profile-trigger:hover { background: rgba(255,255,255,0.15); }
        .profile-avatar {
            width: 36px; height: 36px; border-radius: 50%;
            background: rgba(255,255,255,0.25); border: 2px solid rgba(255,255,255,0.5);
            display: flex; align-items: center; justify-content: center;
            font-weight: 600; font-size: 14px; overflow: hidden; flex-shrink: 0;
        }
        .profile-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .profile-info { text-align: left; line-height: 1.3; }
        .profile-name { font-weight: 500; font-size: 13px; }
        .profile-email { font-size: 11px; opacity: 0.75; }
        .profile-arrow { font-size: 10px; transition: transform 0.2s; opacity: 0.7; }
        .profile-wrapper.open .profile-arrow { transform: rotate(180deg); }
        .profile-dropdown {
            position: absolute; top: calc(100% + 10px); right: 0;
            background: var(--bg-dropdown); border: 1px solid var(--border-color);
            border-radius: 14px; box-shadow: var(--shadow-lg); min-width: 270px;
            opacity: 0; visibility: hidden; transform: translateY(-10px);
            transition: all 0.25s; z-index: 999; overflow: hidden;
        }
        .profile-wrapper.open .profile-dropdown { opacity: 1; visibility: visible; transform: translateY(0); }
        .dropdown-menu { padding: 6px 0; }
        .dropdown-item {
            display: flex; align-items: center; gap: 10px; padding: 11px 20px;
            font-family: 'Kanit', sans-serif; font-size: 14px; color: var(--text-primary);
            transition: background 0.15s; cursor: pointer; border: none; background: none;
            width: 100%; text-align: left;
        }
        .dropdown-item:hover { background: var(--bg-hover); }
        .dropdown-item .icon { font-size: 16px; width: 22px; text-align: center; }
        .dropdown-divider { height: 1px; background: var(--border-color); margin: 4px 0; }
        .dropdown-item.danger { color: #e74c3c; }
        .dropdown-item.danger:hover { background: #fff0ee; }
        [data-theme="dark"] .dropdown-item.danger:hover { background: #3d1c1c; }
        .dropdown-overlay { display: none; position: fixed; inset: 0; z-index: 998; }
        .dropdown-overlay.active { display: block; }

        /* ================================
           CONTAINER
        ================================ */
        .container { max-width: 1400px; margin: 24px auto; padding: 0 24px; }
        .page-header { margin-bottom: 24px; }
        .page-header h2 { font-size: 24px; font-weight: 600; margin-bottom: 4px; }
        .page-header p { font-size: 14px; color: var(--text-muted); font-weight: 300; }

        /* ================================
           STATS CARDS
        ================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 14px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 14px;
            padding: 18px;
            box-shadow: var(--shadow);
            transition: all 0.3s;
            cursor: pointer;
            text-align: center;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-lg); border-color: var(--accent); }
        .stat-card.active { border-color: var(--accent); background: var(--accent-light); }
        .stat-icon { font-size: 28px; margin-bottom: 6px; }
        .stat-count { font-size: 26px; font-weight: 700; color: var(--accent); }
        .stat-label { font-size: 12px; color: var(--text-muted); font-weight: 400; margin-top: 2px; }

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
            display: flex; gap: 12px; align-items: center; flex-wrap: wrap;
        }
        .search-wrapper {
            flex: 1; min-width: 200px; position: relative;
        }
        .search-wrapper .search-icon {
            position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
            font-size: 16px; color: var(--text-muted); pointer-events: none;
        }
        .search-input {
            width: 100%; padding: 10px 14px 10px 42px;
            border: 1px solid var(--border-color); border-radius: 10px;
            font-family: 'Kanit', sans-serif; font-size: 14px;
            background: var(--bg-primary); color: var(--text-primary); transition: border-color 0.2s;
        }
        .search-input:focus { outline: none; border-color: var(--accent); }
        .search-input::placeholder { color: var(--text-muted); }
        .filter-select {
            padding: 10px 14px; border: 1px solid var(--border-color); border-radius: 10px;
            font-family: 'Kanit', sans-serif; font-size: 13px;
            background: var(--bg-primary); color: var(--text-primary); cursor: pointer; min-width: 140px;
        }
        .filter-select:focus { outline: none; border-color: var(--accent); }
        .date-input {
            padding: 10px 14px; border: 1px solid var(--border-color); border-radius: 10px;
            font-family: 'Kanit', sans-serif; font-size: 13px;
            background: var(--bg-primary); color: var(--text-primary); cursor: pointer;
        }
        .date-input:focus { outline: none; border-color: var(--accent); }
        .btn-filter {
            padding: 10px 20px; border: none; border-radius: 10px;
            background: var(--accent); color: #fff;
            font-family: 'Kanit', sans-serif; font-size: 13px; font-weight: 500;
            cursor: pointer; transition: all 0.2s;
        }
        .btn-filter:hover { background: var(--accent-hover); }
        .btn-reset {
            padding: 10px 20px; border: 1px solid var(--border-color); border-radius: 10px;
            background: var(--bg-primary); color: var(--text-muted);
            font-family: 'Kanit', sans-serif; font-size: 13px; cursor: pointer; transition: all 0.2s;
        }
        .btn-reset:hover { border-color: var(--accent); color: var(--accent); }

        /* ================================
           LOG TABLE
        ================================ */
        .log-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 14px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        .log-table-wrapper {
            overflow-x: auto;
        }
        .log-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .log-table thead th {
            background: var(--accent-light);
            color: var(--text-primary);
            padding: 14px 16px;
            text-align: left;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--border-color);
            white-space: nowrap;
        }
        .log-table tbody tr {
            border-bottom: 1px solid var(--border-color);
            transition: background 0.15s;
        }
        .log-table tbody tr:hover { background: var(--bg-hover); }
        .log-table tbody tr:last-child { border-bottom: none; }
        .log-table td {
            padding: 12px 16px;
            vertical-align: middle;
        }

        /* Action badge */
        .action-badge {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 4px 12px; border-radius: 20px;
            font-size: 12px; font-weight: 500; white-space: nowrap;
        }
        .action-badge.upload     { background: #e8f5e9; color: #2e7d32; border: 1px solid #a5d6a7; }
        .action-badge.delete     { background: #ffebee; color: #c62828; border: 1px solid #ef9a9a; }
        .action-badge.save_drawing { background: #e3f2fd; color: #1565c0; border: 1px solid #90caf9; }
        .action-badge.clear_drawing { background: #fff3e0; color: #e65100; border: 1px solid #ffcc80; }
        .action-badge.status_change { background: #f3e5f5; color: #6a1b9a; border: 1px solid #ce93d8; }
        .action-badge.download   { background: #e0f2f1; color: #00695c; border: 1px solid #80cbc4; }
        .action-badge.view       { background: #fce4ec; color: #880e4f; border: 1px solid #f48fb1; }
        .action-badge.tool_use   { background: #fff8e1; color: #f57f17; border: 1px solid #ffe082; }
        .action-badge.comment    { background: #e8eaf6; color: #283593; border: 1px solid #9fa8da; }
        [data-theme="dark"] .action-badge.upload     { background: #1b3d1f; color: #81c784; border-color: #2e5e32; }
        [data-theme="dark"] .action-badge.delete     { background: #3d1b1b; color: #ef9a9a; border-color: #5e2e2e; }
        [data-theme="dark"] .action-badge.save_drawing { background: #0a2a3d; color: #64b5f6; border-color: #1a3d5e; }
        [data-theme="dark"] .action-badge.clear_drawing { background: #3d2a0a; color: #ffb74d; border-color: #5e4a1a; }
        [data-theme="dark"] .action-badge.status_change { background: #2a0a3d; color: #ba68c8; border-color: #3d1a5e; }
        [data-theme="dark"] .action-badge.download   { background: #0a3d35; color: #4db6ac; border-color: #1a5e4a; }
        [data-theme="dark"] .action-badge.view       { background: #3d0a2a; color: #f48fb1; border-color: #5e1a3d; }
        [data-theme="dark"] .action-badge.tool_use   { background: #3d2e0a; color: #ffd54f; border-color: #5e4a1a; }
        [data-theme="dark"] .action-badge.comment    { background: #0a0a3d; color: #9fa8da; border-color: #1a1a5e; }

        /* User cell */
        .user-cell {
            display: flex; align-items: center; gap: 8px;
        }
        .user-cell .user-avatar {
            width: 30px; height: 30px; border-radius: 50%;
            background: var(--accent-light); border: 1px solid var(--border-color);
            display: flex; align-items: center; justify-content: center;
            font-size: 12px; font-weight: 600; flex-shrink: 0;
            color: var(--accent);
        }
        .user-cell .user-name { font-weight: 500; font-size: 13px; }
        .user-cell .user-email { font-size: 11px; color: var(--text-muted); }

        /* File cell */
        .file-link {
            color: var(--accent); font-weight: 500;
            transition: opacity 0.2s;
        }
        .file-link:hover { opacity: 0.7; text-decoration: underline; }

        .detail-text {
            max-width: 300px; overflow: hidden;
            text-overflow: ellipsis; white-space: nowrap;
            color: var(--text-secondary); font-size: 12px;
        }
        .detail-text:hover {
            white-space: normal; overflow: visible;
        }

        .time-cell {
            white-space: nowrap; font-size: 12px; color: var(--text-muted);
        }
        .time-cell .time-date { font-weight: 500; color: var(--text-primary); }

        .ip-cell { font-size: 11px; color: var(--text-muted); font-family: monospace; }

        /* ================================
           PAGINATION
        ================================ */
        .pagination {
            display: flex; justify-content: center; align-items: center;
            gap: 6px; padding: 20px 24px;
            border-top: 1px solid var(--border-color);
        }
        .page-btn {
            min-width: 38px; height: 38px;
            display: flex; align-items: center; justify-content: center;
            border: 1px solid var(--border-color); border-radius: 10px;
            background: var(--bg-primary); color: var(--text-primary);
            font-family: 'Kanit', sans-serif; font-size: 13px;
            cursor: pointer; transition: all 0.2s;
        }
        .page-btn:hover { border-color: var(--accent); color: var(--accent); }
        .page-btn.active { background: var(--accent); color: #fff; border-color: var(--accent); }
        .page-btn:disabled { opacity: 0.4; cursor: not-allowed; }
        .page-info { font-size: 13px; color: var(--text-muted); padding: 0 12px; }

        /* ================================
           LOADING & EMPTY
        ================================ */
        .loading-overlay {
            display: none; justify-content: center; align-items: center;
            padding: 60px; color: var(--text-muted); font-size: 14px;
        }
        .loading-overlay.active { display: flex; }
        .spinner {
            width: 32px; height: 32px; border: 3px solid var(--border-color);
            border-top-color: var(--accent); border-radius: 50%;
            animation: spin 0.8s linear infinite; margin-right: 12px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        .empty-state {
            text-align: center; padding: 60px 24px; color: var(--text-muted);
        }
        .empty-icon { font-size: 48px; margin-bottom: 12px; }
        .empty-state h3 { font-size: 16px; font-weight: 500; margin-bottom: 6px; color: var(--text-primary); }
        .empty-state p { font-size: 13px; font-weight: 300; }

        /* ================================
           FOOTER
        ================================ */
        .footer {
            text-align: center; padding: 20px;
            color: var(--text-muted); font-size: 12px; font-weight: 300;
            border-top: 1px solid var(--border-color); margin-top: 40px;
        }
        .footer-brand { color: var(--accent); font-weight: 500; }

        /* ================================
           RESPONSIVE
        ================================ */
        @media (max-width: 768px) {
            .navbar { padding: 0 16px; }
            .nav-links { display: none; }
            .container { padding: 0 12px; }
            .stats-grid { grid-template-columns: repeat(3, 1fr); gap: 8px; }
            .stat-card { padding: 12px; }
            .stat-icon { font-size: 22px; }
            .stat-count { font-size: 20px; }
            .filter-row { flex-direction: column; }
            .search-wrapper { min-width: 100%; }
            .filter-select, .date-input { width: 100%; }
            .profile-info { display: none; }
        }

        /* ================================
           TIMELINE VIEW (alternative)
        ================================ */
        .view-toggle {
            display: flex; gap: 6px; margin-left: auto;
        }
        .view-btn {
            padding: 8px 14px; border: 1px solid var(--border-color); border-radius: 8px;
            background: var(--bg-primary); color: var(--text-muted);
            font-family: 'Kanit', sans-serif; font-size: 12px;
            cursor: pointer; transition: all 0.2s;
        }
        .view-btn:hover { border-color: var(--accent); color: var(--accent); }
        .view-btn.active { background: var(--accent); color: #fff; border-color: var(--accent); }

        /* Timeline */
        .timeline-view { display: none; padding: 24px; }
        .timeline-view.active { display: block; }
        .timeline-item {
            display: flex; gap: 16px; padding: 16px 0;
            border-bottom: 1px solid var(--border-color);
        }
        .timeline-item:last-child { border-bottom: none; }
        .timeline-dot {
            width: 12px; height: 12px; border-radius: 50%;
            background: var(--accent); margin-top: 4px; flex-shrink: 0;
            position: relative;
        }
        .timeline-dot::after {
            content: ''; position: absolute; left: 5px; top: 12px;
            width: 2px; height: calc(100% + 20px);
            background: var(--border-color);
        }
        .timeline-item:last-child .timeline-dot::after { display: none; }
        .timeline-content { flex: 1; }
        .timeline-header {
            display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
            margin-bottom: 4px;
        }
        .timeline-time { font-size: 12px; color: var(--text-muted); }
        .timeline-detail { font-size: 13px; color: var(--text-secondary); }
        .timeline-file { font-size: 12px; color: var(--accent); margin-top: 2px; }
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
            <a href="upload.php" class="nav-link">📁 Upload</a>
            <a href="select_file.php" class="nav-link">📋 ไฟล์ทั้งหมด</a>
            <?php if (in_array(trim($_SESSION['department'] ?? ''), ['IT','HR'], true)): ?>
            <a href="logdraw.php" class="nav-link active">📜 Activity Logs</a>
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
        <h2>📜 Activity Logs</h2>
        <p>ประวัติการกระทำทั้งหมดในระบบ Drawing — อัปโหลด, ลบ, บันทึก, เปลี่ยนสถานะ, ดาวน์โหลด, ใช้เครื่องมือ และอื่นๆ</p>
    </div>

    <!-- STATS -->
    <div class="stats-grid">
        <div class="stat-card" data-filter="" onclick="filterByType('')">
            <div class="stat-icon">📊</div>
            <div class="stat-count" id="statTotal"><?= $totalLogs ?></div>
            <div class="stat-label">ทั้งหมด</div>
        </div>
        <div class="stat-card" data-filter="upload" onclick="filterByType('upload')">
            <div class="stat-icon">📤</div>
            <div class="stat-count"><?= $statsMap['upload'] ?? 0 ?></div>
            <div class="stat-label">อัปโหลด</div>
        </div>
        <div class="stat-card" data-filter="delete" onclick="filterByType('delete')">
            <div class="stat-icon">🗑️</div>
            <div class="stat-count"><?= $statsMap['delete'] ?? 0 ?></div>
            <div class="stat-label">ลบไฟล์</div>
        </div>
        <div class="stat-card" data-filter="save_drawing" onclick="filterByType('save_drawing')">
            <div class="stat-icon">💾</div>
            <div class="stat-count"><?= $statsMap['save_drawing'] ?? 0 ?></div>
            <div class="stat-label">บันทึก Drawing</div>
        </div>
        <div class="stat-card" data-filter="clear_drawing" onclick="filterByType('clear_drawing')">
            <div class="stat-icon">🧹</div>
            <div class="stat-count"><?= $statsMap['clear_drawing'] ?? 0 ?></div>
            <div class="stat-label">ล้าง Drawing</div>
        </div>
        <div class="stat-card" data-filter="status_change" onclick="filterByType('status_change')">
            <div class="stat-icon">🔄</div>
            <div class="stat-count"><?= $statsMap['status_change'] ?? 0 ?></div>
            <div class="stat-label">เปลี่ยนสถานะ</div>
        </div>
        <div class="stat-card" data-filter="download" onclick="filterByType('download')">
            <div class="stat-icon">📥</div>
            <div class="stat-count"><?= $statsMap['download'] ?? 0 ?></div>
            <div class="stat-label">ดาวน์โหลด</div>
        </div>
        <div class="stat-card" data-filter="view" onclick="filterByType('view')">
            <div class="stat-icon">👁️</div>
            <div class="stat-count"><?= $statsMap['view'] ?? 0 ?></div>
            <div class="stat-label">เปิดดู</div>
        </div>
        <div class="stat-card" data-filter="tool_use" onclick="filterByType('tool_use')">
            <div class="stat-icon">🔧</div>
            <div class="stat-count"><?= $statsMap['tool_use'] ?? 0 ?></div>
            <div class="stat-label">ใช้เครื่องมือ</div>
        </div>
    </div>

    <!-- FILTER BAR -->
    <div class="filter-bar">
        <div class="filter-row">
            <div class="search-wrapper">
                <span class="search-icon">🔍</span>
                <input type="text" class="search-input" id="searchInput" placeholder="ค้นหาชื่อไฟล์, ผู้ใช้, รายละเอียด...">
            </div>
            <select class="filter-select" id="filterAction">
                <option value="">📌 กิจกรรมทั้งหมด</option>
                <option value="upload">📤 อัปโหลด</option>
                <option value="delete">🗑️ ลบไฟล์</option>
                <option value="save_drawing">💾 บันทึก Drawing</option>
                <option value="clear_drawing">🧹 ล้าง Drawing</option>
                <option value="status_change">🔄 เปลี่ยนสถานะ</option>
                <option value="download">📥 ดาวน์โหลด</option>
                <option value="view">👁️ เปิดดู</option>
                <option value="tool_use">🔧 ใช้เครื่องมือ</option>
                <option value="comment">💬 คอมเมนต์</option>
            </select>
            <select class="filter-select" id="filterUser">
                <option value="">👤 ผู้ใช้ทั้งหมด</option>
                <?php foreach ($logUsers as $lu): ?>
                    <option value="<?= $lu['user_id'] ?>"><?= htmlspecialchars($lu['display_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-row">
            <input type="date" class="date-input" id="dateFrom" title="วันที่เริ่มต้น">
            <span style="color:var(--text-muted); font-size:13px;">ถึง</span>
            <input type="date" class="date-input" id="dateTo" title="วันที่สิ้นสุด">
            <button class="btn-filter" onclick="loadLogs(1)">🔍 ค้นหา</button>
            <button class="btn-reset" onclick="resetFilters()">↻ ล้างตัวกรอง</button>

            <div class="view-toggle">
                <button class="view-btn active" id="btnTableView" onclick="switchView('table')">📋 ตาราง</button>
                <button class="view-btn" id="btnTimelineView" onclick="switchView('timeline')">📅 Timeline</button>
            </div>
        </div>
    </div>

    <!-- LOG CONTENT -->
    <div class="log-card">
        <!-- Loading -->
        <div class="loading-overlay" id="loadingOverlay">
            <div class="spinner"></div>
            กำลังโหลดข้อมูล...
        </div>

        <!-- Table View -->
        <div class="log-table-wrapper" id="tableView">
            <table class="log-table">
                <thead>
                    <tr>
                        <th style="width:50px">#</th>
                        <th>เวลา</th>
                        <th>ผู้ใช้</th>
                        <th>กิจกรรม</th>
                        <th>รายละเอียด</th>
                        <th>ไฟล์</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody id="logTableBody">
                    <tr>
                        <td colspan="7">
                            <div class="empty-state">
                                <div class="empty-icon">📜</div>
                                <h3>กำลังโหลด...</h3>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Timeline View -->
        <div class="timeline-view" id="timelineView"></div>

        <!-- Pagination -->
        <div class="pagination" id="pagination"></div>
    </div>

</div>

<div class="footer">
    © 2026 Lolane Co., Ltd. | Powered by <span class="footer-brand">ALPHABET</span>
</div>

<script>
// ================================
// STATE
// ================================
let currentPage = 1;
let currentView = 'table';
let searchTimeout = null;

const actionLabels = {
    upload:        '📤 อัปโหลด',
    delete:        '🗑️ ลบไฟล์',
    save_drawing:  '💾 บันทึก Drawing',
    clear_drawing: '🧹 ล้าง Drawing',
    status_change: '🔄 เปลี่ยนสถานะ',
    download:      '📥 ดาวน์โหลด',
    view:          '👁️ เปิดดู',
    tool_use:      '🔧 ใช้เครื่องมือ',
    comment:       '💬 คอมเมนต์',
};

// ================================
// LOAD LOGS
// ================================
async function loadLogs(page = 1) {
    currentPage = page;
    const loading = document.getElementById('loadingOverlay');
    loading.classList.add('active');

    const params = new URLSearchParams();
    params.set('page', page);
    params.set('limit', '20');

    const search     = document.getElementById('searchInput').value.trim();
    const actionType = document.getElementById('filterAction').value;
    const userId     = document.getElementById('filterUser').value;
    const dateFrom   = document.getElementById('dateFrom').value;
    const dateTo     = document.getElementById('dateTo').value;

    if (search)     params.set('search', search);
    if (actionType) params.set('action_type', actionType);
    if (userId)     params.set('filter_user', userId);
    if (dateFrom)   params.set('date_from', dateFrom);
    if (dateTo)     params.set('date_to', dateTo);

    try {
        const res  = await fetch('get_activity_logs.php?' + params.toString());
        const data = await res.json();

        if (data.success) {
            renderTable(data.logs, data.total, data.page, data.totalPages);
            renderTimeline(data.logs);
            renderPagination(data.page, data.totalPages, data.total);
            updateStats(data.stats, data.total);
        } else {
            showEmpty('เกิดข้อผิดพลาด: ' + (data.error || ''));
        }
    } catch(e) {
        showEmpty('ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้');
        console.error(e);
    }

    loading.classList.remove('active');
}

// ================================
// RENDER TABLE
// ================================
function renderTable(logs, total, page, totalPages) {
    const tbody = document.getElementById('logTableBody');
    if (!logs.length) {
        tbody.innerHTML = `<tr><td colspan="7">
            <div class="empty-state">
                <div class="empty-icon">📭</div>
                <h3>ไม่พบข้อมูล</h3>
                <p>ลองเปลี่ยนตัวกรองหรือค้นหาด้วยคำอื่น</p>
            </div>
        </td></tr>`;
        return;
    }

    const startNum = (page - 1) * 20;
    tbody.innerHTML = logs.map((log, i) => {
        const dt = formatDateTime(log.created_at);
        const initial = (log.display_name || '?').charAt(0).toUpperCase();
        const actionClass = log.action_type.replace(/[^a-z_]/g, '');
        const actionLabel = actionLabels[log.action_type] || log.action_type;
        const fileLink = log.file_id
            ? `<a href="view_file.php?id=${log.file_id}" class="file-link" title="${escapeHtml(log.file_name || '')}">${escapeHtml(truncate(log.file_name || 'ไฟล์ #'+log.file_id, 30))}</a>`
            : '<span style="color:var(--text-muted)">—</span>';

        return `<tr>
            <td style="color:var(--text-muted); font-size:11px;">${startNum + i + 1}</td>
            <td class="time-cell">
                <div class="time-date">${dt.date}</div>
                ${dt.time}
            </td>
            <td>
                <div class="user-cell">
                    <div class="user-avatar">${initial}</div>
                    <div>
                        <div class="user-name">${escapeHtml(log.display_name)}</div>
                        <div class="user-email">${escapeHtml(log.email || '')}</div>
                    </div>
                </div>
            </td>
            <td><span class="action-badge ${actionClass}">${actionLabel}</span></td>
            <td><div class="detail-text" title="${escapeHtml(log.action_detail || '')}">${escapeHtml(log.action_detail || '—')}</div></td>
            <td>${fileLink}</td>
            <td class="ip-cell">${escapeHtml(log.ip_address || '—')}</td>
        </tr>`;
    }).join('');
}

// ================================
// RENDER TIMELINE
// ================================
function renderTimeline(logs) {
    const el = document.getElementById('timelineView');
    if (!logs.length) {
        el.innerHTML = `<div class="empty-state">
            <div class="empty-icon">📭</div>
            <h3>ไม่พบข้อมูล</h3>
        </div>`;
        return;
    }

    el.innerHTML = logs.map(log => {
        const dt = formatDateTime(log.created_at);
        const actionClass = log.action_type.replace(/[^a-z_]/g, '');
        const actionLabel = actionLabels[log.action_type] || log.action_type;
        const fileInfo = log.file_name
            ? `<div class="timeline-file">📎 ${escapeHtml(log.file_name)}</div>`
            : '';

        return `<div class="timeline-item">
            <div class="timeline-dot"></div>
            <div class="timeline-content">
                <div class="timeline-header">
                    <span class="action-badge ${actionClass}">${actionLabel}</span>
                    <strong>${escapeHtml(log.display_name)}</strong>
                    <span class="timeline-time">${dt.date} ${dt.time}</span>
                </div>
                <div class="timeline-detail">${escapeHtml(log.action_detail || '')}</div>
                ${fileInfo}
            </div>
        </div>`;
    }).join('');
}

// ================================
// RENDER PAGINATION
// ================================
function renderPagination(page, totalPages, total) {
    const el = document.getElementById('pagination');
    if (totalPages <= 1) { el.innerHTML = ''; return; }

    let html = '';
    html += `<button class="page-btn" onclick="loadLogs(1)" ${page===1?'disabled':''}">« แรก</button>`;
    html += `<button class="page-btn" onclick="loadLogs(${page-1})" ${page===1?'disabled':''}>‹ ก่อน</button>`;

    // Show page numbers
    const start = Math.max(1, page - 2);
    const end   = Math.min(totalPages, page + 2);
    for (let i = start; i <= end; i++) {
        html += `<button class="page-btn ${i===page?'active':''}" onclick="loadLogs(${i})">${i}</button>`;
    }

    html += `<button class="page-btn" onclick="loadLogs(${page+1})" ${page>=totalPages?'disabled':''}>ถัดไป ›</button>`;
    html += `<button class="page-btn" onclick="loadLogs(${totalPages})" ${page>=totalPages?'disabled':''}>สุดท้าย »</button>`;
    html += `<span class="page-info">หน้า ${page}/${totalPages} (${total.toLocaleString()} รายการ)</span>`;

    el.innerHTML = html;
}

// ================================
// UPDATE STATS
// ================================
function updateStats(stats, total) {
    // Highlight active filter card
    const active = document.getElementById('filterAction').value;
    document.querySelectorAll('.stat-card').forEach(card => {
        card.classList.toggle('active', card.dataset.filter === active);
    });
}

// ================================
// FILTER HELPERS
// ================================
function filterByType(type) {
    document.getElementById('filterAction').value = type;
    // Highlight
    document.querySelectorAll('.stat-card').forEach(card => {
        card.classList.toggle('active', card.dataset.filter === type);
    });
    loadLogs(1);
}

function resetFilters() {
    document.getElementById('searchInput').value   = '';
    document.getElementById('filterAction').value   = '';
    document.getElementById('filterUser').value     = '';
    document.getElementById('dateFrom').value       = '';
    document.getElementById('dateTo').value         = '';
    document.querySelectorAll('.stat-card').forEach(c => c.classList.remove('active'));
    loadLogs(1);
}

function switchView(view) {
    currentView = view;
    const tableEl    = document.getElementById('tableView');
    const timelineEl = document.getElementById('timelineView');
    document.getElementById('btnTableView').classList.toggle('active', view === 'table');
    document.getElementById('btnTimelineView').classList.toggle('active', view === 'timeline');

    if (view === 'table') {
        tableEl.style.display    = '';
        timelineEl.classList.remove('active');
    } else {
        tableEl.style.display    = 'none';
        timelineEl.classList.add('active');
    }
}

function showEmpty(msg) {
    document.getElementById('logTableBody').innerHTML = `<tr><td colspan="7">
        <div class="empty-state">
            <div class="empty-icon">⚠️</div>
            <h3>${escapeHtml(msg)}</h3>
        </div>
    </td></tr>`;
}

// ================================
// UTILITIES
// ================================
function formatDateTime(str) {
    if (!str) return { date: '—', time: '' };
    const d = new Date(str);
    const pad = n => String(n).padStart(2, '0');
    return {
        date: `${pad(d.getDate())}/${pad(d.getMonth()+1)}/${d.getFullYear()}`,
        time: `${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`,
    };
}

function truncate(str, max) {
    return str.length > max ? str.substring(0, max) + '…' : str;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ================================
// SEARCH DEBOUNCE
// ================================
document.getElementById('searchInput').addEventListener('input', () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => loadLogs(1), 400);
});

// Enter key on search
document.getElementById('searchInput').addEventListener('keydown', e => {
    if (e.key === 'Enter') {
        clearTimeout(searchTimeout);
        loadLogs(1);
    }
});

// ================================
// PROFILE DROPDOWN
// ================================
const profileWrapper = document.getElementById('profileWrapper');
const profileTrigger = document.getElementById('profileTrigger');
const overlay = document.getElementById('dropdownOverlay');
profileTrigger.addEventListener('click', e => {
    e.stopPropagation();
    profileWrapper.classList.toggle('open');
    overlay.classList.toggle('active');
});
overlay.addEventListener('click', closeDropdown);
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDropdown(); });
function closeDropdown() {
    profileWrapper.classList.remove('open');
    overlay.classList.remove('active');
}

// ================================
// THEME
// ================================
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
themeToggle.addEventListener('change', () => applyTheme(themeToggle.checked ? 'dark' : 'light'));
function toggleThemeFromMenu() { applyTheme(html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark'); }
loadTheme();

// ================================
// INIT
// ================================
loadLogs(1);
</script>
</body>
</html>
