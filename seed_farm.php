<?php
require 'db.php';
date_default_timezone_set('Asia/Taipei');

$pdo->beginTransaction();
try {
    // 清空重置，迎接新活動
    $pdo->exec("DELETE FROM schedules");
    $pdo->exec("DELETE FROM stations");
    $pdo->exec("DELETE FROM notifications");
    $pdo->exec("DELETE FROM sqlite_sequence WHERE name IN ('schedules', 'stations', 'notifications')");

    $stmt_st = $pdo->prepare("INSERT INTO stations (name, lat, lng, type) VALUES (?, ?, ?, 'farm')");
    $stmt_sch = $pdo->prepare("INSERT INTO schedules (squad_id, station_id, start_time, end_time) VALUES (?, ?, ?, ?)");
    
    // 匯入你的四天外採日期
    $farm_dates = ['2026-07-07', '2026-07-10', '2026-07-14', '2026-07-17'];

    // 建立 8 個農場關卡與精準座標 (標註 10分 關卡方便關主識別)
    $farm_coords = [
        ['name' => '農場關卡1', 'lat' => 24.960639, 'lng' => 121.528167], 
        ['name' => '農場關卡2', 'lat' => 24.960111, 'lng' => 121.527861],
        ['name' => '農場關卡3', 'lat' => 24.959889, 'lng' => 121.527556], 
        ['name' => '農場關卡4 (大關)', 'lat' => 24.959250, 'lng' => 121.526806],
        ['name' => '農場關卡5', 'lat' => 24.959139, 'lng' => 121.526444], 
        ['name' => '農場關卡6', 'lat' => 24.959000, 'lng' => 121.526139],
        ['name' => '農場關卡7 (大關)', 'lat' => 24.958722, 'lng' => 121.526000], 
        ['name' => '農場關卡8', 'lat' => 24.958778, 'lng' => 121.525833]
    ];
    $farm_ids = [];
    foreach ($farm_coords as $c) {
        $stmt_st->execute([$c['name'], $c['lat'], $c['lng']]);
        $farm_ids[] = $pdo->lastInsertId();
    }

    // 8 個時段設計：[開始時間, 一般關卡結束時間(留3分走路), 大關卡結束時間(留1分走路)]
    $rounds = [
        ['09:30:00', '09:37:00', '09:39:00'],
        ['09:40:00', '09:47:00', '09:49:00'],
        ['09:50:00', '09:57:00', '09:59:00'],
        ['10:00:00', '10:07:00', '10:09:00'],
        // === 10:09 ~ 10:15 大遷徙 (兩區互換，最長有 8 分鐘走路) ===
        ['10:15:00', '10:22:00', '10:24:00'],
        ['10:25:00', '10:32:00', '10:34:00'],
        ['10:35:00', '10:42:00', '10:44:00'],
        ['10:45:00', '10:52:00', '10:54:00'] // 10:54 結束，走到門口 11:00 上車
    ];

    // 完美半區分流矩陣：前四局 1-4隊在左、5-8隊在右，後四局互換
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
                
                // 判斷是不是第 4 關或第 7 關 (Index 為 3 和 6)
                $is_heavy_station = ($i === 3 || $i === 6);
                
                $start_time = $date . ' ' . $times[0];
                // 大關卡給 9 分鐘，一般關卡給 7 分鐘
                $end_time = $date . ' ' . ($is_heavy_station ? $times[2] : $times[1]);
                
                $stmt_sch->execute(["第{$sq}小隊", $sid, $start_time, $end_time]);
            }
        }
    }

    $pdo->commit();
    echo "<h1 style='color:green;'>✅ [外採] 安康農場高效分流＋走路緩衝排程 已載入完畢！</h1>";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "<h1>❌ 錯誤：" . $e->getMessage() . "</h1>";
}
?>
