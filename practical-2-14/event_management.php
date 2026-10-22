<?php
require_once __DIR__ . '/session_config.php';
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/mysql_db.php';
require_once __DIR__ . '/audit_log.php';

if (!isset($_SESSION['role']) || strtolower(trim((string) $_SESSION['role'])) !== 'admin') {
    header('Location: login.html');
    exit;
}

function ensureEventsTable(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS events (
            event_id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(150) NOT NULL,
            description TEXT,
            event_date DATE NOT NULL,
            venue VARCHAR(150) NOT NULL,
            organizer VARCHAR(100),
            capacity INT,
            status ENUM('open', 'closed', 'cancelled') NOT NULL DEFAULT 'open',
            poster VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB"
    );

    $columns = $pdo->query("SHOW COLUMNS FROM events LIKE 'poster'")->fetchAll();
    if (empty($columns)) {
        $pdo->exec("ALTER TABLE events ADD COLUMN poster VARCHAR(255) NULL AFTER status");
    }
}

function sanitizeText(?string $value): string
{
    return trim(strip_tags((string) $value));
}

function validatePosterUpload(?array $file): ?string
{
    if (!$file || !isset($file['name']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('Poster upload failed. Please try again.');
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes, true)) {
        throw new InvalidArgumentException('Invalid poster format. Only JPG, PNG, and WEBP images are allowed.');
    }

    if ($file['size'] > 2 * 1024 * 1024) {
        throw new InvalidArgumentException('Poster size must be less than or equal to 2MB.');
    }

    $uploadDir = __DIR__ . '/uploads/events';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($extension === '') {
        $extension = 'jpg';
    }

    $safeName = 'event_' . uniqid('', true) . '.' . $extension;
    $targetPath = $uploadDir . '/' . $safeName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new InvalidArgumentException('Failed to save the uploaded poster.');
    }

    return 'uploads/events/' . $safeName;
}

$message = '';
$messageType = '';
$editEvent = null;

