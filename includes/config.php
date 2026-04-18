<?php
/**
 * Pumpfiction – Configuration
 */

if (basename($_SERVER['PHP_SELF']) === 'config.php') {
    http_response_code(403);
    exit('Forbidden');
}

date_default_timezone_set('Europe/Berlin');

// DB credentials in separate file (survives app updates)
$credFile = __DIR__ . '/db_credentials.php';
if (!file_exists($credFile)) {
    die('Datei includes/db_credentials.php fehlt. Bitte install/install.php ausführen.');
}
require_once $credFile;

define('APP_NAME', 'Pumpfiction');
define('APP_TAGLINE', 'Motion Lock – Pumpfiction (Tracking Edition)');
define('COOKIE_NAME', 'pumpfiction_auth');
define('COOKIE_DAYS', 90);
define('CSRF_TOKEN_NAME', 'pf_csrf');
define('MAX_BACKFILL_DAYS', 1);

define('LEVELS', [
    1  => ['title' => 'Couch Potato',              'xp' => 0],
    2  => ['title' => 'Frischling',                 'xp' => 100],
    3  => ['title' => 'Anfänger mit Ambitionen',    'xp' => 300],
    4  => ['title' => 'Schweinehund-Bekämpfer',     'xp' => 600],
    5  => ['title' => 'Routine-Rookie',             'xp' => 1000],
    6  => ['title' => 'Fitness-Padawan',            'xp' => 1500],
    7  => ['title' => 'Pump-Profi',                 'xp' => 2500],
    8  => ['title' => 'Beast Mode',                 'xp' => 4000],
    9  => ['title' => 'Iron Will',                  'xp' => 6000],
    10 => ['title' => 'Legend',                     'xp' => 10000],
    11 => ['title' => 'Unstoppable',                'xp' => 15000],
]);

define('XP_DAILY_GOAL', 10);
define('XP_PERFECT_DAY', 25);
define('XP_STREAK_BONUS', 5);
define('XP_WEEKLY_GOAL', 50);
define('XP_MONTHLY_GOAL', 200);
define('XP_YEARLY_GOAL', 1000);
define('XP_PERSONAL_MILESTONE', 100);
define('XP_PROPOSAL', 5);
define('XP_VOTING', 5);

define('STREAK_MILESTONES', [7, 14, 30, 60, 90, 180, 365]);

define('AVATARS', [
    'bear' => '🐻', 'wolf' => '🐺', 'lion' => '🦁', 'eagle' => '🦅',
    'shark' => '🦈', 'dragon' => '🐉', 'gorilla' => '🦍', 'tiger' => '🐯',
    'fox' => '🦊', 'unicorn' => '🦄', 'octopus' => '🐙', 'owl' => '🦉',
    'snake' => '🐍', 'bull' => '🐂', 'rhino' => '🦏', 'panther' => '🐆',
    'rocket' => '🚀', 'fire' => '🔥', 'lightning' => '⚡', 'crown' => '👑',
    'ninja' => '🥷', 'robot' => '🤖', 'alien' => '👽', 'skull' => '💀',
    'muscle' => '💪', 'star' => '⭐', 'diamond' => '💎', 'trophy' => '🏆',
]);

define('QUICK_ADD_BUTTONS', [
    'Schritte'  => [100, 500, 1000, 5000],
    'Stück'     => [5, 10, 20, 50],
    'Sekunden'  => [15, 30, 60, 120],
    'ml'        => [250, 500, 1000],
    'Minuten'   => [5, 10, 15, 30],
    'Stunden'   => [0.5, 1, 2, 4],
]);

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        // Sync MySQL timezone with PHP (handles CET/CEST automatically)
        $offset = (new DateTime())->format('P');
        $pdo->exec("SET time_zone = '{$offset}'");
    }
    return $pdo;
}

function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function today(): string {
    return date('Y-m-d');
}

function yesterday(): string {
    return date('Y-m-d', strtotime('-1 day'));
}
