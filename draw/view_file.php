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

$fileId = (int) ($_GET['id'] ?? 0);
if (!$fileId) { header('Location: select_file.php'); exit; }

$stmt = $pdo->prepare(
    "SELECT u.id, u.user_id, u.file_name, u.file_type, u.file_mime, u.file_size, u.file_path, u.description, u.status, u.uploaded_at,
            usr.display_name AS uploader_name, usr.email AS uploader_email
     FROM uploads u JOIN users usr ON u.user_id = usr.id
     WHERE u.id = :id"
);
$stmt->execute([':id' => $fileId]);
$file = $stmt->fetch();
if (!$file) { header('Location: select_file.php'); exit; }

// ★★★ ตรวจสอบสิทธิ์ผู้ตรวจงาน ★★★
$isOwner = ((int)$file['user_id'] === $userId);
$rvStmt = $pdo->prepare("SELECT COUNT(*) FROM file_reviewers WHERE upload_id = :uid AND user_id = :rid");
$rvStmt->execute([':uid' => $fileId, ':rid' => $userId]);
$isReviewer = $rvStmt->fetchColumn() > 0;
$canEdit = ($isOwner || $isReviewer); // สิทธิ์ในการเปลี่ยนสถานะและใช้เครื่องมือ

// ดึงรายชื่อผู้ตรวจทั้งหมด
$rvListStmt = $pdo->prepare(
    "SELECT u.display_name, u.email, u.department, fr.user_id AS reviewer_user_id, fr.assigned_by,
            fr.rv_status, fr.rv_description
     FROM file_reviewers fr JOIN users u ON fr.user_id = u.id
     WHERE fr.upload_id = :uid ORDER BY fr.assigned_at ASC"
);
$rvListStmt->execute([':uid' => $fileId]);
$reviewerList = $rvListStmt->fetchAll();

// ★ Log: เปิดดูไฟล์
logActivity($pdo, 'view', 'เปิดดูไฟล์ "' . $file['file_name'] . '"', $fileId, $file['file_name']);

function formatFileSize(int $bytes): string {
    if ($bytes === 0) return '0 B';
    $units = ['B','KB','MB','GB'];
    $i = floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), 1) . ' ' . $units[$i];
}
function getStatusClass(string $s): string {
    switch ($s) {
        case 'กำลังตรวจ': return 'reviewing';
        case 'ผ่าน':     return 'approved';
        case 'ไม่ผ่าน':   return 'rejected';
        default:         return 'pending';
    }
}
function getStatusIcon(string $s): string {
    switch ($s) {
        case 'กำลังตรวจ': return '🔄';
        case 'ผ่าน':     return '✅';
        case 'ไม่ผ่าน':   return '❌';
        default:         return '⏳';
    }
}
function getReviewerStatusClass(string $s): string {
    switch ($s) {
        case 'ผ่าน':    return 'approved';
        case 'แก้ไข':   return 'reviewing';
        case 'ไม่ผ่าน': return 'rejected';
        default:        return '';
    }
}
function getReviewerStatusIcon(string $s): string {
    switch ($s) {
        case 'ผ่าน':    return '✅';
        case 'แก้ไข':   return '✏️';
        case 'ไม่ผ่าน': return '❌';
        default:        return '';
    }
}

