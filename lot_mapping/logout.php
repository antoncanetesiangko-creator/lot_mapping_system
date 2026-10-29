<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';

// Only allow logout through POST to help prevent unwanted logout requests.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

$userId = isset($_SESSION['user_id'])
    ? (int)$_SESSION['user_id']
    : null;

$token = (string)($_POST['csrf_token'] ?? '');
$sessionToken = (string)($_SESSION['logout_csrf'] ?? '');

if (
    $userId !== null &&
    $sessionToken !== '' &&
    hash_equals($sessionToken, $token)
) {
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO activity_logs
                (user_id, action, entity_type, entity_id, ip_address)
             VALUES
                (:user_id, :action, :entity_type, :entity_id, :ip)'
        );

        $stmt->execute([
            'user_id' => $userId,
            'action' => 'logout',
            'entity_type' => 'user',
            'entity_id' => $userId,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (PDOException $e) {
        error_log('LOTMAP logout log error: ' . $e->getMessage());
    }
}

// Clear the session and destroy it.
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]
    );
}

session_destroy();

header('Location: login.php');
exit;
