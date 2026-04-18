<?php
header("Cache-Control: no-cache, no-store, must-revalidate");
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    logoutUser(); header('Location: ../index.php'); exit;
}
$user = getCurrentUser();
if (!$user) jsonResponse(['error' => 'Nicht eingeloggt'], 401);
requireCsrf();
$input = json_decode(file_get_contents('php://input'), true);
$db = getDB();
switch ($input['action'] ?? '') {
    case 'update_avatar':
        $av = $input['avatar'] ?? '';
        if (!isset(AVATARS[$av])) jsonResponse(['error' => 'Ungültiger Avatar'], 400);
        $db->prepare("UPDATE users SET avatar = ? WHERE id = ?")->execute([$av, $user['id']]);
        jsonResponse(['success' => true]);
    case 'update_nickname':
        $nn = trim($input['nickname'] ?? '');
        if (strlen($nn) < 3 || strlen($nn) > 20) jsonResponse(['error' => '3-20 Zeichen'], 400);
        $s = $db->prepare("SELECT id FROM users WHERE nickname = ? AND id != ?"); $s->execute([$nn, $user['id']]);
        if ($s->fetch()) jsonResponse(['error' => 'Nickname vergeben'], 400);
        $db->prepare("UPDATE users SET nickname = ? WHERE id = ?")->execute([$nn, $user['id']]);
        jsonResponse(['success' => true]);
    case 'update_pin':
        $pin = trim($input['pin'] ?? '');
        if (!preg_match('/^\d{4}$/', $pin)) jsonResponse(['error' => 'PIN: genau 4 Ziffern'], 400);
        $db->prepare("UPDATE users SET pin_hash = ? WHERE id = ?")->execute([password_hash($pin, PASSWORD_DEFAULT), $user['id']]);
        jsonResponse(['success' => true]);
    default: jsonResponse(['error' => 'Unbekannte Aktion'], 400);
}
