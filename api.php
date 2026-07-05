<?php
require 'db.php';

$action = $_GET['action'] ?? '';

// ==========================================
// 1. 獲取「小隊」當前或下一關的任務
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'get_schedule') {
    $squad_id = $_GET['squad_id'] ?? '';
    $current_time = $_GET['time'] ?? date('Y-m-d H:i:s'); 
    
    $stmt = $pdo->prepare("
        SELECT s.start_time, s.end_time, st.name, st.lat, st.lng 
        FROM schedules s
        JOIN stations st ON s.station_id = st.id
        WHERE s.squad_id = ? AND s.end_time >= ?
        ORDER BY s.start_time ASC
        LIMIT 1
    ");
    $stmt->execute(["第" . $squad_id . "小隊", $current_time]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($task) {
        echo json_encode(['status' => 'success', 'data' => $task]);
    } else {
        echo json_encode(['status' => 'empty', 'message' => '目前無任務或已過營業時間']);
    }
    exit;
}

// ==========================================
// 2. 獲取「關主」目前應接待的小隊
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'get_station_schedule') {
    $station_id = $_GET['station_id'] ?? '';
    $current_time = $_GET['time'] ?? date('Y-m-d H:i:s'); 
    
    $stmt = $pdo->prepare("
        SELECT squad_id, start_time, end_time
        FROM schedules
        WHERE station_id = ? AND end_time >= ?
        ORDER BY start_time ASC
        LIMIT 1
    ");
    $stmt->execute([$station_id, $current_time]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($task) {
        echo json_encode(['status' => 'success', 'data' => $task]);
    } else {
        echo json_encode(['status' => 'empty', 'message' => '目前無接待任務']);
    }
    exit;
}

// ==========================================
// 3. 處理關主發送的 Delay 通知 (寫入資料庫)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'notify') {
    $data = json_decode(file_get_contents('php://input'), true);
    $stmt = $pdo->prepare("INSERT INTO notifications (sender, target_squad, message) VALUES (?, ?, ?)");
    $stmt->execute([$data['sender'], $data['target_squad'], $data['message']]);
    echo json_encode(['status' => 'success']);
    exit;
}


// ==========================================
// 4. 輪詢端點：獲取最新通知 (完全取代之前的 SSE)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'get_notifications') {
    // 【關鍵修復】告訴瀏覽器絕對不要快取這個請求，每次都要去資料庫重抓
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    $last_id = isset($_GET['last_id']) ? intval($_GET['last_id']) : 0;
    
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE id > ? ORDER BY id ASC");
    $stmt->execute([$last_id]);
    $new_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['status' => 'success', 'data' => $new_notifications]);
    exit;
}
?>