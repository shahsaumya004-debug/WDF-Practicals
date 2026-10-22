<?php
require_once __DIR__ . '/session_config.php';

$timeoutSeconds = 900;

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    session_unset();
    session_destroy();
    header('Location: login.html');
    exit;
}

$role = strtolower(trim((string) $_SESSION['role']));
if (!in_array($role, ['admin', 'student'], true)) {
    session_unset();
    session_destroy();
    header('Location: login.html');
    exit;
}

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeoutSeconds)) {
    session_unset();
    session_destroy();
    header('Location: login.html?msg=timeout');
    exit;
}

$_SESSION['last_activity'] = time();
