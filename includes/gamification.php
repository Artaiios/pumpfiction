<?php
/**
 * Pumpfiction – Gamification Engine
 */
if (basename($_SERVER['PHP_SELF']) === 'gamification.php') { http_response_code(403); exit('Forbidden'); }
require_once __DIR__ . '/wall_events.php';

function awardXP(int $userId, int $amount, string $reason): void {
    if ($amount <= 0) return;
    $db = getDB();
    $db->prepare("UPDATE users SET xp = xp + ? WHERE id = ?")->execute([$amount, $userId]);
    $db->prepare("INSERT INTO xp_log (user_id, amount, reason) VALUES (?, ?, ?)")->execute([$userId, $amount, $reason]);
    $stmt = $db->prepare("SELECT xp, level FROM users WHERE id = ?"); $stmt->execute([$userId]); $user = $stmt->fetch();
    $newLevel = calculateLevel((int)$user['xp']);
    if ($newLevel > (int)$user['level']) {
        $db->prepare("UPDATE users SET level = ? WHERE id = ?")->execute([$newLevel, $userId]);
        $nickname = $db->query("SELECT nickname FROM users WHERE id = {$userId}")->fetchColumn();
        wallLevelUp($userId, $nickname, $newLevel);
    }
}

function processGamification(int $userId, string $date): array {
    $events = []; $db = getDB();
    $stmt = $db->prepare("SELECT nickname, current_streak, longest_streak FROM users WHERE id = ?");
    $stmt->execute([$userId]); $user = $stmt->fetch();
    $challenges = getActiveChallenges(); $logs = getDayLogs($userId, $date);

    foreach ($challenges as $ch) {
        $log = $logs[$ch['id']] ?? null;
        if ($log && (float)$log['value'] >= (float)$ch['daily_target']) {
            $key = "daily_goal_{$ch['id']}_{$date}";
            $alr = $db->prepare("SELECT COUNT(*) FROM xp_log WHERE user_id = ? AND reason = ?"); $alr->execute([$userId, $key]);
            if ((int)$alr->fetchColumn() === 0) { awardXP($userId, XP_DAILY_GOAL, $key); $events[] = ['type' => 'daily_goal', 'challenge' => $ch['name']]; }
        }
    }

    if (isPerfectDay($userId, $date)) {
        $key = "perfect_day_{$date}";
        $alr = $db->prepare("SELECT COUNT(*) FROM xp_log WHERE user_id = ? AND reason = ?"); $alr->execute([$userId, $key]);
        if ((int)$alr->fetchColumn() === 0) { awardXP($userId, XP_PERFECT_DAY, $key); $events[] = ['type' => 'perfect_day']; }
    }

    if ($date === today() || $date === yesterday()) updateStreak($userId);
    checkPeriodGoals($userId, $date);

    foreach (checkBadges($userId) as $badge) {
        $events[] = ['type' => 'badge', 'badge' => $badge];
        wallBadgeUnlocked($userId, $user['nickname'], $badge['name'], $badge['icon']);
    }
    foreach (checkMilestones($userId) as $ms) {
        $events[] = ['type' => 'milestone', 'milestone' => $ms];
        awardXP($userId, XP_PERSONAL_MILESTONE, "milestone_{$ms['id']}");
        wallMilestoneReached($userId, $user['nickname'], $ms['description'] ?? $ms['challenge_name'], $ms['target_value']);
    }
    return $events;
}

