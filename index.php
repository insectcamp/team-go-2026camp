<?php 
require 'db.php'; 

// 撈取所有關卡站點供選單使用
$stmt = $pdo->query("SELECT id, name, type FROM stations ORDER BY id ASC");
$stations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 營隊預設 8 個小隊
$squads = range(1, 8); 
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>營隊即時管理系統</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
</head>
<body class="bg-gray-100 p-4">

    <div id="app" class="max-w-md mx-auto bg-white rounded-xl shadow-md overflow-hidden p-6">
        <h1 class="text-2xl font-bold mb-6 text-center text-indigo-600">營隊即時連線系統</h1>
        
        <select id="role-selector" class="w-full p-2 border border-gray-300 rounded-lg mb-4 bg-gray-50 text-gray-800 focus:outline-none focus:ring-2 focus:ring-indigo-500" onchange="switchView(this.value, this.options[this.selectedIndex].text)">
            <option value="">請選擇您的身份...</option>
            
            <optgroup label="🏕️ 小隊輔">
                <?php foreach($squads as $s): ?>
                    <option value="squad_<?= $s ?>">第<?= $s ?>小隊</option>
                <?php endforeach; ?>
            </optgroup>
            
            <optgroup label="🎯 關主">
                <?php foreach($stations as $st): ?>
                    <option value="station_<?= $st['id'] ?>"><?= htmlspecialchars($st['name']) ?></option>
                <?php endforeach; ?>
            </optgroup>
            
            <optgroup label="📡 總部">
                <option value="coordinator_0">場控總部</option>
            </optgroup>
        </select>

        <div id="view-squad" class="hidden">
            <div class="bg-blue-600 text-white p-3 rounded-t-lg flex justify-between items-center">
                <h2 class="text-xl font-bold" id="squad-title">小隊任務</h2>
                <span id="squad-clock" class="text-xs font-mono bg-blue-800 px-2 py-1 rounded">--:--:--</span>
            </div>
            <div class="border border-blue-200 border-t-0 rounded-b-lg p-3 mb-4 bg-blue-50">
                <p class="text-base text-gray-700 leading-relaxed" id="squad-next-station">任務讀取中...</p>
            </div>
            
            <div id="map" class="h-64 w-full bg-gray-200 rounded-lg shadow-sm border border-gray-300 mb-4 z-0"></div>
            <div id="squad-notifications" class="p-3 bg-red-100 border-l-4 border-red-500 text-red-700 rounded text-sm hidden font-medium shadow-sm"></div>
        </div>

        <div id="view-station" class="hidden">
            <div class="bg-green-600 text-white p-3 rounded-t-lg flex justify-between items-center">
                <h2 class="text-xl font-bold" id="station-title">關主控制台</h2>
                <span id="station-clock" class="text-xs font-mono bg-green-800 px-2 py-1 rounded">--:--:--</span>
            </div>
            <div class="border border-green-200 border-t-0 rounded-b-lg p-4 mb-4 bg-green-50 text-center">
                <p class="text-lg font-medium text-green-800" id="station-incoming">任務讀取中...</p>
            </div>
            
            <button onclick="notifyDelay()" class="w-full bg-yellow-500 text-white p-3 rounded-lg font-bold shadow hover:bg-yellow-600 transition flex justify-center items-center gap-2">
                <span>⚠️</span> 回報本關卡延遲 5 分鐘
            </button>
        </div>

        <div id="view-coordinator" class="hidden">
            <div class="bg-purple-600 text-white p-3 rounded-t-lg">
                <h2 class="text-xl font-bold">全局監控日誌</h2>
            </div>
            <ul id="global-logs" class="text-sm space-y-2 h-64 overflow-y-auto border border-purple-200 border-t-0 p-3 rounded-b-lg bg-gray-50 shadow-inner">
                <li class="text-gray-400 text-center italic mt-4">等待系統通知...</li>
            </ul>
        </div>
    </div>

    <script>
        let map = null;
        let marker = null;
        let currentIdentity = { type: '', id: '', name: '' };
        let currentTargetSquad = '全體'; 
        let lastNotificationId = 0; 

        // ================= 取得手機精準時間 (格式化) =================
        function getDeviceTime() {
            const now = new Date();
            const y = now.getFullYear();
            const m = String(now.getMonth() + 1).padStart(2, '0');
            const d = String(now.getDate()).padStart(2, '0');
            const h = String(now.getHours()).padStart(2, '0');
            const min = String(now.getMinutes()).padStart(2, '0');
            const s = String(now.getSeconds()).padStart(2, '0');
            
            // 更新畫面的時鐘顯示
            if(document.getElementById('squad-clock')) document.getElementById('squad-clock').innerText = `${h}:${min}:${s}`;
            if(document.getElementById('station-clock')) document.getElementById('station-clock').innerText = `${h}:${min}:${s}`;
            
            return `${y}-${m}-${d} ${h}:${min}:${s}`;
        }

        // ================= UI 視角切換與初始化 =================
        function switchView(roleValue, roleText) {
            document.querySelectorAll('#app > div[id^="view-"]').forEach(el => el.classList.add('hidden'));
            
            if (!roleValue) {
                currentIdentity = { type: '', id: '', name: '' };
                return;
            }

            const [type, id] = roleValue.split('_');
            currentIdentity = { type, id, name: roleText };

            document.getElementById(`view-${type}`).classList.remove('hidden');

            if (type === 'squad') {
                document.getElementById('squad-title').innerText = roleText;
                
                if (!map) {
                    setTimeout(() => {
                        map = L.map('map').setView([25.017, 121.539], 16); 
                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            attribution: '© OpenStreetMap'
                        }).addTo(map);
                        fetchSchedule(); 
                    }, 100);
                } else {
                    fetchSchedule();
                }
            } else if (type === 'station') {
                document.getElementById('station-title').innerText = roleText;
                fetchSchedule();
            }
        }

        // ================= 獲取正式行程 API =================
        async function fetchSchedule() {
            if (currentIdentity.type === 'coordinator' || !currentIdentity.type) return;
            
            // 每次發送請求前，獲取手機當下時間
            const deviceTime = getDeviceTime();

            try {
                if (currentIdentity.type === 'squad') {
                    // 將手機時間附加在網址參數後方傳給後端
                    const response = await fetch(`api.php?action=get_schedule&squad_id=${currentIdentity.id}&time=${deviceTime}`);
                    const result = await response.json();
                    const infoBox = document.getElementById('squad-next-station');
                    
                    if (result.status === 'success') {
                        const task = result.data;
                        infoBox.innerHTML = `
                            <div class="flex items-center gap-2 mb-1">
                                <span class="bg-blue-200 text-blue-800 text-xs px-2 py-1 rounded font-bold">目前任務</span>
                                <span class="font-bold text-gray-900">${task.name}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="bg-gray-200 text-gray-700 text-xs px-2 py-1 rounded font-bold">時段</span>
                                <span class="text-gray-700">${task.start_time.substring(11,16)} ~ ${task.end_time.substring(11,16)}</span>
                            </div>
                        `;
                        
                        if (task.lat && task.lng) {
                            const latLng = [parseFloat(task.lat), parseFloat(task.lng)];
                            map.setView(latLng, 18);
                            if (marker) map.removeLayer(marker);
                            marker = L.marker(latLng).addTo(map)
                                .bindPopup(`<b class="text-blue-600">${task.name}</b>`).openPopup();
                        }
                    } else {
                        infoBox.innerHTML = `<span class="text-red-500 font-medium">${result.message}</span>`;
                        if (marker) { map.removeLayer(marker); marker = null; }
                    }
                } 
                else if (currentIdentity.type === 'station') {
                    const response = await fetch(`api.php?action=get_station_schedule&station_id=${currentIdentity.id}&time=${deviceTime}`);
                    const result = await response.json();
                    const incomingBox = document.getElementById('station-incoming');
                    
                    if (result.status === 'success') {
                        const task = result.data;
                        currentTargetSquad = task.squad_id; 
                        incomingBox.innerHTML = `
                            目前接待：<span class="font-bold text-green-700 text-xl">${task.squad_id}</span><br>
                            <span class="text-sm text-gray-500">${task.start_time.substring(11,16)} ~ ${task.end_time.substring(11,16)}</span>
                        `;
                    } else {
                        currentTargetSquad = '全體';
                        incomingBox.innerHTML = `<span class="text-gray-500">${result.message}</span>`;
                    }
                }
            } catch (error) {
                console.error("排程讀取失敗:", error);
            }
        }

        // ================= 發送 Delay 推播 =================
        async function notifyDelay() {
            if(currentIdentity.type !== 'station') return;
            
            try {
                const response = await fetch('api.php?action=notify', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        sender: currentIdentity.name,
                        target_squad: currentTargetSquad, 
                        message: `${currentIdentity.name} 現場微調中，${currentTargetSquad}請稍候再前往。`
                    })
                });
                const result = await response.json();
                
                // 【關鍵修復】確實收到伺服器成功回應才跳通知
                if(result.status === 'success') {
                    alert(`已成功發送 Delay 通知給 ${currentTargetSquad} 與場控總部！`);
                } else {
                    alert('伺服器沒收到，請再點一次。');
                }
            } catch (error) {
                alert('發送失敗，請確認網路狀態。');
            }
        }

        // ================= 輪詢 (Polling) 接收即時通知 =================
        async function pollNotifications() {
            if (!currentIdentity.type) return; 

            try {
                // 【關鍵修復】在網址後面加上隨機時間戳 &t=...，徹底防堵瀏覽器快取
                const noCacheUrl = `api.php?action=get_notifications&last_id=${lastNotificationId}&t=${new Date().getTime()}`;
                const response = await fetch(noCacheUrl);
                const result = await response.json();
                
                if (result.status === 'success' && result.data.length > 0) {
                    result.data.forEach(data => {
                        lastNotificationId = data.id; 
                        
                        if (currentIdentity.type === 'coordinator') {
                            const logList = document.getElementById('global-logs');
                            if (logList.innerHTML.includes('等待系統通知')) {
                                logList.innerHTML = '';
                            }
                            const newLog = `
                                <li class="p-2 bg-white border-l-4 border-red-500 shadow-sm rounded mb-2">
                                    <div class="text-xs text-gray-500 mb-1">${data.created_at}</div>
                                    <div><span class="font-bold text-gray-800">${data.sender}</span> 通知 <span class="text-blue-600 font-medium">${data.target_squad}</span>：${data.message}</div>
                                </li>`;
                            logList.insertAdjacentHTML('afterbegin', newLog); 
                        }
                        
                        if (currentIdentity.type === 'squad') {
                            // 判斷推播對象是不是自己這隊，或是全體廣播
                            if (data.target_squad === currentIdentity.name || data.target_squad === '全體') {
                                const alertBox = document.getElementById('squad-notifications');
                                alertBox.innerText = `🚨 ${data.sender} 通知：${data.message}`;
                                alertBox.classList.remove('hidden');
                            }
                        }
                    });
                }
            } catch (error) {
                console.warn("通知同步延遲，將於下次重試。");
            }
        }
        // ================= 背景自動更新排程機制 =================
        // 更新時鐘顯示，每 1 秒跑一次
        setInterval(getDeviceTime, 1000);
        // 推播每 5 秒同步一次
        setInterval(pollNotifications, 5000);
        // 行程表每 15 秒同步一次 (縮短秒數讓換關更即時)
        setInterval(fetchSchedule, 15000);
    </script>
</body>
</html>