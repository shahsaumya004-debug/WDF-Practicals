<?php
$host = "localhost";
$dbname = "studenthub";
$dbuser = "root";
$dbpass = "";

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $dbuser,
        $dbpass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]
    );

    $sql = "INSERT INTO registrations (student_id, event_id, status)
            VALUES (:student_id, :event_id, :status)";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':student_id' => 1,
        ':event_id' => 1,
        ':status' => 'registered'
    ]);

    echo "Registration saved successfully";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>