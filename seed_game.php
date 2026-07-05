<?php
// seed_games.php
require 'db.php'; // 確保已經連上 /app/data/camp_database.sqlite

echo "開始匯入小團康賽程資料...\n";

// 1. 定義 8 個關卡 (a, b 兩階段)
$stations = [
    // a 階段 (ID: 1~4)
    ['name' => '603教室 - 關卡1a(巧拼前行)', 'type' => 'indoor'],
    ['name' => '609教室 - 關卡2a(蟲蟲在哪兒)', 'type' => 'indoor'],
    ['name' => '614教室 - 關卡3a(123偽裝蟲)', 'type' => 'indoor'],
    ['name' => '走廊 - 關卡4a(蟲蟲擬在哪)', 'type' => 'indoor'],
    // b 階段 (ID: 5~8)
    ['name' => '603教室 - 關卡1b(昆蟲OX賽)', 'type' => 'indoor'],
    ['name' => '609教室 - 關卡2b(蟲蟲吃點心)', 'type' => 'indoor'],
    ['name' => '614教室 - 關卡3b(蟲蟲爭奪戰)', 'type' => 'indoor'],
    ['name' => '走廊 - 關卡4b(拼圖)', 'type' => 'indoor']
];

// 清空舊站點並匯入新站點
$pdo->exec("DELETE FROM stations WHERE type = 'indoor'");
$stmt_station = $pdo->prepare("INSERT INTO stations (name, type) VALUES (?, ?)");
foreach ($stations as $station) {
    $stmt_station->execute([$station['name'], $station['type']]);
}

// 2. 定義營隊日期
$dates = ['2026-07-06', '2026-07-09', '2026-07-13', '2026-07-16'];

// 3. 陣列化跑關對戰表 (依照圖片邏輯)
// games 陣列對應的場地順序為： [603教室, 609教室, 614教室, 走廊]
$schedule_template = [
    // --- a 階段 (對應 Station ID: 1, 2, 3, 4) ---
    ['start' => '10:15:00', 'end' => '10:23:00', 'phase' => 'a', 'games' => [[1,2], [3,4], [5,6], [7,8]]],
    ['start' => '10:25:00', 'end' => '10:33:00', 'phase' => 'a', 'games' => [[3,5], [1,7], [2,8], [4,6]]],
    ['start' => '10:35:00', 'end' => '10:43:00', 'phase' => 'a', 'games' => [[4,8], [2,6], [3,7], [1,5]]],
    ['start' => '10:45:00', 'end' => '10:53:00', 'phase' => 'a', 'games' => [[6,7], [5,8], [1,4], [2,3]]],
    
    // --- b 階段 (對應 Station ID: 5, 6, 7, 8) ---
    ['start' => '10:55:00', 'end' => '11:03:00', 'phase' => 'b', 'games' => [[1,2], [3,4], [5,6], [7,8]]],
    ['start' => '11:05:00', 'end' => '11:13:00', 'phase' => 'b', 'games' => [[3,5], [1,7], [2,8], [4,6]]],
    ['start' => '11:15:00', 'end' => '11:23:00', 'phase' => 'b', 'games' => [[4,8], [2,6], [3,7], [1,5]]],
    ['start' => '11:25:00', 'end' => '11:33:00', 'phase' => 'b', 'games' => [[6,7], [5,8], [1,4], [2,3]]]
];

// 清空舊賽程
$pdo->exec("DELETE FROM schedules");
$stmt_schedule = $pdo->prepare("INSERT INTO schedules (squad_id, station_id, start_time, end_time) VALUES (?, ?, ?, ?)");

// 4. 執行交叉合併與寫入
$pdo->beginTransaction();
try {
    foreach ($dates as $date) {
        foreach ($schedule_template as $slot) {
            $start_datetime = $date . ' ' . $slot['start'];
            $end_datetime   = $date . ' ' . $slot['end'];
            
            // 判斷當前時段對應的站點 ID 起點 (a階段從1開始，b階段從5開始)
            // 注意：此處假設資料庫中 indoor 站點的 ID 為 1~8。實務上建議用關卡名稱關聯更嚴謹，此處為快速開發的簡化版。
            $station_offset = ($slot['phase'] === 'a') ? 1 : 5; 

            // 遍歷 4 個場地
            foreach ($slot['games'] as $index => $matchup) {
                $current_station_id = $station_offset + $index;
                
                // 每個場地有兩支小隊 (對戰)
                $squad_1 = "第{$matchup[0]}小隊";
                $squad_2 = "第{$matchup[1]}小隊";
                
                $stmt_schedule->execute([$squad_1, $current_station_id, $start_datetime, $end_datetime]);
                $stmt_schedule->execute([$squad_2, $current_station_id, $start_datetime, $end_datetime]);
            }
        }
    }
    $pdo->commit();
    echo "匯入成功！共新增了 " . (4 * 8 * 4 * 2) . " 筆小隊跑關紀錄。";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "匯入失敗：" . $e->getMessage();
}
?>