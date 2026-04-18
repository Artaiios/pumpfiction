<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/gamification.php';
require_once __DIR__ . '/includes/voting_logic.php';
$user = requireAuth(); $votingData = getVotingData($user['id']);
$totalUsers = (int)getDB()->query("SELECT COUNT(*) FROM users WHERE is_deleted = 0")->fetchColumn();
$csrf = csrfToken();
$mNames = ['','Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pumpfiction – Voting</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <meta name="csrf-token" content="<?= e($csrf) ?>">
</head>
<body class="pf-body">
    <div class="pf-toast-container" id="toasts"></div>
    <div class="pf-main-content pf-page-content">
        <div class="max-w-2xl mx-auto px-4 py-6">
            <h1 class="text-2xl font-black mb-2">🗳️ Voting</h1>
            <p class="text-sm text-gray-400 mb-4"><?= $mNames[$votingData['period']['month']] ?> <?= $votingData['period']['year'] ?> · Noch <?= $votingData['days_left'] ?> Tage</p>

            <?php if ($votingData['is_final_phase']): ?>
            <div class="bg-[#ff6b35]/10 border border-[#ff6b35]/30 rounded-xl p-4 mb-5">
                <p class="text-[#ff6b35] font-bold text-sm">⚠️ Finale Abstimmungsphase!</p>
                <p class="text-xs text-gray-300 mt-1">Nur noch <strong><?= $votingData['days_left'] ?> Tag(e)</strong> – Stimme nur noch einmal änderbar!</p>
            </div>
            <?php else: ?>
            <div class="bg-[#00ff88]/5 border border-[#00ff88]/10 rounded-xl p-3 mb-5 text-xs text-gray-400">🟢 Offene Phase – frei abstimmen.</div>
            <?php endif; ?>

            <h2 class="text-sm font-bold text-gray-400 uppercase tracking-wider mb-3">Aktive Challenges</h2>
            <div class="space-y-3 mb-8">
                <?php foreach ($votingData['challenges'] as $ch): ?>
                <div class="pf-card-static p-4">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <span class="text-lg"><?= $ch['icon'] ?></span>
                            <div><h3 class="font-semibold text-sm"><?= e($ch['name']) ?></h3><p class="text-xs text-gray-500">Ziel: <?= formatNumber((float)$ch['daily_target']) ?> <?= e($ch['unit'] ?? '') ?></p></div>
                        </div>
                        <div class="flex items-center gap-2">
                            <button class="pf-vote-btn <?= $ch['my_vote']===1?'vote-up':'' ?>" onclick="voteChallenge(<?= $ch['id'] ?>, 1, this)" <?= ($votingData['is_final_phase'] && $ch['final_used'])?'disabled style="opacity:0.4"':'' ?>>👍</button>
                            <button class="pf-vote-btn <?= $ch['my_vote']===-1?'vote-down':'' ?>" onclick="voteChallenge(<?= $ch['id'] ?>, -1, this)" <?= ($votingData['is_final_phase'] && $ch['final_used'])?'disabled style="opacity:0.4"':'' ?>>👎</button>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 mb-3">
                        <span class="text-xs text-[#00ff88]"><?= $ch['votes_up'] ?> 👍</span>
                        <div class="flex-1 pf-progress-bg"><?php $tv=$ch['votes_up']+$ch['votes_down']; $up=$tv>0?round(($ch['votes_up']/$tv)*100):50; ?>
                            <div class="pf-progress-bar pf-progress-green" style="width: <?= $up ?>%"></div></div>
                        <span class="text-xs text-[#ff6b35]"><?= $ch['votes_down'] ?> 👎</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-gray-400">Neues Ziel?</span>
                        <input type="number" class="pf-input text-xs py-1 px-2 w-24" id="target-<?= $ch['id'] ?>" value="<?= $ch['my_target_vote'] !== null ? $ch['my_target_vote'] : '' ?>" placeholder="<?= $ch['daily_target'] ?>">
                        <button class="pf-btn-secondary text-xs py-1" onclick="voteTarget(<?= $ch['id'] ?>)">Vorschlagen</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <h2 class="text-sm font-bold text-gray-400 uppercase tracking-wider mb-3">💡 Neue Vorschläge</h2>
            <?php foreach ($votingData['proposals'] as $prop): ?>
            <div class="pf-card-static p-4 mb-3">
                <div class="flex items-center justify-between mb-2">
                    <div><h3 class="font-semibold text-sm"><?= e($prop['name']) ?></h3>
                        <p class="text-xs text-gray-500"><?= $prop['type']==='yesno'?'Ja/Nein':formatNumber((float)$prop['daily_target']).' '.e($prop['unit']??'') ?> · von <?= e($prop['proposer']) ?></p></div>
                    <div class="flex items-center gap-2">
                        <button class="pf-vote-btn <?= $prop['my_vote']===1?'vote-up':'' ?>" onclick="voteProposal(<?= $prop['id'] ?>, 1, this)">👍</button>
                        <button class="pf-vote-btn <?= $prop['my_vote']===-1?'vote-down':'' ?>" onclick="voteProposal(<?= $prop['id'] ?>, -1, this)">👎</button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <div class="pf-card p-4 mt-4">
                <h3 class="font-semibold text-sm mb-3">Eigenen Vorschlag einreichen</h3>
                <div class="space-y-3">
                    <input type="text" id="prop-name" class="pf-input w-full text-sm" placeholder="Challenge-Name" maxlength="100">
                    <div class="flex gap-2">
                        <select id="prop-type" class="pf-input text-sm flex-1" onchange="document.getElementById('prop-unit').style.display=this.value==='yesno'?'none':''"><option value="number">Zahlenwert</option><option value="yesno">Ja/Nein</option></select>
                        <input type="text" id="prop-unit" class="pf-input text-sm flex-1" placeholder="Einheit">
                    </div>
                    <div class="flex gap-2">
                        <input type="number" id="prop-target" class="pf-input text-sm flex-1" placeholder="Tagesziel" min="1">
                        <input type="text" id="prop-desc" class="pf-input text-sm flex-1" placeholder="Beschreibung" maxlength="500">
                    </div>
                    <button class="pf-btn-primary w-full py-2 text-sm" onclick="submitProposal()">💡 Vorschlag einreichen</button>
                </div>
            </div>
        </div>
    </div>
    <?php include __DIR__ . '/includes/nav.php'; ?>
    <script src="assets/js/app.js"></script>
    <script>
        async function voteChallenge(id, vote, btn) { const d = await apiCall('api/vote.php', {action:'challenge_vote',challenge_id:id,vote}); if(d.error){showToast(d.error,'error');return;} showToast('Stimme gespeichert!','success'); btn.parentElement.querySelectorAll('.pf-vote-btn').forEach(b=>b.classList.remove('vote-up','vote-down')); btn.classList.add(vote===1?'vote-up':'vote-down'); }
        async function voteTarget(id) { const t=parseFloat(document.getElementById('target-'+id).value); if(isNaN(t)||t<=0){showToast('Gültigen Zielwert eingeben','error');return;} const d = await apiCall('api/vote.php', {action:'target_vote',challenge_id:id,target:t}); if(d.error){showToast(d.error,'error');return;} showToast('Ziel-Vorschlag gespeichert!','success'); }
        async function voteProposal(id, vote, btn) { const d = await apiCall('api/vote.php', {action:'proposal_vote',proposal_id:id,vote}); if(d.error){showToast(d.error,'error');return;} showToast('Stimme gespeichert!','success'); btn.parentElement.querySelectorAll('.pf-vote-btn').forEach(b=>b.classList.remove('vote-up','vote-down')); btn.classList.add(vote===1?'vote-up':'vote-down'); }
        async function submitProposal() { const n=document.getElementById('prop-name').value.trim(),ty=document.getElementById('prop-type').value,u=document.getElementById('prop-unit').value.trim(),t=parseFloat(document.getElementById('prop-target').value),de=document.getElementById('prop-desc').value.trim();
            if(!n){showToast('Name fehlt','error');return;} if(isNaN(t)||t<=0){showToast('Tagesziel fehlt','error');return;}
            const d=await apiCall('api/propose.php',{name:n,type:ty,unit:ty==='yesno'?null:u,target:t,description:de}); if(d.error){showToast(d.error,'error');return;} showToast('Vorschlag eingereicht! +5 XP 🎉','fame'); setTimeout(()=>location.reload(),1500); }
    </script>
</body>
</html>
