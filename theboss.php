<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/voting_logic.php';
session_start(); $db = getDB(); $msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['admin_action'] ?? '';
    switch ($action) {
        case 'delete_user': $db->prepare("UPDATE users SET is_deleted = 1, cookie_token = NULL WHERE id = ?")->execute([(int)$_POST['user_id']]); $msg = 'User deaktiviert.'; break;
        case 'restore_user': $db->prepare("UPDATE users SET is_deleted = 0 WHERE id = ?")->execute([(int)$_POST['user_id']]); $msg = 'User wiederhergestellt.'; break;
        case 'toggle_challenge': $db->prepare("UPDATE challenges SET is_active = NOT is_active WHERE id = ?")->execute([(int)$_POST['challenge_id']]); $msg = 'Status geändert.'; break;
        case 'update_target': $t = (float)$_POST['new_target']; if ($t > 0) { $db->prepare("UPDATE challenges SET daily_target = ? WHERE id = ?")->execute([$t, (int)$_POST['challenge_id']]); $msg = 'Ziel aktualisiert.'; } break;
        case 'delete_wall': $db->prepare("UPDATE wall_entries SET is_deleted = 1 WHERE id = ?")->execute([(int)$_POST['wall_id']]); $msg = 'Gelöscht.'; break;
        case 'force_voting': processMonthlyVoting(); $msg = 'Voting ausgewertet.'; break;
    }
}
$users = $db->query("SELECT *, (SELECT COUNT(*) FROM challenge_logs WHERE user_id = users.id) as log_count FROM users ORDER BY is_deleted ASC, last_login DESC")->fetchAll();
$challenges = $db->query("SELECT * FROM challenges ORDER BY sort_order")->fetchAll();
$recentWall = $db->query("SELECT we.*, u.nickname FROM wall_entries we LEFT JOIN users u ON we.user_id = u.id WHERE we.is_deleted = 0 ORDER BY we.created_at DESC LIMIT 20")->fetchAll();
$totalLogs = (int)$db->query("SELECT COUNT(*) FROM challenge_logs")->fetchColumn();
$activeUsers = (int)$db->query("SELECT COUNT(*) FROM users WHERE is_deleted = 0 AND last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
$dbSize = $db->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) FROM information_schema.tables WHERE table_schema = '" . DB_NAME . "'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pumpfiction – Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="pf-body">
    <div class="max-w-4xl mx-auto px-4 py-6">
        <div class="flex items-center justify-between mb-6"><h1 class="text-2xl font-black pf-gradient-text">🔧 Admin</h1><a href="dashboard.php" class="pf-btn-ghost text-xs">← App</a></div>
        <?php if ($msg): ?><div class="bg-[#00ff88]/10 border border-[#00ff88]/30 rounded-xl p-3 mb-4 text-sm text-[#00ff88]"><?= e($msg) ?></div><?php endif; ?>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
            <div class="pf-card-static p-4 text-center"><div class="text-2xl font-black text-[#00ff88]"><?= count(array_filter($users, fn($u) => !(int)$u['is_deleted'])) ?></div><div class="text-xs text-gray-500">User</div></div>
            <div class="pf-card-static p-4 text-center"><div class="text-2xl font-black text-[#00d4ff]"><?= $activeUsers ?></div><div class="text-xs text-gray-500">Aktiv (7d)</div></div>
            <div class="pf-card-static p-4 text-center"><div class="text-2xl font-black text-white"><?= number_format($totalLogs) ?></div><div class="text-xs text-gray-500">Logs</div></div>
            <div class="pf-card-static p-4 text-center"><div class="text-2xl font-black text-gray-400"><?= $dbSize ?> MB</div><div class="text-xs text-gray-500">DB</div></div>
        </div>

        <div class="pf-card p-5 mb-6"><h2 class="font-bold mb-4">👥 User</h2>
            <div class="overflow-x-auto"><table class="w-full text-sm">
                <thead><tr class="text-left text-xs text-gray-500 border-b border-gray-800"><th class="pb-2">User</th><th class="pb-2">Lvl</th><th class="pb-2">XP</th><th class="pb-2">Logs</th><th class="pb-2">Login</th><th class="pb-2">Aktion</th></tr></thead>
                <tbody><?php foreach ($users as $u): ?>
                <tr class="border-b border-gray-800/50 <?= (int)$u['is_deleted']?'opacity-40':'' ?>">
                    <td class="py-2"><?= AVATARS[$u['avatar']]??'💪' ?> <?= e($u['nickname']) ?></td><td class="py-2"><?= $u['level'] ?></td><td class="py-2"><?= number_format((int)$u['xp']) ?></td><td class="py-2"><?= (int)$u['log_count'] ?></td>
                    <td class="py-2 text-xs text-gray-400"><?= $u['last_login'] ? date('d.m. H:i', strtotime($u['last_login'])) : '–' ?></td>
                    <td class="py-2"><form method="POST" class="inline" onsubmit="return confirm('Sicher?')"><input type="hidden" name="user_id" value="<?= $u['id'] ?>"><input type="hidden" name="admin_action" value="<?= (int)$u['is_deleted']?'restore_user':'delete_user' ?>"><button class="text-xs <?= (int)$u['is_deleted']?'text-[#00ff88]':'text-[#ff6b35]' ?> hover:underline"><?= (int)$u['is_deleted']?'Restore':'Deaktivieren' ?></button></form></td>
                </tr><?php endforeach; ?></tbody>
            </table></div>
        </div>

        <div class="pf-card p-5 mb-6"><h2 class="font-bold mb-4">💪 Challenges</h2>
            <?php foreach ($challenges as $ch): ?>
            <div class="bg-[#141416] rounded-lg p-3 flex items-center gap-3 flex-wrap mb-2 <?= !(int)$ch['is_active']?'opacity-40':'' ?>">
                <span class="text-lg"><?= $ch['icon'] ?></span><span class="font-semibold text-sm flex-1"><?= e($ch['name']) ?></span>
                <span class="text-xs text-gray-400"><?= formatNumber((float)$ch['daily_target']) ?> <?= e($ch['unit']??'') ?></span>
                <form method="POST" class="flex items-center gap-1"><input type="hidden" name="challenge_id" value="<?= $ch['id'] ?>"><input type="hidden" name="admin_action" value="update_target"><input type="number" name="new_target" class="pf-input text-xs py-1 px-2 w-20" placeholder="Neues Ziel" step="any"><button class="pf-btn-secondary text-xs py-1">Setzen</button></form>
                <form method="POST"><input type="hidden" name="challenge_id" value="<?= $ch['id'] ?>"><input type="hidden" name="admin_action" value="toggle_challenge"><button class="text-xs <?= (int)$ch['is_active']?'text-[#ff6b35]':'text-[#00ff88]' ?>"><?= (int)$ch['is_active']?'Deaktivieren':'Aktivieren' ?></button></form>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="pf-card p-5 mb-6"><h2 class="font-bold mb-4">🗳️ Voting</h2>
            <form method="POST" onsubmit="return confirm('Voting jetzt auswerten?')"><input type="hidden" name="admin_action" value="force_voting"><button class="pf-btn-primary text-sm">Jetzt auswerten</button></form>
        </div>

        <div class="pf-card p-5 mb-6"><h2 class="font-bold mb-4">📢 Wall (letzte 20)</h2>
            <?php foreach ($recentWall as $w): ?>
            <div class="bg-[#141416] rounded-lg p-2 flex items-center gap-2 text-xs mb-1">
                <span><?= $w['icon'] ?></span><span class="flex-1 truncate"><?= e($w['message']) ?></span>
                <span class="text-gray-500 flex-shrink-0"><?= date('d.m. H:i', strtotime($w['created_at'])) ?></span>
                <form method="POST"><input type="hidden" name="wall_id" value="<?= $w['id'] ?>"><input type="hidden" name="admin_action" value="delete_wall"><button class="text-[#ff6b35]">✕</button></form>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>
