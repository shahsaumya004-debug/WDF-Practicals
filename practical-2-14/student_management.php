<?php
require_once __DIR__ . '/session_config.php';
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/mysql_db.php';
require_once __DIR__ . '/audit_log.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: student_dashboard.php');
    exit;
}

function getStudentDbConnection(): mysqli
{
    $conn = new mysqli('localhost', 'root', '', 'studenthub');
    if ($conn->connect_error) {
        throw new RuntimeException('Database connection failed: ' . $conn->connect_error);
    }

    $conn->set_charset('utf8mb4');
    return $conn;
}

$message = '';
$messageType = '';
$editStudent = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        $conn = getStudentDbConnection();

        if ($action === 'save') {
            $studentId = isset($_POST['student_id']) && $_POST['student_id'] !== '' ? (int) $_POST['student_id'] : null;
            $fullName = trim($_POST['full_name'] ?? '');
            $enrollmentNumber = trim($_POST['enrollment_number'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $department = trim($_POST['department'] ?? '');
            $semester = trim($_POST['semester'] ?? '');

            $auditPdo = getMysqlConnection();
            ensureAuditLogTable($auditPdo);

            if ($fullName === '' || $enrollmentNumber === '' || $email === '' || $department === '' || $semester === '') {
                throw new InvalidArgumentException('Please fill in all required student fields.');
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Please enter a valid email address.');
            }

            if ($studentId) {
                $stmt = $conn->prepare('UPDATE students SET full_name = ?, enrollment_number = ?, email = ?, phone = ?, department = ?, semester = ? WHERE student_id = ?');
                $stmt->bind_param('ssssssi', $fullName, $enrollmentNumber, $email, $phone, $department, $semester, $studentId);
                $stmt->execute();
                $message = 'Student record updated successfully.';
                logAuditEvent($auditPdo, (int) $_SESSION['user_id'], 'update_student', 'Updated student profile', 'student', $studentId);
            } else {
                $stmt = $conn->prepare('INSERT INTO students (full_name, enrollment_number, email, phone, department, semester) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->bind_param('ssssss', $fullName, $enrollmentNumber, $email, $phone, $department, $semester);
                $stmt->execute();
                $message = 'New student added successfully.';
                logAuditEvent($auditPdo, (int) $_SESSION['user_id'], 'create_student', 'Created new student record', 'student', (int) $conn->insert_id);
            }

            $messageType = 'success';
        } elseif ($action === 'delete') {
            $studentId = isset($_POST['student_id']) ? (int) $_POST['student_id'] : 0;

            if ($studentId <= 0) {
                throw new InvalidArgumentException('Invalid student selected for deletion.');
            }

            $stmt = $conn->prepare('DELETE FROM students WHERE student_id = ?');
            $stmt->bind_param('i', $studentId);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                $message = 'Student deleted successfully.';
                $messageType = 'success';
                $auditPdo = getMysqlConnection();
                ensureAuditLogTable($auditPdo);
                logAuditEvent($auditPdo, (int) $_SESSION['user_id'], 'delete_student', 'Deleted student record', 'student', $studentId);
            } else {
                $message = 'Student not found or already deleted.';
                $messageType = 'error';
            }
        }
    } catch (Throwable $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
}

$search = trim($_GET['search'] ?? '');
$departmentFilter = trim($_GET['department'] ?? '');
$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;

try {
    $conn = getStudentDbConnection();

    $departmentOptions = $conn->query('SELECT DISTINCT department FROM students ORDER BY department');

    $query = 'SELECT * FROM students WHERE 1 = 1';
    $types = '';
    $params = [];

    if ($search !== '') {
        $query .= ' AND (full_name LIKE ? OR email LIKE ?)';
        $like = '%' . $search . '%';
        $types .= 'ss';
        $params[] = $like;
        $params[] = $like;
    }

    if ($departmentFilter !== '') {
        $query .= ' AND department = ?';
        $types .= 's';
        $params[] = $departmentFilter;
    }

    $query .= ' ORDER BY student_id DESC';

    $stmt = $conn->prepare($query);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $studentsResult = $stmt->get_result();
    $students = $studentsResult->fetch_all(MYSQLI_ASSOC);

    if ($editId > 0) {
        $stmt = $conn->prepare('SELECT * FROM students WHERE student_id = ? LIMIT 1');
        $stmt->bind_param('i', $editId);
        $stmt->execute();
        $editStudent = $stmt->get_result()->fetch_assoc();
    }
} catch (Throwable $e) {
    $message = $e->getMessage();
    $messageType = 'error';
    $students = [];
    $departmentOptions = null;
    $courseOptions = null;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Management</title>
    <style>
        :root {
            --primary: #1f3c88;
            --primary-dark: #142d60;
            --success: #1e8e3e;
            --error: #c62828;
            --warning: #f59e0b;
            --bg: #f4f7fb;
            --card: #ffffff;
            --text: #1f2937;
            --muted: #6b7280;
            --border: #dbe3ee;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: var(--bg);
            color: var(--text);
        }
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px 40px;
        }
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }
        .topbar h2 {
            margin: 0;
            color: var(--primary-dark);
        }
        .btn {
            display: inline-block;
            padding: 10px 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            transition: 0.2s ease;
        }
        .btn-primary { background: var(--primary); color: white; }
        .btn-secondary { background: #e5edff; color: var(--primary-dark); }
        .btn-danger { background: var(--error); color: white; }
        .btn-success { background: var(--success); color: white; }
        .alert {
            padding: 12px 14px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 600;
        }
        .alert.success { background: #eaf7ee; color: var(--success); border: 1px solid #bfe8cb; }
        .alert.error { background: #fdeaea; color: var(--error); border: 1px solid #f2b5b5; }
        .panel {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
            margin-bottom: 20px;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
        }
        .field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        label {
            font-weight: 700;
            font-size: 13px;
            color: var(--text);
        }
        input, select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            background: #fff;
        }
        .filters {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: end;
        }
        .filters .field { flex: 1 1 180px; }
        .actions-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            overflow: hidden;
        }
        th, td {
            padding: 12px 10px;
            border-bottom: 1px solid var(--border);
            text-align: left;
            vertical-align: top;
            font-size: 14px;
        }
        th {
            background: #eef3ff;
            color: var(--primary-dark);
        }
        .status-tag {
            display: inline-block;
            padding: 5px 8px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: bold;
            text-transform: capitalize;
        }
        .status-active { background: #eaf7ee; color: var(--success); }
        .status-inactive { background: #fff2e6; color: #b45309; }
        .status-on-leave { background: #f3e8ff; color: #7c3aed; }
        .table-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .small-btn {
            padding: 7px 10px;
            border-radius: 6px;
            text-decoration: none;
            color: white;
            font-size: 12px;
            border: none;
            cursor: pointer;
        }
        .small-btn.edit { background: var(--primary); }
        .small-btn.delete { background: var(--error); }
        .empty {
            padding: 18px;
            text-align: center;
            color: var(--muted);
        }
        @media (max-width: 700px) {
            .topbar, .actions-row { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="topbar">
            <h2>Student Management</h2>
            <a href="admin_dashboard.php" class="btn btn-secondary">Back to Admin Dashboard</a>
        </div>

        <?php if ($message !== ''): ?>
            <div class="alert <?php echo $messageType === 'success' ? 'success' : 'error'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="panel">
            <h3 style="margin-top:0;"><?php echo $editStudent ? 'Edit Student' : 'Add New Student'; ?></h3>
            <form method="POST" action="student_management.php">
                <input type="hidden" name="action" value="save">
                <?php if ($editStudent): ?>
                    <input type="hidden" name="student_id" value="<?php echo (int) $editStudent['student_id']; ?>">
                <?php endif; ?>

                <div class="grid">
                    <div class="field">
                        <label for="full_name">Full Name</label>
                        <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($editStudent['full_name'] ?? ''); ?>" required>
                    </div>
                    <div class="field">
                        <label for="enrollment_number">Enrollment Number</label>
                        <input type="text" id="enrollment_number" name="enrollment_number" value="<?php echo htmlspecialchars($editStudent['enrollment_number'] ?? ''); ?>" required>
                    </div>
                    <div class="field">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($editStudent['email'] ?? ''); ?>" required>
                    </div>
                    <div class="field">
                        <label for="phone">Phone</label>
                        <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($editStudent['phone'] ?? ''); ?>">
                    </div>
                    <div class="field">
                        <label for="department">Department</label>
                        <input type="text" id="department" name="department" value="<?php echo htmlspecialchars($editStudent['department'] ?? ''); ?>" required>
                    </div>
                    <div class="field">
                        <label for="semester">Semester</label>
                        <input type="text" id="semester" name="semester" value="<?php echo htmlspecialchars($editStudent['semester'] ?? ''); ?>" required>
                    </div>
                </div>

                <div style="margin-top:16px; display:flex; gap:10px; flex-wrap:wrap;">
                    <button type="submit" class="btn btn-success"><?php echo $editStudent ? 'Update Student' : 'Add Student'; ?></button>
                    <?php if ($editStudent): ?>
                        <a href="student_management.php" class="btn btn-secondary">Cancel Edit</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="panel">
            <div class="actions-row">
                <h3 style="margin:0;">Student Records</h3>
                <form method="GET" action="student_management.php" class="filters">
                    <div class="field">
                        <label for="search">Search</label>
                        <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Name or email">
                    </div>
                    <div class="field">
                        <label for="department">Department</label>
                        <select id="department" name="department">
                            <option value="">All</option>
                            <?php if ($departmentOptions): while ($row = $departmentOptions->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($row['department']); ?>" <?php echo $departmentFilter === $row['department'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($row['department']); ?>
                                </option>
                            <?php endwhile; endif; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Apply Filter</button>
                </form>
            </div>

            <?php if (empty($students)): ?>
                <div class="empty">No student records found.</div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Enrollment</th>
                            <th>Email</th>
                            <th>Department</th>
                            <th>Semester</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                            <tr>
                                <td><?php echo (int) $student['student_id']; ?></td>
                                <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($student['enrollment_number']); ?></td>
                                <td><?php echo htmlspecialchars($student['email']); ?></td>
                                <td><?php echo htmlspecialchars($student['department']); ?></td>
                                <td><?php echo htmlspecialchars($student['semester']); ?></td>
                                <td>
                                    <div class="table-actions">
                                        <a href="student_management.php?edit=<?php echo (int) $student['student_id']; ?>" class="small-btn edit">Edit</a>
                                        <form method="POST" action="student_management.php" style="display:inline;" onsubmit="return confirm('Delete this student record? This action cannot be undone.');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="student_id" value="<?php echo (int) $student['student_id']; ?>">
                                            <button type="submit" class="small-btn delete">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
