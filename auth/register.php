<?php
session_start();
include '../php/db.php';
include '../php/auth_functions.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    // Set role to 'client' automatically
    $role = 'client';

    // Validate inputs
    if (empty($username) || empty($email) || empty($password)) {
        $_SESSION['error'] = "All fields are required.";
    } elseif (strlen($password) < 8) {
        $_SESSION['error'] = "Password must be at least 8 characters long.";
    } elseif (isUsernameTaken($username, $conn)) {
        $_SESSION['error'] = "Username is already taken.";
    } elseif (isEmailTaken($email, $conn)) {
        $_SESSION['error'] = "Email is already registered.";
    } else {
        if (registerUser($username, $email, $password, $role, $conn)) {
            $_SESSION['success'] = "Registration successful! Please login.";
            header("Location: ../index.php");
            exit();
        } else {
            $_SESSION['error'] = "Registration failed. Please try again.";
        }
    }
    header("Location: ../index.php");
    exit();
}
?>