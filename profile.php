<?php
require_once 'config.php';
requireLogin();

$userId      = $_SESSION['user_id'];
$displayName = htmlspecialchars($_SESSION['display_name'] ?? 'User');
$email       = htmlspecialchars($_SESSION['email'] ?? '');
$initial     = mb_substr($displayName, 0, 1);

// ดึงข้อมูล user
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch();

$avatar     = $user['avatar'] ?? '';
$phone      = htmlspecialchars($user['phone'] ?? '');
$department = htmlspecialchars($user['department'] ?? '');
$position   = htmlspecialchars($user['position'] ?? '');
$bio        = htmlspecialchars($user['bio'] ?? '');
$createdAt  = $user['created_at'] ?? date('Y-m-d');

// นับสถิติ
$stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM uploads WHERE user_id = :uid");
$stmtTotal->execute([':uid' => $userId]);
$totalFiles = $stmtTotal->fetchColumn();

$stmtApproved = $pdo->prepare("SELECT COUNT(*) FROM uploads WHERE user_id = :uid AND status = 'ผ่าน'");
$stmtApproved->execute([':uid' => $userId]);
$approvedFiles = $stmtApproved->fetchColumn();

$stmtPending = $pdo->prepare("SELECT COUNT(*) FROM uploads WHERE user_id = :uid AND status = 'รอตรวจ'");
$stmtPending->execute([':uid' => $userId]);
$pendingFiles = $stmtPending->fetchColumn();
?>
<!DOCTYPE html>
<html lang="th" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>โปรไฟล์ - Lolane Portal</title>

    <link rel="icon" type="image/jpeg" href="img/logo.jpg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/profile.css">
