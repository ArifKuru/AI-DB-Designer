<?php
// API Yapılandırması
header('Content-Type: application/json; charset=utf-8');
require_once $_SERVER["DOCUMENT_ROOT"].'/config/db.php';

// Session Başlat
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$response = ['success' => false, 'message' => ''];

// 1. Yetki Kontrolü
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    $response['message'] = 'Oturum süreniz dolmuş.';
    echo json_encode($response);
    exit;
}

// 2. İstek Yöntemi Kontrolü (POST olmalı)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $project_id = $_POST['id'] ?? null;
    $user_id = $_SESSION['user_id'];

    if (!$project_id || !is_numeric($project_id)) {
        $response['message'] = 'Geçersiz proje ID.';
        echo json_encode($response);
        exit;
    }

    try {
        // 3. Silme İşlemi (Sadece kendi projesini silebilir)
        // ON DELETE CASCADE olduğu için rule'lar ve tablolar otomatik silinir.
        $stmt = $db->prepare("DELETE FROM projects WHERE id = :id AND user_id = :user_id");
        $result = $stmt->execute([
            ':id' => $project_id,
            ':user_id' => $user_id
        ]);

        if ($stmt->rowCount() > 0) {
            $response['success'] = true;
            $response['message'] = 'Proje başarıyla silindi.';
            $response['redirect'] = '/admin/projects/index.php'; // Dashboard'a yönlendir
        } else {
            $response['message'] = 'Proje bulunamadı veya silme yetkiniz yok.';
        }

    } catch (PDOException $e) {
        $response['message'] = 'Veritabanı hatası: ' . $e->getMessage();
    }

} else {
    $response['message'] = 'Geçersiz istek türü.';
}

echo json_encode($response);
exit;