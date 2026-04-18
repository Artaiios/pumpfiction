<?php
/**
 * Pumpfiction – Wall Event Generators
 */
if (basename($_SERVER['PHP_SELF']) === 'wall_events.php') { http_response_code(403); exit('Forbidden'); }

function wallLevelUp(int $userId, string $nickname, int $newLevel): void {
    $title = getLevelTitle($newLevel);
    $msgs = [
        "{$nickname} hat gerade Level {$newLevel} ({$title}) erreicht! 🔥",
        "{$nickname} ist jetzt Level {$newLevel} – {$title}! Respekt! 💪",
        "Level Up! {$nickname} ist jetzt ein \"{$title}\"! 🎮",
    ];
    addWallEntry($userId, 'fame', $msgs[array_rand($msgs)], '🔥');
}

function wallBadgeUnlocked(int $userId, string $nickname, string $badgeName, string $badgeIcon): void {
    addWallEntry($userId, 'fame', "{$nickname} hat den Badge \"{$badgeName}\" {$badgeIcon} freigeschaltet!", $badgeIcon);
}

function wallStreakMilestone(int $userId, string $nickname, int $days): void {
    $msgs = [
        "{$nickname} ist on fire – {$days} Tage Streak! 🔥",
        "{$nickname} hat die {$days}-Tage-Streak-Marke geknackt! Wahnsinn! 💪",
        "Streak-Alarm! {$nickname} mit {$days} Tagen in Folge! Nicht zu stoppen! 🚀",
    ];
    addWallEntry($userId, 'fame', $msgs[array_rand($msgs)], '🔥');
}

function wallStreakLost(int $userId, string $nickname, int $oldStreak): void {
    $msgs = [
        "{$nickname} hat seinen {$oldStreak}-Tage-Streak verloren – F in den Chat! 😬",
        "RIP Streak! {$nickname} hat nach {$oldStreak} Tagen den Faden verloren 📉",
        "{$oldStreak} Tage Streak – weg. {$nickname}, was war da los? 🤔",
        "{$nickname}s {$oldStreak}-Tage-Streak ist Geschichte. Moment der Stille bitte... 😢",
    ];
    addWallEntry($userId, 'shame', $msgs[array_rand($msgs)], '💔');
}

function wallLeaderboardOvertake(int $userId, string $nickname, string $overtakenNickname): void {
    addWallEntry($userId, 'shame', "{$overtakenNickname} wurde von {$nickname} auf dem Leaderboard überholt – das tut weh! 📉", '📉');
}

function wallInactiveUser(int $userId, string $nickname): void {
    $msgs = [
        "{$nickname} hat heute noch nichts eingetragen – lebt der noch? 🤔",
        "Hallo {$nickname}? Jemand zu Hause? Keine Einträge heute... 👀",
        "{$nickname} ist verschollen. Vermisstenanzeige raus? 🕵️",
    ];
    addWallEntry($userId, 'shame', $msgs[array_rand($msgs)], '😴');
}

function wallBackfillShame(int $userId, string $nickname, int $count): void {
    if ($count > 0 && $count % 5 === 0) {
        addWallEntry($userId, 'shame', "{$nickname} hat zum {$count}. Mal den Vortag nachgetragen – Prokrastination Level 100 😅", '😅');
    }
}

function wallMilestoneReached(int $userId, string $nickname, string $description, float $target): void {
    addWallEntry($userId, 'fame', "{$nickname} hat sein persönliches Ziel erreicht: {$description} ({$target})! 🎯", '🎯');
}

function generateDailyShameMessages(): void {
    $db = getDB();
    $stmt = $db->query("
        SELECT u.id, u.nickname FROM users u
        WHERE u.is_deleted = 0 AND u.last_login >= DATE_SUB(NOW(), INTERVAL 3 DAY)
        AND u.id NOT IN (SELECT DISTINCT user_id FROM challenge_logs WHERE log_date = CURDATE())
        AND HOUR(NOW()) >= 20
    ");
    foreach ($stmt->fetchAll() as $user) {
        $check = $db->prepare("SELECT COUNT(*) FROM wall_entries WHERE user_id = ? AND entry_type = 'shame' AND DATE(created_at) = CURDATE()");
        $check->execute([$user['id']]);
        if ((int)$check->fetchColumn() === 0) wallInactiveUser($user['id'], $user['nickname']);
    }
}
