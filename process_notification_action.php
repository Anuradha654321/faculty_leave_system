<?php
require_once 'config/config.php';

// Require login to access this page
requireLogin();

// Check if user has the required role
$userRole = $_SESSION['role'];
if (!in_array($userRole, ['hod', 'central_admin', 'admin'])) {
    redirect('index.php');
}

// Function to update leave balance
function updateLeaveBalance($conn, $userId, $leaveTypeId, $totalDays, $currentYear) {
    // First check if the balance record exists
    $checkBalanceQuery = "SELECT id, used_days FROM leave_balances 
                         WHERE user_id = ? AND leave_type_id = ? AND year = ?";
    $checkBalanceStmt = $conn->prepare($checkBalanceQuery);
    $checkBalanceStmt->bind_param("iii", $userId, $leaveTypeId, $currentYear);
    $checkBalanceStmt->execute();
    $balanceResult = $checkBalanceStmt->get_result();
    
    if ($balanceResult->num_rows > 0) {
        // Update existing balance
        $balanceRow = $balanceResult->fetch_assoc();
        $newUsedDays = $balanceRow['used_days'] + $totalDays;
        
        $updateBalanceQuery = "UPDATE leave_balances 
                             SET used_days = ? 
                             WHERE id = ?";
        $updateBalanceStmt = $conn->prepare($updateBalanceQuery);
        $updateBalanceStmt->bind_param("di", $newUsedDays, $balanceRow['id']);
        $updateBalanceStmt->execute();
    } else {
        // Create new balance record if it doesn't exist
        $insertBalanceQuery = "INSERT INTO leave_balances (user_id, leave_type_id, year, total_days, used_days) 
                             VALUES (?, ?, ?, 0, ?)";
        $insertBalanceStmt = $conn->prepare($insertBalanceQuery);
        $insertBalanceStmt->bind_param("iiid", $userId, $leaveTypeId, $currentYear, $totalDays);
        $insertBalanceStmt->execute();
    }
}

