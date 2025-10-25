<?php
// db.php
$DB_HOST = getenv('DB_HOST') ?: 'localhost';
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASS = getenv('DB_PASS') ?: '12345678';
$DB_PORT = getenv('DB_PORT') ?: '3307';
$DB_NAME = getenv('DB_NAME') ?: 'eventdb'; // use the created DB name

$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
?>
