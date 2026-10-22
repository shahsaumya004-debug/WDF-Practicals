<?php
require_once __DIR__ . '/session_config.php';
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/mysql_db.php';

header('Content-Type: application/json; charset=utf-8');

function sendJson(int $statusCode, string $message, array $data = []): void
{
    http_response_code($statusCode);
    echo json_encode([
        'status' => $statusCode,
        'message' => $message,
        'data' => $data,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $pdo = getMysqlConnection();

    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

    if ($method === 'GET') {
        $search = trim((string) ($_GET['search'] ?? ''));
        $statusFilter = trim((string) ($_GET['status'] ?? ''));

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

        sendJson(200, 'Events fetched successfully.', ['events' => $events]);
    }

    if ($method !== 'POST') {
        sendJson(405, 'Method not allowed. Use GET or POST.');
    }

    $rawBody = file_get_contents('php://input');
    $payload = json_decode($rawBody, true);
    $form = is_array($payload) ? $payload : $_POST;
    $action = strtolower(trim((string) ($form['action'] ?? $_POST['action'] ?? '')));

    if ($action === '') {
        sendJson(400, 'Action is required.');
    }

    if ($action === 'list') {
        $search = trim((string) ($form['search'] ?? ''));
        $statusFilter = trim((string) ($form['status'] ?? ''));

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

        sendJson(200, 'Events retrieved successfully.', ['events' => $events]);
    }

    if ($action === 'add') {
        $title = trim((string) ($form['title'] ?? ''));
        $description = trim((string) ($form['description'] ?? ''));
        $eventDate = trim((string) ($form['event_date'] ?? ''));
        $venue = trim((string) ($form['venue'] ?? ''));
        $organizer = trim((string) ($form['organizer'] ?? ''));
        $capacity = isset($form['capacity']) && $form['capacity'] !== '' ? (int) $form['capacity'] : null;
        $status = in_array(($form['status'] ?? 'open'), ['open', 'closed', 'cancelled'], true) ? (string) $form['status'] : 'open';
        $poster = trim((string) ($form['poster'] ?? ''));

        if ($title === '' || $eventDate === '' || $venue === '') {
            sendJson(422, 'Title, event date, and venue are required.');
        }

        if ($capacity !== null && $capacity < 1) {
            sendJson(422, 'Capacity must be a positive number.');
        }

        if (!empty($eventDate) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate)) {
            sendJson(422, 'Event date must be in YYYY-MM-DD format.');
        }

        $sql = 'INSERT INTO events (title, description, event_date, venue, organizer, capacity, status, poster) VALUES (:title, :description, :event_date, :venue, :organizer, :capacity, :status, :poster)';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':title' => $title,
            ':description' => $description,
            ':event_date' => $eventDate,
            ':venue' => $venue,
            ':organizer' => $organizer,
            ':capacity' => $capacity,
            ':status' => $status,
            ':poster' => $poster !== '' ? $poster : null,
        ]);

        $eventId = (int) $pdo->lastInsertId();
        $inserted = $pdo->prepare('SELECT * FROM events WHERE event_id = :id LIMIT 1');
        $inserted->execute([':id' => $eventId]);
        $event = $inserted->fetch(PDO::FETCH_ASSOC);

        sendJson(201, 'Event added successfully.', ['event' => $event]);
    }

    if ($action === 'update') {
        $eventId = isset($form['event_id']) ? (int) $form['event_id'] : 0;
        $title = trim((string) ($form['title'] ?? ''));
        $description = trim((string) ($form['description'] ?? ''));
        $eventDate = trim((string) ($form['event_date'] ?? ''));
        $venue = trim((string) ($form['venue'] ?? ''));
        $organizer = trim((string) ($form['organizer'] ?? ''));
        $capacity = isset($form['capacity']) && $form['capacity'] !== '' ? (int) $form['capacity'] : null;
        $status = in_array(($form['status'] ?? 'open'), ['open', 'closed', 'cancelled'], true) ? (string) $form['status'] : 'open';
        $poster = trim((string) ($form['poster'] ?? ''));

        if ($eventId <= 0) {
            sendJson(422, 'A valid event ID is required for update.');
        }

        if ($title === '' || $eventDate === '' || $venue === '') {
            sendJson(422, 'Title, event date, and venue are required.');
        }

        if ($capacity !== null && $capacity < 1) {
            sendJson(422, 'Capacity must be a positive number.');
        }

        $existing = $pdo->prepare('SELECT * FROM events WHERE event_id = :id LIMIT 1');
        $existing->execute([':id' => $eventId]);
        if (!$existing->fetch()) {
            sendJson(404, 'Event not found.');
        }

        $sql = 'UPDATE events SET title = :title, description = :description, event_date = :event_date, venue = :venue, organizer = :organizer, capacity = :capacity, status = :status, poster = :poster WHERE event_id = :id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':title' => $title,
            ':description' => $description,
            ':event_date' => $eventDate,
            ':venue' => $venue,
            ':organizer' => $organizer,
            ':capacity' => $capacity,
            ':status' => $status,
            ':poster' => $poster !== '' ? $poster : null,
            ':id' => $eventId,
        ]);

        $updated = $pdo->prepare('SELECT * FROM events WHERE event_id = :id LIMIT 1');
        $updated->execute([':id' => $eventId]);
        $event = $updated->fetch(PDO::FETCH_ASSOC);

        sendJson(200, 'Event updated successfully.', ['event' => $event]);
    }

    if ($action === 'delete') {
        $eventId = isset($form['event_id']) ? (int) $form['event_id'] : 0;

        if ($eventId <= 0) {
            sendJson(422, 'A valid event ID is required for deletion.');
        }

        $check = $pdo->prepare('SELECT event_id FROM events WHERE event_id = :id LIMIT 1');
        $check->execute([':id' => $eventId]);
        if (!$check->fetch()) {
            sendJson(404, 'Event not found.');
        }

        $stmt = $pdo->prepare('DELETE FROM events WHERE event_id = :id');
        $stmt->execute([':id' => $eventId]);

        sendJson(200, 'Event deleted successfully.', ['event_id' => $eventId]);
    }

    sendJson(400, 'Unsupported action.');
} catch (Throwable $e) {
    sendJson(500, 'Server error: ' . $e->getMessage());
}
