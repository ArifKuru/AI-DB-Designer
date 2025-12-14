<?php
// Hata raporlamayı açalım (Geliştirme aşamasında)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// JSON döneceğimizi belirtelim
header('Content-Type: application/json; charset=utf-8');

// Veritabanı bağlantısı (Kök dizinden)
require_once $_SERVER["DOCUMENT_ROOT"].'/config/db.php';

// Oturum başlat
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$response = ['success' => false, 'message' => ''];

// Sadece POST isteği kabul et
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Verileri al
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';

    if (empty($username) || empty($password)) {
        $response['message'] = 'Lütfen kullanıcı adı ve şifre giriniz.';
        echo json_encode($response);
        exit;
    }

    try {
        // Kullanıcıyı sorgula
        $stmt = $db->prepare("SELECT * FROM users WHERE username = :username");
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        // Kullanıcı var mı ve şifre doğru mu?
        if ($user && password_verify($password, $user['password'])) {
            // BAŞARILI
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION["email"]=$user['email'];
            $response['success'] = true;
            $response['message'] = 'Giriş başarılı, yönlendiriliyorsunuz...';
            $response['redirect'] = '/admin/projects'; // Başarılı olunca gidilecek yer
        } else {
            // BAŞARISIZ
            $response['message'] = 'Hatalı kullanıcı adı veya şifre!';
        }

    } catch (PDOException $e) {
        $response['message'] = 'Veritabanı hatası: ' . $e->getMessage();
    }
} else {
    $response['message'] = 'Geçersiz istek türü.';
}

echo json_encode($response);
exit;