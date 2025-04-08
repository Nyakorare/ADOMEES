<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$response = ['success' => false, 'message' => ''];

// Get POST data
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

// Validate username
if (empty($username)) {
    $response['message'] = 'Username cannot be empty';
    echo json_encode($response);
    exit;
}

// Check if username is already taken by another user
$stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
$stmt->bind_param("si", $username, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $response['message'] = 'Username is already taken';
    echo json_encode($response);
    exit;
}

// Update user information
try {
    if (!empty($password)) {
        // Update both username and password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET username = ?, password = ? WHERE id = ?");
        $stmt->bind_param("ssi", $username, $hashed_password, $user_id);
    } else {
        // Update only username
        $stmt = $conn->prepare("UPDATE users SET username = ? WHERE id = ?");
        $stmt->bind_param("si", $username, $user_id);
    }

    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Profile updated successfully';
        $response['username'] = $username;
        
        // Update session username
        $_SESSION['username'] = $username;
    } else {
        $response['message'] = 'Failed to update profile';
    }
} catch (Exception $e) {
    $response['message'] = 'An error occurred while updating your profile';
}

echo json_encode($response); 