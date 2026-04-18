<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

$user = getCurrentUser();
if ($user) { header('Location: ' . (!(int)$user['has_seen_intro'] ? 'info.php' : 'dashboard.php')); exit; }

$error = ''; $mode = $_GET['mode'] ?? 'login';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'login') {
        $user = loginUser(trim($_POST['nickname'] ?? ''), trim($_POST['pin'] ?? ''));
        if ($user) { header('Location: ' . (!(int)$user['has_seen_intro'] ? 'info.php' : 'dashboard.php')); exit; }
        $error = 'Nickname oder PIN falsch.'; $mode = 'login';
    } elseif ($action === 'register') {
        $nickname = trim($_POST['nickname'] ?? ''); $pin = trim($_POST['pin'] ?? ''); $avatar = $_POST['avatar'] ?? 'bear';
        if (strlen($nickname) < 3 || strlen($nickname) > 20) $error = 'Nickname: 3-20 Zeichen.';
        elseif (!preg_match('/^\d{4}$/', $pin)) $error = 'PIN: genau 4 Ziffern.';
        elseif (!isset(AVATARS[$avatar])) $error = 'Bitte Avatar wählen.';
        else { $user = registerUser($nickname, $pin, $avatar); if ($user) { header('Location: info.php'); exit; } $error = 'Nickname vergeben.'; }
        $mode = 'register';
    }
}
$csrf = csrfToken();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pumpfiction – Motion Lock</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="pf-body min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="text-center mb-8 animate-fadeIn">
            <div class="text-7xl mb-4 animate-pulse-slow">💪</div>
            <h1 class="text-4xl font-black pf-gradient-text">Pumpfiction</h1>
            <p class="text-gray-400 mt-1 text-sm tracking-widest uppercase">Motion Lock – Tracking Edition</p>
        </div>
        <?php if ($error): ?>
        <div class="bg-red-500/20 border border-red-500/30 rounded-xl p-4 mb-6 text-red-300 text-center text-sm animate-shake"><?= e($error) ?></div>
        <?php endif; ?>
        <div class="flex mb-6 bg-[#1c1c1f] rounded-xl p-1">
            <button onclick="switchMode('login')" id="tab-login" class="flex-1 py-3 rounded-lg text-sm font-semibold transition-all <?= $mode === 'login' ? 'bg-gradient-to-r from-[#00ff88] to-[#00d4ff] text-black' : 'text-gray-400 hover:text-white' ?>">Login</button>
            <button onclick="switchMode('register')" id="tab-register" class="flex-1 py-3 rounded-lg text-sm font-semibold transition-all <?= $mode === 'register' ? 'bg-gradient-to-r from-[#00ff88] to-[#00d4ff] text-black' : 'text-gray-400 hover:text-white' ?>">Registrieren</button>
        </div>
        <div id="form-login" class="pf-card p-6 <?= $mode !== 'login' ? 'hidden' : '' ?>">
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="login"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <div><label class="block text-sm text-gray-400 mb-1">Nickname</label><input type="text" name="nickname" required autocomplete="username" maxlength="20" class="pf-input w-full" placeholder="Dein Nickname" autofocus></div>
                <div><label class="block text-sm text-gray-400 mb-1">PIN</label><input type="password" name="pin" required inputmode="numeric" pattern="\d{4}" maxlength="4" class="pf-input w-full" placeholder="4-stellige PIN"></div>
                <button type="submit" class="pf-btn-primary w-full py-3">Einloggen →</button>
            </form>
        </div>
        <div id="form-register" class="pf-card p-6 <?= $mode !== 'register' ? 'hidden' : '' ?>">
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="register"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                <div><label class="block text-sm text-gray-400 mb-1">Nickname</label><input type="text" name="nickname" required minlength="3" maxlength="20" class="pf-input w-full" placeholder="3-20 Zeichen"></div>
                <div><label class="block text-sm text-gray-400 mb-1">PIN</label><input type="password" name="pin" required inputmode="numeric" pattern="\d{4}" maxlength="4" class="pf-input w-full" placeholder="4 Ziffern"></div>
                <div>
                    <label class="block text-sm text-gray-400 mb-2">Avatar wählen</label>
                    <input type="hidden" name="avatar" id="selected-avatar" value="bear">
                    <div class="grid grid-cols-7 gap-2">
                        <?php foreach (AVATARS as $key => $emoji): ?>
                        <button type="button" onclick="selectAvatar('<?= $key ?>')" id="av-<?= $key ?>"
                                class="avatar-btn text-2xl p-2 rounded-lg border-2 transition-all hover:scale-110 <?= $key === 'bear' ? 'border-[#00ff88] bg-[#00ff88]/10' : 'border-transparent bg-[#141416]' ?>"><?= $emoji ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <button type="submit" class="pf-btn-primary w-full py-3">Account erstellen 🚀</button>
            </form>
        </div>
    </div>
    <script>
        function switchMode(m) {
            document.getElementById('form-login').classList.toggle('hidden', m !== 'login');
            document.getElementById('form-register').classList.toggle('hidden', m !== 'register');
            ['login','register'].forEach(t => { const b = document.getElementById('tab-'+t); b.className = `flex-1 py-3 rounded-lg text-sm font-semibold transition-all ${m===t ? 'bg-gradient-to-r from-[#00ff88] to-[#00d4ff] text-black' : 'text-gray-400 hover:text-white'}`; });
        }
        function selectAvatar(key) {
            document.querySelectorAll('.avatar-btn').forEach(b => { b.classList.remove('border-[#00ff88]','bg-[#00ff88]/10'); b.classList.add('border-transparent','bg-[#141416]'); });
            const b = document.getElementById('av-'+key); b.classList.add('border-[#00ff88]','bg-[#00ff88]/10'); b.classList.remove('border-transparent','bg-[#141416]');
            document.getElementById('selected-avatar').value = key;
        }
    </script>
</body>
</html>
