<?php
header("Cache-Control: no-cache, no-store, must-revalidate");
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';
$user = getCurrentUser();
if (!$user) jsonResponse(['error' => 'Nicht eingeloggt'], 401);
$challengeId = (int)($_GET['challenge_id'] ?? 0);
$days = min(max((int)($_GET['days'] ?? 30), 7), 365);
if ($challengeId <= 0) jsonResponse(['error' => 'Challenge nicht angegeben'], 400);
jsonResponse(getChallengeChartData((int)$user['id'], $challengeId, $days));
