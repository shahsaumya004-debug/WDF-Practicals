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
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    $sql = "INSERT INTO students (full_name, enrollment_number, email, phone, department, semester, password_hash)
            VALUES (:full_name, :enrollment_number, :email, :phone, :department, :semester, :password_hash)";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':full_name' => 'Rahul Sharma',
        ':enrollment_number' => '24012011001',
        ':email' => 'rahul.sharma@apex.edu.in',
        ':phone' => '9876543210',
        ':department' => 'Computer Engineering',
        ':semester' => 'Semester 5',
        ':password_hash' => password_hash('Student@123', PASSWORD_DEFAULT)
    ]);

    echo "Student inserted successfully";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>