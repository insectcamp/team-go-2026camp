<?php
// seed_specimen.php
require 'db.php';

echo "開始匯入標本關賽程資料...\n";

// 1. 定義 8 個標本關卡
$specimen_stations = [
    '關卡1(609前)', '關卡2(609後)', '關卡3(走廊1)', '關卡4(走廊2)', 
    '關卡5(603前)', '關卡6(603後)', '關卡7(614後)', '關卡8(614前)'
];

$pdo->exec("DELETE FROM stations WHERE type = 'specimen'");
$stmt_station = $pdo->prepare("INSERT INTO stations (name, type) VALUES (?, 'specimen')");

$station_ids = [];
foreach ($specimen_stations as $name) {
    $stmt_station->execute([$name]);
    $station_ids[] = $pdo->lastInsertId(); // 記錄自動生成的關卡 ID
}

// 2. 定義時間與輪轉矩陣 (依照你的 Excel 截圖)
$dates = ['2026-07-06', '2026-07-09', '2026-07-13', '2026-07-16'];
$time_slots = [
    ['start' => '14:05:00', 'end' => '14:12:00'],
    ['start' => '14:12:00', 'end' => '14:19:00'],
    ['start' => '14:19:00', 'end' => '14:26:00'],
    ['start' => '14:26:00', 'end' => '14:33:00'],
    ['start' => '14:33:00', 'end' => '14:40:00'],
    ['start' => '14:40:00', 'end' => '14:47:00'],
    ['start' => '14:47:00', 'end' => '14:54:00'],
    ['start' => '14:54:00', 'end' => '15:01:00']
];

// 橫列對應時間段，直行對應關卡順序
$squad_matrix = [
    [1, 2, 3, 4, 5, 6, 7, 8],
    [8, 1, 2, 3, 4, 5, 6, 7],
    [7, 8, 1, 2, 3, 4, 5, 6],
    [6, 7, 8, 1, 2, 3, 4, 5],
    [5, 6, 7, 8, 1, 2, 3, 4],
    [4, 5, 6, 7, 8, 1, 2, 3],
    [3, 4, 5, 6, 7, 8, 1, 2],
    [2, 3, 4, 5, 6, 7, 8, 1]
];

$stmt_schedule = $pdo->prepare("INSERT INTO schedules (squad_id, station_id, start_time, end_time) VALUES (?, ?, ?, ?)");

$pdo->beginTransaction();
try {
    // 刪除舊的標本關賽程避免重複
    $pdo->exec("DELETE FROM schedules WHERE station_id IN (" . implode(',', $station_ids) . ")");
    
    foreach ($dates as $date) {
        foreach ($time_slots as $row_index => $slot) {
            $start_datetime = $date . ' ' . $slot['start'];
            $end_datetime   = $date . ' ' . $slot['end'];
            
            // 遍歷 8 個關卡
            foreach ($station_ids as $col_index => $st_id) {
                $squad_num = $squad_matrix[$row_index][$col_index];
                $squad_name = "第{$squad_num}小隊";
                $stmt_schedule->execute([$squad_name, $st_id, $start_datetime, $end_datetime]);
            }
        }
    }
    $pdo->commit();
    echo "匯入成功！已寫入 4 天的標本關賽程。";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "匯入失敗：" . $e->getMessage();
}
?>