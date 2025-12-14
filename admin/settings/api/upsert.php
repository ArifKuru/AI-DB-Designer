<?php
// /admin/settings/api/upsert.php
header('Content-Type: application/json; charset=utf-8');
require_once $_SERVER["DOCUMENT_ROOT"].'/config/db.php';

// Session Başlat
if (session_status() == PHP_SESSION_NONE) { session_start(); }

$response = ['success' => false, 'message' => ''];

// Yetki Kontrolü
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim.']);
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $api_key = trim($_POST['gemini_api_key'] ?? '');

        // Validasyon
        if (empty($api_key)) {
            throw new Exception("Gemini API Key alanı boş bırakılamaz.");
        }

        // 1. Kullanıcının ayarı var mı kontrol et
        $checkStmt = $db->prepare("SELECT id FROM settings WHERE user_id = ?");
        $checkStmt->execute([$user_id]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // VARSA GÜNCELLE
            $sql = "UPDATE settings SET gemini_api_key = :api_key WHERE id = :id";
            $stmt = $db->prepare($sql);
            $result = $stmt->execute([
                ':api_key' => $api_key,
                ':id' => $existing['id']
            ]);
        } else {
            // YOKSA EKLE
            $sql = "INSERT INTO settings (user_id, gemini_api_key) VALUES (:user_id, :api_key)";
            $stmt = $db->prepare($sql);
            $result = $stmt->execute([
                ':user_id' => $user_id,
                ':api_key' => $api_key
            ]);
        }

        if ($result) {
            $response['success'] = true;
            $response['message'] = 'Ayarlar başarıyla kaydedildi.';
        } else {
            throw new Exception("Veritabanı hatası oluştu.");
        }

    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }

    echo json_encode($response);
}
?>