<?php
/**
 * Navbar Component
 * ต้อง include หลัง config.php + requireLogin() + get_avatar.php
 * ต้องมีตัวแปร: $displayName, $email, $initial, $userAvatar, $currentPage
 *
 * $currentPage = 'home' | 'dashboard' | 'upload' | 'files' | 'profile'
 */

$navBasePath = '';
// ตรวจสอบว่าอยู่ใน subfolder หรือไม่
if (strpos($_SERVER['SCRIPT_NAME'], '/draw/') !== false) {
    $navBasePath = '../';
}
?>
<div class="dropdown-overlay" id="dropdownOverlay"></div>
<nav class="navbar">
    <div class="nav-left">
        <a href="<?= $navBasePath ?>index.php" class="nav-logo">
            <img src="<?= $navBasePath ?>img/logo.jpg" alt="Lolane" class="nav-logo-img">Lolane Portal
        </a>
        <div class="nav-links">
            <a href="<?= $navBasePath ?>index.php" class="nav-link <?= ($currentPage ?? '')==='home'?'active':'' ?>">🏠 หน้าหลัก</a>
            <a href="<?= $navBasePath ?>dashboard.php" class="nav-link <?= ($currentPage ?? '')==='dashboard'?'active':'' ?>">📊 Dashboard</a>
            <a href="<?= $navBasePath ?>draw/upload.php" class="nav-link <?= ($currentPage ?? '')==='upload'?'active':'' ?>">📁 Upload</a>
            <a href="<?= $navBasePath ?>draw/select_file.php" class="nav-link <?= ($currentPage ?? '')==='files'?'active':'' ?>">📋 ไฟล์</a>
        </div>
    </div>
    <div class="nav-right">
        <label class="theme-toggle" title="สลับธีม"><input type="checkbox" id="themeToggle"><span class="toggle-slider"></span></label>
        <div class="profile-wrapper" id="profileWrapper">
            <button class="profile-trigger" id="profileTrigger">
                <div class="nav-avatar" id="navAvatar">
                    <?php if ($userAvatar): ?>
                        <img src="data:image/jpeg;base64,<?= $userAvatar ?>" alt="avatar">
                    <?php else: ?>
                        <span class="nav-avatar-initial"><?= $initial ?></span>
                    <?php endif; ?>
                </div>
                <div class="profile-info">
                    <div class="profile-name"><?= $displayName ?></div>
                    <div class="profile-email"><?= $email ?></div>
                </div>
                <span class="profile-arrow">▼</span>
            </button>
            <div class="profile-dropdown"><div class="dropdown-menu">
                <a href="<?= $navBasePath ?>profile.php" class="dropdown-item"><span class="icon">👤</span> โปรไฟล์ของฉัน</a>
                <button class="dropdown-item" onclick="toggleThemeFromMenu()"><span class="icon" id="themeMenuIcon">🌙</span><span id="themeMenuText">Dark Mode</span></button>
                <div class="dropdown-divider"></div>
                <a href="<?= $navBasePath ?>logout.php" class="dropdown-item danger"><span class="icon">🚪</span> ออกจากระบบ</a>
            </div></div>
        </div>
    </div>
</nav>