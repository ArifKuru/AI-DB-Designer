<?php
// Hata raporlama
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

// DB Bağlantısı
require_once $_SERVER["DOCUMENT_ROOT"].'/config/db.php';

// Session Başlat
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Verileri Al ve Temizle
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $email    = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';

    // Basit Validasyon
    if (empty($username) || empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all fields.']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
        exit;
    }

    try {
        // 1. Kullanıcı Zaten Var mı Kontrolü
        $checkStmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $checkStmt->execute([$username, $email]);

        if ($checkStmt->rowCount() > 0) {
            echo json_encode(['success' => false, 'message' => 'Username or Email already exists.']);
            exit;
        }

        // 2. Şifre Hashleme
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // 3. Kayıt İşlemi (Insert)
        $insertStmt = $db->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
        $result = $insertStmt->execute([$username, $email, $hashedPassword]);

        if ($result) {
            // Kayıt Başarılı -> Otomatik Login Yap
            $newUserId = $db->lastInsertId();

            $_SESSION['user_id'] = $newUserId;
            $_SESSION['username'] = $username;
            $_SESSION['email'] = $email;

            echo json_encode([
                'success' => true,
                'message' => 'Registration successful! Logging in...',
                'redirect' => '/admin/projects' // Dashboard'a yönlendir
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error during registration.']);
        }

    } catch (PDOException $e) {
        // Hata Loglama (Opsiyonel)
        // error_log($e->getMessage());
        echo json_encode(['success' => false, 'message' => 'System error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>