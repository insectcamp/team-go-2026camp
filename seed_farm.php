<?php
require 'db.php';
date_default_timezone_set('Asia/Taipei');

$pdo->beginTransaction();
try {
    $pdo->exec("DELETE FROM schedules");
    $pdo->exec("DELETE FROM stations");
    $pdo->exec("DELETE FROM notifications");
    $pdo->exec("DELETE FROM sqlite_sequence WHERE name IN ('schedules', 'stations', 'notifications')");

    $stmt_st = $pdo->prepare("INSERT INTO stations (name, lat, lng, type) VALUES (?, ?, ?, 'farm')");
    $stmt_sch = $pdo->prepare("INSERT INTO schedules (squad_id, station_id, start_time, end_time) VALUES (?, ?, ?, ?)");
    
    $farm_dates = ['2026-07-07', '2026-07-10', '2026-07-14', '2026-07-17'];

    // 使用最新更新的 8 個關卡精準座標
    $farm_coords = [
        ['name' => '農場關卡1', 'lat' => 24.960639, 'lng' => 121.528167], 
        ['name' => '農場關卡2', 'lat' => 24.960333, 'lng' => 121.528083],
        ['name' => '農場關卡3', 'lat' => 24.960083, 'lng' => 121.527861], 
        ['name' => '農場關卡4 (大關)', 'lat' => 24.959889, 'lng' => 121.527556],
        ['name' => '農場關卡5', 'lat' => 24.959806, 'lng' => 121.527278], 
        ['name' => '農場關卡6', 'lat' => 24.959500, 'lng' => 121.527056],
        ['name' => '農場關卡7 (大關)', 'lat' => 24.959250, 'lng' => 121.526806], 
        ['name' => '農場關卡8', 'lat' => 24.959139, 'lng' => 121.526444]
    ];
    
    $farm_ids = [];
    foreach ($farm_coords as $c) {
        $stmt_st->execute([$c['name'], $c['lat'], $c['lng']]);
        $farm_ids[] = $pdo->lastInsertId();
    }

    // 嚴格控管 70 分鐘時程表：[開始時間, 一般關結束(留3分), 大關卡結束(留2分)]
    $rounds = [
        ['09:30:00', '09:35:00', '09:36:00'],
        ['09:38:00', '09:43:00', '09:44:00'],
        ['09:46:00', '09:51:00', '09:52:00'],
        ['09:54:00', '09:59:00', '10:00:00'],
        // === 10:02 ~ 10:08 中場大遷徙 (跨區移動 6 分鐘) ===
        ['10:08:00', '10:13:00', '10:14:00'],
        ['10:16:00', '10:21:00', '10:22:00'],
        ['10:24:00', '10:29:00', '10:30:00'],
        ['10:32:00', '10:37:00', '10:38:00'] // 10:40 完美壓線全面結束
    ];

    $squad_matrix = [
        [1, 2, 3, 4, 5, 6, 7, 8],
        [4, 1, 2, 3, 8, 5, 6, 7],
        [3, 4, 1, 2, 7, 8, 5, 6],
        [2, 3, 4, 1, 6, 7, 8, 5],
        [5, 6, 7, 8, 1, 2, 3, 4],
        [8, 5, 6, 7, 4, 1, 2, 3],
        [7, 8, 5, 6, 3, 4, 1, 2],
        [6, 7, 8, 5, 2, 3, 4, 1]
    ];
    
    foreach ($farm_dates as $date) {
        foreach ($rounds as $r => $times) {
            foreach ($farm_ids as $i => $sid) {
                $sq = $squad_matrix[$r][$i];
                $is_heavy = ($i === 3 || $i === 6); // 關卡4與關卡7
                
                $start_time = $date . ' ' . $times[0];
                $end_time = $date . ' ' . ($is_heavy ? $times[2] : $times[1]);
                
                $stmt_sch->execute(["第{$sq}小隊", $sid, $start_time, $end_time]);
            }
        }
    }

    $pdo->commit();
    echo "<h1 style='color:green;'>✅ [外採] 5+3 / 6+2 緩衝行程已成功載入！</h1>";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "<h1>❌ 錯誤：" . $e->getMessage() . "</h1>";
}
?>
