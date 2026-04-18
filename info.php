<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
$user = requireAuth(); $db = getDB();
if (isset($_GET['done'])) { $db->prepare("UPDATE users SET has_seen_intro = 1 WHERE id = ?")->execute([$user['id']]); header('Location: dashboard.php'); exit; }
$avatar = AVATARS[$user['avatar']] ?? '💪';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pumpfiction – Anleitung</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="pf-body min-h-screen">
    <div class="max-w-2xl mx-auto px-4 py-8">
        <div class="text-center mb-8 animate-fadeIn">
            <div class="text-6xl mb-3"><?= $avatar ?></div>
            <h1 class="text-3xl font-black pf-gradient-text mb-1">Willkommen, <?= e($user['nickname']) ?>!</h1>
            <p class="text-gray-400">So funktioniert Pumpfiction</p>
        </div>
        <div class="space-y-4 stagger-children">
            <details class="pf-card p-5 animate-fadeInUp group" open>
                <summary class="flex items-center justify-between cursor-pointer list-none"><div class="flex items-center gap-3"><span class="text-2xl">🎮</span><h2 class="text-lg font-bold">Was ist Pumpfiction?</h2></div><span class="text-gray-500 group-open:rotate-180 transition-transform">▼</span></summary>
                <div class="mt-4 text-gray-300 space-y-3 text-sm leading-relaxed">
                    <p><strong class="text-white">Pumpfiction (Tracking Edition)</strong> ist euer gemeinsamer Fitness-Challenge-Tracker! Trackt tägliche Challenges, sammelt XP, steigt in Levels auf und fordert euch gegenseitig heraus.</p>
                    <p>Das Ziel: <strong class="text-[#00ff88]">Jeden Tag ein bisschen besser werden</strong> – mit Spaß und freundschaftlichem Trash-Talk. 😏</p>
                </div>
            </details>
            <details class="pf-card p-5 animate-fadeInUp group">
                <summary class="flex items-center justify-between cursor-pointer list-none"><div class="flex items-center gap-3"><span class="text-2xl">💪</span><h2 class="text-lg font-bold">Die Challenges</h2></div><span class="text-gray-500 group-open:rotate-180 transition-transform">▼</span></summary>
                <div class="mt-4 text-gray-300 space-y-3 text-sm leading-relaxed">
                    <p><strong class="text-white">10 Start-Challenges</strong> für alle:</p>
                    <div class="grid grid-cols-2 gap-2 my-3">
                        <div class="bg-[#141416] rounded-lg p-2 text-xs">👟 10.000 Schritte</div><div class="bg-[#141416] rounded-lg p-2 text-xs">💪 70 Push-Ups</div>
                        <div class="bg-[#141416] rounded-lg p-2 text-xs">🧘 180 Sek. Plank</div><div class="bg-[#141416] rounded-lg p-2 text-xs">💧 3.000 ml Wasser</div>
                        <div class="bg-[#141416] rounded-lg p-2 text-xs">🚫 Kein Alkohol</div><div class="bg-[#141416] rounded-lg p-2 text-xs">🥗 Clean Eating</div>
                        <div class="bg-[#141416] rounded-lg p-2 text-xs">🧘‍♂️ 15 Min Meditation</div><div class="bg-[#141416] rounded-lg p-2 text-xs">😴 7h Schlaf</div>
                        <div class="bg-[#141416] rounded-lg p-2 text-xs">⏱️ 12h Fasten</div><div class="bg-[#141416] rounded-lg p-2 text-xs">🥶 Kalt duschen</div>
                    </div>
                    <p><strong class="text-[#00d4ff]">Zahlenwerte</strong> werden über den Tag aufaddiert. <strong class="text-[#00d4ff]">Ja/Nein</strong> per Toggle. Den <strong class="text-white">Vortag</strong> nachtragen geht, weiter zurück nicht.</p>
                </div>
            </details>
            <details class="pf-card p-5 animate-fadeInUp group">
                <summary class="flex items-center justify-between cursor-pointer list-none"><div class="flex items-center gap-3"><span class="text-2xl">🗳️</span><h2 class="text-lg font-bold">Das Voting-System</h2></div><span class="text-gray-500 group-open:rotate-180 transition-transform">▼</span></summary>
                <div class="mt-4 text-gray-300 space-y-3 text-sm leading-relaxed">
                    <p>Jeden Monat stimmt ihr demokratisch ab:</p>
                    <div class="bg-[#141416] rounded-xl p-4 space-y-3">
                        <div><p class="text-white font-semibold mb-1">👍👎 Challenges behalten/entfernen</p><p class="text-xs text-gray-400">Bei >50% Daumen runter wird deaktiviert – privat weiterführen geht trotzdem.</p></div>
                        <div><p class="text-white font-semibold mb-1">🎯 Tagesziele anpassen</p><p class="text-xs text-gray-400">Alternatives Ziel vorschlagen. Der Median wird übernommen.</p></div>
                        <div><p class="text-white font-semibold mb-1">💡 Neue Challenges vorschlagen</p><p class="text-xs text-gray-400">Bei >50% Zustimmung wird sie aktiviert.</p></div>
                    </div>
                    <div class="bg-[#ff6b35]/10 border border-[#ff6b35]/20 rounded-xl p-4">
                        <p class="text-[#ff6b35] font-semibold text-sm mb-1">⚠️ Finalphase</p>
                        <p class="text-xs text-gray-300">In den <strong>letzten 3 Tagen</strong> nur noch <strong>eine Änderung</strong>. Am 1. werden Ergebnisse umgesetzt.</p>
                    </div>
                </div>
            </details>
            <details class="pf-card p-5 animate-fadeInUp group">
                <summary class="flex items-center justify-between cursor-pointer list-none"><div class="flex items-center gap-3"><span class="text-2xl">⭐</span><h2 class="text-lg font-bold">XP, Levels & Streaks</h2></div><span class="text-gray-500 group-open:rotate-180 transition-transform">▼</span></summary>
                <div class="mt-4 text-gray-300 space-y-3 text-sm leading-relaxed">
                    <div class="bg-[#141416] rounded-xl p-4 space-y-2 text-xs">
                        <div class="flex justify-between"><span>Tagesziel erreicht</span><span class="text-[#00ff88]">+10 XP</span></div>
                        <div class="flex justify-between"><span>Perfect Day (alle Ziele)</span><span class="text-[#00ff88]">+25 XP</span></div>
                        <div class="flex justify-between"><span>Streak-Bonus pro Tag</span><span class="text-[#00ff88]">+5 XP × Tage</span></div>
                        <div class="flex justify-between"><span>Wochenziel</span><span class="text-[#00ff88]">+50 XP</span></div>
                        <div class="flex justify-between"><span>Monatsziel</span><span class="text-[#00ff88]">+200 XP</span></div>
                        <div class="flex justify-between"><span>Persönliches Etappenziel</span><span class="text-[#00ff88]">+100 XP</span></div>
                    </div>
                    <p class="font-semibold text-white mt-3">Level-Stufen:</p>
                    <div class="bg-[#141416] rounded-xl p-4 space-y-1 text-xs">
                        <?php foreach (LEVELS as $lvl => $data): ?>
                        <div class="flex justify-between"><span>Lvl <?= $lvl ?>: <?= e($data['title']) ?></span><span class="text-gray-500"><?= number_format($data['xp']) ?> XP</span></div>
                        <?php endforeach; ?>
                    </div>
                    <p><strong class="text-white">Streaks</strong> 🔥 – Alle aktiven Challenges an aufeinanderfolgenden Tagen schaffen = Streak aufbauen!</p>
                </div>
            </details>
            <details class="pf-card p-5 animate-fadeInUp group">
                <summary class="flex items-center justify-between cursor-pointer list-none"><div class="flex items-center gap-3"><span class="text-2xl">📢</span><h2 class="text-lg font-bold">Wall of Shame & Fame</h2></div><span class="text-gray-500 group-open:rotate-180 transition-transform">▼</span></summary>
                <div class="mt-4 text-gray-300 space-y-3 text-sm leading-relaxed">
                    <div class="bg-[#00ff88]/5 border border-[#00ff88]/10 rounded-lg p-3"><span class="font-semibold text-[#00ff88]">Fame</span> 🌟 – Level-Ups, Badges, Streak-Meilensteine, Wochensieger</div>
                    <div class="bg-[#ff6b35]/5 border border-[#ff6b35]/10 rounded-lg p-3"><span class="font-semibold text-[#ff6b35]">Shame</span> 😬 – Streak verloren, überholt, nichts eingetragen</div>
                    <p class="text-xs text-gray-400">Alles augenzwinkernd – wir sind Freunde! 😄</p>
                </div>
            </details>
            <details class="pf-card p-5 animate-fadeInUp group">
                <summary class="flex items-center justify-between cursor-pointer list-none"><div class="flex items-center gap-3"><span class="text-2xl">💡</span><h2 class="text-lg font-bold">Tipps</h2></div><span class="text-gray-500 group-open:rotate-180 transition-transform">▼</span></summary>
                <div class="mt-4 text-gray-300 space-y-2 text-sm">
                    <p>✅ <strong class="text-white">Jeden Tag eintragen</strong> – auch bei Teilzielen</p>
                    <p>✅ <strong class="text-white">Schnelltasten nutzen</strong> – ein Tap zum Aufaddieren</p>
                    <p>✅ <strong class="text-white">Persönliche Ziele setzen</strong> – im Profil</p>
                    <p>✅ <strong class="text-white">Monatlich abstimmen</strong> – Challenges mitgestalten</p>
                    <p>✅ <strong class="text-white">Wall checken</strong> – Trash-Talk motiviert! 😏</p>
                </div>
            </details>
        </div>
        <div class="text-center mt-8"><a href="?done=1" class="inline-block pf-btn-primary text-lg px-10 py-4">Los geht's! 🚀</a><p class="text-gray-500 text-xs mt-3">Jederzeit im Profil erneut aufrufbar.</p></div>
    </div>
</body>
</html>
