<?php
/**
 * Pumpfiction – Helper Functions
 */
if (basename($_SERVER['PHP_SELF']) === 'functions.php') { http_response_code(403); exit('Forbidden'); }

function getActiveChallenges(): array {
    return getDB()->query("SELECT * FROM challenges WHERE is_active = 1 ORDER BY sort_order ASC")->fetchAll();
}

function getAllChallenges(): array {
    return getDB()->query("SELECT * FROM challenges ORDER BY sort_order ASC")->fetchAll();
}

function getUserChallenges(int $userId): array {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT c.*, IF(c.is_active = 1, 'public', 'private') as visibility
        FROM challenges c WHERE c.is_active = 1
        UNION
        SELECT c.*, 'private' as visibility
        FROM challenges c
        JOIN user_private_challenges upc ON c.id = upc.challenge_id AND upc.user_id = ?
        WHERE c.is_active = 0
        ORDER BY sort_order ASC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function getDayLogs(int $userId, string $date): array {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT cl.*, c.name, c.type, c.unit, c.daily_target, c.icon
        FROM challenge_logs cl JOIN challenges c ON cl.challenge_id = c.id
        WHERE cl.user_id = ? AND cl.log_date = ?
    ");
    $stmt->execute([$userId, $date]);
    $logs = [];
    foreach ($stmt->fetchAll() as $row) $logs[$row['challenge_id']] = $row;
    return $logs;
}

function logChallengeEntry(int $userId, int $challengeId, float $value, string $date, bool $isAdd = true): array {
    $db = getDB();
    $todayStr = today();
    $yesterdayStr = yesterday();
    if ($date !== $todayStr && $date !== $yesterdayStr) {
        return ['error' => 'Nur heute oder gestern können eingetragen werden.'];
    }

    $stmt = $db->prepare("SELECT * FROM challenges WHERE id = ? LIMIT 1");
    $stmt->execute([$challengeId]);
    $challenge = $stmt->fetch();
    if (!$challenge) return ['error' => 'Challenge nicht gefunden.'];

    $stmt = $db->prepare("SELECT * FROM challenge_logs WHERE user_id = ? AND challenge_id = ? AND log_date = ? LIMIT 1");
    $stmt->execute([$userId, $challengeId, $date]);
    $existing = $stmt->fetch();

    if ($challenge['type'] === 'yesno') {
        $newValue = $value ? 1 : 0;
    } else {
        $newValue = ($isAdd && $existing) ? (float)$existing['value'] + $value : $value;
        $newValue = max(0, $newValue);
    }

    if ($existing) {
        $db->prepare("UPDATE challenge_logs SET value = ?, updated_at = NOW() WHERE id = ?")->execute([$newValue, $existing['id']]);
    } else {
        $db->prepare("INSERT INTO challenge_logs (user_id, challenge_id, log_date, value) VALUES (?, ?, ?, ?)")->execute([$userId, $challengeId, $date, $newValue]);
    }

    if ($date === $yesterdayStr && !$existing) incrementBackfillCount($userId);

    $pct = $challenge['type'] === 'yesno'
        ? ($newValue >= 1 ? 100 : 0)
        : min(round(($newValue / (float)$challenge['daily_target']) * 100), 999);

    return ['success' => true, 'value' => $newValue, 'percentage' => $pct, 'target_reached' => $pct >= 100];
}

function resetChallengeEntry(int $userId, int $challengeId, string $date): array {
    $db = getDB();
    if ($date !== today() && $date !== yesterday()) {
        return ['error' => 'Nur heute oder gestern können zurückgesetzt werden.'];
    }
    $db->prepare("DELETE FROM challenge_logs WHERE user_id = ? AND challenge_id = ? AND log_date = ?")->execute([$userId, $challengeId, $date]);
    return ['success' => true, 'value' => 0, 'percentage' => 0, 'target_reached' => false];
}

function incrementBackfillCount(int $userId): void {
    getDB()->prepare("INSERT INTO xp_log (user_id, amount, reason) VALUES (?, 0, 'backfill')")->execute([$userId]);
}

function getBackfillCount(int $userId): int {
    $stmt = getDB()->prepare("SELECT COUNT(*) FROM xp_log WHERE user_id = ? AND reason = 'backfill'");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

function getLifetimeTotals(int $userId): array {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT c.id, c.name, c.type, c.unit, c.icon,
               COALESCE(SUM(cl.value), 0) as total,
               COUNT(CASE WHEN cl.value >= c.daily_target THEN 1 END) as days_reached,
               COUNT(cl.id) as days_logged, MAX(cl.value) as best_day
        FROM challenges c LEFT JOIN challenge_logs cl ON c.id = cl.challenge_id AND cl.user_id = ?
        GROUP BY c.id ORDER BY c.sort_order
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function getChallengeLifetimeTotal(int $userId, int $challengeId): float {
    $stmt = getDB()->prepare("SELECT COALESCE(SUM(value), 0) FROM challenge_logs WHERE user_id = ? AND challenge_id = ?");
    $stmt->execute([$userId, $challengeId]);
    return (float)$stmt->fetchColumn();
}

function isPerfectDay(int $userId, string $date): bool {
    $challenges = getActiveChallenges();
    if (empty($challenges)) return false;
    $db = getDB();
    foreach ($challenges as $ch) {
        $stmt = $db->prepare("SELECT value FROM challenge_logs WHERE user_id = ? AND challenge_id = ? AND log_date = ?");
        $stmt->execute([$userId, $ch['id'], $date]);
        $val = $stmt->fetchColumn();
        if ($val === false || (float)$val < (float)$ch['daily_target']) return false;
    }
    return true;
}

function countPerfectDays(int $userId): int {
    $db = getDB();
    $stmt = $db->prepare("SELECT DISTINCT log_date FROM challenge_logs WHERE user_id = ? ORDER BY log_date");
    $stmt->execute([$userId]);
    $perfect = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $date) {
        if (isPerfectDay($userId, $date)) $perfect++;
    }
    return $perfect;
}

function getLeaderboard(string $period = 'week'): array {
    $db = getDB();
    switch ($period) {
        case 'week': $startDate = date('Y-m-d', strtotime('monday this week')); $endDate = date('Y-m-d', strtotime('sunday this week')); break;
        case 'month': $startDate = date('Y-m-01'); $endDate = date('Y-m-t'); break;
        case 'year': $startDate = date('Y-01-01'); $endDate = date('Y-12-31'); break;
        default: $startDate = '2000-01-01'; $endDate = '2099-12-31';
    }

    if ($period === 'alltime') {
        $stmt = $db->prepare("SELECT u.id, u.nickname, u.avatar, u.xp, u.level, u.current_streak,
            COUNT(DISTINCT cl.log_date) as days_active, u.xp as period_xp
            FROM users u LEFT JOIN challenge_logs cl ON u.id = cl.user_id WHERE u.is_deleted = 0
            GROUP BY u.id ORDER BY u.xp DESC");
        $stmt->execute();
    } else {
        $stmt = $db->prepare("SELECT u.id, u.nickname, u.avatar, u.xp, u.level, u.current_streak,
            COUNT(DISTINCT cl.log_date) as days_active,
            COALESCE(SUM(xl.amount), 0) as period_xp
            FROM users u
            LEFT JOIN challenge_logs cl ON u.id = cl.user_id AND cl.log_date BETWEEN ? AND ?
            LEFT JOIN xp_log xl ON u.id = xl.user_id AND xl.created_at BETWEEN ? AND CONCAT(?, ' 23:59:59')
            WHERE u.is_deleted = 0 GROUP BY u.id ORDER BY period_xp DESC, u.xp DESC");
        $stmt->execute([$startDate, $endDate, $startDate, $endDate]);
    }

    $rows = $stmt->fetchAll();
    $challenges = getActiveChallenges();
    $chCount = count($challenges);

    foreach ($rows as &$row) {
        if ($chCount > 0 && $row['days_active'] > 0) {
            $stmtR = $db->prepare("SELECT COUNT(*) FROM challenge_logs cl JOIN challenges c ON cl.challenge_id = c.id
                WHERE cl.user_id = ? AND c.is_active = 1 AND cl.value >= c.daily_target"
                . ($period !== 'alltime' ? " AND cl.log_date BETWEEN ? AND ?" : ""));
            $period !== 'alltime' ? $stmtR->execute([$row['id'], $startDate, $endDate]) : $stmtR->execute([$row['id']]);
            $reached = (int)$stmtR->fetchColumn();
            $possible = $row['days_active'] * $chCount;
            $row['success_rate'] = $possible > 0 ? round(($reached / $possible) * 100) : 0;
        } else { $row['success_rate'] = 0; }
    }
    unset($row);
    return $rows;
}

function getUserRank(int $userId): int {
    foreach (getLeaderboard('alltime') as $i => $row) {
        if ((int)$row['id'] === $userId) return $i + 1;
    }
    return 999;
}

function getChallengeChartData(int $userId, int $challengeId, int $days = 30): array {
    $db = getDB();
    $startDate = date('Y-m-d', strtotime("-{$days} days"));
    $stmt = $db->prepare("SELECT log_date, value FROM challenge_logs WHERE user_id = ? AND challenge_id = ? AND log_date >= ? ORDER BY log_date ASC");
    $stmt->execute([$userId, $challengeId, $startDate]);
    $userData = $stmt->fetchAll();
    $stmt = $db->prepare("SELECT log_date, AVG(value) as avg_value FROM challenge_logs WHERE challenge_id = ? AND log_date >= ? GROUP BY log_date ORDER BY log_date ASC");
    $stmt->execute([$challengeId, $startDate]);
    return ['user' => $userData, 'average' => $stmt->fetchAll()];
}

function getHeatmapData(int $userId, int $days = 365): array {
    $db = getDB();
    $chCount = count(getActiveChallenges());
    if ($chCount === 0) return [];
    $startDate = date('Y-m-d', strtotime("-{$days} days"));
    $stmt = $db->prepare("SELECT cl.log_date, COUNT(CASE WHEN cl.value >= c.daily_target THEN 1 END) as reached, ? as total_challenges
        FROM challenge_logs cl JOIN challenges c ON cl.challenge_id = c.id AND c.is_active = 1
        WHERE cl.user_id = ? AND cl.log_date >= ? GROUP BY cl.log_date ORDER BY cl.log_date ASC");
    $stmt->execute([$chCount, $userId, $startDate]);
    return $stmt->fetchAll();
}

function getMotivationQuote(array $user): string {
    $db = getDB();
    $context = 'general';
    $rank = getUserRank($user['id']);
    $totalUsers = (int)$db->query("SELECT COUNT(*) FROM users WHERE is_deleted = 0")->fetchColumn();

    if ($user['current_streak'] > 2) $context = 'streak';
    elseif ($user['current_streak'] == 0 && $user['longest_streak'] > 0) $context = 'no_streak';
    elseif ($rank <= max(1, (int)($totalUsers * 0.2))) $context = 'leading';
    elseif ($rank > max(1, (int)($totalUsers * 0.6))) $context = 'behind';
    elseif ((int)$user['xp'] === 0) $context = 'new_user';

    if ($user['last_login'] && strtotime($user['last_login']) < strtotime('-3 days')) $context = 'comeback';
    if (isPerfectDay($user['id'], yesterday())) $context = 'perfect_day';

    $stmt = $db->prepare("SELECT quote_text FROM motivation_quotes WHERE context_type = ? AND is_active = 1 ORDER BY RAND() LIMIT 1");
    $stmt->execute([$context]);
    $quote = $stmt->fetchColumn();
    if (!$quote) {
        $stmt = $db->prepare("SELECT quote_text FROM motivation_quotes WHERE context_type = 'general' AND is_active = 1 ORDER BY RAND() LIMIT 1");
        $stmt->execute();
        $quote = $stmt->fetchColumn() ?: 'Let\'s go! 💪';
    }
    return str_replace(['{streak}', '{rank}', '{level}'], [$user['current_streak'], $rank, getLevelTitle($user['level'])], $quote);
}

function getLevelTitle(int $level): string {
    if ($level <= 11) return LEVELS[$level]['title'] ?? 'Unknown';
    return "Unstoppable " . ($level - 10);
}

function calculateLevel(int $xp): int {
    $level = 1;
    foreach (LEVELS as $lvl => $data) { if ($xp >= $data['xp']) $level = $lvl; }
    if ($xp >= 15000) $level = 11 + (int)floor(($xp - 15000) / 5000);
    return $level;
}

function getNextLevelXP(int $currentLevel): int {
    if ($currentLevel < 11) return LEVELS[$currentLevel + 1]['xp'] ?? LEVELS[11]['xp'];
    return 15000 + (($currentLevel - 10) * 5000);
}

function getCurrentLevelXP(int $currentLevel): int {
    if ($currentLevel <= 11) return LEVELS[$currentLevel]['xp'] ?? 0;
    return 15000 + (($currentLevel - 11) * 5000);
}

function getUserMilestones(int $userId): array {
    $stmt = getDB()->prepare("SELECT um.*, c.name as challenge_name, c.icon, c.unit,
        COALESCE((SELECT SUM(cl2.value) FROM challenge_logs cl2 WHERE cl2.user_id = um.user_id AND cl2.challenge_id = um.challenge_id), 0) as current_total
        FROM user_milestones um JOIN challenges c ON um.challenge_id = c.id
        WHERE um.user_id = ? ORDER BY um.is_reached ASC, um.created_at DESC");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function getUserBadges(int $userId): array {
    $stmt = getDB()->prepare("SELECT b.*, ub.unlocked_at, IF(ub.id IS NOT NULL, 1, 0) as unlocked
        FROM badges b LEFT JOIN user_badges ub ON b.id = ub.badge_id AND ub.user_id = ?
        ORDER BY b.sort_order ASC");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function getWeekdayStats(int $userId): array {
    $stmt = getDB()->prepare("SELECT DAYOFWEEK(log_date) as dow,
        COUNT(CASE WHEN cl.value >= c.daily_target THEN 1 END) as reached, COUNT(*) as total
        FROM challenge_logs cl JOIN challenges c ON cl.challenge_id = c.id WHERE cl.user_id = ?
        GROUP BY DAYOFWEEK(log_date)");
    $stmt->execute([$userId]);
    $weekdays = ['', 'So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];
    $result = [];
    foreach ($stmt->fetchAll() as $row) {
        $result[] = ['day' => $weekdays[(int)$row['dow']], 'rate' => $row['total'] > 0 ? round(($row['reached'] / $row['total']) * 100) : 0];
    }
    return $result;
}

function getConsecutiveDays(int $userId, int $challengeId): int {
    $stmt = getDB()->prepare("SELECT log_date FROM challenge_logs WHERE user_id = ? AND challenge_id = ? AND value >= 1 ORDER BY log_date DESC");
    $stmt->execute([$userId, $challengeId]);
    $count = 0;
    $expected = today();
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $d) {
        if ($d === $expected || ($count === 0 && $d === yesterday())) { $count++; $expected = date('Y-m-d', strtotime($d . ' -1 day')); }
        else break;
    }
    return $count;
}

function countDaysAbove(int $userId, int $challengeId, float $threshold): int {
    $stmt = getDB()->prepare("SELECT COUNT(*) FROM challenge_logs WHERE user_id = ? AND challenge_id = ? AND value >= ?");
    $stmt->execute([$userId, $challengeId, $threshold]);
    return (int)$stmt->fetchColumn();
}

function getSingleDayMax(int $userId, int $challengeId): float {
    $stmt = getDB()->prepare("SELECT COALESCE(MAX(value), 0) FROM challenge_logs WHERE user_id = ? AND challenge_id = ?");
    $stmt->execute([$userId, $challengeId]);
    return (float)$stmt->fetchColumn();
}

function formatNumber(float $num, ?string $unit = null): string {
    if ($unit === 'ml' && $num >= 1000) return number_format($num / 1000, 1, ',', '.') . ' L';
    if ($num >= 1000000) return number_format($num / 1000000, 1, ',', '.') . ' M';
    if ($num >= 10000) return number_format($num / 1000, 1, ',', '.') . 'k';
    if (floor($num) == $num) return number_format($num, 0, ',', '.');
    return number_format($num, 1, ',', '.');
}

function addWallEntry(?int $userId, string $type, string $message, string $icon = '📢', ?int $badgeId = null): void {
    getDB()->prepare("INSERT INTO wall_entries (user_id, entry_type, message, icon, related_badge_id) VALUES (?, ?, ?, ?, ?)")
        ->execute([$userId, $type, $message, $icon, $badgeId]);
}

function isVotingFinalPhase(): bool {
    return ((int)date('t') - (int)date('j')) < 3;
}

function processMonthlyVoting(): void {
    $db = getDB();
    $lastMonth = (int)date('n', strtotime('-1 month'));
    $lastYear = (int)date('Y', strtotime('-1 month'));
    $totalUsers = (int)$db->query("SELECT COUNT(*) FROM users WHERE is_deleted = 0")->fetchColumn();
    if ($totalUsers === 0) return;
    $threshold = $totalUsers / 2;

    // Remove challenges with >50% down votes
    $stmt = $db->prepare("SELECT challenge_id, SUM(CASE WHEN vote = -1 THEN 1 ELSE 0 END) as down_votes
        FROM voting_challenge_votes WHERE vote_month = ? AND vote_year = ? GROUP BY challenge_id HAVING down_votes > ?");
    $stmt->execute([$lastMonth, $lastYear, $threshold]);
    foreach ($stmt->fetchAll() as $row) {
        $db->prepare("UPDATE challenges SET is_active = 0 WHERE id = ?")->execute([$row['challenge_id']]);
        $chName = $db->query("SELECT name FROM challenges WHERE id = " . (int)$row['challenge_id'])->fetchColumn();
        addWallEntry(null, 'system', "Challenge \"{$chName}\" wurde durch Voting deaktiviert 📊", '🗳️');
    }

    // Adjust targets (median)
    $stmt = $db->prepare("SELECT challenge_id, GROUP_CONCAT(proposed_target ORDER BY proposed_target) as targets
        FROM voting_target_votes WHERE vote_month = ? AND vote_year = ? GROUP BY challenge_id");
    $stmt->execute([$lastMonth, $lastYear]);
    foreach ($stmt->fetchAll() as $row) {
        $targets = array_map('floatval', explode(',', $row['targets']));
        $count = count($targets);
        $median = $count % 2 === 0 ? ($targets[$count/2 - 1] + $targets[$count/2]) / 2 : $targets[(int)floor($count/2)];
        $db->prepare("UPDATE challenges SET daily_target = ? WHERE id = ?")->execute([$median, $row['challenge_id']]);
        $chName = $db->query("SELECT name FROM challenges WHERE id = " . (int)$row['challenge_id'])->fetchColumn();
        addWallEntry(null, 'system', "Tagesziel für \"{$chName}\" auf " . formatNumber($median) . " angepasst 🎯", '🗳️');
    }

    // Accept proposals with >50% yes
    $stmt = $db->prepare("SELECT vp.*, COUNT(CASE WHEN vpv.vote = 1 THEN 1 END) as yes_votes
        FROM voting_proposals vp LEFT JOIN voting_proposal_votes vpv ON vp.id = vpv.proposal_id
        WHERE vp.vote_month = ? AND vp.vote_year = ? AND vp.status = 'pending'
        GROUP BY vp.id HAVING yes_votes > ?");
    $stmt->execute([$lastMonth, $lastYear, $threshold]);
    foreach ($stmt->fetchAll() as $prop) {
        $maxSort = (int)$db->query("SELECT COALESCE(MAX(sort_order), 0) FROM challenges")->fetchColumn();
        $db->prepare("INSERT INTO challenges (name, type, unit, daily_target, is_active, created_by, sort_order) VALUES (?, ?, ?, ?, 1, ?, ?)")
           ->execute([$prop['name'], $prop['type'], $prop['unit'], $prop['daily_target'], $prop['proposed_by'], $maxSort + 1]);
        $db->prepare("UPDATE voting_proposals SET status = 'accepted' WHERE id = ?")->execute([$prop['id']]);
        addWallEntry(null, 'system', "Neue Challenge \"{$prop['name']}\" wurde durch Voting aktiviert! 🎉", '🗳️');
    }
    $db->prepare("UPDATE voting_proposals SET status = 'rejected' WHERE vote_month = ? AND vote_year = ? AND status = 'pending'")->execute([$lastMonth, $lastYear]);
}

function checkMonthTransition(): void {
    if ((int)date('j') <= 1) {
        $db = getDB();
        $lastProcess = $db->query("SELECT MAX(created_at) FROM wall_entries WHERE entry_type = 'system' AND message LIKE '%wurde durch Voting%'")->fetchColumn();
        if ($lastProcess && date('Y-m', strtotime($lastProcess)) === date('Y-m')) return;
        processMonthlyVoting();
    }
}
