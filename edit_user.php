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
    header('Location: index.php?error=no_permission');
    exit;
}

// ดึง user ID จาก query param
$editId = intval($_GET['id'] ?? 0);
if ($editId <= 0) {
    header('Location: manage_users.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
$stmt->execute([':id' => $editId]);
$editUser = $stmt->fetch();

if (!$editUser) {
    header('Location: manage_users.php?error=user_not_found');
    exit;
}

// จัดการ POST
$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_info') {
        $fields = [
            'display_name'    => trim($_POST['display_name'] ?? ''),
            'first_name'      => trim($_POST['first_name'] ?? ''),
            'last_name'       => trim($_POST['last_name'] ?? ''),
            'department'      => trim($_POST['department'] ?? ''),
            'position'        => trim($_POST['position'] ?? ''),
            'job_title'       => trim($_POST['job_title'] ?? ''),
            'phone'           => trim($_POST['phone'] ?? ''),
            'mobile_phone'    => trim($_POST['mobile_phone'] ?? ''),
            'office_location' => trim($_POST['office_location'] ?? ''),
            'bio'             => trim($_POST['bio'] ?? ''),
        ];

        if (empty($fields['display_name'])) {
            $error = 'กรุณากรอกชื่อที่แสดง';
        } else {
            try {
                $sql = "UPDATE users SET
                            display_name    = :display_name,
                            first_name      = :first_name,
                            last_name       = :last_name,
                            department      = :department,
                            position        = :position,
                            job_title       = :job_title,
                            phone           = :phone,
                            mobile_phone    = :mobile_phone,
                            office_location = :office_location,
                            bio             = :bio,
                            updated_at      = NOW()
                        WHERE id = :id";
                $upd = $pdo->prepare($sql);
                $upd->execute(array_merge($fields, [':id' => $editId]));

                // Log activity ถ้ามี log_helper
                if (file_exists(__DIR__ . '/draw/log_helper.php')) {
                    require_once __DIR__ . '/draw/log_helper.php';
                    logActivity($pdo, 'edit_user', "แก้ไขข้อมูลผู้ใช้: {$fields['display_name']} (ID: {$editId})");
                }

                $success = 'บันทึกข้อมูลเรียบร้อยแล้ว';

                // reload user data
                $stmt->execute([':id' => $editId]);
                $editUser = $stmt->fetch();
            } catch (Exception $e) {
                $error = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
            }
        }
    }
}

