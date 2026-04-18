<?php
if (basename($_SERVER['PHP_SELF']) === 'voting_logic.php') { http_response_code(403); exit('Forbidden'); }

function getCurrentVotingPeriod(): array {
    return ['month' => (int)date('n'), 'year' => (int)date('Y')];
}

function getVotingData(int $userId): array {
    $db = getDB();
    $period = getCurrentVotingPeriod();
    $m = $period['month']; $y = $period['year'];
    $challenges = getActiveChallenges();
    foreach ($challenges as &$ch) {
        $stmt = $db->prepare("SELECT SUM(CASE WHEN vote = 1 THEN 1 ELSE 0 END) as up, SUM(CASE WHEN vote = -1 THEN 1 ELSE 0 END) as down FROM voting_challenge_votes WHERE challenge_id = ? AND vote_month = ? AND vote_year = ?");
        $stmt->execute([$ch['id'], $m, $y]); $votes = $stmt->fetch();
        $ch['votes_up'] = (int)($votes['up'] ?? 0); $ch['votes_down'] = (int)($votes['down'] ?? 0);

        $stmt = $db->prepare("SELECT vote, final_vote_used FROM voting_challenge_votes WHERE user_id = ? AND challenge_id = ? AND vote_month = ? AND vote_year = ?");
        $stmt->execute([$userId, $ch['id'], $m, $y]); $myVote = $stmt->fetch();
        $ch['my_vote'] = $myVote ? (int)$myVote['vote'] : 0;
        $ch['final_used'] = $myVote ? (int)$myVote['final_vote_used'] : 0;

        $stmt = $db->prepare("SELECT proposed_target FROM voting_target_votes WHERE user_id = ? AND challenge_id = ? AND vote_month = ? AND vote_year = ?");
        $stmt->execute([$userId, $ch['id'], $m, $y]); $myTarget = $stmt->fetch();
        $ch['my_target_vote'] = $myTarget ? (float)$myTarget['proposed_target'] : null;

        $stmt = $db->prepare("SELECT proposed_target FROM voting_target_votes WHERE challenge_id = ? AND vote_month = ? AND vote_year = ? ORDER BY proposed_target");
        $stmt->execute([$ch['id'], $m, $y]); $ch['target_votes'] = array_map('floatval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }
    unset($ch);

    $stmt = $db->prepare("SELECT vp.*, u.nickname as proposer,
        (SELECT COUNT(*) FROM voting_proposal_votes WHERE proposal_id = vp.id AND vote = 1) as yes_votes,
        (SELECT COUNT(*) FROM voting_proposal_votes WHERE proposal_id = vp.id AND vote = -1) as no_votes
        FROM voting_proposals vp JOIN users u ON vp.proposed_by = u.id
        WHERE vp.vote_month = ? AND vp.vote_year = ? AND vp.status = 'pending' ORDER BY vp.created_at DESC");
    $stmt->execute([$m, $y]); $proposals = $stmt->fetchAll();
    foreach ($proposals as &$prop) {
        $stmt = $db->prepare("SELECT vote, final_vote_used FROM voting_proposal_votes WHERE user_id = ? AND proposal_id = ?");
        $stmt->execute([$userId, $prop['id']]); $mv = $stmt->fetch();
        $prop['my_vote'] = $mv ? (int)$mv['vote'] : 0; $prop['final_used'] = $mv ? (int)$mv['final_vote_used'] : 0;
    }
    unset($prop);

    return ['challenges' => $challenges, 'proposals' => $proposals, 'is_final_phase' => isVotingFinalPhase(),
            'days_left' => (int)date('t') - (int)date('j'), 'period' => $period];
}

function voteChallengeKeepRemove(int $userId, int $challengeId, int $vote): array {
    $db = getDB(); $period = getCurrentVotingPeriod(); $isFinal = isVotingFinalPhase();
    $stmt = $db->prepare("SELECT final_vote_used FROM voting_challenge_votes WHERE user_id = ? AND challenge_id = ? AND vote_month = ? AND vote_year = ?");
    $stmt->execute([$userId, $challengeId, $period['month'], $period['year']]); $existing = $stmt->fetch();
    if ($isFinal && $existing && (int)$existing['final_vote_used']) return ['error' => 'Finale Stimme bereits genutzt.'];
    $finalUsed = $isFinal ? 1 : 0;
    $db->prepare("INSERT INTO voting_challenge_votes (user_id, challenge_id, vote, vote_month, vote_year, final_vote_used) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE vote = VALUES(vote), final_vote_used = IF(? = 1, 1, final_vote_used), updated_at = NOW()")
       ->execute([$userId, $challengeId, $vote, $period['month'], $period['year'], $finalUsed, $finalUsed]);
    $xpKey = "voting_{$period['month']}_{$period['year']}";
    $check = $db->prepare("SELECT COUNT(*) FROM xp_log WHERE user_id = ? AND reason = ?"); $check->execute([$userId, $xpKey]);
    if ((int)$check->fetchColumn() === 0) awardXP($userId, XP_VOTING, $xpKey);
    return ['success' => true];
}

function voteTargetAdjustment(int $userId, int $challengeId, float $target): array {
    if ($target <= 0) return ['error' => 'Zielwert muss größer als 0 sein.'];
    $period = getCurrentVotingPeriod();
    getDB()->prepare("INSERT INTO voting_target_votes (user_id, challenge_id, proposed_target, vote_month, vote_year) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE proposed_target = VALUES(proposed_target), updated_at = NOW()")
        ->execute([$userId, $challengeId, $target, $period['month'], $period['year']]);
    return ['success' => true];
}

function voteOnProposal(int $userId, int $proposalId, int $vote): array {
    $db = getDB(); $isFinal = isVotingFinalPhase();
    $stmt = $db->prepare("SELECT final_vote_used FROM voting_proposal_votes WHERE user_id = ? AND proposal_id = ?");
    $stmt->execute([$userId, $proposalId]); $existing = $stmt->fetch();
    if ($isFinal && $existing && (int)$existing['final_vote_used']) return ['error' => 'Finale Stimme bereits genutzt.'];
    $finalUsed = $isFinal ? 1 : 0;
    $db->prepare("INSERT INTO voting_proposal_votes (user_id, proposal_id, vote, final_vote_used) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE vote = VALUES(vote), final_vote_used = IF(? = 1, 1, final_vote_used), updated_at = NOW()")
       ->execute([$userId, $proposalId, $vote, $finalUsed, $finalUsed]);
    return ['success' => true];
}

function submitProposal(int $userId, string $name, string $type, ?string $unit, float $target, ?string $description): array {
    if (strlen($name) < 2 || strlen($name) > 100) return ['error' => 'Name: 2-100 Zeichen.'];
    if (!in_array($type, ['number', 'yesno'])) return ['error' => 'Ungültiger Typ.'];
    if ($target <= 0) return ['error' => 'Zielwert muss > 0 sein.'];
    $db = getDB(); $period = getCurrentVotingPeriod();
    $db->prepare("INSERT INTO voting_proposals (proposed_by, name, type, unit, daily_target, description, vote_month, vote_year) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
       ->execute([$userId, $name, $type, $unit, $target, $description, $period['month'], $period['year']]);
    awardXP($userId, XP_PROPOSAL, "proposal_" . $db->lastInsertId());
    return ['success' => true, 'id' => $db->lastInsertId()];
}
