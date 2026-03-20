<?php
/**
 * หน้า Login - Microsoft OAuth
 * ★ รองรับ Multi-tenant (lolane.com + srcgroupth.com)
 * ★ Dark / Light Theme
 */
require_once 'config.php';

// ถ้า login แล้ว ไปหน้าหลัก
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

// ถ้ากดปุ่ม Login
if (isset($_GET['action']) && $_GET['action'] === 'login') {
    $state = generateState();

    $params = http_build_query([
        'client_id'     => $clientId,
        'response_type' => 'code',
        'redirect_uri'  => $redirectUri,
        'response_mode' => 'query',
        'scope'         => $scope,
        'state'         => $state,
        'prompt'        => 'login',
        'claims'        => json_encode([
            'id_token' => [
                'acr' => [
                    'essential' => true,
                    'values'    => ['http://schemas.microsoft.com/claims/multipleauthn']
                ]
            ]
        ]),
    ]);

    header("Location: {$authorizeUrl}?{$params}");
    exit;
}
?>
<!DOCTYPE html>
<html lang="th" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lolane Portal - เข้าสู่ระบบ</title>

    <link rel="icon" type="image/png" href="img/logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        /* ================================
           THEME VARIABLES
        ================================ */
        :root, [data-theme="light"] {
            --bg-primary: #fef7f0;
            --bg-secondary: #fff5ec;
            --bg-card: #ffffff;
            --bg-glass: rgba(255, 255, 255, 0.85);
            --text-primary: #2d2d2d;
            --text-secondary: #666666;
            --text-muted: #b0885a;
            --accent: #ff6b35;
            --accent-hover: #e55a28;
            --accent-light: #fff0e5;
            --accent-glow: rgba(255, 107, 53, 0.3);
            --border-color: #ffecd2;
            --shadow: 0 8px 32px rgba(255, 140, 66, 0.12);
            --shadow-lg: 0 20px 60px rgba(255, 107, 53, 0.15);
            --shadow-btn: 0 8px 24px rgba(255, 107, 53, 0.35);
            --gradient-bg: linear-gradient(135deg, #fff5ec 0%, #fef7f0 50%, #fff0e5 100%);
            --gradient-accent: linear-gradient(135deg, #ff8c42, #ff6b35);
            --gradient-card: linear-gradient(145deg, #ffffff, #fff8f2);
            --dot-color: rgba(255, 107, 53, 0.08);
            --divider: #ffecd2;
            --ms-btn-bg: #2f2f2f;
            --ms-btn-hover: #1a1a1a;
            --domain-bg: #fff8f2;
            --domain-border: #ffecd2;
            --check-color: #2ecc71;
            --toggle-bg: rgba(0, 0, 0, 0.08);
            --toggle-dot: #ff6b35;
        }

        [data-theme="dark"] {
            --bg-primary: #1a1208;
            --bg-secondary: #221a0e;
            --bg-card: #2a1f10;
            --bg-glass: rgba(42, 31, 16, 0.9);
            --text-primary: #ffecd2;
            --text-secondary: #c4a882;
            --text-muted: #8a7050;
            --accent: #ff8c42;
            --accent-hover: #ffa05c;
            --accent-light: #3d2e18;
            --accent-glow: rgba(255, 140, 66, 0.25);
            --border-color: #3d2e18;
            --shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            --shadow-lg: 0 20px 60px rgba(0, 0, 0, 0.5);
            --shadow-btn: 0 8px 24px rgba(255, 140, 66, 0.2);
            --gradient-bg: linear-gradient(135deg, #1a1208 0%, #221a0e 50%, #1a1208 100%);
            --gradient-accent: linear-gradient(135deg, #ff8c42, #ff6b35);
            --gradient-card: linear-gradient(145deg, #2a1f10, #332814);
            --dot-color: rgba(255, 140, 66, 0.05);
            --divider: #3d2e18;
            --ms-btn-bg: #ffffff;
            --ms-btn-hover: #f0f0f0;
            --domain-bg: #332814;
            --domain-border: #3d2e18;
            --check-color: #81c784;
            --toggle-bg: rgba(255, 255, 255, 0.08);
            --toggle-dot: #ff8c42;
        }

        /* ================================
           BASE
        ================================ */
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Kanit', sans-serif;
            background: var(--gradient-bg);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-primary);
            transition: background 0.4s, color 0.4s;
            position: relative;
            overflow: hidden;
        }

        /* ★ Background Pattern */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image: radial-gradient(circle, var(--dot-color) 1px, transparent 1px);
            background-size: 30px 30px;
            z-index: 0;
            pointer-events: none;
        }

        /* ★ Floating Orbs */
        .orb {
            position: fixed;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.4;
            z-index: 0;
            pointer-events: none;
            animation: float 20s infinite ease-in-out;
        }
        .orb-1 {
            width: 400px; height: 400px;
            background: var(--accent);
            top: -100px; right: -100px;
            animation-delay: 0s;
        }
        .orb-2 {
            width: 300px; height: 300px;
            background: #ff8c42;
            bottom: -80px; left: -80px;
            animation-delay: -7s;
        }
        .orb-3 {
            width: 200px; height: 200px;
            background: #ffa05c;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            animation-delay: -14s;
            opacity: 0.2;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) scale(1); }
            25% { transform: translate(30px, -40px) scale(1.05); }
            50% { transform: translate(-20px, 20px) scale(0.95); }
            75% { transform: translate(40px, 30px) scale(1.02); }
        }

        /* ================================
           THEME TOGGLE (มุมขวาบน)
        ================================ */
        .theme-toggle-wrap {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 100;
        }
        .theme-toggle {
            width: 56px;
            height: 28px;
            border-radius: 14px;
            background: var(--toggle-bg);
            border: 2px solid var(--border-color);
            cursor: pointer;
            position: relative;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            padding: 0 4px;
        }
        .theme-toggle:hover {
            border-color: var(--accent);
        }
        .theme-toggle input { display: none; }
        .theme-dot {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--toggle-dot);
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            box-shadow: 0 2px 8px var(--accent-glow);
        }
        .theme-toggle input:checked ~ .theme-dot {
            transform: translateX(26px);
        }
        .theme-dot::after {
            content: '☀️';
            font-size: 12px;
        }
        [data-theme="dark"] .theme-dot::after {
            content: '🌙';
        }

        /* ================================
           LOGIN CARD
        ================================ */
        .login-wrapper {
            position: relative;
            z-index: 10;
            width: 90%;
            max-width: 460px;
        }

        .login-card {
            background: var(--gradient-card);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 48px 40px;
            box-shadow: var(--shadow-lg);
            text-align: center;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            transition: all 0.3s;
            animation: cardIn 0.6s ease-out;
        }
        .login-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg), 0 0 0 1px var(--accent-glow);
        }

        @keyframes cardIn {
            from { opacity: 0; transform: translateY(30px) scale(0.96); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        /* ★ Logo */
        .logo-wrap {
            margin-bottom: 24px;
            animation: logoIn 0.8s ease-out;
        }
        @keyframes logoIn {
            from { opacity: 0; transform: scale(0.8); }
            to { opacity: 1; transform: scale(1); }
        }
        .logo-img {
            width: 100px;
            height: 100px;
            border-radius: 24px;
            object-fit: cover;
            border: 3px solid var(--border-color);
            box-shadow: 0 8px 24px var(--accent-glow);
            transition: all 0.3s;
        }
        .logo-img:hover {
            transform: scale(1.05) rotate(-2deg);
            box-shadow: 0 12px 32px var(--accent-glow);
        }

        /* ★ Title */
        .login-title {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 4px;
            background: var(--gradient-accent);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .login-subtitle {
            font-size: 14px;
            font-weight: 300;
            color: var(--text-secondary);
            margin-bottom: 28px;
        }

        /* ★ Error */
        .error-box {
            background: #fff0ee;
            border: 1px solid #ffcccc;
            color: #c0392b;
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 13px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
            animation: shake 0.4s ease-in-out;
        }
        [data-theme="dark"] .error-box {
            background: #3d1b1b;
            border-color: #5e2e2e;
            color: #ef9a9a;
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-6px); }
            75% { transform: translateX(6px); }
        }

        /* ★ Divider */
        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 24px 0;
        }
        .divider-line {
            flex: 1;
            height: 1px;
            background: var(--divider);
        }
        .divider-text {
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 400;
            white-space: nowrap;
        }

        /* ★ Microsoft Button */
        .ms-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            width: 100%;
            padding: 14px 24px;
            border: none;
            border-radius: 14px;
            font-family: 'Kanit', sans-serif;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s;
            background: var(--ms-btn-bg);
            color: #ffffff;
            box-shadow: var(--shadow);
        }
        [data-theme="dark"] .ms-btn {
            color: #2d2d2d;
        }
        .ms-btn:hover {
            background: var(--ms-btn-hover);
            transform: translateY(-2px);
            box-shadow: var(--shadow-btn);
        }
        .ms-btn:active {
            transform: translateY(0);
        }
        .ms-btn svg {
            width: 22px;
            height: 22px;
            flex-shrink: 0;
        }

        /* ★ OR: Accent Button (อีกสไตล์) */
        .accent-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 14px 24px;
            border: none;
            border-radius: 14px;
            font-family: 'Kanit', sans-serif;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s;
            background: var(--gradient-accent);
            color: #ffffff;
            box-shadow: var(--shadow-btn);
        }
        .accent-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 32px rgba(255, 107, 53, 0.4);
            filter: brightness(1.05);
        }
        .accent-btn:active {
            transform: translateY(0);
        }

        /* ★ Domain Info */
        .domain-info {
            margin-top: 28px;
            padding: 16px;
            background: var(--domain-bg);
            border: 1px solid var(--domain-border);
            border-radius: 14px;
        }
        .domain-title {
            font-size: 12px;
            font-weight: 500;
            color: var(--text-muted);
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .domain-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .domain-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: var(--text-secondary);
        }
        .domain-check {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--check-color);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            flex-shrink: 0;
        }
        .domain-name {
            font-weight: 500;
            color: var(--accent);
        }
        .domain-org {
            font-weight: 300;
            color: var(--text-muted);
            font-size: 12px;
        }

        /* ★ Footer */
        .login-footer {
            margin-top: 24px;
            font-size: 11px;
            color: var(--text-muted);
            font-weight: 300;
        }
        .login-footer .brand {
            color: var(--accent);
            font-weight: 600;
            letter-spacing: 1px;
        }

        /* ================================
           Responsive
        ================================ */
        @media (max-width: 480px) {
            .login-card {
                padding: 36px 24px;
                border-radius: 20px;
            }
            .logo-img {
                width: 80px;
                height: 80px;
            }
            .login-title {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>

    <!-- ★ Background Orbs -->
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>

    <!-- ★ Theme Toggle -->
    <div class="theme-toggle-wrap">
        <label class="theme-toggle">
            <input type="checkbox" id="themeToggle">
            <span class="theme-dot"></span>
        </label>
    </div>

    <!-- ★ Login Card -->
    <div class="login-wrapper">
        <div class="login-card">

            <!-- Logo -->
            <div class="logo-wrap">
                <img src="img/logo.jpg" alt="Lolane" class="logo-img">
            </div>

            <!-- Title -->
            <h1 class="login-title">Lolane Service</h1>
            <p class="login-subtitle">เข้าสู่ระบบด้วยบัญชี Microsoft ขององค์กร</p>

            <!-- Error -->
            <?php if (isset($_GET['error'])): ?>
                <div class="error-box">
                    <span>⚠️</span>
                    <span>
                    <?php
                    $errors = [
                        'invalid_state'  => 'เซสชันไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง',
                        'token_failed'   => 'ไม่สามารถยืนยันตัวตนได้ กรุณาลองใหม่',
                        'invalid_domain' => 'อนุญาตเฉพาะอีเมลขององค์กรที่กำหนดเท่านั้น',
                        'login_failed'   => 'เข้าสู่ระบบไม่สำเร็จ กรุณาลองใหม่',
                    ];
                    echo $errors[$_GET['error']] ?? 'เกิดข้อผิดพลาด กรุณาลองใหม่';
                    ?>
                    </span>
                </div>
            <?php endif; ?>

            <!-- ★ Login Button (Accent Style) -->

            <!-- Divider -->
            <div class="divider">
                <div class="divider-line"></div>
                <span class="divider-text">หรือ</span>
                <div class="divider-line"></div>
            </div>

            <!-- ★ Microsoft Classic Button -->
            <a href="login.php?action=login" class="ms-btn">
                <svg viewBox="0 0 21 21" xmlns="http://www.w3.org/2000/svg">
                    <rect x="1" y="1" width="9" height="9" fill="#f25022"/>
                    <rect x="1" y="11" width="9" height="9" fill="#00a4ef"/>
                    <rect x="11" y="1" width="9" height="9" fill="#7fba00"/>
                    <rect x="11" y="11" width="9" height="9" fill="#ffb900"/>
                </svg>
                Sign in with Microsoft
            </a>

            <!-- ★ Domain Info -->
            <div class="domain-info">
                <div class="domain-title">🔒 โดเมนที่อนุญาตเข้าใช้งาน</div>
                <div class="domain-list">
                    <div class="domain-item">
                        <span class="domain-check">✓</span>
                        <span class="domain-name">@lolane.com</span>
                        <span class="domain-org">— Lolane Co., Ltd.</span>
                    </div>
                    <div class="domain-item">
                        <span class="domain-check">✓</span>
                        <span class="domain-name">@srcgroupth.com</span>
                        <span class="domain-org">— SRC Group Thailand</span>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="login-footer">
                © 2026 Lolane Co., Ltd. | Powered by <span class="brand">ALPHABET</span>
            </div>

        </div>
    </div>

    <!-- ★ Theme Script -->
    <script>
    (function() {
        var toggle = document.getElementById('themeToggle');
        var html = document.documentElement;

        function loadTheme() {
            var saved = localStorage.getItem('lolane_theme') || 'light';
            applyTheme(saved);
        }

        function applyTheme(theme) {
            html.setAttribute('data-theme', theme);
            localStorage.setItem('lolane_theme', theme);
            toggle.checked = (theme === 'dark');
        }

        toggle.addEventListener('change', function() {
            applyTheme(toggle.checked ? 'dark' : 'light');
        });

        loadTheme();
    })();
    </script>

</body>
</html>