<?php
require_once '../config.php';
requireLogin();
require_once 'log_helper.php';

header('Content-Type: application/json; charset=utf-8');

$userId = $_SESSION['user_id'];
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// ★ Helper: ดึง avatar เป็น base64 — รองรับทุกรูปแบบ
function getUserAvatar($pdo, $uid) {
    try {
        // ★ ดึง column ที่เกี่ยวกับ avatar ทั้งหมด
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->execute([':id' => $uid]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) return null;

        // ★ ลองหา avatar จากหลาย column
        $possibleFields = ['avatar', 'profile_image', 'photo', 'picture', 'avatar_url', 'img', 'profile_pic'];
        $avatarData = null;

        foreach ($possibleFields as $field) {
            if (isset($user[$field]) && !empty($user[$field])) {
                $avatarData = $user[$field];
                break;
            }
        }

        if (!$avatarData) return null;

        // ★ กรณี 1: เป็น data URI อยู่แล้ว (data:image/...)
        if (strpos($avatarData, 'data:image') === 0) {
            // ตัดเอาแค่ base64 ส่วนหลัง comma
            $parts = explode(',', $avatarData, 2);
            return isset($parts[1]) ? $parts[1] : null;
        }

        // ★ กรณี 2: เป็น file path (เช่น uploads/avatar/xxx.jpg)
        if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $avatarData)) {
            // ลองหาไฟล์
            $possiblePaths = [
                __DIR__ . '/../' . $avatarData,
                __DIR__ . '/' . $avatarData,
                $_SERVER['DOCUMENT_ROOT'] . '/' . $avatarData,
                __DIR__ . '/../uploads/avatars/' . $avatarData,
                __DIR__ . '/../img/avatars/' . $avatarData,
            ];
            foreach ($possiblePaths as $path) {
                if (file_exists($path)) {
                    return base64_encode(file_get_contents($path));
                }
            }
            return null;
        }

        // ★ กรณี 3: เป็น pure base64 string อยู่แล้ว (ไม่มี prefix)
        if (preg_match('/^[A-Za-z0-9+\/=]{100,}$/', substr($avatarData, 0, 200))) {
            return $avatarData;
        }

        // ★ กรณี 4: เป็น binary BLOB
        if (!mb_check_encoding($avatarData, 'UTF-8') || strlen($avatarData) > 500) {
            return base64_encode($avatarData);
        }

        // ★ กรณี 5: เป็น URL (http://...)
        if (strpos($avatarData, 'http') === 0) {
            // ส่ง URL กลับไปตรงๆ
            return 'URL:' . $avatarData;
        }

        return null;

    } catch (PDOException $e) {
        return null;
    }
}

