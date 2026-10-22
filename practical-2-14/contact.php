<?php
require_once __DIR__ . '/db.php';

function sanitizeText(string $value): string
{
    return trim(htmlspecialchars(strip_tags($value), ENT_QUOTES, 'UTF-8'));
}

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: home.html?status=error&message=' . urlencode('Invalid request method.'));
    exit;
}

$name = sanitizeText($_POST['name'] ?? '');
$email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
$subject = sanitizeText($_POST['subject'] ?? '');
$message = sanitizeText($_POST['message'] ?? '');

$errors = [];

if ($name === '' || strlen($name) < 2) {
    $errors[] = 'Name is required.';
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Please enter a valid email address.';
}

if ($subject === '' || strlen($subject) < 3) {
    $errors[] = 'Subject is required.';
}

if ($message === '' || strlen($message) < 10) {
    $errors[] = 'Message must be at least 10 characters long.';
}

if (!empty($errors)) {
    header('Location: home.html?status=error&message=' . urlencode(implode(' ', $errors)));
    exit;
}

try {
    $pdo = getDatabaseConnection();
    $stmt = $pdo->prepare('INSERT INTO contact_messages (name, email, subject, message) VALUES (:name, :email, :subject, :message)');
    $stmt->execute([
        ':name' => $name,
        ':email' => $email,
        ':subject' => $subject,
        ':message' => $message,
    ]);

    $recordId = $pdo->lastInsertId();
    $record = [
        'id' => (int) $recordId,
        'name' => $name,
        'email' => $email,
        'subject' => $subject,
        'message' => $message,
        'created_at' => date('Y-m-d H:i:s'),
    ];

    appendJsonArchive('contact_messages.json', $record);
    appendCsvArchive('contact_messages.csv', ['id', 'name', 'email', 'subject', 'message', 'created_at'], $record);

    header('Location: home.html?status=success&message=' . urlencode('Your message has been saved successfully and forwarded to the admin.'));
    exit;
} catch (Throwable $e) {
    header('Location: home.html?status=error&message=' . urlencode('Database error: ' . $e->getMessage()));
    exit;
}
