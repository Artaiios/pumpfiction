<?php
date_default_timezone_set('Europe/Berlin');
$step = $_GET['step'] ?? '1'; $error = ''; $success = '';
if (file_exists(__DIR__ . '/.installed')) die('Pumpfiction ist bereits installiert. Lösche install/.installed um erneut zu installieren.');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === '2') {
    $host = trim($_POST['db_host'] ?? ''); $name = trim($_POST['db_name'] ?? '');
    $user = trim($_POST['db_user'] ?? ''); $pass = $_POST['db_pass'] ?? '';
    try {
        $pdo = new PDO("mysql:host={$host};charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$name}`");
        $pdo->exec(file_get_contents(__DIR__ . '/schema.sql'));

        // Only seed if tables are empty (prevents duplicates on re-install)
        if ((int)$pdo->query("SELECT COUNT(*) FROM challenges")->fetchColumn() === 0) {
            $pdo->exec(file_get_contents(__DIR__ . '/seed.sql'));
        }

        // Write credentials to separate file
        $credContent = "<?php\n// Pumpfiction – DB Credentials (auto-generated)\n";
        $credContent .= "define('DB_HOST', '" . addslashes($host) . "');\n";
        $credContent .= "define('DB_NAME', '" . addslashes($name) . "');\n";
        $credContent .= "define('DB_USER', '" . addslashes($user) . "');\n";
        $credContent .= "define('DB_PASS', '" . addslashes($pass) . "');\n";
        file_put_contents(dirname(__DIR__) . '/includes/db_credentials.php', $credContent);
        file_put_contents(__DIR__ . '/.installed', date('Y-m-d H:i:s'));
        $step = '3';
    } catch (PDOException $e) { $error = 'DB-Fehler: ' . $e->getMessage(); $step = '1'; }
    catch (Exception $e) { $error = 'Fehler: ' . $e->getMessage(); $step = '1'; }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pumpfiction – Installation</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>body { font-family: 'Outfit', sans-serif; background: #111113; color: #fff; } .glow { box-shadow: 0 0 20px rgba(0,255,136,0.15); }</style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-lg">
        <div class="text-center mb-8"><h1 class="text-4xl font-black" style="background:linear-gradient(135deg,#00ff88,#00d4ff);-webkit-background-clip:text;-webkit-text-fill-color:transparent">💪 Pumpfiction</h1><p class="text-gray-400 mt-2">Installation</p></div>
        <?php if ($error): ?><div class="bg-red-500/20 border border-red-500/50 rounded-xl p-4 mb-6 text-red-300"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($step === '1' || $step === '2'): ?>
        <div class="bg-[#1c1c1f] rounded-2xl p-6 glow">
            <h2 class="text-xl font-bold mb-4">📦 Datenbank-Konfiguration</h2>
            <form method="POST" action="?step=2" class="space-y-4">
                <?php foreach (['db_host'=>['DB Host','localhost'], 'db_name'=>['Datenbank','pumpfiction'], 'db_user'=>['Benutzer',''], 'db_pass'=>['Passwort','']] as $field => [$label, $default]): ?>
                <div><label class="block text-sm text-gray-400 mb-1"><?= $label ?></label><input type="<?= $field==='db_pass'?'password':'text' ?>" name="<?= $field ?>" value="<?= htmlspecialchars($_POST[$field] ?? $default) ?>" class="w-full bg-[#141416] border border-gray-700 rounded-lg px-4 py-3 text-white focus:border-[#00ff88] focus:outline-none"></div>
                <?php endforeach; ?>
                <button type="submit" class="w-full bg-gradient-to-r from-[#00ff88] to-[#00d4ff] text-black font-bold py-3 rounded-xl hover:opacity-90">🚀 Installation starten</button>
            </form>
        </div>
        <?php elseif ($step === '3'): ?>
        <div class="bg-[#1c1c1f] rounded-2xl p-6 glow text-center">
            <div class="text-6xl mb-4">🎉</div>
            <h2 class="text-2xl font-bold text-[#00ff88] mb-4">Installation erfolgreich!</h2>
            <p class="text-gray-300 mb-6">Erstelle jetzt deinen Account.</p>
            <div class="bg-[#141416] rounded-xl p-4 mb-6 text-left"><p class="text-sm text-gray-400 mb-2">📌 Admin-Bereich:</p><code class="text-[#00d4ff]">/pumpfiction/theboss.php</code></div>
            <a href="../index.php" class="inline-block bg-gradient-to-r from-[#00ff88] to-[#00d4ff] text-black font-bold py-3 px-8 rounded-xl hover:opacity-90">Zur App →</a>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
