<?php
function getDatabaseConnection(): PDO
{
    $dbDir = __DIR__ . '/data';
    if (!is_dir($dbDir)) {
        mkdir($dbDir, 0777, true);
    }

    $dbFile = $dbDir . '/student_portal.db';
    $pdo = new PDO('sqlite:' . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS registrations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            fullname TEXT NOT NULL,
            enrollment TEXT NOT NULL,
            email TEXT NOT NULL,
            mobile TEXT NOT NULL,
            department TEXT NOT NULL,
            semester TEXT NOT NULL,
            password_hash TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS contact_messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL,
            subject TEXT NOT NULL,
            message TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )"
    );

    return $pdo;
}

function ensureArchiveFiles(): void
{
    $folder = __DIR__ . '/data';
    if (!is_dir($folder)) {
        mkdir($folder, 0777, true);
    }

    $jsonFile = $folder . '/registrations.json';
    if (!file_exists($jsonFile)) {
        file_put_contents($jsonFile, "[]");
    }

    $csvFile = $folder . '/registrations.csv';
    if (!file_exists($csvFile)) {
        $header = "id,fullname,enrollment,email,mobile,department,semester,created_at\n";
        file_put_contents($csvFile, $header);
    }

    $contactCsv = $folder . '/contact_messages.csv';
    if (!file_exists($contactCsv)) {
        $header = "id,name,email,subject,message,created_at\n";
        file_put_contents($contactCsv, $header);
    }

    $contactJson = $folder . '/contact_messages.json';
    if (!file_exists($contactJson)) {
        file_put_contents($contactJson, "[]");
    }
}

function appendJsonArchive(string $fileName, array $record): void
{
    $filePath = __DIR__ . '/data/' . $fileName;
    ensureArchiveFiles();
    $records = [];

    if (file_exists($filePath) && filesize($filePath) > 0) {
        $existing = file_get_contents($filePath);
        $records = json_decode($existing, true);
        if (!is_array($records)) {
            $records = [];
        }
    }

    $records[] = $record;
    file_put_contents($filePath, json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function appendCsvArchive(string $fileName, array $headers, array $record): void
{
    $filePath = __DIR__ . '/data/' . $fileName;
    ensureArchiveFiles();

    if (!file_exists($filePath) || filesize($filePath) === 0) {
        $headerLine = implode(',', $headers) . PHP_EOL;
        file_put_contents($filePath, $headerLine);
    }

    $row = [];
    foreach ($headers as $header) {
        $value = $record[$header] ?? '';
        $row[] = '"' . str_replace('"', '""', (string) $value) . '"';
    }

    file_put_contents($filePath, implode(',', $row) . PHP_EOL, FILE_APPEND);
}