$isPDF   = $file['file_type'] === 'pdf';
$dataSrc = 'serve_file.php?id=' . $fileId;
?>
<!DOCTYPE html>
<html lang="th" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($file['file_name']) ?> - Lolane Portal</title>

    <link rel="icon" type="image/jpeg" href="../img/logo.jpg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&family=Sarabun:wght@300;400;500;600&family=Prompt:wght@300;400;500;600&family=Mitr:wght@300;400;500&family=Noto+Sans+Thai:wght@300;400;500;600&display=swap" rel="stylesheet">

    <?php if ($isPDF): ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script>pdfjsLib.GlobalWorkerOptions.workerSrc='https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';</script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://unpkg.com/pdf-lib@1.17.1/dist/pdf-lib.min.js"></script>
    <!-- pdf-lib: โหลด PDF ต้นฉบับ + overlay annotation แล้ว save เป็น PDF คุณภาพเดียวต้นฉบับ -->
    <script src="https://unpkg.com/pdf-lib@1.17.1/dist/pdf-lib.min.js"></script>
    <?php endif; ?>

    <style>
        :root,[data-theme="light"]{
            --bg-primary:#fef7f0;--bg-card:#fff;--bg-hover:#fff5ec;
            --bg-navbar:linear-gradient(135deg,#ff8c42,#ff6b35);--bg-dropdown:#fff;
            --text-primary:#2d2d2d;--text-secondary:#777;--text-muted:#b0885a;
            --border-color:#ffecd2;--accent:#ff6b35;--accent-light:#fff0e5;
            --shadow:0 4px 20px rgba(255,140,66,.08);--shadow-lg:0 10px 40px rgba(255,140,66,.15);
            --navbar-shadow:0 4px 20px rgba(255,107,53,.3);
            --tool-bg:#fff;--tool-border:#ffecd2;--tool-btn-bg:#fef7f0;
            --tool-btn-active:#ff6b35;--tool-btn-active-text:#fff;
        }
        [data-theme="dark"]{
            --bg-primary:#1a1208;--bg-card:#2a1f10;--bg-hover:#332814;
            --bg-navbar:linear-gradient(135deg,#b85a1e,#8c3d0f);--bg-dropdown:#2a1f10;
            --text-primary:#ffecd2;--text-secondary:#c4a882;--text-muted:#8a7050;
            --border-color:#3d2e18;--accent:#ff8c42;--accent-light:#3d2e18;
            --shadow:0 4px 20px rgba(0,0,0,.3);--shadow-lg:0 10px 40px rgba(0,0,0,.5);
            --navbar-shadow:0 4px 20px rgba(0,0,0,.4);
            --tool-bg:#2a1f10;--tool-border:#3d2e18;--tool-btn-bg:#1a1208;
            --tool-btn-active:#ff8c42;--tool-btn-active-text:#1a1208;
        }
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Kanit',sans-serif;background:var(--bg-primary);color:var(--text-primary);min-height:100vh;transition:background .4s,color .4s;overflow-x:hidden}
        a{color:inherit;text-decoration:none}
        .navbar{background:var(--bg-navbar);color:#fff;padding:0 32px;width:100%;height:64px;display:flex;justify-content:space-between;align-items:center;box-shadow:var(--navbar-shadow);position:sticky;top:0;z-index:1000}
        .nav-left{display:flex;align-items:center;gap:28px}
        .nav-logo{display:flex;align-items:center;gap:10px;font-size:20px;font-weight:600;color:#fff;text-decoration:none}.nav-logo:hover{opacity:.9}
        .nav-logo-img{width:36px;height:36px;border-radius:8px;object-fit:cover;border:2px solid rgba(255,255,255,.4)}
        .nav-links{display:flex;gap:4px}
        .nav-link{color:rgba(255,255,255,.8);padding:8px 16px;border-radius:8px;font-size:14px;transition:all .2s}
        .nav-link:hover,.nav-link.active{background:rgba(255,255,255,.2);color:#fff}
        .nav-right{display:flex;align-items:center;gap:14px}
        .theme-toggle{position:relative;width:52px;height:26px;cursor:pointer}.theme-toggle input{display:none}
        .toggle-slider{position:absolute;inset:0;background:rgba(255,255,255,.25);border-radius:13px;transition:all .3s}
        .toggle-slider::before{content:"☀️";font-size:12px;position:absolute;top:2px;left:3px;width:22px;height:22px;display:flex;align-items:center;justify-content:center;background:#fff;border-radius:50%;transition:transform .3s}
        .theme-toggle input:checked+.toggle-slider::before{content:"🌙";transform:translateX(25px);background:#2a1f10}
        .profile-wrapper{position:relative}
        .profile-trigger{display:flex;align-items:center;gap:10px;cursor:pointer;padding:5px 12px;border-radius:12px;transition:background .2s;border:none;background:none;color:#fff;font-family:'Kanit',sans-serif}.profile-trigger:hover{background:rgba(255,255,255,.15)}
        .profile-avatar{width:36px;height:36px;background:rgba(255,255,255,.25);border:2px solid rgba(255,255,255,.5);border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:14px;overflow:hidden;flex-shrink:0}
        .profile-avatar img{width:100%;height:100%;object-fit:cover}
        .profile-info{text-align:left;line-height:1.3}.profile-name{font-weight:500;font-size:13px}.profile-email{font-size:11px;opacity:.75}
        .profile-arrow{font-size:10px;transition:transform .2s;opacity:.7}.profile-wrapper.open .profile-arrow{transform:rotate(180deg)}
        .profile-dropdown{position:absolute;top:calc(100% + 10px);right:0;background:var(--bg-dropdown);border:1px solid var(--border-color);border-radius:14px;box-shadow:var(--shadow-lg);min-width:270px;opacity:0;visibility:hidden;transform:translateY(-10px);transition:all .25s;z-index:999;overflow:hidden}
        .profile-wrapper.open .profile-dropdown{opacity:1;visibility:visible;transform:translateY(0)}
        .dropdown-menu{padding:6px 0}
        .dropdown-item{display:flex;align-items:center;gap:10px;padding:11px 20px;font-family:'Kanit',sans-serif;font-size:14px;color:var(--text-primary);transition:background .15s;cursor:pointer;border:none;background:none;width:100%;text-align:left}.dropdown-item:hover{background:var(--bg-hover)}
        .dropdown-item .icon{font-size:16px;width:22px;text-align:center}
        .dropdown-divider{height:1px;background:var(--border-color);margin:4px 0}
        .dropdown-item.danger{color:#e74c3c}.dropdown-item.danger:hover{background:#fff0ee}
        [data-theme="dark"] .dropdown-item.danger:hover{background:#3d1c1c}
        .dropdown-overlay{display:none;position:fixed;inset:0;z-index:998}.dropdown-overlay.active{display:block}
        .container{width:100%;margin:24px auto;padding:0 24px}
        .back-bar{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px}
        .btn-back{display:inline-flex;align-items:center;gap:8px;background:var(--bg-card);border:1px solid var(--border-color);color:var(--text-primary);padding:10px 20px;border-radius:10px;font-family:'Kanit',sans-serif;font-size:14px;transition:all .2s}.btn-back:hover{border-color:var(--accent);color:var(--accent)}
        /* ★ Email button */
        .btn-send-email{display:inline-flex;align-items:center;gap:8px;background:linear-gradient(135deg,#0078d4,#005a9e);border:none;color:#fff;padding:10px 20px;border-radius:10px;font-family:'Kanit',sans-serif;font-size:14px;cursor:pointer;transition:all .2s;box-shadow:0 2px 8px rgba(0,120,212,.3)}.btn-send-email:hover{opacity:.88;transform:translateY(-1px)}
        /* ★ Email modal */
        .email-modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:6000;display:none;align-items:center;justify-content:center;backdrop-filter:blur(4px)}
        .email-modal-overlay.active{display:flex}
        .email-modal{background:var(--bg-card);border-radius:16px;padding:28px;box-shadow:0 20px 60px rgba(0,0,0,.3);width:90%;max-width:540px;display:flex;flex-direction:column;gap:14px}
        .email-modal-header{display:flex;align-items:center;justify-content:space-between}
        .email-modal-header h3{font-size:17px;font-weight:600;display:flex;align-items:center;gap:8px}
        .email-modal-close{background:none;border:none;font-size:20px;cursor:pointer;color:var(--text-muted);line-height:1;padding:0 4px;border-radius:6px;transition:background .15s}.email-modal-close:hover{background:var(--bg-hover)}
        .email-field{display:flex;flex-direction:column;gap:5px}
        .email-field label{font-size:12px;font-weight:500;color:var(--text-muted)}
        .email-input{width:100%;padding:10px 14px;border:1px solid var(--border-color);border-radius:10px;font-family:'Kanit',sans-serif;font-size:14px;color:var(--text-primary);background:var(--bg-primary);outline:none;transition:border-color .2s;box-sizing:border-box}.email-input:focus{border-color:#0078d4}
        .email-textarea{min-height:110px;resize:vertical}
        .email-quick-recipients{display:flex;flex-wrap:wrap;gap:6px;margin-top:4px}
        .email-quick-btn{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:16px;border:1px solid #0078d4;background:none;color:#0078d4;font-family:'Kanit',sans-serif;font-size:11px;cursor:pointer;transition:all .15s}.email-quick-btn:hover{background:#0078d4;color:#fff}
        .email-modal-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:4px}
        .email-btn-cancel{padding:9px 20px;border-radius:10px;border:1px solid var(--border-color);background:none;color:var(--text-muted);font-family:'Kanit',sans-serif;font-size:13px;cursor:pointer;transition:all .2s}.email-btn-cancel:hover{border-color:var(--accent);color:var(--accent)}
        .email-btn-send{padding:9px 22px;border-radius:10px;border:none;background:linear-gradient(135deg,#0078d4,#005a9e);color:#fff;font-family:'Kanit',sans-serif;font-size:13px;font-weight:500;cursor:pointer;transition:opacity .2s;display:flex;align-items:center;gap:6px}.email-btn-send:hover{opacity:.88}.email-btn-send:disabled{opacity:.5;cursor:not-allowed}
        .email-status{font-size:12px;padding:8px 12px;border-radius:8px;display:none}
        .email-status.ok{display:block;background:#e8f5e9;color:#2e7d32;border:1px solid #a5d6a7}
        .email-status.err{display:block;background:#ffebee;color:#c62828;border:1px solid #ef9a9a}
        [data-theme="dark"] .email-status.ok{background:#1b3d1f;color:#81c784;border-color:#2e5e32}
        [data-theme="dark"] .email-status.err{background:#3d1b1b;color:#ef9a9a;border-color:#5e2e2e}
        .file-info-bar{background:var(--bg-card);border:1px solid var(--border-color);border-radius:14px;width:100%;padding:16px 24px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;box-shadow:var(--shadow)}
        .file-info-left{display:flex;align-items:center;gap:14px}
        /* ★ Reviewer Info Bar */
        .reviewer-info-bar{background:var(--bg-card);border:1px solid var(--border-color);border-radius:12px;padding:10px 20px;margin-bottom:16px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;box-shadow:var(--shadow);font-size:13px}
        .rib-label{font-weight:500;color:var(--text-muted);font-size:12px;white-space:nowrap}
        .rib-person{display:flex;flex-direction:column;align-items:flex-start;gap:4px;padding:4px 10px 4px 4px;border-radius:12px;background:var(--accent-light);border:1px solid var(--border-color);font-size:12px}
        .rib-person-row{display:inline-flex;align-items:center;gap:6px;flex-wrap:wrap}
        .rib-avatar{width:20px;height:20px;border-radius:50%;background:var(--accent);color:#fff;display:flex;align-items:center;justify-content:center;font-size:9px;font-weight:600;flex-shrink:0}
        .rib-dept{color:var(--text-muted);font-size:11px;font-weight:300}
        /* Reviewer personal status */
        .rib-rstatus{display:inline-flex;align-items:center;gap:4px;padding:1px 7px;border-radius:10px;font-size:11px;font-weight:500;white-space:nowrap}
        .rib-rstatus.approved{background:#e8f5e9;color:#2e7d32;border:1px solid #a5d6a7}
        .rib-rstatus.reviewing{background:#e3f2fd;color:#1565c0;border:1px solid #90caf9}
        .rib-rstatus.rejected{background:#ffebee;color:#c62828;border:1px solid #ef9a9a}
        [data-theme="dark"] .rib-rstatus.approved{background:#1b3d1f;color:#81c784;border-color:#2e5e32}
        [data-theme="dark"] .rib-rstatus.reviewing{background:#0a2a3d;color:#64b5f6;border-color:#1a3d5e}
        [data-theme="dark"] .rib-rstatus.rejected{background:#3d1b1b;color:#ef9a9a;border-color:#5e2e2e}
        .rib-rv-desc{font-size:11px;color:var(--text-muted);font-style:italic;padding:0 2px;max-width:340px;word-break:break-word}
        /* Status trigger button */
        .rib-status-btn{display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:10px;border:1px dashed var(--accent);background:none;color:var(--accent);font-family:'Kanit',sans-serif;font-size:11px;cursor:pointer;transition:all .15s;white-space:nowrap}
        .rib-status-btn:hover{background:var(--accent);color:#fff}
        /* Status panel */
        .rib-status-panel{display:none;flex-direction:column;gap:8px;padding:10px 12px;background:var(--bg-card);border:1px solid var(--border-color);border-radius:10px;box-shadow:var(--shadow);min-width:260px;margin-top:2px}
        .rib-status-panel.open{display:flex}
        .rib-sopt-group{display:flex;gap:6px;flex-wrap:wrap}
        .rib-sopt{padding:4px 12px;border-radius:16px;border:1px solid var(--border-color);background:var(--bg-primary);color:var(--text-primary);font-family:'Kanit',sans-serif;font-size:12px;cursor:pointer;transition:all .15s}
        .rib-sopt:hover{border-color:var(--accent);color:var(--accent)}
        .rib-sopt.rib-sopt-pass.active{background:#e8f5e9;color:#2e7d32;border-color:#a5d6a7;font-weight:500}
        .rib-sopt.rib-sopt-revise.active{background:#e3f2fd;color:#1565c0;border-color:#90caf9;font-weight:500}
        .rib-sopt.rib-sopt-fail.active{background:#ffebee;color:#c62828;border-color:#ef9a9a;font-weight:500}
        [data-theme="dark"] .rib-sopt{background:var(--bg-primary);border-color:var(--border-color);color:var(--text-primary)}
        [data-theme="dark"] .rib-sopt.rib-sopt-pass.active{background:#1b3d1f;color:#81c784;border-color:#2e5e32}
        [data-theme="dark"] .rib-sopt.rib-sopt-revise.active{background:#0a2a3d;color:#64b5f6;border-color:#1a3d5e}
        [data-theme="dark"] .rib-sopt.rib-sopt-fail.active{background:#3d1b1b;color:#ef9a9a;border-color:#5e2e2e}
        .rib-desc-ta{width:100%;min-height:64px;padding:8px 10px;border:1px solid var(--border-color);border-radius:8px;font-family:'Kanit',sans-serif;font-size:12px;color:var(--text-primary);background:var(--bg-primary);resize:vertical;outline:none;box-sizing:border-box;transition:border-color .2s}
        .rib-desc-ta:focus{border-color:var(--accent)}
        .rib-panel-actions{display:flex;gap:8px;justify-content:flex-end}
        .rib-save-btn{padding:5px 14px;border-radius:8px;border:none;background:linear-gradient(135deg,#ff8c42,#ff6b35);color:#fff;font-family:'Kanit',sans-serif;font-size:12px;cursor:pointer;transition:opacity .15s}
        .rib-save-btn:hover{opacity:.88}
        .rib-cancel-btn{padding:5px 14px;border-radius:8px;border:1px solid var(--border-color);background:none;color:var(--text-muted);font-family:'Kanit',sans-serif;font-size:12px;cursor:pointer}
        .rib-role-badge{padding:3px 10px;border-radius:16px;font-size:11px;font-weight:500}
        .rib-role-you{background:#e8f5e9;color:#2e7d32;border:1px solid #a5d6a7}
        [data-theme="dark"] .rib-role-you{background:#1b3d1f;color:#81c784;border-color:#2e5e32}
        .rib-remove-btn{background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:13px;line-height:1;padding:0 2px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;width:16px;height:16px;transition:all .15s;margin-left:2px;flex-shrink:0}
        .rib-remove-btn:hover{background:#e74c3c;color:#fff}
        .rib-add-btn{display:inline-flex;align-items:center;gap:4px;padding:4px 12px;border-radius:16px;border:1px dashed var(--accent);background:none;color:var(--accent);font-family:'Kanit',sans-serif;font-size:12px;cursor:pointer;transition:all .2s;white-space:nowrap;margin-left:4px}
        .rib-add-btn:hover{background:var(--accent);color:#fff}
        /* ★ Add Reviewer Modal */
        .reviewer-modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:5000;display:none;align-items:center;justify-content:center;backdrop-filter:blur(4px)}
        .reviewer-modal-overlay.active{display:flex}
        .reviewer-modal{background:var(--bg-card);border-radius:16px;padding:24px;box-shadow:var(--shadow-lg);width:90%;max-width:440px;max-height:80vh;display:flex;flex-direction:column;gap:14px}
        .reviewer-modal h3{font-size:16px;font-weight:600}
        .reviewer-modal-search{width:100%;padding:10px 14px;border:1px solid var(--border-color);border-radius:10px;font-family:'Kanit',sans-serif;font-size:14px;color:var(--text-primary);background:var(--bg-primary);outline:none;transition:border-color .2s}
        .reviewer-modal-search:focus{border-color:var(--accent)}
        .reviewer-modal-list{overflow-y:auto;flex:1;border:1px solid var(--border-color);border-radius:10px;max-height:300px}
        .reviewer-modal-item{display:flex;align-items:center;gap:10px;padding:10px 14px;cursor:pointer;transition:background .15s;border-bottom:1px solid var(--border-color)}
        .reviewer-modal-item:last-child{border-bottom:none}
        .reviewer-modal-item:hover:not(.already-added){background:var(--bg-hover)}
        .reviewer-modal-item.already-added{opacity:.5;cursor:not-allowed}
        .reviewer-modal-avatar{width:32px;height:32px;border-radius:50%;background:var(--accent);color:#fff;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:600;flex-shrink:0}
        .reviewer-modal-info{flex:1;min-width:0}
        .reviewer-modal-name{font-size:13px;font-weight:500}
        .reviewer-modal-dept{font-size:11px;color:var(--text-muted)}
        .reviewer-modal-check{font-size:16px;color:var(--accent);flex-shrink:0}
        .reviewer-modal-actions{display:flex;gap:8px;justify-content:flex-end}
        .reviewer-modal-btn{padding:8px 20px;border-radius:10px;font-family:'Kanit',sans-serif;font-size:13px;font-weight:500;cursor:pointer;border:none;transition:all .2s}
        .reviewer-modal-btn-cancel{background:var(--bg-primary);color:var(--text-muted);border:1px solid var(--border-color)}
        .reviewer-modal-btn-cancel:hover{border-color:var(--accent);color:var(--accent)}
        .reviewer-modal-btn-confirm{background:linear-gradient(135deg,#ff8c42,#ff6b35);color:#fff}
        .reviewer-modal-btn-confirm:hover{opacity:.9}
        .reviewer-modal-empty{text-align:center;padding:24px;color:var(--text-muted);font-size:13px}
        .file-icon{width:44px;height:44px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:22px}
        .file-icon.pdf{background:#ffebee}.file-icon.image{background:#e8f5e9}
        [data-theme="dark"] .file-icon.pdf{background:#3d1b1b}[data-theme="dark"] .file-icon.image{background:#1b3d1f}
        .file-title h2{font-size:16px;font-weight:600;word-break:break-all}
        .file-meta-inline{display:flex;gap:16px;font-size:12px;color:var(--text-muted);margin-top:2px}
        .status-badge{display:inline-flex;align-items:center;gap:6px;padding:5px 14px;border-radius:20px;font-size:12px;font-weight:500;white-space:nowrap}
        .status-badge.pending{background:#fff8e1;color:#f57f17;border:1px solid #ffe082}
        .status-badge.reviewing{background:#e3f2fd;color:#1565c0;border:1px solid #90caf9}
        .status-badge.approved{background:#e8f5e9;color:#2e7d32;border:1px solid #a5d6a7}
        .status-badge.rejected{background:#ffebee;color:#c62828;border:1px solid #ef9a9a}
        [data-theme="dark"] .status-badge.pending{background:#3d2e0a;color:#ffd54f;border-color:#5e4a1a}
        [data-theme="dark"] .status-badge.reviewing{background:#0a2a3d;color:#64b5f6;border-color:#1a3d5e}
        [data-theme="dark"] .status-badge.approved{background:#1b3d1f;color:#81c784;border-color:#2e5e32}
        [data-theme="dark"] .status-badge.rejected{background:#3d1b1b;color:#ef9a9a;border-color:#5e2e2e}
        .workspace{
            display:flex;
            flex-direction:column;
            gap:0;
            height:100%;
            width:100%;
            max-width:100%;
            min-width:0;
            overflow:hidden;
        }
        /* ★★★ HORIZONTAL TOOLBAR ★★★ */
        .toolbox{
            width:100%;background:var(--tool-bg);border:1px solid var(--tool-border);
            border-radius:14px;padding:12px 16px;box-shadow:var(--shadow);
            z-index:90;margin-bottom:12px;
            transition:padding .25s, border-radius .25s, box-shadow .25s, font-size .25s;
        }
        /* ★ Compact sticky toolbar when scrolled */
        .toolbox.toolbox-sticky{
            position:fixed;top:64px;left:0;right:0;
            margin:0;border-radius:0 0 10px 10px;
            padding:6px 16px;
            box-shadow:0 4px 16px rgba(0,0,0,.15);
            width:100%;max-width:100vw;
            z-index:300;
        }
        .toolbox.toolbox-sticky .tool-btn{padding:4px 7px;font-size:10px}
        .toolbox.toolbox-sticky .tool-btn .tool-icon{font-size:13px}
        .toolbox.toolbox-sticky .tool-btn .tool-shortcut{display:none}
        .toolbox.toolbox-sticky .tb-divider{height:24px}
        .toolbox.toolbox-sticky .action-btn{padding:6px 8px;font-size:11px}
        .toolbox.toolbox-sticky .action-btn.save{padding:8px 10px;font-size:12px}
        .toolbox.toolbox-sticky .size-slider{width:70px}
        .toolbox.toolbox-sticky .pdf-nav-btn{padding:4px 8px;font-size:11px}
        .toolbox.toolbox-sticky .pdf-page-display{padding:3px 8px;font-size:11px}
        .toolbox.toolbox-sticky .zoom-btn{width:28px;height:28px;font-size:11px}
        .toolbox.toolbox-sticky .zoom-display{font-size:12px;min-width:48px;padding:3px 6px}
        .toolbox.toolbox-sticky .color-swatch{width:20px;height:20px}
        .toolbox.toolbox-sticky .color-display{width:26px;height:26px}
        .toolbox-placeholder{display:none;margin-bottom:12px}
        .toolbox-title{display:none}
        .toolbox-inner{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
        .tool-section{margin-bottom:0}
        .tool-section-label{font-size:10px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;font-weight:500;white-space:nowrap}
        .tool-grid{display:flex;gap:4px;flex-wrap:wrap}
        .tool-btn{display:flex;align-items:center;gap:4px;padding:7px 10px;border:1px solid var(--tool-border);border-radius:8px;background:var(--tool-btn-bg);color:var(--text-primary);cursor:pointer;font-family:'Kanit',sans-serif;font-size:11px;transition:all .2s;flex-direction:row;white-space:nowrap}
        .tool-btn:hover{border-color:var(--accent);color:var(--accent)}
        .tool-btn.active{background:var(--tool-btn-active);color:var(--tool-btn-active-text);border-color:var(--tool-btn-active)}
        .tool-btn .tool-icon{font-size:15px}.tool-btn .tool-shortcut{font-size:9px;opacity:.5;margin-left:2px}
        .tool-grid-full{display:flex;gap:4px}
        .tb-divider{width:1px;height:32px;background:var(--tool-border);flex-shrink:0;margin:0 4px}
        .color-display{width:32px;height:32px;border-radius:8px;border:2px solid var(--tool-border);cursor:pointer;position:relative;overflow:hidden;flex-shrink:0}.color-display:hover{border-color:var(--accent)}
        .color-display input[type="color"]{position:absolute;inset:-10px;width:calc(100% + 20px);height:calc(100% + 20px);border:none;cursor:pointer}
        .color-swatches{display:flex;gap:4px;align-items:center}
        .color-swatch{width:24px;height:24px;border-radius:6px;cursor:pointer;border:2px solid transparent;transition:all .15s;flex-shrink:0}.color-swatch:hover{transform:scale(1.15)}.color-swatch.active{border-color:var(--text-primary);transform:scale(1.1)}
        .size-slider{width:100px;margin:0 4px;accent-color:var(--accent);vertical-align:middle}
        .size-adj-btn{width:24px;height:24px;border:1px solid var(--tool-border);border-radius:6px;background:var(--tool-btn-bg);color:var(--text-primary);cursor:pointer;font-size:14px;font-weight:700;display:inline-flex;align-items:center;justify-content:center;transition:all .2s;padding:0;line-height:1}
        .size-adj-btn:hover{border-color:var(--accent);color:var(--accent)}
        .toolbox.toolbox-sticky .size-adj-btn{width:20px;height:20px;font-size:12px}
        .tb-size-wrap{display:flex;align-items:center;gap:6px;font-size:11px;color:var(--text-muted);white-space:nowrap}
        .pdf-nav{display:flex;align-items:center;gap:6px}
        .pdf-nav-title{display:none}
        .pdf-nav-btn{padding:7px 12px;border:1px solid var(--tool-border);border-radius:8px;background:var(--tool-btn-bg);color:var(--text-primary);cursor:pointer;font-family:'Kanit',sans-serif;font-size:12px;transition:all .2s;display:flex;align-items:center;justify-content:center;gap:4px;white-space:nowrap}
        .pdf-nav-btn:hover{border-color:var(--accent);color:var(--accent)}.pdf-nav-btn:disabled{opacity:.35;cursor:not-allowed}
        .pdf-page-display{padding:6px 12px;border:2px solid var(--accent);border-radius:8px;background:var(--tool-btn-bg);color:var(--accent);font-family:'Kanit',sans-serif;font-size:13px;font-weight:600;text-align:center;white-space:nowrap;margin:0}
        .action-grid{display:flex;gap:4px;flex-wrap:wrap}
        .action-btn{display:flex;align-items:center;justify-content:center;gap:6px;padding:10px;border:1px solid var(--tool-border);border-radius:10px;background:var(--tool-btn-bg);color:var(--text-primary);cursor:pointer;font-family:'Kanit',sans-serif;font-size:12px;transition:all .2s}
        .action-btn:hover{border-color:var(--accent);color:var(--accent)}.action-btn:disabled{opacity:.4;cursor:not-allowed}
        .action-btn.save{grid-column:1/-1;background:linear-gradient(135deg,#ff8c42,#ff6b35);color:#fff;border:none;font-weight:500;font-size:14px;padding:12px}.action-btn.save:hover{opacity:.9;transform:translateY(-1px)}
        .action-btn.clear{border-color:#e74c3c;color:#e74c3c}.action-btn.clear:hover{background:#e74c3c;color:#fff}
                .canvas-area{
            flex:1;
            width:100%;
            max-width:100%;
            min-width:0;
            background:var(--bg-card);
            border:1px solid var(--border-color);
            border-radius:16px;
            overflow:hidden;
            box-shadow:var(--shadow);
            display:flex;
            flex-direction:column;
            box-sizing:border-box;
        }
        .canvas-wrapper{
            position:relative;
            overflow:auto;
            display:block;
            flex:1;
            width:100%;
            max-width:100%;
            min-width:0;
            min-height:500px;
            background:var(--bg-primary);
            box-sizing:border-box;
        }
        .canvas-scaler{
            transform-origin:top center;
            transition:transform 0.15s ease;
            display:block;
            margin:0 auto;
        }
        .canvas-container{position:relative;display:inline-block}
        .canvas-container img,.canvas-container .pdf-render{display:block;max-width:100%;height:auto}
        .canvas-container canvas.draw-layer{position:absolute;top:0;left:0;cursor:crosshair}
        .shortcuts-info{font-size:10px;color:var(--text-muted);line-height:1.8;margin-top:12px;padding-top:12px;border-top:1px solid var(--tool-border)}
        .shortcuts-info kbd{background:var(--tool-btn-bg);border:1px solid var(--tool-border);border-radius:4px;padding:1px 5px;font-family:'Kanit',sans-serif;font-size:10px}
        .footer{text-align:center;padding:36px;color:var(--text-muted);font-size:13px;font-weight:300}.footer-brand{color:var(--accent);font-weight:600;letter-spacing:2px}
        /* ================================
           TEXT BOX OVERLAY
        ================================ */
        .tbox-overlay{
            position:absolute;
            z-index:200;
            box-sizing:border-box;
            overflow:visible;
            /* transform-origin set dynamically */
        }
        .tbox-overlay.tbox-drawing{
            border:2px dashed var(--accent);
            background:rgba(255,107,53,.06);
            pointer-events:none;
        }
        .tbox-overlay.tbox-active{
            border:2px solid var(--accent);
            background:rgba(255,255,255,.5);
            backdrop-filter:blur(4px);
            box-shadow:0 2px 12px rgba(0,0,0,.2);
        }
        .tbox-overlay.tbox-saved{
            border:1.5px dashed rgba(255,107,53,.55);
            background:rgba(255,255,255,.5);
        }
        .tbox-overlay.tbox-saved:hover{
            border-color:var(--accent);
            background:rgba(255,255,255,.58);
        }
        .tbox-textarea{
            width:100%;
            height:100%;
            border:none;
            background:transparent;
            outline:none;
            resize:none;
            padding:4px 6px 38px 6px;
            box-sizing:border-box;
            overflow:hidden;
            word-break:break-word;
            cursor:text;
        }
        .tbox-overlay.tbox-active .tbox-textarea{
            padding-top:4px;
        }
        .tbox-textarea[readonly]{
            cursor:default;
            pointer-events:none;
        }
        /* ── textbox toolbar ── */
        .tbox-toolbar{
            position:absolute;top:-30px;left:0;
            height:28px;
            display:none;align-items:center;gap:3px;padding:0 6px;
            background:rgba(255,255,255,.97);
            border:1px solid rgba(255,107,53,.45);
            border-radius:8px 8px 0 0;
            z-index:15;cursor:move;
            white-space:nowrap;
            overflow:visible;
            user-select:none;box-sizing:border-box;
            width:max-content;
            min-width:100%;
            box-shadow:0 -2px 8px rgba(0,0,0,.12);
        }
        .tbox-overlay.tbox-active .tbox-toolbar{display:flex;}
        .tbox-font-sel{height:20px;max-width:88px;border:1px solid rgba(255,107,53,.4);border-radius:4px;background:rgba(255,255,255,.9);color:#333;font-size:11px;padding:0 2px;cursor:pointer;outline:none;}
        .tbox-size-sel{height:20px;width:46px;border:1px solid rgba(255,107,53,.4);border-radius:4px;background:rgba(255,255,255,.9);color:#333;font-size:11px;padding:0 2px;cursor:pointer;outline:none;}
        .tbox-color-inp{width:20px;height:20px;border:1px solid rgba(255,107,53,.4);border-radius:4px;padding:1px;background:rgba(255,255,255,.7);cursor:pointer;flex-shrink:0;}
        .tbox-confirm-btn{height:20px;padding:0 7px;border:none;border-radius:4px;background:linear-gradient(135deg,#ff8c42,#ff6b35);color:#fff;font-family:'Kanit',sans-serif;font-size:11px;font-weight:500;cursor:pointer;white-space:nowrap;margin-left:auto;flex-shrink:0;transition:opacity .15s;}
        .tbox-confirm-btn:hover{opacity:.85;}
        /* resize handle — bottom-right corner */
        .tbox-resize{
            position:absolute;
            right:-1px;
            bottom:-1px;
            width:14px;
            height:14px;
            cursor:se-resize;
            background:var(--accent);
            border-radius:3px 0 3px 0;
            opacity:0;
            transition:opacity .15s;
        }
        .tbox-overlay:hover .tbox-resize,
        .tbox-overlay.tbox-active .tbox-resize{
            opacity:1;
        }
        /* drag handle — top bar */
        .tbox-drag{
            position:absolute;
            top:0;left:0;right:14px;height:12px;
            cursor:move;
            background:transparent;
        }
        /* creator name + date label — bottom strip */
        .tbox-creator{
            position:absolute;
            bottom:3px;
            left:5px;
            right:16px;
            font-size:10px;
            line-height:1.4;
            color:rgba(0,0,0,.7);
            overflow:hidden;
            pointer-events:none;
            font-family:'Kanit',sans-serif;
        }
        .tbox-creator .tc-name{
            font-weight:600;
            display:block;
            white-space:nowrap;
            overflow:hidden;
            text-overflow:ellipsis;
        }
        .tbox-creator .tc-date{
            font-size:9px;
            opacity:.8;
            display:block;
            white-space:nowrap;
            overflow:hidden;
            text-overflow:ellipsis;
        }
        /* delete button */
        .tbox-del{
            position:absolute;
            top:-10px;right:-10px;
            width:20px;height:20px;
            background:#e74c3c;color:#fff;
            border:none;border-radius:50%;
            font-size:13px;line-height:1;
            cursor:pointer;
            display:none;
            align-items:center;justify-content:center;
            z-index:10;
            padding:0;
            box-shadow:0 2px 6px rgba(0,0,0,.3);
        }
        .tbox-overlay:hover .tbox-del,
        .tbox-overlay.tbox-active .tbox-del{
            display:flex;
        }
        /* ================================
           SHAPE OVERLAY SYSTEM
        ================================ */
        .sov-overlay{
            position:absolute;
            z-index:190;
            box-sizing:border-box;
            cursor:default;
        }
        .sov-overlay canvas{
            display:block;
            width:100%;height:100%;
        }
        .sov-overlay.sov-active canvas{
            outline:2px solid var(--accent);
            outline-offset:-1px;
        }
        .sov-overlay.sov-saved:hover canvas{
            outline:1.5px dashed rgba(255,107,53,.7);
            outline-offset:-1px;
        }
        /* resize handles: 8-point */
        .sov-handle{
            position:absolute;
            width:10px;height:10px;
            background:#fff;
            border:2px solid var(--accent);
            border-radius:2px;
            box-sizing:border-box;
            z-index:5;
            display:none;
        }
        .sov-overlay:hover .sov-handle,
        .sov-overlay.sov-active .sov-handle{ display:block; }
        .sov-handle.nw{top:-5px;left:-5px;cursor:nwse-resize;}
        .sov-handle.n {top:-5px;left:calc(50% - 5px);cursor:ns-resize;}
        .sov-handle.ne{top:-5px;right:-5px;cursor:nesw-resize;}
        .sov-handle.w {top:calc(50% - 5px);left:-5px;cursor:ew-resize;}
        .sov-handle.e {top:calc(50% - 5px);right:-5px;cursor:ew-resize;}
        .sov-handle.sw{bottom:-5px;left:-5px;cursor:nesw-resize;}
        .sov-handle.s {bottom:-5px;left:calc(50% - 5px);cursor:ns-resize;}
        .sov-handle.se{bottom:-5px;right:-5px;cursor:nwse-resize;}
        /* drag area — transparent overlay on top */
        .sov-drag{
            position:absolute;
            inset:0;
            cursor:move;
            z-index:4;
        }
        /* delete button */
        .sov-del{
            position:absolute;
            top:-10px;right:-10px;
            width:20px;height:20px;
            background:#e74c3c;color:#fff;
            border:none;border-radius:50%;
            font-size:13px;line-height:1;
            cursor:pointer;
            display:none;
            align-items:center;justify-content:center;
            z-index:10;
            padding:0;
            box-shadow:0 2px 6px rgba(0,0,0,.3);
        }
        .sov-overlay:hover .sov-del,
        .sov-overlay.sov-active .sov-del{ display:flex; }
        /* rotate handle */
        .sov-rotate-handle{
            position:absolute;
            top:-38px;left:calc(50% - 10px);
            width:20px;height:20px;
            background:#fff;
            border:2px solid var(--accent);
            border-radius:50%;
            cursor:grab;
            display:none;
            align-items:center;justify-content:center;
            font-size:14px;line-height:1;
            z-index:12;
            box-shadow:0 2px 6px rgba(0,0,0,.25);
            user-select:none;
            padding:0;
            color:var(--accent);
        }
        .sov-rotate-handle:active{cursor:grabbing;}
        .sov-rotate-connector{
            position:absolute;
            top:-18px;left:calc(50% - 1px);
            width:2px;height:18px;
            background:var(--accent);
            display:none;
            z-index:11;
            pointer-events:none;
            opacity:.7;
        }
        .sov-overlay:hover .sov-rotate-handle,
        .sov-overlay.sov-active .sov-rotate-handle{ display:flex; }
        .sov-overlay:hover .sov-rotate-connector,
        .sov-overlay.sov-active .sov-rotate-connector{ display:block; }
        /* angle badge — แสดงระหว่างหมุน */
        .sov-angle-badge{
            position:absolute;
            left:calc(50% + 14px);
            top:-44px;
            background:rgba(30,30,30,.82);
            color:#fff;
            font-size:12px;
            font-family:'Kanit',sans-serif;
            padding:2px 8px;
            border-radius:8px;
            white-space:nowrap;
            pointer-events:none;
            display:none;
            z-index:20;
            box-shadow:0 2px 6px rgba(0,0,0,.35);
            line-height:1.6;
        }
        /* Creator tooltip on hover */
        .sov-creator-tip{
            position:absolute;
            bottom:calc(100% + 6px);
            left:50%;
            transform:translateX(-50%);
            background:rgba(30,30,30,.82);
            color:#fff;
            font-size:11px;
            font-family:'Kanit',sans-serif;
            padding:3px 9px;
            border-radius:8px;
            white-space:nowrap;
            pointer-events:none;
            display:none;
            z-index:20;
            box-shadow:0 2px 6px rgba(0,0,0,.35);
            line-height:1.6;
        }
        .sov-overlay:hover .sov-creator-tip{ display:block; }
        /* Line tool: hide box border & middle handles, show only endpoint corners */
        .sov-overlay.sov-line canvas{ outline:none !important; }
        .sov-overlay.sov-line.sov-active canvas,
        .sov-overlay.sov-line.sov-saved:hover canvas{ outline:none !important; }
        .sov-overlay.sov-line .sov-handle.n,
        .sov-overlay.sov-line .sov-handle.s,
        .sov-overlay.sov-line .sov-handle.w,
        .sov-overlay.sov-line .sov-handle.e{ display:none !important; }
        .progress-overlay{position:fixed;inset:0;background:rgba(0,0,0,.7);display:none;align-items:center;justify-content:center;z-index:3000}.progress-overlay.active{display:flex}
        .progress-box{background:var(--bg-card);border-radius:16px;padding:32px 40px;text-align:center;box-shadow:var(--shadow-lg);min-width:300px}
        .progress-box h3{font-size:16px;margin-bottom:16px}
        .progress-bar-wrap{background:var(--bg-primary);border-radius:8px;height:12px;overflow:hidden;margin-bottom:12px}
        .progress-bar{height:100%;background:linear-gradient(135deg,#ff8c42,#ff6b35);border-radius:8px;transition:width .3s;width:0}
        .progress-text{font-size:13px;color:var(--text-muted)}
                /* ================================
           STATUS CHANGER
        ================================ */
        .status-wrapper {
            position: relative;
        }
        .status-trigger {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 16px;
            border-radius: 20px;
            font-family: 'Kanit', sans-serif;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
            white-space: nowrap;
        }
        .status-trigger:hover {
            filter: brightness(0.9);
            transform: translateY(-1px);
        }
        .status-trigger .status-arrow {
            font-size: 10px;
            transition: transform 0.2s;
            opacity: 0.7;
        }
        .status-wrapper.open .status-trigger .status-arrow {
            transform: rotate(180deg);
        }

        /* Status Colors */
        .status-trigger.pending {
            background: #fff8e1; color: #f57f17; border: 1px solid #ffe082;
        }
        .status-trigger.reviewing {
            background: #e3f2fd; color: #1565c0; border: 1px solid #90caf9;
        }
        .status-trigger.approved {
            background: #e8f5e9; color: #2e7d32; border: 1px solid #a5d6a7;
        }
        .status-trigger.rejected {
            background: #ffebee; color: #c62828; border: 1px solid #ef9a9a;
        }
        [data-theme="dark"] .status-trigger.pending {
            background: #3d2e0a; color: #ffd54f; border-color: #5e4a1a;
        }
        [data-theme="dark"] .status-trigger.reviewing {
            background: #0a2a3d; color: #64b5f6; border-color: #1a3d5e;
        }
        [data-theme="dark"] .status-trigger.approved {
            background: #1b3d1f; color: #81c784; border-color: #2e5e32;
        }
        [data-theme="dark"] .status-trigger.rejected {
            background: #3d1b1b; color: #ef9a9a; border-color: #5e2e2e;
        }

        /* Dropdown */
        .status-dropdown {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: var(--shadow-lg);
            min-width: 200px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-8px);
            transition: all 0.2s ease;
            z-index: 100;
            overflow: hidden;
        }
        .status-wrapper.open .status-dropdown {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        .status-dropdown-title {
            padding: 12px 16px 8px;
            font-size: 11px;
            font-weight: 500;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .status-option {
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            padding: 10px 16px;
            border: none;
            background: none;
            color: var(--text-primary);
            font-family: 'Kanit', sans-serif;
            font-size: 13px;
            cursor: pointer;
            transition: background 0.15s;
            text-align: left;
        }
        .status-option:hover {
            background: var(--bg-hover);
        }
        .status-option.active {
            background: var(--accent-light);
            color: var(--accent);
            font-weight: 500;
        }
        .status-option .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            flex-shrink: 0;
        }
        .status-dot.pending { background: #f57f17; }
        .status-dot.reviewing { background: #1565c0; }
        .status-dot.approved { background: #2e7d32; }
        .status-dot.rejected { background: #c62828; }

        .status-option .check-icon {
            margin-left: auto;
            font-size: 14px;
            opacity: 0;
        }
        .status-option.active .check-icon {
            opacity: 1;
        }

        .status-overlay {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 99;
        }
        .status-overlay.active {
            display: block;
        }

        /* Toast notification */
        .toast {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 14px 24px;
            border-radius: 12px;
            font-family: 'Kanit', sans-serif;
            font-size: 14px;
            font-weight: 400;
            box-shadow: 0 8px 30px rgba(0,0,0,0.2);
            z-index: 5000;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.3s ease;
        }
        .toast.show {
            transform: translateY(0);
            opacity: 1;
        }
        .toast.success {
            background: #2e7d32;
            color: #fff;
        }
        .toast.error {
            background: #c62828;
            color: #fff;
        }

                /* ================================
           ZOOM CONTROLS
        ================================ */
        .zoom-section { margin-bottom: 18px; }
        .zoom-section-label {
            font-size: 11px; color: var(--text-muted); text-transform: uppercase;
            letter-spacing: .5px; margin-bottom: 8px; font-weight: 500;
        }
        .zoom-controls {
            display: flex; align-items: center; gap: 6px;
        }
        .zoom-btn {
            flex: 1;
            display: flex; align-items: center; justify-content: center; gap: 4px;
            padding: 9px 6px;
            border: 1px solid var(--tool-border); border-radius: 10px;
            background: var(--tool-btn-bg); color: var(--text-primary);
            cursor: pointer; font-family: 'Kanit', sans-serif; font-size: 14px;
            font-weight: 500; transition: all 0.2s;
        }
        .zoom-btn:hover { border-color: var(--accent); color: var(--accent); }
        .zoom-btn:active { transform: scale(0.95); }
        .zoom-btn:disabled { opacity: 0.35; cursor: not-allowed; }

        .zoom-display {
            flex: 1.2;
            padding: 9px 6px;
            border: 2px solid var(--accent); border-radius: 10px;
            background: var(--tool-btn-bg); color: var(--accent);
            font-family: 'Kanit', sans-serif; font-size: 14px; font-weight: 600;
            text-align: center; white-space: nowrap; cursor: pointer;
            transition: all 0.2s;
        }
        .zoom-display:hover { background: var(--accent); color: var(--tool-btn-active-text); }

        .zoom-slider-wrap { margin-top: 8px; }
        .zoom-slider {
            width: 100%; margin: 0; accent-color: var(--accent);
        }
        .zoom-presets {
            display: grid; grid-template-columns: repeat(4, 1fr); gap: 4px; margin-top: 8px;
        }
        .zoom-preset {
            padding: 5px 2px;
            border: 1px solid var(--tool-border); border-radius: 6px;
            background: var(--tool-btn-bg); color: var(--text-primary);
            cursor: pointer; font-family: 'Kanit', sans-serif; font-size: 11px;
            text-align: center; transition: all 0.2s;
        }
        .zoom-preset:hover { border-color: var(--accent); color: var(--accent); }
        .zoom-preset.active { background: var(--accent); color: #fff; border-color: var(--accent); }
        
                /* ================================
           DRAWING TOOLTIP
        ================================ */
        .drawing-tooltip {
            position: fixed;
            pointer-events: none;
            z-index: 4000;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 10px 14px;
            box-shadow: var(--shadow-lg);
            font-family: 'Kanit', sans-serif;
            font-size: 12px;
            opacity: 0;
            transform: translateY(4px);
            transition: opacity 0.15s, transform 0.15s;
            max-width: 250px;
            white-space: nowrap;
        }
        .drawing-tooltip.show {
            opacity: 1;
            transform: translateY(0);
        }
        .drawing-tooltip .tip-row {
            display: flex;
            align-items: center;
            gap: 6px;
            line-height: 1.6;
        }
        .drawing-tooltip .tip-icon {
            font-size: 14px;
            flex-shrink: 0;
        }
        .drawing-tooltip .tip-label {
            color: var(--text-muted);
            font-weight: 300;
        }
        .drawing-tooltip .tip-value {
            color: var(--text-primary);
            font-weight: 500;
        }
        .drawing-tooltip .tip-divider {
            height: 1px;
            background: var(--border-color);
            margin: 5px 0;
        }

        /* ★ Indicator ว่ามีรอยวาดอยู่ */
        .drawing-indicator {
            position: absolute;
            top: 8px;
            right: 8px;
            z-index: 50;
            display: none;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: 8px;
            background: rgba(0,0,0,0.6);
            color: #fff;
            font-family: 'Kanit', sans-serif;
            font-size: 11px;
            backdrop-filter: blur(4px);
        }
        .drawing-indicator.show { display: inline-flex; }
        .drawing-indicator .indicator-dot {
            width: 8px; height: 8px; border-radius: 50%;
            background: #2ecc71;
            animation: pulse-dot 1.5s infinite;
        }

                /* ================================
           ANNOTATION INFO BAR
        ================================ */
        .annotation-info {
            display: none;
            padding: 10px 20px;
            background: var(--accent-light);
            border-bottom: 1px solid var(--border-color);
            font-size: 13px;
            color: var(--text-primary);
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            animation: slideDown 0.3s ease;
        }
        .annotation-info.visible {
            display: flex;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .annotation-info .anno-item {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }
        .annotation-info .anno-icon {
            font-size: 14px;
        }
        .annotation-info .anno-label {
            font-size: 11px;
            color: var(--text-muted);
            font-weight: 400;
        }
        .annotation-info .anno-value {
            font-weight: 500;
        }
        .annotation-info .anno-divider {
            width: 1px;
            height: 16px;
            background: var(--border-color);
        }
        .annotation-info .anno-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
            background: var(--accent);
            color: #fff;
        }
        .annotation-info .anno-page-badge {
            background: var(--tool-btn-bg);
            color: var(--accent);
            border: 1px solid var(--accent);
        }

                /* ================================
           DRAW TOOLTIP (ชี้เส้นที่วาด)
        ================================ */
        .draw-tooltip {
            position: fixed;
            pointer-events: none;
            z-index: 2000;
            background: rgba(30, 30, 30, 0.92);
            color: #fff;
            padding: 8px 14px;
            border-radius: 10px;
            font-family: 'Kanit', sans-serif;
            font-size: 12px;
            line-height: 1.6;
            white-space: nowrap;
            opacity: 0;
            transform: translateY(4px);
            transition: opacity 0.15s, transform 0.15s;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            max-width: 300px;
        }
        .draw-tooltip.visible {
            opacity: 1;
            transform: translateY(0);
        }
        .draw-tooltip .tt-row {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .draw-tooltip .tt-row + .tt-row {
            margin-top: 2px;
        }
        .draw-tooltip .tt-icon {
            font-size: 12px;
            flex-shrink: 0;
        }
        .draw-tooltip .tt-label {
            color: rgba(255,255,255,0.6);
            font-size: 11px;
        }
        .draw-tooltip .tt-value {
            font-weight: 500;
        }
        .draw-tooltip .tt-divider {
            height: 1px;
            background: rgba(255,255,255,0.15);
            margin: 4px 0;
        }

                /* ================================
           COMMENTS SECTION
        ================================ */
        .comments-section {
            width: 100%;
            margin: 30px auto 0;
            padding: 0 24px;
        }
        .comments-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        .comments-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .comments-header h3 {
            font-size: 16px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .comment-count-badge {
            background: var(--accent);
            color: #fff;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        /* ★ Comment Form */
        .comment-form {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            gap: 12px;
            align-items: flex-start;
        }
        .comment-form-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--accent-light);
            border: 2px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
            color: var(--accent);
            flex-shrink: 0;
            overflow: hidden;
        }
        .comment-form-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .comment-form-body {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .comment-textarea {
            width: 100%;
            min-height: 70px;
            padding: 12px 14px;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            font-family: 'Kanit', sans-serif;
            font-size: 14px;
            color: var(--text-primary);
            background: var(--bg-primary);
            resize: vertical;
            transition: border-color 0.2s;
        }
        .comment-textarea:focus {
            outline: none;
            border-color: var(--accent);
        }
        .comment-textarea::placeholder {
            color: var(--text-muted);
        }
        .comment-form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }
        .btn-comment {
            padding: 8px 20px;
            border: none;
            border-radius: 10px;
            font-family: 'Kanit', sans-serif;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-comment-submit {
            background: linear-gradient(135deg, #ff8c42, #ff6b35);
            color: #fff;
        }
        .btn-comment-submit:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        .btn-comment-submit:disabled {
            opacity: 0.4;
            cursor: not-allowed;
            transform: none;
        }
        .btn-comment-cancel {
            background: var(--bg-primary);
            color: var(--text-muted);
            border: 1px solid var(--border-color);
        }
        .btn-comment-cancel:hover {
            border-color: var(--accent);
            color: var(--accent);
        }

        /* ★ Comment Attachment */
        .btn-attach {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 8px 14px;
            border: 1px dashed var(--border-color);
            border-radius: 10px;
            background: none;
            color: var(--text-muted);
            font-family: 'Kanit', sans-serif;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-attach:hover {
            border-color: var(--accent);
            color: var(--accent);
            background: var(--accent-light);
        }
        .comment-attach-preview {
            position: relative;
            display: inline-block;
            margin-top: 6px;
        }
        .comment-attach-preview img {
            max-width: 200px;
            max-height: 160px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            object-fit: cover;
            display: block;
        }
        .comment-attach-remove {
            position: absolute;
            top: -8px;
            right: -8px;
            width: 20px;
            height: 20px;
            background: #e74c3c;
            color: #fff;
            border: none;
            border-radius: 50%;
            font-size: 12px;
            line-height: 1;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            box-shadow: 0 2px 6px rgba(0,0,0,.3);
        }
        /* attachment thumbnail in comment body */
        .comment-attachment {
            margin-top: 8px;
        }
        .comment-attachment img {
            max-width: 280px;
            max-height: 220px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            object-fit: cover;
            cursor: pointer;
            transition: opacity .15s;
            display: block;
        }
        .comment-attachment img:hover { opacity: .85; }
        /* lightbox */
        .img-lightbox {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,.85);
            z-index: 9000;
            display: none;
            align-items: center;
            justify-content: center;
            cursor: zoom-out;
        }
        .img-lightbox.active { display: flex; }
        .img-lightbox img {
            max-width: 92vw;
            max-height: 92vh;
            border-radius: 8px;
            box-shadow: 0 8px 40px rgba(0,0,0,.6);
            object-fit: contain;
            cursor: default;
        }

        /* ★ Comment List */
        .comments-list {
            padding: 0;
        }
        .comment-item {
            padding: 16px 24px;
            border-bottom: 1px solid var(--border-color);
            transition: background 0.15s;
        }
        .comment-item:last-child { border-bottom: none; }
        .comment-item:hover { background: var(--bg-hover); }
        .comment-main {
            display: flex;
            gap: 12px;
            align-items: flex-start;
        }
        .comment-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--accent-light);
            border: 2px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 13px;
            color: var(--accent);
            flex-shrink: 0;
            overflow: hidden;
        }
        .comment-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .comment-body { flex: 1; min-width: 0; }
        .comment-meta {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 4px;
            flex-wrap: wrap;
        }
        .comment-author {
            font-weight: 600;
            font-size: 13px;
        }
        .comment-time {
            font-size: 11px;
            color: var(--text-muted);
            font-weight: 300;
        }
        .comment-owner-badge {
            font-size: 10px;
            padding: 1px 8px;
            border-radius: 8px;
            background: var(--accent);
            color: #fff;
            font-weight: 500;
        }
        .comment-text {
            font-size: 14px;
            line-height: 1.6;
            word-break: break-word;
            color: var(--text-primary);
        }
        .comment-actions {
            display: flex;
            gap: 12px;
            margin-top: 8px;
        }
        .comment-action-btn {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: none;
            border: none;
            font-family: 'Kanit', sans-serif;
            font-size: 12px;
            color: var(--text-muted);
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 6px;
            transition: all 0.15s;
        }
        .comment-action-btn:hover {
            background: var(--bg-primary);
            color: var(--accent);
        }
        .comment-action-btn.liked {
            color: #e74c3c;
        }
        .comment-action-btn.liked:hover {
            color: #c0392b;
        }
        .like-count { font-weight: 500; }
        .comment-action-btn .act-icon { font-size: 14px; }

        /* ★ Reply */
        .comment-replies {
            margin-left: 48px;
            border-left: 2px solid var(--border-color);
            margin-top: 8px;
        }
        .comment-replies .comment-item {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border-color);
        }
        .comment-replies .comment-item:last-child { border-bottom: none; }
        .comment-replies .comment-avatar {
            width: 30px;
            height: 30px;
            font-size: 11px;
        }
        .reply-form {
            margin-left: 48px;
            margin-top: 8px;
            padding: 12px 16px;
            background: var(--bg-primary);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            display: none;
        }
        .reply-form.active { display: block; }
        .reply-form .reply-to-label {
            font-size: 12px;
            color: var(--text-muted);
            margin-bottom: 8px;
        }
        .reply-textarea {
            width: 100%;
            min-height: 50px;
            padding: 10px 12px;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            font-family: 'Kanit', sans-serif;
            font-size: 13px;
            color: var(--text-primary);
            background: var(--bg-card);
            resize: vertical;
        }
        .reply-textarea:focus { outline: none; border-color: var(--accent); }
        .reply-actions {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 8px;
        }

        /* ★ Empty State */
        .comments-empty {
            padding: 40px 24px;
            text-align: center;
            color: var(--text-muted);
        }
        .comments-empty .empty-icon { font-size: 40px; margin-bottom: 8px; }
        .comments-empty h4 { font-size: 14px; font-weight: 500; color: var(--text-primary); }
        .comments-empty p { font-size: 12px; font-weight: 300; }

        /* ★ Loading */
        .comments-loading {
            padding: 30px;
            text-align: center;
            color: var(--text-muted);
            font-size: 14px;
        }

                /* ================================
           UNSAVED CHANGES WARNING
        ================================ */
        .unsaved-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.65);
            z-index: 6000;
            display: none;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            animation: fadeInOverlay 0.2s ease;
        }
        .unsaved-overlay.active {
            display: flex;
        }
        @keyframes fadeInOverlay {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .unsaved-modal {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 36px 32px 28px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
            text-align: center;
            max-width: 420px;
            width: 90%;
            animation: slideUpModal 0.3s ease;
        }
        @keyframes slideUpModal {
            from { opacity: 0; transform: translateY(30px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .unsaved-icon {
            font-size: 48px;
            margin-bottom: 12px;
            animation: shakeIcon 0.5s ease 0.3s;
        }
        @keyframes shakeIcon {
            0%, 100% { transform: rotate(0); }
            20% { transform: rotate(-10deg); }
            40% { transform: rotate(10deg); }
            60% { transform: rotate(-5deg); }
            80% { transform: rotate(5deg); }
        }
        .unsaved-modal h3 {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
        }
        .unsaved-modal p {
            font-size: 14px;
            color: var(--text-secondary);
            line-height: 1.6;
            margin-bottom: 16px;
            font-weight: 300;
        }
        .unsaved-info {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 10px;
            background: #fff8e1;
            border: 1px solid #ffe082;
            color: #f57f17;
            font-size: 12px;
            font-weight: 500;
            margin-bottom: 20px;
        }
        [data-theme="dark"] .unsaved-info {
            background: #3d2e0a;
            border-color: #5e4a1a;
            color: #ffd54f;
        }
        .unsaved-actions {
            display: flex;
            gap: 10px;
            flex-direction: column;
        }
        .unsaved-btn {
            padding: 12px 24px;
            border: none;
            border-radius: 12px;
            font-family: 'Kanit', sans-serif;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .unsaved-btn:hover {
            transform: translateY(-1px);
        }
        .unsaved-btn-stay {
            background: linear-gradient(135deg, #ff8c42, #ff6b35);
            color: #fff;
            box-shadow: 0 4px 15px rgba(255, 107, 53, 0.4);
        }
        .unsaved-btn-stay:hover {
            box-shadow: 0 6px 25px rgba(255, 107, 53, 0.5);
        }
        .unsaved-btn-leave {
            background: var(--bg-primary);
            color: var(--text-muted);
            border: 1px solid var(--border-color);
        }
        .unsaved-btn-leave:hover {
            border-color: #e74c3c;
            color: #e74c3c;
            background: #fff0ee;
        }
        [data-theme="dark"] .unsaved-btn-leave:hover {
            background: #3d1b1b;
            border-color: #ef9a9a;
            color: #ef9a9a;
        }

        @keyframes pulse-dot {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }

        @media(max-width:900px){.workspace{flex-direction:column}.toolbox{width:100%;position:static;order:2}.canvas-area{order:1;width:100%}.tool-grid{grid-template-columns:repeat(4,1fr)}.color-swatches{grid-template-columns:repeat(10,1fr)}.navbar{padding:0 16px}.nav-links{display:none}.profile-info{display:none}}
    </style>
</head>
<body>
    <div class="dropdown-overlay" id="dropdownOverlay"></div>
    <!-- text overlay removed: text boxes are inline overlays on the canvas now -->

    <!-- ★ INSERT IMAGE ★ -->
    <input type="file" id="imageFileInput" accept="image/*" style="display:none">
    <div class="progress-overlay" id="progressOverlay"><div class="progress-box"><h3 id="progressTitle">⏳ กำลังประมวลผล...</h3><div class="progress-bar-wrap"><div class="progress-bar" id="progressBar"></div></div><div class="progress-text" id="progressText">กำลังเตรียม...</div></div></div>

    <!-- ★ UNSAVED CHANGES WARNING MODAL ★ -->
    <div class="unsaved-overlay" id="unsavedOverlay">
        <div class="unsaved-modal">
            <div class="unsaved-icon">⚠️</div>
            <h3>คุณยังไม่ได้บันทึก!</h3>
            <p>คุณมีการใช้งานเครื่องมือวาดภาพ<br>คุณต้องการออกโดยไม่บันทึกหรือไม่?</p>
            <div class="unsaved-info" id="unsavedInfo"></div>
            <div class="unsaved-actions">
                <button class="unsaved-btn unsaved-btn-stay" id="btnStay">
                    ✏️ อยู่ต่อ (บันทึกก่อน)
                </button>
                <button class="unsaved-btn unsaved-btn-leave" id="btnLeave">
                    🚪 ออกโดยไม่บันทึก
                </button>
            </div>
        </div>
    </div>

    <nav class="navbar">
        <div class="nav-left">
            <a href="../index.php" class="nav-logo"><img src="../img/logo.jpg" alt="Lolane" class="nav-logo-img">Lolane Portal</a>
            <div class="nav-links">
                <a href="../index.php" class="nav-link">🏠 หน้าหลัก</a>
                <a href="upload.php" class="nav-link">📁 อัปโหลด</a>
                <?php if (in_array(trim($_SESSION['department'] ?? ''), ['IT','HR'], true)): ?>
                <a href="logdraw.php" class="nav-link active">📜 ประวัติการใช้งาน</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="nav-right">
            <label class="theme-toggle"><input type="checkbox" id="themeToggle"><span class="toggle-slider"></span></label>
            <div class="profile-wrapper" id="profileWrapper">
                <button class="profile-trigger" id="profileTrigger">
                    <div class="profile-avatar">
                        <?php if ($userAvatar): ?>
                            <img src="data:image/jpeg;base64,<?= $userAvatar ?>" alt="avatar">
                        <?php else: ?>
                            <?= $initial ?>
                        <?php endif; ?>
                    </div>
                    <div class="profile-info"><div class="profile-name"><?= $displayName ?></div><div class="profile-email"><?= $email ?></div></div>
                    <span class="profile-arrow">▼</span>
                </button>
                <div class="profile-dropdown"><div class="dropdown-menu">
                    <a href="../dashboard.php" class="dropdown-item"><span class="icon">👤</span> โปรไฟล์</a>
                    <button class="dropdown-item" onclick="toggleThemeFromMenu()"><span class="icon" id="themeMenuIcon">🌙</span><span id="themeMenuText">Dark Mode</span></button>
                    <div class="dropdown-divider"></div>
                    <a href="../logout.php" class="dropdown-item danger"><span class="icon">🚪</span> ออกจากระบบ</a>
                </div></div>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="back-bar">
            <a href="select_file.php" class="btn-back">← กลับ</a>
            <button class="btn-send-email" id="btnOpenEmail" title="ส่งอีเมล Outlook">📧 ส่งอีเมล</button>
        </div>
                <div class="file-info-bar">
            <div class="file-info-left">
                <div class="file-icon <?= $file['file_type'] ?>"><?= $isPDF?'📄':'🖼️' ?></div>
                <div class="file-title"><h2><?= htmlspecialchars($file['file_name']) ?></h2>
                <div class="file-meta-inline"><span>👤 <?= htmlspecialchars($file['uploader_name']) ?></span><span>📅 <?= date('d/m/Y H:i',strtotime($file['uploaded_at'])) ?></span><span>📦 <?= formatFileSize($file['file_size']) ?></span></div></div>
            </div>

            <!-- ★ STATUS CHANGER ★ -->
            <?php if ($canEdit): ?>
            <div class="status-wrapper" id="statusWrapper">
                <div class="status-overlay" id="statusOverlay"></div>
                <button class="status-trigger <?= getStatusClass($file['status']) ?>" id="statusTrigger">
                    <span id="statusIcon"><?= getStatusIcon($file['status']) ?></span>
                    <span id="statusText"><?= htmlspecialchars($file['status']) ?></span>
                    <span class="status-arrow">▼</span>
                </button>
                <div class="status-dropdown" id="statusDropdown">
                    <div class="status-dropdown-title">📌 เปลี่ยนสถานะ</div>
                    <button class="status-option <?= $file['status']==='รอตรวจ'?'active':'' ?>"
                            data-status="รอตรวจ" data-icon="⏳" data-class="pending">
                        <span class="status-dot pending"></span>
                        ⏳ รอตรวจ
                        <span class="check-icon">✓</span>
                    </button>
                    <button class="status-option <?= $file['status']==='กำลังตรวจ'?'active':'' ?>"
                            data-status="กำลังตรวจ" data-icon="🔄" data-class="reviewing">
                        <span class="status-dot reviewing"></span>
                        🔄 กำลังตรวจ
                        <span class="check-icon">✓</span>
                    </button>
                    <button class="status-option <?= $file['status']==='ผ่าน'?'active':'' ?>"
                            data-status="ผ่าน" data-icon="✅" data-class="approved">
                        <span class="status-dot approved"></span>
                        ✅ ผ่าน
                        <span class="check-icon">✓</span>
                    </button>
                    <button class="status-option <?= $file['status']==='ไม่ผ่าน'?'active':'' ?>"
                            data-status="ไม่ผ่าน" data-icon="❌" data-class="rejected">
                        <span class="status-dot rejected"></span>
                        ❌ ไม่ผ่าน
                        <span class="check-icon">✓</span>
                    </button>
                </div>
            </div>
            <?php else: ?>
            <span class="status-badge <?= getStatusClass($file['status']) ?>">
                <?= getStatusIcon($file['status']) ?> <?= htmlspecialchars($file['status']) ?>
            </span>
            <?php endif; ?>
        </div>

        <!-- ★★★ REVIEWER INFO BAR ★★★ -->
        <?php if (!empty($reviewerList) || $canEdit): ?>
        <div class="reviewer-info-bar" id="reviewerInfoBar">
            <span class="rib-label">👥 ผู้ตรวจงาน:</span>
            <span id="reviewerTagsContainer" style="display:inline-flex;align-items:flex-start;gap:6px;flex-wrap:wrap">
            <?php foreach ($reviewerList as $rv):
                $canRemove   = $isOwner || ($isReviewer && (int)$rv['assigned_by'] === $userId);
                $isSelf      = ((int)$rv['reviewer_user_id'] === $userId);
                $rvSClass    = !empty($rv['rv_status']) ? getReviewerStatusClass($rv['rv_status']) : '';
                $rvSIcon     = !empty($rv['rv_status']) ? getReviewerStatusIcon($rv['rv_status']) : '';
                $canSetStatus = $isSelf && ($file['status'] === 'รอตรวจ');
            ?>
            <div class="rib-person" data-reviewer-id="<?= (int)$rv['reviewer_user_id'] ?>">
                <!-- ── Row: avatar / name / dept / status badge / remove / (self: trigger btn) ── -->
                <div class="rib-person-row">
                    <span class="rib-avatar"><?= mb_substr($rv['display_name'], 0, 1) ?></span>
                    <span><?= htmlspecialchars($rv['display_name']) ?></span>
                    <?php if (!empty($rv['department'])): ?>
                        <span class="rib-dept">(<?= htmlspecialchars($rv['department']) ?>)</span>
                    <?php endif; ?>
                    <?php if (!empty($rv['rv_status'])): ?>
                        <span class="rib-rstatus <?= $rvSClass ?>"<?= $isSelf ? ' id="ribMyStatusBadge"' : '' ?>>
                            <?= $rvSIcon ?> <?= htmlspecialchars($rv['rv_status']) ?>
                        </span>
                    <?php endif; ?>
                    <?php if ($canRemove): ?>
                    <button class="rib-remove-btn"
                            data-reviewer-id="<?= (int)$rv['reviewer_user_id'] ?>"
                            data-name="<?= htmlspecialchars($rv['display_name']) ?>"
                            title="ลบ <?= htmlspecialchars($rv['display_name']) ?> ออกจากผู้ตรวจ"
                            onclick="removeReviewer(<?= (int)$rv['reviewer_user_id'] ?>, '<?= htmlspecialchars($rv['display_name'], ENT_QUOTES) ?>', this)">×</button>
                    <?php endif; ?>
                    <?php if ($canSetStatus): ?>
                    <button class="rib-status-btn" id="ribMyStatusBtn" onclick="toggleRibPanel()" title="ตั้งสถานะการตรวจ">
                        📋 สถานะ ▾
                    </button>
                    <?php elseif ($isSelf): ?>
                    <button class="rib-status-btn" id="ribMyStatusBtn" onclick="toggleRibPanel()" title="ดูสถานะการตรวจ">
                        📋 สถานะ ▾
                    </button>
                    <?php endif; ?>
                </div>
                <?php if (!empty($rv['rv_description'])): ?>
                <!-- ── Description (visible to all) ── -->
                <div class="rib-rv-desc"<?= $isSelf ? ' id="ribMyDesc"' : '' ?>>📝 <?= htmlspecialchars($rv['rv_description']) ?></div>
                <?php endif; ?>
                <?php if ($isSelf): ?>
                <!-- ── Status panel (shown when trigger clicked, editable only when file_status=รอตรวจ) ── -->
                <div class="rib-status-panel" id="ribStatusPanel">
                    <div class="rib-sopt-group">
                        <button class="rib-sopt rib-sopt-pass<?= $rv['rv_status']==='ผ่าน' ? ' active' : '' ?>"
                                data-val="ผ่าน" onclick="selectRibStatus(this)">✅ ผ่าน</button>
                        <button class="rib-sopt rib-sopt-revise<?= $rv['rv_status']==='แก้ไข' ? ' active' : '' ?>"
                                data-val="แก้ไข" onclick="selectRibStatus(this)">✏️ แก้ไข</button>
                        <button class="rib-sopt rib-sopt-fail<?= $rv['rv_status']==='ไม่ผ่าน' ? ' active' : '' ?>"
                                data-val="ไม่ผ่าน" onclick="selectRibStatus(this)">❌ ไม่ผ่าน</button>
                    </div>
                    <textarea class="rib-desc-ta" id="ribDescTa" placeholder="หมายเหตุ / รายละเอียด..."<?= !$canSetStatus ? ' disabled' : '' ?>><?= htmlspecialchars($rv['rv_description'] ?? '') ?></textarea>
                    <?php if ($canSetStatus): ?>
                    <div class="rib-panel-actions">
                        <button class="rib-cancel-btn" onclick="toggleRibPanel()">ยกเลิก</button>
                        <button class="rib-save-btn" onclick="saveMyReviewStatus()">💾 บันทึก</button>
                    </div>
                    <?php else: ?>
                    <div style="font-size:11px;color:var(--text-muted);text-align:center">สถานะไฟล์ต้องเป็น "รอตรวจ" จึงจะแก้ไขได้</div>
                    <div class="rib-panel-actions">
                        <button class="rib-cancel-btn" onclick="toggleRibPanel()">ปิด</button>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            </span>
            <?php if ($canEdit): ?>
                <button class="rib-add-btn" id="btnAddReviewer" title="เพิ่มผู้ตรวจงาน">➕ เพิ่มผู้ตรวจ</button>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="workspace">
            <div class="toolbox"<?= !$canEdit ? ' style="display:none"' : '' ?>>
                <div class="toolbox-title">✏️ เครื่องมือ</div>
                <div class="toolbox-inner">

                <?php if($isPDF): ?>
                <div class="pdf-nav">
                    <button class="pdf-nav-btn" id="btnPrevPage" disabled>‹</button>
                    <div class="pdf-page-display" id="pageDisplay">1 / 1</div>
                    <button class="pdf-nav-btn" id="btnNextPage">›</button>
                </div>
                <div class="tb-divider"></div>
                <?php endif; ?>

                <div class="tool-section">
                <div class="tool-grid">
                    <button class="tool-btn" data-tool="hand" title="Hand / Move (H)"><span class="tool-icon">🖐️</span>Hand<span class="tool-shortcut">H</span></button>
                    <button class="tool-btn" data-tool="text" title="Text (T)"><span class="tool-icon">🔤</span>Text<span class="tool-shortcut">T</span></button>
                    <button class="tool-btn active" data-tool="pen" title="Pen (B)"><span class="tool-icon">🖌️</span>Pen<span class="tool-shortcut">B</span></button>
                    <button class="tool-btn" data-tool="pencil" title="Pencil (P)"><span class="tool-icon">✏️</span>Pencil<span class="tool-shortcut">P</span></button>
                    <button class="tool-btn" data-tool="marker" title="Marker (M)"><span class="tool-icon">🖍️</span>Marker<span class="tool-shortcut">M</span></button>
                    <button class="tool-btn" data-tool="eraser" title="Eraser (E)"><span class="tool-icon">🧹</span>Eraser<span class="tool-shortcut">E</span></button>
                    <button class="tool-btn" data-tool="line" title="Line (L)"><span class="tool-icon">➖</span>Line<span class="tool-shortcut">L</span></button>
                    <button class="tool-btn" data-tool="rect" title="Rectangle (R)"><span class="tool-icon">⬜</span>Rectangle<span class="tool-shortcut">R</span></button>
                    <button class="tool-btn" data-tool="circle" title="Circle (C)"><span class="tool-icon">⭕</span>Circle<span class="tool-shortcut">C</span></button>
                    <button class="tool-btn" data-tool="arrow" title="Arrow (A)"><span class="tool-icon">➡️</span>Arrow<span class="tool-shortcut">A</span></button>
                    <button class="tool-btn" id="btnInsertImage" title="Insert Image (I)"><span class="tool-icon">🖼️</span>Image<span class="tool-shortcut">I</span></button>
                </div></div>

                <div class="tb-divider"></div>

                <div class="tool-section">
                <div class="color-swatches">
                    <div class="color-display"><input type="color" id="colorPicker" value="#ff6b35"></div>
                    <div class="color-swatch" style="background:#000" data-color="#000000"></div>
                    <div class="color-swatch" style="background:#fff;border-color:#ddd" data-color="#ffffff"></div>
                    <div class="color-swatch active" style="background:#ff6b35" data-color="#ff6b35"></div>
                    <div class="color-swatch" style="background:#e74c3c" data-color="#e74c3c"></div>
                    <div class="color-swatch" style="background:#3498db" data-color="#3498db"></div>
                    <div class="color-swatch" style="background:#2ecc71" data-color="#2ecc71"></div>
                    <div class="color-swatch" style="background:#9b59b6" data-color="#9b59b6"></div>
                    <div class="color-swatch" style="background:#f39c12" data-color="#f39c12"></div>
                </div></div>

                <div class="tb-divider"></div>

                <div class="tool-section">
                <div class="tb-size-wrap">📏
                <button class="size-adj-btn" id="btnSizeDec" title="ลดขนาด ([ )">−</button>
                <span id="sizeLabel">4</span>px
                <button class="size-adj-btn" id="btnSizeInc" title="เพิ่มขนาด (] )">+</button>
                <input type="range" class="size-slider" id="sizeSlider" min="1" max="50" value="4">
                </div></div>

                <div class="tb-divider"></div>

                <div class="zoom-section" style="margin-bottom:0">
                    <div class="zoom-controls">
                        <button class="zoom-btn" id="btnZoomOut" title="ซูมออก (-)">➖</button>
                        <div class="zoom-display" id="zoomDisplay" title="คลิกเพื่อรีเซ็ต">100%</div>
                        <button class="zoom-btn" id="btnZoomIn" title="ซูมเข้า (+)">➕</button>
                    </div>
                </div>

                <div class="tb-divider"></div>

                <div class="tool-section">
                <div class="action-grid">
                    <button class="action-btn" id="btnUndo" disabled title="Undo (Ctrl+Z)">↩️ Undo</button>
                    <button class="action-btn" id="btnRedo" disabled title="Redo (Ctrl+Y)">↪️ Redo</button>
                    <button class="action-btn clear" id="btnClear" title="Clear (Delete)">🗑️ Clear</button>
                    <button class="action-btn" id="btnDownload" title="Download พร้อมเครื่องมือ (Ctrl+D)">⬇️ Download</button>
                    <a class="action-btn" id="btnOriginal" title="ดาวน์โหลดต้นฉบับ" href="serve_file.php?id=<?=$fileId?>&dl=1" download="<?=htmlspecialchars($file['file_name'])?>">📂 ต้นฉบับ</a>
                    <button class="action-btn save" id="btnSave" title="Save (Ctrl+S)">💾 Save</button>
                </div></div>

                </div><!-- /toolbox-inner -->
            </div>

            <div class="canvas-area">
                <div class="canvas-header">
                    <span>👁️ <?= $isPDF?'PDF + วาดได้ทุกหน้า':'รูปภาพ + วาดได้' ?></span>
                    <span id="canvasSize" style="font-size:12px;color:var(--text-muted)"></span>
                </div>
                <div class="canvas-wrapper" id="canvasWrapper">
                    <div class="canvas-scaler" id="canvasScaler">
                        <div class="canvas-container" id="canvasContainer">
                            <?php if(!$isPDF):?>
                                <img id="baseImage" src="<?= $dataSrc ?>" alt="<?= htmlspecialchars($file['file_name']) ?>">
                            <?php else:?>
                                <canvas id="pdfCanvas" class="pdf-render"></canvas>
                            <?php endif;?>
                            <canvas id="drawCanvas" class="draw-layer"></canvas>
                        </div>
                    </div>
                </div>
                <!-- ★ Tooltip แสดงชื่อผู้วาด ★ -->
                <div class="draw-tooltip" id="drawTooltip"></div>
            </div>
        </div>
    <!-- ================================
         ★ COMMENTS SECTION ★
    ================================ -->
    <div class="comments-section">
        <div class="comments-card">
            <div class="comments-header">
                <h3>💬 คอมเมนต์ <span class="comment-count-badge" id="commentCount">0</span></h3>
            </div>

            <!-- ★ Form เขียนคอมเมนต์ -->
            <div class="comment-form">
                <div class="comment-form-avatar">
                    <?php if ($userAvatar): ?>
                        <img src="data:image/jpeg;base64,<?= $userAvatar ?>" alt="avatar">
                    <?php else: ?>
                        <?= $initial ?>
                    <?php endif; ?>
                </div>
                <div class="comment-form-body">
                    <textarea class="comment-textarea" id="commentInput" placeholder="เขียนคอมเมนต์..." maxlength="2000"></textarea>
                    <div id="commentAttachPreview"></div>
                    <div class="comment-form-actions">
                        <input type="file" id="commentAttachInput" accept="image/*" style="display:none">
                        <button type="button" class="btn-attach" id="btnAttachComment" title="แนบรูปภาพ">📎 แนบรูป</button>
                        <button class="btn-comment btn-comment-submit" id="btnPostComment" disabled>
                            💬 โพสต์คอมเมนต์
                        </button>
                    </div>
                </div>
            </div>

            <!-- ★ รายการคอมเมนต์ -->
            <div class="comments-loading" id="commentsLoading">⏳ กำลังโหลดคอมเมนต์...</div>
            <div class="comments-list" id="commentsList"></div>
        </div>
    </div>

    </div>
    <!-- ★ IMAGE LIGHTBOX ★ -->
    <div class="img-lightbox" id="imgLightbox">
        <img id="imgLightboxImg" src="" alt="">
    </div>

    <!-- ★★★ SEND EMAIL MODAL ★★★ -->
    <div class="email-modal-overlay" id="emailModalOverlay">
        <div class="email-modal">
            <div class="email-modal-header">
                <h3>📧 ส่งอีเมล Outlook</h3>
                <button class="email-modal-close" id="btnCloseEmail">✕</button>
            </div>
            <div class="email-field">
                <label>ถึง <span style="color:#e74c3c">*</span></label>
                <input type="text" class="email-input" id="emailTo" placeholder="อีเมลผู้รับ (คั่นหลายคนด้วย ,)">
                <!-- ปุ่ม Quick-fill จาก reviewer/uploader -->
                <div class="email-quick-recipients" id="emailQuickRecipients">
                    <?php
                    $allRecipients = [];
                    if ($file['uploader_email']) $allRecipients[] = ['name'=>$file['uploader_name'],'email'=>$file['uploader_email']];
                    foreach ($reviewerList as $rv) {
                        if (!empty($rv['email'])) $allRecipients[] = ['name'=>$rv['display_name'],'email'=>$rv['email']];
                    }
                    foreach ($allRecipients as $r): ?>
                    <button type="button" class="email-quick-btn"
                            onclick="emailAddRecipient('<?= htmlspecialchars($r['email'], ENT_QUOTES) ?>')">
                        + <?= htmlspecialchars($r['name']) ?>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="email-field">
                <label>หัวข้อ <span style="color:#e74c3c">*</span></label>
                <input type="text" class="email-input" id="emailSubject"
                       value="<?= htmlspecialchars('[Lolane Portal] ' . $file['file_name']) ?>">
            </div>
            <div class="email-field">
                <label>ข้อความ</label>
                <textarea class="email-input email-textarea" id="emailBody"
                          placeholder="พิมพ์ข้อความ..."></textarea>
            </div>
            <div class="email-status" id="emailStatus"></div>
            <div class="email-modal-actions">
                <button class="email-btn-cancel" id="btnCancelEmail">ยกเลิก</button>
                <button class="email-btn-send" id="btnSendEmail">
                    <span>📤</span> ส่งอีเมล
                </button>
            </div>
        </div>
    </div>

    <!-- ★★★ ADD REVIEWER MODAL ★★★ -->
    <div class="reviewer-modal-overlay" id="reviewerModalOverlay">
        <div class="reviewer-modal">
            <h3>👥 เพิ่มผู้ตรวจงาน</h3>
            <input type="text" class="reviewer-modal-search" id="reviewerModalSearch" placeholder="🔍 ค้นหาชื่อหรืออีเมล...">
            <div class="reviewer-modal-list" id="reviewerModalList">
                <div class="reviewer-modal-empty">⏳ กำลังโหลด...</div>
            </div>
            <div class="reviewer-modal-actions">
                <button class="reviewer-modal-btn reviewer-modal-btn-cancel" id="reviewerModalCancel">ยกเลิก</button>
                <button class="reviewer-modal-btn reviewer-modal-btn-confirm" id="reviewerModalConfirm">✓ เพิ่มผู้ตรวจที่เลือก</button>
            </div>
        </div>
    </div>

    <div class="footer">© 2026 Lolane Co., Ltd. | Powered by <span class="footer-brand">ALPHABET</span></div>

<script>
(function(){
    var dc=document.getElementById('drawCanvas'), ctx=dc.getContext('2d');
    var isPDF=<?=$isPDF?'true':'false'?>, FILE_ID=<?=$fileId?>;
    var CAN_EDIT=<?= $canEdit ? 'true' : 'false' ?>;
    var FILE_STATUS=<?= json_encode($file['status']) ?>;
    var currentTool='pen',currentColor='#ff6b35',currentSize=4;
    var currentFontFamily='Kanit';
    var isDrawing=false,startX,startY;
    var tboxDrawing=false;  // true while drag-drawing a new text box region
    var tboxDeleteStack={};  // { page: [{id,x,y,w,h,text,color,size,createdBy},...] } for undo delete

    // ===================================================================
    // ★★★  SHAPE / IMAGE OVERLAY SYSTEM  ★★★
    // shapes & images stored as HTML overlays (resizable even after save)
    // ===================================================================
    var shapeOverlays = {};      // { page: [sovObj,...] }
    var sovIdCounter  = 0;
    var sovDeleteStack = {};     // { page: [sovData,...] } for undo

    /* position / size helpers — same coord-space as textbox */
    function positionSovEl(el, cx, cy, cw, ch, rotation) {
        var s  = canvasToScreen(cx, cy);
        var sz = canvasSizeToScreen(cw, ch);
        el.style.left   = s.x  + 'px';
        el.style.top    = s.y  + 'px';
        el.style.width  = sz.w + 'px';
        el.style.height = sz.h + 'px';
        el.style.transformOrigin = '50% 50%';
        el.style.transform = 'rotate(' + (rotation || 0) + 'deg)';
        // ensure inner canvas always fills via CSS (clear any stale inline size)
        var ic = el.querySelector('canvas');
        if (ic) { ic.style.width = ''; ic.style.height = ''; }
    }
    function repositionAllSovs() {
        var overlays = shapeOverlays[currentPage] || [];
        overlays.forEach(function(o) { if (o.el) positionSovEl(o.el, o.x, o.y, o.w, o.h, o.rotation || 0); });
    }

    /* draw the shape / image onto a given canvas context */
    function renderSovToCtx(rCtx, o, w, h) {
        rCtx.clearRect(0, 0, w, h);
        rCtx.save();
        rCtx.strokeStyle = o.color || '#ff6b35';
        rCtx.fillStyle   = o.color || '#ff6b35';
        rCtx.lineWidth   = o.size  || 4;
        rCtx.lineJoin    = 'round';
        rCtx.lineCap     = 'round';
        rCtx.globalAlpha = 1;
        rCtx.globalCompositeOperation = 'source-over';
        if (o.tool === 'rect') {
            rCtx.strokeRect(0, 0, w, h);
        } else if (o.tool === 'circle') {
            var rx = w / 2, ry = h / 2;
            rCtx.beginPath();
            rCtx.ellipse(rx, ry, Math.abs(rx), Math.abs(ry), 0, 0, Math.PI * 2);
            rCtx.stroke();
        } else if (o.tool === 'line') {
            rCtx.beginPath();
            rCtx.moveTo(0, 0); rCtx.lineTo(w, h); rCtx.stroke();
        } else if (o.tool === 'arrow') {
            var a   = Math.atan2(h, w);
            var hh  = Math.max(20, (o.size || 4) * 5);
            rCtx.beginPath();
            rCtx.moveTo(0, 0); rCtx.lineTo(w, h); rCtx.stroke();
            rCtx.beginPath();
            rCtx.moveTo(w, h);
            rCtx.lineTo(w - hh * Math.cos(a - Math.PI / 6), h - hh * Math.sin(a - Math.PI / 6));
            rCtx.moveTo(w, h);
            rCtx.lineTo(w - hh * Math.cos(a + Math.PI / 6), h - hh * Math.sin(a + Math.PI / 6));
            rCtx.stroke();
        } else if (o.tool === 'img' && o.imgEl) {
            rCtx.drawImage(o.imgEl, 0, 0, w, h);
        }
        rCtx.restore();
    }

    /* redraw the overlay canvas (call after resize/move) */
    function redrawSov(o) {
        if (!o.canvas) return;
        o.canvas.width  = Math.max(1, Math.round(o.w));
        o.canvas.height = Math.max(1, Math.round(o.h));
        renderSovToCtx(o.canvasCtx, o, o.canvas.width, o.canvas.height);
    }

    /* create a shape overlay */
    function createShapeOverlay(cx, cy, cw, ch, tool, color, size, strokeId, imgSrc, imgEl, createdUserId, rotation, creatorName, createdAt) {
        var pg = currentPage;
        if (!shapeOverlays[pg]) shapeOverlays[pg] = [];
        createdUserId = (createdUserId !== undefined && createdUserId !== null) ? parseInt(createdUserId, 10) : CURRENT_USER_ID_DRAW;

        var id = strokeId || ('sov_' + (++sovIdCounter) + '_' + Date.now());
        cw = Math.abs(cw) || 40; ch = Math.abs(ch) || 40;

        var el = document.createElement('div');
        el.className = 'sov-overlay sov-active';
        el.dataset.sovId = id;
        if (tool === 'line') el.classList.add('sov-line');
        el.style.transformOrigin = '50% 50%';
        positionSovEl(el, cx, cy, cw, ch, rotation || 0);

        // inner canvas (for shape rendering)
        var ic = document.createElement('canvas');
        ic.width  = Math.max(1, Math.round(cw));
        ic.height = Math.max(1, Math.round(ch));
        var icCtx = ic.getContext('2d');

        // drag area
        var drag = document.createElement('div');
        drag.className = 'sov-drag';

        // delete button
        var db = document.createElement('button');
        db.className = 'sov-del';
        db.title = 'ลบ';
        db.textContent = '×';

        // rotate connector + handle
        var rotConn = document.createElement('div');
        rotConn.className = 'sov-rotate-connector';
        var rotHandle = document.createElement('button');
        rotHandle.className = 'sov-rotate-handle';
        rotHandle.title = 'หมุน';
        rotHandle.textContent = '↻';

        // 8 resize handles
        var HANDLES = ['nw','n','ne','w','e','sw','s','se'];
        HANDLES.forEach(function(dir) {
            var h = document.createElement('div');
            h.className = 'sov-handle ' + dir;
            el.appendChild(h);
        });

        // creator tooltip
        var creatorTip = document.createElement('div');
        creatorTip.className = 'sov-creator-tip';
        var tipName = creatorName || CURRENT_USER_NAME;
        var tipDate = createdAt ? formatSovDate(createdAt) : formatSovDate(new Date().toISOString());
        creatorTip.textContent = '✏️ ' + tipName + '  ' + tipDate;

        el.appendChild(ic);
        el.appendChild(drag);
        el.appendChild(db);
        el.appendChild(rotConn);
        el.appendChild(rotHandle);
        el.appendChild(creatorTip);
        document.getElementById('canvasContainer').appendChild(el);

        var sovObj = { id: id, x: cx, y: cy, w: cw, h: ch,
                       tool: tool, color: color || '#ff6b35', size: size || 4,
                       rotation: rotation || 0,
                       imgSrc: imgSrc || null, imgEl: null,
                       el: el, canvas: ic, canvasCtx: icCtx, page: pg,
                       createdUserId: createdUserId,
                       creatorName: creatorName || CURRENT_USER_NAME,
                       createdAt: createdAt || new Date().toISOString() };
        // แสดง/ซ่อน delete button + handles ตามสิทธิ์
        if (!canManage(createdUserId)) {
            db.style.display = 'none';
            rotHandle.style.display = 'none';
            rotConn.style.display = 'none';
            el.querySelectorAll('.sov-handle').forEach(function(h){ h.style.display='none'; });
        }

        // load image if needed
        if (tool === 'img' && (imgEl || imgSrc)) {
            if (imgEl) {
                sovObj.imgEl = imgEl;
                renderSovToCtx(icCtx, sovObj, ic.width, ic.height);
            } else if (imgSrc) {
                var im = new Image();
                im.onload = function() { sovObj.imgEl = im; renderSovToCtx(icCtx, sovObj, ic.width, ic.height); };
                im.src = imgSrc;
            }
        } else {
            renderSovToCtx(icCtx, sovObj, ic.width, ic.height);
        }

        shapeOverlays[pg].push(sovObj);

        // === drag ===
        drag.style.cursor = canManage(createdUserId) ? 'move' : 'default';
        drag.style.pointerEvents = canManage(createdUserId) ? '' : 'none';
        (function() {
            var dragging = false, ox = 0, oy = 0, sl = 0, st = 0;
            drag.addEventListener('mousedown', function(e) {
                if (!canManage(sovObj.createdUserId)) return;
                if (e.button !== 0) return;
                e.preventDefault(); e.stopPropagation();
                dragging = true;
                ox = e.clientX; oy = e.clientY;
                sl = parseFloat(el.style.left) || 0;
                st = parseFloat(el.style.top)  || 0;
            });
            document.addEventListener('mousemove', function(e) {
                if (!dragging) return;
                var dx = e.clientX - ox, dy = e.clientY - oy;
                var nl = sl + dx, nt = st + dy;
                el.style.left = nl + 'px'; el.style.top = nt + 'px';
                var cc = screenPosToCanvas(nl, nt);
                sovObj.x = cc.x; sovObj.y = cc.y;
            });
            document.addEventListener('mouseup', function() {
                if (!dragging) return;
                dragging = false;
                markDirty(); saveSovStrokesForPage(currentPage, true);
            });
        })();

        // === 8-handle resize ===
        HANDLES.forEach(function(dir) {
            var hEl = el.querySelector('.sov-handle.' + dir);
            if (!hEl) return;
            var resizing = false, ox = 0, oy = 0;
            var origL = 0, origT = 0, origW = 0, origH = 0;
            hEl.addEventListener('mousedown', function(e) { if (!canManage(sovObj.createdUserId)) { e.stopPropagation(); return; } });

            hEl.addEventListener('mousedown', function(e) {
                if (e.button !== 0) return;
                e.preventDefault(); e.stopPropagation();
                resizing = true;
                ox = e.clientX; oy = e.clientY;
                origL = parseFloat(el.style.left)   || 0;
                origT = parseFloat(el.style.top)    || 0;
                origW = parseFloat(el.style.width)  || 100;
                origH = parseFloat(el.style.height) || 100;
            });
            document.addEventListener('mousemove', function(e) {
                if (!resizing) return;
                var dx = e.clientX - ox, dy = e.clientY - oy;
                var nl = origL, nt = origT, nw = origW, nh = origH;
                if (dir.indexOf('e') >= 0) nw = Math.max(20, origW + dx);
                if (dir.indexOf('s') >= 0) nh = Math.max(20, origH + dy);
                if (dir.indexOf('w') >= 0) { nw = Math.max(20, origW - dx); nl = origL + origW - nw; }
                if (dir.indexOf('n') >= 0) { nh = Math.max(20, origH - dy); nt = origT + origH - nh; }
                el.style.left = nl + 'px'; el.style.top = nt + 'px';
                el.style.width = nw + 'px'; el.style.height = nh + 'px';
                var cp0 = screenPosToCanvas(nl, nt);
                var cs  = screenSizeToCanvas(nw, nh);
                sovObj.x = cp0.x; sovObj.y = cp0.y;
                sovObj.w = cs.w;  sovObj.h = cs.h;
                // live-redraw: update canvas buffer only — CSS width:100%;height:100% handles display size
                ic.width  = Math.max(1, Math.round(cs.w));
                ic.height = Math.max(1, Math.round(cs.h));
                renderSovToCtx(icCtx, sovObj, ic.width, ic.height);
            });
            document.addEventListener('mouseup', function() {
                if (!resizing) return;
                resizing = false;
                markDirty(); saveSovStrokesForPage(currentPage, true);
            });
        });

        // === delete ===
        db.addEventListener('click', function(e) {
            e.stopPropagation();
            if (!canManage(sovObj.createdUserId)) return;
            removeShapeOverlay(sovObj);
        });

        // === rotate ===
        (function() {
            var rotating = false, startAngle = 0, startRotation = 0;
            // angle badge element
            var angleBadge = document.createElement('div');
            angleBadge.className = 'sov-angle-badge';
            el.appendChild(angleBadge);

            function getCenter() {
                var r = el.getBoundingClientRect();
                return { x: r.left + r.width / 2, y: r.top + r.height / 2 };
            }
            function normAngle(a) {
                a = a % 360;
                if (a < 0) a += 360;
                return Math.round(a);
            }
            rotHandle.addEventListener('mousedown', function(e) {
                if (!canManage(sovObj.createdUserId)) return;
                if (e.button !== 0) return;
                e.preventDefault(); e.stopPropagation();
                rotating = true;
                var c = getCenter();
                startAngle = Math.atan2(e.clientY - c.y, e.clientX - c.x);
                startRotation = sovObj.rotation || 0;
                angleBadge.textContent = normAngle(startRotation) + '°';
                angleBadge.style.display = 'block';
                document.body.style.cursor = 'grabbing';
                activateSov(sovObj);
            });
            document.addEventListener('mousemove', function(e) {
                if (!rotating) return;
                var c = getCenter();
                var angle = Math.atan2(e.clientY - c.y, e.clientX - c.x);
                var delta = (angle - startAngle) * 180 / Math.PI;
                sovObj.rotation = startRotation + delta;
                el.style.transform = 'rotate(' + sovObj.rotation + 'deg)';
                angleBadge.textContent = normAngle(sovObj.rotation) + '°';
            });
            document.addEventListener('mouseup', function() {
                if (!rotating) return;
                rotating = false;
                angleBadge.style.display = 'none';
                document.body.style.cursor = '';
                markDirty(); saveSovStrokesForPage(currentPage, true);
            });
        })();

        // click on canvas → mark active
        ic.addEventListener('mousedown', function(e) { e.stopPropagation(); activateSov(sovObj); });
        drag.addEventListener('mousedown', function() { activateSov(sovObj); });

        return sovObj;
    }

    function activateSov(o) {
        (shapeOverlays[currentPage] || []).forEach(function(b) { b.el.classList.remove('sov-active'); });
        o.el.classList.add('sov-active');
    }

    function removeShapeOverlay(o) {
        var pg = o.page;
        if (!sovDeleteStack[pg]) sovDeleteStack[pg] = [];
        sovDeleteStack[pg].push({ id: o.id, x: o.x, y: o.y, w: o.w, h: o.h,
            tool: o.tool, color: o.color, size: o.size, imgSrc: o.imgSrc, page: pg,
            rotation: o.rotation || 0 });
        if (o.el && o.el.parentNode) o.el.parentNode.removeChild(o.el);
        var arr = shapeOverlays[pg] || [];
        var idx = arr.indexOf(o);
        if (idx >= 0) arr.splice(idx, 1);
        saveSovStrokesForPage(pg, true);
        markDirty();
        updateButtons();
    }

    function hideSovsForPage(pg) {
        (shapeOverlays[pg] || []).forEach(function(o) { if (o.el) o.el.style.display = 'none'; });
    }
    function showSovsForPage(pg) {
        (shapeOverlays[pg] || []).forEach(function(o) {
            if (o.el) { o.el.style.display = ''; positionSovEl(o.el, o.x, o.y, o.w, o.h, o.rotation || 0); }
        });
    }

    /* save current overlays into sessionStrokes (skipDirty avoids re-marking after save) */
    function saveSovStrokesForPage(pg, skipDirty) {
        if (!sessionStrokes[pg]) sessionStrokes[pg] = [];
        sessionStrokes[pg] = sessionStrokes[pg].filter(function(s) {
            return s.sovType === undefined;  // keep non-sov
        });
        (shapeOverlays[pg] || []).forEach(function(o) {
            sessionStrokes[pg].push({
                type: 'shape', tool: o.tool, sovType: o.tool,
                id: o.id, x: o.x, y: o.y, w: o.w, h: o.h,
                color: o.color, size: o.size,
                rotation: o.rotation || 0,
                createdAt: o.createdAt || null,
                imgSrc: o.imgSrc || null, page: pg
            });
        });
        if (!skipDirty) { dirtyPages[pg] = true; updateButtons(); }
    }

    /* load shape overlays from savedStrokesData */
    function loadSovFromStrokes(pg) {
        var strokes = savedStrokesData[pg] || [];
        strokes.forEach(function(item) {
            var s = item.stroke;
            if (!s || !s.sovType) return;
            var arr = shapeOverlays[pg] || [];
            if (arr.find(function(b) { return b.id === s.id; })) return;
            var sov = createShapeOverlay(s.x, s.y, s.w || 80, s.h || 80,
                s.tool, s.color, s.size, s.id, s.imgSrc || null, null, item.user_id, s.rotation || 0, item.user_name || '', item.created_at || s.createdAt || null);
            sov.el.classList.remove('sov-active');
            sov.el.classList.add('sov-saved');
            if (pg !== currentPage) sov.el.style.display = 'none';
        });
    }

    /* flatten all overlays for a given page onto targetCtx (for download / save-to-bitmap) */
    function flattenSovsToCanvas(targetCtx, pg, scaleX, scaleY) {
        scaleX = scaleX || 1; scaleY = scaleY || scaleX;
        var items = (shapeOverlays[pg] || []).slice();
        // also include shapes only in savedStrokesData (other pages)
        (savedStrokesData[pg] || []).forEach(function(item) {
            var s = item.stroke;
            if (!s || !s.sovType) return;
            if (!items.find(function(b) { return b.id === s.id; })) items.push(s);
        });
        items.forEach(function(o) {
            var bx = (o.x || 0) * scaleX, by = (o.y || 0) * scaleY;
            var bw = (o.w || 80)  * scaleX, bh = (o.h || 80) * scaleY;
            var rot = (o.rotation || 0) * Math.PI / 180;
            // temporarily create an off-screen canvas to render the shape
            var tmp = document.createElement('canvas');
            tmp.width  = Math.max(1, Math.round(bw));
            tmp.height = Math.max(1, Math.round(bh));
            var tc = tmp.getContext('2d');
            // build a fake sovObj for rendering
            var fake = { tool: o.tool || o.sovType, color: o.color, size: (o.size || 4) * scaleX,
                         imgEl: (o.imgEl || null) };
            if (o.tool === 'img' || o.sovType === 'img') {
                // image — try live el first
                var imgEl = o.imgEl || null;
                if (!imgEl && o.imgSrc) {
                    // sync draw won't work here; rely on live overlays having imgEl set
                    imgEl = null;
                }
                if (imgEl) { tc.drawImage(imgEl, 0, 0, tmp.width, tmp.height); }
            } else {
                renderSovToCtx(tc, fake, tmp.width, tmp.height);
            }
            // draw with rotation around shape center
            var cx = bx + bw / 2, cy = by + bh / 2;
            targetCtx.save();
            targetCtx.translate(cx, cy);
            targetCtx.rotate(rot);
            targetCtx.drawImage(tmp, -bw / 2, -bh / 2, bw, bh);
            targetCtx.restore();
        });
    }
    // ★★★ PER-STROKE ATTRIBUTION SYSTEM ★★★
    var currentStrokePoints=[];  // จุด path ระหว่างวาด
    var lastDrawX=0,lastDrawY=0; // ตำแหน่งเมาส์ล่าสุดระหว่างวาด
    var sessionStrokes={};       // { page: [stroke,...] } เส้นใหม่ในเซสชันนี้
    var sessionStrokeHist={};    // { page: [[],[s1],[s1,s2],...] } mirror pageDrawings history
    var savedStrokesData={};     // { page: [{user_id,user_name,created_at,stroke},...] } จาก DB
    var userLayers={};           // { userId: {canvas,ctx,name,time} } per page
    var selfLayer=null;          // offscreen canvas ของเส้นใหม่ผู้ใช้ปัจจุบัน
    var CURRENT_USER_ID_DRAW=<?= $userId ?>;
    var CURRENT_USER_NAME=<?= json_encode($displayName) ?>;
    var FILE_OWNER_ID=<?= (int)$file['user_id'] ?>; // ผู้อัปโหลดไฟล์ → ทำได้ทุกอย่าง

    function formatSovDate(dateStr) {
        if (!dateStr) return '';
        var d = new Date(dateStr);
        if (isNaN(d.getTime())) return '';
        var day  = ('0' + d.getDate()).slice(-2);
        var mon  = ('0' + (d.getMonth() + 1)).slice(-2);
        var yr   = d.getFullYear() + 543;
        var h    = ('0' + d.getHours()).slice(-2);
        var min  = ('0' + d.getMinutes()).slice(-2);
        return day + '/' + mon + '/' + yr + ' ' + h + ':' + min;
    }

    // canManage: รับ true ถ้าเป็นเจ้าของสิ่งนั้นเอง หรือเป็นเจ้าของไฟล์
    function canManage(ownerUserId) {
        return CURRENT_USER_ID_DRAW === FILE_OWNER_ID ||
               CURRENT_USER_ID_DRAW === parseInt(ownerUserId, 10);
    }

    // ★ Dynamic cursor preview – shows circle matching brush size
    function getEffectiveSize(){
        if(currentTool==='pencil') return Math.max(1,currentSize*.5);
        if(currentTool==='marker') return currentSize*3;
        if(currentTool==='eraser') return currentSize*3;
        return currentSize; // pen, line, rect, circle, arrow, text
    }
    function updateCursor(){
        if(currentTool==='hand'){dc.style.cursor=moveFloating?'grabbing':'grab';return;}
        if(currentTool==='text'){dc.style.cursor='text';return;}
        var sz=getEffectiveSize()*(zoomLevel/100);
        if(sz<3){dc.style.cursor='crosshair';return;}
        // Browser max cursor is 128×128 — hard clamp
        var MAX=126, pad=2;
        var full, r, half, scale=1;
        if(sz+pad*2>MAX){
            full=MAX; r=(MAX-pad*2)/2; scale=r/(sz/2);
        } else {
            full=Math.floor(sz+pad*2); r=Math.floor(sz/2);
        }
        half=Math.floor(full/2);
        var col=currentTool==='eraser'?'rgba(180,180,180,.85)':currentColor;
        var sw=scale<1?1:1.5;
        var svg='<svg xmlns="http://www.w3.org/2000/svg" width="'+full+'" height="'+full+'">'
            +'<circle cx="'+half+'" cy="'+half+'" r="'+r+'" fill="none" stroke="'+col+'" stroke-width="'+sw+'" opacity="0.8"/>'
            +'<circle cx="'+half+'" cy="'+half+'" r="1" fill="'+col+'" opacity="0.9"/>'
            +(scale<1?'<text x="'+half+'" y="'+(half+4)+'" text-anchor="middle" font-size="10" font-family="sans-serif" fill="'+col+'" opacity="0.7">'+Math.round(sz)+'px</text>':'')
            +'</svg>';
        dc.style.cursor='url("data:image/svg+xml,'+encodeURIComponent(svg)+'") '+half+' '+half+', crosshair';
    }
    var pageDrawings={},pageRedoStack={};
    var currentPage=1,totalPages=1,maxHist=50;
    var pdfDoc=null,pdfScale=1.5;
    var savedDrawings={};
    var dirtyPages={};
    var baseDrawWidth=0, baseDrawHeight=0;  // ★ base resolution for drawings

    // ★★★ Activity Log Helper ★★★
    function logAction(actionType, detail, fileId) {
        fetch('get_activity_logs.php', { method: 'GET' }); // just warm up
        var fd = new FormData();
        fd.append('action_type', actionType);
        fd.append('action_detail', detail);
        fd.append('file_id', fileId || FILE_ID);
        fetch('log_action.php', { method: 'POST', body: fd }).catch(function(){});
    }

    // ★★★ ZOOM STATE ★★★
    var zoomLevel=100, MIN_ZOOM=25, MAX_ZOOM=300, ZOOM_STEP=15;
    var scaler=document.getElementById('canvasScaler');
    var wrapper=document.getElementById('canvasWrapper');

    function getH(){if(!pageDrawings[currentPage])pageDrawings[currentPage]=[];return pageDrawings[currentPage];}
    function getR(){if(!pageRedoStack[currentPage])pageRedoStack[currentPage]=[];return pageRedoStack[currentPage];}

    function showProgress(t){document.getElementById('progressTitle').textContent=t||'⏳';document.getElementById('progressBar').style.width='0%';document.getElementById('progressText').textContent='กำลังเตรียม...';document.getElementById('progressOverlay').classList.add('active');}
    function updateProgress(c,t,x){var p=Math.round(c/t*100);document.getElementById('progressBar').style.width=p+'%';document.getElementById('progressText').textContent=x||('หน้า '+c+'/'+t+' ('+p+'%)');}
    function hideProgress(){document.getElementById('progressOverlay').classList.remove('active');}

    // ★★★ ZOOM FUNCTIONS (ใช้ transform: scale) ★★★
    var zoomDisplay=document.getElementById('zoomDisplay');
    var zoomSlider=document.getElementById('zoomSlider');

    function setZoom(level, fromSlider){
        zoomLevel=Math.max(MIN_ZOOM,Math.min(MAX_ZOOM,Math.round(level)));
        if(zoomDisplay) zoomDisplay.textContent=zoomLevel+'%';
        if(!fromSlider && zoomSlider) zoomSlider.value=zoomLevel;
        document.getElementById('btnZoomOut').disabled=(zoomLevel<=MIN_ZOOM);
        document.getElementById('btnZoomIn').disabled=(zoomLevel>=MAX_ZOOM);
        document.querySelectorAll('.zoom-preset').forEach(function(b){
            b.classList.toggle('active',parseInt(b.dataset.zoom)===zoomLevel);
        });
        applyZoom();
        updateCursor();
    }

    function applyZoom(){
        var scale=zoomLevel/100;
        if(isPDF && pdfDoc){
            var baseW=baseDrawWidth||1, baseH=baseDrawHeight||1;
            var targetW=Math.round(baseW*scale), targetH=Math.round(baseH*scale);
            // ★ CSS ทันที (ยังอาจ blur) + ★★ ไม่ขยาย wrapper เพื่อให้ overflow:auto ทำงาน (hand pan ได้)
            pdfCanvas.style.width=targetW+'px';
            pdfCanvas.style.height=targetH+'px';
            dc.style.width=targetW+'px';
            dc.style.height=targetH+'px';
            scaler.style.transform='none';
            scaler.style.width=targetW+'px';
            scaler.style.height=targetH+'px';
            // ★★★ Debounce re-render at actual zoom scale → คมชัด ไม่ blur ★★★
            clearTimeout(_pdfZoomTimer);
            _pdfZoomTimer=setTimeout(function(){
                if(_pdfRenderTask){try{_pdfRenderTask.cancel();}catch(ex){} _pdfRenderTask=null;}
                var snapScale=scale;
                pdfDoc.getPage(currentPage).then(function(page){
                    var vp=page.getViewport({scale:pdfScale*snapScale});
                    var off=document.createElement('canvas');
                    off.width=vp.width; off.height=vp.height;
                    _pdfRenderTask=page.render({canvasContext:off.getContext('2d'),viewport:vp});
                    _pdfRenderTask.promise.then(function(){
                        _pdfRenderTask=null;
                        if(Math.abs(zoomLevel/100-snapScale)>0.001) return; // zoom เปลี่ยนอีกแล้ว
                        pdfCanvas.width=vp.width; pdfCanvas.height=vp.height;
                        pdfCtx.drawImage(off,0,0);
                        pdfCanvas.style.width=targetW+'px';
                        pdfCanvas.style.height=targetH+'px';
                    }).catch(function(err){if(err&&err.name==='RenderingCancelledException')return;});
                });
            },300);
        } else {
            // ★ IMAGE MODE: use CSS transform as before
            scaler.style.transform='scale('+scale+')';
            var container=document.getElementById('canvasContainer');
            var realW=container.offsetWidth*scale;
            var realH=container.offsetHeight*scale;
            scaler.style.width=container.offsetWidth+'px';
            scaler.style.height=container.offsetHeight+'px';
            scaler.style.minWidth=realW+'px';
            wrapper.scrollLeft=Math.max(0,(realW-wrapper.clientWidth)/2);
        }
    }

    document.getElementById('btnZoomIn').addEventListener('click',function(){setZoom(zoomLevel+ZOOM_STEP);});
    document.getElementById('btnZoomOut').addEventListener('click',function(){setZoom(zoomLevel-ZOOM_STEP);});
    if(zoomDisplay) zoomDisplay.addEventListener('click',function(){setZoom(100);});
    if(zoomSlider) zoomSlider.addEventListener('input',function(){setZoom(parseInt(this.value),true);});
    document.querySelectorAll('.zoom-preset').forEach(function(b){
        b.addEventListener('click',function(){setZoom(parseInt(b.dataset.zoom));});
    });

    // Ctrl + scroll wheel zoom (ใช้ Ctrl+ลูกกลิ้ง เพื่อซูม)
    wrapper.addEventListener('wheel',function(e){
        if(e.ctrlKey||e.metaKey){
            e.preventDefault();
            var delta=e.deltaY>0?-ZOOM_STEP:ZOOM_STEP;
            setZoom(zoomLevel+delta);
        }
    },{passive:false});

    // ★ ป้องกัน Ctrl+Scroll zoom ของ browser ในทุกพื้นที่บนหน้า
    //   (wrapper จัดการ zoom ภาพ/PDF เองแล้ว ส่วนนี้บล็อกพื้นที่อื่น)
    window.addEventListener('wheel',function(e){
        if(e.ctrlKey||e.metaKey){
            e.preventDefault();
        }
    },{passive:false});

    // ★ โหลด drawings ที่บันทึกไว้
    var savedTimestamps={};  // เก็บเวลาบันทึกจาก DB { page: Date }
    var savedDrawerNames={};  // เก็บชื่อผู้วาดจาก DB { page: displayName }
        function loadSavedDrawings(callback){
        fetch('get_drawings.php?id='+FILE_ID)
        .then(function(r){return r.json();})
        .then(function(d){
            if(d.success&&d.drawings){
                savedDrawings={};
                var obj=d.drawings;
                for(var k in obj){if(obj.hasOwnProperty(k))savedDrawings[parseInt(k)]=obj[k];}
            }
            if(d.timestamps){
                var ts=d.timestamps;
                for(var k in ts){if(ts.hasOwnProperty(k))savedTimestamps[parseInt(k)]=new Date(ts[k]);}
            }
            if(d.drawer_names){
                var dn=d.drawer_names;
                for(var k in dn){if(dn.hasOwnProperty(k))savedDrawerNames[parseInt(k)]=dn[k];}
            }
            if(d.strokes){
                var st=d.strokes;
                for(var k in st){if(st.hasOwnProperty(k))savedStrokesData[parseInt(k)]=st[k];}
            }
            // ★ แสดงข้อมูล saved drawings
            showSavedAnnotationInfo();
            if(callback)callback();
        })
        .catch(function(){if(callback)callback();});
    }

    function applySavedDrawing(pageNum,callback){
        var data=savedDrawings[pageNum];
        if(data&&data.length>100){
            var img2=new Image();
            img2.onload=function(){
                ctx.clearRect(0,0,dc.width,dc.height);
                ctx.drawImage(img2,0,0);
                var h=getH();h.length=0;h.push(dc.toDataURL());
                pageRedoStack[currentPage]=[];updateButtons();
                buildUserLayersForPage(pageNum);
                initSelfLayer();
                if(callback)callback();
            };
            img2.onerror=function(){ctx.clearRect(0,0,dc.width,dc.height);initEmptyState();buildUserLayersForPage(pageNum);initSelfLayer();if(callback)callback();};
            var src=data;if(src.indexOf('data:')!==0)src='data:image/png;base64,'+src;
            img2.src=src;
        } else {
            ctx.clearRect(0,0,dc.width,dc.height);initEmptyState();buildUserLayersForPage(pageNum);initSelfLayer();
            if(callback)callback();
        }
    }

    function initEmptyState(){var h=getH();h.length=0;h.push(dc.toDataURL());pageRedoStack[currentPage]=[];updateButtons();}

    // ★★★ PER-USER OFFSCREEN CANVAS HELPERS ★★★

    // replay หนึ่ง stroke object ลงบน context ที่กำหนด
    function replayStroke(rCtx, stroke) {
        if (!stroke || !stroke.type) return;
        rCtx.save();
        rCtx.strokeStyle = stroke.color || '#000';
        rCtx.fillStyle   = stroke.color || '#000';
        rCtx.lineJoin    = 'round';
        rCtx.lineCap     = 'round';
        var sz = stroke.size || 4;
        if (stroke.tool === 'pen')    { rCtx.lineWidth=sz;      rCtx.globalAlpha=1;   rCtx.globalCompositeOperation='source-over'; }
        else if (stroke.tool === 'pencil') { rCtx.lineWidth=Math.max(1,sz*.5); rCtx.globalAlpha=.6; rCtx.globalCompositeOperation='source-over'; }
        else if (stroke.tool === 'marker') { rCtx.lineWidth=sz*3; rCtx.globalAlpha=.3; rCtx.globalCompositeOperation='source-over'; }
        else { rCtx.lineWidth=sz; rCtx.globalAlpha=1; rCtx.globalCompositeOperation='source-over'; }

        if (stroke.type === 'path' && stroke.points && stroke.points.length > 0) {
            rCtx.beginPath();
            rCtx.moveTo(stroke.points[0][0], stroke.points[0][1]);
            for (var i=1; i<stroke.points.length; i++) rCtx.lineTo(stroke.points[i][0], stroke.points[i][1]);
            rCtx.stroke();
        } else if (stroke.type === 'shape') {
            // ★ shape strokes that have sovType are overlay objects — skip bitmap rendering
            if (stroke.sovType) { rCtx.restore(); return; }
            rCtx.lineWidth=sz; rCtx.globalAlpha=1;
            rCtx.beginPath();
            if (stroke.tool === 'line') {
                rCtx.moveTo(stroke.x1,stroke.y1); rCtx.lineTo(stroke.x2,stroke.y2); rCtx.stroke();
            } else if (stroke.tool === 'rect') {
                rCtx.strokeRect(stroke.x1,stroke.y1,stroke.x2-stroke.x1,stroke.y2-stroke.y1);
            } else if (stroke.tool === 'circle') {
                var rx=Math.abs(stroke.x2-stroke.x1)/2, ry=Math.abs(stroke.y2-stroke.y1)/2;
                rCtx.ellipse(Math.min(stroke.x1,stroke.x2)+rx, Math.min(stroke.y1,stroke.y2)+ry, rx,ry, 0,0,Math.PI*2);
                rCtx.stroke();
            } else if (stroke.tool === 'arrow') {
                var a=Math.atan2(stroke.y2-stroke.y1,stroke.x2-stroke.x1), hh=Math.max(20,sz*5);
                rCtx.moveTo(stroke.x1,stroke.y1); rCtx.lineTo(stroke.x2,stroke.y2); rCtx.stroke();
                rCtx.beginPath();
                rCtx.moveTo(stroke.x2,stroke.y2); rCtx.lineTo(stroke.x2-hh*Math.cos(a-Math.PI/6), stroke.y2-hh*Math.sin(a-Math.PI/6));
                rCtx.moveTo(stroke.x2,stroke.y2); rCtx.lineTo(stroke.x2-hh*Math.cos(a+Math.PI/6), stroke.y2-hh*Math.sin(a+Math.PI/6));
                rCtx.stroke();
            }
        } else if (stroke.type === 'text' && stroke.text) {
            rCtx.globalAlpha=1; rCtx.globalCompositeOperation='source-over';
            rCtx.font='bold '+Math.max(16,sz*4)+'px Kanit,sans-serif';
            rCtx.fillText(stroke.text, stroke.x, stroke.y);
        } else if (stroke.type === 'textbox' || stroke.tool === 'textbox') {
            // textbox strokes are rendered as HTML overlays, not on canvas bitmap
            // skip here — handled by loadTextBoxesFromStrokes()
        }
        rCtx.restore();
    }

    // สร้าง offscreen canvas ต่อ user สำหรับหน้าที่กำหนด
    function buildUserLayersForPage(pageNum) {
        userLayers = {};
        var strokes = savedStrokesData[pageNum] || [];
        if (!strokes.length) return;
        var groups = {};
        strokes.forEach(function(item) {
            var uid = '' + item.user_id;
            if (!groups[uid]) groups[uid] = { name: item.user_name, time: item.created_at, strokes: [] };
            groups[uid].strokes.push(item.stroke);
            if (!groups[uid].time || item.created_at > groups[uid].time) groups[uid].time = item.created_at;
        });
        for (var uid in groups) {
            var c = document.createElement('canvas');
            c.width  = dc.width;
            c.height = dc.height;
            var cx = c.getContext('2d');
            groups[uid].strokes.forEach(function(s) { replayStroke(cx, s); });
            userLayers[uid] = { canvas: c, ctx: cx, name: groups[uid].name, time: groups[uid].time };
        }
    }

    // สร้าง/รีเซ็ต selfLayer สำหรับเส้นใหม่ของผู้ใช้ปัจจุบันหน้านี้
    function initSelfLayer() {
        var c = document.createElement('canvas');
        c.width  = dc.width;
        c.height = dc.height;
        selfLayer = { canvas: c, ctx: c.getContext('2d'), w: dc.width, h: dc.height };
        // replay session strokes ของหน้านี้ลงบน selfLayer
        var stks = sessionStrokes[currentPage] || [];
        stks.forEach(function(s) { replayStroke(selfLayer.ctx, s); });
    }

    // เพิ่ม stroke เดียวลงบน selfLayer (incremental)
    function updateSelfLayer(stroke) {
        if (!selfLayer || selfLayer.w !== dc.width || selfLayer.h !== dc.height) { initSelfLayer(); return; }
        replayStroke(selfLayer.ctx, stroke);
    }

    // ตรวจว่า canvas region มี pixel หรือไม่ (อ่านครั้งเดียว)
    function hasPixelInRegion(rCtx, rCanvas, ipx, ipy, r) {
        var x0=Math.max(0,ipx-r), y0=Math.max(0,ipy-r);
        var w=Math.min(rCanvas.width,ipx+r+1)-x0, h=Math.min(rCanvas.height,ipy+r+1)-y0;
        if (w<=0||h<=0) return false;
        var data = rCtx.getImageData(x0,y0,w,h).data;
        for (var i=3; i<data.length; i+=4) { if (data[i]>10) return true; }
        return false;
    }

    // หาผู้วาดที่ตำแหน่ง pixel (ชนะ = เส้นที่วาดทีหลังสุด)
    function findDrawerAtPixel(px, py) {
        var r = 3;
        // ตรวจ selfLayer ก่อน (เส้นใหม่ที่ยังไม่บันทึก = อยู่บนสุด)
        if (selfLayer && hasPixelInRegion(selfLayer.ctx, selfLayer.canvas, px, py, r)) {
            return { name: CURRENT_USER_NAME, time: null };
        }
        // ตรวจ userLayers เรียงจากล่าสุดก่อน
        var uids = Object.keys(userLayers).sort(function(a,b) {
            var ta=userLayers[a].time||'', tb=userLayers[b].time||'';
            return tb>ta?1:tb<ta?-1:0;
        });
        for (var i=0; i<uids.length; i++) {
            var ul = userLayers[uids[i]];
            if (hasPixelInRegion(ul.ctx, ul.canvas, px, py, r)) {
                return { name: ul.name, time: ul.time };
            }
        }
        return null;
    }

    // หลังบันทึก strokes สำเร็จ: ย้าย session strokes ไปรวมใน userLayers
    function mergeSessionStrokesToLayer(pg) {
        var stks = sessionStrokes[pg];
        if (!stks || !stks.length) return;
        var uid = '' + CURRENT_USER_ID_DRAW;
        if (!userLayers[uid]) {
            var c = document.createElement('canvas');
            c.width  = dc.width;
            c.height = dc.height;
            userLayers[uid] = { canvas: c, ctx: c.getContext('2d'), name: CURRENT_USER_NAME, time: new Date().toISOString() };
        }
        stks.forEach(function(s) { replayStroke(userLayers[uid].ctx, s); });
        userLayers[uid].time = new Date().toISOString();
        // เพิ่ม strokes เข้า savedStrokesData ด้วย
        if (!savedStrokesData[pg]) savedStrokesData[pg] = [];
        var now = new Date().toISOString();
        stks.forEach(function(s) {
            savedStrokesData[pg].push({ user_id: CURRENT_USER_ID_DRAW, user_name: CURRENT_USER_NAME, created_at: now, stroke: s });
        });
        // clear session state สำหรับหน้านี้
        delete sessionStrokes[pg];
        delete sessionStrokeHist[pg];
        if (pg === currentPage) { initSelfLayer(); }
    }

    <?php if($isPDF):?>
    // ===== PDF MODE =====
    var pdfCanvas=document.getElementById('pdfCanvas'),pdfCtx=pdfCanvas.getContext('2d');

    loadSavedDrawings(function(){
        if(typeof pdfjsLib === 'undefined'){
            document.getElementById('canvasContainer').insertAdjacentHTML('beforeend',
                '<div style="padding:24px;color:#e74c3c;font-family:Kanit,sans-serif;">❌ โหลด pdf.js ไม่ได้ — กรุณาตรวจสอบการเชื่อมต่ออินเทอร์เน็ต แล้ว Refresh</div>');
            return;
        }
        pdfjsLib.getDocument({url:'serve_file.php?id=<?=$fileId?>',rangeChunkSize:65536,withCredentials:true}).promise.then(function(pdf){
            pdfDoc=pdf;totalPages=pdf.numPages;
            updatePageDisplay();renderPDFPage(1);
        }).catch(function(err){
            console.error('PDF load error:', err);
            var msg = (err && err.message) ? err.message : JSON.stringify(err);
            document.getElementById('canvasContainer').insertAdjacentHTML('beforeend',
                '<div id="pdfErrorMsg" style="padding:24px;color:#e74c3c;font-family:Kanit,sans-serif;">❌ โหลด PDF ไม่ได้: ' + msg + '<br><small style="color:#888">ลอง: <a href="serve_file.php?id=<?=$fileId?>" target="_blank">คลิกที่นี่</a> เพื่อทดสอบไฟล์</small></div>');
        });
    });

    var _pdfRenderTask=null, _pdfZoomTimer=null;
    function renderPDFPage(num){
        if(_pdfRenderTask){ try{_pdfRenderTask.cancel();}catch(e){} _pdfRenderTask=null; }
        currentPage=num;
        pdfDoc.getPage(num).then(function(page){
            var baseVp = page.getViewport({scale:pdfScale});
            var displayScale=zoomLevel/100;
            var displayW=Math.round(baseVp.width*displayScale);
            var displayH=Math.round(baseVp.height*displayScale);

            // Setup draw canvas + layout ทันที ไม่ต้องรอ render
            baseDrawWidth=baseVp.width; baseDrawHeight=baseVp.height;
            dc.width=baseDrawWidth; dc.height=baseDrawHeight;
            dc.style.width=displayW+'px'; dc.style.height=displayH+'px';
            document.getElementById('canvasSize').textContent=Math.round(baseVp.width)+'×'+Math.round(baseVp.height)+' px';
            scaler.style.transform='none';
            scaler.style.width=displayW+'px'; scaler.style.height=displayH+'px';
            pdfCanvas.style.width=displayW+'px'; pdfCanvas.style.height=displayH+'px';

            // ★ ระหว่างรอ render: fade หน้าเดิม → ผู้ใช้ยังเห็นเนื้อหาเดิม ไม่เห็น blank
            pdfCanvas.style.opacity='0.35';

            // ★★ Render full quality ไปที่ offscreen canvas
            //    เมื่อพร้อมค่อย swap → ปรากฏคมชัดทันที ไม่มี blurry เลย
            var offCanvas=document.createElement('canvas');
            offCanvas.width=baseVp.width; offCanvas.height=baseVp.height;
            _pdfRenderTask=page.render({canvasContext:offCanvas.getContext('2d'),viewport:baseVp});
            _pdfRenderTask.promise.then(function(){
                _pdfRenderTask=null;
                if(currentPage!==num){ pdfCanvas.style.opacity=''; return; }
                pdfCanvas.width=baseVp.width; pdfCanvas.height=baseVp.height;
                pdfCtx.drawImage(offCanvas,0,0);
                pdfCanvas.style.width=displayW+'px'; pdfCanvas.style.height=displayH+'px';
                pdfCanvas.style.opacity='';
                var existingHist=pageDrawings[num];
                if(existingHist&&existingHist.length>0){
                    restoreState(existingHist[existingHist.length-1]); updateButtons();
                    buildUserLayersForPage(num); initSelfLayer();
                } else { applySavedDrawing(num); }
                updatePageDisplay();
            }).catch(function(err){
                pdfCanvas.style.opacity='';
                if(err&&err.name==='RenderingCancelledException') return;
                console.error('PDF render error:', err);
            });
        }).catch(function(err){
            pdfCanvas.style.opacity='';
            console.error('PDF getPage error:', err);
        });
    }

    function updatePageDisplay(){
        document.getElementById('pageDisplay').textContent=currentPage+' / '+totalPages;
        document.getElementById('btnPrevPage').disabled=(currentPage<=1);
        document.getElementById('btnNextPage').disabled=(currentPage>=totalPages);
    }
    document.getElementById('btnPrevPage').addEventListener('click',function(){if(currentPage>1)renderPDFPage(currentPage-1);});
    document.getElementById('btnNextPage').addEventListener('click',function(){if(currentPage<totalPages)renderPDFPage(currentPage+1);});

    // ★★★ Download: pdf-lib โหลด PDF ต้นฉบับ + overlay annotation → ได้ไฟล์เดิมทุก byte + annotation ★★★

    function downloadAllPages(){
    if(!pdfDoc) return;
    showProgress('📄 กำลังรวม annotation ลง PDF ต้นฉบับ...');
    saveState();

    // render annotation ที่ 3x เพื่อความคมชัด
    var renderScale = 3.0;
    var annScale    = renderScale / pdfScale;  // 3.0/1.5 = 2.0

    // ดึง raw bytes จาก pdf.js (ไม่ต้องโหลดซ้ำ) → load ด้วย pdf-lib
    pdfDoc.getData().then(function(origBytes){
        return PDFLib.PDFDocument.load(origBytes, {ignoreEncryption:true});
    }).then(function(pdfLibDoc){
        var pages = pdfLibDoc.getPages();
        var queue = Promise.resolve();

        for(var _pn=1; _pn<=totalPages; _pn++){
            (function(pn){
                queue = queue.then(function(){
                    updateProgress(pn, totalPages, 'หน้า '+pn+'...');
                    var dd      = getDrawingForPage(pn);
                    var hasTBox = (textBoxes[pn] && textBoxes[pn].length > 0) ||
                                  (savedStrokesData[pn]||[]).some(function(i){ return i.stroke && i.stroke.type==='textbox'; });
                    var hasSov  = (shapeOverlays[pn] && shapeOverlays[pn].length > 0) ||
                                  (savedStrokesData[pn]||[]).some(function(i){ return i.stroke && i.stroke.sovType; });
                    if(!dd && !hasTBox && !hasSov) return Promise.resolve();

                    var page  = pages[pn-1];
                    var pw    = page.getWidth();   // PDF points ต้นฉบับ
                    var ph    = page.getHeight();
                    // pdf.js ใช้ 1pt = 1px ที่ scale=1 (ไม่ต้องคูณ 96/72)
                    var canvW = Math.round(pw * renderScale);
                    var canvH = Math.round(ph * renderScale);

                    var annCanvas    = document.createElement('canvas');
                    annCanvas.width  = canvW;
                    annCanvas.height = canvH;
                    var annCtx = annCanvas.getContext('2d');

                    function buildAndEmbed(){
                        flattenTextBoxesToCanvas(annCtx, pn, annScale, annScale);
                        flattenSovsToCanvas(annCtx, pn, annScale, annScale);
                        // pdf-lib วาง PNG โดยให้ row 0 = บนสุดของหน้า (จัดการ Y ให้อัตโนมัติ)
                        // ไม่ต้อง flip Y เพิ่มเติม
                        var pngB64   = annCanvas.toDataURL('image/png').split(',')[1];
                        var pngBytes = Uint8Array.from(atob(pngB64), function(c){ return c.charCodeAt(0); });
                        return pdfLibDoc.embedPng(pngBytes).then(function(embImg){
                            page.drawImage(embImg, {x:0, y:0, width:pw, height:ph});
                        });
                    }

                    if(dd){
                        return new Promise(function(resolve){
                            var im = new Image();
                            im.onload = function(){ resolve(im); };
                            im.src = dd;
                        }).then(function(im){
                            annCtx.drawImage(im, 0, 0, im.width*annScale, im.height*annScale);
                            return buildAndEmbed();
                        });
                    } else {
                        return buildAndEmbed();
                    }
                });
            })(_pn);
        }

        return queue.then(function(){ return pdfLibDoc.save(); });

    }).then(function(pdfBytes){
        var blob = new Blob([pdfBytes], {type:'application/pdf'});
        var url  = URL.createObjectURL(blob);
        var lk   = document.createElement('a');
        lk.href  = url;
        lk.download = 'annotated_<?=pathinfo($file['file_name'],PATHINFO_FILENAME)?>.pdf';
        lk.click();
        setTimeout(function(){ URL.revokeObjectURL(url); }, 30000);
        hideProgress();
        logAction('download','ดาวน์โหลดไฟล์ PDF ('+totalPages+' หน้า)');
    }).catch(function(err){
        console.error('PDF export error:', err);
        hideProgress();
        alert('❌ เกิดข้อผิดพลาด: ' + err.message);
    });
    }

    function getDrawingForPage(pn){
        var hist=pageDrawings[pn];
        if(hist&&hist.length>0)return hist[hist.length-1];
        if(savedDrawings[pn]){var d=savedDrawings[pn];return(d.indexOf('data:')===0)?d:'data:image/png;base64,'+d;}
        return null;
    }

        function saveAllPages(){
        if(!pdfDoc)return;

        // ★ ถ้ากำลัง insert image อยู่ → commit ก่อน (ลบ handles) แล้วค่อย save
        if(imgPlacing && insertImgObj){
            commitInsertImage(function(){ saveAllPages(); });
            return;
        }

        // ★ เก็บ state ปัจจุบันก่อน
        saveState();

        // รวมหน้าที่ต้องบันทึก
        var toSave=[];
        for(var p=1;p<=totalPages;p++){
            if(dirtyPages[p]){
                var dd=getDrawingForPage(p);
                if(dd){
                    var b=dd;
                    if(b.indexOf(',')>0) b=b.split(',')[1];
                    toSave.push({page:p, data:b});
                }
            }
        }

        if(toSave.length===0){
            alert('ยังไม่มีการวาดเพิ่มเติม');
            return;
        }

        showProgress('💾 กำลังบันทึก...');

        var idx=0;
        var successCount=0;
        var failedPages=[];

        function next(){
            // ★ เสร็จทุกหน้าแล้ว → แสดงผลลัพธ์ครั้งเดียว
            if(idx>=toSave.length){
                hideProgress();
                selTool('hand');
                if(failedPages.length===0){
                    alert('✅ บันทึกสำเร็จทั้งหมด '+successCount+' หน้า!');
                } else {
                    alert('⚠️ บันทึกสำเร็จ '+successCount+' หน้า\n❌ ล้มเหลว '+failedPages.length+' หน้า: '+failedPages.join(', '));
                }
                return;
            }

            var item=toSave[idx];
            updateProgress(idx+1, toSave.length, 'บันทึกหน้า '+item.page+'...');

            fetch('save_drawing.php',{
                method:'POST',
                headers:{'Content-Type':'application/json'},
                body:JSON.stringify({id:FILE_ID, data:item.data, page:item.page})
            })
            .then(function(r){
                if(!r.ok) throw new Error('HTTP '+r.status);
                return r.json();
            })
            .then(function(d){
                if(d.success){
                    savedDrawings[item.page]=item.data;
                    delete dirtyPages[item.page];
                    delete drawTimestamps[item.page];  // ★ reset ชื่อผู้วาดกลับเป็นข้อมูลจาก DB
                    // ★ sync textboxes + shape overlays into sessionStrokes before POSTing
                    saveTextBoxStrokesForPage(item.page, true);  // skipDirty=true: page was just saved
                    saveSovStrokesForPage(item.page, true);
                    // ★ POST strokes ของหน้านี้ (ส่งเสมอแม้ว่าจะว่าง เพื่อลบ textbox ที่ถูกลบออกจาก DB)
                    (function(pg, stks){
                        fetch('save_strokes.php',{
                            method:'POST',
                            headers:{'Content-Type':'application/json'},
                            body:JSON.stringify({id:FILE_ID,page:pg,strokes:stks})
                        }).then(function(r){return r.json();}).then(function(sd){
                            if(sd.success && stks.length>0){ mergeSessionStrokesToLayer(pg); }
                        }).catch(function(){});
                    })(item.page, (sessionStrokes[item.page]||[]).slice());
                    successCount++;
                } else {
                    console.error('Save error page '+item.page+':', d);
                    failedPages.push(item.page);
                }
                idx++;
                next();
            })
            .catch(function(err){
                console.error('Fetch error page '+item.page+':', err);
                failedPages.push(item.page);
                idx++;
                next(); // ★ ไม่หยุด ทำหน้าถัดไปต่อ
            });
        }

        next();
    }

    <?php else:?>
    // ===== IMAGE MODE =====
    var img=document.getElementById('baseImage');
    loadSavedDrawings(function(){if(img.complete)initImg();else img.onload=initImg;});

    function initImg(){
        dc.width=img.naturalWidth;dc.height=img.naturalHeight;
        // ★ ตั้ง explicit size ให้ img + canvas เท่ากัน (ไม่ใช้ max-width)
        img.style.width=img.naturalWidth+'px';
        img.style.height=img.naturalHeight+'px';
        img.style.maxWidth='none';
        dc.style.width=img.naturalWidth+'px';
        dc.style.height=img.naturalHeight+'px';
        document.getElementById('canvasSize').textContent=img.naturalWidth+'×'+img.naturalHeight+' px';
        applyZoom();
        applySavedDrawing(1);
    }

    function getDrawingForPage(pn){
        var hist=pageDrawings[pn];
        if(hist&&hist.length>0)return hist[hist.length-1];
        if(savedDrawings[pn]){var d=savedDrawings[pn];return(d.indexOf('data:')===0)?d:'data:image/png;base64,'+d;}
        return null;
    }

    function downloadAllPages(){
        // ★ render ที่ naturalWidth/Height → annotations scale 1:1 ตรงกับที่เห็น
        var mg=document.createElement('canvas');mg.width=img.naturalWidth;mg.height=img.naturalHeight;
        var mc=mg.getContext('2d');mc.drawImage(img,0,0,img.naturalWidth,img.naturalHeight);
        mc.drawImage(dc,0,0,dc.width,dc.height);
        // ★ flatten textboxes + shape overlays onto download canvas
        flattenTextBoxesToCanvas(mc, 1, 1, 1);
        flattenSovsToCanvas(mc, 1, 1, 1);
        var lk=document.createElement('a');
        lk.download='annotated_<?=pathinfo($file['file_name'],PATHINFO_FILENAME)?>.png';
        lk.href=mg.toDataURL('image/png');lk.click();
        logAction('download','ดาวน์โหลดไฟล์ (รูปภาพ)');
    }

        function saveAllPages(){
        // ★ ถ้ากำลัง insert image อยู่ → commit ก่อน (ลบ handles) แล้วค่อย save
        if(imgPlacing && insertImgObj){
            commitInsertImage(function(){ saveAllPages(); });
            return;
        }

        if(!dirtyPages[1]){
            alert('ยังไม่มีการวาดเพิ่มเติม');
            return;
        }

        var drawingBase64=dc.toDataURL('image/png').split(',')[1];

        fetch('save_drawing.php',{
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body:JSON.stringify({id:FILE_ID, data:drawingBase64, page:1})
        })
        .then(function(r){
            if(!r.ok) throw new Error('HTTP '+r.status);
            return r.json();
        })
        .then(function(d){
            if(d.success){
                savedDrawings[1]=drawingBase64;
                delete dirtyPages[1];
                delete drawTimestamps[1];  // ★ reset ชื่อผู้วาดกลับเป็นข้อมูลจาก DB
                // ★ sync textboxes + shape overlays into sessionStrokes before POSTing
                saveTextBoxStrokesForPage(1, true);  // skipDirty=true: page was just saved
                saveSovStrokesForPage(1, true);
                // ★ POST strokes ของหน้า 1 (ส่งเสมอแม้ว่าจะว่าง เพื่อลบ textbox ที่ถูกลบออกจาก DB)
                var pgStks1 = (sessionStrokes[1]||[]).slice();
                fetch('save_strokes.php',{
                    method:'POST',
                    headers:{'Content-Type':'application/json'},
                    body:JSON.stringify({id:FILE_ID,page:1,strokes:pgStks1})
                }).then(function(r){return r.json();}).then(function(sd){
                    if(sd.success && pgStks1.length>0){ mergeSessionStrokesToLayer(1); }
                }).catch(function(){});
                selTool('hand');
                alert('✅ บันทึกสำเร็จ!');
            } else {
                console.error('Save error:', d);
                alert('❌ บันทึกไม่สำเร็จ\n' + (d.error||'') + (d.detail ? '\n'+d.detail : ''));
            }
        })
        .catch(function(err){
            console.error('Fetch error:', err);
            alert('❌ บันทึกไม่สำเร็จ\nError: '+err.message);
        });
    }
    <?php endif;?>

    // STATE
        // STATE
    function saveState(){
        var h=getH();if(h.length>maxHist)h.shift();
        h.push(dc.toDataURL());
        pageRedoStack[currentPage]=[];
        dirtyPages[currentPage]=true;
        // ★ บันทึกเวลาที่วาดหน้านี้
        drawTimestamps[currentPage] = new Date();
        updateButtons();
    }

    // ★★★ ANNOTATION INFO — แสดงชื่อคนวาด วันเวลา ★★★
    var annotatorName = <?= json_encode($displayName) ?>;
    var annotationInfo = document.getElementById('annotationInfo');
    var lastDrawTime = {};  // เก็บเวลาวาดล่าสุดของแต่ละหน้า

    function updateAnnotationInfo() {
        var now = new Date();
        lastDrawTime[currentPage] = now;

        var dateStr = padZ(now.getDate()) + '/' + padZ(now.getMonth()+1) + '/' + (now.getFullYear()+543);
        var timeStr = padZ(now.getHours()) + ':' + padZ(now.getMinutes()) + ':' + padZ(now.getSeconds()) + ' น.';

        var dirtyCount = 0;
        for (var k in dirtyPages) { if (dirtyPages.hasOwnProperty(k) && dirtyPages[k]) dirtyCount++; }

        var html = '';
        html += '<span class="anno-item"><span class="anno-icon">👤</span><span class="anno-label">ผู้วาด:</span><span class="anno-value">' + escHtml(annotatorName) + '</span></span>';
        html += '<span class="anno-divider"></span>';
        html += '<span class="anno-item"><span class="anno-icon">📅</span><span class="anno-label">วันที่:</span><span class="anno-value">' + dateStr + '</span></span>';
        html += '<span class="anno-divider"></span>';
        html += '<span class="anno-item"><span class="anno-icon">🕐</span><span class="anno-label">เวลา:</span><span class="anno-value">' + timeStr + '</span></span>';
        html += '<span class="anno-divider"></span>';

        if (isPDF) {
            html += '<span class="anno-item"><span class="anno-badge anno-page-badge">📄 หน้า ' + currentPage + '/' + totalPages + '</span></span>';
            html += '<span class="anno-divider"></span>';
        }

        html += '<span class="anno-item"><span class="anno-badge">✏️ แก้ไข ' + dirtyCount + ' หน้า (ยังไม่บันทึก)</span></span>';

        annotationInfo.innerHTML = html;
        annotationInfo.classList.add('visible');
    }

    function padZ(n) { return n < 10 ? '0' + n : '' + n; }
    function escHtml(t) { var d = document.createElement('div'); d.textContent = t; return d.innerHTML; }

    // ★ อัปเดต annotation info เมื่อโหลด saved drawings สำเร็จ
    function showSavedAnnotationInfo() {
        var savedKeys = Object.keys(savedDrawings);
        if (savedKeys.length > 0) {
            var html = '';
            html += '<span class="anno-item"><span class="anno-icon">💾</span><span class="anno-label">มีการวาดที่บันทึกไว้:</span><span class="anno-value">' + savedKeys.length + ' หน้า</span></span>';
            html += '<span class="anno-divider"></span>';
            html += '<span class="anno-item"><span class="anno-icon">👤</span><span class="anno-label">โดย:</span><span class="anno-value">' + escHtml(annotatorName) + '</span></span>';
            annotationInfo.innerHTML = html;
            annotationInfo.classList.add('visible');
        }
    }
        function updateSavedAnnotationInfo(pageCount) {
        var now = new Date();
        var dateStr = padZ(now.getDate()) + '/' + padZ(now.getMonth()+1) + '/' + (now.getFullYear()+543);
        var timeStr = padZ(now.getHours()) + ':' + padZ(now.getMinutes()) + ':' + padZ(now.getSeconds()) + ' น.';

        var html = '';
        html += '<span class="anno-item"><span class="anno-icon">✅</span><span class="anno-value" style="color:#2e7d32">บันทึกสำเร็จ!</span></span>';
        html += '<span class="anno-divider"></span>';
        html += '<span class="anno-item"><span class="anno-icon">👤</span><span class="anno-label">โดย:</span><span class="anno-value">' + escHtml(annotatorName) + '</span></span>';
        html += '<span class="anno-divider"></span>';
        html += '<span class="anno-item"><span class="anno-icon">📅</span><span class="anno-value">' + dateStr + ' ' + timeStr + '</span></span>';
        html += '<span class="anno-divider"></span>';
        html += '<span class="anno-item"><span class="anno-badge" style="background:#2e7d32">💾 ' + pageCount + ' หน้า</span></span>';

        annotationInfo.innerHTML = html;
        annotationInfo.classList.add('visible');
    }
    function updateButtons(){
        var hasCanvasUndo=getH().length>1;
        var hasTBoxUndo=(typeof tboxDeleteStack!=='undefined')&&(tboxDeleteStack[currentPage]||[]).length>0;
        var hasSovUndo=(typeof sovDeleteStack!=='undefined')&&(sovDeleteStack[currentPage]||[]).length>0;
        document.getElementById('btnUndo').disabled=!hasCanvasUndo&&!hasTBoxUndo&&!hasSovUndo;
        document.getElementById('btnRedo').disabled=getR().length===0;
    }
    function undo(){
        var h=getH();if(h.length<=1)return;
        getR().push(h.pop());restoreState(h[h.length-1]);dirtyPages[currentPage]=true;
        // sync stroke history
        var pg=currentPage,hist=sessionStrokeHist[pg];
        if(hist){var idx=h.length-1;sessionStrokes[pg]=(hist[idx]||[]).slice();initSelfLayer();}
        updateButtons();
    }
    function redo(){
        var r=getR();if(!r.length)return;
        var s=r.pop();var h=getH();h.push(s);restoreState(s);dirtyPages[currentPage]=true;
        // sync stroke history
        var pg=currentPage,hist=sessionStrokeHist[pg];
        if(hist){var idx=h.length-1;sessionStrokes[pg]=(hist[idx]||[]).slice();initSelfLayer();}
        updateButtons();
    }
    function restoreState(u){var i2=new Image();i2.onload=function(){ctx.clearRect(0,0,dc.width,dc.height);ctx.drawImage(i2,0,0);};i2.src=u;}
    function clearCanvas(){if(!confirm('ล้างการวาดทั้งหมด?'))return;ctx.clearRect(0,0,dc.width,dc.height);saveState();logAction('clear_drawing','ล้าง drawing หน้า '+currentPage+' ของไฟล์ #'+FILE_ID);}

    // ★★★ DRAWING — getPos คำนวณจาก actual canvas size (ไม่กระทบ zoom) ★★★
    function getPos(e){
        var r=dc.getBoundingClientRect();
        var sx=dc.width/r.width, sy=dc.height/r.height;
        var t=e.touches?e.touches[0]:e;
        return{x:(t.clientX-r.left)*sx, y:(t.clientY-r.top)*sy};
    }
    function setStyle(){
        ctx.strokeStyle=currentTool==='eraser'?'rgba(0,0,0,1)':currentColor;ctx.fillStyle=currentColor;ctx.lineJoin='round';ctx.lineCap='round';
        if(currentTool==='pen'){ctx.lineWidth=currentSize;ctx.globalAlpha=1;ctx.globalCompositeOperation='source-over';}
        else if(currentTool==='pencil'){ctx.lineWidth=Math.max(1,currentSize*.5);ctx.globalAlpha=.6;ctx.globalCompositeOperation='source-over';}
        else if(currentTool==='marker'){ctx.lineWidth=currentSize*3;ctx.globalAlpha=.3;ctx.globalCompositeOperation='source-over';}
        else if(currentTool==='eraser'){ctx.lineWidth=currentSize*3;ctx.globalAlpha=1;ctx.globalCompositeOperation='destination-out';}
        else{ctx.lineWidth=currentSize;ctx.globalAlpha=1;ctx.globalCompositeOperation='source-over';}
    }
    var tc2,tc2x;
    function createTemp(){tc2=document.createElement('canvas');tc2.width=dc.width;tc2.height=dc.height;tc2x=tc2.getContext('2d');tc2x.drawImage(dc,0,0);}
    dc.addEventListener('mousedown',onS);dc.addEventListener('mousemove',onM);dc.addEventListener('mouseup',onE);dc.addEventListener('mouseleave',onE);
    dc.addEventListener('touchstart',function(e){e.preventDefault();onS(e);},{passive:false});
    dc.addEventListener('touchmove',function(e){e.preventDefault();onM(e);},{passive:false});
    dc.addEventListener('touchend',onE);
        // ★★★ TOOLTIP — แสดงชื่อผู้วาด วันเวลา เมื่อชี้ที่เส้นที่วาด ★★★
    var tooltip = document.getElementById('drawTooltip');
    var tooltipName = <?= json_encode($displayName) ?>;  // ชื่อผู้ใช้ปัจจุบัน (ใช้เมื่อกำลังวาดอยู่)
    var tooltipVisible = false;
    var drawTimestamps = {};  // เก็บเวลาวาดของแต่ละหน้า { page: Date }

    function checkPixelHasDrawing(e) {
        // ถ้ากำลังวาดอยู่ ไม่ต้องแสดง tooltip
        if (isDrawing) { hideTooltip(); return; }

        var pos = getPos(e);
        var px = Math.floor(pos.x);
        var py = Math.floor(pos.y);

        // ป้องกันออกนอกขอบ
        if (px < 0 || py < 0 || px >= dc.width || py >= dc.height) {
            hideTooltip(); return;
        }

        // ตรวจ main canvas ก่อนว่ามี pixel ไหม
        if (!hasPixelInRegion(ctx, dc, px, py, 3)) { hideTooltip(); return; }

        // ★ หาว่าใครวาด ณ ตำแหน่งนี้
        var drawer = findDrawerAtPixel(px, py);
        showTooltipAt(e, drawer);
    }

    function showTooltipAt(e, drawer) {
        var mouseEvent = e.touches ? e.touches[0] : e;
        var mx = mouseEvent.clientX;
        var my = mouseEvent.clientY;

        // ★ ชื่อผู้วาดและเวลา จาก drawer info
        var drawerName = (drawer && drawer.name) ? drawer.name : tooltipName;
        var dateStr = '', timeStr = '';

        if (drawer && drawer.time) {
            var dt = typeof drawer.time === 'string' ? new Date(drawer.time) : drawer.time;
            if (!isNaN(dt.getTime())) {
                dateStr = padZ(dt.getDate()) + '/' + padZ(dt.getMonth()+1) + '/' + (dt.getFullYear()+543);
                timeStr = padZ(dt.getHours()) + ':' + padZ(dt.getMinutes()) + ':' + padZ(dt.getSeconds()) + ' น.';
            }
        } else if (savedTimestamps[currentPage]) {
            var saved = savedTimestamps[currentPage];
            dateStr = padZ(saved.getDate()) + '/' + padZ(saved.getMonth()+1) + '/' + (saved.getFullYear()+543);
            timeStr = padZ(saved.getHours()) + ':' + padZ(saved.getMinutes()) + ':' + padZ(saved.getSeconds()) + ' น.';
        }

        var html = '';
        html += '<div class="tt-row"><span class="tt-icon">👤</span><span class="tt-label">ผู้วาด:</span><span class="tt-value">' + escHtml(drawerName) + '</span></div>';
        if (timeStr) {
            html += '<div class="tt-divider"></div>';
            html += '<div class="tt-row"><span class="tt-icon">📅</span><span class="tt-label">วันที่:</span><span class="tt-value">' + dateStr + '</span></div>';
            html += '<div class="tt-row"><span class="tt-icon">🕐</span><span class="tt-label">เวลา:</span><span class="tt-value">' + timeStr + '</span></div>';
        }
        if (isPDF) {
            html += '<div class="tt-divider"></div>';
            html += '<div class="tt-row"><span class="tt-icon">📄</span><span class="tt-label">หน้า:</span><span class="tt-value">' + currentPage + ' / ' + totalPages + '</span></div>';
        }

        tooltip.innerHTML = html;

        // ★ ตำแหน่ง tooltip (ชิดเมาส์ + ไม่ออกนอกจอ)
        var offsetX = 16, offsetY = 16;
        var tipX = mx + offsetX;
        var tipY = my + offsetY;

        // แสดงก่อนเพื่อวัดขนาด
        tooltip.classList.add('visible');

        var tipW = tooltip.offsetWidth;
        var tipH = tooltip.offsetHeight;
        var winW = window.innerWidth;
        var winH = window.innerHeight;

        if (tipX + tipW > winW - 8) tipX = mx - tipW - 8;
        if (tipY + tipH > winH - 8) tipY = my - tipH - 8;
        if (tipX < 8) tipX = 8;
        if (tipY < 8) tipY = 8;

        tooltip.style.left = tipX + 'px';
        tooltip.style.top = tipY + 'px';

        tooltipVisible = true;
    }

    function hideTooltip() {
        if (tooltipVisible) {
            tooltip.classList.remove('visible');
            tooltipVisible = false;
        }
    }

    function padZ(n) { return n < 10 ? '0' + n : '' + n; }
    function escHtml(t) { var d = document.createElement('div'); d.textContent = t; return d.innerHTML; }

    // ★ mousemove บน canvas → ตรวจจับ drawing pixel
    dc.addEventListener('mousemove', function(e) {
        if (!isDrawing) { checkPixelHasDrawing(e); }
    });
    dc.addEventListener('mouseleave', function() { hideTooltip(); });

    // ★★★ HAND TOOL (pan) state ★★★
    var isPanning=false, panStartX=0, panStartY=0, panScrollX=0, panScrollY=0;
    var _eraserOthersSnap=null; // snapshot ของ pixel คนอื่นก่อนเริ่มลบ

    // ★★★ MOVE TOOL state ★★★
    var moveFloating=null;  // {canvas, ox, oy, cx, cy} — floating piece being dragged
    var moveBaseSnap=null;  // canvas snapshot with the piece erased (base for redraw)
    var moveDragOff={x:0,y:0}; // offset from click to piece top-left

    // Find connected non-transparent pixels from a starting point (BFS flood-fill)
    function findConnectedRegion(sx, sy, alphaTh){
        alphaTh = alphaTh||10;
        var w=dc.width, h=dc.height;
        var imgData=ctx.getImageData(0,0,w,h);
        var data=imgData.data;
        var px=Math.round(sx), py=Math.round(sy);
        if(px<0||py<0||px>=w||py>=h) return null;
        // Check if start pixel has alpha
        if(data[(py*w+px)*4+3]<alphaTh) return null;

        var visited=new Uint8Array(w*h);
        var queue=[[px,py]];
        visited[py*w+px]=1;
        var minX=px,maxX=px,minY=py,maxY=py;
        var pixels=[];

        while(queue.length>0){
            var p=queue.shift();
            var cx=p[0], cy=p[1];
            pixels.push(p);
            if(cx<minX)minX=cx; if(cx>maxX)maxX=cx;
            if(cy<minY)minY=cy; if(cy>maxY)maxY=cy;
            // Check 8 neighbors (connectivity 8)
            for(var dy=-1;dy<=1;dy++){
                for(var dx=-1;dx<=1;dx++){
                    if(dx===0&&dy===0) continue;
                    var nx=cx+dx, ny=cy+dy;
                    if(nx<0||ny<0||nx>=w||ny>=h) continue;
                    var idx=ny*w+nx;
                    if(visited[idx]) continue;
                    if(data[idx*4+3]>=alphaTh){
                        visited[idx]=1;
                        queue.push([nx,ny]);
                    }
                }
            }
        }
        if(pixels.length<2) return null;

        // Create a transparent canvas with just those pixels
        var rw=maxX-minX+1, rh=maxY-minY+1;
        var pieceCanvas=document.createElement('canvas');
        pieceCanvas.width=rw; pieceCanvas.height=rh;
        var pctx=pieceCanvas.getContext('2d');
        var pieceData=pctx.createImageData(rw,rh);
        var pd=pieceData.data;

        for(var i=0;i<pixels.length;i++){
            var ppx=pixels[i][0], ppy=pixels[i][1];
            var srcIdx=(ppy*w+ppx)*4;
            var dstIdx=((ppy-minY)*rw+(ppx-minX))*4;
            pd[dstIdx]=data[srcIdx];
            pd[dstIdx+1]=data[srcIdx+1];
            pd[dstIdx+2]=data[srcIdx+2];
            pd[dstIdx+3]=data[srcIdx+3];
            // Erase from original
            data[srcIdx+3]=0;
        }
        pctx.putImageData(pieceData,0,0);

        // Put erased image back
        ctx.putImageData(imgData,0,0);

        return {canvas:pieceCanvas, ox:minX, oy:minY, w:rw, h:rh};
    }

    function clearMoveState(){
        moveFloating=null; moveBaseSnap=null; moveDragOff={x:0,y:0};
        updateCursor();
    }

    // ★ Track last mouse/touch position for move tool
    var _lastMovePos={x:0,y:0};

    function onS(e){
        if(!CAN_EDIT) return;
        if(imgPlacing) return; // image placement handled by separate listeners
        if(currentTool==='hand'){
            // Try to grab a drawn stroke first (move functionality)
            var pos=getPos(e); _lastMovePos=pos;
            // ชุดตรวจว่า pixel นี้เป็นของตัวเองหรือเหม #มีสิทธิ์ move
            var rx2=Math.round(pos.x), ry2=Math.round(pos.y);
            var canMovePixel = CURRENT_USER_ID_DRAW === FILE_OWNER_ID ||
                (selfLayer && hasPixelInRegion(selfLayer.ctx, selfLayer.canvas, rx2, ry2, 3)) ||
                (userLayers && userLayers['' + CURRENT_USER_ID_DRAW] &&
                 hasPixelInRegion(userLayers['' + CURRENT_USER_ID_DRAW].ctx, userLayers['' + CURRENT_USER_ID_DRAW].canvas, rx2, ry2, 3));
            if(canMovePixel){
                var region=findConnectedRegion(pos.x, pos.y);
                if(region){
                    moveFloating=region;
                    moveDragOff={x:pos.x-region.ox, y:pos.y-region.oy};
                    moveBaseSnap=ctx.getImageData(0,0,dc.width,dc.height);
                    ctx.putImageData(moveBaseSnap,0,0);
                    ctx.drawImage(region.canvas, region.ox, region.oy);
                    isDrawing=true;
                    dc.style.cursor='grabbing';
                    return;
                }
            }
            // No permission or no stroke → pan the canvas
            isPanning=true;var t=e.touches?e.touches[0]:e;panStartX=t.clientX;panStartY=t.clientY;panScrollX=wrapper.scrollLeft;panScrollY=wrapper.scrollTop;dc.style.cursor='grabbing';return;
        }
        if(currentTool==='text'){
            // Check if clicking an existing textbox
            var p=getPos(e);
            var hitBox=hitTestTextBox(p.x,p.y);
            if(hitBox){ activateTextBox(hitBox); return; }
            // Start drawing new textbox region
            tboxDrawing=true;
            isDrawing=true;
            var pos2=getPos(e); startX=pos2.x; startY=pos2.y;
            lastDrawX=pos2.x; lastDrawY=pos2.y;
            createTemp();
            return;
        }
        if(moveFloating){ clearMoveState(); }
        isDrawing=true;var pos=getPos(e);startX=pos.x;startY=pos.y;
        lastDrawX=pos.x;lastDrawY=pos.y;
        setStyle();
        if(['pen','pencil','marker','eraser'].indexOf(currentTool)>=0){
            currentStrokePoints=[[pos.x,pos.y]];ctx.beginPath();ctx.moveTo(pos.x,pos.y);
            // เมื่อเริ่มลบ: เก็บ snapshot ของ canvas คนอื่นไว้ก่อน
            if(currentTool==='eraser'){
                _eraserOthersSnap = null;
                var othersC = document.createElement('canvas');
                othersC.width = dc.width; othersC.height = dc.height;
                var othersCtx = othersC.getContext('2d');
                for(var uid in userLayers){
                    if(parseInt(uid,10) === CURRENT_USER_ID_DRAW) continue;
                    othersCtx.drawImage(userLayers[uid].canvas, 0, 0);
                }
                _eraserOthersSnap = othersC;
            }
        }else{
            currentStrokePoints=[];createTemp();
        }}
    function onM(e){
        if(imgPlacing) return;
        if(isPanning){var t=e.touches?e.touches[0]:e;wrapper.scrollLeft=panScrollX-(t.clientX-panStartX);wrapper.scrollTop=panScrollY-(t.clientY-panStartY);return;}
        if(currentTool==='hand' && isDrawing && moveFloating && moveBaseSnap){
            var pos=getPos(e); _lastMovePos=pos;
            var nx=pos.x-moveDragOff.x, ny=pos.y-moveDragOff.y;
            ctx.putImageData(moveBaseSnap,0,0);
            ctx.drawImage(moveFloating.canvas, nx, ny);
            return;
        }
        if(!isDrawing)return;var pos=getPos(e);
        lastDrawX=pos.x;lastDrawY=pos.y;
        if(tboxDrawing){
            // preview dashed rect
            ctx.clearRect(0,0,dc.width,dc.height);ctx.globalAlpha=1;ctx.globalCompositeOperation='source-over';ctx.drawImage(tc2,0,0);
            ctx.save();ctx.strokeStyle=currentColor;ctx.lineWidth=2;ctx.setLineDash([6,4]);ctx.globalAlpha=.8;
            ctx.strokeRect(startX,startY,pos.x-startX,pos.y-startY);
            ctx.restore();ctx.setLineDash([]);
            return;
        }
        if(['pen','pencil','marker','eraser'].indexOf(currentTool)>=0){
            currentStrokePoints.push([pos.x,pos.y]);ctx.lineTo(pos.x,pos.y);ctx.stroke();
        }else{
            ctx.clearRect(0,0,dc.width,dc.height);ctx.globalAlpha=1;ctx.globalCompositeOperation='source-over';ctx.drawImage(tc2,0,0);setStyle();drawSh(startX,startY,pos.x,pos.y);
        }}
    function onE(){
        if(imgPlacing) return;
        if(isPanning){isPanning=false;updateCursor();return;}
        if(currentTool==='hand' && isDrawing && moveFloating && moveBaseSnap){
            isDrawing=false;
            var pos=_lastMovePos;
            var nx=pos.x-moveDragOff.x, ny=pos.y-moveDragOff.y;
            ctx.putImageData(moveBaseSnap,0,0);
            ctx.drawImage(moveFloating.canvas, nx, ny);
            clearMoveState();
            saveState();
            logAction('tool_use','ย้าย drawing หน้า '+currentPage);
            return;
        }
        // ★ TEXT BOX: finish drawing region
        if(tboxDrawing){
            tboxDrawing=false; isDrawing=false;
            // restore canvas (remove preview dash)
            var hh2=getH();
            if(hh2.length>0) restoreState(hh2[hh2.length-1]);
            else ctx.clearRect(0,0,dc.width,dc.height);
            var bx=Math.min(startX,lastDrawX), by=Math.min(startY,lastDrawY);
            var bw=Math.abs(lastDrawX-startX), bh=Math.abs(lastDrawY-startY);
            if(bw<20) bw=200;
            if(bh<20) bh=80;
            createTextBox(bx, by, bw, bh, '', true);
            return;
        }
        if(!isDrawing)return;isDrawing=false;
        // ★ เมื่อลบเสร็จ: restore พิกเซลของคนอื่นกลับคืน (ยางลบได้เฉพาะของตัวเอง)
        if(currentTool==='eraser' && _eraserOthersSnap && CURRENT_USER_ID_DRAW !== FILE_OWNER_ID){
            ctx.globalAlpha=1; ctx.globalCompositeOperation='source-over';
            ctx.drawImage(_eraserOthersSnap, 0, 0);
            _eraserOthersSnap = null;
        }
        ctx.globalAlpha=1;ctx.globalCompositeOperation='source-over';
        // ★ สร้าง stroke object ก่อน saveState
        var stroke=null;
        if(['pen','pencil','marker'].indexOf(currentTool)>=0 && currentStrokePoints.length>1){
            stroke={type:'path',tool:currentTool,color:currentColor,size:currentSize,points:currentStrokePoints.slice()};
        } else if(['line','rect','circle','arrow'].indexOf(currentTool)>=0){
            // ★ Shape tools → create overlay instead of flattening to canvas
            var bx2=Math.min(startX,lastDrawX), by2=Math.min(startY,lastDrawY);
            var bw2=Math.abs(lastDrawX-startX), bh2=Math.abs(lastDrawY-startY);
            if(bw2<10) bw2=60; if(bh2<10) bh2=60;
            // restore canvas to state before the preview stroke
            var hh2=getH();
            if(hh2.length>0) restoreState(hh2[hh2.length-1]);
            else ctx.clearRect(0,0,dc.width,dc.height);
            var sov=createShapeOverlay(bx2, by2, bw2, bh2, currentTool, currentColor, currentSize, null, null, null, CURRENT_USER_ID_DRAW, 0, CURRENT_USER_NAME, new Date().toISOString());
            saveSovStrokesForPage(currentPage);
            selTool('hand');
            return;
        }
        saveState();
        if(stroke){ recordSessionStroke(stroke); }
        // ★ เครื่องมือ shape เสร็จแล้ว → กลับเป็น Hand
        if(['line','rect','circle','arrow'].indexOf(currentTool)>=0) selTool('hand');
    }
    function drawSh(x1,y1,x2,y2){ctx.beginPath();if(currentTool==='line'){ctx.moveTo(x1,y1);ctx.lineTo(x2,y2);ctx.stroke();}else if(currentTool==='rect'){ctx.strokeRect(x1,y1,x2-x1,y2-y1);}else if(currentTool==='circle'){var rx=Math.abs(x2-x1)/2,ry=Math.abs(y2-y1)/2;ctx.ellipse(Math.min(x1,x2)+rx,Math.min(y1,y2)+ry,rx,ry,0,0,Math.PI*2);ctx.stroke();}else if(currentTool==='arrow'){var a=Math.atan2(y2-y1,x2-x1),hh=Math.max(20,currentSize*5);ctx.moveTo(x1,y1);ctx.lineTo(x2,y2);ctx.stroke();ctx.beginPath();ctx.moveTo(x2,y2);ctx.lineTo(x2-hh*Math.cos(a-Math.PI/6),y2-hh*Math.sin(a-Math.PI/6));ctx.moveTo(x2,y2);ctx.lineTo(x2-hh*Math.cos(a+Math.PI/6),y2-hh*Math.sin(a+Math.PI/6));ctx.stroke();}}

    // ★ บันทึก stroke ลงใน sessionStrokes + sessionStrokeHist + selfLayer
    function recordSessionStroke(stroke) {
        var pg = currentPage;
        if (!sessionStrokes[pg]) sessionStrokes[pg] = [];
        if (!sessionStrokeHist[pg])  sessionStrokeHist[pg]  = [[]];
        sessionStrokes[pg].push(stroke);
        updateSelfLayer(stroke);
        // history index = current pageDrawings length - 1 (saveState เรียกไปแล้ว)
        sessionStrokeHist[pg].push(sessionStrokes[pg].slice());
    }

    // ===================================================================
    // ★★★★★  TEXT BOX SYSTEM  ★★★★★
    // ===================================================================
    var tboxDrawing = false;   // true while dragging to define new box
    var textBoxes = {};        // { page: [ {id, x, y, w, h, text, color, size, createdBy, createdAt, el} ] }
    var tboxIdCounter = 0;

    /* ---------- helpers for creator label ---------- */
    function formatTBoxDate(iso) {
        if (!iso) return '';
        var d = new Date(iso);
        if (isNaN(d.getTime())) return '';
        var dd = String(d.getDate()).padStart(2,'0');
        var mm = String(d.getMonth()+1).padStart(2,'0');
        var yy = String(d.getFullYear()).slice(2);
        var hh = String(d.getHours()).padStart(2,'0');
        var mn = String(d.getMinutes()).padStart(2,'0');
        return dd+'/'+mm+'/'+yy+' '+hh+':'+mn;
    }
    function updateCreatorLabel(cl, name, iso) {
        cl.innerHTML = '';
        var ns = document.createElement('span'); ns.className='tc-name';
        ns.textContent = name || '';
        var ds = document.createElement('span'); ds.className='tc-date';
        ds.textContent = formatTBoxDate(iso);
        cl.appendChild(ns);
        if (ds.textContent) cl.appendChild(ds);
    }

    /* ---------- canvas-to-screen coordinate helpers ---------- */
    function canvasToScreen(cx, cy) {
        var scaleFactor = zoomLevel / 100;
        var rect = document.getElementById('canvasContainer').getBoundingClientRect();
        var wrapRect = document.getElementById('canvasWrapper').getBoundingClientRect();
        // canvas coords → screen coords relative to canvasContainer
        var scx, scy;
        if (isPDF && pdfDoc) {
            var sx = (dc.getBoundingClientRect().width) / dc.width;
            var sy = (dc.getBoundingClientRect().height) / dc.height;
            scx = cx * sx;
            scy = cy * sy;
        } else {
            scx = cx * scaleFactor;
            scy = cy * scaleFactor;
        }
        return { x: scx, y: scy };
    }
    function canvasSizeToScreen(w, h) {
        var scaleFactor = zoomLevel / 100;
        if (isPDF && pdfDoc) {
            var sx = (dc.getBoundingClientRect().width) / dc.width;
            var sy = (dc.getBoundingClientRect().height) / dc.height;
            return { w: w * sx, h: h * sy };
        }
        return { w: w * scaleFactor, h: h * scaleFactor };
    }
    function screenSizeToCanvas(w, h) {
        var scaleFactor = zoomLevel / 100;
        if (isPDF && pdfDoc) {
            var sx = dc.width / (dc.getBoundingClientRect().width || dc.width);
            var sy = dc.height / (dc.getBoundingClientRect().height || dc.height);
            return { w: w * sx, h: h * sy };
        }
        return { w: w / scaleFactor, h: h / scaleFactor };
    }
    function screenPosToCanvas(ex, ey) {
        // ex/ey are relative to canvasContainer top-left (not page)
        var scaleFactor = zoomLevel / 100;
        if (isPDF && pdfDoc) {
            var sx = dc.width / (dc.getBoundingClientRect().width || dc.width);
            var sy = dc.height / (dc.getBoundingClientRect().height || dc.height);
            return { x: ex * sx, y: ey * sy };
        }
        return { x: ex / scaleFactor, y: ey / scaleFactor };
    }

    /* ---------- hit-test: is (cx,cy) inside any textbox overlay? ---------- */
    function hitTestTextBox(cx, cy) {
        var boxes = textBoxes[currentPage] || [];
        for (var i = boxes.length - 1; i >= 0; i--) {
            var b = boxes[i];
            if (cx >= b.x && cx <= b.x + b.w && cy >= b.y && cy <= b.y + b.h) return b;
        }
        return null;
    }

    /* ---------- create & show a textbox overlay ---------- */
    function createTextBox(cx, cy, cw, ch, text, activate, strokeId, createdBy, createdAt, createdUserId) {
        var pg = currentPage;
        if (!textBoxes[pg]) textBoxes[pg] = [];
        createdUserId = (createdUserId !== undefined && createdUserId !== null) ? parseInt(createdUserId, 10) : CURRENT_USER_ID_DRAW;

        var id = strokeId || ('tb_' + (++tboxIdCounter) + '_' + Date.now());
        var bxColor = currentColor;
        var bxSize  = currentSize;
        var bxFontFamily = currentFontFamily || 'Kanit';
        var sizeOpts = [10,12,14,16,18,20,24,28,32,36,42,48,56,64];
        var rawFontPx = Math.max(12, bxSize * 4);
        var bxFontSize = sizeOpts.reduce(function(p,c){ return Math.abs(c-rawFontPx)<Math.abs(p-rawFontPx)?c:p; });
        createdBy = (createdBy !== undefined && createdBy !== null && createdBy !== '') ? createdBy : CURRENT_USER_NAME;
        createdAt = createdAt || new Date().toISOString();
        var _owner = canManage(createdUserId);

        var el = document.createElement('div');
        el.className = 'tbox-overlay tbox-active';
        el.dataset.tboxId = id;
        positionTBoxEl(el, cx, cy, cw, ch);
        el.dataset.bxFontBase   = bxFontSize;
        el.dataset.bxFontFamily = bxFontFamily;

        // ── toolbar ──────────────────────────────────────────────
        var tb = document.createElement('div');
        tb.className = 'tbox-toolbar';
        tb.addEventListener('mousedown', function(e){ e.stopPropagation(); });

        var fontSel = document.createElement('select');
        fontSel.className = 'tbox-font-sel'; fontSel.title = 'ฟอนต์';
        [['Kanit','Kanit'],['Sarabun','Sarabun'],['Prompt','Prompt'],['Mitr','Mitr'],
         ['Noto Sans Thai','Noto Sans Thai'],['Arial','Arial'],
         ['Times New Roman','Times'],['Courier New','Courier']].forEach(function(f){
            var op = document.createElement('option');
            op.value = f[0]; op.textContent = f[1];
            if (f[0] === bxFontFamily) op.selected = true;
            fontSel.appendChild(op);
        });

        var sizeSel = document.createElement('select');
        sizeSel.className = 'tbox-size-sel'; sizeSel.title = 'ขนาดตัวอักษร (px)';
        sizeOpts.forEach(function(s){
            var op = document.createElement('option');
            op.value = s; op.textContent = s;
            if (s === bxFontSize) op.selected = true;
            sizeSel.appendChild(op);
        });

        var colorInp = document.createElement('input');
        colorInp.type = 'color'; colorInp.className = 'tbox-color-inp';
        colorInp.title = 'สีตัวอักษร'; colorInp.value = bxColor;

        var confirmBtn = document.createElement('button');
        confirmBtn.type = 'button'; confirmBtn.className = 'tbox-confirm-btn';
        confirmBtn.textContent = '💾 บันทึก';

        tb.appendChild(fontSel); tb.appendChild(sizeSel);
        tb.appendChild(colorInp); tb.appendChild(confirmBtn);

        // ── textarea ─────────────────────────────────────────────
        var ta = document.createElement('textarea');
        ta.className = 'tbox-textarea';
        ta.value = text || '';
        ta.style.color      = bxColor;
        ta.style.fontSize   = bxFontSize + 'px';
        ta.style.fontFamily = "'" + bxFontFamily + "', sans-serif";
        ta.placeholder = 'พิมพ์ข้อความ...';
        ta.spellcheck = false;

        // ── other elements ───────────────────────────────────────
        var dh = document.createElement('div'); dh.className = 'tbox-drag';
        var cl = document.createElement('div'); cl.className = 'tbox-creator';
        updateCreatorLabel(cl, createdBy, createdAt);
        var rh = document.createElement('div'); rh.className = 'tbox-resize';
        var db = document.createElement('button');
        db.className = 'tbox-del'; db.title = 'ลบกล่องข้อความ'; db.textContent = '×';

        el.appendChild(tb); el.appendChild(ta); el.appendChild(dh);
        el.appendChild(cl); el.appendChild(rh); el.appendChild(db);
        document.getElementById('canvasContainer').appendChild(el);

        var boxObj = { id: id, x: cx, y: cy, w: cw, h: ch,
                       text: text || '', color: bxColor, size: bxSize,
                       fontFamily: bxFontFamily, fontSize: bxFontSize,
                       el: el, ta: ta, page: pg,
                       createdBy: createdBy, createdAt: createdAt, createdUserId: createdUserId };
        textBoxes[pg].push(boxObj);

        if (!_owner) {
            tb.style.display = 'none'; db.style.display = 'none'; rh.style.display = 'none';
            dh.style.cursor = 'default'; dh.style.pointerEvents = 'none';
            ta.setAttribute('readonly', '');
        }

        // ─── drag: toolbar (active) + strip (saved) ───
        makeDraggableTBox(el, tb, boxObj);
        makeDraggableTBox(el, dh, boxObj);

        // ─── resize ───
        makeResizableTBox(el, rh, boxObj);

        // ─── delete ───
        db.addEventListener('click', function(e){
            e.stopPropagation();
            if (!canManage(boxObj.createdUserId)) return;
            removeTextBox(boxObj);
        });

        // ─── toolbar: font ───
        fontSel.addEventListener('mousedown', function(e){ e.stopPropagation(); });
        fontSel.addEventListener('change', function(){
            boxObj.fontFamily = fontSel.value; currentFontFamily = fontSel.value;
            el.dataset.bxFontFamily = fontSel.value;
            ta.style.fontFamily = "'" + fontSel.value + "', sans-serif";
            commitTextBox(boxObj);
            setTimeout(function(){ ta.focus(); }, 10);
        });

        // ─── toolbar: size ───
        sizeSel.addEventListener('mousedown', function(e){ e.stopPropagation(); });
        sizeSel.addEventListener('change', function(){
            boxObj.fontSize = parseInt(sizeSel.value);
            el.dataset.bxFontBase = boxObj.fontSize;
            positionTBoxEl(el, boxObj.x, boxObj.y, boxObj.w, boxObj.h);
            commitTextBox(boxObj);
            setTimeout(function(){ ta.focus(); }, 10);
        });

        // ─── toolbar: color ───
        colorInp.addEventListener('mousedown', function(e){ e.stopPropagation(); });
        colorInp.addEventListener('input', function(){
            boxObj.color = colorInp.value; ta.style.color = colorInp.value;
            commitTextBox(boxObj);
        });
        colorInp.addEventListener('change', function(){ setTimeout(function(){ ta.focus(); }, 10); });

        // ─── toolbar: confirm / save ───
        confirmBtn.addEventListener('mousedown', function(e){ e.preventDefault(); e.stopPropagation(); });
        confirmBtn.addEventListener('click', function(e){
            e.stopPropagation();
            el.classList.remove('tbox-active');
            el.classList.add('tbox-saved');
            if (_owner) ta.setAttribute('readonly', '');
            commitTextBox(boxObj);
        });

        // ─── textarea events ───
        ta.addEventListener('mousedown', function(e){ e.stopPropagation(); });
        ta.addEventListener('focus', function(){
            if (_owner) {
                el.classList.remove('tbox-saved');
                el.classList.add('tbox-active');
            }
        });
        ta.addEventListener('blur', function(){
            setTimeout(function(){
                var ae = document.activeElement;
                if (ae && ae !== document.body && el.contains(ae)) return;
                if (!el.classList.contains('tbox-active')) return;
                el.classList.remove('tbox-active');
                commitTextBox(boxObj);
            }, 120);
        });
        ta.addEventListener('input', function(){ boxObj.text = ta.value; markDirty(); });

        // ─── mouse leaves the overlay → switch to hand ───
        el.addEventListener('mouseleave', function(){
            if (currentTool === 'text') selTool('hand');
        });

        if (activate && _owner) {
            setTimeout(function(){
                ta.removeAttribute('readonly'); ta.focus();
                ta.setSelectionRange(ta.value.length, ta.value.length);
            }, 30);
        }
        return boxObj;
    }

    function positionTBoxEl(el, cx, cy, cw, ch) {
        var s = canvasToScreen(cx, cy);
        var sz = canvasSizeToScreen(cw, ch);
        el.style.left   = s.x + 'px';
        el.style.top    = s.y + 'px';
        el.style.width  = sz.w + 'px';
        el.style.height = sz.h + 'px';
        // scale font proportionally so text never overflows when zooming
        var scale = (cw > 0) ? sz.w / cw : 1;
        var baseFontPx = parseFloat(el.dataset.bxFontBase) || 16;
        var scaledFont = Math.max(8, baseFontPx * scale);
        var ta = el.querySelector('.tbox-textarea');
        if (ta) {
            ta.style.fontSize = scaledFont + 'px';
            var ff = el.dataset.bxFontFamily;
            if (ff) ta.style.fontFamily = "'" + ff + "', sans-serif";
        }
    }

    function repositionAllTBoxes() {
        var boxes = textBoxes[currentPage] || [];
        boxes.forEach(function(b) {
            if (b.el) positionTBoxEl(b.el, b.x, b.y, b.w, b.h);
        });
    }

    function activateTextBox(boxObj) {
        if (!canManage(boxObj.createdUserId)) return;
        boxObj.el.classList.remove('tbox-saved');
        boxObj.el.classList.add('tbox-active');
        boxObj.ta.removeAttribute('readonly');
        // sync toolbar controls with current box state
        var fs = boxObj.el.querySelector('.tbox-font-sel');
        var ss = boxObj.el.querySelector('.tbox-size-sel');
        var ci = boxObj.el.querySelector('.tbox-color-inp');
        if (fs) fs.value = boxObj.fontFamily || 'Kanit';
        if (ss) ss.value = String(boxObj.fontSize || 16);
        if (ci) ci.value = boxObj.color || '#000000';
        setTimeout(function(){ boxObj.ta.focus(); }, 10);
    }

    function commitTextBox(boxObj) {
        boxObj.text = boxObj.ta.value;
        markDirty();
        saveTextBoxStrokesForPage(boxObj.page);
    }

    function removeTextBox(boxObj) {
        // push snapshot to undo stack
        var pg = boxObj.page;
        if (!tboxDeleteStack[pg]) tboxDeleteStack[pg] = [];
        tboxDeleteStack[pg].push({
            id: boxObj.id, x: boxObj.x, y: boxObj.y, w: boxObj.w, h: boxObj.h,
            text: boxObj.text, color: boxObj.color, size: boxObj.size,
            fontFamily: boxObj.fontFamily || 'Kanit', fontSize: boxObj.fontSize || 16,
            createdBy: boxObj.createdBy || '', createdAt: boxObj.createdAt || '', page: pg
        });
        if (boxObj.el && boxObj.el.parentNode) boxObj.el.parentNode.removeChild(boxObj.el);
        var arr = textBoxes[pg] || [];
        var idx = arr.indexOf(boxObj);
        if (idx >= 0) arr.splice(idx, 1);
        saveTextBoxStrokesForPage(pg);
        markDirty();
        updateButtons();
    }

    function markDirty() {
        dirtyPages[currentPage] = true;
        updateButtons();
    }

    /* ---------- drag to move textbox ---------- */
    function makeDraggableTBox(el, handle, boxObj) {
        var dragging = false, ox = 0, oy = 0, startEL = 0, startET = 0;

        function mdStart(e) {
            if (!canManage(boxObj.createdUserId)) return;
            if (e.button !== 0 && e.type !== 'touchstart') return;
            e.preventDefault(); e.stopPropagation();
            dragging = true;
            var t = e.touches ? e.touches[0] : e;
            ox = t.clientX; oy = t.clientY;
            startEL = parseFloat(el.style.left) || 0;
            startET = parseFloat(el.style.top)  || 0;
        }
        function mdMove(e) {
            if (!dragging) return;
            e.preventDefault();
            var t = e.touches ? e.touches[0] : e;
            var dx = t.clientX - ox, dy = t.clientY - oy;
            var nl = startEL + dx, nt = startET + dy;
            el.style.left = nl + 'px';
            el.style.top  = nt + 'px';
            // update canvas coords
            var cc = screenPosToCanvas(nl, nt);
            boxObj.x = cc.x; boxObj.y = cc.y;
        }
        function mdEnd(e) {
            if (!dragging) return;
            dragging = false;
            markDirty();
        }

        handle.addEventListener('mousedown',  mdStart);
        handle.addEventListener('touchstart', mdStart, {passive:false});
        document.addEventListener('mousemove',  mdMove);
        document.addEventListener('touchmove',  mdMove, {passive:false});
        document.addEventListener('mouseup',    mdEnd);
        document.addEventListener('touchend',   mdEnd);
    }

    /* ---------- resize textbox ---------- */
    function makeResizableTBox(el, handle, boxObj) {
        var resizing = false, ox = 0, oy = 0, sw = 0, sh = 0;

        function rsStart(e) {
            if (!canManage(boxObj.createdUserId)) return;
            if (e.button !== 0 && e.type !== 'touchstart') return;
            e.preventDefault(); e.stopPropagation();
            resizing = true;
            var t = e.touches ? e.touches[0] : e;
            ox = t.clientX; oy = t.clientY;
            sw = parseFloat(el.style.width)  || 100;
            sh = parseFloat(el.style.height) || 60;
        }
        function rsMove(e) {
            if (!resizing) return;
            e.preventDefault();
            var t = e.touches ? e.touches[0] : e;
            var nw = Math.max(60, sw + (t.clientX - ox));
            var nh = Math.max(30, sh + (t.clientY - oy));
            el.style.width  = nw + 'px';
            el.style.height = nh + 'px';
            // sync canvas coords
            var cc = screenSizeToCanvas(nw, nh);
            boxObj.w = cc.w; boxObj.h = cc.h;
        }
        function rsEnd() {
            if (!resizing) return;
            resizing = false;
            markDirty();
        }

        handle.addEventListener('mousedown',  rsStart);
        handle.addEventListener('touchstart', rsStart, {passive:false});
        document.addEventListener('mousemove',  rsMove);
        document.addEventListener('touchmove',  rsMove, {passive:false});
        document.addEventListener('mouseup',    rsEnd);
        document.addEventListener('touchend',   rsEnd);
    }

    /* ---------- hide/show textboxes when changing page ---------- */
    function hideTBoxesForPage(pg) {
        var boxes = textBoxes[pg] || [];
        boxes.forEach(function(b) {
            if (b.el) b.el.style.display = 'none';
        });
    }
    function showTBoxesForPage(pg) {
        var boxes = textBoxes[pg] || [];
        boxes.forEach(function(b) {
            if (b.el) {
                b.el.style.display = '';
                positionTBoxEl(b.el, b.x, b.y, b.w, b.h);
            }
        });
    }

    /* ---------- save textbox strokes for this page into sessionStrokes ---------- */
    function saveTextBoxStrokesForPage(pg, skipDirty) {
        // Remove existing textbox strokes for this page from sessionStrokes
        if (!sessionStrokes[pg]) sessionStrokes[pg] = [];
        // filter non-textbox
        sessionStrokes[pg] = sessionStrokes[pg].filter(function(s){ return s.tool !== 'textbox' && s.type !== 'textbox'; });
        // push all current textboxes
        var boxes = textBoxes[pg] || [];
        boxes.forEach(function(b) {
            sessionStrokes[pg].push({
                type: 'textbox', tool: 'textbox',
                id: b.id, x: b.x, y: b.y, w: b.w, h: b.h,
                text: b.text, color: b.color, size: b.size,
                fontFamily: b.fontFamily || 'Kanit',
                fontSize:   b.fontSize   || Math.max(12, b.size * 4),
                page: pg,
                createdBy: b.createdBy || '',
                createdAt: b.createdAt || ''
            });
        });
        if (!skipDirty) {
            dirtyPages[pg] = true;
            updateButtons();
        }
    }

    /* ---------- load textbox strokes from savedStrokesData on init ---------- */
    function loadTextBoxesFromStrokes(pg) {
        var strokes = savedStrokesData[pg] || [];
        strokes.forEach(function(item) {
            var s = item.stroke;
            if (!s || (s.type !== 'textbox' && s.tool !== 'textbox')) return;
            // avoid duplicates
            var boxes = textBoxes[pg] || [];
            if (boxes.find(function(b){ return b.id === s.id; })) return;
            var box = createTextBox(s.x, s.y, s.w || 200, s.h || 80, s.text || '', false, s.id, s.createdBy || '', s.createdAt || '', item.user_id);
            box.color      = s.color      || box.color;
            box.size       = s.size       || box.size;
            box.fontFamily = s.fontFamily || 'Kanit';
            box.fontSize   = s.fontSize   || Math.max(12, box.size * 4);
            // apply to textarea + update dataset so zoom scaling works
            box.ta.style.color      = box.color;
            box.ta.style.fontSize   = box.fontSize + 'px';
            box.ta.style.fontFamily = "'" + box.fontFamily + "', sans-serif";
            box.el.dataset.bxFontBase   = box.fontSize;
            box.el.dataset.bxFontFamily = box.fontFamily;
            // sync toolbar controls
            var fs = box.el.querySelector('.tbox-font-sel');
            var ss = box.el.querySelector('.tbox-size-sel');
            var ci = box.el.querySelector('.tbox-color-inp');
            if (fs) fs.value = box.fontFamily;
            if (ss) ss.value = String(box.fontSize);
            if (ci) ci.value = box.color;
            box.el.classList.remove('tbox-active');
            box.el.classList.add('tbox-saved');
            if (pg !== currentPage) box.el.style.display = 'none';
        });
    }

    /* ---------- flatten textboxes onto a canvas context for download ---------- */
    function flattenTextBoxesToCanvas(targetCtx, pg, scaleX, scaleY) {
        scaleX = scaleX || 1; scaleY = scaleY || scaleX;
        var boxes = textBoxes[pg] || [];
        // also look at savedStrokesData for boxes not in textBoxes (different page)
        var items = boxes.slice();
        (savedStrokesData[pg] || []).forEach(function(item) {
            var s = item.stroke;
            if (!s || s.type !== 'textbox') return;
            if (!items.find(function(b){ return b.id === s.id; })) {
                items.push(s);
            }
        });
        items.forEach(function(b) {
            if (!b.text && !b.createdBy) return;
            var fontSize = Math.max(12, (b.size || 4) * 4) * scaleX;
            // use stored fontSize if available (overrides the size-based calc)
            if (b.fontSize) fontSize = b.fontSize * scaleX;
            var ff = b.fontFamily || 'Kanit';
            var bx = (b.x || 0) * scaleX, by = (b.y || 0) * scaleY;
            var bw = (b.w || 200) * scaleX, bh = (b.h || 80) * scaleY;
            var padding = 4 * scaleX;

            targetCtx.save();
            targetCtx.globalAlpha = 1;
            targetCtx.globalCompositeOperation = 'source-over';

            // ★ พื้นหลังกึ่งโปร่งใส (สีขาว 50%)
            targetCtx.fillStyle = 'rgba(255,255,255,0.5)';
            targetCtx.fillRect(bx, by, bw, bh);

            // ★ เส้นขอบประสีส้ม
            targetCtx.strokeStyle = 'rgba(255,107,53,0.55)';
            targetCtx.lineWidth = Math.max(1, 1.5 * scaleX);
            targetCtx.setLineDash([6 * scaleX, 4 * scaleX]);
            targetCtx.strokeRect(bx, by, bw, bh);
            targetCtx.setLineDash([]);

            // ★ ข้อความหลัก
            if (b.text) {
                targetCtx.fillStyle = b.color || '#000';
                targetCtx.font = 'bold ' + fontSize + 'px ' + ff + ', sans-serif';
                targetCtx.textBaseline = 'top';
                var lineH = fontSize * 1.35;
                var maxW = bw - padding * 2;
                // พื้นที่สำหรับ creator label ด้านล่าง
                var labelH = fontSize * 0.65 * 2 + padding;
                var drawY = by + padding + 12 * scaleX; // เผื่อ drag bar บน
                var lines = b.text.split('\n');
                lines.forEach(function(line) {
                    var wds = line.split(' ');
                    var cur = '';
                    wds.forEach(function(w) {
                        var test = cur ? cur + ' ' + w : w;
                        if (targetCtx.measureText(test).width > maxW && cur) {
                            if (drawY + lineH < by + bh - labelH) {
                                targetCtx.fillText(cur, bx + padding, drawY);
                            }
                            drawY += lineH;
                            cur = w;
                        } else { cur = test; }
                    });
                    if (cur) {
                        if (drawY + lineH < by + bh - labelH) {
                            targetCtx.fillText(cur, bx + padding, drawY);
                        }
                        drawY += lineH;
                    }
                });
            }

            // ★ creator label ด้านล่างกล่อง
            if (b.createdBy) {
                var labelFontSize = Math.max(8, fontSize * 0.55);
                targetCtx.font = '600 ' + labelFontSize + 'px Kanit,sans-serif';
                targetCtx.fillStyle = 'rgba(0,0,0,0.7)';
                targetCtx.textBaseline = 'bottom';
                // ชื่อ
                targetCtx.fillText(b.createdBy, bx + padding, by + bh - labelFontSize * 1.2 - padding);
                // วันที่
                if (b.createdAt) {
                    var d = new Date(b.createdAt);
                    var dateStr = (d.getDate()<10?'0':'')+d.getDate()+'/'+(d.getMonth()<9?'0':'')+(d.getMonth()+1)+'/'+String(d.getFullYear()).slice(-2)+' '+(d.getHours()<10?'0':'')+d.getHours()+':'+(d.getMinutes()<10?'0':'')+d.getMinutes();
                    targetCtx.font = labelFontSize * 0.85 + 'px Kanit,sans-serif';
                    targetCtx.fillText(dateStr, bx + padding, by + bh - padding);
                }
            }

            targetCtx.restore();
        });
    }

    // ★ reposition textboxes/overlays when zoom changes
    var _origSetZoom = setZoom;
    setZoom = function(level, fromSlider) {
        _origSetZoom(level, fromSlider);
        // Force CSS layout reflow ก่อน เพื่อให้ getBoundingClientRect() คืนค่าใหม่ถูกต้อง
        void dc.getBoundingClientRect();
        repositionAllTBoxes();
        repositionAllSovs();
    };

    // ★ load textboxes after drawings load  
    var _origLoadSaved = loadSavedDrawings;
    loadSavedDrawings = function(cb) {
        _origLoadSaved(function(){
            loadTextBoxesFromStrokes(currentPage);
            loadSovFromStrokes(currentPage);
            if (cb) cb();
        });
    };

    // ★ hook: when page changes (PDF), hide old page boxes / show new  
    (function(){
        var origRenderPDF = typeof renderPDFPage !== 'undefined' ? renderPDFPage : null;
        if (origRenderPDF) {
            renderPDFPage = function(num) {
                hideTBoxesForPage(currentPage);
                hideSovsForPage(currentPage);
                origRenderPDF(num);
                // load boxes for new page (may not be loaded yet)
                loadTextBoxesFromStrokes(num);
                loadSovFromStrokes(num);
                showTBoxesForPage(num);
                showSovsForPage(num);
            };
        }
    })();

    // overlay click on canvas container to not steal events from textboxes
    dc.addEventListener('click', function(e) {
        // deactivate all active boxes if clicking outside
        var boxes = textBoxes[currentPage] || [];
        boxes.forEach(function(b) {
            if (b.el.classList.contains('tbox-active')) {
                b.el.classList.remove('tbox-active');
                commitTextBox(b);
            }
        });
        // ★ if text tool is active and click lands outside any textbox → switch to hand
        if (currentTool === 'text') {
            var p = getPos(e);
            if (!hitTestTextBox(p.x, p.y)) {
                selTool('hand');
            }
        }
    });

    // ★ undo: restore last deleted textbox before canvas undo
    var _origUndoTBox = undo;
    undo = function() {
        var stack = tboxDeleteStack[currentPage] || [];
        if (stack.length > 0) {
            var data = stack.pop();
            var restored = createTextBox(data.x, data.y, data.w, data.h, data.text, false, data.id, data.createdBy, data.createdAt);
            restored.color      = data.color      || restored.color;
            restored.size       = data.size       || restored.size;
            restored.fontFamily = data.fontFamily || 'Kanit';
            restored.fontSize   = data.fontSize   || Math.max(12, restored.size * 4);
            restored.ta.style.color      = restored.color;
            restored.ta.style.fontSize   = restored.fontSize + 'px';
            restored.ta.style.fontFamily = "'" + restored.fontFamily + "', sans-serif";
            restored.el.dataset.bxFontBase   = restored.fontSize;
            restored.el.dataset.bxFontFamily = restored.fontFamily;
            var fs2 = restored.el.querySelector('.tbox-font-sel');
            var ss2 = restored.el.querySelector('.tbox-size-sel');
            var ci2 = restored.el.querySelector('.tbox-color-inp');
            if (fs2) fs2.value = restored.fontFamily;
            if (ss2) ss2.value = String(restored.fontSize);
            if (ci2) ci2.value = restored.color;
            var lbl = restored.el.querySelector('.tbox-creator');
            if (lbl) updateCreatorLabel(lbl, restored.createdBy, restored.createdAt);
            restored.el.classList.remove('tbox-active');
            restored.el.classList.add('tbox-saved');
            saveTextBoxStrokesForPage(currentPage);
            markDirty();
            updateButtons();
            return;
        }
        // ★ also restore last deleted shape overlay
        var sovStack = sovDeleteStack[currentPage] || [];
        if (sovStack.length > 0) {
            var sd = sovStack.pop();
            var rs = createShapeOverlay(sd.x, sd.y, sd.w, sd.h, sd.tool, sd.color, sd.size, sd.id, sd.imgSrc || null, null, undefined, sd.rotation || 0, CURRENT_USER_NAME, sd.createdAt || null);
            rs.el.classList.remove('sov-active');
            rs.el.classList.add('sov-saved');
            saveSovStrokesForPage(currentPage, true);
            markDirty();
            updateButtons();
            return;
        }
        _origUndoTBox();
    };

    // ★★★  keep old function signatures for compatibility  ★★★
    var tPos={x:0,y:0};
    function showTI(x,y){ createTextBox(x, y, 200, 80, '', true); }
    window.confirmText=function(){};
    window.cancelText=function(){};
    // (old textInput keydown listener removed — no element exists)

    // ★★★ INSERT IMAGE TOOL ★★★
    var insertImgObj=null; // {img, scale, x, y}
    var imgPlacing=false, imgDragging=false, imgDragOff={x:0,y:0};

    document.getElementById('btnInsertImage').addEventListener('click',function(){
        document.getElementById('imageFileInput').click();
    });

    document.getElementById('imageFileInput').addEventListener('change',function(e){
        var file=e.target.files[0]; if(!file) return;
        var reader=new FileReader();
        reader.onload=function(ev){
            var img=new Image();
            img.onload=function(){
                // Auto-scale to fit canvas if too large (max 50% of canvas)
                var maxW=dc.width*0.5, maxH=dc.height*0.5;
                var sc=1;
                if(img.width>maxW) sc=Math.min(sc, maxW/img.width);
                if(img.height*sc>maxH) sc=maxH/img.height;
                // Center on canvas
                var iw=img.width*sc, ih=img.height*sc;
                insertImgObj={img:img, scale:sc, x:(dc.width-iw)/2, y:(dc.height-ih)/2};
                imgPlacing=true;
                dc.style.cursor='move';
                drawImagePreviewOnCanvas();
            };
            img.src=ev.target.result;
        };
        reader.readAsDataURL(file);
        e.target.value='';
    });

    // Click to start dragging image OR resizing from corner
    var imgResizing=false, imgResizeCorner=null, imgResizeOrigin=null;
    var IMG_HANDLE=10; // handle hit radius in canvas pixels

    function getImgCorners(){
        if(!insertImgObj) return null;
        var iw=insertImgObj.img.width*insertImgObj.scale;
        var ih=insertImgObj.img.height*insertImgObj.scale;
        var x=insertImgObj.x, y=insertImgObj.y;
        return {
            tl:{x:x, y:y},
            tr:{x:x+iw, y:y},
            bl:{x:x, y:y+ih},
            br:{x:x+iw, y:y+ih}
        };
    }

    function hitCorner(pos){
        var c=getImgCorners(); if(!c) return null;
        // Adjust handle size based on zoom so it's always easy to grab
        var hr=IMG_HANDLE/(zoomLevel/100);
        if(Math.abs(pos.x-c.tl.x)<hr && Math.abs(pos.y-c.tl.y)<hr) return 'tl';
        if(Math.abs(pos.x-c.tr.x)<hr && Math.abs(pos.y-c.tr.y)<hr) return 'tr';
        if(Math.abs(pos.x-c.bl.x)<hr && Math.abs(pos.y-c.bl.y)<hr) return 'bl';
        if(Math.abs(pos.x-c.br.x)<hr && Math.abs(pos.y-c.br.y)<hr) return 'br';
        return null;
    }

    function cornerCursor(corner){
        if(corner==='tl'||corner==='br') return 'nwse-resize';
        if(corner==='tr'||corner==='bl') return 'nesw-resize';
        return 'move';
    }

    dc.addEventListener('mousedown',function(e){
        if(!imgPlacing||!insertImgObj) return;
        e.stopPropagation();
        var pos=getPos(e);
        var iw=insertImgObj.img.width*insertImgObj.scale;
        var ih=insertImgObj.img.height*insertImgObj.scale;

        // Check corner handles first
        var corner=hitCorner(pos);
        if(corner){
            imgResizing=true;
            imgResizeCorner=corner;
            // Store the anchor (opposite corner stays fixed)
            var c=getImgCorners();
            var opp={tl:'br',tr:'bl',bl:'tr',br:'tl'};
            imgResizeOrigin={x:c[opp[corner]].x, y:c[opp[corner]].y};
            dc.style.cursor=cornerCursor(corner);
            return;
        }

        // Otherwise drag the whole image
        if(pos.x>=insertImgObj.x && pos.x<=insertImgObj.x+iw && pos.y>=insertImgObj.y && pos.y<=insertImgObj.y+ih){
            imgDragOff={x:pos.x-insertImgObj.x, y:pos.y-insertImgObj.y};
        } else {
            insertImgObj.x=pos.x-iw/2; insertImgObj.y=pos.y-ih/2;
            imgDragOff={x:iw/2, y:ih/2};
        }
        imgDragging=true;
        dc.style.cursor='grabbing';
        drawImagePreviewOnCanvas();
    });

    dc.addEventListener('mousemove',function(e){
        if(!imgPlacing||!insertImgObj) return;

        var pos=getPos(e);

        // Corner resizing
        if(imgResizing&&imgResizeCorner&&imgResizeOrigin){
            var ox=imgResizeOrigin.x, oy=imgResizeOrigin.y;
            var dx=Math.abs(pos.x-ox), dy=Math.abs(pos.y-oy);
            // Maintain aspect ratio — use the larger axis to determine scale
            var aspect=insertImgObj.img.width/insertImgObj.img.height;
            var newW, newH;
            if(dx/aspect > dy){
                newW=Math.max(20, dx);
                newH=newW/aspect;
            } else {
                newH=Math.max(20, dy);
                newW=newH*aspect;
            }
            insertImgObj.scale=newW/insertImgObj.img.width;
            // Position: anchor corner stays fixed
            var rc=imgResizeCorner;
            if(rc==='tl'){
                insertImgObj.x=ox-newW; insertImgObj.y=oy-newH;
            } else if(rc==='tr'){
                insertImgObj.x=ox; insertImgObj.y=oy-newH;
            } else if(rc==='bl'){
                insertImgObj.x=ox-newW; insertImgObj.y=oy;
            } else {
                insertImgObj.x=ox; insertImgObj.y=oy;
            }
            drawImagePreviewOnCanvas();
            return;
        }

        // Dragging
        if(imgDragging){
            insertImgObj.x=pos.x-imgDragOff.x;
            insertImgObj.y=pos.y-imgDragOff.y;
            drawImagePreviewOnCanvas();
            return;
        }

        // Hover: change cursor over corners
        var corner=hitCorner(pos);
        if(corner){
            dc.style.cursor=cornerCursor(corner);
        } else {
            var iw=insertImgObj.img.width*insertImgObj.scale;
            var ih=insertImgObj.img.height*insertImgObj.scale;
            if(pos.x>=insertImgObj.x && pos.x<=insertImgObj.x+iw && pos.y>=insertImgObj.y && pos.y<=insertImgObj.y+ih){
                dc.style.cursor='move';
            } else {
                dc.style.cursor='default';
            }
        }
    });

    dc.addEventListener('mouseup',function(e){
        if(!imgPlacing||!insertImgObj) return;
        if(imgResizing){
            imgResizing=false; imgResizeCorner=null; imgResizeOrigin=null;
            dc.style.cursor='move';
            return;
        }
        if(imgDragging){
            imgDragging=false;
            dc.style.cursor='move';
        }
    });

    // Double-click to confirm placement
    dc.addEventListener('dblclick',function(e){
        if(!imgPlacing||!insertImgObj) return;
        commitInsertImage();
    });

    function commitInsertImage(callback){
        if(!insertImgObj){ if(callback) callback(); return; }
        var iw=insertImgObj.img.width*insertImgObj.scale;
        var ih=insertImgObj.img.height*insertImgObj.scale;
        var imgRef=insertImgObj.img;
        var ix=insertImgObj.x, iy=insertImgObj.y;

        // ★ Instead of flattening to canvas bitmap, create a resizable overlay
        // Convert image data to dataURL for persistence
        var imgSrc = imgRef.src || '';

        // Clear placement state first (restore canvas without image)
        var h=getH();
        function restoreAndCreate(){
            imgPlacing=false; insertImgObj=null; imgDragging=false; imgResizing=false; imgResizeCorner=null; imgResizeOrigin=null;
            dc.style.cursor='default';
            // Create shape overlay using canvas coords
            createShapeOverlay(ix, iy, iw, ih, 'img', '#000', 4, null, imgSrc, imgRef, CURRENT_USER_ID_DRAW, 0, CURRENT_USER_NAME, new Date().toISOString());
            saveSovStrokesForPage(currentPage);
            selTool('hand');
            logAction('tool_use','แทรกรูปภาพลง drawing หน้า '+currentPage);
            if(callback) callback();
        }
        if(h.length>0){
            var base=new Image(); base.onload=function(){
                ctx.clearRect(0,0,dc.width,dc.height);
                ctx.drawImage(base,0,0);
                restoreAndCreate();
            }; base.src=h[h.length-1];
        } else {
            ctx.clearRect(0,0,dc.width,dc.height);
            restoreAndCreate();
        }
    }

    // (scroll-to-resize removed — use corner handles instead)

    function drawImagePreviewOnCanvas(){
        if(!insertImgObj) return;
        var iw=insertImgObj.img.width*insertImgObj.scale;
        var ih=insertImgObj.img.height*insertImgObj.scale;
        var h=getH();
        function drawHandles(){
            var x=insertImgObj.x, y=insertImgObj.y;
            var hs=7; // handle square half-size
            var corners=[{x:x,y:y},{x:x+iw,y:y},{x:x,y:y+ih},{x:x+iw,y:y+ih}];
            ctx.save();
            // Thin border around image (subtle)
            ctx.strokeStyle='rgba(255,107,53,0.5)'; ctx.lineWidth=1.5; ctx.setLineDash([]);
            ctx.strokeRect(x, y, iw, ih);
            // Corner handles
            corners.forEach(function(c){
                ctx.fillStyle='#fff';
                ctx.fillRect(c.x-hs, c.y-hs, hs*2, hs*2);
                ctx.strokeStyle='#ff6b35'; ctx.lineWidth=2; ctx.setLineDash([]);
                ctx.strokeRect(c.x-hs, c.y-hs, hs*2, hs*2);
            });
            ctx.restore(); ctx.setLineDash([]);
        }
        if(h.length>0){
            var base=new Image(); base.onload=function(){
                ctx.clearRect(0,0,dc.width,dc.height);
                ctx.drawImage(base,0,0);
                ctx.drawImage(insertImgObj.img, insertImgObj.x, insertImgObj.y, iw, ih);
                drawHandles();
            }; base.src=h[h.length-1];
        } else {
            ctx.clearRect(0,0,dc.width,dc.height);
            ctx.drawImage(insertImgObj.img, insertImgObj.x, insertImgObj.y, iw, ih);
            drawHandles();
        }
    }

    document.querySelectorAll('.tool-btn[data-tool]').forEach(function(b){b.addEventListener('click',function(){selTool(b.dataset.tool);});});
    var _lastLoggedTool='pen';
    function selTool(t){if(moveFloating&&t!=='hand'){clearMoveState();}currentTool=t;document.querySelectorAll('.tool-btn').forEach(function(b){b.classList.toggle('active',b.dataset.tool===t);});updateCursor();if(t!==_lastLoggedTool){logAction('tool_use','ใช้เครื่องมือ: '+t);_lastLoggedTool=t;}}
    var cp=document.getElementById('colorPicker');
    cp.addEventListener('input',function(e){currentColor=e.target.value;uSw();updateCursor();});
    document.querySelectorAll('.color-swatch').forEach(function(s){s.addEventListener('click',function(){currentColor=s.dataset.color;cp.value=currentColor;uSw();updateCursor();});});
    function uSw(){document.querySelectorAll('.color-swatch').forEach(function(s){s.classList.toggle('active',s.dataset.color===currentColor);});}
    var ss=document.getElementById('sizeSlider'),sl=document.getElementById('sizeLabel');
    ss.addEventListener('input',function(){currentSize=parseInt(ss.value);sl.textContent=currentSize;updateCursor();});
    document.getElementById('btnSizeDec').addEventListener('click',function(){currentSize=Math.max(1,currentSize-1);ss.value=currentSize;sl.textContent=currentSize;updateCursor();});
    document.getElementById('btnSizeInc').addEventListener('click',function(){currentSize=Math.min(50,currentSize+1);ss.value=currentSize;sl.textContent=currentSize;updateCursor();});
    uSw(); selTool('hand'); // initial tool = Hand

    // ★★★ STICKY COMPACT TOOLBAR ON SCROLL ★★★
    (function(){
        var toolbox=document.querySelector('.toolbox');
        var placeholder=document.createElement('div');
        placeholder.className='toolbox-placeholder';
        toolbox.parentNode.insertBefore(placeholder, toolbox.nextSibling);
        var isSticky=false;

        function checkSticky(){
            if(isSticky){
                // Check using placeholder position (original location)
                var phRect=placeholder.getBoundingClientRect();
                if(phRect.top > 64){
                    // Back to normal
                    toolbox.classList.remove('toolbox-sticky');
                    placeholder.style.display='none';
                    isSticky=false;
                }
            } else {
                var tbRect=toolbox.getBoundingClientRect();
                if(tbRect.top <= 64){
                    // Go sticky
                    placeholder.style.display='block';
                    placeholder.style.height=tbRect.height+'px';
                    toolbox.classList.add('toolbox-sticky');
                    isSticky=true;
                }
            }
        }
        window.addEventListener('scroll',checkSticky,{passive:true});
        window.addEventListener('resize',function(){
            if(isSticky){
                // Update placeholder height
                toolbox.classList.remove('toolbox-sticky');
                placeholder.style.display='none';
                isSticky=false;
                checkSticky();
            }
        });
    })();

    document.getElementById('btnUndo').addEventListener('click',undo);
    document.getElementById('btnRedo').addEventListener('click',redo);
    document.getElementById('btnClear').addEventListener('click',clearCanvas);
    document.getElementById('btnDownload').addEventListener('click',downloadAllPages);
    document.getElementById('btnSave').addEventListener('click',saveAllPages);

    // KEYBOARD — ใช้ e.code เพื่อให้ทำงานได้ทุกภาษา
    document.addEventListener('keydown',function(e){
        if(e.target.tagName==='INPUT'||e.target.tagName==='TEXTAREA')return;
        var code=e.code||'';
        if(e.ctrlKey||e.metaKey){
            switch(code){
                case'KeyZ':e.preventDefault();undo();break;
                case'KeyY':e.preventDefault();redo();break;
                case'KeyS':e.preventDefault();saveAllPages();break;
                case'KeyD':e.preventDefault();downloadAllPages();break;
                case'Equal':case'NumpadAdd':e.preventDefault();setZoom(zoomLevel+ZOOM_STEP);break;
                case'Minus':case'NumpadSubtract':e.preventDefault();setZoom(zoomLevel-ZOOM_STEP);break;
                case'Digit0':case'Numpad0':e.preventDefault();setZoom(100);break;
            }
        } else {
            switch(code){
                case'KeyV':selTool('hand');break;
                case'KeyB':selTool('pen');break;
                case'KeyP':selTool('pencil');break;
                case'KeyM':selTool('marker');break;
                case'KeyE':selTool('eraser');break;
                case'KeyL':selTool('line');break;
                case'KeyR':selTool('rect');break;
                case'KeyC':selTool('circle');break;
                case'KeyA':selTool('arrow');break;
                case'KeyT':selTool('text');break;
                case'KeyH':selTool('hand');break;
                case'KeyI':document.getElementById('imageFileInput').click();break;
                case'Delete':clearCanvas();break;
                case'Escape':if(imgPlacing){imgPlacing=false;imgDragging=false;imgResizing=false;imgResizeCorner=null;imgResizeOrigin=null;insertImgObj=null;var hh=getH();if(hh.length>0)restoreState(hh[hh.length-1]);updateCursor();}else if(moveFloating){var h=getH();if(h.length>0)restoreState(h[h.length-1]);clearMoveState();}break;
                case'Enter':case'NumpadEnter':if(imgPlacing&&insertImgObj){commitInsertImage();}break;
                case'BracketLeft':currentSize=Math.max(1,currentSize-1);ss.value=currentSize;sl.textContent=currentSize;updateCursor();break;
                case'BracketRight':currentSize=Math.min(50,currentSize+1);ss.value=currentSize;sl.textContent=currentSize;updateCursor();break;
                case'Equal':case'NumpadAdd':currentSize=Math.min(50,currentSize+1);ss.value=currentSize;sl.textContent=currentSize;updateCursor();break;
                case'Minus':case'NumpadSubtract':currentSize=Math.max(1,currentSize-1);ss.value=currentSize;sl.textContent=currentSize;updateCursor();break;
                case'Digit0':case'Numpad0':setZoom(100);break;
                <?php if($isPDF):?>
                case'ArrowLeft':e.preventDefault();document.getElementById('btnPrevPage').click();break;
                case'ArrowRight':e.preventDefault();document.getElementById('btnNextPage').click();break;
                <?php endif;?>
            }
        }
    });

    // ★★★ EXPOSE global functions for unsaved-changes warning ★★★
    window.__hasDirtyPages = function(){
        for(var k in dirtyPages){ if(dirtyPages.hasOwnProperty(k) && dirtyPages[k]) return true; }
        return false;
    };
    window.__getDirtyCount = function(){
        var c=0;
        for(var k in dirtyPages){ if(dirtyPages.hasOwnProperty(k) && dirtyPages[k]) c++; }
        return c;
    };
})();

// PROFILE/THEME
var pw=document.getElementById('profileWrapper'),pt=document.getElementById('profileTrigger'),ov=document.getElementById('dropdownOverlay');
pt.addEventListener('click',function(e){e.stopPropagation();pw.classList.toggle('open');ov.classList.toggle('active');});
ov.addEventListener('click',cDD);document.addEventListener('keydown',function(e){if(e.key==='Escape')cDD();});
function cDD(){pw.classList.remove('open');ov.classList.remove('active');}
var tt=document.getElementById('themeToggle'),ti2=document.getElementById('themeMenuIcon'),tx=document.getElementById('themeMenuText'),ht=document.documentElement;
function lT(){aT(localStorage.getItem('lolane_theme')||'light');}
function aT(t){ht.setAttribute('data-theme',t);localStorage.setItem('lolane_theme',t);tt.checked=(t==='dark');ti2.textContent=t==='dark'?'☀️':'🌙';tx.textContent=t==='dark'?'Light Mode':'Dark Mode';}
tt.addEventListener('change',function(){aT(tt.checked?'dark':'light');});
function toggleThemeFromMenu(){aT(ht.getAttribute('data-theme')==='dark'?'light':'dark');}
lT();

// STATUS CHANGER
(function(){
    var sw2=document.getElementById('statusWrapper');
    if(!sw2) return; // ไม่มีสิทธิ์เปลี่ยนสถานะ
    var st=document.getElementById('statusTrigger'),sd=document.getElementById('statusDropdown'),so=document.getElementById('statusOverlay');
    var SID=<?=$fileId?>;
    st.addEventListener('click',function(e){e.stopPropagation();sw2.classList.toggle('open');so.classList.toggle('active');});
    so.addEventListener('click',function(){sw2.classList.remove('open');so.classList.remove('active');});
    sd.querySelectorAll('.status-option').forEach(function(opt){
        opt.addEventListener('click',function(){
            var ns=opt.dataset.status,ni=opt.dataset.icon,nc=opt.dataset['class'];
            sw2.classList.remove('open');so.classList.remove('active');
            document.getElementById('statusIcon').textContent=ni;
            document.getElementById('statusText').textContent=ns;
            st.className='status-trigger '+nc;
            sd.querySelectorAll('.status-option').forEach(function(o){o.classList.remove('active');});
            opt.classList.add('active');
            fetch('update_status.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:SID,status:ns})})
            .then(function(r){return r.json();})
            .then(function(d){if(d.success)showToast('✅ เปลี่ยนสถานะเป็น "'+ns+'" แล้ว','success');else showToast('❌ '+(d.error||'ไม่สำเร็จ'),'error');})
            .catch(function(){showToast('❌ เกิดข้อผิดพลาด','error');});
        });
    });
})();
function showToast(m,t){var toast=document.getElementById('toast');toast.textContent=m;toast.className='toast '+(t||'success');setTimeout(function(){toast.classList.add('show');},10);setTimeout(function(){toast.classList.remove('show');},3000);}

// ─── Reviewer personal status ────────────────────────────────────────────────
(function(){
    var RIB_FILE_ID = <?= $fileId ?>;
    var panel   = document.getElementById('ribStatusPanel');
    var trigger = document.getElementById('ribMyStatusBtn');
    if (!panel) return; // current user is not a reviewer

    var _ribSelected = null; // current selected status value in panel

    // Pre-set active button from PHP initial state
    panel.querySelectorAll('.rib-sopt').forEach(function(b){ if(b.classList.contains('active')) _ribSelected = b.dataset.val; });

    window.toggleRibPanel = function() {
        panel.classList.toggle('open');
    };

    window.selectRibStatus = function(btn) {
        panel.querySelectorAll('.rib-sopt').forEach(function(b){ b.classList.remove('active'); });
        btn.classList.add('active');
        _ribSelected = btn.dataset.val;
    };

    window.saveMyReviewStatus = function() {
        var desc = (document.getElementById('ribDescTa') || {}).value || '';
        var status = _ribSelected || '';
        fetch('reviewer_api.php?action=set_review_status', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ upload_id: RIB_FILE_ID, rv_status: status, rv_description: desc })
        })
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.success) {
                // Update badge
                var badge = document.getElementById('ribMyStatusBadge');
                if (status) {
                    var icons = {'ผ่าน':'✅ ','แก้ไข':'✏️ ','ไม่ผ่าน':'❌ '};
                    var classes = {'ผ่าน':'approved','แก้ไข':'reviewing','ไม่ผ่าน':'rejected'};
                    if (!badge) {
                        badge = document.createElement('span');
                        badge.id = 'ribMyStatusBadge';
                        badge.className = 'rib-rstatus';
                        // insert before remove/trigger buttons
                        var row = document.querySelector('#ribStatusPanel').previousElementSibling;
                        if (row) row.appendChild(badge);
                    }
                    badge.className = 'rib-rstatus ' + (classes[status] || '');
                    badge.textContent = (icons[status] || '') + status;
                } else if (badge) {
                    badge.remove();
                }
                // Update description line
                var descEl = document.getElementById('ribMyDesc');
                if (desc) {
                    if (!descEl) {
                        descEl = document.createElement('div');
                        descEl.id = 'ribMyDesc';
                        descEl.className = 'rib-rv-desc';
                        panel.parentNode.insertBefore(descEl, panel);
                    }
                    descEl.textContent = '📝 ' + desc;
                } else if (descEl) {
                    descEl.remove();
                }
                panel.classList.remove('open');
                showToast('✅ บันทึกสถานะแล้ว', 'success');
            } else {
                showToast('❌ ' + (d.error || 'ไม่สำเร็จ'), 'error');
            }
        })
        .catch(function(){ showToast('❌ เกิดข้อผิดพลาด', 'error'); });
    };
})();
// ─────────────────────────────────────────────────────────────────────────────
</script>
<script>
// ================================
// ★★★ COMMENT SYSTEM ★★★
// ================================
(function(){
    var FILE_ID = <?= $fileId ?>;
    var CURRENT_USER_ID = <?= $userId ?>;
    var commentInput = document.getElementById('commentInput');
    var btnPost = document.getElementById('btnPostComment');
    var commentsList = document.getElementById('commentsList');
    var commentsLoading = document.getElementById('commentsLoading');
    var commentCount = document.getElementById('commentCount');

    // ★ Attachment state
    var attachDataURL = null;  // base64 data-URI of selected image
    var attachInput = document.getElementById('commentAttachInput');
    var attachPreviewBox = document.getElementById('commentAttachPreview');
    var btnAttach = document.getElementById('btnAttachComment');

    btnAttach.addEventListener('click', function() {
        attachInput.click();
    });

    attachInput.addEventListener('change', function(e) {
        var file = e.target.files[0];
        if (!file) return;
        // 5 MB limit
        if (file.size > 5 * 1024 * 1024) {
            alert('❌ รูปภาพใหญ่เกินไป (สูงสุด 5 MB)');
            attachInput.value = '';
            return;
        }
        var reader = new FileReader();
        reader.onload = function(ev) {
            attachDataURL = ev.target.result;
            renderAttachPreview();
            updatePostBtn();
        };
        reader.readAsDataURL(file);
        e.target.value = '';
    });

    function renderAttachPreview() {
        if (!attachDataURL) { attachPreviewBox.innerHTML = ''; return; }
        attachPreviewBox.innerHTML =
            '<div class="comment-attach-preview">' +
            '<img src="' + attachDataURL + '" alt="แนบรูป">' +
            '<button class="comment-attach-remove" title="ลบรูป" id="btnRemoveAttach">×</button>' +
            '</div>';
        document.getElementById('btnRemoveAttach').addEventListener('click', function() {
            attachDataURL = null;
            renderAttachPreview();
            updatePostBtn();
        });
    }

    function updatePostBtn() {
        btnPost.disabled = !commentInput.value.trim() && !attachDataURL;
    }

    // ★ Enable/disable ปุ่มโพสต์
    commentInput.addEventListener('input', updatePostBtn);

    // ★ Paste image into main comment textarea
    commentInput.addEventListener('paste', function(e) {
        var items = (e.clipboardData || e.originalEvent.clipboardData).items;
        for (var i = 0; i < items.length; i++) {
            if (items[i].type.indexOf('image') !== -1) {
                e.preventDefault();
                var file = items[i].getAsFile();
                if (!file) return;
                if (file.size > 5 * 1024 * 1024) { alert('❌ รูปภาพใหญ่เกินไป (สูงสุด 5 MB)'); return; }
                var reader = new FileReader();
                reader.onload = function(ev) {
                    attachDataURL = ev.target.result;
                    renderAttachPreview();
                    updatePostBtn();
                };
                reader.readAsDataURL(file);
                return;
            }
        }
    });

    // ★ โพสต์คอมเมนต์
    btnPost.addEventListener('click', function() {
        var content = commentInput.value.trim();
        if (!content && !attachDataURL) return;
        btnPost.disabled = true;
        btnPost.textContent = '⏳ กำลังโพสต์...';

        fetch('comment_api.php?action=post', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ upload_id: FILE_ID, parent_id: null, content: content, attachment: attachDataURL })
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.success) {
                commentInput.value = '';
                attachDataURL = null;
                renderAttachPreview();
                loadComments();
            } else {
                alert('❌ ' + (d.error || 'โพสต์ไม่สำเร็จ'));
            }
            btnPost.disabled = false;
            btnPost.textContent = '💬 โพสต์คอมเมนต์';
        })
        .catch(function() {
            alert('❌ เกิดข้อผิดพลาด');
            btnPost.disabled = false;
            btnPost.textContent = '💬 โพสต์คอมเมนต์';
        });
    });

    // ★ Ctrl+Enter ส่งคอมเมนต์
    commentInput.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            e.preventDefault();
            btnPost.click();
        }
    });

    // ★ โหลดคอมเมนต์
    function loadComments() {
        fetch('comment_api.php?action=list&upload_id=' + FILE_ID)
        .then(function(r) { return r.json(); })
        .then(function(d) {
            commentsLoading.style.display = 'none';
            if (d.success) {
                renderComments(d.comments);
            }
        })
        .catch(function() {
            commentsLoading.textContent = '❌ โหลดคอมเมนต์ไม่สำเร็จ';
        });
    }

    // ★ Render คอมเมนต์
    function renderComments(comments) {
        // แยก main vs replies
        var mainComments = [];
        var repliesMap = {}; // parent_id → [replies]

        comments.forEach(function(c) {
            if (c.parent_id) {
                if (!repliesMap[c.parent_id]) repliesMap[c.parent_id] = [];
                repliesMap[c.parent_id].push(c);
            } else {
                mainComments.push(c);
            }
        });

        commentCount.textContent = comments.length;

        if (comments.length === 0) {
            commentsList.innerHTML =
                '<div class="comments-empty">' +
                '<div class="empty-icon">💬</div>' +
                '<h4>ยังไม่มีคอมเมนต์</h4>' +
                '<p>เป็นคนแรกที่แสดงความคิดเห็น!</p>' +
                '</div>';
            return;
        }

        var html = '';
        mainComments.forEach(function(c) {
            html += renderOneComment(c, false);

            // Replies
            var replies = repliesMap[c.id] || [];
            if (replies.length > 0) {
                html += '<div class="comment-replies">';
                replies.forEach(function(r) {
                    html += renderOneComment(r, true);
                });
                html += '</div>';
            }

            // Reply form
            html += '<div class="reply-form" id="replyForm_' + c.id + '">';
            html += '<div class="reply-to-label">↩️ ตอบกลับ <strong>' + esc(c.display_name) + '</strong></div>';
            html += '<textarea class="reply-textarea" id="replyInput_' + c.id + '" placeholder="เขียนตอบกลับ..." maxlength="2000"></textarea>';
            html += '<div id="replyAttachPreview_' + c.id + '"></div>';
            html += '<div class="reply-actions">';
            html += '<input type="file" id="replyAttachInput_' + c.id + '" accept="image/*" style="display:none">';
            html += '<button type="button" class="btn-attach" style="padding:5px 10px;font-size:12px" onclick="triggerReplyAttach(' + c.id + ')">📎 แนบรูป</button>';
            html += '<button class="btn-comment btn-comment-cancel" onclick="closeReply(' + c.id + ')">\u0e22\u0e01\u0e40\u0e25\u0e34\u0e01</button>';
            html += '<button class="btn-comment btn-comment-submit" onclick="submitReply(' + c.id + ')">↩️ ตอบกลับ</button>';
            html += '</div></div>';
        });

        commentsList.innerHTML = html;

        // wire up reply attachment inputs after render
        mainComments.forEach(function(c) {
            var inp = document.getElementById('replyAttachInput_' + c.id);
            if (inp) {
                inp.addEventListener('change', function(e) {
                    var file = e.target.files[0]; if (!file) return;
                    if (file.size > 5 * 1024 * 1024) { alert('❌ รูปภาพใหญ่เกินไป (สูงสุด 5 MB)'); e.target.value=''; return; }
                    var reader = new FileReader();
                    reader.onload = function(ev) {
                        inp._attachDataURL = ev.target.result;
                        var box = document.getElementById('replyAttachPreview_' + c.id);
                        if (box) box.innerHTML = '<div class="comment-attach-preview"><img src="' + ev.target.result + '" alt="แนบรูป"><button class="comment-attach-remove" onclick="clearReplyAttach(' + c.id + ')">\xd7</button></div>';
                    };
                    reader.readAsDataURL(file);
                    e.target.value = '';
                });
            }

            // ★ Paste image into reply textarea
            var replyTa = document.getElementById('replyInput_' + c.id);
            if (replyTa) {
                replyTa.addEventListener('paste', function(e) {
                    var items = (e.clipboardData || e.originalEvent.clipboardData).items;
                    for (var i = 0; i < items.length; i++) {
                        if (items[i].type.indexOf('image') !== -1) {
                            e.preventDefault();
                            var file = items[i].getAsFile();
                            if (!file) return;
                            if (file.size > 5 * 1024 * 1024) { alert('❌ รูปภาพใหญ่เกินไป (สูงสุด 5 MB)'); return; }
                            var cid = c.id;
                            var reader = new FileReader();
                            reader.onload = function(ev) {
                                var attachInp = document.getElementById('replyAttachInput_' + cid);
                                if (attachInp) attachInp._attachDataURL = ev.target.result;
                                var box = document.getElementById('replyAttachPreview_' + cid);
                                if (box) box.innerHTML = '<div class="comment-attach-preview"><img src="' + ev.target.result + '" alt="แนบรูป"><button class="comment-attach-remove" onclick="clearReplyAttach(' + cid + ')">\xd7</button></div>';
                            };
                            reader.readAsDataURL(file);
                            return;
                        }
                    }
                });
            }
        });
    }   // end renderComments

    function renderOneComment(c, isReply) {
        var avatar;
        if (c.avatar && c.avatar.length > 20) {
            var src;
            if (c.avatar.indexOf('URL:') === 0) {
                src = c.avatar.substring(4);
            } else if (c.avatar.indexOf('data:') === 0) {
                src = c.avatar;
            } else {
                var mime = 'image/jpeg';
                if (c.avatar.indexOf('iVBOR') === 0) mime = 'image/png';
                else if (c.avatar.indexOf('/9j/') === 0) mime = 'image/jpeg';
                else if (c.avatar.indexOf('R0lG') === 0) mime = 'image/gif';
                else if (c.avatar.indexOf('UklG') === 0) mime = 'image/webp';
                src = 'data:' + mime + ';base64,' + c.avatar;
            }
            avatar = '<img src="' + src + '" alt="" onerror="this.parentElement.textContent=\'' + esc(c.initial || '?') + '\'">';
        } else {
            avatar = esc(c.initial || '?');
        }

        var isOwner = c.user_id === CURRENT_USER_ID;
        var ownerBadge = isOwner ? '<span class="comment-owner-badge">คุณ</span>' : '';
        var timeAgo = formatTimeAgo(c.created_at);
        var likedClass = c.liked ? ' liked' : '';
        var likeIcon = c.liked ? '❤️' : '🤍';
        var likeText = c.likes > 0 ? '<span class="like-count">' + c.likes + '</span>' : '';

        // attachment thumbnail
        var attachHtml = '';
        if (c.attachment) {
            attachHtml = '<div class="comment-attachment"><img src="' + c.attachment + '" alt="แนบรูป" onclick="openLightbox(\'' + escAttr(c.attachment) + '\')" loading="lazy"></div>';
        }

        // show content only if not just whitespace placeholder
        var contentHtml = (c.content && c.content.trim()) ? '<div class="comment-text">' + formatContent(c.content) + '</div>' : '';

        var html = '<div class="comment-item" id="comment_' + c.id + '">';
        html += '<div class="comment-main">';
        html += '<div class="comment-avatar">' + avatar + '</div>';
        html += '<div class="comment-body">';
        html += '<div class="comment-meta">';
        html += '<span class="comment-author">' + esc(c.display_name) + '</span>';
        html += ownerBadge;
        html += '<span class="comment-time" title="' + esc(c.created_at) + '">' + timeAgo + '</span>';
        html += '</div>';
        html += contentHtml;
        html += attachHtml;
        html += '<div class="comment-actions">';
        html += '<button class="comment-action-btn' + likedClass + '" onclick="toggleLike(' + c.id + ', this)"><span class="act-icon">' + likeIcon + '</span> ถูกใจ ' + likeText + '</button>';
        if (!isReply) {
            html += '<button class="comment-action-btn" onclick="openReply(' + c.id + ')"><span class="act-icon">↩️</span> ตอบกลับ</button>';
        }
        if (isOwner) {
            html += '<button class="comment-action-btn" onclick="deleteComment(' + c.id + ')" style="color:#e74c3c"><span class="act-icon">🗑️</span> ลบ</button>';
        }
        html += '</div></div></div></div>';
        return html;
    }

    // ★ Toggle Like
    window.toggleLike = function(commentId, btn) {
        fetch('comment_api.php?action=like', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ comment_id: commentId })
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.success) {
                var icon = d.liked ? '❤️' : '🤍';
                var countHtml = d.likes > 0 ? '<span class="like-count">' + d.likes + '</span>' : '';
                btn.innerHTML = '<span class="act-icon">' + icon + '</span> ถูกใจ ' + countHtml;
                btn.classList.toggle('liked', d.liked);
            }
        });
    };

    // ★ Reply
    window.openReply = function(parentId) {
        document.querySelectorAll('.reply-form').forEach(function(f) { f.classList.remove('active'); });
        var form = document.getElementById('replyForm_' + parentId);
        if (form) {
            form.classList.add('active');
            var input = document.getElementById('replyInput_' + parentId);
            if (input) input.focus();
        }
    };

    window.closeReply = function(parentId) {
        var form = document.getElementById('replyForm_' + parentId);
        if (form) form.classList.remove('active');
    };

    window.submitReply = function(parentId) {
        var input = document.getElementById('replyInput_' + parentId);
        var content = input ? input.value.trim() : '';
        var attachInp = document.getElementById('replyAttachInput_' + parentId);
        var replyAttach = attachInp ? (attachInp._attachDataURL || null) : null;
        if (!content && !replyAttach) return;

        fetch('comment_api.php?action=post', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ upload_id: FILE_ID, parent_id: parentId, content: content, attachment: replyAttach })
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.success) {
                closeReply(parentId);
                loadComments();
            } else {
                alert('❌ ' + (d.error || 'ตอบกลับไม่สำเร็จ'));
            }
        })
        .catch(function() { alert('❌ เกิดข้อผิดพลาด'); });
    };

    // reply attachment helpers
    window.triggerReplyAttach = function(parentId) {
        var inp = document.getElementById('replyAttachInput_' + parentId);
        if (inp) inp.click();
    };
    window.clearReplyAttach = function(parentId) {
        var inp = document.getElementById('replyAttachInput_' + parentId);
        if (inp) { inp._attachDataURL = null; }
        var box = document.getElementById('replyAttachPreview_' + parentId);
        if (box) box.innerHTML = '';
    };

    // ★ Delete
    window.deleteComment = function(commentId) {
        if (!confirm('ต้องการลบคอมเมนต์นี้?')) return;

        fetch('comment_api.php?action=delete', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ comment_id: commentId })
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.success) { loadComments(); }
            else { alert('❌ ลบไม่สำเร็จ'); }
        });
    };

    // ★ Helpers
    function esc(t) {
        var d = document.createElement('div');
        d.textContent = t || '';
        return d.innerHTML;
    }

    function escAttr(t) {
        // escape for use inside single-quoted HTML attribute
        return (t || '').replace(/'/g, '&#39;').replace(/"/g, '&quot;');
    }

    function formatContent(text) {
        if (!text || !text.trim()) return '';
        return esc(text).replace(/\n/g, '<br>');
    }

    // ★ Lightbox
    window.openLightbox = function(src) {
        var lb = document.getElementById('imgLightbox');
        var img = document.getElementById('imgLightboxImg');
        if (!lb || !img) return;
        img.src = src;
        lb.classList.add('active');
    };
    (function() {
        var lb = document.getElementById('imgLightbox');
        if (lb) lb.addEventListener('click', function() { lb.classList.remove('active'); });
    })();

    function formatTimeAgo(dateStr) {
        var date = new Date(dateStr);
        var now = new Date();
        var diff = Math.floor((now - date) / 1000);

        if (diff < 60) return 'เมื่อสักครู่';
        if (diff < 3600) return Math.floor(diff / 60) + ' นาทีที่แล้ว';
        if (diff < 86400) return Math.floor(diff / 3600) + ' ชั่วโมงที่แล้ว';
        if (diff < 604800) return Math.floor(diff / 86400) + ' วันที่แล้ว';

        // แสดงวันที่เต็ม
        var d = date.getDate();
        var m = date.getMonth() + 1;
        var y = date.getFullYear() + 543;
        var h = ('0' + date.getHours()).slice(-2);
        var min = ('0' + date.getMinutes()).slice(-2);
        return d + '/' + m + '/' + y + ' ' + h + ':' + min;
    }

    // ★ โหลดคอมเมนต์ตอนเปิดหน้า
    loadComments();
})();
</script>
<script>
// ================================
// ★★★ SEND EMAIL (OUTLOOK) ★★★
// ================================
(function(){
    var FILE_ID_EMAIL = <?= $fileId ?>;
    var overlay  = document.getElementById('emailModalOverlay');
    var btnOpen  = document.getElementById('btnOpenEmail');
    var btnClose = document.getElementById('btnCloseEmail');
    var btnCancel= document.getElementById('btnCancelEmail');
    var btnSend  = document.getElementById('btnSendEmail');
    var toInput  = document.getElementById('emailTo');
    var statusEl = document.getElementById('emailStatus');

    function openModal() {
        overlay.classList.add('active');
        statusEl.className = 'email-status';
        statusEl.textContent = '';
        btnSend.disabled = false;
        btnSend.innerHTML = '<span>📤</span> ส่งอีเมล';
        if (toInput) toInput.focus();
    }
    function closeModal() { overlay.classList.remove('active'); }

    if (btnOpen)   btnOpen.addEventListener('click', openModal);
    if (btnClose)  btnClose.addEventListener('click', closeModal);
    if (btnCancel) btnCancel.addEventListener('click', closeModal);
    overlay.addEventListener('click', function(e){ if(e.target===overlay) closeModal(); });

    window.emailAddRecipient = function(email) {
        var cur = toInput.value.trim();
        if (!cur) { toInput.value = email; return; }
        // ถ้ายังไม่มีค่านี้อยู่
        if (cur.split(/[,;]+/).map(function(s){return s.trim();}).indexOf(email) === -1) {
            toInput.value = cur.replace(/[,;\s]+$/, '') + ', ' + email;
        }
    };

    if (btnSend) btnSend.addEventListener('click', function() {
        var to      = document.getElementById('emailTo').value.trim();
        var subject = document.getElementById('emailSubject').value.trim();
        var body    = document.getElementById('emailBody').value.trim();

        statusEl.className = 'email-status';
        statusEl.textContent = '';

        if (!to)      { showStatus('err', '⚠️ กรุณากรอกอีเมลผู้รับ'); toInput.focus(); return; }
        if (!subject) { showStatus('err', '⚠️ กรุณากรอกหัวข้ออีเมล'); return; }

        btnSend.disabled = true;
        btnSend.innerHTML = '<span>⏳</span> กำลังส่ง...';

        fetch('send_email_api.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({ to: to, subject: subject, body: body, file_id: FILE_ID_EMAIL })
        })
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.success) {
                showStatus('ok', '✅ ส่งอีเมลสำเร็จ!');
                btnSend.innerHTML = '<span>✅</span> ส่งแล้ว';
                setTimeout(closeModal, 2000);
            } else if (d.error === 'session_expired') {
                showStatus('err', '⚠️ Session หมดอายุ — กรุณา Login ใหม่เพื่อให้สิทธิ์ Mail.Send');
                btnSend.disabled = false;
                btnSend.innerHTML = '<span>📤</span> ส่งอีเมล';
            } else if (d.error === 'no_permission') {
                showStatus('err', '⚠️ ไม่มีสิทธิ์ส่งเมล — กรุณา Logout แล้ว Login ใหม่เพื่ออนุมัติ Mail.Send');
                btnSend.disabled = false;
                btnSend.innerHTML = '<span>📤</span> ส่งอีเมล';
            } else {
                showStatus('err', '❌ ' + (d.error || 'เกิดข้อผิดพลาด'));
                btnSend.disabled = false;
                btnSend.innerHTML = '<span>📤</span> ส่งอีเมล';
            }
        })
        .catch(function(){
            showStatus('err', '❌ เกิดข้อผิดพลาดในการเชื่อมต่อ');
            btnSend.disabled = false;
            btnSend.innerHTML = '<span>📤</span> ส่งอีเมล';
        });
    });

    function showStatus(type, msg) {
        statusEl.className = 'email-status ' + type;
        statusEl.textContent = msg;
    }
})();
</script>
<script>
// ================================
// ★★★ UNSAVED CHANGES WARNING ★★★
// ================================
(function(){
    var unsavedOverlay = document.getElementById('unsavedOverlay');
    var unsavedInfo    = document.getElementById('unsavedInfo');
    var btnStay        = document.getElementById('btnStay');
    var btnLeave       = document.getElementById('btnLeave');
    var pendingHref    = null;  // URL ที่จะไปถ้ากด "ออก"

    // ★ ตรวจว่ามีการวาดที่ยังไม่บันทึกหรือไม่
    function hasUnsavedChanges() {
        // dirtyPages อยู่ใน IIFE ของ drawing → ต้อง expose ออกมา
        // เราใช้ global flag แทน
        return window.__hasDirtyPages && window.__hasDirtyPages();
    }

    // ★ แสดง modal
    function showUnsavedWarning(href) {
        pendingHref = href;

        // แสดงจำนวนหน้าที่ยังไม่บันทึก
        var count = window.__getDirtyCount ? window.__getDirtyCount() : 0;
        unsavedInfo.textContent = '📝 มี ' + count + ' หน้าที่ยังไม่ได้บันทึก';

        unsavedOverlay.classList.add('active');
    }

    // ★ ปิด modal
    function hideUnsavedWarning() {
        unsavedOverlay.classList.remove('active');
        pendingHref = null;
    }

    // ★ กด "อยู่ต่อ"
    btnStay.addEventListener('click', function() {
        hideUnsavedWarning();
    });

    // ★ กด "ออก"
    btnLeave.addEventListener('click', function() {
        // ปิด beforeunload ก่อน เพื่อไม่ให้โดน block ซ้ำ
        window.__skipBeforeUnload = true;
        if (pendingHref) {
            window.location.href = pendingHref;
        } else {
            history.back();
        }
    });

    // ★ กด Escape ปิด modal
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && unsavedOverlay.classList.contains('active')) {
            hideUnsavedWarning();
        }
    });

    // ★ คลิก overlay ปิด modal
    unsavedOverlay.addEventListener('click', function(e) {
        if (e.target === unsavedOverlay) {
            hideUnsavedWarning();
        }
    });

    // ★ Intercept ลิงก์ทั้งหมดในหน้า (ปุ่มกลับ, navbar links, etc.)
    document.addEventListener('click', function(e) {
        var link = e.target.closest('a[href]');
        if (!link) return;

        // ข้าม anchor links, javascript:void, # links
        var href = link.getAttribute('href');
        if (!href || href === '#' || href.indexOf('javascript:') === 0) return;

        // ★ ตรวจว่ามี unsaved changes
        if (hasUnsavedChanges()) {
            e.preventDefault();
            e.stopPropagation();
            showUnsavedWarning(href);
        }
    }, true); // ★ ใช้ capture phase เพื่อ intercept ก่อน

    // ★ Intercept browser back/forward + close tab
    window.addEventListener('beforeunload', function(e) {
        if (window.__skipBeforeUnload) return;
        if (hasUnsavedChanges()) {
            e.preventDefault();
            e.returnValue = 'คุณมีการเปลี่ยนแปลงที่ยังไม่ได้บันทึก';
            return e.returnValue;
        }
    });

    // ★ Intercept browser back button (popstate)
    // Push extra state เพื่อดัก back button
    if (history.pushState) {
        history.pushState(null, '', window.location.href);
        window.addEventListener('popstate', function(e) {
            if (hasUnsavedChanges()) {
                // Push state กลับเพื่อไม่ให้ออก
                history.pushState(null, '', window.location.href);
                showUnsavedWarning(document.querySelector('.btn-back')?.getAttribute('href') || 'select_file.php');
            }
        });
    }
})();
</script>
 <div class="toast" id="toast"></div>

<?php if ($canEdit): ?>
<script>
// ================================
// ★★★ REVIEWER MANAGEMENT (Add / Remove) ★★★
// ================================
(function(){
    var FILE_ID = <?= $fileId ?>;
    var IS_OWNER = <?= $isOwner ? 'true' : 'false' ?>;
    var CURRENT_USER_ID_RV = <?= $userId ?>;

    // ─── ลบผู้ตรวจ ───────────────────────────────────────────
    window.removeReviewer = function(reviewerUserId, reviewerName, btnEl) {
        if (!confirm('ต้องการลบ "' + reviewerName + '" ออกจากผู้ตรวจงานหรือไม่?')) return;
        btnEl.disabled = true;
        fetch('reviewer_api.php?action=remove', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ upload_id: FILE_ID, user_id: reviewerUserId })
        })
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.success) {
                var tag = document.querySelector('.rib-person[data-reviewer-id="' + reviewerUserId + '"]');
                if (tag) tag.remove();
                showToast('✅ ลบ "' + reviewerName + '" ออกจากผู้ตรวจแล้ว', 'success');
            } else {
                btnEl.disabled = false;
                showToast('❌ ' + (d.error || 'ไม่สำเร็จ'), 'error');
            }
        })
        .catch(function(){
            btnEl.disabled = false;
            showToast('❌ เกิดข้อผิดพลาด', 'error');
        });
    };

    // ─── โมดอล เพิ่มผู้ตรวจ ──────────────────────────────────
    var btnAdd = document.getElementById('btnAddReviewer');
    if (!btnAdd) return;

    var overlay = document.getElementById('reviewerModalOverlay');
    var listEl  = document.getElementById('reviewerModalList');
    var searchEl = document.getElementById('reviewerModalSearch');
    var btnConfirm = document.getElementById('reviewerModalConfirm');
    var btnCancel  = document.getElementById('reviewerModalCancel');

    var allUsers = [];
    var selectedIds = {}; // userId => userData

    // เปิดโมดอล
    btnAdd.addEventListener('click', function(){
        selectedIds = {};
        searchEl.value = '';
        overlay.classList.add('active');
        loadUsers('');
        searchEl.focus();
    });

    // ปิดโมดอล
    btnCancel.addEventListener('click', closeModal);
    overlay.addEventListener('click', function(e){
        if (e.target === overlay) closeModal();
    });
    document.addEventListener('keydown', function(e){
        if (e.key === 'Escape' && overlay.classList.contains('active')) closeModal();
    });

    function closeModal(){
        overlay.classList.remove('active');
    }

    // ค้นหา users
    var searchTimer = null;
    searchEl.addEventListener('input', function(){
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function(){ loadUsers(searchEl.value.trim()); }, 300);
    });

    function loadUsers(q) {
        listEl.innerHTML = '<div class="reviewer-modal-empty">⏳ กำลังโหลด...</div>';
        var url = 'reviewer_api.php?action=users&upload_id=' + FILE_ID + (q ? '&q=' + encodeURIComponent(q) : '');
        fetch(url)
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.success) {
                allUsers = d.users;
                renderUserList(allUsers);
            } else {
                listEl.innerHTML = '<div class="reviewer-modal-empty">❌ โหลดไม่สำเร็จ</div>';
            }
        })
        .catch(function(){
            listEl.innerHTML = '<div class="reviewer-modal-empty">❌ เกิดข้อผิดพลาด</div>';
        });
    }

    function renderUserList(users) {
        if (users.length === 0) {
            listEl.innerHTML = '<div class="reviewer-modal-empty">🔍 ไม่พบผู้ใช้</div>';
            return;
        }
        var html = '';
        users.forEach(function(u){
            var alreadyAdded = u.assigned;
            var isSelected = !!selectedIds[u.id];
            var cls = '';
            if (alreadyAdded) cls += ' already-added';
            html += '<div class="reviewer-modal-item' + cls + '" data-uid="' + u.id + '"'
                  + (alreadyAdded ? ' title="เพิ่มเป็นผู้ตรวจแล้ว"' : '') + '>';
            html += '<div class="reviewer-modal-avatar">' + escHtmlRv(u.initial) + '</div>';
            html += '<div class="reviewer-modal-info">';
            html += '<div class="reviewer-modal-name">' + escHtmlRv(u.display_name) + '</div>';
            if (u.department) html += '<div class="reviewer-modal-dept">' + escHtmlRv(u.department) + '</div>';
            html += '</div>';
            if (alreadyAdded) {
                html += '<span class="reviewer-modal-check">✓</span>';
            } else if (isSelected) {
                html += '<span class="reviewer-modal-check">☑</span>';
            }
            html += '</div>';
        });
        listEl.innerHTML = html;

        // Click handler
        listEl.querySelectorAll('.reviewer-modal-item:not(.already-added)').forEach(function(item){
            item.addEventListener('click', function(){
                var uid = parseInt(item.dataset.uid);
                var user = allUsers.find(function(u){ return u.id === uid; });
                if (!user) return;
                if (selectedIds[uid]) {
                    delete selectedIds[uid];
                    item.querySelector('.reviewer-modal-check').remove();
                } else {
                    selectedIds[uid] = user;
                    var ck = document.createElement('span');
                    ck.className = 'reviewer-modal-check';
                    ck.textContent = '☑';
                    item.appendChild(ck);
                }
            });
        });
    }

    // ยืนยันเพิ่ม
    btnConfirm.addEventListener('click', function(){
        var ids = Object.keys(selectedIds);
        if (ids.length === 0) {
            showToast('⚠️ กรุณาเลือกผู้ตรวจก่อน', 'error');
            return;
        }
        btnConfirm.disabled = true;
        btnConfirm.textContent = '⏳ กำลังเพิ่ม...';

        var queue = ids.slice();
        var added = [];
        var failed = 0;

        function next(){
            if (queue.length === 0) {
                btnConfirm.disabled = false;
                btnConfirm.textContent = '✓ เพิ่มผู้ตรวจที่เลือก';
                closeModal();
                if (added.length > 0) {
                    // เพิ่ม tags ลงใน reviewer bar
                    var container = document.getElementById('reviewerTagsContainer');
                    added.forEach(function(u){
                        var tag = document.createElement('span');
                        tag.className = 'rib-person';
                        tag.dataset.reviewerId = u.user_id;
                        tag.title = u.email || '';
                        var initial = (u.display_name || '?').charAt(0);
                        var inner = '<span class="rib-avatar">' + escHtmlRv(initial) + '</span>'
                                  + escHtmlRv(u.display_name);
                        if (u.department) inner += ' <span class="rib-dept">(' + escHtmlRv(u.department) + ')</span>';
                        // เพิ่มผู้ตรวจนี้ → assigned_by = current user → ผู้ตรวจปัจจุบันสามารถลบได้
                        inner += '<button class="rib-remove-btn"'
                               + ' data-reviewer-id="' + u.user_id + '"'
                               + ' data-name="' + escAttr(u.display_name) + '"'
                               + ' title="ลบ ' + escAttr(u.display_name) + ' ออกจากผู้ตรวจ"'
                               + ' onclick="removeReviewer(' + u.user_id + ',\'' + escAttr(u.display_name) + '\',this)">×</button>';
                        tag.innerHTML = inner;
                        container.appendChild(tag);
                    });
                    showToast('✅ เพิ่มผู้ตรวจ ' + added.length + ' คน', 'success');
                }
                if (failed > 0) showToast('⚠️ เพิ่มไม่สำเร็จ ' + failed + ' คน', 'error');
                return;
            }
            var uid = parseInt(queue.shift());
            fetch('reviewer_api.php?action=add', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ upload_id: FILE_ID, user_id: uid })
            })
            .then(function(r){ return r.json(); })
            .then(function(d){
                if (d.success && d.user) {
                    added.push(d.user);
                } else {
                    failed++;
                }
                next();
            })
            .catch(function(){ failed++; next(); });
        }
        next();
    });

    function escHtmlRv(t) {
        var d = document.createElement('div');
        d.textContent = t || '';
        return d.innerHTML;
    }
    function escAttr(t) {
        return (t || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
    }
})();
</script>
<?php endif; ?>
</body>
</html>