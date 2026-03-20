<?php
/**
 * reviewer_api.php
 * API สำหรับจัดการผู้ตรวจงาน (file reviewers)
 *
 * GET  ?action=list&upload_id=X     → ดึงรายชื่อผู้ตรวจของไฟล์
 * GET  ?action=users&q=...          → ค้นหา users สำหรับ autocomplete
 * POST ?action=assign               → มอบหมายผู้ตรวจ  { upload_id, user_ids: [...] }
 * POST ?action=remove               → ลบผู้ตรวจ        { upload_id, user_id }
 */
require_once '../config.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

$action   = $_GET['action'] ?? $_POST['action'] ?? '';
$userId   = $_SESSION['user_id'];

// ─── GET: ค้นหา users สำหรับ autocomplete ─────────────
if ($action === 'users' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $q = trim($_GET['q'] ?? '');
    $uploadId = intval($_GET['upload_id'] ?? 0);

    if (mb_strlen($q) < 1) {
        // ถ้าไม่มีคำค้น → ส่งลิสต์ทั้งหมด (ไม่รวมตัวเอง)
        $stmt = $pdo->prepare(
            "SELECT id, display_name, email, department, avatar
             FROM users
             WHERE id != :uid
             ORDER BY display_name ASC
             LIMIT 50"
        );
        $stmt->execute([':uid' => $userId]);
    } else {
        $stmt = $pdo->prepare(
            "SELECT id, display_name, email, department, avatar
             FROM users
             WHERE id != :uid
               AND (display_name LIKE :q1 OR email LIKE :q2 OR first_name LIKE :q3 OR last_name LIKE :q4)
             ORDER BY display_name ASC
             LIMIT 20"
        );
        $like = "%{$q}%";
        $stmt->execute([':uid' => $userId, ':q1' => $like, ':q2' => $like, ':q3' => $like, ':q4' => $like]);
    }
    $users = $stmt->fetchAll();

    // ถ้ามี upload_id → mark ว่าใครถูกเลือกแล้ว
    $assignedIds = [];
    if ($uploadId > 0) {
        $aStmt = $pdo->prepare("SELECT user_id FROM file_reviewers WHERE upload_id = :uid");
        $aStmt->execute([':uid' => $uploadId]);
        $assignedIds = $aStmt->fetchAll(PDO::FETCH_COLUMN);
    }

    $result = [];
    foreach ($users as $u) {
        $result[] = [
            'id'           => (int)$u['id'],
            'display_name' => $u['display_name'],
            'email'        => $u['email'],
            'department'   => $u['department'] ?? '',
            'has_avatar'   => !empty($u['avatar']),
            'initial'      => mb_substr($u['display_name'] ?? '?', 0, 1),
            'assigned'     => in_array($u['id'], $assignedIds),
        ];
    }
    echo json_encode(['success' => true, 'users' => $result]);
    exit;
}

// ─── GET: ดึงรายชื่อผู้ตรวจของไฟล์ ─────────────
if ($action === 'list' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $uploadId = intval($_GET['upload_id'] ?? 0);
    if (!$uploadId) {
        echo json_encode(['success' => false, 'error' => 'ไม่ระบุ upload_id']);
        exit;
    }

    $stmt = $pdo->prepare(
        "SELECT fr.id, fr.user_id, fr.assigned_at, fr.assigned_by,
                u.display_name, u.email, u.department, u.avatar
         FROM file_reviewers fr
         JOIN users u ON fr.user_id = u.id
         WHERE fr.upload_id = :uid
         ORDER BY fr.assigned_at ASC"
    );
    $stmt->execute([':uid' => $uploadId]);
    $reviewers = $stmt->fetchAll();

    $result = [];
    foreach ($reviewers as $r) {
        $result[] = [
            'id'           => (int)$r['id'],
            'user_id'      => (int)$r['user_id'],
            'display_name' => $r['display_name'],
            'email'        => $r['email'],
            'department'   => $r['department'] ?? '',
            'has_avatar'   => !empty($r['avatar']),
            'initial'      => mb_substr($r['display_name'] ?? '?', 0, 1),
            'assigned_at'  => $r['assigned_at'],
            'assigned_by'  => (int)$r['assigned_by'],
        ];
    }
    echo json_encode(['success' => true, 'reviewers' => $result]);
    exit;
}

