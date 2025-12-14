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
    http_response_code(401);
    $response['message'] = 'Oturum süreniz dolmuş.';
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // 1. Verileri Al
        $id = $_POST['id'] ?? null;
        $name = trim($_POST['name'] ?? '');
        $user_id = $_SESSION['user_id'];

        if (!$id || !is_numeric($id)) {
            throw new Exception("Geçersiz proje ID.");
        }
        if (empty($name)) {
            throw new Exception("Proje adı boş olamaz.");
        }

        // 2. Detayları JSON Formatına Hazırla
        // Kullanıcının create.php'de girdiği alanların aynısı
        $project_details = [
            'domain'            => trim($_POST['domain'] ?? ''),
            'primary_entity'    => trim($_POST['primary_entity'] ?? ''),
            'constraints'       => trim($_POST['constraints'] ?? ''),
            'advanced_features' => trim($_POST['advanced_features'] ?? ''),
            'security_access'   => trim($_POST['security_access'] ?? ''),
            'reporting'         => trim($_POST['reporting'] ?? ''),
            'common_tasks'      => trim($_POST['common_tasks'] ?? '')
        ];

        // JSON'a çevir (Türkçe karakterleri bozmadan)
        $description_json = json_encode($project_details, JSON_UNESCAPED_UNICODE);

        // 3. Veritabanını Güncelle
        // Sadece ID yetmez, user_id de eşleşmeli (Başkası başkasının projesini güncelleyemesin)
        $sql = "UPDATE projects 
                SET name = :name, description = :description 
                WHERE id = :id AND user_id = :user_id";

        $stmt = $db->prepare($sql);
        $result = $stmt->execute([
            ':name'        => $name,
            ':description' => $description_json,
            ':id'          => $id,
            ':user_id'     => $user_id
        ]);

        if ($result && $stmt->rowCount() >= 0) {
            // rowCount 0 olabilir (Eğer hiçbir şey değiştirmeden kaydet dediyse), bu hata değildir.
            $response['success'] = true;
            $response['message'] = 'Proje başarıyla güncellendi!';
            $response['redirect'] = '/admin/projects/detail?id=' . $id; // Detay sayfasına geri at
        } else {
            throw new Exception("Güncelleme işlemi başarısız oldu.");
        }

    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
} else {
    $response['message'] = 'Geçersiz istek türü.';
}

echo json_encode($response);
exit;