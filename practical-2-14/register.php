<?php
require_once __DIR__ . '/db.php';

function sanitizeText(string $value): string
{
    return trim(htmlspecialchars(strip_tags($value), ENT_QUOTES, 'UTF-8'));
}

function sanitizeEmail(string $value): string
{
    return filter_var(trim($value), FILTER_SANITIZE_EMAIL);
}

function addFlashMessage(string $type, string $message): void
{
    $_SESSION['flash_message'] = [
        'type' => $type,
        'text' => $message,
    ];
}

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = $_POST;
    $fullname = sanitizeText($data['fullname'] ?? '');
    $enrollment = sanitizeText($data['enrollment'] ?? '');
    $email = sanitizeEmail($data['email'] ?? '');
    $mobile = sanitizeText($data['mobile'] ?? '');
    $department = sanitizeText($data['department'] ?? '');
    $semester = sanitizeText($data['semester'] ?? '');
    $password = $data['password'] ?? '';
    $confirmPassword = $data['confirm'] ?? '';
    $terms = isset($data['terms']);

    $errors = [];

    if ($fullname === '' || strlen($fullname) < 2) {
        $errors[] = 'Full name is required and must be at least 2 characters.';
    }

    if (!preg_match('/^[A-Za-z0-9]{11,12}$/', $enrollment)) {
        $errors[] = 'Enrollment number must contain 11 to 12 alphanumeric characters.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid institutional email address.';
    }

    if (!preg_match('/^[0-9]{10}$/', $mobile)) {
        $errors[] = 'Mobile number must contain exactly 10 digits.';
    }

    if ($department === '') {
        $errors[] = 'Please choose your department.';
    }

    if ($semester === '') {
        $errors[] = 'Please choose your current semester.';
    }

    if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password) || !preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'Password must be at least 8 characters long and include uppercase, lowercase, number, and special character.';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$terms) {
        $errors[] = 'You must accept the College IT policy before registering.';
    }

    if (empty($errors)) {
        try {
            $pdo = getDatabaseConnection();
            $existing = $pdo->prepare('SELECT id FROM registrations WHERE email = :email OR enrollment = :enrollment LIMIT 1');
            $existing->execute([
                ':email' => $email,
                ':enrollment' => $enrollment,
            ]);

            if ($existing->fetch()) {
                $errors[] = 'A student with this email or enrollment number is already registered.';
            } else {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('INSERT INTO registrations (fullname, enrollment, email, mobile, department, semester, password_hash) VALUES (:fullname, :enrollment, :email, :mobile, :department, :semester, :password_hash)');
                $stmt->execute([
                    ':fullname' => $fullname,
                    ':enrollment' => $enrollment,
                    ':email' => $email,
                    ':mobile' => $mobile,
                    ':department' => $department,
                    ':semester' => $semester,
                    ':password_hash' => $passwordHash,
                ]);

                $recordId = $pdo->lastInsertId();
                $record = [
                    'id' => (int) $recordId,
                    'fullname' => $fullname,
                    'enrollment' => $enrollment,
                    'email' => $email,
                    'mobile' => $mobile,
                    'department' => $department,
                    'semester' => $semester,
                    'created_at' => date('Y-m-d H:i:s'),
                ];

                ensureArchiveFiles();
                appendJsonArchive('registrations.json', $record);
                appendCsvArchive('registrations.csv', ['id', 'fullname', 'enrollment', 'email', 'mobile', 'department', 'semester', 'created_at'], $record);

                $successMessage = urlencode('Registration successful! Your record was saved in the database and CSV/JSON archives.');
                header('Location: registration.html?status=success&message=' . $successMessage);
                exit;
            }
        } catch (Throwable $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }

    if (!empty($errors)) {
        $errorMessage = urlencode(implode(' ', $errors));
        header('Location: registration.html?status=error&message=' . $errorMessage);
        exit;
    }
}

header('Location: registration.html?status=error&message=' . urlencode('Invalid request.'));
exit;
