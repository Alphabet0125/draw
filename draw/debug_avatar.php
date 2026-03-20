<?php
require_once '../config.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

$userId = $_SESSION['user_id'];

// ดึงข้อมูล avatar ดิบ
$stmt = $pdo->prepare("SELECT avatar, display_name FROM users WHERE id = :id");
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode(['error' => 'user not found']);
    exit;
}

$avatar = $user['avatar'];

$info = [
    'display_name' => $user['display_name'],
    'avatar_is_null' => is_null($avatar),
    'avatar_is_empty' => empty($avatar),
    'avatar_type' => gettype($avatar),
    'avatar_length' => $avatar ? strlen($avatar) : 0,
    'avatar_first_50_chars' => $avatar ? substr($avatar, 0, 50) : null,
    'avatar_is_file_path' => $avatar ? (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $avatar) ? true : false) : false,
    'avatar_starts_with_slash' => $avatar ? (substr($avatar, 0, 1) === '/') : false,
    'avatar_starts_with_data' => $avatar ? (strpos($avatar, 'data:') === 0) : false,
    'avatar_looks_like_base64' => $avatar ? (preg_match('/^[A-Za-z0-9+\/=]+$/', substr($avatar, 0, 100)) ? true : false) : false,
    'avatar_has_binary' => $avatar ? (!mb_check_encoding($avatar, 'UTF-8')) : false,
];

// ★ เช็ค columns ของตาราง users
$columns = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_ASSOC);
$columnNames = array_column($columns, 'Field');
$avatarColumnInfo = null;
foreach ($columns as $col) {
    if (strtolower($col['Field']) === 'avatar') {
        $avatarColumnInfo = $col;
    }
}

$info['all_user_columns'] = $columnNames;
$info['avatar_column_info'] = $avatarColumnInfo;

// ★ เช็คว่ามี profile_image หรือ avatar_url หรือ photo column ไหม
$possibleAvatarColumns = [];
foreach ($columnNames as $col) {
    if (preg_match('/(avatar|image|photo|picture|pic|img|profile)/i', $col)) {
        $possibleAvatarColumns[] = $col;
    }
}
$info['possible_avatar_columns'] = $possibleAvatarColumns;

echo json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);