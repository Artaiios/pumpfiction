<?php
/**
 * Pumpfiction – Authentication & Cookie Management
 */

if (basename($_SERVER['PHP_SELF']) === 'auth.php') {
    http_response_code(403);
    exit('Forbidden');
}

function getCurrentUser(): ?array {
    $db = getDB();
    if (isset($_COOKIE[COOKIE_NAME])) {
        $token = $_COOKIE[COOKIE_NAME];
        $stmt = $db->prepare("SELECT * FROM users WHERE cookie_token = ? AND is_deleted = 0 LIMIT 1");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        if ($user) {
            $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
            return $user;
        }
        clearAuthCookie();
    }
    return null;
}

function requireAuth(): array {
    $user = getCurrentUser();
    if (!$user) {
        header('Location: index.php');
        exit;
    }
    return $user;
}

function loginUser(string $nickname, string $pin): ?array {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE nickname = ? AND is_deleted = 0 LIMIT 1");
    $stmt->execute([$nickname]);
    $user = $stmt->fetch();

    if ($user && password_verify($pin, $user['pin_hash'])) {
        $token = bin2hex(random_bytes(32));
        $db->prepare("UPDATE users SET cookie_token = ?, last_login = NOW() WHERE id = ?")
           ->execute([$token, $user['id']]);
        setAuthCookie($token);
        return $user;
    }
    return null;
}

function registerUser(string $nickname, string $pin, string $avatar): ?array {
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM users WHERE nickname = ? LIMIT 1");
    $stmt->execute([$nickname]);
    if ($stmt->fetch()) return null;

    $pinHash = password_hash($pin, PASSWORD_DEFAULT);
    $token = bin2hex(random_bytes(32));
    $stmt = $db->prepare("INSERT INTO users (nickname, pin_hash, avatar, cookie_token, last_login) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$nickname, $pinHash, $avatar, $token]);
    $userId = (int)$db->lastInsertId();
    setAuthCookie($token);

    return ['id' => $userId, 'nickname' => $nickname, 'avatar' => $avatar, 'xp' => 0, 'level' => 1, 'current_streak' => 0, 'longest_streak' => 0, 'has_seen_intro' => 0];
}

function logoutUser(): void {
    $user = getCurrentUser();
    if ($user) {
        getDB()->prepare("UPDATE users SET cookie_token = NULL WHERE id = ?")->execute([$user['id']]);
    }
    clearAuthCookie();
}

function setAuthCookie(string $token): void {
    setcookie(COOKIE_NAME, $token, [
        'expires' => time() + (COOKIE_DAYS * 86400), 'path' => '/',
        'secure' => isset($_SERVER['HTTPS']), 'httponly' => true, 'samesite' => 'Lax',
    ]);
}

function clearAuthCookie(): void {
    setcookie(COOKIE_NAME, '', [
        'expires' => time() - 3600, 'path' => '/',
        'secure' => isset($_SERVER['HTTPS']), 'httponly' => true, 'samesite' => 'Lax',
    ]);
}

function csrfToken(): string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function verifyCsrf(): bool {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return !empty($token) && hash_equals($_SESSION[CSRF_TOKEN_NAME] ?? '', $token);
}

function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function requireCsrf(): void {
    if (!verifyCsrf()) jsonResponse(['error' => 'Invalid CSRF token'], 403);
}
