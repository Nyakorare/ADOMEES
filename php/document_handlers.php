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
            $stmt = $conn->prepare("
                SELECT d.*, u.username as client_name, w.current_stage
                FROM documents d
                JOIN users u ON d.client_id = u.id
                LEFT JOIN document_workflow w ON d.id = w.document_id
                WHERE d.id = ?
            ");
            $stmt->bind_param("i", $document_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception('Document not found');
            }
            
            $document = $result->fetch_assoc();
            $response = [
                'success' => true,
                'document' => $document
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
            
            if (!isset($_FILES['document']) || !isset($_POST['title']) || !isset($_POST['payment_type'])) {
                throw new Exception('Missing required fields');
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
                echo json_encode(['success' => false, 'message' => 'Only sales agents can assign documents to editors']);
                exit;
            }

            if (!isset($_POST['document_id']) || !isset($_POST['editor_id'])) {
                echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
                exit;
            }

            $document_id = $_POST['document_id'];
            $editor_id = $_POST['editor_id'];

            // Check if editor is available
            $stmt = $conn->prepare("SELECT is_available FROM users WHERE id = ? AND role = 'editor'");
            $stmt->bind_param("i", $editor_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $editor = $result->fetch_assoc();

            if (!$editor || !$editor['is_available']) {
                echo json_encode(['success' => false, 'message' => 'Selected editor is not available']);
                exit;
            }

            // Update document workflow
            $stmt = $conn->prepare("UPDATE document_workflow SET current_stage = 'editor_polishing', editor_id = ?, updated_at = NOW() WHERE document_id = ?");
            $stmt->bind_param("ii", $editor_id, $document_id);
            
            if ($stmt->execute()) {
                // Notify client
                $stmt = $conn->prepare("SELECT d.title, c.user_id as client_id FROM documents d JOIN users c ON d.client_id = c.id WHERE d.id = ?");
                $stmt->bind_param("i", $document_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $document = $result->fetch_assoc();
                
                if ($document) {
                    createNotification(
                        $document['client_id'],
                        'Your document "' . $document['title'] . '" has been assigned to an editor for polishing.',
                        'document_assigned',
                        $document_id,
                        $conn
                    );
                }
                
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to assign document to editor']);
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
                throw new Exception('Only sales agents can request payment');
            }
            
            if (!isset($_POST['document_id'])) {
                throw new Exception('Document ID is required');
            }
            
            // Update payment agreement status
            $stmt = $conn->prepare("
                UPDATE payment_agreements 
                SET status = 'pending',
                    sales_accepted = 1
                WHERE document_id = ?
            ");
            $stmt->bind_param("i", $_POST['document_id']);
            
            if ($stmt->execute()) {
                $document = getDocumentDetails($_POST['document_id'], $conn);
                addDocumentNotification(
                    $_POST['document_id'],
                    $document['client_id'],
                    'Payment has been requested for your document. Please review and accept.',
                    $conn
                );
                $response = ['success' => true];
            } else {
                $response = ['success' => false, 'message' => 'Failed to request payment'];
            }
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
                echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
                exit;
            }

            // Get availability status
            $is_available = isset($_POST['is_available']) ? (bool)$_POST['is_available'] : false;

            // Update user's availability
            $stmt = $conn->prepare("UPDATE users SET is_available = ? WHERE id = ? AND (role = 'editor' OR role = 'operator')");
            $stmt->bind_param("ii", $is_available, $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update availability']);
            }
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