<?php
require_once __DIR__ . '/session_config.php';
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/mysql_db.php';
require_once __DIR__ . '/audit_log.php';

if (!isset($_SESSION['role']) || strtolower(trim((string) $_SESSION['role'])) !== 'admin') {
    if (isset($_SESSION['role']) && strtolower(trim((string) $_SESSION['role'])) === 'student') {
        header('Location: student_dashboard.php');
    } else {
        header('Location: login.html');
    }
    exit;
}

$pdo = getMysqlConnection();
ensureAuditLogTable($pdo);

$menu = [
    'admin' => [
        ['label' => 'Dashboard', 'url' => 'admin_dashboard.php'],
        ['label' => 'Manage Students', 'url' => 'student_management.php'],
        ['label' => 'Manage Events', 'url' => 'event_management.php'],
        ['label' => 'Student Portal', 'url' => 'home.html'],
        ['label' => 'Audit Logs', 'url' => '#audit-log'],
        ['label' => 'Logout', 'url' => 'logout.php'],
    ],
    'student' => [
        ['label' => 'Dashboard', 'url' => 'student_dashboard.php'],
        ['label' => 'Home', 'url' => 'home.html'],
        ['label' => 'Logout', 'url' => 'logout.php'],
    ],
];

$stats = [
    'total_users' => 0,
    'total_students' => 0,
    'total_admins' => 0,
    'total_events' => 0,
    'total_registrations' => 0,
];

$stats['total_users'] = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$stats['total_students'] = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
$stats['total_admins'] = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
$stats['total_events'] = (int) $pdo->query('SELECT COUNT(*) FROM events')->fetchColumn();
$stats['total_registrations'] = (int) $pdo->query('SELECT COUNT(*) FROM registrations')->fetchColumn();

$latestRegistrations = $pdo->query(
    "SELECT r.registration_id, s.full_name AS student_name, e.title AS event_name, r.status, r.registered_at
     FROM registrations r
     JOIN students s ON s.student_id = r.student_id
     JOIN events e ON e.event_id = r.event_id
     ORDER BY r.registered_at DESC
     LIMIT 5"
)->fetchAll(PDO::FETCH_ASSOC);

$recentAuditLogs = $pdo->query(
    "SELECT a.*, COALESCE(u.full_name, 'System') AS actor_name
     FROM audit_logs a
     LEFT JOIN users u ON u.user_id = a.user_id
     ORDER BY a.created_at DESC
     LIMIT 8"
)->fetchAll(PDO::FETCH_ASSOC);

$roleLabel = ucfirst(strtolower((string) ($_SESSION['role'] ?? 'admin')));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f7fb;
            color: #1f2937;
        }
        .topbar {
            background: #1f3c88;
            color: white;
            padding: 16px 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        .topbar h2 { margin: 0; }
        .menu {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .menu a {
            color: white;
            text-decoration: none;
            padding: 8px 12px;
            border-radius: 8px;
            background: rgba(255,255,255,0.08);
            font-size: 14px;
        }
        .container {
            max-width: 1200px;
            margin: 28px auto;
            padding: 0 18px 40px;
        }
        .welcome {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        .welcome h1 {
            margin: 0;
            color: #1f3c88;
        }
        .badge {
            background: #e8f1ff;
            color: #1f3c88;
            border: 1px solid #d3e1ff;
            padding: 8px 12px;
            border-radius: 999px;
            font-weight: 700;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 26px;
        }
        .card {
            background: white;
            border: 1px solid #dde5f0;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
            padding: 18px;
        }
        .stat-label {
            color: #64748b;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            margin-top: 10px;
            color: #1f2937;
        }
        .layout {
            display: grid;
            grid-template-columns: 1.4fr 1fr;
            gap: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px 10px;
            border-bottom: 1px solid #edf2f7;
            text-align: left;
            vertical-align: top;
        }
        th {
            background: #f8fafc;
            color: #475569;
            font-size: 12px;
            text-transform: uppercase;
        }
        .status {
            display: inline-block;
            padding: 5px 8px;
            font-size: 12px;
            border-radius: 999px;
            font-weight: 700;
            text-transform: capitalize;
        }
        .status.registered { background: #eaf7ee; color: #166534; }
        .status.attended { background: #eaf3ff; color: #1d4ed8; }
        .status.cancelled { background: #fdeaea; color: #991b1b; }
        .audit-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .audit-list li {
            padding: 12px 0;
            border-bottom: 1px solid #edf2f7;
        }
        .muted {
            color: #64748b;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="topbar">
        <h2>StudentHub Admin</h2>
        <nav class="menu">
            <?php foreach ($menu[$_SESSION['role']] ?? $menu['admin'] as $item): ?>
                <a href="<?php echo htmlspecialchars($item['url']); ?>"><?php echo htmlspecialchars($item['label']); ?></a>
            <?php endforeach; ?>
        </nav>
    </div>

    <div class="container">
        <div class="welcome">
            <h1>Admin Dashboard</h1>
            <span class="badge">Welcome, <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Administrator'); ?> (<?php echo htmlspecialchars($roleLabel); ?>)</span>
        </div>

        <div class="stats-grid">
            <div class="card">
                <div class="stat-label">Total Users</div>
                <div class="stat-value"><?php echo (int) $stats['total_users']; ?></div>
            </div>
            <div class="card">
                <div class="stat-label">Students</div>
                <div class="stat-value"><?php echo (int) $stats['total_students']; ?></div>
            </div>
            <div class="card">
                <div class="stat-label">Admins</div>
                <div class="stat-value"><?php echo (int) $stats['total_admins']; ?></div>
            </div>
            <div class="card">
                <div class="stat-label">Events</div>
                <div class="stat-value"><?php echo (int) $stats['total_events']; ?></div>
            </div>
            <div class="card">
                <div class="stat-label">Registrations</div>
                <div class="stat-value"><?php echo (int) $stats['total_registrations']; ?></div>
            </div>
        </div>

        <div class="layout">
            <div class="card">
                <h3>Latest Registrations</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Event</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($latestRegistrations)): ?>
                            <tr>
                                <td colspan="4" class="muted">No registrations found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($latestRegistrations as $registration): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($registration['student_name'] ?? 'Unknown'); ?></td>
                                    <td><?php echo htmlspecialchars($registration['event_name'] ?? 'Unknown'); ?></td>
                                    <td><span class="status <?php echo htmlspecialchars($registration['status'] ?? 'registered'); ?>"><?php echo htmlspecialchars($registration['status'] ?? 'registered'); ?></span></td>
                                    <td><?php echo htmlspecialchars($registration['registered_at'] ?? ''); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="card" id="audit-log">
                <h3>Recent Audit Log</h3>
                <ul class="audit-list">
                    <?php if (empty($recentAuditLogs)): ?>
                        <li class="muted">No audit entries yet.</li>
                    <?php else: ?>
                        <?php foreach ($recentAuditLogs as $log): ?>
                            <li>
                                <strong><?php echo htmlspecialchars($log['action'] ?? 'Action'); ?></strong>
                                <div class="muted"><?php echo htmlspecialchars($log['actor_name'] ?? 'System'); ?> • <?php echo htmlspecialchars($log['created_at'] ?? ''); ?></div>
                                <div><?php echo htmlspecialchars($log['details'] ?? 'No details'); ?></div>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>
