<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "adomees";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create payment_agreements table
$sql = "CREATE TABLE IF NOT EXISTS payment_agreements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    document_id INT NOT NULL,
    sales_agent_id INT NOT NULL,
    client_id INT NOT NULL,
    proposed_amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'accepted', 'rejected') NOT NULL DEFAULT 'pending',
    client_accepted BOOLEAN DEFAULT FALSE,
    sales_accepted BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (document_id) REFERENCES documents(id),
    FOREIGN KEY (sales_agent_id) REFERENCES users(id),
    FOREIGN KEY (client_id) REFERENCES users(id)
)";
$conn->query($sql);

// Create documents table
$sql = "CREATE TABLE IF NOT EXISTS documents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    client_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    file_path VARCHAR(255) NOT NULL,
    status ENUM('pending', 'assigned', 'in_progress', 'completed', 'payment_completed') DEFAULT 'pending',
    payment_amount DECIMAL(10,2),
    payment_type ENUM('full', 'down') DEFAULT 'full',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES users(id)
)";
$conn->query($sql);
?>