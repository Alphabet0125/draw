<?php
require_once 'config.php';
requireLogin();

// ★ โหลด avatar
require_once 'get_avatar.php';

$displayName = htmlspecialchars($_SESSION['display_name'] ?? 'User');
$email       = htmlspecialchars($_SESSION['email'] ?? '');
$initial     = mb_substr($displayName, 0, 1);

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
$stmt->execute([':id' => $_SESSION['user_id']]);
$user = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="th" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Lolane Portal</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/jpeg" href="img/logo.jpg">
    <link rel="shortcut icon" type="image/jpeg" href="img/logo.jpg">
    <link rel="apple-touch-icon" href="img/logo.jpg">
    <link rel="stylesheet" href="css/dashboard.css">
</head>
<body>

    <div class="dropdown-overlay" id="dropdownOverlay"></div>

    <!-- ===== NAVBAR (เหมือน index.php เป๊ะ) ===== -->
    <nav class="navbar">
        <div class="nav-left">
            <a href="index.php" class="nav-logo">
                <img src="img/logo.jpg" alt="Lolane" class="nav-logo-img">
                Lolane
            </a>
            <div class="nav-links">
                <a href="index.php" class="nav-link">🏠 หน้าหลัก</a>
                <a href="dashboard.php" class="nav-link active">📊 Dashboard</a>
                <a href="fetch_d365.php" class="nav-link">📦 D365</a>
            </div>
        </div>

        <div class="nav-right">
            <!-- Theme Toggle -->
            <label class="theme-toggle" title="สลับธีม Light / Dark">
                <input type="checkbox" id="themeToggle">
                <span class="toggle-slider"></span>
            </label>

            <!-- Profile Dropdown -->
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
                        <a href="dashboard.php" class="dropdown-item">
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
        <div class="welcome-card">
            <div class="emoji">👋</div>
            <h2>สวัสดี, <?= $displayName ?>!</h2>
            <p>ยินดีต้อนรับเข้าสู่ระบบ Lolane Portal</p>
            <a href="index.php" class="enter-btn">🚀 เข้าสู่ระบบ</a>
        </div>

        <div class="grid">
            <div class="card">
                <h3>👤 ข้อมูลส่วนตัว</h3>
                <div class="info-row">
                    <span class="info-label">ชื่อ</span>
                    <span class="info-value"><?= htmlspecialchars($user['display_name'] ?? '-') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">อีเมล</span>
                    <span class="info-value"><?= htmlspecialchars($user['email'] ?? '-') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">ตำแหน่ง</span>
                    <span class="info-value"><?= htmlspecialchars($user['job_title'] ?? '-') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">แผนก</span>
                    <span class="info-value"><?= htmlspecialchars($user['department'] ?? '-') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">สำนักงาน</span>
                    <span class="info-value"><?= htmlspecialchars($user['office_location'] ?? '-') ?></span>
                </div>
            </div>

            <div class="card">
                <h3>📋 ประวัติการเข้าสู่ระบบ</h3>
                <?php
                $logStmt = $pdo->prepare(
                    "SELECT login_at, ip_address FROM login_logs
                     WHERE user_id = :uid ORDER BY login_at DESC LIMIT 5"
                );
                $logStmt->execute([':uid' => $_SESSION['user_id']]);
                $logs = $logStmt->fetchAll();
                foreach ($logs as $log): ?>
                    <div class="info-row">
                        <span class="info-label"><?= htmlspecialchars($log['ip_address']) ?></span>
                        <span class="info-value"><?= date('d/m/Y H:i', strtotime($log['login_at'])) ?></span>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($logs)): ?>
                    <p style="color:var(--text-muted); font-size:14px; font-weight:300;">ยังไม่มีประวัติ</p>
                <?php endif; ?>
            </div>
        </div>
        <div class="footer">
        © 2026 Lolane Co., Ltd. | Powered by <span class="footer-brand">ALPHABET</span>
        </div>
    </div>

    <!-- ===== JAVASCRIPT (เหมือน index.php เป๊ะ) ===== -->
    <script>
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
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeDropdown();
    });

    function closeDropdown() {
        profileWrapper.classList.remove('open');
        overlay.classList.remove('active');
    }

    // Theme Toggle
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
        if (theme === 'dark') {
            themeMenuIcon.textContent = '☀️';
            themeMenuText.textContent = 'Light Mode';
        } else {
            themeMenuIcon.textContent = '🌙';
            themeMenuText.textContent = 'Dark Mode';
        }
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