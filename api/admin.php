<?php
header("Cache-Control: no-cache, no-store, must-revalidate");
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';
$input = json_decode(file_get_contents('php://input'), true);
$db = getDB();
if (($input['action'] ?? '') === 'get_system_stats') {
    jsonResponse([
        'total_users' => (int)$db->query("SELECT COUNT(*) FROM users WHERE is_deleted = 0")->fetchColumn(),
        'total_logs' => (int)$db->query("SELECT COUNT(*) FROM challenge_logs")->fetchColumn(),
        'today_logs' => (int)$db->query("SELECT COUNT(DISTINCT user_id) FROM challenge_logs WHERE log_date = CURDATE()")->fetchColumn(),
    ]);
}
jsonResponse(['error' => 'Unbekannte Aktion'], 400);
