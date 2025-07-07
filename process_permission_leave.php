<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Initialize response array
$response = [
    'success' => false,
    'message' => '',
    'redirect' => ''
];

try {
    // Check if this is a POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Get form data
    $permissionDate = isset($_POST['permission_date']) ? trim($_POST['permission_date']) : '';
    $permissionSlot = isset($_POST['permission_slot']) ? trim($_POST['permission_slot']) : '';
    $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';
    
    // Validate required fields
    if (empty($permissionDate) || empty($permissionSlot) || empty($reason)) {
        throw new Exception('All fields are required');
    }

    // Validate date format
    if (!strtotime($permissionDate)) {
        throw new Exception('Invalid date format');
    }

    // Validate permission slot
    if (!in_array($permissionSlot, ['morning', 'evening'])) {
        throw new Exception('Invalid time slot selected');
    }

    // Start transaction
    $conn->begin_transaction();

    try {
        // Insert leave application
        $stmt = $conn->prepare("INSERT INTO leave_applications 
            (user_id, leave_type_id, start_date, end_date, reason, status, is_permission, permission_slot, created_at) 
            VALUES (?, ?, ?, ?, ?, 'pending', 1, ?, NOW())");
        
        // Get the permission leave type ID
        $leaveTypeStmt = $conn->prepare("SELECT id FROM leave_types WHERE name = 'permission_leave' LIMIT 1");
        $leaveTypeStmt->execute();
        $leaveTypeResult = $leaveTypeStmt->get_result();
        $leaveType = $leaveTypeResult->fetch_assoc();
        
        if (!$leaveType) {
            throw new Exception('Permission leave type not found');
        }
        
        $leaveTypeId = $leaveType['id'];
        $startDate = $permissionDate;
        $endDate = $permissionDate; // Same as start date for permission leave
        
        $stmt->bind_param("iissss", 
            $_SESSION['user_id'], 
            $leaveTypeId, 
            $startDate, 
            $endDate, 
            $reason,
            $permissionSlot
        );
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to save leave application: ' . $stmt->error);
        }
        
        $leaveId = $conn->insert_id;
        
        // Process class adjustments if any
        if (isset($_POST['adjustments']) && is_array($_POST['adjustments'])) {
            $adjStmt = $conn->prepare("INSERT INTO class_adjustments 
                (leave_id, adjustment_date, adjustment_time, subject, adjusted_faculty_id, status, created_at) 
                VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
            
            foreach ($_POST['adjustments'] as $adjustment) {
                if (!empty($adjustment['date']) && !empty($adjustment['time']) && 
                    !empty($adjustment['subject']) && !empty($adjustment['faculty_id'])) {
                    
                    $adjStmt->bind_param("isssi", 
                        $leaveId,
                        $adjustment['date'],
                        $adjustment['time'],
                        $adjustment['subject'],
                        $adjustment['faculty_id']
                    );
                    
                    if (!$adjStmt->execute()) {
                        throw new Exception('Failed to save class adjustment: ' . $adjStmt->error);
                    }
                }
            }
            
            $adjStmt->close();
        }
        
        // Send notification to HOD
        $notificationMessage = "New permission leave request from " . $_SESSION['name'] . " for " . 
                             date('d-m-Y', strtotime($permissionDate)) . " (" . ucfirst($permissionSlot) . ")";
        
        // Get HOD user ID (assuming role_id 2 is HOD)
        $hodStmt = $conn->prepare("SELECT id FROM users WHERE role_id = 2 AND department_id = ? LIMIT 1");
        $hodStmt->bind_param("i", $_SESSION['department_id']);
        $hodStmt->execute();
        $hodResult = $hodStmt->get_result();
        
        if ($hod = $hodResult->fetch_assoc()) {
            $notifStmt = $conn->prepare("INSERT INTO notifications 
                (user_id, title, message, type, reference_id, created_at) 
                VALUES (?, 'New Permission Leave Request', ?, 'permission_leave', ?, NOW())");
            
            $notifStmt->bind_param("isi", $hod['id'], $notificationMessage, $leaveId);
            $notifStmt->execute();
            $notifStmt->close();
        }
        
        $hodStmt->close();
        
        // Commit transaction
        $conn->commit();
        
        $response['success'] = true;
        $response['message'] = 'Permission leave submitted successfully';
        $response['redirect'] = 'leave_status.php';
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?>
