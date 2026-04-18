<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
$user = requireAuth();
$period = $_GET['period'] ?? 'week';
if (!in_array($period, ['week','month','year','alltime'])) $period = 'week';
$leaderboard = getLeaderboard($period);
$db = getDB();
$weekStart = date('Y-m-d', strtotime('monday this week'));
$ww = $db->prepare("SELECT user_id FROM weekly_winners WHERE week_start = ?"); $ww->execute([$weekStart]);
$weekWinnerId = (int)($ww->fetchColumn() ?: 0);
$periodLabels = ['week'=>'Woche','month'=>'Monat','year'=>'Jahr','alltime'=>'Allzeit'];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pumpfiction – Leaderboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="pf-body">
    <div class="pf-main-content pf-page-content">
        <div class="max-w-2xl mx-auto px-4 py-6">
            <h1 class="text-2xl font-black mb-4 animate-fadeIn">🏆 Leaderboard</h1>
            <div class="flex bg-[#1c1c1f] rounded-xl p-1 mb-6 animate-fadeIn">
                <?php foreach ($periodLabels as $key => $label): ?>
                <a href="?period=<?= $key ?>" class="flex-1 text-center py-2.5 rounded-lg text-sm font-semibold transition-all <?= $period === $key ? 'bg-gradient-to-r from-[#00ff88] to-[#00d4ff] text-black' : 'text-gray-400 hover:text-white' ?>"><?= $label ?></a>
                <?php endforeach; ?>
            </div>
            <div class="space-y-2 stagger-children">
                <?php foreach ($leaderboard as $i => $row):
                    $rank = $i + 1; $av = AVATARS[$row['avatar']] ?? '💪'; $isMe = (int)$row['id'] === (int)$user['id'];
                    $isWinner = (int)$row['id'] === $weekWinnerId;
                    $rankClass = match($rank) { 1 => 'pf-rank-1', 2 => 'pf-rank-2', 3 => 'pf-rank-3', default => '' };
                    $rankIcon = match($rank) { 1 => '🥇', 2 => '🥈', 3 => '🥉', default => $rank };
                ?>
                <div class="pf-card p-4 animate-fadeInUp <?= $rankClass ?> <?= $isMe ? 'pf-gradient-border' : '' ?> <?= $rank <= 3 ? 'pf-glow-green' : '' ?>">
                    <div class="flex items-center gap-3">
                        <div class="w-8 text-center font-black text-lg <?= $rank <= 3 ? 'text-[#00ff88]' : 'text-gray-500' ?>"><?= $rankIcon ?></div>
                        <div class="text-2xl"><?= $av ?></div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="font-bold truncate <?= $isMe ? 'text-[#00ff88]' : '' ?>"><?= e($row['nickname']) ?></span>
                                <?php if ($isWinner): ?><span class="text-sm">🏆</span><?php endif; ?>
                            </div>
                            <div class="flex items-center gap-2 text-xs text-gray-500">
                                <span>Lvl <?= $row['level'] ?></span><span>·</span><span>🔥 <?= $row['current_streak'] ?></span><span>·</span><span><?= $row['success_rate'] ?>%</span>
                            </div>
                        </div>
                        <div class="text-right"><div class="font-black text-[#00d4ff]"><?= number_format((int)$row['period_xp']) ?></div><div class="text-xs text-gray-500">XP</div></div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($leaderboard)): ?><div class="text-center text-gray-500 py-12"><div class="text-4xl mb-3">🏜️</div><p>Noch keine Daten.</p></div><?php endif; ?>
            </div>
        </div>
    </div>
    <?php include __DIR__ . '/includes/nav.php'; ?>
</body>
</html>
