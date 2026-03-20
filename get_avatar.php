<?php
/**
 * Include ไฟล์นี้หลัง requireLogin() ในทุกหน้า
 * จะได้ตัวแปร $userAvatar สำหรับแสดงรูปโปรไฟล์
 */

$userAvatar = '';

if (isset($_SESSION['user_id'])) {
    // ★ เช็คใน session ก่อน (cache)
    if (isset($_SESSION['avatar_cache'])) {
        $userAvatar = $_SESSION['avatar_cache'];
    } else {
        try {
            $stmtAvatar = $pdo->prepare("SELECT avatar FROM users WHERE id = :id");
            $stmtAvatar->execute([':id' => $_SESSION['user_id']]);
            $rowAvatar = $stmtAvatar->fetch();
            $userAvatar = $rowAvatar['avatar'] ?? '';
            // เก็บ cache ใน session
            $_SESSION['avatar_cache'] = $userAvatar;
        } catch (PDOException $e) {
            $userAvatar = '';
        }
    }
}