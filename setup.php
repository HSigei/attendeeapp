<?php
// setup.php

// Load env variables
$DB_HOST = getenv('DB_HOST') ?: 'localhost';
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASS = getenv('DB_PASS') ?: '12345678';
$DB_PORT = getenv('DB_PORT') ?: '3307';

// Ask for DB name if not set
if (!isset($_GET['dbname'])) {
    echo '<form method="get"><label>Enter new DB name: </label>
          <input type="text" name="dbname" required>
          <button type="submit">Create</button></form>';
    exit;
}

$DB_NAME = $_GET['dbname'];

// Connect to MariaDB (no DB yet)
$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, '', $DB_PORT);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Create database
$conn->query("CREATE DATABASE IF NOT EXISTS `$DB_NAME`");
$conn->select_db($DB_NAME);

// USERS table
$conn->query("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','user') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// ATTENDEES table
$conn->query("CREATE TABLE IF NOT EXISTS attendees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    company VARCHAR(150),
    position VARCHAR(150),
    type ENUM('delegate','speaker','exhibitor','usher','staff'),
    registered_by INT,
    registration_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    qr_code VARCHAR(255),
    checked_in_at TIMESTAMP NULL,
    FOREIGN KEY (registered_by) REFERENCES users(id)
)");

// UPLOADS table
$conn->query("CREATE TABLE IF NOT EXISTS uploads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    file_name VARCHAR(255),
    uploaded_by INT,
    upload_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
)");

// Create default admin
$adminPass = password_hash("admin123", PASSWORD_DEFAULT);
$conn->query("INSERT IGNORE INTO users (username, password, role) VALUES ('admin', '$adminPass', 'admin')");

echo "<h3>Database '$DB_NAME' created successfully with required tables!</h3>";
echo "<p>Default admin: <b>admin</b> / <b>admin123</b></p>";
?>
