<?php
// -----------------------------------------------------------------------------
// AJAX ENDPOINT (Sihirbazın anlık veri çekmesi için - EN ÜSTE EKLENDİ)
// -----------------------------------------------------------------------------
if (isset($_GET['ajax_action']) && $_GET['ajax_action'] === 'get_pending_issues' && isset($_GET['id'])) {
    require_once $_SERVER["DOCUMENT_ROOT"].'/config/db.php';
    $pid = (int)$_GET['id'];
    $stmt = $db->prepare("SELECT id FROM project_missing_rules WHERE project_id = ? AND status = 'pending'");
    $stmt->execute([$pid]);
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    header('Content-Type: application/json');
    echo json_encode(['ids' => $ids]);
    exit;
}
