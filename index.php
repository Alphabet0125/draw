<?php
require_once 'config.php';
requireLogin();

$displayName = htmlspecialchars($_SESSION['display_name'] ?? 'User');
$email       = htmlspecialchars($_SESSION['email'] ?? '');
$initial     = mb_substr($displayName, 0, 1);

// ★ โหลด avatar
require_once 'get_avatar.php';

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
$stmt->execute([':id' => $_SESSION['user_id']]);
$user = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="th" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lolane - หน้าแรก</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/jpeg" href="img/logo.jpg">
    <link rel="shortcut icon" type="image/jpeg" href="img/logo.jpg">
    <link rel="apple-touch-icon" href="img/logo.jpg">
    <link rel="stylesheet" href="css/index.css">

    <style>
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

    <!-- ===== NAVBAR ===== -->
    <nav class="navbar">
        <div class="nav-left">
            <a href="index.php" class="nav-logo">
                <img src="img/logo.jpg" alt="Lolane" class="nav-logo-img">
                Lolane
            </a>
            <div class="nav-links">
                <a href="index.php" class="nav-link">🏠 หน้าหลัก</a>
                <?php if (in_array(trim($_SESSION['department'] ?? ''), ['IT','HR'], true)): ?>
                <a href="manage_users.php" class="nav-link">👥 จัดการผู้ใช้งาน</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="nav-right">
            <label class="theme-toggle" title="สลับธีม Light / Dark">
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
                        <a href="profile.php" class="dropdown-item">
                            <span class="icon">👤</span> โปรไฟล์ของฉัน
                        </a>
                        <a href="dashboard.php" class="dropdown-item">
                            <span class="icon">⚙️</span> ตั้งค่า
                        </a>
                        <button class="dropdown-item" onclick="toggleThemeFromMenu()">
                            <span class="icon" id="themeMenuIcon">🌙</span>
                            <span id="themeMenuText">Dark Mode</span>
                        </button>
                        <div class="dropdown-divider"></div>
                        <a href="logout.php" class="dropdown-item danger">
                            <span class="icon">🚪</span> ออกจากระบบ
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- ===== CONTENT ===== -->
    <div class="container">

        <div class="page-header">
            <h2>ยินดีต้อนรับ, <?= $displayName ?>! 👋</h2>
            <p>ระบบจัดการข้อมูล Lolane Portal</p>
        </div>

        <div class="menu-grid">
            <a href="draw/upload.php" class="menu-card">
                <div class="menu-icon" style="background: #fff0e5;">🎨</div>
                <div class="menu-info">
                    <h3>Draw</h3>
                    <p>ระบบจับฉลากและสุ่มรางวัล</p>
                </div>
                <span class="menu-arrow">→</span>
            </a>

            <a href="#" class="menu-card">
                <div class="menu-icon" style="background: #e8f5e9;">📦</div>
                <div class="menu-info">
                    <h3>Inventory IT</h3>
                    <p>จัดการอุปกรณ์ IT และสินค้าคงคลัง</p>
                </div>
                <span class="menu-arrow">→</span>
            </a>

            <a href="#" class="menu-card">
                <div class="menu-icon" style="background: #e3f2fd;">📰</div>
                <div class="menu-info">
                    <h3>News</h3>
                    <p>ข่าวสารและประกาศภายในองค์กร</p>
                </div>
                <span class="menu-arrow">→</span>
            </a>

            <a href="#" class="menu-card">
                <div class="menu-icon" style="background: #f3e5f5;">📋</div>
                <div class="menu-info">
                    <h3>Request</h3>
                    <p>แจ้งคำร้องและขอใช้บริการต่างๆ</p>
                </div>
                <span class="menu-arrow">→</span>
            </a>
        </div>

    </div>

    <div class="footer">
        © 2026 Lolane Co., Ltd. | Powered by <span class="footer-brand">ALPHABET</span>
    </div>

    <script>
    // PROFILE DROPDOWN
    const profileWrapper = document.getElementById('profileWrapper');
    const profileTrigger = document.getElementById('profileTrigger');
    const overlay        = document.getElementById('dropdownOverlay');

    profileTrigger.addEventListener('click', (e) => {
        e.stopPropagation();
        profileWrapper.classList.toggle('open');
        overlay.classList.toggle('active');
    });

    overlay.addEventListener('click', closeDropdown);
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeDropdown();
    });

    function closeDropdown() {
        profileWrapper.classList.remove('open');
        overlay.classList.remove('active');
    }

    // THEME TOGGLE
    const themeToggle   = document.getElementById('themeToggle');
    const themeMenuIcon = document.getElementById('themeMenuIcon');
    const themeMenuText = document.getElementById('themeMenuText');
    const html          = document.documentElement;

    function loadTheme() {
        const saved = localStorage.getItem('lolane_theme') || 'light';
        applyTheme(saved);
    }

    function applyTheme(theme) {
        html.setAttribute('data-theme', theme);
        localStorage.setItem('lolane_theme', theme);
        themeToggle.checked = (theme === 'dark');
        themeMenuIcon.textContent = theme === 'dark' ? '☀️' : '🌙';
        themeMenuText.textContent = theme === 'dark' ? 'Light Mode' : 'Dark Mode';
    }

    themeToggle.addEventListener('change', () => {
        applyTheme(themeToggle.checked ? 'dark' : 'light');
    });

    function toggleThemeFromMenu() {
        const current = html.getAttribute('data-theme');
        applyTheme(current === 'dark' ? 'light' : 'dark');
    }

    loadTheme();
    </script>
</body>
</html>