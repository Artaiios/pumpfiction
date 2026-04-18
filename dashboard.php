<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/gamification.php';

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$user = requireAuth();
$db = getDB();
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?"); $stmt->execute([$user['id']]); $user = $stmt->fetch();
checkMonthTransition();
if (date('N') == 1) checkWeeklyWinner();
updateStreak($user['id']);
$stmt->execute([$user['id']]); $user = $stmt->fetch();

// Server-side date handling (fixes reload bug)
$viewDate = ($_GET['date'] ?? '') === 'yesterday' ? yesterday() : today();
$isYesterdayView = ($viewDate === yesterday());

$challenges = getUserChallenges($user['id']);
$activeLogs = getDayLogs($user['id'], $viewDate);
$quote = getMotivationQuote($user);
$milestones = getUserMilestones($user['id']);
$avatar = AVATARS[$user['avatar']] ?? '💪';
$levelTitle = getLevelTitle((int)$user['level']);
$nextLevelXP = getNextLevelXP((int)$user['level']);
$currentLevelXP = getCurrentLevelXP((int)$user['level']);
$xpProgress = $nextLevelXP > $currentLevelXP ? round((($user['xp'] - $currentLevelXP) / ($nextLevelXP - $currentLevelXP)) * 100) : 100;

$completedToday = 0; $totalActive = 0;
foreach ($challenges as $ch) {
    if ($ch['is_active'] || ($ch['visibility'] ?? '') === 'private') {
        $totalActive++;
        $log = $activeLogs[$ch['id']] ?? null;
        if ($log && (float)$log['value'] >= (float)$ch['daily_target']) $completedToday++;
    }
}
$csrf = csrfToken();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pumpfiction – Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <meta name="csrf-token" content="<?= e($csrf) ?>">
</head>
<body class="pf-body">
    <div class="pf-toast-container" id="toasts"></div>
    <div class="pf-main-content pf-page-content">
        <div class="max-w-2xl mx-auto px-4 py-6">
            <div class="flex items-center justify-between mb-4 animate-fadeIn">
                <div class="flex items-center gap-3">
                    <div class="text-4xl"><?= $avatar ?></div>
                    <div>
                        <h1 class="text-xl font-bold">Hey, <?= e($user['nickname']) ?>!</h1>
                        <span class="pf-badge pf-badge-fame">Lvl <?= $user['level'] ?> · <?= e($levelTitle) ?></span>
                    </div>
                </div>
                <div class="text-right">
                    <div class="flex items-center gap-1 text-2xl <?= $user['current_streak'] > 0 ? 'animate-fire' : '' ?>">🔥 <span class="font-black text-[#00ff88]"><?= $user['current_streak'] ?></span></div>
                    <span class="text-[0.65rem] text-gray-500">Streak</span>
                </div>
            </div>

            <div class="pf-card-static p-3 mb-4 animate-fadeIn">
                <div class="flex items-center justify-between text-xs mb-1">
                    <span class="text-gray-400"><?= number_format($user['xp']) ?> XP</span>
                    <span class="text-gray-500"><?= number_format($nextLevelXP) ?> XP → Lvl <?= (int)$user['level'] + 1 ?></span>
                </div>
                <div class="pf-progress-bg pf-progress-lg"><div class="pf-progress-bar pf-progress-green" style="width: <?= $xpProgress ?>%"></div></div>
            </div>

            <div class="pf-card-static p-4 mb-5 text-center animate-fadeIn"><p class="text-sm text-gray-300 italic"><?= e($quote) ?></p></div>

            <div class="flex items-center justify-between mb-4 animate-fadeIn">
                <div class="text-sm">
                    <span class="text-gray-400"><?= $isYesterdayView ? 'Gestern:' : 'Heute:' ?></span>
                    <span class="font-bold text-[#00ff88]"><?= $completedToday ?></span><span class="text-gray-500">/<?= $totalActive ?> Challenges</span>
                </div>
                <div class="pf-date-toggle">
                    <button class="pf-date-btn <?= !$isYesterdayView ? 'active' : '' ?>" onclick="switchDate('today')">Heute</button>
                    <button class="pf-date-btn <?= $isYesterdayView ? 'active' : '' ?>" onclick="switchDate('yesterday')">Gestern</button>
                </div>
            </div>

            <div class="space-y-3 stagger-children" id="challenge-list">
                <?php foreach ($challenges as $ch):
                    $log = $activeLogs[$ch['id']] ?? null;
                    $currentVal = $log ? (float)$log['value'] : 0;
                    $target = (float)$ch['daily_target'];
                    $isYesNo = $ch['type'] === 'yesno';
                    $pct = $isYesNo ? ($currentVal >= 1 ? 100 : 0) : ($target > 0 ? min(round(($currentVal / $target) * 100), 999) : 0);
                    $progressClass = $pct >= 100 ? 'pf-progress-green' : ($pct >= 50 ? 'pf-progress-orange' : 'pf-progress-red');
                    $quickButtons = QUICK_ADD_BUTTONS[$ch['unit']] ?? [];
                ?>
                <div class="pf-card p-4 animate-fadeInUp <?= $pct >= 100 ? 'pf-glow-green' : '' ?>" id="card-<?= $ch['id'] ?>">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-2">
                            <span class="text-xl"><?= $ch['icon'] ?></span>
                            <div>
                                <h3 class="font-semibold text-sm"><?= e($ch['name']) ?></h3>
                                <?php if (!$isYesNo): ?><p class="text-xs text-gray-500">Ziel: <?= formatNumber($target) ?> <?= e($ch['unit'] ?? '') ?></p><?php endif; ?>
                            </div>
                        </div>
                        <?php if ($isYesNo): ?>
                        <button class="pf-toggle <?= $currentVal >= 1 ? 'active' : '' ?>" onclick="toggleYesNo(<?= $ch['id'] ?>, this)" data-value="<?= $currentVal >= 1 ? 1 : 0 ?>"></button>
                        <?php else: ?>
                        <div class="text-right">
                            <span class="text-lg font-black <?= $pct >= 100 ? 'text-[#00ff88]' : 'text-white' ?>" id="val-<?= $ch['id'] ?>"><?= formatNumber($currentVal, $ch['unit']) ?></span>
                            <span class="text-xs text-gray-500"> / <?= formatNumber($target) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php if (!$isYesNo): ?>
                    <div class="pf-progress-bg mb-3"><div class="pf-progress-bar <?= $progressClass ?>" style="width: <?= min($pct, 100) ?>%" id="prog-<?= $ch['id'] ?>"></div></div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <?php foreach ($quickButtons as $qv): ?>
                        <button class="pf-quick-btn" onclick="quickAdd(<?= $ch['id'] ?>, <?= $qv ?>)">+<?= $qv >= 1000 ? number_format($qv/1000, $qv % 1000 ? 1 : 0) . 'k' : $qv ?></button>
                        <?php endforeach; ?>
                        <div class="flex items-center gap-1 ml-auto">
                            <input type="number" class="pf-input text-sm w-20 text-center py-1 px-2" id="custom-<?= $ch['id'] ?>" placeholder="Wert" min="0" step="<?= $ch['unit'] === 'Stunden' ? '0.5' : '1' ?>">
                            <button class="pf-quick-btn" onclick="customAdd(<?= $ch['id'] ?>)">+</button>
                            <?php if ($currentVal > 0): ?>
                            <button class="pf-quick-btn" style="color:#ff6b35;border-color:rgba(255,107,53,0.3);background:rgba(255,107,53,0.08)" onclick="resetValue(<?= $ch['id'] ?>)" title="Zurücksetzen">↺</button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php else: ?>
                    <?php if ($currentVal > 0): ?><div class="text-right mt-1"><button class="text-[0.65rem] text-gray-500 hover:text-[#ff6b35] transition" onclick="resetValue(<?= $ch['id'] ?>)">↺ Zurücksetzen</button></div><?php endif; ?>
                    <?php endif; ?>
                    <div class="mt-2 text-right"><span class="text-xs font-semibold <?= $pct >= 100 ? 'text-[#00ff88]' : ($pct >= 50 ? 'text-[#ffaa00]' : 'text-[#ff6b35]') ?>" id="pct-<?= $ch['id'] ?>"><?= $pct ?>%</span></div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if (!empty($milestones)): ?>
            <div class="mt-6">
                <h2 class="text-sm font-bold text-gray-400 uppercase tracking-wider mb-3">🎯 Deine Etappenziele</h2>
                <?php foreach (array_filter($milestones, fn($m) => !(int)$m['is_reached']) as $ms):
                    $msPct = (float)$ms['target_value'] > 0 ? min(round(((float)$ms['current_total'] / (float)$ms['target_value']) * 100), 100) : 0; ?>
                <div class="pf-card-static p-3 mb-2">
                    <div class="flex items-center justify-between text-sm mb-1">
                        <span><?= $ms['icon'] ?> <?= e($ms['description'] ?? $ms['challenge_name']) ?></span>
                        <span class="text-xs text-gray-400"><?= formatNumber((float)$ms['current_total']) ?> / <?= formatNumber((float)$ms['target_value']) ?></span>
                    </div>
                    <div class="pf-progress-bg"><div class="pf-progress-bar pf-progress-green" style="width: <?= $msPct ?>%"></div></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php include __DIR__ . '/includes/nav.php'; ?>
    <script src="assets/js/app.js"></script>
    <script>
        const viewDateStr = '<?= e($viewDate) ?>';
        function switchDate(w) { window.location.href = 'dashboard.php' + (w === 'yesterday' ? '?date=yesterday' : ''); }
        function quickAdd(id, v) { logEntry(id, v, true); }
        function customAdd(id) { const i = document.getElementById('custom-'+id); const v = parseFloat(i.value); if (isNaN(v)||v<=0) return; logEntry(id, v, true); i.value = ''; }
        function toggleYesNo(id, btn) { const c = parseInt(btn.dataset.value); const n = c ? 0 : 1; logEntry(id, n, false); btn.dataset.value = n; btn.classList.toggle('active', n===1); }
        function resetValue(id) { if (!confirm('Wert für diese Challenge zurücksetzen?')) return; logEntry(id, 0, false, true); }
        function logEntry(id, value, isAdd, isReset=false) {
            const csrf = document.querySelector('meta[name="csrf-token"]').content;
            fetch('api/log_entry.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ challenge_id: id, value, date: viewDateStr, is_add: isAdd, reset: isReset }) })
            .then(r => { if (!r.ok) throw new Error('HTTP '+r.status); return r.json(); })
            .then(data => {
                if (data.error) { showToast(data.error, 'error'); return; }
                if (isReset) { showToast('Wert zurückgesetzt', 'info'); setTimeout(() => location.reload(), 500); return; }
                const ve = document.getElementById('val-'+id), pe = document.getElementById('pct-'+id), pr = document.getElementById('prog-'+id), ca = document.getElementById('card-'+id);
                if (ve) { ve.textContent = fmtN(data.value); ve.classList.add('animate-countUp'); setTimeout(()=>ve.classList.remove('animate-countUp'), 300); }
                if (pe) { pe.textContent = data.percentage+'%'; pe.className = 'text-xs font-semibold '+(data.percentage>=100?'text-[#00ff88]':data.percentage>=50?'text-[#ffaa00]':'text-[#ff6b35]'); }
                if (pr) { pr.style.width = Math.min(data.percentage,100)+'%'; pr.className = 'pf-progress-bar '+(data.percentage>=100?'pf-progress-green':data.percentage>=50?'pf-progress-orange':'pf-progress-red'); }
                if (ca) ca.classList.toggle('pf-glow-green', data.target_reached);
                if (data.events) data.events.forEach(e => {
                    if (e.type==='badge') { showToast(e.badge.icon+' Badge: '+e.badge.name, 'badge'); triggerConfetti(); }
                    else if (e.type==='perfect_day') { showToast('✨ PERFECT DAY!', 'fame'); triggerConfetti(); }
                    else if (e.type==='daily_goal') showToast('✅ '+e.challenge+' geschafft!', 'xp');
                    else if (e.type==='milestone') { showToast('🎯 Etappenziel! +100 XP', 'badge'); triggerConfetti(); }
                });
            }).catch(() => showToast('Fehler – bitte Seite neu laden', 'error'));
        }
        function fmtN(n) { if (n>=10000) return (n/1000).toFixed(1)+'k'; if (Number.isInteger(n)) return n.toLocaleString('de-DE'); return n.toLocaleString('de-DE',{maximumFractionDigits:1}); }
    </script>
</body>
</html>
