<?php
require_once __DIR__ . '/session_config.php';
require_once __DIR__ . '/mysql_db.php';
require_once __DIR__ . '/audit_log.php';

$error = '';

if (isset($_GET['msg']) && $_GET['msg'] === 'timeout') {
    $error = 'Your session has expired. Please login again.';
}

if (isset($_SESSION['user_id'])) {
    header('Location: home.html');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emailOrEnrollment = trim($_POST['email'] ?? $_POST['enrollment'] ?? $_POST['email_or_enrollment'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember_me']) || isset($_POST['remember']);

    if ($emailOrEnrollment === '' || $password === '') {
        $error = 'Please enter email or enrollment number and password.';
    } else {
        try {
            $pdo = getMysqlConnection();

            $identifier = $emailOrEnrollment;
            $column = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'enrollment_number';

            $stmt = $pdo->prepare("SELECT * FROM users WHERE {$column} = :identifier LIMIT 1");
            $stmt->execute([':identifier' => $identifier]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);

                $_SESSION = [];
                $_SESSION['user_id'] = (int) $user['user_id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = strtolower(trim((string) $user['role']));
                $_SESSION['is_admin'] = ($_SESSION['role'] === 'admin');
                $_SESSION['last_activity'] = time();

                ensureAuditLogTable($pdo);
                logAuditEvent($pdo, (int) $user['user_id'], 'login', 'User signed in successfully', 'user', (int) $user['user_id']);

                $update = $pdo->prepare('UPDATE users SET last_login = NOW() WHERE user_id = :id');
                $update->execute([':id' => $user['user_id']]);

                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    $tokenHash = hash('sha256', $token);

                    $rememberStmt = $pdo->prepare('UPDATE users SET remember_token_hash = :token WHERE user_id = :id');
                    $rememberStmt->execute([
                        ':token' => $tokenHash,
                        ':id' => $user['user_id'],
                    ]);

                    setcookie('remember_me', $user['user_id'] . ':' . $token, time() + (30 * 24 * 60 * 60), '/', '', false, true);
                }

                if ($user['role'] === 'admin') {
                    header('Location: admin_dashboard.php');
                } else {
                    header('Location: home.html');
                }
                exit;
            } else {
                $error = 'Invalid email/enrollment or password.';
            }
        } catch (Throwable $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudentHub | Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="college-header">
        <div class="header-inner">
            <a href="home.html" class="brand-section">
                <div class="brand-logo"><i class="fa-solid fa-graduation-cap"></i></div>
                <div class="brand-text">
                    <h1>Apex Institute of Engineering & Technology</h1>
                    <p>Student Academic Information & Learning Management System</p>
                </div>
            </a>
            <div class="header-meta"><span class="term-badge">Academic Session 2026-27</span></div>
        </div>
    </header>

    <main class="auth-wrapper">
        <div class="auth-card">
            <div class="auth-header">
                <div class="auth-crest"><i class="fa-solid fa-user-shield"></i></div>
                <h2>StudentHub Login</h2>
                <p>Enter your institutional credentials to access your dashboard</p>
            </div>

            <?php if ($error): ?>
                <div class="form-alert-banner error" style="display:flex;">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <div><?php echo htmlspecialchars($error); ?></div>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label class="form-label" for="email">Email</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-envelope input-icon"></i>
                        <input type="email" id="email" name="email" class="form-control has-icon" placeholder="Enter email" required>
                    </div>
                </div>

                <div class="form-group">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                        <label class="form-label" for="password" style="margin-bottom:0;">Password</label>
                    </div>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-lock input-icon"></i>
                        <input type="password" id="password" name="password" class="form-control has-icon" placeholder="••••••••" required>
                    </div>
                </div>

                <div class="form-group" style="display:flex; justify-content:space-between; align-items:center;">
                    <label class="checkbox-label" for="remember_me">
                        <input type="checkbox" id="remember_me" name="remember_me" checked>
                        <span>Keep me signed in</span>
                    </label>
                </div>

                <div style="display:flex; gap:10px; margin-top:20px;">
                    <button type="submit" class="btn btn-primary" style="flex:1;">
                        <i class="fa-solid fa-arrow-right-to-bracket"></i> Login
                    </button>
                    <button type="reset" class="btn btn-outline" style="width:90px;">Clear</button>
                </div>
            </form>

            <div class="auth-footer">
                <p>
                    Need an account?
                    <a href="registration.html">Register New Student <i class="fa-solid fa-arrow-right"></i></a>
                </p>
            </div>
        </div>
    </main>

    <footer class="portal-footer">
        <div class="footer-inner">
            <p>&copy; 2026 Apex Institute of Engineering & Technology. All Rights Reserved.</p>
        </div>
    </footer>
</body>
</html>
