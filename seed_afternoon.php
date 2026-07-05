<?php
require 'db.php';
date_default_timezone_set('Asia/Taipei');

$pdo->beginTransaction();
try {
    $pdo->exec("DELETE FROM schedules");
    $pdo->exec("DELETE FROM stations");
    $pdo->exec("DELETE FROM notifications");
    $pdo->exec("DELETE FROM sqlite_sequence WHERE name IN ('schedules', 'stations', 'notifications')");

    $stmt_st = $pdo->prepare("INSERT INTO stations (name, type) VALUES (?, 'specimen')");
    $stmt_sch = $pdo->prepare("INSERT INTO schedules (squad_id, station_id, start_time, end_time) VALUES (?, ?, ?, ?)");
    $dates = ['2026-07-06', '2026-07-09', '2026-07-13', '2026-07-16'];

    // 建立 8 個標本關卡
    $specimen = ['關卡1(609前)', '關卡2(609後)', '關卡3(走廊1)', '關卡4(走廊2)', '關卡5(603前)', '關卡6(603後)', '關卡7(614後)', '關卡8(614前)'];
    $spec_ids = [];
    foreach ($specimen as $name) {
        $stmt_st->execute([$name]);
        $spec_ids[] = $pdo->lastInsertId();
    }

    // 完整 8 個時段與輪轉矩陣
    $afternoon_slots = [
        ['14:05:00', '14:12:00'], ['14:12:00', '14:19:00'], 
        ['14:19:00', '14:26:00'], ['14:26:00', '14:33:00'], 
        ['14:33:00', '14:40:00'], ['14:40:00', '14:47:00'], 
        ['14:47:00', '14:54:00'], ['14:54:00', '15:01:00']
    ];
    $squad_matrix = [
        [1,2,3,4,5,6,7,8], [8,1,2,3,4,5,6,7], [7,8,1,2,3,4,5,6], [6,7,8,1,2,3,4,5], 
        [5,6,7,8,1,2,3,4], [4,5,6,7,8,1,2,3], [3,4,5,6,7,8,1,2], [2,3,4,5,6,7,8,1]
    ];
    
    foreach ($dates as $date) {
        foreach ($afternoon_slots as $r => $slot) {
            foreach ($spec_ids as $i => $sid) {
                $sq = $squad_matrix[$r][$i];
                $stmt_sch->execute(["第{$sq}小隊", $sid, $date . ' ' . $slot[0], $date . ' ' . $slot[1]]);
            }
        }
    }

    $pdo->commit();
    echo "<h1 style='color:orange;'>✅ [下午] 標本關行程已載入完畢！</h1>";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "<h1>❌ 錯誤：" . $e->getMessage() . "</h1>";
}
?>