</head>
<body>

    <div class="dropdown-overlay" id="dropdownOverlay"></div>

    <!-- NAVBAR -->
    <nav class="navbar">
        <div class="nav-left">
            <a href="index.php" class="nav-logo">
                <img src="img/logo.jpg" alt="Lolane" class="nav-logo-img">Lolane Portal
            </a>
            <div class="nav-links">
                <a href="index.php" class="nav-link">🏠 หน้าหลัก</a>
                <a href="dashboard.php" class="nav-link">📊 Dashboard</a>
                <a href="draw/upload.php" class="nav-link">📁 Upload</a>
                <a href="profile.php" class="nav-link active">👤 โปรไฟล์</a>
            </div>
        </div>
        <div class="nav-right">
            <label class="theme-toggle"><input type="checkbox" id="themeToggle"><span class="toggle-slider"></span></label>
            <div class="profile-wrapper" id="profileWrapper">
                <button class="profile-trigger" id="profileTrigger">
                    <div class="profile-avatar-nav">
                        <?php if ($avatar): ?>
                            <img src="data:image/jpeg;base64,<?= $avatar ?>" alt="avatar">
                        <?php else: ?>
                            <?= $initial ?>
                        <?php endif; ?>
                    </div>
                    <div class="profile-info"><div class="profile-name"><?= $displayName ?></div><div class="profile-email-nav"><?= $email ?></div></div>
                    <span class="profile-arrow">▼</span>
                </button>
                <div class="profile-dropdown"><div class="dropdown-menu">
                    <a href="profile.php" class="dropdown-item"><span class="icon">👤</span> โปรไฟล์ของฉัน</a>
                    <button class="dropdown-item" onclick="toggleThemeFromMenu()"><span class="icon" id="themeMenuIcon">🌙</span><span id="themeMenuText">Dark Mode</span></button>
                    <div class="dropdown-divider"></div>
                    <a href="logout.php" class="dropdown-item danger"><span class="icon">🚪</span> ออกจากระบบ</a>
                </div></div>
            </div>
        </div>
    </nav>

    <div class="container">

        <!-- Profile Header -->
        <div class="profile-header-card">
            <div class="profile-cover"></div>
            <div class="profile-header-body">
                <div class="avatar-wrapper">
                    <div class="avatar-main" id="avatarMain" title="คลิกเพื่อเปลี่ยนรูปโปรไฟล์">
                        <?php if ($avatar): ?>
                            <img id="avatarImg" src="data:image/jpeg;base64,<?= $avatar ?>" alt="avatar">
                        <?php else: ?>
                            <span id="avatarInitial"><?= $initial ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="avatar-edit-badge" id="avatarEditBadge" title="เปลี่ยนรูป">📷</div>
                    <input type="file" class="avatar-input" id="avatarInput" accept="image/jpeg,image/png,image/gif,image/webp">
                </div>
                <div class="profile-header-info">
                    <h1><?= $displayName ?></h1>
                    <div class="profile-email"><?= $email ?></div>
                    <?php if ($position): ?>
                        <span class="profile-role"><?= $position ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Profile Form -->
        <form id="profileForm">
            <div class="profile-card">
                <div class="profile-card-title">📝 ข้อมูลส่วนตัว</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">👤 ชื่อที่แสดง <span class="required">*</span></label>
                        <input type="text" class="form-input" name="display_name" id="inputName"
                               value="<?= $displayName ?>" required maxlength="100">
                    </div>
                    <div class="form-group">
                        <label class="form-label">📧 อีเมล</label>
                        <input type="email" class="form-input" value="<?= $email ?>" disabled>
                        <span class="form-hint">อีเมลไม่สามารถเปลี่ยนได้</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label">📱 เบอร์โทรศัพท์</label>
                        <input type="tel" class="form-input" name="phone" id="inputPhone"
                               value="<?= $phone ?>" placeholder="0xx-xxx-xxxx" maxlength="20">
                    </div>
                    <div class="form-group">
                        <label class="form-label">🏢 แผนก</label>
                        <input type="text" class="form-input" name="department" id="inputDept"
                               value="<?= $department ?>" placeholder="เช่น IT, Marketing..." maxlength="100">
                    </div>
                    <div class="form-group">
                        <label class="form-label">💼 ตำแหน่ง</label>
                        <input type="text" class="form-input" name="position" id="inputPos"
                               value="<?= $position ?>" placeholder="เช่น Developer, Manager..." maxlength="100">
                    </div>
                    <div class="form-group">
                        <label class="form-label">📅 สมาชิกตั้งแต่</label>
                        <input type="text" class="form-input" value="<?= date('d/m/Y', strtotime($createdAt)) ?>" disabled>
                    </div>
                    <div class="form-group full">
                        <label class="form-label">📖 เกี่ยวกับฉัน</label>
                        <textarea class="form-textarea" name="bio" id="inputBio"
                                  placeholder="เขียนอะไรเกี่ยวกับตัวเอง..." maxlength="500"><?= $bio ?></textarea>
                        <span class="form-hint"><span id="bioCount"><?= mb_strlen($bio) ?></span>/500</span>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="window.location.reload()">↩️ ยกเลิก</button>
                    <button type="submit" class="btn-save" id="btnSave">💾 บันทึกข้อมูล</button>
                </div>
            </div>
        </form>

    </div>

    <div class="footer">© 2026 Lolane Co., Ltd. | Powered by <span class="footer-brand">ALPHABET</span></div>
    <div class="toast" id="toast"></div>

    <script>
    // ================================
    // ★ AVATAR UPLOAD ★
    // ================================
    (function(){
        var avatarMain = document.getElementById('avatarMain');
        var avatarBadge = document.getElementById('avatarEditBadge');
        var avatarInput = document.getElementById('avatarInput');
        var maxSize = 2 * 1024 * 1024; // 2MB

        avatarMain.addEventListener('click', function(){ avatarInput.click(); });
        avatarBadge.addEventListener('click', function(){ avatarInput.click(); });

        avatarInput.addEventListener('change', function(){
            var file = this.files[0];
            if (!file) return;

            if (file.size > maxSize) {
                showToast('❌ รูปภาพต้องมีขนาดไม่เกิน 2MB', 'error');
                return;
            }

            var allowed = ['image/jpeg','image/png','image/gif','image/webp'];
            if (allowed.indexOf(file.type) < 0) {
                showToast('❌ รองรับเฉพาะ JPG, PNG, GIF, WEBP', 'error');
                return;
            }

            var reader = new FileReader();
            reader.onload = function(e) {
                var base64 = e.target.result.split(',')[1];

                // แสดง preview ทันที
                avatarMain.innerHTML = '<img id="avatarImg" src="' + e.target.result + '" alt="avatar">';

                // ส่งไป server
                fetch('update_profile.php', {
                    method: 'POST',
                    headers: {'Content-Type':'application/json'},
                    body: JSON.stringify({ action: 'avatar', avatar: base64 })
                })
                .then(function(r){ return r.json(); })
                .then(function(d){
                    if (d.success) {
                        showToast('✅ อัปเดตรูปโปรไฟล์แล้ว', 'success');
                        // อัปเดต navbar avatar
                        var navAvatar = document.querySelector('.profile-avatar-nav');
                        if (navAvatar) navAvatar.innerHTML = '<img src="' + e.target.result + '" alt="avatar">';
                    } else {
                        showToast('❌ ' + (d.error||'อัปโหลดไม่สำเร็จ'), 'error');
                    }
                })
                .catch(function(){ showToast('❌ เกิดข้อผิดพลาด', 'error'); });
            };
            reader.readAsDataURL(file);
        });
    })();

    // ================================
    // ★ PROFILE FORM ★
    // ================================
    document.getElementById('profileForm').addEventListener('submit', function(e){
        e.preventDefault();
        var btn = document.getElementById('btnSave');
        btn.disabled = true;
        btn.textContent = '⏳ กำลังบันทึก...';

        var data = {
            action:       'info',
            display_name: document.getElementById('inputName').value.trim(),
            phone:        document.getElementById('inputPhone').value.trim(),
            department:   document.getElementById('inputDept').value.trim(),
            position:     document.getElementById('inputPos').value.trim(),
            bio:          document.getElementById('inputBio').value.trim()
        };

        if (!data.display_name) {
            showToast('❌ กรุณากรอกชื่อที่แสดง', 'error');
            btn.disabled = false;
            btn.textContent = '💾 บันทึกข้อมูล';
            return;
        }

        fetch('update_profile.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify(data)
        })
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.success) {
                showToast('✅ บันทึกข้อมูลเรียบร้อย', 'success');
                // อัปเดต navbar name
                var navName = document.querySelector('.profile-name');
                if (navName) navName.textContent = data.display_name;
                // อัปเดต header
                var h1 = document.querySelector('.profile-header-info h1');
                if (h1) h1.textContent = data.display_name;
                // อัปเดต role
                if (data.position) {
                    var role = document.querySelector('.profile-role');
                    if (role) role.textContent = data.position;
                }
            } else {
                showToast('❌ ' + (d.error||'บันทึกไม่สำเร็จ'), 'error');
            }
            btn.disabled = false;
            btn.textContent = '💾 บันทึกข้อมูล';
        })
        .catch(function(){
            showToast('❌ เกิดข้อผิดพลาด', 'error');
            btn.disabled = false;
            btn.textContent = '💾 บันทึกข้อมูล';
        });
    });

    // Bio counter
    document.getElementById('inputBio').addEventListener('input', function(){
        document.getElementById('bioCount').textContent = this.value.length;
    });

    // ================================
    // Toast
    // ================================
    function showToast(msg, type){
        var t = document.getElementById('toast');
        t.textContent = msg;
        t.className = 'toast ' + (type||'success');
        setTimeout(function(){ t.classList.add('show'); }, 10);
        setTimeout(function(){ t.classList.remove('show'); }, 3000);
    }

    // ================================
    // PROFILE DROPDOWN / THEME
    // ================================
    var pw=document.getElementById('profileWrapper'),pt=document.getElementById('profileTrigger'),ov=document.getElementById('dropdownOverlay');
    pt.addEventListener('click',function(e){e.stopPropagation();pw.classList.toggle('open');ov.classList.toggle('active');});
    ov.addEventListener('click',cDD);document.addEventListener('keydown',function(e){if(e.key==='Escape')cDD();});
    function cDD(){pw.classList.remove('open');ov.classList.remove('active');}
    var tt=document.getElementById('themeToggle'),ti=document.getElementById('themeMenuIcon'),tx=document.getElementById('themeMenuText'),ht=document.documentElement;
    function lT(){aT(localStorage.getItem('lolane_theme')||'light');}
    function aT(t){ht.setAttribute('data-theme',t);localStorage.setItem('lolane_theme',t);tt.checked=(t==='dark');ti.textContent=t==='dark'?'☀️':'🌙';tx.textContent=t==='dark'?'Light Mode':'Dark Mode';}
    tt.addEventListener('change',function(){aT(tt.checked?'dark':'light');});
    function toggleThemeFromMenu(){aT(ht.getAttribute('data-theme')==='dark'?'light':'dark');}
    lT();
    </script>
</body>
</html>