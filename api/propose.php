<?php
header("Cache-Control: no-cache, no-store, must-revalidate");
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/gamification.php';
require_once dirname(__DIR__) . '/includes/voting_logic.php';
$user = getCurrentUser();
if (!$user) jsonResponse(['error' => 'Nicht eingeloggt'], 401);
requireCsrf();
$input = json_decode(file_get_contents('php://input'), true);
jsonResponse(submitProposal($user['id'], trim($input['name'] ?? ''), $input['type'] ?? 'number',
    isset($input['unit']) ? trim($input['unit']) : null, (float)($input['target'] ?? 0),
    isset($input['description']) ? trim($input['description']) : null));
