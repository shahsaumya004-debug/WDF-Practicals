
<?php

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $name = $_POST["name"];
    $email = $_POST["email"];
    $password = $_POST["password"];

    // CSV file
    $file = fopen("users.csv", "a");

    // Save data
    fputcsv($file, [$name, $email, $password]);

    fclose($file);

    echo "<p style='color: green;'>Registration successful!</p>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Registration</title>
</head>

<body>

    <h2>Registration Form</h2>

    <form method="POST" action="">

        <label>Name:</label><br>
        <input type="text" name="name" required>
        <br><br>

        <label>Email:</label><br>
        <input type="email" name="email" required>
        <br><br>

        <label>Password:</label><br>
        <input type="password" name="password" required>
        <br><br>

        <button type="submit">Register</button>

    </form>

</body>
</html>

