<?php
DEFINE("DB_SERVER_NAME", "localhost");
DEFINE("DB_USERNAME", "arif_database");
DEFINE("DB_PASSWORD", "8qQuzuEaQu2Q4WN8qP4L");
DEFINE("DB_NAME", "arif_database");


// Veritabanı Ayarları
$host = DB_SERVER_NAME;
$dbname = DB_NAME; // Projemizin veritabanı ismi
$username = DB_USERNAME;         // Localhost varsayılan kullanıcı adı
$password = DB_PASSWORD;             // Localhost varsayılan şifresi (MAMP/XAMPP'a göre değişebilir)
$charset = 'utf8mb4';       // Türkçe karakter sorunu yaşamamak için

// DSN (Data Source Name) Tanımlaması
$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";

// PDO Seçenekleri
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // Hataları exception olarak fırlat
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,      // Verileri varsayılan olarak dizi (array) formatında çek
    PDO::ATTR_EMULATE_PREPARES => false,                 // SQL Injection güvenliği için gerçek prepare kullan
];

try {
    // Bağlantıyı oluştur ve $db değişkenine ata
    $db = new PDO($dsn, $username, $password, $options);
} catch (\PDOException $e) {
    // Hata durumunda çalışmayı durdur ve hatayı göster
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
