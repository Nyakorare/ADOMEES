<?php
session_start();
include './db.php';
include './auth_functions.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if required parameters are provided
if (!isset($_POST['user_id']) || !isset($_POST['role'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$user_id = intval($_POST['user_id']);
$new_role = $_POST['role'];

// Validate role
$valid_roles = ['client', 'sales', 'editor', 'operator'];
if (!in_array($new_role, $valid_roles)) {
    echo json_encode(['success' => false, 'message' => 'Invalid role']);
    exit;
}

// Prevent modifying your own role
if ($user_id === $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'message' => 'You cannot modify your own role']);
    exit;
}

// First check if the user exists
$check_stmt = $conn->prepare("SELECT id, role FROM users WHERE id = ?");
$check_stmt->bind_param("i", $user_id);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}

$user_data = $result->fetch_assoc();
$old_role = $user_data['role'];

// Update the user's role
$update_stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
$update_stmt->bind_param("si", $new_role, $user_id);

if ($update_stmt->execute()) {
    // Log the role change
    $log_stmt = $conn->prepare("INSERT INTO role_changes (user_id, old_role, new_role, changed_by) VALUES (?, ?, ?, ?)");
    $log_stmt->bind_param("issi", $user_id, $old_role, $new_role, $_SESSION['user_id']);
    $log_stmt->execute();
    $log_stmt->close();
    
    echo json_encode(['success' => true, 'message' => 'Role updated successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update role: ' . $update_stmt->error]);
}

$check_stmt->close();
$update_stmt->close();
$conn->close();
?> 