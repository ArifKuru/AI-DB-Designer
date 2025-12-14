<?php
// /admin/projects/api/read.php
header('Content-Type: application/json; charset=utf-8');
require_once $_SERVER["DOCUMENT_ROOT"].'/config/db.php';

if (session_status() == PHP_SESSION_NONE) { session_start(); }

$response = ['success' => false, 'data' => [], 'message' => ''];

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // Parametreler
    $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $status = isset($_GET['status']) ? trim($_GET['status']) : '';

    // Sayfalama
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 9;
    $offset = ($page - 1) * $limit;

    // 1. TEKİL ID SORGUSU
    if ($id) {
        $stmt = $db->prepare("SELECT * FROM projects WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $user_id]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($project) {
            echo json_encode(['success' => true, 'data' => $project]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Project not found']);
        }
        exit;
    }

    // 2. LİSTE SORGUSU (Filtreli)
    $sql = "SELECT * FROM projects WHERE user_id = :user_id";
    $params = [':user_id' => $user_id];

    // Arama
    if (!empty($search)) {
        $sql .= " AND (name LIKE :search OR description LIKE :search)";
        $params[':search'] = "%$search%";
    }

    // Statü Filtresi (Yeni 7 Aşamalı Yapı)
    if (!empty($status) && $status !== 'all') {
        // SQL Injection'a karşı sadece geçerli değerlere izin verelim
        $validStatuses = [
            'draft', 'rules_extracted', 'tables_created',
            'audit_passed', 'normalized', 'diagram_generated', 'completed'
        ];

        if(in_array($status, $validStatuses)) {
            $sql .= " AND status = :status";
            $params[':status'] = $status;
        }
    }

    // Toplam Sayı (Pagination için)
    $countStmt = $db->prepare(str_replace("SELECT *", "SELECT COUNT(*)", $sql));
    $countStmt->execute($params);
    $total_records = $countStmt->fetchColumn();
    $total_pages = ceil($total_records / $limit);

    // Sıralama ve Limit
    $sql .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset"; // En son güncellenen en üstte

    $stmt = $db->prepare($sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $projects,
        'pagination' => [
            'total_records' => $total_records,
            'total_pages' => $total_pages,
            'current_page' => $page,
            'limit' => $limit
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB Error: ' . $e->getMessage()]);
}
?>