try {
    $pdo = getMysqlConnection();
    ensureAuditLogTable($pdo);
    ensureEventsTable($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'save') {
            $eventId = isset($_POST['event_id']) && $_POST['event_id'] !== '' ? (int) $_POST['event_id'] : null;
            $title = sanitizeText($_POST['title'] ?? '');
            $description = trim((string) ($_POST['description'] ?? ''));
            $eventDate = sanitizeText($_POST['event_date'] ?? '');
            $venue = sanitizeText($_POST['venue'] ?? '');
            $organizer = sanitizeText($_POST['organizer'] ?? '');
            $capacity = isset($_POST['capacity']) && $_POST['capacity'] !== '' ? (int) $_POST['capacity'] : null;
            $status = in_array($_POST['status'] ?? '', ['open', 'closed', 'cancelled'], true) ? $_POST['status'] : 'open';

            if ($title === '' || $eventDate === '' || $venue === '') {
                throw new InvalidArgumentException('Title, event date, and venue are required.');
            }

            if ($capacity !== null && $capacity < 1) {
                throw new InvalidArgumentException('Capacity must be a positive number.');
            }

            $newPosterPath = validatePosterUpload($_FILES['poster'] ?? null);

            if ($eventId) {
                $existing = $pdo->prepare('SELECT poster FROM events WHERE event_id = :id LIMIT 1');
                $existing->execute([':id' => $eventId]);
                $currentPoster = $existing->fetchColumn();

                if ($newPosterPath !== null) {
                    $posterValue = $newPosterPath;
                    if ($currentPoster && file_exists(__DIR__ . '/' . $currentPoster) && $currentPoster !== $newPosterPath) {
                        @unlink(__DIR__ . '/' . $currentPoster);
                    }
                } else {
                    $posterValue = $currentPoster;
                }

                $stmt = $pdo->prepare(
                    'UPDATE events SET title = :title, description = :description, event_date = :event_date, venue = :venue, organizer = :organizer, capacity = :capacity, status = :status, poster = :poster WHERE event_id = :id'
                );
                $stmt->execute([
                    ':title' => $title,
                    ':description' => $description,
                    ':event_date' => $eventDate,
                    ':venue' => $venue,
                    ':organizer' => $organizer,
                    ':capacity' => $capacity,
                    ':status' => $status,
                    ':poster' => $posterValue,
                    ':id' => $eventId,
                ]);
                $message = 'Event updated successfully.';
                logAuditEvent($pdo, (int) $_SESSION['user_id'], 'update_event', 'Updated event information', 'event', $eventId);
            } else {
                if ($newPosterPath === null) {
                    $newPosterPath = null;
                }

                $stmt = $pdo->prepare(
                    'INSERT INTO events (title, description, event_date, venue, organizer, capacity, status, poster) VALUES (:title, :description, :event_date, :venue, :organizer, :capacity, :status, :poster)'
                );
                $stmt->execute([
                    ':title' => $title,
                    ':description' => $description,
                    ':event_date' => $eventDate,
                    ':venue' => $venue,
                    ':organizer' => $organizer,
                    ':capacity' => $capacity,
                    ':status' => $status,
                    ':poster' => $newPosterPath,
                ]);
                $message = 'Event added successfully.';
                logAuditEvent($pdo, (int) $_SESSION['user_id'], 'create_event', 'Created new event', 'event', (int) $pdo->lastInsertId());
            }

            $messageType = 'success';
        } elseif ($action === 'delete') {
            $eventId = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;

            if ($eventId <= 0) {
                throw new InvalidArgumentException('Invalid event selected for deletion.');
            }

            $stmt = $pdo->prepare('SELECT poster FROM events WHERE event_id = :id LIMIT 1');
            $stmt->execute([':id' => $eventId]);
            $posterPath = $stmt->fetchColumn();

            $del = $pdo->prepare('DELETE FROM events WHERE event_id = :id');
            $del->execute([':id' => $eventId]);

            if ($posterPath && file_exists(__DIR__ . '/' . $posterPath)) {
                @unlink(__DIR__ . '/' . $posterPath);
            }

            $message = 'Event deleted successfully.';
            $messageType = 'success';
            logAuditEvent($pdo, (int) $_SESSION['user_id'], 'delete_event', 'Deleted an event', 'event', $eventId);
        }
    }

    $statusFilter = $_GET['status'] ?? '';
    $search = trim((string) ($_GET['search'] ?? ''));
    $editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;

    $query = 'SELECT * FROM events WHERE 1 = 1';
    $params = [];

    if ($search !== '') {
        $query .= ' AND (title LIKE :search OR venue LIKE :search OR organizer LIKE :search)';
        $params[':search'] = '%' . $search . '%';
    }

    if ($statusFilter !== '') {
        $query .= ' AND status = :status';
        $params[':status'] = $statusFilter;
    }

    $query .= ' ORDER BY event_date DESC';

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($editId > 0) {
        $editStmt = $pdo->prepare('SELECT * FROM events WHERE event_id = :id LIMIT 1');
        $editStmt->execute([':id' => $editId]);
        $editEvent = $editStmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    $message = $e->getMessage();
    $messageType = 'error';
    $events = [];
    $editEvent = null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Management</title>
    <style>
        :root {
            --primary: #1f3c88;
            --primary-dark: #142d60;
            --success: #1f8f4d;
            --error: #c62828;
            --warning: #d97706;
            --bg: #f4f7fb;
            --card: #ffffff;
            --text: #1f2937;
            --muted: #6b7280;
            --border: #dfe8f3;
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
            margin: 28px auto;
            padding: 0 18px 40px;
        }
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        h2 {
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
            font-weight: 600;
            transition: 0.2s;
        }
        .btn-primary { background: var(--primary); color: white; }
        .btn-secondary { background: #eaf0ff; color: var(--primary-dark); }
        .btn-danger { background: var(--error); color: white; }
        .alert {
            padding: 12px 14px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-weight: 600;
        }
        .alert.success { background: #eaf8ef; color: var(--success); border: 1px solid #bce7c8; }
        .alert.error { background: #fdeaea; color: var(--error); border: 1px solid #f4b7b7; }
        .panel {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.03);
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
            font-size: 13px;
            font-weight: 700;
            color: var(--text);
        }
        input, select, textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            background: #fff;
        }
        textarea {
            min-height: 90px;
            resize: vertical;
        }
        .filters {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: end;
        }
        .filters .field { flex: 1 1 180px; }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px 10px;
            border-bottom: 1px solid var(--border);
            text-align: left;
            vertical-align: top;
        }
        th {
            background: #f8fafc;
            font-size: 13px;
            text-transform: uppercase;
            color: var(--muted);
        }
        td {
            font-size: 14px;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 999px;
            font-weight: 700;
            font-size: 12px;
            text-transform: capitalize;
        }
        .status-open { background: #eaf8ef; color: var(--success); }
        .status-closed { background: #fff3db; color: var(--warning); }
        .status-cancelled { background: #fdeaea; color: var(--error); }
        .poster-preview {
            display: block;
            width: 120px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: #f5f5f5;
        }
        .actions-cell {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .small-btn {
            display: inline-block;
            padding: 7px 10px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 700;
        }
        .small-btn.edit { background: #eaf0ff; color: var(--primary-dark); }
        .small-btn.delete { background: #fdeaea; color: var(--error); }
        @media (max-width: 700px) {
            .grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="topbar">
            <h2>Event Management</h2>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <a href="admin_dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
                <a href="logout.php" class="btn btn-primary">Logout</a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert <?php echo htmlspecialchars($messageType); ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="panel">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save">
                <?php if ($editEvent): ?>
                    <input type="hidden" name="event_id" value="<?php echo (int) $editEvent['event_id']; ?>">
                <?php endif; ?>

                <div class="grid">
                    <div class="field">
                        <label for="title">Event Title</label>
                        <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($editEvent['title'] ?? ''); ?>" required>
                    </div>

                    <div class="field">
                        <label for="event_date">Event Date</label>
                        <input type="date" id="event_date" name="event_date" value="<?php echo htmlspecialchars($editEvent['event_date'] ?? ''); ?>" required>
                    </div>

                    <div class="field">
                        <label for="venue">Venue</label>
                        <input type="text" id="venue" name="venue" value="<?php echo htmlspecialchars($editEvent['venue'] ?? ''); ?>" required>
                    </div>

                    <div class="field">
                        <label for="organizer">Organizer</label>
                        <input type="text" id="organizer" name="organizer" value="<?php echo htmlspecialchars($editEvent['organizer'] ?? ''); ?>">
                    </div>

                    <div class="field">
                        <label for="capacity">Capacity</label>
                        <input type="number" id="capacity" name="capacity" min="1" value="<?php echo htmlspecialchars((string) ($editEvent['capacity'] ?? '')); ?>">
                    </div>

                    <div class="field">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="open" <?php echo (($editEvent['status'] ?? 'open') === 'open') ? 'selected' : ''; ?>>Open</option>
                            <option value="closed" <?php echo (($editEvent['status'] ?? 'open') === 'closed') ? 'selected' : ''; ?>>Closed</option>
                            <option value="cancelled" <?php echo (($editEvent['status'] ?? 'open') === 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                </div>

                <div class="field" style="margin-top: 15px;">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" required><?php echo htmlspecialchars($editEvent['description'] ?? ''); ?></textarea>
                </div>

                <div class="field" style="margin-top: 15px; max-width: 350px;">
                    <label for="poster">Event Poster</label>
                    <input type="file" id="poster" name="poster" accept="image/jpeg,image/png,image/webp">
                    <small style="color: var(--muted);">Allowed: JPG, PNG, WEBP. Maximum size: 2MB.</small>
                    <?php if (!empty($editEvent['poster'])): ?>
                        <img src="<?php echo htmlspecialchars($editEvent['poster']); ?>" alt="Event Poster" class="poster-preview" style="margin-top:10px;">
                    <?php endif; ?>
                </div>

                <div style="margin-top:18px; display:flex; gap:10px; flex-wrap:wrap;">
                    <button type="submit" class="btn btn-primary"><?php echo $editEvent ? 'Update Event' : 'Add Event'; ?></button>
                    <?php if ($editEvent): ?>
                        <a href="event_management.php" class="btn btn-secondary">Cancel Edit</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="panel">
            <form method="GET" class="filters">
                <div class="field">
                    <label for="search">Search</label>
                    <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" placeholder="Search title, venue or organizer">
                </div>
                <div class="field">
                    <label for="status">Filter By Status</label>
                    <select id="status" name="status">
                        <option value="">All Status</option>
                        <option value="open" <?php echo (($_GET['status'] ?? '') === 'open') ? 'selected' : ''; ?>>Open</option>
                        <option value="closed" <?php echo (($_GET['status'] ?? '') === 'closed') ? 'selected' : ''; ?>>Closed</option>
                        <option value="cancelled" <?php echo (($_GET['status'] ?? '') === 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                <div class="field">
                    <button type="submit" class="btn btn-primary">Apply Filter</button>
                </div>
            </form>
        </div>

        <div class="panel" style="overflow:auto;">
            <table>
                <thead>
                    <tr>
                        <th>Poster</th>
                        <th>Title</th>
                        <th>Date</th>
                        <th>Venue</th>
                        <th>Organizer</th>
                        <th>Capacity</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($events)): ?>
                        <tr>
                            <td colspan="8" style="text-align:center; color: var(--muted);">No events found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($events as $event): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($event['poster'])): ?>
                                        <img src="<?php echo htmlspecialchars($event['poster']); ?>" alt="Poster" class="poster-preview">
                                    <?php else: ?>
                                        <span style="color: var(--muted);">No poster</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($event['title']); ?></td>
                                <td><?php echo htmlspecialchars($event['event_date']); ?></td>
                                <td><?php echo htmlspecialchars($event['venue']); ?></td>
                                <td><?php echo htmlspecialchars($event['organizer'] ?: '—'); ?></td>
                                <td><?php echo htmlspecialchars((string) ($event['capacity'] ?? '—')); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo htmlspecialchars(strtolower((string) $event['status'])); ?>"><?php echo htmlspecialchars(strtoupper((string) $event['status'])); ?></span>
                                </td>
                                <td>
                                    <div class="actions-cell">
                                        <a href="event_management.php?edit=<?php echo (int) $event['event_id']; ?>" class="small-btn edit">Edit</a>
                                        <form method="POST" onsubmit="return confirm('Delete this event?');" style="display:inline;">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="event_id" value="<?php echo (int) $event['event_id']; ?>">
                                            <button type="submit" class="small-btn delete">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
