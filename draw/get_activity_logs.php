<?php
/**
 * API: ดึง activity logs (JSON)
 * รองรับ filter: action_type, user_id, file_id, date_from, date_to, search, page, limit
 */
require_once '../config.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

$page      = max(1, (int)($_GET['page'] ?? 1));
$limit     = min(100, max(10, (int)($_GET['limit'] ?? 20)));
$offset    = ($page - 1) * $limit;

$actionType = trim($_GET['action_type'] ?? '');
$userId     = (int)($_GET['filter_user'] ?? 0);
$fileId     = (int)($_GET['file_id'] ?? 0);
$dateFrom   = trim($_GET['date_from'] ?? '');
$dateTo     = trim($_GET['date_to'] ?? '');
$search     = trim($_GET['search'] ?? '');

// Build WHERE clauses
$where  = [];
$params = [];

if ($actionType) {
    $where[]  = 'al.action_type = :atype';
    $params[':atype'] = $actionType;
}
if ($userId) {
    $where[]  = 'al.user_id = :fuser';
    $params[':fuser'] = $userId;
}
if ($fileId) {
    $where[]  = 'al.file_id = :fid';
    $params[':fid'] = $fileId;
}
if ($dateFrom) {
    $where[]  = 'al.created_at >= :dfrom';
    $params[':dfrom'] = $dateFrom . ' 00:00:00';
}
if ($dateTo) {
    $where[]  = 'al.created_at <= :dto';
    $params[':dto'] = $dateTo . ' 23:59:59';
}
if ($search) {
    $where[]  = '(al.action_detail LIKE :search OR al.file_name LIKE :search2 OR al.display_name LIKE :search3)';
    $params[':search']  = "%{$search}%";
    $params[':search2'] = "%{$search}%";
    $params[':search3'] = "%{$search}%";
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

try {
    // Count total
    $countSQL = "SELECT COUNT(*) FROM activity_logs al {$whereSQL}";
    $countStmt = $pdo->prepare($countSQL);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    // Fetch logs
    $sql = "SELECT al.*, u.email, u.avatar
            FROM activity_logs al
            LEFT JOIN users u ON al.user_id = u.id
            {$whereSQL}
            ORDER BY al.created_at DESC
            LIMIT {$limit} OFFSET {$offset}";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Clean avatar data (don't send full blob)
    foreach ($logs as &$log) {
        $log['has_avatar'] = !empty($log['avatar']);
        unset($log['avatar']);
        unset($log['user_agent']); // ไม่ส่ง user_agent กลับ (ไม่จำเป็น)
    }
    unset($log);

    // Stats summary
    $statsSQL = "SELECT action_type, COUNT(*) as cnt FROM activity_logs al {$whereSQL} GROUP BY action_type";
    $statsStmt = $pdo->prepare($statsSQL);
    $statsStmt->execute($params);
    $stats = [];
    while ($row = $statsStmt->fetch()) {
        $stats[$row['action_type']] = (int)$row['cnt'];
    }

    echo json_encode([
        'success'    => true,
        'logs'       => $logs,
        'total'      => $total,
        'page'       => $page,
        'limit'      => $limit,
        'totalPages' => ceil($total / $limit),
        'stats'      => $stats,
    ]);

} catch (PDOException $e) {
    error_log("Get activity logs error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
}
