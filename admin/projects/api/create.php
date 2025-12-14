<?php
// API Yapılandırması
header('Content-Type: application/json; charset=utf-8');
require_once $_SERVER["DOCUMENT_ROOT"].'/config/db.php';

// Session Kontrolü
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$response = ['success' => false, 'message' => ''];

// Yetki Kontrolü
if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Oturum süreniz dolmuş. Lütfen tekrar giriş yapın.';
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // 1. Verileri Al ve Temizle
        $name = trim($_POST['name'] ?? '');

        // Dökümandaki "Project Definition Format" alanları
        $project_details = [
            'domain'                => trim($_POST['domain'] ?? ''),
            'primary_entity'        => trim($_POST['primary_entity'] ?? ''),
            'constraints'           => trim($_POST['constraints'] ?? ''),
            'advanced_features'     => trim($_POST['advanced_features'] ?? ''),
            'security_access'       => trim($_POST['security_access'] ?? ''),
            'reporting'             => trim($_POST['reporting'] ?? ''),
            'common_tasks'          => trim($_POST['common_tasks'] ?? '')
        ];

        // 2. Basit Doğrulama
        if (empty($name)) {
            throw new Exception("Lütfen bir proje adı giriniz.");
        }
        if (empty($project_details['domain']) || empty($project_details['primary_entity'])) {
            throw new Exception("Domain ve Ana Varlık (Primary Entity) alanları zorunludur.");
        }

        // 3. Detayları JSON'a çevir (DB'deki description alanına kaydedeceğiz)
        // JSON_UNESCAPED_UNICODE: Türkçe karakterleri bozmadan saklar
        $description_json = json_encode($project_details, JSON_UNESCAPED_UNICODE);

        // 4. Veritabanına Kaydet
        $sql = "INSERT INTO projects (user_id, name, description, status) VALUES (:user_id, :name, :description, 'draft')";
        $stmt = $db->prepare($sql);

        $result = $stmt->execute([
            ':user_id'     => $_SESSION['user_id'],
            ':name'        => $name,
            ':description' => $description_json
        ]);

        if ($result) {
            $response['success'] = true;
            $response['message'] = 'Proje başarıyla oluşturuldu!';
            $response['redirect'] = '/admin/projects'; // Başarılı olunca Dashboard'a dön
        } else {
            throw new Exception("Veritabanına kayıt yapılamadı.");
        }

    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
} else {
    $response['message'] = 'Geçersiz istek yöntemi.';
}

echo json_encode($response);
exit;