function updateStreak(int $userId): void {
    $db = getDB();
    $yesterdayPerfect = isPerfectDay($userId, yesterday());
    $todayPerfect = isPerfectDay($userId, today());
    $stmt = $db->prepare("SELECT current_streak, longest_streak, last_streak_date FROM users WHERE id = ?");
    $stmt->execute([$userId]); $user = $stmt->fetch();
    $cs = (int)$user['current_streak']; $ls = (int)$user['longest_streak']; $ld = $user['last_streak_date'];

    if ($todayPerfect) {
        if ($ld === yesterday() || $ld === today()) { if ($ld !== today()) $cs++; } else $cs = 1;
        $ld = today();
    } elseif ($yesterdayPerfect && $ld !== yesterday()) {
        $cs = ($ld === date('Y-m-d', strtotime('-2 days'))) ? $cs + 1 : 1;
        $ld = yesterday();
    } elseif ($ld && $ld < yesterday()) {
        $old = $cs; $cs = 0;
        if ($old >= 3) { $n = $db->query("SELECT nickname FROM users WHERE id = {$userId}")->fetchColumn(); wallStreakLost($userId, $n, $old); }
    }

    if ($cs > 0) {
        foreach (STREAK_MILESTONES as $ms) {
            if ($cs >= $ms && (int)$user['current_streak'] < $ms) {
                $n = $db->query("SELECT nickname FROM users WHERE id = {$userId}")->fetchColumn();
                wallStreakMilestone($userId, $n, $ms);
                awardXP($userId, XP_STREAK_BONUS * $ms, "streak_milestone_{$ms}");
            }
        }
    }
    if ($cs > (int)$user['current_streak'] && $cs > 1) awardXP($userId, XP_STREAK_BONUS * min($cs, 50), "streak_bonus_day_{$cs}");
    if ($cs > $ls) $ls = $cs;
    $db->prepare("UPDATE users SET current_streak = ?, longest_streak = ?, last_streak_date = ? WHERE id = ?")->execute([$cs, $ls, $ld, $userId]);
}

function checkPeriodGoals(int $userId, string $date): void {
    $db = getDB();
    foreach (getActiveChallenges() as $ch) {
        $target = (float)$ch['daily_target'];
        $weekStart = date('Y-m-d', strtotime('monday this week', strtotime($date)));
        $weekEnd = date('Y-m-d', strtotime('sunday this week', strtotime($date)));
        $stmt = $db->prepare("SELECT COALESCE(SUM(value), 0) FROM challenge_logs WHERE user_id = ? AND challenge_id = ? AND log_date BETWEEN ? AND ?");
        $stmt->execute([$userId, $ch['id'], $weekStart, $weekEnd]);
        if ((float)$stmt->fetchColumn() >= $target * 7) {
            $key = "weekly_goal_{$ch['id']}_{$weekStart}";
            $chk = $db->prepare("SELECT COUNT(*) FROM xp_log WHERE user_id = ? AND reason = ?"); $chk->execute([$userId, $key]);
            if ((int)$chk->fetchColumn() === 0) awardXP($userId, XP_WEEKLY_GOAL, $key);
        }
        $monthStart = date('Y-m-01', strtotime($date)); $monthEnd = date('Y-m-t', strtotime($date));
        $stmt->execute([$userId, $ch['id'], $monthStart, $monthEnd]);
        if ((float)$stmt->fetchColumn() >= $target * (int)date('t', strtotime($date))) {
            $key = "monthly_goal_{$ch['id']}_{$monthStart}";
            $chk = $db->prepare("SELECT COUNT(*) FROM xp_log WHERE user_id = ? AND reason = ?"); $chk->execute([$userId, $key]);
            if ((int)$chk->fetchColumn() === 0) {
                awardXP($userId, XP_MONTHLY_GOAL, $key);
                $nickname = $db->query("SELECT nickname FROM users WHERE id = {$userId}")->fetchColumn();
                addWallEntry($userId, 'fame', "{$nickname} hat das Monatsziel für {$ch['name']} geknackt! 💪", '🎯');
            }
        }
    }
}

