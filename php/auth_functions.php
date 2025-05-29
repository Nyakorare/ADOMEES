<?php
function isUsernameTaken($username, $conn) {
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}

function isEmailTaken($email, $conn) {
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}

function registerUser($username, $email, $password, $role, $conn) {
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $username, $email, $hashed_password, $role);
    
    if ($stmt->execute()) {
        return true;
    }
    
    return false;
}

function loginUser($username, $password, $conn) {
    $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            return $user;
        }
    }
    
    return false;
}

function getUserById($user_id, $conn) {
    $stmt = $conn->prepare("SELECT id, username, email, role, is_available FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return false;
}

function getAvailableClients($conn) {
    $stmt = $conn->prepare("
        SELECT c.id, c.status, c.inquiry_description, u.username, u.email 
        FROM clients c 
        JOIN users u ON c.user_id = u.id 
        WHERE c.status = 'available'
    ");
    $stmt->execute();
    return $stmt->get_result();
}

function getClientsBySalesAgent($sales_agent_id, $conn) {
    $stmt = $conn->prepare("
        SELECT c.id, c.status, u.username, u.email 
        FROM clients c 
        JOIN users u ON c.user_id = u.id 
        WHERE c.sales_agent_id = ?
    ");
    $stmt->bind_param("i", $sales_agent_id);
    $stmt->execute();
    return $stmt->get_result();
}

function getFilesByClient($client_id, $conn) {
    $stmt = $conn->prepare("
        SELECT f.*, u.username as sales_agent_name, e.username as editor_name, o.username as operator_name
        FROM files f
        LEFT JOIN users u ON f.sales_agent_id = u.id
        LEFT JOIN users e ON f.editor_id = e.id
        LEFT JOIN users o ON f.operator_id = o.id
        WHERE f.client_id = ?
    ");
    $stmt->bind_param("i", $client_id);
    $stmt->execute();
    return $stmt->get_result();
}

function getFilesByEditor($editor_id, $conn) {
    $stmt = $conn->prepare("
        SELECT f.*, c.id as client_id, u.username as client_name, s.username as sales_agent_name
        FROM files f
        JOIN clients c ON f.client_id = c.id
        JOIN users u ON c.user_id = u.id
        JOIN users s ON f.sales_agent_id = s.id
        WHERE f.editor_id = ?
    ");
    $stmt->bind_param("i", $editor_id);
    $stmt->execute();
    return $stmt->get_result();
}

function getFilesByOperator($operator_id, $conn) {
    $stmt = $conn->prepare("
        SELECT f.*, c.id as client_id, u.username as client_name, e.username as editor_name
        FROM files f
        JOIN clients c ON f.client_id = c.id
        JOIN users u ON c.user_id = u.id
        JOIN users e ON f.editor_id = e.id
        WHERE f.operator_id = ?
    ");
    $stmt->bind_param("i", $operator_id);
    $stmt->execute();
    return $stmt->get_result();
}

function getAllUsers($conn) {
    // Force lowercase comparison to catch 'Admin', 'ADMIN', etc.
    $query = "SELECT id, username, email, role, created_at FROM users WHERE LOWER(role) != 'admin'";
    
    // Optional: Also exclude the current admin if you're logged in as one (extra safety)
    if (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'admin') {
        $query .= " AND id != " . (int)$_SESSION['user_id'];
    }
    
    $query .= ")"; // Close the WHERE clause
    $result = $conn->query($query);
    return $result;
}

function assignClientToSalesAgent($client_id, $sales_agent_id, $conn) {
    $stmt = $conn->prepare("UPDATE clients SET sales_agent_id = ?, status = 'taken' WHERE id = ?");
    $stmt->bind_param("ii", $sales_agent_id, $client_id);
    return $stmt->execute();
}

function assignFileToEditor($file_id, $editor_id, $conn) {
    $stmt = $conn->prepare("UPDATE files SET editor_id = ? WHERE id = ?");
    $stmt->bind_param("ii", $editor_id, $file_id);
    return $stmt->execute();
}

function assignFileToOperator($file_id, $operator_id, $conn) {
    $stmt = $conn->prepare("UPDATE files SET operator_id = ? WHERE id = ?");
    $stmt->bind_param("ii", $operator_id, $file_id);
    return $stmt->execute();
}

function updateFileStatus($file_id, $status, $conn) {
    $stmt = $conn->prepare("UPDATE files SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $file_id);
    return $stmt->execute();
}

function updateClientStatus($client_id, $status, $conn) {
    $stmt = $conn->prepare("UPDATE clients SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $client_id);
    return $stmt->execute();
}

function deleteUser($user_id, $conn) {
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    
    if ($stmt->execute()) {
        return true;
    }
    
    return false;
}
?>