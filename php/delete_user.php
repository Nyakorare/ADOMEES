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

// Check if user_id is provided
if (!isset($_POST['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User ID is required']);
    exit;
}

$user_id = intval($_POST['user_id']);

// Prevent deleting your own account
if ($user_id === $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'message' => 'You cannot delete your own account']);
    exit;
}

// First check if the user exists
$check_stmt = $conn->prepare("SELECT id, username FROM users WHERE id = ?");
$check_stmt->bind_param("i", $user_id);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}

$user_data = $result->fetch_assoc();
$username = $user_data['username'];

// Start transaction
$conn->begin_transaction();

try {
    // Delete the user (cascade will handle related records)
    $delete_stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $delete_stmt->bind_param("i", $user_id);
    
    if ($delete_stmt->execute()) {
        // Log the deletion
        $log_stmt = $conn->prepare("INSERT INTO user_deletions (username, deleted_by) VALUES (?, ?)");
        $log_stmt->bind_param("si", $username, $_SESSION['user_id']);
        $log_stmt->execute();
        $log_stmt->close();
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
    } else {
        throw new Exception("Failed to delete user: " . $delete_stmt->error);
    }
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$check_stmt->close();
$delete_stmt->close();
$conn->close();
?> 