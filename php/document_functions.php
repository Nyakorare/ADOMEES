<?php
// Function to upload a document
function uploadDocument($client_id, $title, $description, $file, $conn, $payment_type) {
    // Use absolute path for upload directory
    $upload_dir = dirname(__DIR__) . '/uploads/documents/';
    
    // Create directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        if (!mkdir($upload_dir, 0777, true)) {
            return ['success' => false, 'message' => 'Failed to create upload directory'];
        }
    }

    // Validate file
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['success' => false, 'message' => 'Invalid file upload'];
    }

    // Generate unique filename
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $file_name = time() . '_' . uniqid() . '.' . $file_extension;
    $target_path = $upload_dir . $file_name;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $target_path)) {
        return ['success' => false, 'message' => 'Failed to move uploaded file'];
    }

    $conn->begin_transaction();
    
    try {
        // Insert document record
        $stmt = $conn->prepare("
            INSERT INTO documents (
                client_id, 
                title, 
                description, 
                file_path,
                status,
                payment_type
            ) VALUES (?, ?, ?, ?, 'pending', ?)
        ");
        $stmt->bind_param("issss", 
            $client_id, 
            $title, 
            $description, 
            $file_name,
            $payment_type
        );
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to insert document record');
        }
        
        $document_id = $conn->insert_id;
        
        // Create initial workflow entry
        $workflow_stmt = $conn->prepare("
            INSERT INTO document_workflow (
                document_id,
                current_stage,
                payment_requested,
                payment_status
            ) VALUES (?, 'pending', FALSE, 'pending')
        ");
        $workflow_stmt->bind_param("i", $document_id);
        
        if (!$workflow_stmt->execute()) {
            throw new Exception('Failed to create workflow entry');
        }
        
        // Create initial payment agreement
        $agreement_stmt = $conn->prepare("
            INSERT INTO payment_agreements (
                document_id,
                status,
                client_accepted,
                sales_accepted
            ) VALUES (?, 'pending', 1, 0)
        ");
        $agreement_stmt->bind_param("i", $document_id);
        
        if (!$agreement_stmt->execute()) {
            throw new Exception('Failed to create payment agreement');
        }
        
        $conn->commit();
        return ['success' => true, 'document_id' => $document_id];
        
    } catch (Exception $e) {
        $conn->rollback();
        // Clean up uploaded file if database transaction fails
        if (file_exists($target_path)) {
            unlink($target_path);
        }
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Function to get available documents for sales agents
function getAvailableDocuments($conn) {
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
    return $stmt->get_result();
}

// Function to get documents assigned to a sales agent
function getSalesAgentDocuments($sales_agent_id, $conn) {
    $stmt = $conn->prepare("
        SELECT 
            d.id,
            d.title,
            d.description,
            d.file_path,
            d.created_at,
            c.username as client_name,
            w.current_stage,
            w.editor_id,
            w.operator_id,
            w.payment_requested,
            w.payment_status,
            w.updated_at as workflow_updated_at,
            e.username as editor_name,
            o.username as operator_name
        FROM documents d
        JOIN users c ON d.client_id = c.id
        LEFT JOIN document_workflow w ON d.id = w.document_id
        LEFT JOIN users e ON w.editor_id = e.id
        LEFT JOIN users o ON w.operator_id = o.id
        WHERE w.sales_agent_id = ? 
        AND w.current_stage IN ('sales_review', 'editor_polishing', 'printing_document', 'payment_pending', 'payment_accepted')
        ORDER BY w.updated_at DESC
    ");
    $stmt->bind_param("i", $sales_agent_id);
    $stmt->execute();
    return $stmt->get_result();
}

// Function to get documents for an editor
function getEditorDocuments($editor_id, $conn) {
    $stmt = $conn->prepare("
        SELECT d.*, 
               u.username as client_name, 
               s.username as sales_agent_name,
               w.current_stage, 
               w.editor_notes, 
               w.updated_at as workflow_updated_at,
               w.updated_at as assigned_at,
               p.payment_type,
               p.status as payment_status
        FROM documents d 
        JOIN users u ON d.client_id = u.id 
        JOIN document_workflow w ON d.id = w.document_id
        LEFT JOIN document_assignments da ON d.id = da.document_id
        LEFT JOIN users s ON da.sales_agent_id = s.id
        LEFT JOIN payments p ON d.id = p.document_id
        WHERE w.editor_id = ? 
        AND w.current_stage IN ('editor_polishing', 'editor_review')
        ORDER BY w.updated_at DESC
    ");
    $stmt->bind_param("i", $editor_id);
    $stmt->execute();
    return $stmt->get_result();
}

// Function to get documents for a printing operator
function getOperatorDocuments($operator_id, $conn) {
    $stmt = $conn->prepare("
        SELECT d.*, u.username as client_name, e.username as editor_name,
               w.current_stage, w.operator_notes, w.updated_at as assigned_at
        FROM documents d 
        JOIN users u ON d.client_id = u.id 
        JOIN document_workflow w ON d.id = w.document_id
        JOIN users e ON w.editor_id = e.id
        WHERE w.operator_id = ? OR (w.current_stage = 'editor_review' AND w.operator_id IS NULL)
    ");
    $stmt->bind_param("i", $operator_id);
    $stmt->execute();
    return $stmt->get_result();
}

// Function to add workflow history entry
function addWorkflowHistory($document_id, $from_stage, $to_stage, $changed_by, $notes, $conn) {
    $stmt = $conn->prepare("
        INSERT INTO workflow_history (
            document_id, 
            from_stage, 
            to_stage, 
            changed_by, 
            notes
        ) VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("issis", $document_id, $from_stage, $to_stage, $changed_by, $notes);
    return $stmt->execute();
}

// Function to get workflow history
function getWorkflowHistory($document_id, $conn) {
    $stmt = $conn->prepare("
        SELECT h.*, u.username as changed_by_name
        FROM workflow_history h
        JOIN users u ON h.changed_by = u.id
        WHERE h.document_id = ?
        ORDER BY h.created_at DESC
    ");
    $stmt->bind_param("i", $document_id);
    $stmt->execute();
    return $stmt->get_result();
}

// Function to assign a document to a sales agent
function assignDocumentToSalesAgent($document_id, $sales_agent_id, $conn) {
    $conn->begin_transaction();
    
    try {
        // Check if document is already assigned
        $check_stmt = $conn->prepare("SELECT 1 FROM document_assignments WHERE document_id = ?");
        $check_stmt->bind_param("i", $document_id);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            throw new Exception("Document is already assigned to a sales agent");
        }
        
        // Get sales agent name
        $name_stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
        $name_stmt->bind_param("i", $sales_agent_id);
        $name_stmt->execute();
        $sales_agent_name = $name_stmt->get_result()->fetch_assoc()['username'];
        
        // Get current stage
        $stage_stmt = $conn->prepare("SELECT current_stage FROM document_workflow WHERE document_id = ?");
        $stage_stmt->bind_param("i", $document_id);
        $stage_stmt->execute();
        $current_stage = $stage_stmt->get_result()->fetch_assoc()['current_stage'] ?? 'pending';
        
        // Assign document to sales agent
        $assign_stmt = $conn->prepare("INSERT INTO document_assignments (document_id, sales_agent_id) VALUES (?, ?)");
        $assign_stmt->bind_param("ii", $document_id, $sales_agent_id);
        $assign_stmt->execute();
        
        // Update document status and workflow
        $update_stmt = $conn->prepare("
            UPDATE documents d 
            JOIN document_workflow w ON d.id = w.document_id 
            SET d.status = 'in_progress', 
                w.current_stage = 'sales_review',
                w.sales_agent_id = ?,
                w.sales_notes = CONCAT('Document accepted by ', ?)
            WHERE d.id = ?
        ");
        $update_stmt->bind_param("isi", $sales_agent_id, $sales_agent_name, $document_id);
        $update_stmt->execute();
        
        // Add workflow history
        addWorkflowHistory(
            $document_id,
            $current_stage,
            'sales_review',
            $sales_agent_id,
            "Document assigned to sales agent: $sales_agent_name",
            $conn
        );
        
        $conn->commit();
        return ['success' => true];
    } catch (Exception $e) {
        $conn->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Function to assign a document to an editor
function assignDocumentToEditor($document_id, $editor_id, $conn) {
    $conn->begin_transaction();
    
    try {
        // Get editor name
        $name_stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
        $name_stmt->bind_param("i", $editor_id);
        $name_stmt->execute();
        $editor_name = $name_stmt->get_result()->fetch_assoc()['username'];
        
        // Get current stage
        $stage_stmt = $conn->prepare("SELECT current_stage FROM document_workflow WHERE document_id = ?");
        $stage_stmt->bind_param("i", $document_id);
        $stage_stmt->execute();
        $current_stage = $stage_stmt->get_result()->fetch_assoc()['current_stage'];
        
        $stmt = $conn->prepare("
            UPDATE document_workflow 
            SET editor_id = ?, 
                current_stage = 'editor_polishing',
                editor_notes = CONCAT('Document assigned to editor: ', ?)
            WHERE document_id = ?
        ");
        $stmt->bind_param("isi", $editor_id, $editor_name, $document_id);
        
        if ($stmt->execute()) {
            // Add workflow history
            addWorkflowHistory(
                $document_id,
                $current_stage,
                'editor_polishing',
                $editor_id,
                "Document assigned to editor: $editor_name",
                $conn
            );
            
            $conn->commit();
            return ['success' => true];
        }
        
        throw new Exception('Failed to assign document to editor');
    } catch (Exception $e) {
        $conn->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Function to assign a document to a printing operator
function assignDocumentToOperator($document_id, $operator_id, $conn) {
    $conn->begin_transaction();
    
    try {
        // Get operator name
        $name_stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
        $name_stmt->bind_param("i", $operator_id);
        $name_stmt->execute();
        $operator_name = $name_stmt->get_result()->fetch_assoc()['username'];
        
        // Get current stage
        $stage_stmt = $conn->prepare("SELECT current_stage FROM document_workflow WHERE document_id = ?");
        $stage_stmt->bind_param("i", $document_id);
        $stage_stmt->execute();
        $current_stage = $stage_stmt->get_result()->fetch_assoc()['current_stage'];
        
        $stmt = $conn->prepare("
            UPDATE document_workflow 
            SET operator_id = ?, 
                current_stage = 'printing_document',
                operator_notes = CONCAT('Document assigned to operator: ', ?)
            WHERE document_id = ?
        ");
        $stmt->bind_param("isi", $operator_id, $operator_name, $document_id);
        
        if ($stmt->execute()) {
            // Add workflow history
            addWorkflowHistory(
                $document_id,
                $current_stage,
                'printing_document',
                $operator_id,
                "Document assigned to operator: $operator_name",
                $conn
            );
            
            $conn->commit();
            return ['success' => true];
        }
        
        throw new Exception('Failed to assign document to operator');
    } catch (Exception $e) {
        $conn->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Function to upload a print receipt
function uploadPrintReceipt($document_id, $operator_id, $receipt_file, $notes, $conn) {
    $upload_dir = '../uploads/receipts/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $file_name = time() . '_' . basename($receipt_file['name']);
    $target_path = $upload_dir . $file_name;

    if (move_uploaded_file($receipt_file['tmp_name'], $target_path)) {
        $conn->begin_transaction();
        
        try {
            // Get current stage
            $stage_stmt = $conn->prepare("SELECT current_stage FROM document_workflow WHERE document_id = ?");
            $stage_stmt->bind_param("i", $document_id);
            $stage_stmt->execute();
            $current_stage = $stage_stmt->get_result()->fetch_assoc()['current_stage'];
            
            // Insert receipt record
            $receipt_stmt = $conn->prepare("
                INSERT INTO print_receipts (document_id, operator_id, receipt_path, notes) 
                VALUES (?, ?, ?, ?)
            ");
            $receipt_stmt->bind_param("iiss", $document_id, $operator_id, $file_name, $notes);
            $receipt_stmt->execute();
            
            // Update document status and workflow
            $update_stmt = $conn->prepare("
                UPDATE documents d 
                JOIN document_workflow w ON d.id = w.document_id 
                SET d.status = 'payment_pending', 
                    w.current_stage = 'payment_pending',
                    w.cost_receipt_path = ?
                WHERE d.id = ?
            ");
            $update_stmt->bind_param("si", $file_name, $document_id);
            $update_stmt->execute();
            
            // Add workflow history
            addWorkflowHistory(
                $document_id,
                $current_stage,
                'payment_pending',
                $operator_id,
                "Print receipt uploaded: $notes",
                $conn
            );
            
            $conn->commit();
            return ['success' => true];
        } catch (Exception $e) {
            $conn->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    return ['success' => false, 'message' => 'Failed to upload receipt'];
}

// Function to add a payment record
function addPayment($document_id, $payment_type, $receipt_file, $conn) {
    $upload_dir = '../uploads/payments/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $file_name = time() . '_' . basename($receipt_file['name']);
    $target_path = $upload_dir . $file_name;

    if (move_uploaded_file($receipt_file['tmp_name'], $target_path)) {
        $stmt = $conn->prepare("
            INSERT INTO payments (document_id, payment_type, receipt_path) 
            VALUES (?, ?, ?)
        ");
        $stmt->bind_param("iss", $document_id, $payment_type, $file_name);
        
        if ($stmt->execute()) {
            return ['success' => true];
        }
    }
    
    return ['success' => false, 'message' => 'Failed to add payment'];
}

// Function to get document details
function getDocumentDetails($document_id) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT d.*, 
               w.current_stage, 
               w.editor_id, 
               w.operator_id, 
               w.sales_notes,
               w.editor_notes,
               w.operator_notes,
               w.cost_receipt_path,
               w.payment_requested,
               w.payment_status,
               w.updated_at as workflow_updated_at,
               da.sales_agent_id,
               c.username as client_name,
               c.email as client_email,
               s.username as sales_agent_name,
               s.email as sales_agent_email,
               e.username as editor_name,
               e.email as editor_email,
               o.username as operator_name,
               o.email as operator_email,
               pa.status as payment_agreement_status,
               pa.client_accepted,
               pa.sales_accepted
        FROM documents d
        LEFT JOIN document_workflow w ON d.id = w.document_id
        LEFT JOIN document_assignments da ON d.id = da.document_id
        LEFT JOIN users c ON d.client_id = c.id
        LEFT JOIN users s ON da.sales_agent_id = s.id
        LEFT JOIN users e ON w.editor_id = e.id
        LEFT JOIN users o ON w.operator_id = o.id
        LEFT JOIN payment_agreements pa ON d.id = pa.document_id
        WHERE d.id = ?
    ");
    $stmt->bind_param("i", $document_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Function to add a notification
function addDocumentNotification($document_id, $user_id, $message, $conn) {
    $stmt = $conn->prepare("
        INSERT INTO document_notifications (document_id, user_id, message) 
        VALUES (?, ?, ?)
    ");
    $stmt->bind_param("iis", $document_id, $user_id, $message);
    return $stmt->execute();
}

// Function to get unread notifications for a user
function getUnreadNotifications($user_id, $conn) {
    $stmt = $conn->prepare("
        SELECT n.*, d.title as document_title 
        FROM document_notifications n
        JOIN documents d ON n.document_id = d.id
        WHERE n.user_id = ? AND n.is_read = FALSE
        ORDER BY n.created_at DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result();
}

// Function to mark notifications as read
function markNotificationsAsRead($notification_ids, $conn) {
    $ids = implode(',', array_map('intval', $notification_ids));
    $stmt = $conn->prepare("
        UPDATE document_notifications 
        SET is_read = TRUE 
        WHERE id IN ($ids)
    ");
    return $stmt->execute();
}

function getClientDocuments($client_id, $conn) {
    $stmt = $conn->prepare("
        SELECT d.*, 
               u.username as sales_agent_name,
               w.current_stage,
               w.editor_notes,
               w.operator_notes,
               w.updated_at as workflow_updated_at,
               w.payment_requested,
               w.payment_status,
               w.cost_receipt_path
        FROM documents d
        LEFT JOIN document_assignments da ON d.id = da.document_id
        LEFT JOIN users u ON da.sales_agent_id = u.id
        LEFT JOIN document_workflow w ON d.id = w.document_id
        WHERE d.client_id = ?
        ORDER BY d.created_at DESC
    ");
    $stmt->bind_param("i", $client_id);
    $stmt->execute();
    return $stmt->get_result();
}

function markDocumentAsFinished($document_id, $conn) {
    $conn->begin_transaction();
    
    try {
        // Get current stage
        $stage_stmt = $conn->prepare("SELECT current_stage FROM document_workflow WHERE document_id = ?");
        $stage_stmt->bind_param("i", $document_id);
        $stage_stmt->execute();
        $current_stage = $stage_stmt->get_result()->fetch_assoc()['current_stage'];
        
        // Update document status and workflow
        $update_stmt = $conn->prepare("
            UPDATE documents d 
            JOIN document_workflow w ON d.id = w.document_id 
            SET d.status = 'completed', 
                w.current_stage = 'completed'
            WHERE d.id = ?
        ");
        $update_stmt->bind_param("i", $document_id);
        
        if ($update_stmt->execute()) {
            // Add workflow history
            addWorkflowHistory(
                $document_id,
                $current_stage,
                'completed',
                $_SESSION['user_id'],
                "Document marked as completed",
                $conn
            );
            
            $conn->commit();
            return ['success' => true];
        }
        
        throw new Exception('Failed to mark document as finished');
    } catch (Exception $e) {
        $conn->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Document helper functions

function getUserDocuments($user_id, $role) {
    global $conn;
    
    $query = "SELECT d.*, 
                     w.current_stage, 
                     w.editor_id, 
                     w.operator_id, 
                     da.sales_agent_id,
                     u.username as client_name,
                     s.username as sales_agent_name,
                     e.username as editor_name,
                     o.username as operator_name
              FROM documents d
              LEFT JOIN document_workflow w ON d.id = w.document_id
              LEFT JOIN document_assignments da ON d.id = da.document_id
              LEFT JOIN users u ON d.client_id = u.id
              LEFT JOIN users s ON da.sales_agent_id = s.id
              LEFT JOIN users e ON w.editor_id = e.id
              LEFT JOIN users o ON w.operator_id = o.id
              WHERE ";
    
    switch ($role) {
        case 'admin':
            $query .= "1=1"; // Admin can see all documents
            break;
        case 'client':
            $query .= "d.client_id = ?";
            break;
        case 'sales':
            $query .= "(da.sales_agent_id = ? OR w.current_stage = 'sales_review')";
            break;
        case 'editor':
            $query .= "(w.editor_id = ? OR w.current_stage IN ('editor_polishing', 'editor_review'))";
            break;
        case 'operator':
            $query .= "(w.operator_id = ? OR w.current_stage = 'printing_document')";
            break;
        default:
            return [];
    }
    
    $query .= " ORDER BY d.created_at DESC";
    
    $stmt = $conn->prepare($query);
    if ($role !== 'admin') {
        $stmt->bind_param("i", $user_id);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $documents = [];
    while ($row = $result->fetch_assoc()) {
        $documents[] = $row;
    }
    
    return $documents;
}

function updateDocumentStage($document_id, $new_stage, $editor_id = null, $operator_id = null) {
    global $conn;
    
    $stmt = $conn->prepare("
        UPDATE document_workflow 
        SET current_stage = ?, editor_id = ?, operator_id = ?, updated_at = NOW()
        WHERE document_id = ?
    ");
    $stmt->bind_param("siii", $new_stage, $editor_id, $operator_id, $document_id);
    return $stmt->execute();
}

function assignDocument($document_id, $user_id, $role) {
    global $conn;
    
    switch ($role) {
        case 'editor':
            $stmt = $conn->prepare("UPDATE document_workflow SET editor_id = ? WHERE document_id = ?");
            break;
        case 'operator':
            $stmt = $conn->prepare("UPDATE document_workflow SET operator_id = ? WHERE document_id = ?");
            break;
        case 'sales':
            $stmt = $conn->prepare("UPDATE document_assignments SET sales_agent_id = ? WHERE document_id = ?");
            break;
        default:
            return false;
    }
    
    $stmt->bind_param("ii", $user_id, $document_id);
    return $stmt->execute();
}

function getAvailableUsers($role) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT id, username, email FROM users WHERE role = ? AND status = 'active'");
    $stmt->bind_param("s", $role);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    
    return $users;
}

function finishPrinting($document_id, $operator_id, $cost, $notes, $conn) {
    try {
        $conn->begin_transaction();

        // Get document details with operator information
        $stmt = $conn->prepare("
            SELECT d.title, u.username as operator_name, w.current_stage
            FROM documents d 
            JOIN users u ON u.id = ? 
            JOIN document_workflow w ON d.id = w.document_id
            WHERE d.id = ?
        ");
        $stmt->bind_param("ii", $operator_id, $document_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $document = $result->fetch_assoc();

        if (!$document) {
            throw new Exception("Document not found");
        }

        // Generate receipt path
        $receipt_dir = dirname(__DIR__) . '/uploads/receipts/';
        if (!file_exists($receipt_dir)) {
            mkdir($receipt_dir, 0777, true);
        }

        $receipt_filename = 'receipt_' . time() . '_' . uniqid() . '.txt';
        $receipt_path = $receipt_dir . $receipt_filename;

        // Create receipt content
        $receipt_content = "Printing Receipt\n";
        $receipt_content .= "================\n\n";
        $receipt_content .= "Document: " . $document['title'] . "\n";
        $receipt_content .= "Cost: $" . number_format($cost, 2) . "\n";
        $receipt_content .= "Operator: " . $document['operator_name'] . "\n";
        $receipt_content .= "Date: " . date('Y-m-d H:i:s') . "\n";
        $receipt_content .= "Notes: " . $notes . "\n";

        // Save receipt file
        file_put_contents($receipt_path, $receipt_content);

        // Update document workflow with detailed notes
        $stmt = $conn->prepare("
            UPDATE document_workflow 
            SET current_stage = 'payment_pending',
                operator_notes = CONCAT('Printing completed on ', NOW(), 
                                      '\nOperator Notes: ', ?,
                                      '\nCost: $', ?,
                                      '\nCompleted by: ', ?),
                cost_receipt_path = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE document_id = ?
        ");
        $stmt->bind_param("sdssi", $notes, $cost, $document['operator_name'], $receipt_path, $document_id);
        $stmt->execute();

        // Add workflow history with detailed information
        addWorkflowHistory(
            $document_id,
            $document['current_stage'],
            'payment_pending',
            $operator_id,
            "Printing completed by operator {$document['operator_name']}. Cost: $" . number_format($cost, 2) . ". Notes: {$notes}",
            $conn
        );

        // Add notification for sales agent
        $stmt = $conn->prepare("
            SELECT sales_agent_id 
            FROM document_workflow 
            WHERE document_id = ?
        ");
        $stmt->bind_param("i", $document_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $workflow = $result->fetch_assoc();

        if ($workflow && $workflow['sales_agent_id']) {
            addDocumentNotification(
                $document_id,
                $workflow['sales_agent_id'],
                "Printing completed for document: " . $document['title'] . " by " . $document['operator_name'],
                $conn
            );
        }

        $conn->commit();

        return [
            'success' => true,
            'receipt' => [
                'document_title' => $document['title'],
                'cost' => number_format($cost, 2),
                'notes' => $notes,
                'operator_name' => $document['operator_name'],
                'created_at' => date('Y-m-d H:i:s')
            ]
        ];
    } catch (Exception $e) {
        $conn->rollback();
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}
?> 