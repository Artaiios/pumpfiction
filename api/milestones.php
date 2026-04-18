<?php
header("Cache-Control: no-cache, no-store, must-revalidate");
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';
$user = getCurrentUser();
if (!$user) jsonResponse(['error' => 'Nicht eingeloggt'], 401);
requireCsrf();
$input = json_decode(file_get_contents('php://input'), true);
$db = getDB();
switch ($input['action'] ?? '') {
    case 'add':
        $challengeId = (int)($input['challenge_id'] ?? 0);
        $target = (float)($input['target'] ?? 0);
        $desc = trim($input['description'] ?? '');
        if ($challengeId <= 0) jsonResponse(['error' => 'Challenge fehlt'], 400);
        if ($target <= 0) jsonResponse(['error' => 'Zielwert muss > 0 sein'], 400);
        $c = $db->prepare("SELECT COUNT(*) FROM user_milestones WHERE user_id = ? AND is_reached = 0");
        $c->execute([$user['id']]);
        if ((int)$c->fetchColumn() >= 20) jsonResponse(['error' => 'Max 20 aktive Ziele'], 400);
        $db->prepare("INSERT INTO user_milestones (user_id, challenge_id, target_value, description) VALUES (?, ?, ?, ?)")
           ->execute([$user['id'], $challengeId, $target, $desc ?: null]);
        jsonResponse(['success' => true, 'id' => $db->lastInsertId()]);
    case 'delete':
        $db->prepare("DELETE FROM user_milestones WHERE id = ? AND user_id = ? AND is_reached = 0")
           ->execute([(int)($input['id'] ?? 0), $user['id']]);
        jsonResponse(['success' => true]);
    default: jsonResponse(['error' => 'Unbekannte Aktion'], 400);
}
