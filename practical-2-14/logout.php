<?php
require_once __DIR__ . '/session_config.php';
require_once __DIR__ . '/mysql_db.php';
require_once __DIR__ . '/audit_log.php';

if (isset($_SESSION['user_id'])) {
    try {
        $pdo = getMysqlConnection();
        ensureAuditLogTable($pdo);
        logAuditEvent($pdo, (int) $_SESSION['user_id'], 'logout', 'User signed out', 'user', (int) $_SESSION['user_id']);

        $stmt = $pdo->prepare('UPDATE users SET remember_token_hash = NULL WHERE user_id = :id');
        $stmt->execute([':id' => $_SESSION['user_id']]);
    } catch (Throwable $e) {
        // Ignore DB error for logout; still log out the user.
    }
}

if (isset($_COOKIE['remember_me'])) {
    setcookie('remember_me', '', time() - 3600, '/', '', false, true);
}

$_SESSION = [];
session_unset();
session_destroy();

header('Location: login.html');
exit;
