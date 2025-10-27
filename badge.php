<?php
// badge.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

if (!isset($_GET['id'])) {
    die("❌ No attendee selected.");
}

$attendee_id = intval($_GET['id']);
$result = $conn->query("SELECT a.*, u.username AS registered_by_user 
                        FROM attendees a 
                        LEFT JOIN users u ON a.registered_by = u.id 
                        WHERE a.id = $attendee_id");

if ($result->num_rows === 0) {
    die("❌ Attendee not found.");
}

$attendee = $result->fetch_assoc();

// Generate QR code using Google Chart API
$qr_data = urlencode($attendee['qr_code']);
$qr_url = "https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=$qr_data&choe=UTF-8";
?>
<!DOCTYPE html>
<html>
<head>
    <title>Print Badge</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f8f9fc;
            padding: 40px;
            text-align: center;
        }
        .badge {
            width: 350px;
            background: white;
            margin: auto;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
            position: relative;
        }
        .badge h2 {
            margin-bottom: 5px;
            color: #222;
        }
        .badge p {
            margin: 3px 0;
            color: #555;
        }
        .badge-type {
            background: #007bff;
            color: white;
            padding: 5px 10px;
            border-radius: 10px;
            display: inline-block;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        .badge img {
            margin-top: 10px;
        }
        button {
            margin-top: 20px;
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 6px;
            cursor: pointer;
        }
        button:hover { background: #0056b3; }
        @media print {
            button { display: none; }
            body { background: white; }
            .badge { box-shadow: none; border: 1px solid #ccc; }
        }
    </style>
</head>
<body>
    <div class="badge">
        <h2><?= htmlspecialchars($attendee['first_name'] . ' ' . $attendee['last_name']) ?></h2>
        <p><b>Company:</b> <?= htmlspecialchars($attendee['company']) ?></p>
        <p><b>Position:</b> <?= htmlspecialchars($attendee['position']) ?></p>
        <p class="badge-type"><?= htmlspecialchars($attendee['type']) ?></p>
        <img src="<?= $qr_url ?>" alt="QR Code"><br>
        <small>Registered by: <?= htmlspecialchars($attendee['registered_by_user']) ?></small><br>
        <small>Time: <?= htmlspecialchars($attendee['reg_time']) ?></small>
    </div>

    <button onclick="window.print()">🖨️ Print Badge</button>
</body>
</html>
