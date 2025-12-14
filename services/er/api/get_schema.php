<?php
// /services/api/get_schema.php
header('Content-Type: application/json; charset=utf-8');
require_once $_SERVER["DOCUMENT_ROOT"].'/config/db.php';

if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$project_id = $_GET['id'] ?? null;
if (!$project_id) {
    echo json_encode(['success' => false, 'message' => 'Missing ID']);
    exit;
}

try {
    // 1. Tabloları Çek
    $stmtTables = $db->prepare("SELECT * FROM project_tables WHERE project_id = ?");
    $stmtTables->execute([$project_id]);
    $tables = $stmtTables->fetchAll(PDO::FETCH_ASSOC);

    $formattedTables = [];
    foreach($tables as $t) {
        // DÜZELTME BURADA: is_unique ve is_nullable EKLENDİ
        $stmtCols = $db->prepare("SELECT name, data_type, is_primary_key, is_foreign_key, is_unique, is_nullable FROM project_columns WHERE table_id = ?");
        $stmtCols->execute([$t['id']]);
        $t['columns'] = $stmtCols->fetchAll(PDO::FETCH_ASSOC);

        // ID'yi key olarak kullanıyoruz (JS tarafında kolay erişim için)
        $formattedTables[$t['id']] = $t;
    }

    // 2. İlişkileri Çek
    $stmtRels = $db->prepare("SELECT * FROM project_relationships WHERE project_id = ?");
    $stmtRels->execute([$project_id]);
    $relationships = $stmtRels->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'tables' => $formattedTables,
        'relationships' => $relationships
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>