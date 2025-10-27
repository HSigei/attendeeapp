<?php
// scan_process.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include 'db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Please login first']);
    exit;
}

if (!isset($_POST['qr_code']) || empty(trim($_POST['qr_code']))) {
    echo json_encode(['status' => 'error', 'message' => 'No QR code data received']);
    exit;
}

$qr_code = trim($_POST['qr_code']);

// Find attendee by QR code
$stmt = $conn->prepare("SELECT * FROM attendees WHERE qr_code = ?");
$stmt->bind_param("s", $qr_code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Attendee not found']);
    exit;
}

$attendee = $result->fetch_assoc();

// Check if already checked in
if (empty($attendee['checkin_time'])) {
    $update = $conn->prepare("UPDATE attendees SET checkin_time = NOW() WHERE id = ?");
    $update->bind_param("i", $attendee['id']);
    $update->execute();
}

// Return success response
echo json_encode([
    'status' => 'success',
    'attendee' => [
        'first_name' => $attendee['first_name'],
        'last_name' => $attendee['last_name'],
        'company' => $attendee['company'],
        'position' => $attendee['position'],
        'type' => $attendee['type'],
        'reg_time' => $attendee['reg_time']
    ]
]);

$stmt->close();
if (isset($update)) $update->close();
$conn->close();
?>