// Process the form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $applicationId = isset($_POST['application_id']) ? intval($_POST['application_id']) : 0;
    $status = isset($_POST['status']) ? sanitizeInput($_POST['status']) : '';
    $remarks = isset($_POST['remarks']) ? sanitizeInput($_POST['remarks']) : '';
    
    if (!$applicationId || !in_array($status, ['approved', 'rejected'])) {
        $_SESSION['error'] = 'Invalid parameters provided.';
        redirect('notifications.php');
    }
    
    $conn = connectDB();
    $conn->begin_transaction();
    
    try {
        if ($userRole === 'hod') {
            // Update HOD approval status
            $query = "UPDATE leave_applications 
                      SET hod_approval = ?, hod_remarks = ?, hod_action_date = NOW()
                      WHERE application_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssi", $status, $remarks, $applicationId);
            $stmt->execute();
            
            if ($status === 'approved') {
                // Get application details
                $checkQuery = "SELECT lt.type_name, la.total_days, la.user_id, 
                              la.leave_type_id
                              FROM leave_applications la
                              JOIN leave_types lt ON la.leave_type_id = lt.type_id
                              WHERE la.application_id = ?";
                $checkStmt = $conn->prepare($checkQuery);
                $checkStmt->bind_param("i", $applicationId);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();
                $checkRow = $checkResult->fetch_assoc();
                
                // Get application details
                $userId = $checkRow['user_id'];
                $leaveTypeId = $checkRow['leave_type_id'];
                $totalDays = $checkRow['total_days'];
                $currentYear = date('Y');
                
                // Determine if further approval is needed based on the new rules
                if ((strpos($checkRow['type_name'], 'casual_leave') !== false) && $checkRow['total_days'] <= 3) {
                    // Casual leaves of 3 days or less only need HOD approval
                    $newStatus = 'approved';
                    
                    // Update leave application status
                    $updateStatusQuery = "UPDATE leave_applications SET status = ? WHERE application_id = ?";
                    $updateStatusStmt = $conn->prepare($updateStatusQuery);
                    $updateStatusStmt->bind_param("si", $newStatus, $applicationId);
                    $updateStatusStmt->execute();
                    
                    // Update leave balance immediately
                    updateLeaveBalance($conn, $userId, $leaveTypeId, $totalDays, $currentYear);
                } else {
                    // Other leave types or casual leaves > 3 days need further approval
                    $newStatus = 'approved_by_hod';
                    
                    // Update leave application status
                    $updateStatusQuery = "UPDATE leave_applications SET status = ? WHERE application_id = ?";
                    $updateStatusStmt = $conn->prepare($updateStatusQuery);
                    $updateStatusStmt->bind_param("si", $newStatus, $applicationId);
                    $updateStatusStmt->execute();
                }
                
                // Add notification for the faculty
                $notificationQuery = "INSERT INTO notifications (user_id, title, message, is_read, created_at) 
                                     VALUES (?, 'Leave Application Update', ?, 0, NOW())";
                $notificationMessage = "Your leave application has been approved by HOD. Remarks: " . $remarks;
                if ($newStatus === 'approved') {
                    $notificationMessage = "Your leave application has been fully approved. Remarks: " . $remarks;
                }
                $notificationStmt = $conn->prepare($notificationQuery);
                $notificationStmt->bind_param("is", $userId, $notificationMessage);
                $notificationStmt->execute();
            } else {
                // If rejected, update the status to rejected
                $updateStatusQuery = "UPDATE leave_applications SET status = 'rejected' WHERE application_id = ?";
                $updateStatusStmt = $conn->prepare($updateStatusQuery);
                $updateStatusStmt->bind_param("i", $applicationId);
                $updateStatusStmt->execute();
                
                // Get user ID for notification
                $userQuery = "SELECT user_id FROM leave_applications WHERE application_id = ?";
                $userStmt = $conn->prepare($userQuery);
                $userStmt->bind_param("i", $applicationId);
                $userStmt->execute();
                $userResult = $userStmt->get_result();
                $userRow = $userResult->fetch_assoc();
                
                // Add notification for the faculty
                $notificationQuery = "INSERT INTO notifications (user_id, title, message, is_read, created_at) 
                                     VALUES (?, 'Leave Application Rejected', ?, 0, NOW())";
                $notificationMessage = "Your leave application has been rejected by HOD. Reason: " . $remarks;
                $notificationStmt = $conn->prepare($notificationQuery);
                $notificationStmt->bind_param("is", $userRow['user_id'], $notificationMessage);
                $notificationStmt->execute();
            }
        } elseif ($userRole === 'admin') {
            // Update admin approval status
            $query = "UPDATE leave_applications 
                      SET admin_approval = ?, admin_remarks = ?, admin_action_date = NOW()
                      WHERE application_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssi", $status, $remarks, $applicationId);
            $stmt->execute();
            
            if ($status === 'approved') {
                // Update the main status to approved
                $updateStatusQuery = "UPDATE leave_applications SET status = 'approved' WHERE application_id = ?";
                $updateStatusStmt = $conn->prepare($updateStatusQuery);
                $updateStatusStmt->bind_param("i", $applicationId);
                $updateStatusStmt->execute();
                
                // Get application details for leave balance update
                $checkQuery = "SELECT la.user_id, la.leave_type_id, la.total_days 
                              FROM leave_applications la
                              WHERE la.application_id = ?";
                $checkStmt = $conn->prepare($checkQuery);
                $checkStmt->bind_param("i", $applicationId);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();
                $checkRow = $checkResult->fetch_assoc();
                
                // Update leave balance
                $currentYear = date('Y');
                updateLeaveBalance($conn, $checkRow['user_id'], $checkRow['leave_type_id'], $checkRow['total_days'], $currentYear);
                
                // Add notification for the faculty
                $notificationQuery = "INSERT INTO notifications (user_id, title, message, is_read, created_at) 
                                     VALUES (?, 'Leave Application Approved', ?, 0, NOW())";
                $notificationMessage = "Your leave application has been fully approved by Admin. Remarks: " . $remarks;
                $notificationStmt = $conn->prepare($notificationQuery);
                $notificationStmt->bind_param("is", $checkRow['user_id'], $notificationMessage);
                $notificationStmt->execute();
            } else {
                // If rejected, update the main status to rejected
                $updateStatusQuery = "UPDATE leave_applications SET status = 'rejected' WHERE application_id = ?";
                $updateStatusStmt = $conn->prepare($updateStatusQuery);
                $updateStatusStmt->bind_param("i", $applicationId);
                $updateStatusStmt->execute();
                
                // Get user ID for notification
                $userQuery = "SELECT user_id FROM leave_applications WHERE application_id = ?";
                $userStmt = $conn->prepare($userQuery);
                $userStmt->bind_param("i", $applicationId);
                $userStmt->execute();
                $userResult = $userStmt->get_result();
                $userRow = $userResult->fetch_assoc();
                
                // Add notification for the faculty
                $notificationQuery = "INSERT INTO notifications (user_id, title, message, is_read, created_at) 
                                     VALUES (?, 'Leave Application Rejected', ?, 0, NOW())";
                $notificationMessage = "Your leave application has been rejected by Admin. Reason: " . $remarks;
                $notificationStmt = $conn->prepare($notificationQuery);
                $notificationStmt->bind_param("is", $userRow['user_id'], $notificationMessage);
                $notificationStmt->execute();
            }
        }
        
        // Update the application's last_updated field
        $updateLastUpdatedQuery = "UPDATE leave_applications SET last_updated = NOW() WHERE application_id = ?";
        $updateLastUpdatedStmt = $conn->prepare($updateLastUpdatedQuery);
        $updateLastUpdatedStmt->bind_param("i", $applicationId);
        $updateLastUpdatedStmt->execute();
        
        $conn->commit();
        $_SESSION['success'] = 'Leave application ' . ($status === 'approved' ? 'approved' : 'rejected') . ' successfully.';
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = 'An error occurred: ' . $e->getMessage();
    }
    
    closeDB($conn);
    
    // Check if it's an AJAX request
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        // Return JSON response for AJAX requests
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Leave application ' . ($status === 'approved' ? 'approved' : 'rejected') . ' successfully.'
        ]);
        exit;
    } else {
        // Regular form submission - redirect
        redirect('notifications.php');
    }
}
?>
