<?php
// reset_db.php
require 'db.php';
date_default_timezone_set('Asia/Taipei');

$pdo->beginTransaction();
try {
    $pdo->exec("DELETE FROM schedules");
    $pdo->exec("DELETE FROM stations");
    $pdo->exec("DELETE FROM notifications");
    $pdo->exec("DELETE FROM sqlite_sequence WHERE name IN ('schedules', 'stations', 'notifications')");

    $stmt_st = $pdo->prepare("INSERT INTO stations (name, lat, lng, type) VALUES (?, ?, ?, ?)");
    $stmt_sch = $pdo->prepare("INSERT INTO schedules (squad_id, station_id, start_time, end_time) VALUES (?, ?, ?, ?)");
    $dates = ['2026-07-06', '2026-07-09', '2026-07-13', '2026-07-16'];

    // --- (A) 早上：小團康關卡 ---
    $indoor = [['603-1a'], ['609-2a'], ['614-3a'], ['走廊-4a'], ['603-1b'], ['609-2b'], ['614-3b'], ['走廊-4b']];
    foreach ($indoor as $st) {
        $stmt_st->execute([$st[0], null, null, 'indoor']);
        $id = $pdo->lastInsertId();
        foreach ($dates as $d) {
            // 簡化：匯入全天時段
            $stmt_sch->execute(["第1小隊", $id, $d . ' 10:15:00', $d . ' 11:33:00']);
        }
    }

    // --- (B) 下午：標本關卡 ---
    $specimen = ['關卡1(609前)', '關卡2(609後)', '關卡3(走廊1)', '關卡4(走廊2)', '關卡5(603前)', '關卡6(603後)', '關卡7(614後)', '關卡8(614前)'];
    $afternoon_slots = [
        ['14:05', '14:12'], ['14:12', '14:19'], ['14:19', '14:26'], ['14:26', '14:33'], 
        ['14:33', '14:40'], ['14:40', '14:47'], ['14:47', '14:54'], ['14:54', '15:01']
    ];
    $squad_matrix = [[1,2,3,4,5,6,7,8], [8,1,2,3,4,5,6,7], [7,8,1,2,3,4,5,6], [6,7,8,1,2,3,4,5], [5,6,7,8,1,2,3,4], [4,5,6,7,8,1,2,3], [3,4,5,6,7,8,1,2], [2,3,4,5,6,7,8,1]];
    
    foreach ($specimen as $i => $name) {
        $stmt_st->execute([$name, null, null, 'specimen']);
        $sid = $pdo->lastInsertId();
        foreach ($dates as $d) {
            foreach ($afternoon_slots as $r => $slot) {
                $sq = $squad_matrix[$r][$i];
                $stmt_sch->execute(["第{$sq}小隊", $sid, $d . ' ' . $slot[0] . ':00', $d . ' ' . $slot[1] . ':00']);
            }
        }
    }

    // --- (C) 安康農場：8 關卡 + 精確座標 ---
    $farm_coords = [
        ['name' => '農場關卡1', 'lat' => 24.960639, 'lng' => 121.528167], ['name' => '農場關卡2', 'lat' => 24.960111, 'lng' => 121.527861],
        ['name' => '農場關卡3', 'lat' => 24.959889, 'lng' => 121.527556], ['name' => '農場關卡4', 'lat' => 24.959250, 'lng' => 121.526806],
        ['name' => '農場關卡5', 'lat' => 24.959139, 'lng' => 121.526444], ['name' => '農場關卡6', 'lat' => 24.959000, 'lng' => 121.526139],
        ['name' => '農場關卡7', 'lat' => 24.958722, 'lng' => 121.526000], ['name' => '農場關卡8', 'lat' => 24.958778, 'lng' => 121.525833]
    ];
    $farm_dates = ['2026-07-07', '2026-07-10', '2026-07-14', '2026-07-17'];
    
    foreach ($farm_coords as $i => $c) {
        $stmt_st->execute([$c['name'], $c['lat'], $c['lng'], 'farm']);
        $sid = $pdo->lastInsertId();
        foreach ($farm_dates as $d) {
            for ($s = 1; $s <= 8; $s++) {
                $stmt_sch->execute(["第{$s}小隊", $sid, $d . ' 08:30:00', $d . ' 12:00:00']);
            }
        }
    }

    $pdo->commit();
    echo "<h1>✅ 系統重置成功！</h1><p>全行程已納入，包含輪轉邏輯與精確座標。</p>";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "<h1>❌ 錯誤：" . $e->getMessage() . "</h1>";
}
?>