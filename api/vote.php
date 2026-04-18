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
switch ($input['action'] ?? '') {
    case 'challenge_vote':
        $vote = (int)($input['vote'] ?? 0);
        if (!in_array($vote, [1, -1])) jsonResponse(['error' => 'Ungültige Stimme'], 400);
        jsonResponse(voteChallengeKeepRemove($user['id'], (int)$input['challenge_id'], $vote));
    case 'target_vote':
        jsonResponse(voteTargetAdjustment($user['id'], (int)$input['challenge_id'], (float)$input['target']));
    case 'proposal_vote':
        $vote = (int)($input['vote'] ?? 0);
        if (!in_array($vote, [1, -1])) jsonResponse(['error' => 'Ungültige Stimme'], 400);
        jsonResponse(voteOnProposal($user['id'], (int)$input['proposal_id'], $vote));
    default: jsonResponse(['error' => 'Unbekannte Aktion'], 400);
}
