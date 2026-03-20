<?php
require_once 'config.php';
requireLogin();
require_once 'get_avatar.php';

$displayName = htmlspecialchars($_SESSION['display_name'] ?? 'User');
$email       = htmlspecialchars($_SESSION['email'] ?? '');
$initial     = mb_substr($displayName, 0, 1);
$userId      = $_SESSION['user_id'];

// ★★★ ตรวจสอบสิทธิ์ — เฉพาะแผนก IT และ HR เท่านั้น ★★★
$currentUserStmt = $pdo->prepare("SELECT department FROM users WHERE id = :id");
$currentUserStmt->execute([':id' => $userId]);
$currentUser = $currentUserStmt->fetch();
$myDepartment = trim($currentUser['department'] ?? '');

$allowedDepts = ['IT', 'it', 'HR', 'hr', 'Information Technology', 'Human Resources', 'ไอที', 'ฝ่ายบุคคล'];
$hasAccess = false;
foreach ($allowedDepts as $dept) {
    if (stripos($myDepartment, $dept) !== false) {
        $hasAccess = true;
        break;
    }
}

if (!$hasAccess) {
    // redirect ไปหน้าหลักพร้อม message
    header('Location: index.php?error=no_permission');
    exit;
}

// ดึงข้อมูล users ทั้งหมด
$search     = trim($_GET['search'] ?? '');
$filterDept = trim($_GET['dept'] ?? '');

$where  = [];
$params = [];

