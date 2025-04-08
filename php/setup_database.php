<?php
// Database connection parameters
$servername = "localhost";
$username = "root";
$password = "";

// Create connection without database
$conn = new mysqli($servername, $username, $password);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Drop database if exists
$sql = "DROP DATABASE IF EXISTS adomees";
if ($conn->query($sql) === TRUE) {
    echo "Database dropped successfully<br>";
} else {
    echo "Error dropping database: " . $conn->error . "<br>";
}

// Create database
$sql = "CREATE DATABASE adomees";
if ($conn->query($sql) === TRUE) {
    echo "Database created successfully<br>";
} else {
    echo "Error creating database: " . $conn->error . "<br>";
}

// Select the database
$conn->select_db("adomees");

// Create users table
$sql = "CREATE TABLE users (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'client', 'sales', 'editor', 'operator') NOT NULL DEFAULT 'client',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
    echo "Users table created successfully<br>";
} else {
    echo "Error creating users table: " . $conn->error . "<br>";
}

// Create clients table
$sql = "CREATE TABLE clients (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    status ENUM('available', 'taken', 'completed') NOT NULL DEFAULT 'available',
    inquiry_description TEXT,
    sales_agent_id INT(11) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (sales_agent_id) REFERENCES users(id) ON DELETE SET NULL
)";

if ($conn->query($sql) === TRUE) {
    echo "Clients table created successfully<br>";
} else {
    echo "Error creating clients table: " . $conn->error . "<br>";
}

// Create files table
$sql = "CREATE TABLE files (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    client_id INT(11) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    status ENUM('pending', 'processing', 'completed', 'rejected') NOT NULL DEFAULT 'pending',
    sales_agent_id INT(11) DEFAULT NULL,
    editor_id INT(11) DEFAULT NULL,
    operator_id INT(11) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (sales_agent_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (editor_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (operator_id) REFERENCES users(id) ON DELETE SET NULL
)";

if ($conn->query($sql) === TRUE) {
    echo "Files table created successfully<br>";
} else {
    echo "Error creating files table: " . $conn->error . "<br>";
}

// Create role_changes table for logging role changes
$sql = "CREATE TABLE role_changes (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    old_role VARCHAR(20) NOT NULL,
    new_role VARCHAR(20) NOT NULL,
    changed_by INT(11) NOT NULL,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE CASCADE
)";

if ($conn->query($sql) === TRUE) {
    echo "Role changes table created successfully<br>";
} else {
    echo "Error creating role changes table: " . $conn->error . "<br>";
}

// Create user_deletions table for logging user deletions
$sql = "CREATE TABLE user_deletions (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    deleted_by INT(11) NOT NULL,
    deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE CASCADE
)";

if ($conn->query($sql) === TRUE) {
    echo "User deletions table created successfully<br>";
} else {
    echo "Error creating user deletions table: " . $conn->error . "<br>";
}

// Create admin user
$admin_username = "admin";
$admin_email = "admin@adomees.com";
$admin_password = password_hash("admin", PASSWORD_DEFAULT);
$admin_role = "admin";

$stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssss", $admin_username, $admin_email, $admin_password, $admin_role);

if ($stmt->execute()) {
    echo "Admin user created successfully<br>";
} else {
    echo "Error creating admin user: " . $stmt->error . "<br>";
}

// Create sample users for each role
$roles = ['client', 'sales', 'editor', 'operator'];
foreach ($roles as $role) {
    $username = $role;
    $email = $role . "@adomees.com";
    $password = password_hash($role, PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $username, $email, $password, $role);
    
    if ($stmt->execute()) {
        echo "Created $role user successfully<br>";
    } else {
        echo "Error creating $role user: " . $stmt->error . "<br>";
    }
}

// Create sample client
$client_id = 4; // Assuming the client user has ID 4
$inquiry = "Sample inquiry for testing";

$stmt = $conn->prepare("INSERT INTO clients (user_id, inquiry_description) VALUES (?, ?)");
$stmt->bind_param("is", $client_id, $inquiry);

if ($stmt->execute()) {
    echo "Sample client created successfully<br>";
} else {
    echo "Error creating sample client: " . $stmt->error . "<br>";
}

echo "Database setup completed successfully!<br>";
echo "You can now log in with the following credentials:<br>";
echo "Admin: username=admin, password=admin<br>";
echo "Other roles: username=role_name, password=role_name (e.g., client/client, sales/sales, etc.)<br>";

$conn->close();

// Create setup_completed.txt file
$setup_file = '../setup_completed.txt';
file_put_contents($setup_file, date('Y-m-d H:i:s'));

// Redirect to login page after 5 seconds
echo "<script>
    setTimeout(function() {
        window.location.href = '../index.php';
    }, 5000);
</script>";
echo "<p>Redirecting to login page in 5 seconds...</p>";
?> 