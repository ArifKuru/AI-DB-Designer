<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Kullanıcı giriş yapmış mı kontrol et
if (!isset($_SESSION['user_id'])) {
    // Login sayfasına yönlendir (Path yapısına göre değişebilir, ana dizindeyse:)
    header("Location: /login");
    exit;
}
?>