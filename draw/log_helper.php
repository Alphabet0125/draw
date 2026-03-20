<?php
/**
 * Activity Log Helper
 * ใช้บันทึกกิจกรรมทั้งหมดในโฟลเดอร์ Draw
 * 
 * action_type:
 *   upload         = อัปโหลดไฟล์
 *   delete         = ลบไฟล์
 *   save_drawing   = บันทึก drawing
 *   clear_drawing  = ล้าง drawing
 *   status_change  = เปลี่ยนสถานะไฟล์
 *   download       = ดาวน์โหลดไฟล์
 *   view           = เปิดดูไฟล์
 *   tool_use       = ใช้เครื่องมือวาด
 *   comment        = แสดงความคิดเห็น
 */

function logActivity($pdo, $actionType, $actionDetail = '', $fileId = null, $fileName = null)
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $userId      = $_SESSION['user_id'] ?? 0;
    $displayName = $_SESSION['display_name'] ?? 'Unknown';

    if (!$userId) return false;

    $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    // ถ้ามีหลาย IP (proxy chain) ให้เอาตัวแรก
    if (strpos($ipAddress, ',') !== false) {
        $ipAddress = trim(explode(',', $ipAddress)[0]);
    }
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO activity_logs (user_id, display_name, action_type, action_detail, file_id, file_name, ip_address, user_agent)
             VALUES (:uid, :dname, :atype, :adetail, :fid, :fname, :ip, :ua)"
        );
        $stmt->execute([
            ':uid'     => $userId,
            ':dname'   => $displayName,
            ':atype'   => $actionType,
            ':adetail' => $actionDetail,
            ':fid'     => $fileId,
            ':fname'   => $fileName,
            ':ip'      => $ipAddress,
            ':ua'      => $userAgent,
        ]);
        return true;
    } catch (PDOException $e) {
        error_log("Activity log error: " . $e->getMessage());
        return false;
    }
}
