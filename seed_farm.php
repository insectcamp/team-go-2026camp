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

    // 建立 8 個農場關卡與精準座標
    $farm_coords = [
        ['name' => '農場關卡1', 'lat' => 24.960639, 'lng' => 121.528167], 
        ['name' => '農場關卡2', 'lat' => 24.960111, 'lng' => 121.527861],
        ['name' => '農場關卡3', 'lat' => 24.959889, 'lng' => 121.527556], 
        ['name' => '農場關卡4', 'lat' => 24.959250, 'lng' => 121.526806],
        ['name' => '農場關卡5', 'lat' => 24.959139, 'lng' => 121.526444], 
        ['name' => '農場關卡6', 'lat' => 24.959000, 'lng' => 121.526139],
        ['name' => '農場關卡7', 'lat' => 24.958722, 'lng' => 121.526000], 
        ['name' => '農場關卡8', 'lat' => 24.958778, 'lng' => 121.525833]
    ];
    $farm_ids = [];
    foreach ($farm_coords as $c) {
        $stmt_st->execute([$c['name'], $c['lat'], $c['lng']]);
        $farm_ids[] = $pdo->lastInsertId();
    }

    // 完整 8 個時段與輪轉矩陣 (每段 25 分鐘)
    $farm_slots = [
        ['08:30:00', '08:55:00'], ['08:55:00', '09:20:00'],
        ['09:20:00', '09:45:00'], ['09:45:00', '10:10:00'],
        ['10:10:00', '10:35:00'], ['10:35:00', '11:00:00'],
        ['11:00:00', '11:25:00'], ['11:25:00', '11:50:00']
    ];
    $squad_matrix = [
        [1,2,3,4,5,6,7,8], [8,1,2,3,4,5,6,7], [7,8,1,2,3,4,5,6], [6,7,8,1,2,3,4,5], 
        [5,6,7,8,1,2,3,4], [4,5,6,7,8,1,2,3], [3,4,5,6,7,8,1,2], [2,3,4,5,6,7,8,1]
    ];
    
    foreach ($farm_dates as $date) {
        foreach ($farm_slots as $r => $slot) {
            foreach ($farm_ids as $i => $sid) {
                $sq = $squad_matrix[$r][$i];
                $stmt_sch->execute(["第{$sq}小隊", $sid, $date . ' ' . $slot[0], $date . ' ' . $slot[1]]);
            }
        }
    }

    $pdo->commit();
    echo "<h1 style='color:green;'>✅ [外採] 安康農場行程已載入完畢！</h1>";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "<h1>❌ 錯誤：" . $e->getMessage() . "</h1>";
}
?>