// ─── POST: มอบหมายผู้ตรวจ ─────────────
if ($action === 'assign') {
    $input    = json_decode(file_get_contents('php://input'), true);
    $uploadId = intval($input['upload_id'] ?? 0);
    $userIds  = $input['user_ids'] ?? [];

    if (!$uploadId) {
        echo json_encode(['success' => false, 'error' => 'ไม่ระบุ upload_id']);
        exit;
    }

    // ตรวจสอบว่าไฟล์มีอยู่จริง
    $fStmt = $pdo->prepare("SELECT id, user_id, file_name FROM uploads WHERE id = :id");
    $fStmt->execute([':id' => $uploadId]);
    $fileRow = $fStmt->fetch();
    if (!$fileRow) {
        echo json_encode(['success' => false, 'error' => 'ไม่พบไฟล์']);
        exit;
    }

    // ลบ reviewers เก่าทั้งหมด แล้ว insert ใหม่ (replace strategy)
    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM file_reviewers WHERE upload_id = :uid")->execute([':uid' => $uploadId]);

        if (!empty($userIds)) {
            $ins = $pdo->prepare(
                "INSERT INTO file_reviewers (upload_id, user_id, assigned_by) VALUES (:uid, :rid, :aid)"
            );
            foreach ($userIds as $rid) {
                $rid = intval($rid);
                if ($rid > 0) {
                    $ins->execute([':uid' => $uploadId, ':rid' => $rid, ':aid' => $userId]);
                }
            }
        }

        $pdo->commit();

        // Log
        if (file_exists(__DIR__ . '/log_helper.php')) {
            require_once 'log_helper.php';
            $names = [];
            if (!empty($userIds)) {
                $inList = implode(',', array_map('intval', $userIds));
                $nStmt = $pdo->query("SELECT display_name FROM users WHERE id IN ({$inList})");
                $names = $nStmt->fetchAll(PDO::FETCH_COLUMN);
            }
            $nameStr = empty($names) ? 'ลบผู้ตรวจทั้งหมด' : 'มอบหมาย: ' . implode(', ', $names);
            logActivity($pdo, 'assign_reviewer', $nameStr . ' (ไฟล์: ' . $fileRow['file_name'] . ')', $uploadId, $fileRow['file_name']);
        }

        echo json_encode(['success' => true, 'count' => count($userIds)]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ─── POST: เพิ่มผู้ตรวจคนเดียว (ไม่ลบคนเก่า) ─────────────
if ($action === 'add') {
    $input     = json_decode(file_get_contents('php://input'), true);
    $uploadId  = intval($input['upload_id'] ?? 0);
    $addUserId = intval($input['user_id'] ?? 0);

    if (!$uploadId || !$addUserId) {
        echo json_encode(['success' => false, 'error' => 'ข้อมูลไม่ครบ']);
        exit;
    }

    // ตรวจสอบว่าไฟล์มีอยู่จริง
    $fStmt = $pdo->prepare("SELECT id, user_id, file_name FROM uploads WHERE id = :id");
    $fStmt->execute([':id' => $uploadId]);
    $fileRow = $fStmt->fetch();
    if (!$fileRow) {
        echo json_encode(['success' => false, 'error' => 'ไม่พบไฟล์']);
        exit;
    }

    // ตรวจสิทธิ์: ต้องเป็นเจ้าของไฟล์ หรือเป็นผู้ตรวจที่มีอยู่แล้ว
    $isFileOwner = ((int)$fileRow['user_id'] === $userId);
    if (!$isFileOwner) {
        $rvStmt = $pdo->prepare("SELECT COUNT(*) FROM file_reviewers WHERE upload_id = :uid AND user_id = :rid");
        $rvStmt->execute([':uid' => $uploadId, ':rid' => $userId]);
        if ($rvStmt->fetchColumn() == 0) {
            echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์เพิ่มผู้ตรวจ']);
            exit;
        }
    }

    try {
        $ins = $pdo->prepare(
            "INSERT INTO file_reviewers (upload_id, user_id, assigned_by) VALUES (:uid, :rid, :aid)"
        );
        $ins->execute([':uid' => $uploadId, ':rid' => $addUserId, ':aid' => $userId]);

        // ดึงข้อมูลผู้ใช้ที่เพิ่ม
        $uStmt = $pdo->prepare("SELECT display_name, email, department FROM users WHERE id = :id");
        $uStmt->execute([':id' => $addUserId]);
        $addedUser = $uStmt->fetch();

        echo json_encode(['success' => true, 'user' => [
            'user_id'      => $addUserId,
            'display_name' => $addedUser['display_name'] ?? '',
            'email'        => $addedUser['email'] ?? '',
            'department'   => $addedUser['department'] ?? '',
            'initial'      => mb_substr($addedUser['display_name'] ?? '?', 0, 1),
            'assigned_by'  => $userId,
        ]]);
    } catch (PDOException $e) {
        // duplicate → already reviewer
        echo json_encode(['success' => false, 'error' => 'ผู้ใช้นี้เป็นผู้ตรวจอยู่แล้ว']);
    }
    exit;
}

// ─── POST: ลบผู้ตรวจคนเดียว ─────────────
if ($action === 'remove') {
    $input    = json_decode(file_get_contents('php://input'), true);
    $uploadId = intval($input['upload_id'] ?? 0);
    $removeId = intval($input['user_id'] ?? 0);

    if (!$uploadId || !$removeId) {
        echo json_encode(['success' => false, 'error' => 'ข้อมูลไม่ครบ']);
        exit;
    }

    // ตรวจสอบว่าไฟล์มีอยู่จริง
    $fStmt = $pdo->prepare("SELECT user_id FROM uploads WHERE id = :id");
    $fStmt->execute([':id' => $uploadId]);
    $uploadRow = $fStmt->fetch();
    if (!$uploadRow) {
        echo json_encode(['success' => false, 'error' => 'ไม่พบไฟล์']);
        exit;
    }

    // ตรวจสิทธิ์: เจ้าของไฟล์ลบได้ทุกคน / ผู้ตรวจลบได้เฉพาะที่ตัวเองเพิ่มมา
    $isFileOwner = ((int)$uploadRow['user_id'] === $userId);
    if (!$isFileOwner) {
        $chkStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM file_reviewers WHERE upload_id = :uid AND user_id = :rid AND assigned_by = :aid"
        );
        $chkStmt->execute([':uid' => $uploadId, ':rid' => $removeId, ':aid' => $userId]);
        if ($chkStmt->fetchColumn() == 0) {
            echo json_encode(['success' => false, 'error' => 'ไม่มีสิทธิ์ลบผู้ตรวจรายนี้']);
            exit;
        }
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM file_reviewers WHERE upload_id = :uid AND user_id = :rid");
        $stmt->execute([':uid' => $uploadId, ':rid' => $removeId]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ─── POST: ตรวจสอบสิทธิ์ reviewer ─────────────
if ($action === 'check') {
    $uploadId = intval($_GET['upload_id'] ?? 0);
    if (!$uploadId) {
        echo json_encode(['success' => false, 'is_reviewer' => false]);
        exit;
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM file_reviewers WHERE upload_id = :uid AND user_id = :rid");
    $stmt->execute([':uid' => $uploadId, ':rid' => $userId]);
    $isReviewer = $stmt->fetchColumn() > 0;

    // เจ้าของไฟล์ก็ถือว่ามีสิทธิ์
    $oStmt = $pdo->prepare("SELECT user_id FROM uploads WHERE id = :id");
    $oStmt->execute([':id' => $uploadId]);
    $owner = $oStmt->fetch();
    $isOwner = ($owner && (int)$owner['user_id'] === $userId);

    echo json_encode(['success' => true, 'is_reviewer' => $isReviewer, 'is_owner' => $isOwner]);
    exit;
}

// ─── POST: ตั้งสถานะของผู้ตรวจ (เฉพาะตัวเอง) ─────────────
if ($action === 'set_review_status') {
    $input    = json_decode(file_get_contents('php://input'), true);
    $uploadId = intval($input['upload_id'] ?? 0);
    $rvStatus = trim($input['rv_status'] ?? '');
    $rvDesc   = trim($input['rv_description'] ?? '');

    if (!$uploadId) {
        echo json_encode(['success' => false, 'error' => 'ไม่ระบุ upload_id']);
        exit;
    }

    $allowed = ['ผ่าน', 'แก้ไข', 'ไม่ผ่าน', ''];
    if (!in_array($rvStatus, $allowed, true)) {
        echo json_encode(['success' => false, 'error' => 'สถานะไม่ถูกต้อง']);
        exit;
    }

    // ตรวจสอบไฟล์และสถานะไฟล์
    $fStmt = $pdo->prepare("SELECT status FROM uploads WHERE id = :id");
    $fStmt->execute([':id' => $uploadId]);
    $fileRow = $fStmt->fetch();
    if (!$fileRow) {
        echo json_encode(['success' => false, 'error' => 'ไม่พบไฟล์']);
        exit;
    }
    if ($fileRow['status'] !== 'รอตรวจ') {
        echo json_encode(['success' => false, 'error' => 'ไม่สามารถตั้งสถานะได้ สถานะไฟล์ต้องเป็น "รอตรวจ"']);
        exit;
    }

    // ยืนยันว่าเป็นผู้ตรวจของไฟล์นี้
    $rvStmt = $pdo->prepare("SELECT COUNT(*) FROM file_reviewers WHERE upload_id = :uid AND user_id = :rid");
    $rvStmt->execute([':uid' => $uploadId, ':rid' => $userId]);
    if ($rvStmt->fetchColumn() == 0) {
        echo json_encode(['success' => false, 'error' => 'คุณไม่ใช่ผู้ตรวจของไฟล์นี้']);
        exit;
    }

    $upd = $pdo->prepare(
        "UPDATE file_reviewers SET rv_status = :s, rv_description = :d
         WHERE upload_id = :uid AND user_id = :rid"
    );
    $upd->execute([
        ':s'   => $rvStatus ?: null,
        ':d'   => $rvDesc   ?: null,
        ':uid' => $uploadId,
        ':rid' => $userId,
    ]);

    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'ไม่พบ action']);
