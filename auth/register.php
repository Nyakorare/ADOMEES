<?php
session_start();
include '../php/db.php';
include '../php/auth_functions.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    
    // Validate input
    $errors = [];
    
    // Check if username is empty
    if (empty($username)) {
        $errors[] = "Username is required.";
    }
    
    // Check if email is empty or invalid
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    
    // Password strength validation
    if (empty($password)) {
        $errors[] = "Password is required.";
    } else {
        // Check password length
        if (strlen($password) < 8) {
            $errors[] = "Password must be at least 8 characters long.";
        }
        
        // Check for uppercase letter
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least one uppercase letter.";
        }
        
        // Check for lowercase letter
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least one lowercase letter.";
        }
        
        // Check for number
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one number.";
        }
        
        // Check for special character
        if (!preg_match('/[!@#$%^&*()\-_=+{};:,<.>]/', $password)) {
            $errors[] = "Password must contain at least one special character (!@#$%^&*()-_=+{};:,<.>).";
        }
    }
    
    // Check if username is already taken
    if (isUsernameTaken($username, $conn)) {
        $errors[] = "Username is already taken. Please choose another one.";
    }
    
    // Check if email is already taken
    if (isEmailTaken($email, $conn)) {
        $errors[] = "Email is already registered. Please use another email.";
    }
    
    // If there are no errors, register the user
    if (empty($errors)) {
        // Set default role as 'client' for new registrations
        $role = 'client';
        
        if (registerUser($username, $email, $password, $role, $conn)) {
            $_SESSION['success'] = "Registration successful! You can now login with your credentials.";
            header("Location: ../index.php");
            exit();
        } else {
            $_SESSION['error'] = "Registration failed. Please try again later.";
            header("Location: ../index.php");
            exit();
        }
    } else {
        // If there are errors, store them in session and redirect back
        $_SESSION['error'] = implode("<br>", $errors);
        header("Location: ../index.php");
        exit();
    }
}
?>