$eu = $editUser; // shorthand
$euAvatar = !empty($eu['avatar']) ? $eu['avatar'] : '';
$euInitial = mb_substr($eu['display_name'] ?? '?', 0, 1);
?>
<!DOCTYPE html>
<html lang="th" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไขผู้ใช้ - <?= htmlspecialchars($eu['display_name']) ?> - Lolane Portal</title>

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
        .container { max-width: 900px; margin: 24px auto; padding: 0 24px; }

        /* BACK LINK */
        .back-link { display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px; border-radius: 10px; font-size: 13px; color: var(--text-muted); transition: all 0.2s; margin-bottom: 20px; }
        .back-link:hover { background: var(--bg-hover); color: var(--accent); }

        /* USER HEADER */
        .user-header-card { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 28px; box-shadow: var(--shadow); margin-bottom: 24px; }
        .user-header { display: flex; align-items: center; gap: 20px; }
        .user-big-avatar { width: 80px; height: 80px; border-radius: 50%; background: var(--accent-light); border: 3px solid var(--accent); display: flex; align-items: center; justify-content: center; font-size: 28px; font-weight: 700; color: var(--accent); overflow: hidden; flex-shrink: 0; }
        .user-big-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .user-header-info h2 { font-size: 22px; font-weight: 600; margin-bottom: 2px; }
        .user-header-info .email { font-size: 14px; color: var(--text-muted); }
        .user-header-info .meta { display: flex; gap: 14px; margin-top: 8px; flex-wrap: wrap; }
        .meta-item { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; color: var(--text-secondary); background: var(--bg-primary); padding: 4px 12px; border-radius: 16px; border: 1px solid var(--border-color); }

        /* ALERT / SUCCESS */
        .alert { padding: 14px 20px; border-radius: 12px; font-size: 14px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; animation: slideDown 0.3s ease; }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }
        .alert-success { background: #e8f5e9; color: #2e7d32; border: 1px solid #a5d6a7; }
        .alert-error { background: #ffebee; color: #c62828; border: 1px solid #ef9a9a; }
        [data-theme="dark"] .alert-success { background: #1b3d1f; color: #81c784; border-color: #2e5e32; }
        [data-theme="dark"] .alert-error { background: #3d1c1c; color: #ef9a9a; border-color: #5e2e2e; }

        /* FORM CARD */
        .form-card { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; box-shadow: var(--shadow); overflow: hidden; }
        .form-card-header { padding: 20px 28px; border-bottom: 1px solid var(--border-color); font-size: 16px; font-weight: 600; display: flex; align-items: center; gap: 10px; }
        .form-card-body { padding: 28px; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-group.full { grid-column: 1 / -1; }
        .form-label { font-size: 13px; font-weight: 500; color: var(--text-secondary); }
        .form-label .required { color: #e74c3c; margin-left: 2px; }
        .form-input, .form-textarea { width: 100%; padding: 11px 16px; border: 1px solid var(--border-color); border-radius: 10px; font-family: 'Kanit', sans-serif; font-size: 14px; background: var(--bg-primary); color: var(--text-primary); transition: border-color 0.2s, box-shadow 0.2s; }
        .form-input:focus, .form-textarea:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(255, 107, 53, 0.1); }
        .form-input:disabled { background: var(--bg-hover); color: var(--text-muted); cursor: not-allowed; opacity: 0.7; }
        .form-input::placeholder, .form-textarea::placeholder { color: var(--text-muted); font-weight: 300; }
        .form-textarea { resize: vertical; min-height: 80px; }
        .form-hint { font-size: 11px; color: var(--text-muted); font-weight: 300; }

        /* Button row */
        .form-actions { display: flex; justify-content: flex-end; gap: 12px; padding: 20px 28px; border-top: 1px solid var(--border-color); }
        .btn { padding: 11px 28px; border-radius: 10px; font-family: 'Kanit', sans-serif; font-size: 14px; font-weight: 500; border: none; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px; }
        .btn-primary { background: var(--accent); color: #fff; }
        .btn-primary:hover { background: var(--accent-hover); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(255, 107, 53, 0.3); }
        .btn-secondary { background: var(--bg-primary); color: var(--text-secondary); border: 1px solid var(--border-color); }
        .btn-secondary:hover { border-color: var(--accent); color: var(--accent); }

        /* READONLY INFO */
        .info-card { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; box-shadow: var(--shadow); margin-top: 24px; overflow: hidden; }
        .info-card-header { padding: 20px 28px; border-bottom: 1px solid var(--border-color); font-size: 16px; font-weight: 600; display: flex; align-items: center; gap: 10px; }
        .info-grid { padding: 20px 28px; display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .info-item { }
        .info-item-label { font-size: 11px; font-weight: 400; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px; }
        .info-item-value { font-size: 14px; font-weight: 400; word-break: break-all; }
        .info-item-value.dim { color: var(--text-muted); font-style: italic; }

        /* FOOTER */
        .footer { text-align: center; padding: 20px; color: var(--text-muted); font-size: 12px; font-weight: 300; border-top: 1px solid var(--border-color); margin-top: 40px; }
        .footer-brand { color: var(--accent); font-weight: 500; }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            .navbar { padding: 0 16px; }
            .nav-links { display: none; }
            .container { padding: 0 12px; }
            .form-grid, .info-grid { grid-template-columns: 1fr; }
            .profile-info { display: none; }
            .user-header { flex-direction: column; text-align: center; }
            .user-header-info .meta { justify-content: center; }
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
            <a href="dashboard.php" class="nav-link">📊 Dashboard</a>
            <a href="draw/upload.php" class="nav-link">📁 Upload</a>
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

    <a href="manage_users.php" class="back-link">← กลับหน้าจัดการผู้ใช้</a>

    <?php if ($success): ?>
        <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error">❌ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- USER HEADER -->
    <div class="user-header-card">
        <div class="user-header">
            <div class="user-big-avatar">
                <?php if ($euAvatar): ?>
                    <img src="data:image/jpeg;base64,<?= $euAvatar ?>" alt="">
                <?php else: ?>
                    <?= $euInitial ?>
                <?php endif; ?>
            </div>
            <div class="user-header-info">
                <h2><?= htmlspecialchars($eu['display_name'] ?? 'ไม่ระบุ') ?></h2>
                <div class="email"><?= htmlspecialchars($eu['email']) ?></div>
                <div class="meta">
                    <?php if (!empty($eu['department'])): ?>
                        <span class="meta-item">🏢 <?= htmlspecialchars($eu['department']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($eu['position'])): ?>
                        <span class="meta-item">💼 <?= htmlspecialchars($eu['position']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($eu['last_login'])): ?>
                        <span class="meta-item">🕐 เข้าใช้ล่าสุด: <?= date('d/m/Y H:i', strtotime($eu['last_login'])) ?></span>
                    <?php endif; ?>
                    <span class="meta-item">📅 สร้างเมื่อ: <?= date('d/m/Y', strtotime($eu['created_at'])) ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- EDIT FORM -->
    <form method="POST" id="editForm">
        <input type="hidden" name="action" value="update_info">
        <div class="form-card">
            <div class="form-card-header">✏️ แก้ไขข้อมูลผู้ใช้</div>
            <div class="form-card-body">
                <div class="form-grid">

                    <div class="form-group full">
                        <label class="form-label">ชื่อที่แสดง <span class="required">*</span></label>
                        <input class="form-input" type="text" name="display_name"
                               value="<?= htmlspecialchars($eu['display_name'] ?? '') ?>"
                               placeholder="Display Name" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">ชื่อ (First Name)</label>
                        <input class="form-input" type="text" name="first_name"
                               value="<?= htmlspecialchars($eu['first_name'] ?? '') ?>"
                               placeholder="ชื่อ">
                    </div>

                    <div class="form-group">
                        <label class="form-label">นามสกุล (Last Name)</label>
                        <input class="form-input" type="text" name="last_name"
                               value="<?= htmlspecialchars($eu['last_name'] ?? '') ?>"
                               placeholder="นามสกุล">
                    </div>

                    <div class="form-group">
                        <label class="form-label">แผนก / Department</label>
                        <input class="form-input" type="text" name="department"
                               value="<?= htmlspecialchars($eu['department'] ?? '') ?>"
                               placeholder="เช่น IT, HR, Marketing">
                    </div>

                    <div class="form-group">
                        <label class="form-label">ตำแหน่ง / Position</label>
                        <input class="form-input" type="text" name="position"
                               value="<?= htmlspecialchars($eu['position'] ?? '') ?>"
                               placeholder="เช่น Software Developer">
                    </div>

                    <div class="form-group full">
                        <label class="form-label">Job Title</label>
                        <input class="form-input" type="text" name="job_title"
                               value="<?= htmlspecialchars($eu['job_title'] ?? '') ?>"
                               placeholder="Job Title จาก Microsoft 365">
                    </div>

                    <div class="form-group">
                        <label class="form-label">เบอร์โทรศัพท์</label>
                        <input class="form-input" type="text" name="phone"
                               value="<?= htmlspecialchars($eu['phone'] ?? '') ?>"
                               placeholder="เบอร์โทรศัพท์">
                    </div>

                    <div class="form-group">
                        <label class="form-label">มือถือ</label>
                        <input class="form-input" type="text" name="mobile_phone"
                               value="<?= htmlspecialchars($eu['mobile_phone'] ?? '') ?>"
                               placeholder="เบอร์มือถือ">
                    </div>

                    <div class="form-group full">
                        <label class="form-label">สำนักงาน / Office Location</label>
                        <input class="form-input" type="text" name="office_location"
                               value="<?= htmlspecialchars($eu['office_location'] ?? '') ?>"
                               placeholder="ที่ตั้งสำนักงาน">
                    </div>

                    <div class="form-group full">
                        <label class="form-label">เกี่ยวกับ / Bio</label>
                        <textarea class="form-textarea" name="bio" placeholder="คำอธิบายสั้นๆ เกี่ยวกับผู้ใช้..."><?= htmlspecialchars($eu['bio'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group full">
                        <label class="form-label">อีเมล</label>
                        <input class="form-input" type="email" value="<?= htmlspecialchars($eu['email']) ?>" disabled>
                        <span class="form-hint">อีเมลไม่สามารถเปลี่ยนได้ (ใช้จาก Microsoft 365)</span>
                    </div>

                </div>
            </div>
            <div class="form-actions">
                <a href="manage_users.php" class="btn btn-secondary">ยกเลิก</a>
                <button type="submit" class="btn btn-primary" id="btnSave">💾 บันทึกข้อมูล</button>
            </div>
        </div>
    </form>

    <!-- READ-ONLY INFO -->
    <div class="info-card">
        <div class="info-card-header">ℹ️ ข้อมูลระบบ (อ่านอย่างเดียว)</div>
        <div class="info-grid">
            <div class="info-item">
                <div class="info-item-label">User ID</div>
                <div class="info-item-value"><?= $eu['id'] ?></div>
            </div>
            <div class="info-item">
                <div class="info-item-label">Microsoft ID</div>
                <div class="info-item-value <?= empty($eu['microsoft_id']) ? 'dim' : '' ?>"><?= htmlspecialchars($eu['microsoft_id'] ?? 'N/A') ?></div>
            </div>
            <div class="info-item">
                <div class="info-item-label">สร้างเมื่อ</div>
                <div class="info-item-value"><?= !empty($eu['created_at']) ? date('d/m/Y H:i:s', strtotime($eu['created_at'])) : '—' ?></div>
            </div>
            <div class="info-item">
                <div class="info-item-label">อัปเดตล่าสุด</div>
                <div class="info-item-value"><?= !empty($eu['updated_at']) ? date('d/m/Y H:i:s', strtotime($eu['updated_at'])) : '—' ?></div>
            </div>
            <div class="info-item">
                <div class="info-item-label">เข้าใช้ล่าสุด</div>
                <div class="info-item-value"><?= !empty($eu['last_login']) ? date('d/m/Y H:i:s', strtotime($eu['last_login'])) : 'ยังไม่เคยเข้าใช้' ?></div>
            </div>
            <div class="info-item">
                <div class="info-item-label">Token Expires</div>
                <div class="info-item-value"><?= !empty($eu['token_expires_at']) ? date('d/m/Y H:i:s', strtotime($eu['token_expires_at'])) : '—' ?></div>
            </div>
        </div>
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

// Confirm before leaving with changes
var form = document.getElementById('editForm');
var formChanged = false;
form.addEventListener('input', function() { formChanged = true; });
window.addEventListener('beforeunload', function(e) {
    if (formChanged && !form.dataset.submitted) { e.preventDefault(); e.returnValue = ''; }
});
form.addEventListener('submit', function() {
    form.dataset.submitted = 'true';
    var btn = document.getElementById('btnSave');
    btn.disabled = true;
    btn.innerHTML = '⏳ กำลังบันทึก...';
});
</script>
</body>
</html>