switch ($action) {

    // ★ ดึงคอมเมนต์ทั้งหมด
    case 'list':
        $uploadId = (int)($_GET['upload_id'] ?? 0);
        if (!$uploadId) { echo json_encode(['success'=>false,'error'=>'missing upload_id']); exit; }

        // ★ ไม่ดึง avatar ตรงนี้ (หนักเกินไป) ดึงแยกทีหลัง
        $stmt = $pdo->prepare(
            "SELECT c.id, c.upload_id, c.user_id, c.parent_id, c.content, c.attachment, c.likes, c.created_at,
                    u.display_name, u.email
             FROM file_comments c
             JOIN users u ON c.user_id = u.id
             WHERE c.upload_id = :uid
             ORDER BY c.created_at ASC"
        );
        $stmt->execute([':uid' => $uploadId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // เช็คว่า user กด like อันไหนแล้ว
        $likedStmt = $pdo->prepare(
            "SELECT comment_id FROM comment_likes WHERE user_id = :uid"
        );
        $likedStmt->execute([':uid' => $userId]);
        $likedIds = $likedStmt->fetchAll(PDO::FETCH_COLUMN);

        // ★ ดึง avatar ของ unique users ทีเดียว
        $userIds = array_unique(array_column($rows, 'user_id'));
        $avatarCache = [];
        foreach ($userIds as $uid) {
            $avatarCache[(int)$uid] = getUserAvatar($pdo, (int)$uid);
        }

        $comments = [];
        foreach ($rows as $r) {
            $comments[] = [
                'id'           => (int)$r['id'],
                'upload_id'    => (int)$r['upload_id'],
                'user_id'      => (int)$r['user_id'],
                'parent_id'    => $r['parent_id'] ? (int)$r['parent_id'] : null,
                'content'      => $r['content'],
                'attachment'   => $r['attachment'] ?? null,
                'likes'        => (int)$r['likes'],
                'liked'        => in_array((int)$r['id'], $likedIds),
                'display_name' => $r['display_name'],
                'email'        => $r['email'],
                'avatar'       => $avatarCache[(int)$r['user_id']] ?? null,
                'created_at'   => $r['created_at'],
                'initial'      => mb_substr($r['display_name'], 0, 1),
            ];
        }

        echo json_encode(['success'=>true, 'comments'=>$comments]);
        break;

    // ★ โพสต์คอมเมนต์ใหม่
    case 'post':
        $input = json_decode(file_get_contents('php://input'), true);
        $uploadId  = (int)($input['upload_id'] ?? 0);
        $parentId  = !empty($input['parent_id']) ? (int)$input['parent_id'] : null;
        $content   = trim($input['content'] ?? '');
        $attachment = $input['attachment'] ?? null;  // base64 data-URI or null

        if (!$uploadId || (!$content && !$attachment)) {
            echo json_encode(['success'=>false,'error'=>'ข้อมูลไม่ครบ']);
            exit;
        }

        if (mb_strlen($content) > 2000) {
            echo json_encode(['success'=>false,'error'=>'ข้อความยาวเกิน 2000 ตัวอักษร']);
            exit;
        }

        // validate attachment: must be a data-URI image, max ~5MB base64
        if ($attachment !== null) {
            if (!preg_match('/^data:image\/(jpeg|jpg|png|gif|webp);base64,/i', $attachment)) {
                echo json_encode(['success'=>false,'error'=>'ไฟล์แนบต้องเป็นรูปภาพ (JPEG/PNG/GIF/WEBP)']);
                exit;
            }
            $b64 = substr($attachment, strpos($attachment, ',') + 1);
            $bytes = strlen($b64) * 3 / 4;
            if ($bytes > 5 * 1024 * 1024) {
                echo json_encode(['success'=>false,'error'=>'รูปภาพใหญ่เกินไป (สูงสุด 5 MB)']);
                exit;
            }
        }

        try {
            $stmt = $pdo->prepare(
                "INSERT INTO file_comments (upload_id, user_id, parent_id, content, attachment)
                 VALUES (:fid, :uid, :pid, :content, :attachment)"
            );
            $stmt->execute([
                ':fid'        => $uploadId,
                ':uid'        => $userId,
                ':pid'        => $parentId,
                ':content'    => $content,
                ':attachment' => $attachment,
            ]);
            $newId = $pdo->lastInsertId();

            // ★ Log: แสดงความคิดเห็น
            $shortContent = mb_strlen($content) > 50 ? mb_substr($content, 0, 50) . '...' : $content;
            logActivity($pdo, 'comment', 'แสดงความคิดเห็น: "' . $shortContent . '"', $uploadId, null);

            $stmt2 = $pdo->prepare(
                "SELECT c.id, c.upload_id, c.user_id, c.parent_id, c.content, c.attachment, c.likes, c.created_at,
                        u.display_name, u.email
                 FROM file_comments c JOIN users u ON c.user_id = u.id
                 WHERE c.id = :id"
            );
            $stmt2->execute([':id' => $newId]);
            $r = $stmt2->fetch(PDO::FETCH_ASSOC);

            echo json_encode(['success'=>true, 'comment'=>[
                'id'           => (int)$r['id'],
                'upload_id'    => (int)$r['upload_id'],
                'user_id'      => (int)$r['user_id'],
                'parent_id'    => $r['parent_id'] ? (int)$r['parent_id'] : null,
                'content'      => $r['content'],
                'attachment'   => $r['attachment'] ?? null,
                'likes'        => 0,
                'liked'        => false,
                'display_name' => $r['display_name'],
                'email'        => $r['email'],
                'avatar'       => getUserAvatar($pdo, (int)$r['user_id']),
                'created_at'   => $r['created_at'],
                'initial'      => mb_substr($r['display_name'], 0, 1),
            ]]);
        } catch (PDOException $e) {
            echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
        }
        break;

    // ★ กดถูกใจ / ยกเลิกถูกใจ
    case 'like':
        $input = json_decode(file_get_contents('php://input'), true);
        $commentId = (int)($input['comment_id'] ?? 0);
        if (!$commentId) { echo json_encode(['success'=>false,'error'=>'missing comment_id']); exit; }

        try {
            $check = $pdo->prepare("SELECT id FROM comment_likes WHERE comment_id=:cid AND user_id=:uid");
            $check->execute([':cid'=>$commentId, ':uid'=>$userId]);

            if ($check->fetch()) {
                $pdo->prepare("DELETE FROM comment_likes WHERE comment_id=:cid AND user_id=:uid")
                    ->execute([':cid'=>$commentId, ':uid'=>$userId]);
                $pdo->prepare("UPDATE file_comments SET likes = GREATEST(likes-1, 0) WHERE id=:id")
                    ->execute([':id'=>$commentId]);
                $liked = false;
            } else {
                $pdo->prepare("INSERT INTO comment_likes (comment_id, user_id) VALUES (:cid, :uid)")
                    ->execute([':cid'=>$commentId, ':uid'=>$userId]);
                $pdo->prepare("UPDATE file_comments SET likes = likes+1 WHERE id=:id")
                    ->execute([':id'=>$commentId]);
                $liked = true;
            }

            $cnt = $pdo->prepare("SELECT likes FROM file_comments WHERE id=:id");
            $cnt->execute([':id'=>$commentId]);
            $newLikes = (int)$cnt->fetchColumn();

            echo json_encode(['success'=>true, 'liked'=>$liked, 'likes'=>$newLikes]);
        } catch (PDOException $e) {
            echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
        }
        break;

    // ★ ลบคอมเมนต์
    case 'delete':
        $input = json_decode(file_get_contents('php://input'), true);
        $commentId = (int)($input['comment_id'] ?? 0);
        if (!$commentId) { echo json_encode(['success'=>false,'error'=>'missing comment_id']); exit; }

        try {
            $pdo->prepare("DELETE FROM comment_likes WHERE comment_id IN (SELECT id FROM file_comments WHERE parent_id=:pid)")
                ->execute([':pid'=>$commentId]);
            $pdo->prepare("DELETE FROM file_comments WHERE parent_id=:pid")
                ->execute([':pid'=>$commentId]);
            $pdo->prepare("DELETE FROM comment_likes WHERE comment_id=:cid")
                ->execute([':cid'=>$commentId]);
            $stmt = $pdo->prepare("DELETE FROM file_comments WHERE id=:id AND user_id=:uid");
            $stmt->execute([':id'=>$commentId, ':uid'=>$userId]);

            echo json_encode(['success'=>true, 'deleted'=>$stmt->rowCount()>0]);
        } catch (PDOException $e) {
            echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success'=>false,'error'=>'unknown action']);
}