<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
$user = requireAuth(); $db = getDB();
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?"); $stmt->execute([$user['id']]); $user = $stmt->fetch();
$challenges = getActiveChallenges(); $lifetimeTotals = getLifetimeTotals($user['id']);
$heatmapData = getHeatmapData($user['id'], 365); $weekdayStats = getWeekdayStats($user['id']);
$milestones = getUserMilestones($user['id']); $perfectDays = countPerfectDays($user['id']);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pumpfiction – Statistiken</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body class="pf-body">
    <div class="pf-main-content pf-page-content">
        <div class="max-w-2xl mx-auto px-4 py-6">
            <h1 class="text-2xl font-black mb-6">📊 Statistiken</h1>

            <h2 class="text-sm font-bold text-gray-400 uppercase tracking-wider mb-3">Lifetime Counters</h2>
            <div class="grid grid-cols-2 gap-3 mb-6">
                <?php foreach ($lifetimeTotals as $lt): ?>
                <div class="pf-card-static p-4 text-center">
                    <div class="text-2xl mb-1"><?= $lt['icon'] ?></div>
                    <div class="text-2xl font-black pf-gradient-text"><?= formatNumber((float)$lt['total'], $lt['unit']) ?></div>
                    <div class="text-xs text-gray-500 mt-1"><?= e($lt['name']) ?></div>
                    <div class="text-[0.65rem] text-gray-600 mt-0.5"><?= $lt['type'] !== 'yesno' ? 'Best: ' . formatNumber((float)$lt['best_day'], $lt['unit']) : (int)$lt['days_reached'] . ' Tage ✓' ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="grid grid-cols-3 gap-3 mb-6">
                <div class="pf-card-static p-3 text-center"><div class="text-xl font-black text-[#00ff88]"><?= $perfectDays ?></div><div class="text-[0.65rem] text-gray-500">Perfect Days</div></div>
                <div class="pf-card-static p-3 text-center"><div class="text-xl font-black text-[#00d4ff]"><?= (int)$user['longest_streak'] ?></div><div class="text-[0.65rem] text-gray-500">Längste Streak</div></div>
                <div class="pf-card-static p-3 text-center"><div class="text-xl font-black text-[#ffd700]"><?= number_format((int)$user['xp']) ?></div><div class="text-[0.65rem] text-gray-500">Gesamt XP</div></div>
            </div>

            <h2 class="text-sm font-bold text-gray-400 uppercase tracking-wider mb-3">Aktivitäts-Heatmap</h2>
            <div class="pf-card-static p-4 overflow-x-auto mb-6">
                <div class="flex gap-[2px] flex-wrap">
                    <?php
                    $hm = []; foreach ($heatmapData as $h) $hm[$h['log_date']] = $h;
                    $tc = count($challenges); $start = strtotime('-364 days');
                    for ($d = 0; $d < 365; $d++):
                        $date = date('Y-m-d', $start + ($d * 86400));
                        $e = $hm[$date] ?? null; $lvl = 0;
                        if ($e && $tc > 0) { $r = (int)$e['reached'] / $tc; if ($r > 0) $lvl = 1; if ($r >= 0.5) $lvl = 2; if ($r >= 0.75) $lvl = 3; if ($r >= 1.0) $lvl = 4; }
                    ?><div class="heatmap-cell heatmap-<?= $lvl ?>" title="<?= $date ?>"></div><?php endfor; ?>
                </div>
                <div class="flex items-center gap-2 mt-3 text-[0.6rem] text-gray-500">
                    <span>Weniger</span><div class="heatmap-cell heatmap-0"></div><div class="heatmap-cell heatmap-1"></div><div class="heatmap-cell heatmap-2"></div><div class="heatmap-cell heatmap-3"></div><div class="heatmap-cell heatmap-4"></div><span>Mehr</span>
                </div>
            </div>

            <h2 class="text-sm font-bold text-gray-400 uppercase tracking-wider mb-3">Wochentag-Performance</h2>
            <div class="pf-card-static p-4 mb-6">
                <div class="flex items-end justify-around h-32">
                    <?php $dowNames = ['Mo','Di','Mi','Do','Fr','Sa','So']; $dowD = [];
                    foreach ($weekdayStats as $ws) $dowD[$ws['day']] = $ws['rate'];
                    foreach ($dowNames as $dow): $rate = $dowD[$dow] ?? 0; ?>
                    <div class="flex flex-col items-center gap-1">
                        <span class="text-[0.6rem] text-gray-400"><?= $rate ?>%</span>
                        <div class="w-6 rounded-t" style="height: <?= max(4, $rate) ?>%; background: linear-gradient(to top, #00ff88, #00d4ff); min-height: 4px;"></div>
                        <span class="text-[0.6rem] text-gray-500"><?= $dow ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-bold text-gray-400 uppercase tracking-wider">Trend-Charts</h2>
                <select id="chart-period" class="pf-input text-xs py-1 px-2" onchange="loadCharts()">
                    <option value="7">7 Tage</option><option value="30" selected>30 Tage</option><option value="90">90 Tage</option><option value="365">1 Jahr</option>
                </select>
            </div>
            <?php foreach ($challenges as $ch): if ($ch['type'] === 'yesno') continue; ?>
            <div class="pf-card-static p-4 mb-3">
                <div class="flex items-center gap-2 mb-2"><span><?= $ch['icon'] ?></span><span class="text-sm font-semibold"><?= e($ch['name']) ?></span></div>
                <div style="height: 160px;"><canvas id="chart-<?= $ch['id'] ?>"></canvas></div>
            </div>
            <?php endforeach; ?>

            <h2 class="text-sm font-bold text-gray-400 uppercase tracking-wider mb-3 mt-6">Erfolgsquoten</h2>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-6">
                <?php foreach ($lifetimeTotals as $lt):
                    $sr = (int)$lt['days_logged'] > 0 ? round(((int)$lt['days_reached'] / (int)$lt['days_logged']) * 100) : 0; ?>
                <div class="pf-card-static p-3 text-center">
                    <canvas id="donut-<?= $lt['id'] ?>" width="80" height="80" style="max-width:80px;max-height:80px;margin:0 auto;"></canvas>
                    <div class="text-xs text-gray-400 mt-2"><?= e($lt['name']) ?></div>
                    <div class="text-sm font-bold text-[#00ff88]"><?= $sr ?>%</div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php include __DIR__ . '/includes/nav.php'; ?>
    <script src="assets/js/app.js"></script>
    <script>
        const chData = <?= json_encode(array_values(array_filter(array_map(fn($c) => $c['type'] !== 'yesno' ? ['id'=>$c['id'],'name'=>$c['name'],'target'=>(float)$c['daily_target']] : null, $challenges)))) ?>;
        const userId = <?= (int)$user['id'] ?>;
        const charts = {};
        function loadCharts() {
            const days = parseInt(document.getElementById('chart-period').value);
            chData.forEach(ch => {
                fetch(`api/get_stats.php?user_id=${userId}&challenge_id=${ch.id}&days=${days}`).then(r=>r.json()).then(d => {
                    if (d.error) return;
                    const c = document.getElementById('chart-'+ch.id); if (!c) return;
                    if (charts[ch.id]) charts[ch.id].destroy();
                    charts[ch.id] = new Chart(c, { type:'line', data: { labels: d.user.map(x=>x.log_date.substring(5)),
                        datasets: [
                            { label:'Du', data:d.user.map(x=>parseFloat(x.value)), borderColor:'#00ff88', backgroundColor:'rgba(0,255,136,0.1)', fill:true, tension:0.3, pointRadius:days<=30?3:0, borderWidth:2 },
                            { label:'Ø', data:d.average.map(x=>parseFloat(x.avg_value)), borderColor:'#00d4ff', borderDash:[5,5], tension:0.3, pointRadius:0, borderWidth:1.5 },
                            { label:'Ziel', data:Array(Math.max(d.user.length,d.average.length)).fill(ch.target), borderColor:'rgba(255,255,255,0.15)', borderDash:[3,3], pointRadius:0, borderWidth:1 }
                        ]}, options:{ responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}},
                        scales:{ x:{ticks:{color:'#6b7280',font:{size:9},maxTicksLimit:8},grid:{color:'rgba(255,255,255,0.03)'}}, y:{ticks:{color:'#6b7280',font:{size:9}},grid:{color:'rgba(255,255,255,0.03)'},beginAtZero:true} }}});
                });
            });
        }
        const ltData = <?= json_encode(array_map(fn($lt) => ['id'=>$lt['id'],'rate'=>(int)$lt['days_logged']>0?round(((int)$lt['days_reached']/(int)$lt['days_logged'])*100):0], $lifetimeTotals)) ?>;
        ltData.forEach(lt => { const c = document.getElementById('donut-'+lt.id); if (!c) return;
            new Chart(c, { type:'doughnut', data:{datasets:[{data:[lt.rate,100-lt.rate],backgroundColor:['#00ff88','rgba(255,255,255,0.05)'],borderWidth:0}]},
            options:{cutout:'70%',plugins:{legend:{display:false},tooltip:{enabled:false}}} }); });
        loadCharts();
    </script>
</body>
</html>