if ($search) {
    $where[]  = '(u.display_name LIKE :s1 OR u.email LIKE :s2 OR u.first_name LIKE :s3 OR u.last_name LIKE :s4)';
    $params[':s1'] = "%{$search}%";
    $params[':s2'] = "%{$search}%";
    $params[':s3'] = "%{$search}%";
    $params[':s4'] = "%{$search}%";
}
if ($filterDept) {
    $where[]  = 'u.department = :dept';
    $params[':dept'] = $filterDept;
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $pdo->prepare(
    "SELECT u.id, u.display_name, u.email, u.first_name, u.last_name,
            u.department, u.position, u.job_title, u.phone, u.mobile_phone,
            u.office_location, u.last_login, u.created_at, u.avatar,
            (SELECT COUNT(*) FROM uploads up WHERE up.user_id = u.id) as file_count
     FROM users u
     {$whereSQL}
     ORDER BY u.display_name ASC"
);
$stmt->execute($params);
$users = $stmt->fetchAll();

$totalUsers = count($users);

// ดึงแผนกทั้งหมดสำหรับ filter
$deptStmt = $pdo->query("SELECT DISTINCT department FROM users WHERE department IS NOT NULL AND department != '' ORDER BY department");
$departments = $deptStmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="th" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการผู้ใช้งาน - Lolane Portal</title>

    <link rel="icon" type="image/jpeg" href="img/logo.jpg">
    <link rel="shortcut icon" type="image/jpeg" href="img/logo.jpg">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root, [data-theme="light"] {
            --bg-primary: #fef7f0; --bg-secondary: #ffffff;
            --bg-navbar: linear-gradient(135deg, #ff8c42, #ff6b35);
            --bg-card: #ffffff; --bg-hover: #fff5ec; --bg-dropdown: #ffffff;
            --text-primary: #2d2d2d; --text-secondary: #777777; --text-muted: #b0885a;
            --border-color: #ffecd2;
            --shadow: 0 4px 20px rgba(255, 140, 66, 0.08);
            --shadow-lg: 0 10px 40px rgba(255, 140, 66, 0.15);
            --accent: #ff6b35; --accent-light: #fff0e5; --accent-hover: #e85d2c;
            --navbar-shadow: 0 4px 20px rgba(255, 107, 53, 0.3);
        }
        [data-theme="dark"] {
            --bg-primary: #1a1208; --bg-secondary: #261c0e;
            --bg-navbar: linear-gradient(135deg, #b85a1e, #8c3d0f);
            --bg-card: #2a1f10; --bg-hover: #332814; --bg-dropdown: #2a1f10;
            --text-primary: #ffecd2; --text-secondary: #c4a882; --text-muted: #8a7050;
            --border-color: #3d2e18;
            --shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            --shadow-lg: 0 10px 40px rgba(0, 0, 0, 0.5);
            --accent: #ff8c42; --accent-light: #3d2e18; --accent-hover: #ffa05c;
            --navbar-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Kanit', sans-serif; background: var(--bg-primary); color: var(--text-primary); min-height: 100vh; transition: background 0.4s, color 0.4s; }
        a { color: inherit; text-decoration: none; }

        /* NAVBAR */
        .navbar { background: var(--bg-navbar); color: white; padding: 0 32px; height: 64px; display: flex; justify-content: space-between; align-items: center; box-shadow: var(--navbar-shadow); position: sticky; top: 0; z-index: 1000; }
        .nav-left { display: flex; align-items: center; gap: 28px; }
        .nav-logo { display: flex; align-items: center; gap: 10px; font-size: 20px; font-weight: 600; color: white; }
        .nav-logo:hover { opacity: 0.9; }
        .nav-logo-img { width: 36px; height: 36px; border-radius: 8px; object-fit: cover; border: 2px solid rgba(255,255,255,0.4); }
        .nav-links { display: flex; gap: 4px; }
        .nav-link { color: rgba(255,255,255,0.8); padding: 8px 16px; border-radius: 8px; font-size: 14px; transition: all 0.2s; }
        .nav-link:hover, .nav-link.active { background: rgba(255,255,255,0.2); color: white; }
        .nav-right { display: flex; align-items: center; gap: 14px; }

        .theme-toggle { position: relative; width: 52px; height: 26px; cursor: pointer; }
        .theme-toggle input { display: none; }
        .toggle-slider { position: absolute; inset: 0; background: rgba(255,255,255,0.25); border-radius: 13px; transition: all 0.3s; }
        .toggle-slider::before { content: "☀️"; font-size: 12px; position: absolute; top: 2px; left: 3px; width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; background: #fff; border-radius: 50%; transition: transform 0.3s; }
        .theme-toggle input:checked + .toggle-slider::before { content: "🌙"; transform: translateX(25px); background: #2a1f10; }

        .profile-wrapper { position: relative; }
        .profile-trigger { display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 5px 12px; border-radius: 12px; transition: background 0.2s; border: none; background: none; color: white; font-family: 'Kanit', sans-serif; }
        .profile-trigger:hover { background: rgba(255,255,255,0.15); }
        .profile-avatar { width: 36px; height: 36px; border-radius: 50%; background: rgba(255,255,255,0.25); border: 2px solid rgba(255,255,255,0.5); display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 14px; overflow: hidden; flex-shrink: 0; }
        .profile-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .profile-info { text-align: left; line-height: 1.3; }
        .profile-name { font-weight: 500; font-size: 13px; }
        .profile-email { font-size: 11px; opacity: 0.75; }
        .profile-arrow { font-size: 10px; transition: transform 0.2s; opacity: 0.7; }
        .profile-wrapper.open .profile-arrow { transform: rotate(180deg); }
        .profile-dropdown { position: absolute; top: calc(100% + 10px); right: 0; background: var(--bg-dropdown); border: 1px solid var(--border-color); border-radius: 14px; box-shadow: var(--shadow-lg); min-width: 270px; opacity: 0; visibility: hidden; transform: translateY(-10px); transition: all 0.25s; z-index: 999; overflow: hidden; }
        .profile-wrapper.open .profile-dropdown { opacity: 1; visibility: visible; transform: translateY(0); }
        .dropdown-menu { padding: 6px 0; }
        .dropdown-item { display: flex; align-items: center; gap: 10px; padding: 11px 20px; font-family: 'Kanit', sans-serif; font-size: 14px; color: var(--text-primary); transition: background 0.15s; cursor: pointer; border: none; background: none; width: 100%; text-align: left; }
        .dropdown-item:hover { background: var(--bg-hover); }
        .dropdown-item .icon { font-size: 16px; width: 22px; text-align: center; }
        .dropdown-divider { height: 1px; background: var(--border-color); margin: 4px 0; }
        .dropdown-item.danger { color: #e74c3c; }
        .dropdown-item.danger:hover { background: #fff0ee; }
        [data-theme="dark"] .dropdown-item.danger:hover { background: #3d1c1c; }
        .dropdown-overlay { display: none; position: fixed; inset: 0; z-index: 998; }
        .dropdown-overlay.active { display: block; }

        /* CONTAINER */
        .container { max-width: 1400px; margin: 24px auto; padding: 0 24px; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px; }
        .page-header-left h2 { font-size: 24px; font-weight: 600; margin-bottom: 2px; }
        .page-header-left p { font-size: 14px; color: var(--text-muted); font-weight: 300; }
        .badge-admin { display: inline-flex; align-items: center; gap: 6px; padding: 4px 14px; border-radius: 20px; font-size: 12px; font-weight: 500; background: #e8f5e9; color: #2e7d32; border: 1px solid #a5d6a7; }
        [data-theme="dark"] .badge-admin { background: #1b3d1f; color: #81c784; border-color: #2e5e32; }

        /* STATS */
        .stats-row { display: flex; gap: 14px; margin-bottom: 24px; flex-wrap: wrap; }
        .stat-mini { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; padding: 16px 24px; box-shadow: var(--shadow); display: flex; align-items: center; gap: 14px; flex: 1; min-width: 180px; }
        .stat-mini-icon { font-size: 28px; }
        .stat-mini-info .stat-mini-count { font-size: 22px; font-weight: 700; color: var(--accent); }
        .stat-mini-info .stat-mini-label { font-size: 12px; color: var(--text-muted); }

        /* FILTER */
        .filter-bar { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 14px; padding: 16px 24px; margin-bottom: 20px; box-shadow: var(--shadow); display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
        .search-wrapper { flex: 1; min-width: 220px; position: relative; }
        .search-wrapper .search-icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); font-size: 16px; color: var(--text-muted); pointer-events: none; }
        .search-input { width: 100%; padding: 10px 14px 10px 42px; border: 1px solid var(--border-color); border-radius: 10px; font-family: 'Kanit', sans-serif; font-size: 14px; background: var(--bg-primary); color: var(--text-primary); transition: border-color 0.2s; }
        .search-input:focus { outline: none; border-color: var(--accent); }
        .search-input::placeholder { color: var(--text-muted); }
        .filter-select { padding: 10px 14px; border: 1px solid var(--border-color); border-radius: 10px; font-family: 'Kanit', sans-serif; font-size: 13px; background: var(--bg-primary); color: var(--text-primary); cursor: pointer; min-width: 160px; }
        .filter-select:focus { outline: none; border-color: var(--accent); }
        .btn-search { padding: 10px 20px; border: none; border-radius: 10px; background: var(--accent); color: #fff; font-family: 'Kanit', sans-serif; font-size: 13px; font-weight: 500; cursor: pointer; transition: all 0.2s; }
        .btn-search:hover { background: var(--accent-hover); }
        .btn-reset { padding: 10px 20px; border: 1px solid var(--border-color); border-radius: 10px; background: var(--bg-primary); color: var(--text-muted); font-family: 'Kanit', sans-serif; font-size: 13px; cursor: pointer; transition: all 0.2s; }
        .btn-reset:hover { border-color: var(--accent); color: var(--accent); }

        /* USER TABLE */
        .users-card { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 14px; box-shadow: var(--shadow); overflow: hidden; }
        .users-table-wrapper { overflow-x: auto; }
        .users-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .users-table thead th { background: var(--accent-light); color: var(--text-primary); padding: 14px 16px; text-align: left; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid var(--border-color); white-space: nowrap; }
        .users-table tbody tr { border-bottom: 1px solid var(--border-color); transition: background 0.15s; }
        .users-table tbody tr:hover { background: var(--bg-hover); }
        .users-table tbody tr:last-child { border-bottom: none; }
        .users-table td { padding: 12px 16px; vertical-align: middle; }

        .user-cell { display: flex; align-items: center; gap: 12px; }
        .user-cell-avatar { width: 38px; height: 38px; border-radius: 50%; background: var(--accent-light); border: 2px solid var(--border-color); display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 600; color: var(--accent); overflow: hidden; flex-shrink: 0; }
        .user-cell-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .user-cell-info .user-cell-name { font-weight: 500; font-size: 14px; }
        .user-cell-info .user-cell-email { font-size: 11px; color: var(--text-muted); }

        .dept-badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 16px; font-size: 11px; font-weight: 500; background: var(--accent-light); color: var(--accent); border: 1px solid var(--border-color); white-space: nowrap; }
        .pos-text { font-size: 13px; color: var(--text-secondary); max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .login-time { font-size: 12px; color: var(--text-muted); white-space: nowrap; }
        .file-count { display: inline-flex; align-items: center; gap: 4px; padding: 2px 10px; border-radius: 12px; font-size: 12px; font-weight: 500; background: #e3f2fd; color: #1565c0; border: 1px solid #90caf9; }
        [data-theme="dark"] .file-count { background: #0a2a3d; color: #64b5f6; border-color: #1a3d5e; }

        .btn-edit { display: inline-flex; align-items: center; gap: 6px; padding: 6px 16px; border: 1px solid var(--accent); border-radius: 8px; color: var(--accent); font-family: 'Kanit', sans-serif; font-size: 12px; font-weight: 500; background: none; cursor: pointer; transition: all 0.2s; }
        .btn-edit:hover { background: var(--accent); color: #fff; }
        .btn-delete { display: inline-flex; align-items: center; gap: 6px; padding: 6px 16px; border: 1px solid #e74c3c; border-radius: 8px; color: #e74c3c; font-family: 'Kanit', sans-serif; font-size: 12px; font-weight: 500; background: none; cursor: pointer; transition: all 0.2s; }
        .btn-delete:hover { background: #e74c3c; color: #fff; }
        .action-cell { display: flex; gap: 8px; align-items: center; }

        /* EMPTY */
        .empty-state { text-align: center; padding: 60px 24px; color: var(--text-muted); }
        .empty-icon { font-size: 48px; margin-bottom: 12px; }
        .empty-state h3 { font-size: 16px; font-weight: 500; margin-bottom: 6px; color: var(--text-primary); }
        .empty-state p { font-size: 13px; font-weight: 300; }

        /* FOOTER */
        .footer { text-align: center; padding: 20px; color: var(--text-muted); font-size: 12px; font-weight: 300; border-top: 1px solid var(--border-color); margin-top: 40px; }
        .footer-brand { color: var(--accent); font-weight: 500; }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            .navbar { padding: 0 16px; }
            .nav-links { display: none; }
            .container { padding: 0 12px; }
            .stats-row { flex-direction: column; }
            .filter-bar { flex-direction: column; }
            .search-wrapper { min-width: 100%; }
            .profile-info { display: none; }
            .page-header { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>

<div class="dropdown-overlay" id="dropdownOverlay"></div>

<nav class="navbar">
    <div class="nav-left">
        <a href="index.php" class="nav-logo">
            <img src="img/logo.jpg" alt="Lolane" class="nav-logo-img">
            Lolane Portal
        </a>
        <div class="nav-links">
            <a href="index.php" class="nav-link">🏠 หน้าหลัก</a>
            <a href="manage_users.php" class="nav-link active">👥 จัดการผู้ใช้</a>
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
                    <a href="profile.php" class="dropdown-item"><span class="icon">👤</span> โปรไฟล์ของฉัน</a>
                    <a href="manage_users.php" class="dropdown-item"><span class="icon">👥</span> จัดการผู้ใช้</a>
                    <button class="dropdown-item" onclick="toggleThemeFromMenu()">
                        <span class="icon" id="themeMenuIcon">🌙</span>
                        <span id="themeMenuText">Dark Mode</span>
                    </button>
                    <div class="dropdown-divider"></div>
                    <a href="logout.php" class="dropdown-item danger"><span class="icon">🚪</span> ออกจากระบบ</a>
                </div>
            </div>
        </div>
    </div>
</nav>

<div class="container">

    <div class="page-header">
        <div class="page-header-left">
            <h2>👥 จัดการผู้ใช้งาน</h2>
            <p>ดูและแก้ไขข้อมูลผู้ใช้ทั้งหมดในระบบ</p>
        </div>
        <span class="badge-admin">🔒 เฉพาะแผนก <?= htmlspecialchars($myDepartment) ?></span>
    </div>

    <!-- STATS -->
    <div class="stats-row">
        <div class="stat-mini">
            <div class="stat-mini-icon">👥</div>
            <div class="stat-mini-info">
                <div class="stat-mini-count"><?= $totalUsers ?></div>
                <div class="stat-mini-label">ผู้ใช้ทั้งหมด</div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon">🏢</div>
            <div class="stat-mini-info">
                <div class="stat-mini-count"><?= count($departments) ?></div>
                <div class="stat-mini-label">จำนวนแผนก</div>
            </div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-icon">🕐</div>
            <div class="stat-mini-info">
                <?php
                try {
                    $todayLogins = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM login_logs WHERE DATE(login_at) = CURDATE()")->fetchColumn();
                } catch (Exception $e) {
                    $todayLogins = '—';
                }
                ?>
                <div class="stat-mini-count"><?= $todayLogins ?></div>
                <div class="stat-mini-label">เข้าใช้วันนี้</div>
            </div>
        </div>
    </div>

    <!-- FILTER -->
    <form class="filter-bar" method="GET">
        <div class="search-wrapper">
            <span class="search-icon">🔍</span>
            <input type="text" class="search-input" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="ค้นหาชื่อ, อีเมล...">
        </div>
        <select class="filter-select" name="dept">
            <option value="">🏢 แผนกทั้งหมด</option>
            <?php foreach ($departments as $d): ?>
                <option value="<?= htmlspecialchars($d) ?>" <?= $filterDept === $d ? 'selected' : '' ?>><?= htmlspecialchars($d) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn-search">🔍 ค้นหา</button>
        <a href="manage_users.php" class="btn-reset">↻ ล้าง</a>
    </form>

    <!-- USERS TABLE -->
    <div class="users-card">
        <?php if (empty($users)): ?>
            <div class="empty-state">
                <div class="empty-icon">📭</div>
                <h3>ไม่พบผู้ใช้</h3>
                <p>ลองเปลี่ยนคำค้นหาหรือตัวกรอง</p>
            </div>
        <?php else: ?>
            <div class="users-table-wrapper">
                <table class="users-table">
                    <thead>
                        <tr>
                            <th style="width:50px">#</th>
                            <th>ผู้ใช้</th>
                            <th>แผนก</th>
                            <th>ตำแหน่ง</th>
                            <th>โทรศัพท์</th>
                            <th>ไฟล์</th>
                            <th>เข้าใช้ล่าสุด</th>
                            <th>จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $i => $u): ?>
                        <tr>
                            <td style="color:var(--text-muted); font-size:12px;"><?= $i + 1 ?></td>
                            <td>
                                <div class="user-cell">
                                    <div class="user-cell-avatar">
                                        <?php if (!empty($u['avatar'])): ?>
                                            <img src="data:image/jpeg;base64,<?= $u['avatar'] ?>" alt="">
                                        <?php else: ?>
                                            <?= mb_substr($u['display_name'] ?? '?', 0, 1) ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="user-cell-info">
                                        <div class="user-cell-name"><?= htmlspecialchars($u['display_name'] ?? 'ไม่ระบุ') ?></div>
                                        <div class="user-cell-email"><?= htmlspecialchars($u['email']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($u['department'])): ?>
                                    <span class="dept-badge">🏢 <?= htmlspecialchars($u['department']) ?></span>
                                <?php else: ?>
                                    <span style="color:var(--text-muted); font-size:12px;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="pos-text"><?= htmlspecialchars($u['position'] ?? $u['job_title'] ?? '—') ?></div>
                            </td>
                            <td style="font-size:12px; color:var(--text-secondary);">
                                <?= htmlspecialchars($u['phone'] ?? $u['mobile_phone'] ?? '—') ?>
                            </td>
                            <td>
                                <span class="file-count">📁 <?= $u['file_count'] ?></span>
                            </td>
                            <td>
                                <?php if ($u['last_login']): ?>
                                    <div class="login-time">
                                        <?= date('d/m/Y', strtotime($u['last_login'])) ?><br>
                                        <?= date('H:i น.', strtotime($u['last_login'])) ?>
                                    </div>
                                <?php else: ?>
                                    <span style="color:var(--text-muted); font-size:12px;">ยังไม่เคย</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-cell">
                                    <a href="edit_user.php?id=<?= $u['id'] ?>" class="btn-edit">✏️ แก้ไข</a>
                                    <?php if ($u['id'] != $userId): ?>
                                    <button class="btn-delete" onclick="deleteUser(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['display_name'] ?? 'ผู้ใช้'), ENT_QUOTES) ?>')">
                                        🗑️ ลบ
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="footer">© 2026 Lolane Co., Ltd. | Powered by <span class="footer-brand">ALPHABET</span></div>

<script>
// Profile Dropdown
var pw = document.getElementById('profileWrapper');
var pt = document.getElementById('profileTrigger');
var ov = document.getElementById('dropdownOverlay');
pt.addEventListener('click', function(e) { e.stopPropagation(); pw.classList.toggle('open'); ov.classList.toggle('active'); });
ov.addEventListener('click', cDD);
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') cDD(); });
function cDD() { pw.classList.remove('open'); ov.classList.remove('active'); }

// Theme
var tt = document.getElementById('themeToggle');
var ti = document.getElementById('themeMenuIcon');
var tx = document.getElementById('themeMenuText');
var ht = document.documentElement;
function lT() { aT(localStorage.getItem('lolane_theme') || 'light'); }
function aT(t) { ht.setAttribute('data-theme', t); localStorage.setItem('lolane_theme', t); tt.checked = (t === 'dark'); ti.textContent = t === 'dark' ? '☀️' : '🌙'; tx.textContent = t === 'dark' ? 'Light Mode' : 'Dark Mode'; }
tt.addEventListener('change', function() { aT(tt.checked ? 'dark' : 'light'); });
function toggleThemeFromMenu() { aT(ht.getAttribute('data-theme') === 'dark' ? 'light' : 'dark'); }
lT();

// Keyboard shortcut: Ctrl+F → focus search
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
        e.preventDefault();
        var s = document.querySelector('.search-input');
        if (s) { s.focus(); s.select(); }
    }
});

// ★ Delete User
function deleteUser(uid, name) {
    if (!confirm('⚠️ ยืนยันลบผู้ใช้ "' + name + '" ?\n\nการลบจะไม่สามารถกู้คืนได้')) return;
    fetch('delete_user.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_id: uid })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('✅ ลบผู้ใช้ "' + name + '" สำเร็จ');
            location.reload();
        } else {
            alert('❌ ' + (data.error || 'เกิดข้อผิดพลาด'));
        }
    })
    .catch(() => alert('❌ เกิดข้อผิดพลาดในการเชื่อมต่อ'));
}
</script>
</body>
</html>
