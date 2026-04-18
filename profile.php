<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
$user = requireAuth(); $db = getDB();
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?"); $stmt->execute([$user['id']]); $user = $stmt->fetch();
$badges = getUserBadges($user['id']); $milestones = getUserMilestones($user['id']);
$challenges = getAllChallenges(); $avatar = AVATARS[$user['avatar']] ?? '💪';
$csrf = csrfToken();
$unlockedCount = count(array_filter($badges, fn($b) => (int)$b['unlocked']));
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pumpfiction – Profil</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <meta name="csrf-token" content="<?= e($csrf) ?>">
</head>
<body class="pf-body">
    <div class="pf-toast-container" id="toasts"></div>
    <div class="pf-main-content pf-page-content">
        <div class="max-w-2xl mx-auto px-4 py-6">
            <div class="text-center mb-6 animate-fadeIn">
                <div class="text-6xl mb-2"><?= $avatar ?></div>
                <h1 class="text-2xl font-black"><?= e($user['nickname']) ?></h1>
                <p class="text-sm text-gray-400">Level <?= $user['level'] ?> · <?= e(getLevelTitle((int)$user['level'])) ?></p>
                <p class="text-xs text-gray-500 mt-1">Dabei seit <?= date('d.m.Y', strtotime($user['created_at'])) ?></p>
            </div>

            <div class="pf-card p-5 mb-4">
                <h2 class="font-bold text-sm mb-4">✏️ Profil bearbeiten</h2>
                <div class="mb-4"><label class="block text-xs text-gray-400 mb-2">Avatar</label>
                    <div class="grid grid-cols-7 gap-2">
                        <?php foreach (AVATARS as $key => $emoji): ?>
                        <button type="button" onclick="updateAvatar('<?= $key ?>')" id="pav-<?= $key ?>" class="text-2xl p-2 rounded-lg border-2 transition-all hover:scale-110 <?= $key === $user['avatar'] ? 'border-[#00ff88] bg-[#00ff88]/10' : 'border-transparent bg-[#141416]' ?>"><?= $emoji ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="mb-3"><label class="block text-xs text-gray-400 mb-1">Nickname</label>
                    <div class="flex gap-2"><input type="text" id="new-nickname" class="pf-input text-sm flex-1" value="<?= e($user['nickname']) ?>" maxlength="20"><button class="pf-btn-secondary text-xs" onclick="updateNickname()">Ändern</button></div>
                </div>
                <div><label class="block text-xs text-gray-400 mb-1">Neue PIN</label>
                    <div class="flex gap-2"><input type="password" id="new-pin" class="pf-input text-sm flex-1" placeholder="4 Ziffern" maxlength="4" inputmode="numeric"><button class="pf-btn-secondary text-xs" onclick="updatePin()">Ändern</button></div>
                </div>
            </div>

            <div class="pf-card p-5 mb-4">
                <h2 class="font-bold text-sm mb-1">🏅 Badges</h2>
                <p class="text-xs text-gray-500 mb-4"><?= $unlockedCount ?> / <?= count($badges) ?> freigeschaltet</p>
                <div class="grid grid-cols-3 sm:grid-cols-4 gap-3">
                    <?php foreach ($badges as $b): ?>
                    <div class="text-center p-3 rounded-xl <?= (int)$b['unlocked'] ? 'bg-[#141416]' : 'bg-[#141416] opacity-30' ?> hover:scale-105 transition-all" title="<?= e($b['description']) ?>">
                        <div class="text-2xl mb-1"><?= $b['icon'] ?></div>
                        <div class="text-[0.65rem] font-semibold"><?= e($b['name']) ?></div>
                        <div class="text-[0.55rem] text-gray-600 mt-0.5"><?= (int)$b['unlocked'] ? date('d.m.Y', strtotime($b['unlocked_at'])) : e($b['description']) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="pf-card p-5 mb-4">
                <h2 class="font-bold text-sm mb-4">🎯 Persönliche Etappenziele</h2>
                <?php foreach ($milestones as $ms): $msPct = (float)$ms['target_value'] > 0 ? min(round(((float)$ms['current_total']/(float)$ms['target_value'])*100),100) : 0; ?>
                <div class="bg-[#141416] rounded-lg p-3 flex items-center gap-3 mb-2">
                    <div class="flex-1">
                        <div class="text-xs font-semibold"><?= $ms['icon'] ?> <?= e($ms['description'] ?? $ms['challenge_name']) ?> <?= (int)$ms['is_reached']?'✅':'' ?></div>
                        <div class="pf-progress-bg mt-1"><div class="pf-progress-bar pf-progress-green" style="width: <?= $msPct ?>%"></div></div>
                        <div class="text-[0.6rem] text-gray-500 mt-1"><?= formatNumber((float)$ms['current_total']) ?> / <?= formatNumber((float)$ms['target_value']) ?></div>
                    </div>
                    <?php if (!(int)$ms['is_reached']): ?><button class="text-[#ff6b35] text-xs" onclick="deleteMilestone(<?= $ms['id'] ?>)">✕</button><?php endif; ?>
                </div>
                <?php endforeach; ?>
                <div class="bg-[#141416] rounded-lg p-3 mt-3">
                    <div class="text-xs text-gray-400 mb-2">Neues Ziel:</div>
                    <select id="ms-challenge" class="pf-input text-xs w-full mb-2">
                        <?php foreach ($challenges as $ch): ?><option value="<?= $ch['id'] ?>"><?= $ch['icon'] ?> <?= e($ch['name']) ?></option><?php endforeach; ?>
                    </select>
                    <div class="flex gap-2 mb-2">
                        <input type="number" id="ms-target" class="pf-input text-xs flex-1" placeholder="Zielwert" min="1">
                        <input type="text" id="ms-desc" class="pf-input text-xs flex-1" placeholder="Beschreibung" maxlength="200">
                    </div>
                    <button class="pf-btn-primary w-full py-2 text-sm" onclick="addMilestone()">Ziel hinzufügen</button>
                </div>
            </div>

            <a href="info.php" class="block pf-card-static p-4 text-sm text-gray-300 hover:text-white transition mb-2">📖 Anleitung & Info</a>
            <button onclick="if(confirm('Ausloggen?')) window.location.href='api/profile.php?action=logout'" class="w-full pf-card-static p-4 text-sm text-[#ff6b35] text-left hover:bg-[#ff6b35]/5 transition rounded-2xl">🚪 Ausloggen</button>
        </div>
    </div>
    <?php include __DIR__ . '/includes/nav.php'; ?>
    <script src="assets/js/app.js"></script>
    <script>
        async function updateAvatar(k) { const d=await apiCall('api/profile.php',{action:'update_avatar',avatar:k}); if(d.error){showToast(d.error,'error');return;} showToast('Avatar geändert!','success'); document.querySelectorAll('[id^="pav-"]').forEach(b=>{b.classList.remove('border-[#00ff88]','bg-[#00ff88]/10');b.classList.add('border-transparent','bg-[#141416]');}); const b=document.getElementById('pav-'+k); b.classList.add('border-[#00ff88]','bg-[#00ff88]/10'); b.classList.remove('border-transparent','bg-[#141416]'); }
        async function updateNickname() { const n=document.getElementById('new-nickname').value.trim(); if(n.length<3||n.length>20){showToast('3-20 Zeichen','error');return;} const d=await apiCall('api/profile.php',{action:'update_nickname',nickname:n}); if(d.error){showToast(d.error,'error');return;} showToast('Nickname geändert!','success'); setTimeout(()=>location.reload(),1000); }
        async function updatePin() { const p=document.getElementById('new-pin').value.trim(); if(!/^\d{4}$/.test(p)){showToast('4 Ziffern','error');return;} const d=await apiCall('api/profile.php',{action:'update_pin',pin:p}); if(d.error){showToast(d.error,'error');return;} showToast('PIN geändert!','success'); document.getElementById('new-pin').value=''; }
        async function addMilestone() { const c=parseInt(document.getElementById('ms-challenge').value),t=parseFloat(document.getElementById('ms-target').value),de=document.getElementById('ms-desc').value.trim(); if(isNaN(t)||t<=0){showToast('Gültigen Zielwert eingeben','error');return;} const d=await apiCall('api/milestones.php',{action:'add',challenge_id:c,target:t,description:de}); if(d.error){showToast(d.error,'error');return;} showToast('Ziel hinzugefügt!','success'); setTimeout(()=>location.reload(),1000); }
        async function deleteMilestone(id) { if(!confirm('Ziel löschen?')) return; const d=await apiCall('api/milestones.php',{action:'delete',id}); if(d.error){showToast(d.error,'error');return;} showToast('Gelöscht','info'); setTimeout(()=>location.reload(),500); }
    </script>
</body>
</html>
