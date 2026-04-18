<?php
header('Cache-Control: no-cache, no-store, must-revalidate');
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/gamification.php';

$user = getCurrentUser();
if (!$user) jsonResponse(['error' => 'Nicht eingeloggt'], 401);
requireCsrf();

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) jsonResponse(['error' => 'Ungültige Eingabe'], 400);

$challengeId = (int)($input['challenge_id'] ?? 0);
$value = (float)($input['value'] ?? 0);
$date = $input['date'] ?? today();
$isAdd = (bool)($input['is_add'] ?? true);
$isReset = (bool)($input['reset'] ?? false);

if ($challengeId <= 0) jsonResponse(['error' => 'Challenge nicht angegeben'], 400);
if ($date !== today() && $date !== yesterday()) jsonResponse(['error' => 'Nur heute oder gestern.'], 400);

if ($isReset) { jsonResponse(resetChallengeEntry($user['id'], $challengeId, $date)); }

$result = logChallengeEntry($user['id'], $challengeId, $value, $date, $isAdd);
if (isset($result['error'])) jsonResponse($result, 400);

$events = processGamification($user['id'], $date);
if ($date === yesterday()) wallBackfillShame($user['id'], $user['nickname'], getBackfillCount($user['id']));
$result['events'] = $events;
jsonResponse($result);
