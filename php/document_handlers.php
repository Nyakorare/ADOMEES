<?php
session_start();
include './db.php';
include './document_functions.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$action = $_POST['action'] ?? '';
$response = ['success' => false, 'message' => 'Invalid action'];

try {
switch ($action) {
        case 'get_document_details':
            if (!isset($_POST['document_id'])) {
                throw new Exception('Document ID is required');
            }
            
            $document_id = intval($_POST['document_id']);
            $document = getDocumentDetails($document_id, $conn);
            
            if (!$document) {
                throw new Exception('Document not found');
            }
            
            // Get workflow history
            $history = getWorkflowHistory($document_id, $conn);
            $document['workflow_history'] = [];
            while ($entry = $history->fetch_assoc()) {
                $document['workflow_history'][] = $entry;
            }
            
            // Get payment information
            $payment_stmt = $conn->prepare("
                SELECT p.*
                FROM payments p
                WHERE p.document_id = ?
                ORDER BY p.created_at DESC
            ");
            $payment_stmt->bind_param("i", $document_id);
            $payment_stmt->execute();
            $payments = $payment_stmt->get_result();
            $document['payments'] = [];
            while ($payment = $payments->fetch_assoc()) {
                $document['payments'][] = $payment;
            }
            
            $response = [
                'success' => true,
                'document' => $document
            ];
            break;
            
        case 'get_available_editors':
            if ($_SESSION['role'] !== 'sales') {
                throw new Exception('Only sales agents can view available editors');
            }
            
            $stmt = $conn->prepare("
                SELECT id, username, email 
                FROM users 
                WHERE role = 'editor' 
                AND is_available = 1 
                AND is_active = 1
            ");
            $stmt->execute();
            $result = $stmt->get_result();
            $editors = $result->fetch_all(MYSQLI_ASSOC);
            
            $response = [
                'success' => true,
                'editors' => $editors
            ];
            break;
            
        case 'accept_client':
            if ($_SESSION['role'] !== 'sales') {
                throw new Exception('Only sales agents can accept clients');
            }
            
            if (!isset($_POST['document_id'])) {
                throw new Exception('Document ID is required');
            }
            
            $document_id = intval($_POST['document_id']);
            
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // Get the document details
                $stmt = $conn->prepare("
                    SELECT d.*, w.id as workflow_id
                    FROM documents d 
                    LEFT JOIN document_workflow w ON d.id = w.document_id 
                    WHERE d.id = ?
                ");
                $stmt->bind_param("i", $document_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $document = $result->fetch_assoc();
                
                if (!$document) {
                    throw new Exception('Document not found');
                }

                // Check if document is already assigned
                $check_stmt = $conn->prepare("SELECT 1 FROM document_assignments WHERE document_id = ?");
                $check_stmt->bind_param("i", $document_id);
                $check_stmt->execute();
                if ($check_stmt->get_result()->num_rows > 0) {
                    throw new Exception('Document is already assigned to a sales agent');
                }
                
                // Update document status
                $stmt = $conn->prepare("
                    UPDATE documents 
                    SET status = 'in_progress'
                    WHERE id = ?
                ");
                $stmt->bind_param("i", $document_id);
                $stmt->execute();
                
                // Assign document to sales agent
                $stmt = $conn->prepare("
                    INSERT INTO document_assignments (
                        document_id,
                        sales_agent_id
                    ) VALUES (?, ?)
                ");
                $stmt->bind_param("ii", $document_id, $_SESSION['user_id']);
                $stmt->execute();
                
                // Update workflow
                if ($document['workflow_id']) {
                    $stmt = $conn->prepare("
                        UPDATE document_workflow 
                        SET current_stage = 'sales_review',
                            sales_agent_id = ?,
                            sales_notes = 'Document accepted by sales agent'
                        WHERE document_id = ?
                    ");
                    $stmt->bind_param("ii", $_SESSION['user_id'], $document_id);
                } else {
                $stmt = $conn->prepare("
                    INSERT INTO document_workflow (
                        document_id,
                        current_stage,
                        sales_agent_id,
                        sales_notes
                    ) VALUES (?, 'sales_review', ?, 'Document accepted by sales agent')
                ");
                $stmt->bind_param("ii", $document_id, $_SESSION['user_id']);
                }
                $stmt->execute();
                
                // Add notification
                addDocumentNotification(
                    $document_id,
                    $document['client_id'],
                    'Your document has been accepted by a sales agent',
                    $conn
                );
                
                $conn->commit();
                $response = ['success' => true, 'message' => 'Client accepted successfully'];
            } catch (Exception $e) {
                $conn->rollback();
                throw $e;
            }
            break;
            
        case 'get_available_clients':
            if ($_SESSION['role'] !== 'sales') {
                throw new Exception('Only sales agents can view available clients');
            }
            
            $stmt = $conn->prepare("
                SELECT d.*, u.username as client_name, w.current_stage
                FROM documents d
                JOIN users u ON d.client_id = u.id
                LEFT JOIN document_workflow w ON d.id = w.document_id
                WHERE d.status = 'pending'
                AND NOT EXISTS (
                    SELECT 1 FROM document_assignments da WHERE da.document_id = d.id
                )
            ");
            $stmt->execute();
            $result = $stmt->get_result();
            $clients = $result->fetch_all(MYSQLI_ASSOC);
            
            $response = [
                'success' => true,
                'clients' => $clients
            ];
            break;
            
        case 'upload_document':
            if ($_SESSION['role'] !== 'client') {
                throw new Exception('Only clients can upload documents');
            }
            
            // Validate required fields
            if (!isset($_FILES['document']) || empty($_FILES['document']['tmp_name'])) {
                throw new Exception('No file was uploaded');
            }
            
            if (!isset($_POST['title']) || empty($_POST['title'])) {
                throw new Exception('Document title is required');
            }
            
            if (!isset($_POST['payment_type']) || empty($_POST['payment_type'])) {
                throw new Exception('Payment type is required');
            }
            
            // Validate file size (max 10MB)
            if ($_FILES['document']['size'] > 10 * 1024 * 1024) {
                throw new Exception('File size exceeds maximum limit of 10MB');
            }
            
            // Validate file type
            $allowed_types = ['pdf', 'doc', 'docx', 'txt', 'rtf'];
            $file_extension = strtolower(pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION));
            if (!in_array($file_extension, $allowed_types)) {
                throw new Exception('Invalid file type. Allowed types: ' . implode(', ', $allowed_types));
            }

            $result = uploadDocument(
                $_SESSION['user_id'],
                $_POST['title'],
                $_POST['description'] ?? '',
                $_FILES['document'],
                $conn,
                $_POST['payment_type']
            );

            if ($result['success']) {
                addDocumentNotification(
                    $result['document_id'],
                    $_SESSION['user_id'],
                    'Document uploaded successfully',
                    $conn
                );
            }

            $response = $result;
            break;
        
    case 'assign_to_sales':
        if ($_SESSION['role'] !== 'sales') {
                throw new Exception('Only sales agents can accept documents');
        }
        
        if (!isset($_POST['document_id'])) {
                throw new Exception('Document ID is required');
        }
        
        $result = assignDocumentToSalesAgent($_POST['document_id'], $_SESSION['user_id'], $conn);
        
        if ($result['success']) {
            $document = getDocumentDetails($_POST['document_id'], $conn);
            addDocumentNotification(
                $_POST['document_id'],
                $document['client_id'],
                'Your document has been accepted by a sales agent',
                $conn
            );
        }
        
        $response = $result;
        break;
        
    case 'assign_to_editor':
            if ($_SESSION['role'] !== 'sales') {
            throw new Exception('Only sales agents can assign documents to editors');
        }
        
            if (!isset($_POST['document_id']) || !isset($_POST['editor_id'])) {
            throw new Exception('Document ID and Editor ID are required');
        }
        
        $document_id = intval($_POST['document_id']);
        $editor_id = intval($_POST['editor_id']);

        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Verify the document is assigned to this sales agent
            $check_stmt = $conn->prepare("
                SELECT d.id, d.title, d.description, w.current_stage 
                FROM documents d
                JOIN document_workflow w ON d.id = w.document_id
                JOIN document_assignments da ON d.id = da.document_id
                WHERE d.id = ? 
                AND da.sales_agent_id = ?
                AND w.current_stage = 'sales_review'
            ");
            $check_stmt->bind_param("ii", $document_id, $_SESSION['user_id']);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception('Document not found or not in the correct stage');
            }

            $document = $result->fetch_assoc();

            // Update document workflow
            $update_stmt = $conn->prepare("
                UPDATE document_workflow 
                SET current_stage = 'editor_polishing',
                    editor_id = ?,
                    editor_notes = CONCAT('Document forwarded to editor on ', NOW(), '\nClient Description: ', ?)
                WHERE document_id = ?
            ");
            $update_stmt->bind_param("isi", $editor_id, $document['description'], $document_id);
            
            if (!$update_stmt->execute()) {
                throw new Exception('Failed to update document workflow');
            }

            // Add workflow history
            addWorkflowHistory(
                $document_id,
                'editor_polishing',
                'printing_document',
                $_SESSION['user_id'],
                'Document forwarded to operator by editor',
                $conn
            );

            // Add notification for editor
            addDocumentNotification(
                        $document_id,
                $editor_id,
                "New document assigned to you: " . $document['title'],
                $conn
            );
            
            $conn->commit();
            $response = ['success' => true, 'message' => 'Document assigned to editor successfully'];
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
            }
        break;
        
    case 'assign_to_operator':
            if ($_SESSION['role'] !== 'editor') {
                throw new Exception('Only editors can assign documents to operators');
        }
        
            if (!isset($_POST['document_id']) || !isset($_POST['operator_id'])) {
                throw new Exception('Document ID and Operator ID are required');
        }
        
            $result = assignDocumentToOperator($_POST['document_id'], $_POST['operator_id'], $conn);
        
        if ($result['success']) {
            $document = getDocumentDetails($_POST['document_id'], $conn);
            addDocumentNotification(
                $_POST['document_id'],
                    $document['sales_agent_id'],
                    'Your document has been assigned to a printing operator',
                $conn
            );
        }
        
        $response = $result;
        break;
        
    case 'upload_receipt':
        if ($_SESSION['role'] !== 'operator') {
                throw new Exception('Only operators can upload receipts');
        }
        
        if (!isset($_FILES['receipt']) || !isset($_POST['document_id'])) {
                throw new Exception('Missing required fields');
        }
        
        $result = uploadPrintReceipt(
            $_POST['document_id'],
            $_SESSION['user_id'],
            $_FILES['receipt'],
            $_POST['notes'] ?? '',
            $conn
        );
        
        if ($result['success']) {
            $document = getDocumentDetails($_POST['document_id'], $conn);
            addDocumentNotification(
                $_POST['document_id'],
                $document['sales_agent_id'],
                    'Print receipt has been uploaded. Please review the cost and request payment from the client.',
                $conn
            );
        }
        
        $response = $result;
        break;
        
    case 'add_payment':
        if ($_SESSION['role'] !== 'sales') {
                throw new Exception('Only sales agents can add payments');
        }
        
            if (!isset($_FILES['receipt']) || !isset($_POST['document_id'])) {
                throw new Exception('Missing required fields');
        }
        
        $result = addPayment(
            $_POST['document_id'],
            $_POST['payment_type'],
            $_FILES['receipt'],
            $conn
        );
        
        if ($result['success']) {
            $document = getDocumentDetails($_POST['document_id'], $conn);
            addDocumentNotification(
                $_POST['document_id'],
                $document['client_id'],
                'Payment has been recorded',
                $conn
            );
        }
        
        $response = $result;
        break;
        
    case 'get_notifications':
        $notifications = getUnreadNotifications($_SESSION['user_id'], $conn);
        $response = ['success' => true, 'notifications' => $notifications->fetch_all(MYSQLI_ASSOC)];
        break;
        
    case 'mark_notifications_read':
        if (!isset($_POST['notification_ids'])) {
                throw new Exception('Notification IDs are required');
        }
        
        $result = markNotificationsAsRead($_POST['notification_ids'], $conn);
        $response = ['success' => $result];
        break;
            
        case 'update_payment_agreement':
            if (!isset($_POST['document_id']) || !isset($_POST['status'])) {
                throw new Exception('Missing required fields');
            }
            
            $document_id = intval($_POST['document_id']);
            $status = $_POST['status'];
            $user_role = $_SESSION['role'];
            
            // Verify user has permission to update agreement
            $stmt = $conn->prepare("
                SELECT pa.*, d.client_id, d.sales_agent_id
                FROM payment_agreements pa
                JOIN documents d ON pa.document_id = d.id
                WHERE pa.document_id = ?
            ");
            $stmt->bind_param("i", $document_id);
            $stmt->execute();
            $agreement = $stmt->get_result()->fetch_assoc();
            
            if (!$agreement) {
                throw new Exception('Payment agreement not found');
            }
            
            if ($user_role === 'client' && $agreement['client_id'] !== $_SESSION['user_id']) {
                throw new Exception('Unauthorized');
            }
            
            if ($user_role === 'sales' && $agreement['sales_agent_id'] !== $_SESSION['user_id']) {
                throw new Exception('Unauthorized');
            }
            
            // Update agreement status
            $stmt = $conn->prepare("
                UPDATE payment_agreements 
                SET status = ?,
                    " . ($user_role === 'client' ? 'client_accepted' : 'sales_accepted') . " = 1
                WHERE document_id = ?
            ");
            $stmt->bind_param("si", $status, $document_id);
            $stmt->execute();
            
            // If both parties have accepted, update document status
            if ($agreement['client_accepted'] && $agreement['sales_accepted']) {
                $stmt = $conn->prepare("
                    UPDATE documents 
                    SET status = 'payment_agreed'
                    WHERE id = ?
                ");
                $stmt->bind_param("i", $document_id);
                $stmt->execute();
                
                // Add notification
                addDocumentNotification(
                    $document_id,
                    $agreement['sales_agent_id'],
                    'Payment agreement has been accepted by both parties',
                    $conn
                );
            }
            
            $response = ['success' => true, 'message' => 'Payment agreement updated'];
            break;
            
        case 'get_payment_agreement':
            if (!isset($_POST['document_id'])) {
                throw new Exception('Document ID is required');
            }
            
            $stmt = $conn->prepare("
                SELECT pa.*, u.username as sales_agent_name, c.username as client_name
                FROM payment_agreements pa
                JOIN users u ON pa.sales_agent_id = u.id
                JOIN users c ON pa.client_id = c.id
                WHERE pa.document_id = ?
            ");
            $stmt->bind_param("i", $_POST['document_id']);
            $stmt->execute();
            $agreement = $stmt->get_result()->fetch_assoc();
            
            if (!$agreement) {
                throw new Exception('Payment agreement not found');
            }
            
            $response = [
                'success' => true,
                'agreement' => $agreement
            ];
            break;
            
        case 'make_payment':
            if ($_SESSION['role'] !== 'client') {
                throw new Exception('Only clients can make payments');
            }
            
            if (!isset($_POST['document_id']) || !isset($_FILES['receipt'])) {
                throw new Exception('Missing required fields');
            }
            
            // Verify document belongs to client and is accepted by sales agent
            $stmt = $conn->prepare("
                SELECT d.*, da.sales_agent_id 
                FROM documents d
                LEFT JOIN document_assignments da ON d.id = da.document_id
                WHERE d.id = ? AND d.client_id = ? AND da.sales_agent_id IS NOT NULL
            ");
            $stmt->bind_param("ii", $_POST['document_id'], $_SESSION['user_id']);
            $stmt->execute();
            $document = $stmt->get_result()->fetch_assoc();
            
            if (!$document) {
                throw new Exception('Document not found or not accepted by sales agent');
            }
            
            // Add payment record
            $stmt = $conn->prepare("
                INSERT INTO payments (
                    document_id,
                    amount,
                    payment_type,
                    receipt_file,
                    status
                ) VALUES (?, ?, ?, ?, 'completed')
            ");
            
            $receipt_path = uploadFile($_FILES['receipt'], 'receipts');
            $stmt->bind_param("idss", 
                $_POST['document_id'],
                $document['payment_amount'],
                $document['payment_type'],
                $receipt_path
            );
            $stmt->execute();
            
            // Update document status
            $stmt = $conn->prepare("
                UPDATE documents 
                SET status = 'payment_completed'
                WHERE id = ?
            ");
            $stmt->bind_param("i", $_POST['document_id']);
            $stmt->execute();
            
            // Add notification
            addDocumentNotification(
                $_POST['document_id'],
                $document['sales_agent_id'],
                'Payment has been made for the document',
                $conn
            );
            
            $response = ['success' => true, 'message' => 'Payment completed successfully'];
                break;
                
        case 'request_payment':
            if ($_SESSION['role'] !== 'sales') {
                throw new Exception('Only sales agents can request payments');
            }
            
            if (!isset($_POST['document_id'])) {
                throw new Exception('Document ID is required');
            }
            
            $document_id = intval($_POST['document_id']);
            
            // Verify the document is assigned to this sales agent
            $check_stmt = $conn->prepare("
                SELECT d.id, d.title, d.description, w.current_stage 
                FROM documents d
                JOIN document_workflow w ON d.id = w.document_id
                JOIN document_assignments da ON d.id = da.document_id
                WHERE d.id = ? 
                AND da.sales_agent_id = ?
                AND w.current_stage = 'sales_review'
            ");
            $check_stmt->bind_param("ii", $document_id, $_SESSION['user_id']);
            $check_stmt->execute();
            
            if ($check_stmt->get_result()->num_rows === 0) {
                throw new Exception('Document not found or not in the correct stage');
            }
            
            // Update document workflow
            $update_stmt = $conn->prepare("
                UPDATE document_workflow 
                SET current_stage = 'payment_pending',
                    payment_requested = TRUE,
                    sales_notes = CONCAT('Payment requested on ', NOW())
                WHERE document_id = ?
            ");
            $update_stmt->bind_param("i", $document_id);
            
            if (!$update_stmt->execute()) {
                throw new Exception('Failed to update document workflow');
            }
            
            // Add workflow history
            addWorkflowHistory(
                $document_id,
                'sales_review',
                'payment_pending',
                $_SESSION['user_id'],
                "Payment requested by sales agent",
                $conn
            );
            
            $response = ['success' => true];
            break;

        case 'accept_payment':
            if ($_SESSION['role'] !== 'client') {
                throw new Exception('Only clients can accept payment requests');
            }
            
            if (!isset($_POST['document_id'])) {
                throw new Exception('Document ID is required');
            }
            
            // Update payment agreement status
            $stmt = $conn->prepare("
                UPDATE payment_agreements 
                SET status = 'accepted',
                    client_accepted = 1
                WHERE document_id = ?
            ");
            $stmt->bind_param("i", $_POST['document_id']);
            
            if ($stmt->execute()) {
                $document = getDocumentDetails($_POST['document_id'], $conn);
                addDocumentNotification(
                    $_POST['document_id'],
                    $document['sales_agent_id'],
                    'Client has accepted the payment request. You can now mark the document as finished.',
                    $conn
                );
                $response = ['success' => true];
            } else {
                $response = ['success' => false, 'message' => 'Failed to accept payment'];
            }
            break;

        case 'mark_as_finished':
            if ($_SESSION['role'] !== 'sales') {
                throw new Exception('Only sales agents can mark documents as finished');
            }
            
            if (!isset($_POST['document_id'])) {
                throw new Exception('Document ID is required');
            }
            
            $result = markDocumentAsFinished($_POST['document_id'], $conn);
            
            if ($result['success']) {
                $document = getDocumentDetails($_POST['document_id'], $conn);
                addDocumentNotification(
                    $_POST['document_id'],
                    $document['client_id'],
                    'Your document has been marked as finished and is ready for delivery.',
                    $conn
                );
            }
            
            $response = $result;
            break;
            
        case 'update_availability':
            // Check if user is editor or operator
            if ($_SESSION['role'] !== 'editor' && $_SESSION['role'] !== 'operator') {
                $response = ['success' => false, 'message' => 'Unauthorized access'];
                break;
            }

            // Validate input
            if (!isset($_POST['is_available']) || !in_array($_POST['is_available'], ['0', '1'])) {
                $response = ['success' => false, 'message' => 'Invalid availability value'];
                break;
            }

            $is_available = (int)$_POST['is_available'];
            $user_id = $_SESSION['user_id'];

            try {
                // Update the user's availability
                $stmt = $conn->prepare("UPDATE users SET is_available = ? WHERE id = ?");
                $stmt->bind_param("ii", $is_available, $user_id);

                if ($stmt->execute()) {
                    // Try to log the activity, but don't fail if the table doesn't exist yet
                    try {
                        $action = $is_available ? 'set as available' : 'set as unavailable';
                        $stmt = $conn->prepare("INSERT INTO user_activity_log (user_id, action, details) VALUES (?, 'availability_update', ?)");
                        $stmt->bind_param("is", $user_id, $action);
                        $stmt->execute();
                    } catch (Exception $e) {
                        // Silently continue if logging fails
                    }

                    $response = [
                        'success' => true, 
                        'message' => 'Availability updated successfully',
                        'is_available' => $is_available
                    ];
                } else {
                    throw new Exception('Failed to update availability: ' . $conn->error);
                }
            } catch (Exception $e) {
                $response = [
                    'success' => false, 
                    'message' => $e->getMessage()
                ];
            }
            break;
            
        case 'forward_to_operator':
            if (!isset($_POST['document_id']) || !isset($_FILES['edited_document'])) {
                $response = ['success' => false, 'message' => 'Missing required fields'];
                break;
            }

            $document_id = $_POST['document_id'];
            $editor_notes = $_POST['editor_notes'] ?? '';
            $edited_document = $_FILES['edited_document'];

            // Get document details with user information
            $stmt = $conn->prepare("
                SELECT d.*, w.current_stage, w.editor_id, 
                       e.username as editor_name, o.username as operator_name
                FROM documents d 
                JOIN document_workflow w ON d.id = w.document_id 
                JOIN users e ON w.editor_id = e.id
                LEFT JOIN users o ON w.operator_id = o.id
                WHERE d.id = ? AND w.editor_id = ?
            ");
            $stmt->bind_param("ii", $document_id, $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $document = $result->fetch_assoc();

            if (!$document) {
                $response = ['success' => false, 'message' => 'Document not found or not assigned to you'];
                break;
            }

            if ($document['current_stage'] !== 'editor_polishing') {
                $response = ['success' => false, 'message' => 'Document is not in the correct stage for forwarding'];
                break;
            }

            // Get available operators with their current task count
            $stmt = $conn->prepare("
                SELECT u.*, 
                    (SELECT COUNT(*) FROM document_workflow w WHERE w.operator_id = u.id AND w.current_stage = 'printing_document') as current_tasks
                FROM users u 
                WHERE u.role = 'operator' AND u.is_available = 1
                HAVING current_tasks < 3
                ORDER BY current_tasks ASC
                LIMIT 1
            ");
            $stmt->execute();
            $result = $stmt->get_result();
            $operator = $result->fetch_assoc();

            if (!$operator) {
                $response = ['success' => false, 'message' => 'No available operators at the moment'];
                break;
            }

            // Start transaction
            $conn->begin_transaction();

            try {
                // Upload the edited document
                $upload_dir = '../uploads/edited_documents/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                $file_extension = pathinfo($edited_document['name'], PATHINFO_EXTENSION);
                $new_filename = 'edited_' . $document_id . '_' . time() . '.' . $file_extension;
                $file_path = $upload_dir . $new_filename;

                if (!move_uploaded_file($edited_document['tmp_name'], $file_path)) {
                    throw new Exception('Failed to upload edited document');
                }

                // Update document file path
                $stmt = $conn->prepare("UPDATE documents SET file_path = ? WHERE id = ?");
                $stmt->bind_param("si", $file_path, $document_id);
                $stmt->execute();

                // Update workflow with detailed notes
                $stmt = $conn->prepare("
                    UPDATE document_workflow 
                    SET current_stage = 'printing_document',
                        operator_id = ?,
                        editor_notes = CONCAT('Document forwarded to operator on ', NOW(), 
                                            '\nEditor Notes: ', ?,
                                            '\nForwarded by: ', ?,
                                            '\nPrevious Editor: ', ?)
                    WHERE document_id = ?
                ");
                $stmt->bind_param("isssi", $operator['id'], $editor_notes, $document['editor_name'], $document['editor_name'], $document_id);
                $stmt->execute();

                // Add workflow history with detailed information
                addWorkflowHistory(
                    $document_id,
                    'editor_polishing',
                    'printing_document',
                    $_SESSION['user_id'],
                    "Document forwarded to operator {$operator['username']} by editor {$document['editor_name']}. Editor Notes: {$editor_notes}",
                    $conn
                );

                $conn->commit();
                $response = ['success' => true, 'message' => 'Document forwarded successfully'];
            } catch (Exception $e) {
                $conn->rollback();
                $response = ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
            }
            break;
            
        case 'delete_document':
            if ($_SESSION['role'] !== 'client') {
                throw new Exception('Only clients can delete their documents');
            }

            if (!isset($_POST['document_id'])) {
                throw new Exception('Document ID is required');
            }

            $document_id = intval($_POST['document_id']);

            // Verify the document belongs to this client and is in pending state
            $check_stmt = $conn->prepare("
                SELECT d.file_path, w.current_stage 
                FROM documents d
                JOIN document_workflow w ON d.id = w.document_id
                WHERE d.id = ? 
                AND d.client_id = ?
                AND w.current_stage = 'pending'
            ");
            $check_stmt->bind_param("ii", $document_id, $_SESSION['user_id']);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception('Document not found or cannot be deleted');
            }

            $document = $result->fetch_assoc();
            
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // Delete the file
                $file_path = '../uploads/documents/' . $document['file_path'];
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
                
                // Delete workflow history
                $stmt = $conn->prepare("DELETE FROM workflow_history WHERE document_id = ?");
                $stmt->bind_param("i", $document_id);
                $stmt->execute();
                
                // Delete workflow
                $stmt = $conn->prepare("DELETE FROM document_workflow WHERE document_id = ?");
                $stmt->bind_param("i", $document_id);
                $stmt->execute();
                
                // Delete document
                $stmt = $conn->prepare("DELETE FROM documents WHERE id = ?");
                $stmt->bind_param("i", $document_id);
                $stmt->execute();
                
                $conn->commit();
                $response = ['success' => true];
            } catch (Exception $e) {
                $conn->rollback();
                throw new Exception('Failed to delete document: ' . $e->getMessage());
            }
            break;
            
        case 'get_available_operators':
            if ($_SESSION['role'] !== 'editor') {
                throw new Exception('Only editors can view available operators');
            }
            
            $stmt = $conn->prepare("
                SELECT id, username, email 
                FROM users 
                WHERE role = 'operator' 
                AND is_available = 1 
                AND is_active = 1
            ");
            $stmt->execute();
            $result = $stmt->get_result();
            $operators = $result->fetch_all(MYSQLI_ASSOC);
            
            $response = [
                'success' => true,
                'operators' => $operators
            ];
            break;
            
        case 'finish_printing':
            if (!isset($_POST['document_id']) || !isset($_POST['printing_cost'])) {
                $response = ['success' => false, 'message' => 'Missing required parameters'];
                break;
            }

            $document_id = intval($_POST['document_id']);
            $printing_cost = floatval($_POST['printing_cost']);
            $printing_notes = $_POST['printing_notes'] ?? '';

            $result = finishPrinting($document_id, $_SESSION['user_id'], $printing_cost, $printing_notes, $conn);
            $response = $result;
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    $response = [
        'success' => false,
        'message' => $e->getMessage()
    ];
}

// Ensure proper JSON output
echo json_encode($response);
exit;
?> 