function checkBadges(int $userId): array {
    $db = getDB(); $newBadges = [];
    $stmt = $db->prepare("SELECT b.* FROM badges b WHERE b.id NOT IN (SELECT badge_id FROM user_badges WHERE user_id = ?) ORDER BY b.sort_order");
    $stmt->execute([$userId]);
    foreach ($stmt->fetchAll() as $badge) {
        $earned = false;
        switch ($badge['condition_type']) {
            case 'first_log': $c = $db->prepare("SELECT COUNT(*) FROM challenge_logs WHERE user_id = ?"); $c->execute([$userId]); $earned = (int)$c->fetchColumn() >= 1; break;
            case 'streak': $s = $db->prepare("SELECT longest_streak FROM users WHERE id = ?"); $s->execute([$userId]); $earned = (int)$s->fetchColumn() >= (int)$badge['condition_value']; break;
            case 'perfect_day': case 'perfect_days_total': $earned = countPerfectDays($userId) >= (int)$badge['condition_value']; break;
            case 'lifetime_total':
                if ($badge['condition_extra']) { $cs = $db->prepare("SELECT id FROM challenges WHERE name = ?"); $cs->execute([$badge['condition_extra']]); $cid = $cs->fetchColumn();
                    if ($cid) $earned = getChallengeLifetimeTotal($userId, (int)$cid) >= (float)$badge['condition_value']; } break;
            case 'single_day_max':
                if ($badge['condition_extra']) { $cs = $db->prepare("SELECT id FROM challenges WHERE name = ?"); $cs->execute([$badge['condition_extra']]); $cid = $cs->fetchColumn();
                    if ($cid) $earned = getSingleDayMax($userId, (int)$cid) >= (float)$badge['condition_value']; } break;
            case 'consecutive_days':
                if ($badge['condition_extra']) { $cs = $db->prepare("SELECT id FROM challenges WHERE name = ?"); $cs->execute([$badge['condition_extra']]); $cid = $cs->fetchColumn();
                    if ($cid) $earned = getConsecutiveDays($userId, (int)$cid) >= (int)$badge['condition_value']; } break;
            case 'days_above':
                if ($badge['condition_extra'] && strpos($badge['condition_extra'], ':') !== false) {
                    [$chName, $thr] = explode(':', $badge['condition_extra']);
                    $cs = $db->prepare("SELECT id FROM challenges WHERE name = ?"); $cs->execute([$chName]); $cid = $cs->fetchColumn();
                    if ($cid) $earned = countDaysAbove($userId, (int)$cid, (float)$thr) >= (int)$badge['condition_value'];
                } break;
            case 'backfill_count': $earned = getBackfillCount($userId) >= (int)$badge['condition_value']; break;
            case 'weekly_winner': $s = $db->prepare("SELECT COUNT(*) FROM weekly_winners WHERE user_id = ?"); $s->execute([$userId]); $earned = (int)$s->fetchColumn() >= (int)$badge['condition_value']; break;
            case 'proposal_accepted': $s = $db->prepare("SELECT COUNT(*) FROM voting_proposals WHERE proposed_by = ? AND status = 'accepted'"); $s->execute([$userId]); $earned = (int)$s->fetchColumn() >= (int)$badge['condition_value']; break;
        }
        if ($earned) { $db->prepare("INSERT IGNORE INTO user_badges (user_id, badge_id) VALUES (?, ?)")->execute([$userId, $badge['id']]); $newBadges[] = $badge; }
    }
    return $newBadges;
}

function checkMilestones(int $userId): array {
    $db = getDB(); $reached = [];
    $stmt = $db->prepare("SELECT um.*, c.name as challenge_name FROM user_milestones um JOIN challenges c ON um.challenge_id = c.id WHERE um.user_id = ? AND um.is_reached = 0");
    $stmt->execute([$userId]);
    foreach ($stmt->fetchAll() as $ms) {
        if (getChallengeLifetimeTotal($userId, (int)$ms['challenge_id']) >= (float)$ms['target_value']) {
            $db->prepare("UPDATE user_milestones SET is_reached = 1, reached_at = NOW() WHERE id = ?")->execute([$ms['id']]);
            $reached[] = $ms;
        }
    }
    return $reached;
}

function checkWeeklyWinner(): void {
    $db = getDB();
    $weekStart = date('Y-m-d', strtotime('monday last week')); $weekEnd = date('Y-m-d', strtotime('sunday last week'));
    $stmt = $db->prepare("SELECT COUNT(*) FROM weekly_winners WHERE week_start = ?"); $stmt->execute([$weekStart]);
    if ((int)$stmt->fetchColumn() > 0) return;
    $stmt = $db->prepare("SELECT xl.user_id, SUM(xl.amount) as week_xp FROM xp_log xl JOIN users u ON xl.user_id = u.id AND u.is_deleted = 0
        WHERE xl.created_at BETWEEN ? AND CONCAT(?, ' 23:59:59') GROUP BY xl.user_id ORDER BY week_xp DESC LIMIT 1");
    $stmt->execute([$weekStart, $weekEnd]); $winner = $stmt->fetch();
    if ($winner && (int)$winner['week_xp'] > 0) {
        $db->prepare("INSERT INTO weekly_winners (user_id, week_start, week_end, xp_earned) VALUES (?, ?, ?, ?)")
           ->execute([$winner['user_id'], $weekStart, $weekEnd, $winner['week_xp']]);
        $n = $db->query("SELECT nickname FROM users WHERE id = " . (int)$winner['user_id'])->fetchColumn();
        addWallEntry($winner['user_id'], 'fame', "{$n} ist der Wochensieger! 🏆 ({$winner['week_xp']} XP)", '🏆');
    }
}
