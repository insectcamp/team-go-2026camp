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

    $stmt_st = $pdo->prepare("INSERT INTO stations (name, type) VALUES (?, 'indoor')");
    $stmt_sch = $pdo->prepare("INSERT INTO schedules (squad_id, station_id, start_time, end_time) VALUES (?, ?, ?, ?)");
    $dates = ['2026-07-06', '2026-07-09', '2026-07-13', '2026-07-16'];

    // 建立 8 個小團康關卡
    $indoor_stations = [
        '609教室 - 關卡1', '609教室 - 關卡2', '609教室 - 關卡3', '603 教室- 關卡4',
        '603教室 - 關卡5', '614教室 - 關卡6', '614教室 - 關卡7', '走廊 - 關卡8'
    ];
    $indoor_ids = [];
    foreach ($indoor_stations as $st) {
        $stmt_st->execute([$st]);
        $indoor_ids[] = $pdo->lastInsertId();
    }

    // 完整 8 個時段與對戰矩陣
    $morning_slots = [
        ['start' => '10:15:00', 'end' => '10:23:00', 'phase' => 'a', 'games' => [[1,2],[3,4],[5,6],[7,8]]],
        ['start' => '10:25:00', 'end' => '10:33:00', 'phase' => 'a', 'games' => [[3,5],[1,7],[2,8],[4,6]]],
        ['start' => '10:35:00', 'end' => '10:43:00', 'phase' => 'a', 'games' => [[4,8],[2,6],[3,7],[1,5]]],
        ['start' => '10:45:00', 'end' => '10:53:00', 'phase' => 'a', 'games' => [[6,7],[5,8],[1,4],[2,3]]],
        ['start' => '10:55:00', 'end' => '11:03:00', 'phase' => 'b', 'games' => [[1,2],[3,4],[5,6],[7,8]]],
        ['start' => '11:05:00', 'end' => '11:13:00', 'phase' => 'b', 'games' => [[3,5],[1,7],[2,8],[4,6]]],
        ['start' => '11:15:00', 'end' => '11:23:00', 'phase' => 'b', 'games' => [[4,8],[2,6],[3,7],[1,5]]],
        ['start' => '11:25:00', 'end' => '11:33:00', 'phase' => 'b', 'games' => [[6,7],[5,8],[1,4],[2,3]]]
    ];

    foreach ($dates as $date) {
        foreach ($morning_slots as $slot) {
            $start_dt = $date . ' ' . $slot['start'];
            $end_dt   = $date . ' ' . $slot['end'];
            $offset = ($slot['phase'] === 'a') ? 0 : 4; 
            
            foreach ($slot['games'] as $idx => $matchup) {
                $st_id = $indoor_ids[$offset + $idx];
                $stmt_sch->execute(["第{$matchup[0]}小隊", $st_id, $start_dt, $end_dt]);
                $stmt_sch->execute(["第{$matchup[1]}小隊", $st_id, $start_dt, $end_dt]);
            }
        }
    }

    $pdo->commit();
    echo "<h1 style='color:blue;'>✅ [早上] 小團康行程已載入完畢！</h1>";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "<h1>❌ 錯誤：" . $e->getMessage() . "</h1>";
}
?>
