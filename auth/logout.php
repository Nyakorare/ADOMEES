<?php
session_start();

// Store success message before clearing session
$success_message = "You have been successfully logged out.";

// Clear all session variables
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destroy the session
session_destroy();

// Start a new session for the success message
session_start();
$_SESSION['success'] = $success_message;

// Redirect to index page
header("Location: ../index.php");
exit();
?>