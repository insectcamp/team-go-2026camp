<?php
// db.php
date_default_timezone_set('Asia/Taipei'); // 【關鍵防呆】確保伺服器使用台灣時間

$db_dir = __DIR__ . '/data';
$db_file = $db_dir . '/camp_database.sqlite';

if (!is_dir($db_dir)) {
    mkdir($db_dir, 0777, true);
}

$is_new = !file_exists($db_file);

try {
    $pdo = new PDO("sqlite:" . $db_file);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($is_new) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS stations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT, lat REAL, lng REAL, type TEXT
            );
            CREATE TABLE IF NOT EXISTS schedules (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                squad_id TEXT, station_id INTEGER, start_time TEXT, end_time TEXT
            );
            CREATE TABLE IF NOT EXISTS notifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                sender TEXT, target_squad TEXT, message TEXT, 
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");
    }
} catch (PDOException $e) {
    die("Database Connection failed: " . $e->getMessage());
}
?>