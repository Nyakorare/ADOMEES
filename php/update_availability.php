<?php
session_start();
include './db.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

// Check if required parameters are provided
if (!isset($_POST['is_available'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$user_id = $_SESSION['user_id'];
$is_available = (int)$_POST['is_available'];

// Update the user's availability
$stmt = $conn->prepare("UPDATE users SET is_available = ? WHERE id = ?");
$stmt->bind_param("ii", $is_available, $user_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Availability updated successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update availability']);
}

$stmt->close();
$conn->close();
?> 