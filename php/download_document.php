<?php
session_start();
include './db.php';
include './document_functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    exit('Unauthorized access');
}

// Check if document_id is provided
if (!isset($_GET['document_id'])) {
    header('HTTP/1.1 400 Bad Request');
    exit('Document ID is required');
}

$document_id = intval($_GET['document_id']);

// Get document details and verify permissions
$stmt = $conn->prepare("
    SELECT d.*, w.current_stage, w.editor_id, w.operator_id, da.sales_agent_id 
    FROM documents d
    LEFT JOIN document_workflow w ON d.id = w.document_id
    LEFT JOIN document_assignments da ON d.id = da.document_id
    WHERE d.id = ?
");
$stmt->bind_param("i", $document_id);
$stmt->execute();
$result = $stmt->get_result();
$document = $result->fetch_assoc();

if (!$document) {
    header('HTTP/1.1 404 Not Found');
    exit('Document not found');
}

// Check if user has permission to download
$can_download = false;
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Clients can download finished documents
if ($user_role === 'client' && $document['current_stage'] === 'finished') {
    $can_download = $document['client_id'] === $user_id;
}
// Editors can download documents assigned to them
else if ($user_role === 'editor' && $document['editor_id'] === $user_id) {
    $can_download = true;
}
// Operators can download documents assigned to them
else if ($user_role === 'operator' && $document['operator_id'] === $user_id) {
    $can_download = true;
}

if (!$can_download) {
    header('HTTP/1.1 403 Forbidden');
    exit('You do not have permission to download this document');
}

// Get the file path and ensure it's within the uploads directory
$file_path = '../uploads/documents/' . $document['file_path'];

// Check if file exists
if (!file_exists($file_path)) {
    header('HTTP/1.1 404 Not Found');
    exit('File not found');
}

// Get file info
$file_info = pathinfo($file_path);
$file_name = $file_info['basename'];
$file_extension = strtolower($file_info['extension']);

// Set appropriate headers
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $file_name . '"');
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Output file
readfile($file_path);
exit; 