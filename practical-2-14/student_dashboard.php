<?php
require_once __DIR__ . '/auth_check.php';

if (!isset($_SESSION['role'])) {
    header('Location: login.html');
    exit;
}

if ($_SESSION['role'] === 'admin') {
    header('Location: admin_dashboard.php');
    exit;
}

if ($_SESSION['role'] !== 'student') {
    header('Location: login.html');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <main style="padding:40px; max-width:800px; margin:0 auto;">
        <h2>Student Dashboard</h2>
        <p>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></p>
        <p><strong>Role:</strong> Student</p>
        <a href="logout.php" class="btn btn-primary">Logout</a>
    </main>
</body>
</html>
