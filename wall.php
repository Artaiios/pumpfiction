<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
$user = requireAuth(); $db = getDB();
$filter = $_GET['filter'] ?? 'all';
$page = max(1, (int)($_GET['page'] ?? 1)); $perPage = 30; $offset = ($page - 1) * $perPage;
$where = "WHERE we.is_deleted = 0";
if ($filter === 'fame') $where .= " AND we.entry_type = 'fame'";
elseif ($filter === 'shame') $where .= " AND we.entry_type = 'shame'";
$total = (int)$db->query("SELECT COUNT(*) FROM wall_entries we {$where}")->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));
$entries = $db->query("SELECT we.*, u.nickname, u.avatar FROM wall_entries we LEFT JOIN users u ON we.user_id = u.id {$where} ORDER BY we.created_at DESC LIMIT {$perPage} OFFSET {$offset}")->fetchAll();
function timeAgo(string $dt): string { $d = time() - strtotime($dt); if ($d < 60) return 'gerade eben'; if ($d < 3600) return floor($d/60).' Min.'; if ($d < 86400) return floor($d/3600).' Std.'; if ($d < 604800) return floor($d/86400).' Tage'; return date('d.m.Y', strtotime($dt)); }
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pumpfiction – Wall</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="pf-body">
    <div class="pf-main-content pf-page-content">
        <div class="max-w-2xl mx-auto px-4 py-6">
            <h1 class="text-2xl font-black mb-4">📢 Wall of Shame & Fame</h1>
            <div class="flex bg-[#1c1c1f] rounded-xl p-1 mb-6">
                <?php foreach (['all'=>'Alles','fame'=>'🌟 Fame','shame'=>'😬 Shame'] as $k=>$l): ?>
                <a href="?filter=<?= $k ?>" class="flex-1 text-center py-2.5 rounded-lg text-sm font-semibold transition-all <?= $filter === $k ? 'bg-gradient-to-r from-[#00ff88] to-[#00d4ff] text-black' : 'text-gray-400' ?>"><?= $l ?></a>
                <?php endforeach; ?>
            </div>
            <div class="space-y-2 stagger-children">
                <?php foreach ($entries as $e):
                    $bc = match($e['entry_type']) { 'fame'=>'border-l-[#00ff88]', 'shame'=>'border-l-[#ff6b35]', default=>'border-l-[#00d4ff]' };
                    $bg = match($e['entry_type']) { 'fame'=>'bg-[#00ff88]/[0.03]', 'shame'=>'bg-[#ff6b35]/[0.03]', default=>'' };
                ?>
                <div class="pf-card-static p-4 border-l-2 <?= $bc ?> <?= $bg ?> animate-fadeInUp">
                    <div class="flex items-start gap-3">
                        <div class="text-xl flex-shrink-0"><?= $e['icon'] ?></div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm"><?= e($e['message']) ?></p>
                            <div class="flex items-center gap-2 mt-1.5 text-[0.65rem] text-gray-500">
                                <?php if ($e['nickname']): ?><span><?= AVATARS[$e['avatar']??'']??'📢' ?> <?= e($e['nickname']) ?></span><span>·</span><?php endif; ?>
                                <span><?= timeAgo($e['created_at']) ?></span>
                                <span class="pf-badge pf-badge-<?= $e['entry_type'] ?>"><?= $e['entry_type'] ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($entries)): ?><div class="text-center text-gray-500 py-12"><div class="text-4xl mb-3">🦗</div><p>Noch nichts auf der Wall.</p></div><?php endif; ?>
            </div>
            <?php if ($totalPages > 1): ?>
            <div class="flex items-center justify-center gap-2 mt-6">
                <?php if ($page > 1): ?><a href="?filter=<?= $filter ?>&page=<?= $page-1 ?>" class="pf-btn-ghost text-xs">← Zurück</a><?php endif; ?>
                <span class="text-xs text-gray-500">Seite <?= $page ?>/<?= $totalPages ?></span>
                <?php if ($page < $totalPages): ?><a href="?filter=<?= $filter ?>&page=<?= $page+1 ?>" class="pf-btn-ghost text-xs">Weiter →</a><?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php include __DIR__ . '/includes/nav.php'; ?>
</body>
</html>
