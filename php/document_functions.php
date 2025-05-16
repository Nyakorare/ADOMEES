<?php
// Function to upload a document
function uploadDocument($client_id, $title, $description, $file, $conn, $payment_type) {
    $upload_dir = '../uploads/documents/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $file_name = time() . '_' . basename($file['name']);
    $target_path = $upload_dir . $file_name;

    if (move_uploaded_file($file['tmp_name'], $target_path)) {
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
                    current_stage
                ) VALUES (?, 'sales_review')
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
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    return ['success' => false, 'message' => 'Failed to upload document file'];
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
        SELECT d.*, u.username as client_name, da.assigned_at,
               w.current_stage, w.sales_notes
        FROM documents d 
        JOIN users u ON d.client_id = u.id 
        JOIN document_assignments da ON d.id = da.document_id
        JOIN document_workflow w ON d.id = w.document_id
        WHERE da.sales_agent_id = ?
    ");
    $stmt->bind_param("i", $sales_agent_id);
    $stmt->execute();
    return $stmt->get_result();
}

// Function to get documents for an editor
function getEditorDocuments($editor_id, $conn) {
    $stmt = $conn->prepare("
        SELECT d.*, u.username as client_name, s.username as sales_agent_name,
               w.current_stage, w.editor_notes
        FROM documents d 
        JOIN users u ON d.client_id = u.id 
        JOIN document_assignments da ON d.id = da.document_id
        JOIN users s ON da.sales_agent_id = s.id
        JOIN document_workflow w ON d.id = w.document_id
        WHERE w.editor_id = ? OR (w.current_stage = 'sales_review' AND w.editor_id IS NULL)
    ");
    $stmt->bind_param("i", $editor_id);
    $stmt->execute();
    return $stmt->get_result();
}

// Function to get documents for a printing operator
function getOperatorDocuments($operator_id, $conn) {
    $stmt = $conn->prepare("
        SELECT d.*, u.username as client_name, e.username as editor_name,
               w.current_stage, w.operator_notes
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
        
        $stmt = $conn->prepare("
            UPDATE document_workflow 
            SET editor_id = ?, 
                current_stage = 'editor_polishing',
                editor_notes = CONCAT('Document assigned to editor: ', ?)
            WHERE document_id = ?
        ");
        $stmt->bind_param("isi", $editor_id, $editor_name, $document_id);
        
        if ($stmt->execute()) {
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
        
        $stmt = $conn->prepare("
            UPDATE document_workflow 
            SET operator_id = ?, 
                current_stage = 'printing_document',
                operator_notes = CONCAT('Document assigned to operator: ', ?)
            WHERE document_id = ?
        ");
        $stmt->bind_param("isi", $operator_id, $operator_name, $document_id);
        
        if ($stmt->execute()) {
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
function getDocumentDetails($document_id, $conn) {
    $stmt = $conn->prepare("
        SELECT d.*, 
               u.username as client_name,
               s.username as sales_agent_name,
               e.username as editor_name,
               o.username as operator_name,
               w.current_stage,
               w.sales_notes,
               w.editor_notes,
               w.operator_notes,
               w.sales_agent_id,
               w.editor_id,
               w.operator_id,
               w.updated_at as workflow_updated_at,
               p.amount as payment_amount,
               p.payment_type,
               p.status as payment_status,
               pr.receipt_path as print_receipt_path,
               pr.notes as print_receipt_notes,
               da.assigned_at as sales_assigned_at
        FROM documents d
        JOIN users u ON d.client_id = u.id
        LEFT JOIN document_assignments da ON d.id = da.document_id
        LEFT JOIN users s ON da.sales_agent_id = s.id
        LEFT JOIN document_workflow w ON d.id = w.document_id
        LEFT JOIN users e ON w.editor_id = e.id
        LEFT JOIN users o ON w.operator_id = o.id
        LEFT JOIN payments p ON d.id = p.document_id
        LEFT JOIN print_receipts pr ON d.id = pr.document_id
        WHERE d.id = ?
    ");
    $stmt->bind_param("i", $document_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
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
               w.updated_at as workflow_updated_at
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
        // Update document status and workflow
        $update_stmt = $conn->prepare("
            UPDATE documents d 
            JOIN document_workflow w ON d.id = w.document_id 
            SET d.status = 'completed', 
                w.current_stage = 'finished'
            WHERE d.id = ?
        ");
        $update_stmt->bind_param("i", $document_id);
        
        if ($update_stmt->execute()) {
            $conn->commit();
            return ['success' => true];
        }
        
        throw new Exception('Failed to mark document as finished');
    } catch (Exception $e) {
        $conn->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}
?> 