<?php
require_once 'config/config.php';

// Require login to access this page
requireLogin();

// Check if user has the required role
$userRole = $_SESSION['role'];
if (!in_array($userRole, ['hod', 'central_admin', 'admin'])) {
    redirect(BASE_URL . 'unauthorized.php');
}

// Function to update leave balance
function updateLeaveBalance($conn, $userId, $leaveTypeId, $totalDays, $currentYear) {
    // Log the update for debugging
    error_log("Updating leave balance for user $userId, leave type $leaveTypeId, days $totalDays, year $currentYear");
    
    // First check if the balance record exists
    $checkBalanceQuery = "SELECT id, used_days, total_days FROM leave_balances 
                         WHERE user_id = ? AND leave_type_id = ? AND year = ?";
    $checkBalanceStmt = $conn->prepare($checkBalanceQuery);
    $checkBalanceStmt->bind_param("iii", $userId, $leaveTypeId, $currentYear);
    $checkBalanceStmt->execute();
    $balanceResult = $checkBalanceStmt->get_result();
    
    if ($balanceResult->num_rows > 0) {
        // Update existing balance
        $balanceRow = $balanceResult->fetch_assoc();
        $newUsedDays = $balanceRow['used_days'] + $totalDays;
        
        // Log the update details
        error_log("Existing balance found: ID {$balanceRow['id']}, current used days {$balanceRow['used_days']}, new used days $newUsedDays");
        
        $updateBalanceQuery = "UPDATE leave_balances 
                             SET used_days = ? 
                             WHERE id = ?";
        $updateBalanceStmt = $conn->prepare($updateBalanceQuery);
        $updateBalanceStmt->bind_param("di", $newUsedDays, $balanceRow['id']);
        $result = $updateBalanceStmt->execute();
        
        if (!$result) {
            error_log("Failed to update leave balance: " . $conn->error);
        } else {
            error_log("Successfully updated leave balance");
        }
    } else {
        // Get default total days for this leave type
        $defaultDaysQuery = "SELECT default_days FROM leave_types WHERE type_id = ?";
        $defaultDaysStmt = $conn->prepare($defaultDaysQuery);
        $defaultDaysStmt->bind_param("i", $leaveTypeId);
        $defaultDaysStmt->execute();
        $defaultDaysResult = $defaultDaysStmt->get_result();
        $defaultDays = 0;
        
        if ($defaultDaysResult->num_rows > 0) {
            $defaultDaysRow = $defaultDaysResult->fetch_assoc();
            $defaultDays = $defaultDaysRow['default_days'];
        }
        
        // Create new balance record if it doesn't exist
        error_log("No balance record found, creating new one with default days $defaultDays and used days $totalDays");
        
        $insertBalanceQuery = "INSERT INTO leave_balances (user_id, leave_type_id, year, total_days, used_days) 
                              VALUES (?, ?, ?, ?, ?)";
        $insertBalanceStmt = $conn->prepare($insertBalanceQuery);
        $insertBalanceStmt->bind_param("iiiii", $userId, $leaveTypeId, $currentYear, $defaultDays, $totalDays);
        $result = $insertBalanceStmt->execute();
        
        if (!$result) {
            error_log("Failed to create leave balance: " . $conn->error);
        } else {
            error_log("Successfully created leave balance");
        }
    }
}

// Function to get user's leave balances
function getUserLeaveBalances($userId) {
    $conn = connectDB();
    $currentYear = date('Y');
    
    $query = "SELECT lt.type_id, lt.type_name, lt.description, lb.total_days, lb.used_days, (lb.total_days - lb.used_days) as remaining_days
              FROM leave_types lt
              LEFT JOIN leave_balances lb ON lt.type_id = lb.leave_type_id AND lb.user_id = ? AND lb.year = ?
              ORDER BY lt.type_name";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $userId, $currentYear);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $balances = [];
    while ($row = $result->fetch_assoc()) {
        $balances[] = $row;
    }
    
    $stmt->close();
    closeDB($conn);
    
    return $balances;
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];
$deptId = $_SESSION['dept_id'];

// Get pending leave applications
function getPendingApplications($userRole, $deptId) {
    $conn = connectDB();
    
    // Different queries based on user role
    if ($userRole === 'hod') {
        // HOD sees applications from their department only
        $query = "SELECT la.application_id, u.user_id, u.first_name, u.last_name, lt.type_name, lt.type_id, 
                  la.start_date, la.end_date, la.total_days, la.status, la.application_date,
                  la.reason, la.document_path, la.leave_type_id
                  FROM leave_applications la
                  JOIN users u ON la.user_id = u.user_id
                  JOIN leave_types lt ON la.leave_type_id = lt.type_id
                  WHERE u.dept_id = ? AND la.status = 'pending'
                  ORDER BY la.application_date DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $deptId);
    } elseif ($userRole === 'central_admin') {
        // Central admin sees applications approved by HOD
        $query = "SELECT la.application_id, u.user_id, u.first_name, u.last_name, lt.type_name, lt.type_id, 
                  la.start_date, la.end_date, la.total_days, la.status, la.application_date,
                  la.reason, la.document_path, d.dept_name, la.leave_type_id
                  FROM leave_applications la
                  JOIN users u ON la.user_id = u.user_id
                  JOIN leave_types lt ON la.leave_type_id = lt.type_id
                  JOIN departments d ON u.dept_id = d.dept_id
                  WHERE la.hod_approval = 'approved' AND la.admin_approval = 'pending'
                  ORDER BY la.application_date DESC";
        
        $stmt = $conn->prepare($query);
    } elseif ($userRole === 'admin') {
        // Admin sees applications approved by central admin or those requiring direct admin approval
        // Exclude casual leaves of 3 days or less as they only need HOD approval
        $query = "SELECT la.application_id, u.user_id, u.first_name, u.last_name, lt.type_name, lt.type_id, 
                  la.start_date, la.end_date, la.total_days, la.status, la.application_date,
                  la.reason, la.document_path, d.dept_name, la.leave_type_id
                  FROM leave_applications la
                  JOIN users u ON la.user_id = u.user_id
                  JOIN leave_types lt ON la.leave_type_id = lt.type_id
                  JOIN departments d ON u.dept_id = d.dept_id
                  WHERE (la.hod_approval = 'approved' AND la.admin_approval = 'pending')
                  AND NOT (lt.type_name LIKE '%casual_leave%' AND la.total_days <= 3)
                  ORDER BY la.application_date DESC";
        
        $stmt = $conn->prepare($query);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $applications = [];
    while ($row = $result->fetch_assoc()) {
        $applications[] = $row;
    }
    
    $stmt->close();
    closeDB($conn);
    
    return $applications;
}

// Get class adjustments for a leave application
function getClassAdjustments($applicationId) {
    $conn = connectDB();
    if (!$conn) {
        return [];
    }
    
    $query = "SELECT 
                ca.adjustment_id, 
                ca.class_date, 
                ca.class_time, 
                COALESCE(ca.subject, 'No Subject') as subject, 
                ca.status, 
                ca.remarks, 
                u.first_name, 
                u.last_name,
                la.leave_type_id,
                lt.type_name as leave_type
              FROM class_adjustments ca
              JOIN users u ON ca.adjusted_by = u.user_id
              LEFT JOIN leave_applications la ON ca.application_id = la.application_id
              LEFT JOIN leave_types lt ON la.leave_type_id = lt.type_id
              WHERE ca.application_id = ?
              ORDER BY ca.class_date ASC";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("Failed to prepare statement: " . $conn->error);
        closeDB($conn);
        return [];
    }
    
    if (!$stmt->bind_param("i", $applicationId)) {
        error_log("Failed to bind parameters: " . $stmt->error);
        $stmt->close();
        closeDB($conn);
        return [];
    }
    
    if (!$stmt->execute()) {
        error_log("Failed to execute query: " . $stmt->error);
        $stmt->close();
        closeDB($conn);
        return [];
    }
    
    $result = $stmt->get_result();
    if (!$result) {
        error_log("Failed to get result: " . $stmt->error);
        $stmt->close();
        closeDB($conn);
        return [];
    }
    
    $adjustments = [];
    while ($row = $result->fetch_assoc()) {
        $adjustments[] = $row;
    }
    
    $stmt->close();
    closeDB($conn);
    
    return $adjustments;
}

// Handle application approval/rejection
$success = '';
$error = '';

// Handle actions from GET parameters (e.g., from notification links)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = sanitizeInput($_GET['action']);
    $applicationId = intval($_GET['id']);
    
    if ($action === 'approve') {
        // Show a form to confirm approval with optional comments
        $approvalForm = true;
        $formAction = 'approve';
        $formId = $applicationId;
    } elseif ($action === 'reject') {
        // Show a form to confirm rejection with required comments
        $approvalForm = true;
        $formAction = 'reject';
        $formId = $applicationId;
    } elseif ($action === 'confirm_approve') {
        // Set up POST data for approval
        $_POST['update_application'] = true;
        $_POST['application_id'] = $applicationId;
        $_POST['status'] = 'approved';
        $_POST['remarks'] = isset($_GET['remarks']) ? sanitizeInput($_GET['remarks']) : 'Approved from notification';
    } elseif ($action === 'confirm_reject') {
        // Set up POST data for rejection
        if (!isset($_GET['remarks']) || empty($_GET['remarks'])) {
            $_SESSION['error'] = 'Rejection reason is required.';
            redirect('pending_approvals.php');
        }
        $_POST['update_application'] = true;
        $_POST['application_id'] = $applicationId;
        $_POST['status'] = 'rejected';
        $_POST['remarks'] = sanitizeInput($_GET['remarks']);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_application'])) {
    $applicationId = intval($_POST['application_id']);
    $status = sanitizeInput($_POST['status']);
    $remarks = sanitizeInput($_POST['remarks']);
    
    if (!in_array($status, ['approved', 'rejected'])) {
        $error = 'Invalid status value.';
    } else {
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
                
                // If approved, update the main status
                if ($status === 'approved') {
                    // Get leave application details
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
                    
                    // Determine if further approval is needed based on the rules:
                    // 1. Permission leaves and casual leaves of 3 days or less only need HOD approval
                    // 2. Other leaves or casual leaves of more than 3 days need both HOD and admin approval
                    if ($checkRow['is_permission'] == 1 || (strpos($checkRow['type_name'], 'casual_leave') !== false && $checkRow['total_days'] <= 3)) {
                        // Casual leaves of 3 days or less only need HOD approval
                        $newStatus = 'approved';
                        
                        // Update leave application status
                        $updateStatusQuery = "UPDATE leave_applications SET status = ?, hod_approval = ? WHERE application_id = ?";
                        $updateStatusStmt = $conn->prepare($updateStatusQuery);
                        $updateStatusStmt->bind_param("ssi", $newStatus, $status, $applicationId);
                        $updateStatusStmt->execute();
                        
                        // Update leave balance immediately
                        updateLeaveBalance($conn, $userId, $leaveTypeId, $totalDays, $currentYear);
                        
                        // Log the approval for debugging
                        error_log("HOD approved leave application $applicationId. Leave balance updated for user $userId, leave type $leaveTypeId, days $totalDays");
                    } else {
                        // Other leave types or casual leaves > 3 days need further approval
                        $newStatus = 'approved_by_hod';
                        
                        // Update leave application status
                        $updateStatusQuery = "UPDATE leave_applications SET status = ?, hod_approval = ? WHERE application_id = ?";
                        $updateStatusStmt = $conn->prepare($updateStatusQuery);
                        $updateStatusStmt->bind_param("ssi", $newStatus, $status, $applicationId);
                        $updateStatusStmt->execute();
                        
                        // Log the approval for debugging
                        error_log("HOD approved leave application $applicationId. Pending admin approval.");
                    }
                    // Update the main status
                    $updateStatusQuery = "UPDATE leave_applications SET status = ?, hod_approval = ? WHERE application_id = ?";
                    $updateStatusStmt = $conn->prepare($updateStatusQuery);
                    $updateStatusStmt->bind_param("ssi", $newStatus, $status, $applicationId);
                    $updateStatusStmt->execute();
                    
                    // Add notification for the faculty
                    $notificationQuery = "INSERT INTO notifications (user_id, title, message, is_read, created_at) 
                                         VALUES (?, 'Leave Application Update', ?, 0, NOW())";
                    $notificationMessage = "Your leave application has been approved by HOD.";
                    if ($newStatus === 'approved') {
                        $notificationMessage = "Your leave application has been fully approved.";
                    }
                    $notificationStmt = $conn->prepare($notificationQuery);
                    $notificationStmt->bind_param("is", $checkRow['user_id'], $notificationMessage);
                    $notificationStmt->execute();
                    
                    // Update the application's last_updated field to trigger UI refresh
                    $updateLastUpdatedQuery = "UPDATE leave_applications SET last_updated = NOW() WHERE application_id = ?";
                    $updateLastUpdatedStmt = $conn->prepare($updateLastUpdatedQuery);
                    $updateLastUpdatedStmt->bind_param("i", $applicationId);
                    $updateLastUpdatedStmt->execute();
                    
                } elseif ($status === 'rejected') {
                    // If rejected, update the main status to rejected
                    $updateStatusQuery = "UPDATE leave_applications SET status = 'rejected', hod_approval = ? WHERE application_id = ?";
                    $updateStatusStmt = $conn->prepare($updateStatusQuery);
                    $updateStatusStmt->bind_param("si", $status, $applicationId);
                    $updateStatusStmt->execute();
                    
                    // Get user_id for notification
                    $userQuery = "SELECT user_id FROM leave_applications WHERE application_id = ?";
                    $userStmt = $conn->prepare($userQuery);
                    $userStmt->bind_param("i", $applicationId);
                    $userStmt->execute();
                    $userResult = $userStmt->get_result();
                    $userRow = $userResult->fetch_assoc();
                    
                    // Add notification for the faculty
                    $notificationQuery = "INSERT INTO notifications (user_id, title, message, is_read, created_at) 
                                         VALUES (?, 'Leave Application Rejected', ?, 0, NOW())";
                    $notificationMessage = "Your leave application has been rejected by HOD. Remarks: " . $remarks;
                    $notificationStmt = $conn->prepare($notificationQuery);
                    $notificationStmt->bind_param("is", $userRow['user_id'], $notificationMessage);
                    $notificationStmt->execute();
                    
                    // Update the application's last_updated field to trigger UI refresh
                    $updateLastUpdatedQuery = "UPDATE leave_applications SET last_updated = NOW() WHERE application_id = ?";
                    $updateLastUpdatedStmt = $conn->prepare($updateLastUpdatedQuery);
                    $updateLastUpdatedStmt->bind_param("i", $applicationId);
                    $updateLastUpdatedStmt->execute();
                }
            } elseif ($userRole === 'central_admin') {
                // Similar code for central_admin approval with notifications
                // ...
            } elseif ($userRole === 'admin') {
                // Update admin approval status
                $query = "UPDATE leave_applications 
                          SET admin_approval = ?, admin_remarks = ?, admin_action_date = NOW()
                          WHERE application_id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ssi", $status, $remarks, $applicationId);
                $stmt->execute();
                
                // If approved, update the main status
                if ($status === 'approved') {
                    // Update the main status to approved
                    $updateStatusQuery = "UPDATE leave_applications SET status = ?, admin_approval = ? WHERE application_id = ?";
                    $updateStatusStmt = $conn->prepare($updateStatusQuery);
                    $updateStatusStmt->bind_param("ssi", $status, $status, $applicationId);
                    $updateStatusStmt->execute();
                    
                    // Update leave balance
                    $updateBalanceQuery = "UPDATE leave_balances lb
                                        INNER JOIN leave_applications la ON lb.user_id = la.user_id 
                                        AND lb.leave_type_id = la.leave_type_id
                                        AND lb.year = YEAR(CURDATE())
                                        SET lb.used_days = lb.used_days + la.total_days
                                        WHERE la.application_id = ?";
                    $updateBalanceStmt = $conn->prepare($updateBalanceQuery);
                    $updateBalanceStmt->bind_param("i", $applicationId);
                    $updateBalanceStmt->execute();
                    
                    // Get user ID for notification
                    $userQuery = "SELECT user_id FROM leave_applications WHERE application_id = ?";
                    $userStmt = $conn->prepare($userQuery);
                    $userStmt->bind_param("i", $applicationId);
                    $userStmt->execute();
                    $userResult = $userStmt->get_result();
                    $userRow = $userResult->fetch_assoc();
                    
                    // Add notification for the faculty
                    $notificationQuery = "INSERT INTO notifications (user_id, title, message, is_read, created_at) 
                                         VALUES (?, 'Leave Application Approved', ?, 0, NOW())";
                    $notificationMessage = "Your leave application has been fully approved by Admin. Remarks: " . $remarks;
                    $notificationStmt = $conn->prepare($notificationQuery);
                    $notificationStmt->bind_param("is", $userRow['user_id'], $notificationMessage);
                    $notificationStmt->execute();
                    
                } elseif ($status === 'rejected') {
                    // If rejected, update the main status to rejected
                    $updateStatusQuery = "UPDATE leave_applications SET status = 'rejected', admin_approval = ? WHERE application_id = ?";
                    $updateStatusStmt = $conn->prepare($updateStatusQuery);
                    $updateStatusStmt->bind_param("si", $status, $applicationId);
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
                    $notificationMessage = "Your leave application has been rejected by Admin. Remarks: " . $remarks;
                    $notificationStmt = $conn->prepare($notificationQuery);
                    $notificationStmt->bind_param("is", $userRow['user_id'], $notificationMessage);
                    $notificationStmt->execute();
                }
                
                // UI refresh will happen naturally when the page is reloaded
            }
            
            $conn->commit();
            $success = 'Application ' . ($status === 'approved' ? 'approved' : 'rejected') . ' successfully.';
            
        } catch (Exception $e) {
            $conn->rollback();
            $error = 'An error occurred: ' . $e->getMessage();
        }
        
        closeDB($conn);
    }
}

$pendingApplications = getPendingApplications($userRole, $deptId);

// Get class adjustments for applications
$applicationAdjustments = [];
foreach ($pendingApplications as $application) {
    $applicationId = $application['application_id'];
    $adjustments = getClassAdjustments($applicationId);
    if (!empty($adjustments)) {
        $applicationAdjustments[$applicationId] = $adjustments;
    }
}

// Page title
$pageTitle = "Pending Approvals";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> | Faculty Leave Management System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <div class="col-md-9 ml-sm-auto col-lg-10 px-md-4 py-4">
                <?php if (isset($approvalForm) && $approvalForm): ?>
                <div class="card mb-4">
                    <div class="card-header bg-<?php echo ($formAction === 'approve') ? 'success' : 'danger'; ?> text-white">
                        <h5 class="mb-0"><?php echo ($formAction === 'approve') ? 'Approve' : 'Reject'; ?> Leave Application</h5>
                    </div>
                    <div class="card-body">
                        <?php 
                        // Get application details
                        $conn = connectDB();
                        $query = "SELECT la.*, u.first_name, u.last_name, lt.type_name 
                                  FROM leave_applications la 
                                  JOIN users u ON la.user_id = u.user_id 
                                  JOIN leave_types lt ON la.leave_type_id = lt.type_id 
                                  WHERE la.application_id = ?";
                        $stmt = $conn->prepare($query);
                        $stmt->bind_param("i", $formId);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $application = $result->fetch_assoc();
                        closeDB($conn);
                        
                        if ($application):
                        ?>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <p><strong>Faculty:</strong> <?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?></p>
                                <p><strong>Leave Type:</strong> <?php echo htmlspecialchars($application['type_name']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>From Date:</strong> <?php echo !empty($application['from_date']) ? date('d-m-Y', strtotime($application['from_date'])) : 'N/A'; ?></p>
                                <p><strong>To Date:</strong> <?php echo !empty($application['to_date']) ? date('d-m-Y', strtotime($application['to_date'])) : 'N/A'; ?></p>
                                <p><strong>Total Days:</strong> <?php echo $application['total_days']; ?></p>
                            </div>
                        </div>
                        <div class="mb-3">
                            <p><strong>Reason:</strong> <?php echo htmlspecialchars($application['reason']); ?></p>
                        </div>
                        
                        <form action="pending_approvals.php" method="get">
                            <input type="hidden" name="action" value="confirm_<?php echo $formAction; ?>">
                            <input type="hidden" name="id" value="<?php echo $formId; ?>">
                            
                            <div class="form-group">
                                <label for="remarks"><?php echo ($formAction === 'approve') ? 'Comments (Optional):' : 'Reason for Rejection: <span class="text-danger">*</span>'; ?></label>
                                <textarea class="form-control" id="remarks" name="remarks" rows="3" <?php echo ($formAction === 'reject') ? 'required' : ''; ?>></textarea>
                            </div>
                            
                            <div class="form-group text-right">
                                <a href="notifications.php" class="btn btn-secondary">Cancel</a>
                                <button type="submit" class="btn btn-<?php echo ($formAction === 'approve') ? 'success' : 'danger'; ?>">
                                    <i class="fas fa-<?php echo ($formAction === 'approve') ? 'check' : 'times'; ?>"></i> 
                                    Confirm <?php echo ($formAction === 'approve') ? 'Approval' : 'Rejection'; ?>
                                </button>
                            </div>
                        </form>
                        <?php else: ?>
                        <div class="alert alert-danger">Application not found.</div>
                        <a href="notifications.php" class="btn btn-primary">Back to Notifications</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><?php echo $pageTitle; ?></h1>
                </div>
                <?php endif; ?>
                
                <main role="main" class="col-12">
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success; ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>
                
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">Pending Leave Applications</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($pendingApplications)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-clipboard-check fa-4x text-muted mb-3"></i>
                                <p class="lead text-muted">No pending leave applications found.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Faculty</th>
                                            <?php if ($userRole !== 'hod'): ?>
                                                <th>Department</th>
                                            <?php endif; ?>
                                            <th>Leave Type</th>
                                            <th>From</th>
                                            <th>To</th>
                                            <th>Days</th>
                                            <th>Status</th>
                                            <th>Applied On</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pendingApplications as $application): ?>
                                            <tr>
                                                <td><?php echo $application['application_id']; ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <i class="fas fa-user-circle fa-2x text-secondary mr-2"></i>
                                                        <div>
                                                            <?php echo $application['first_name'] . ' ' . $application['last_name']; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <?php if ($userRole !== 'hod'): ?>
                                                    <td><?php echo $application['dept_name']; ?></td>
                                                <?php endif; ?>
                                                <td><?php echo $application['type_name']; ?></td>
                                                <td><?php echo date('d-m-Y', strtotime($application['start_date'])); ?></td>
                                                <td><?php echo date('d-m-Y', strtotime($application['end_date'])); ?></td>
                                                <td><span class="badge badge-pill badge-info"><?php echo $application['total_days']; ?></span></td>
                                                <td>
                                                    <?php
                                                    if ($application['status'] == 'pending') {
                                                        echo '<span class="badge badge-warning text-dark">Pending</span>';
                                                    } elseif ($application['status'] == 'approved_by_hod') {
                                                        echo '<span class="badge badge-info">HOD Approved</span>';
                                                    } elseif ($application['status'] == 'approved') {
                                                        echo '<span class="badge badge-success">Approved</span>';
                                                    } elseif ($application['status'] == 'rejected') {
                                                        echo '<span class="badge badge-danger">Rejected</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td><?php echo date('d-m-Y', strtotime($application['application_date'])); ?></td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#applicationModal<?php echo $application['application_id']; ?>">
                                                        <i class="fas fa-eye mr-1"></i> Review
                                                    </button>
                                                    
                                                    <!-- Modal for reviewing application -->
                                                    <div class="modal fade" id="applicationModal<?php echo $application['application_id']; ?>" tabindex="-1" role="dialog" aria-labelledby="applicationModalLabel" aria-hidden="true">
                                                        <div class="modal-dialog modal-lg" role="document">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title" id="applicationModalLabel">Review Leave Application</h5>
                                                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                                        <span aria-hidden="true">&times;</span>
                                                                    </button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <div class="row">
                                                                        <div class="col-md-6">
                                                                            <div class="card mb-3">
                                                                                <div class="card-header bg-light">
                                                                                    <h6 class="mb-0">Application Details</h6>
                                                                                </div>
                                                                                <div class="card-body">
                                                                                    <p><strong><i class="fas fa-user mr-2"></i>Faculty:</strong> <?php echo $application['first_name'] . ' ' . $application['last_name']; ?></p>
                                                                                    <?php if ($userRole !== 'hod'): ?>
                                                                                        <p><strong><i class="fas fa-building mr-2"></i>Department:</strong> <?php echo $application['dept_name']; ?></p>
                                                                                    <?php endif; ?>
                                                                                    <p><strong><i class="fas fa-tag mr-2"></i>Leave Type:</strong> <?php echo $application['type_name']; ?></p>
                                                                                    <p><strong><i class="fas fa-calendar-alt mr-2"></i>Period:</strong> <?php echo date('d-m-Y', strtotime($application['start_date'])); ?> to <?php echo date('d-m-Y', strtotime($application['end_date'])); ?></p>
                                                                                    <p><strong><i class="fas fa-calendar-day mr-2"></i>Total Days:</strong> <?php echo $application['total_days']; ?></p>
                                                                                    <p><strong><i class="fas fa-clock mr-2"></i>Applied On:</strong> <?php echo date('d-m-Y', strtotime($application['application_date'])); ?></p>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-md-6">
                                                                            <div class="card mb-3">
                                                                                <div class="card-header bg-light">
                                                                                    <h6 class="mb-0">Reason for Leave</h6>
                                                                                </div>
                                                                                <div class="card-body">
                                                                                    <p><?php echo nl2br(htmlspecialchars($application['reason'])); ?></p>
                                                                                    <?php if (!empty($application['document_path'])): ?>
                                                                                        <p class="mt-3">
                                                                                            <a href="<?php echo $application['document_path']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                                                                <i class="fas fa-file-alt mr-1"></i> View Attached Document
                                                                                            </a>
                                                                                        </p>
                                                                                    <?php endif; ?>
                                                                                </div>
                                                                            </div>
                                                                            
                                                                            <?php 
                                                                            // Get faculty leave balances
                                                                            $facultyLeaveBalances = getUserLeaveBalances($application['user_id']);
                                                                            ?>
                                                                            <div class="card mt-3">
                                                                                <div class="card-header bg-light">
                                                                                    <h6 class="mb-0"><i class="fas fa-balance-scale mr-2"></i>Leave Balance Details</h6>
                                                                                </div>
                                                                                <div class="card-body p-0">
                                                                                    <div class="table-responsive">
                                                                                        <table class="table table-sm table-bordered mb-0">
                                                                                            <thead class="thead-light">
                                                                                                <tr>
                                                                                                    <th>Leave Type</th>
                                                                                                    <th>Total</th>
                                                                                                    <th>Used</th>
                                                                                                    <th>Remaining</th>
                                                                                                    <th>Status</th>
                                                                                                </tr>
                                                                                            </thead>
                                                                                            <tbody>
                                                                                                <?php 
                                                                                                // Only show the current leave type
                                                                                                foreach ($facultyLeaveBalances as $balance): 
                                                                                                    $isCurrentLeaveType = ($balance['type_id'] == $application['leave_type_id']);
                                                                                                    // Only display the row if it's the current leave type
                                                                                                    if ($isCurrentLeaveType):
                                                                                                ?>
                                                                                                <tr class="table-primary">
                                                                                                    <td>
                                                                                                        <strong><?php echo htmlspecialchars($balance['type_name']); ?></strong>
                                                                                                    </td>
                                                                                                    <td><?php echo isset($balance['total_days']) ? $balance['total_days'] : 0; ?></td>
                                                                                                    <td><?php echo isset($balance['used_days']) ? $balance['used_days'] : 0; ?></td>
                                                                                                    <td>
                                                                                                        <span class="badge <?php echo (isset($balance['remaining_days']) && $balance['remaining_days'] > 0) ? 'badge-success' : 'badge-danger'; ?>">
                                                                                                            <?php echo isset($balance['remaining_days']) ? $balance['remaining_days'] : 0; ?>
                                                                                                        </span>
                                                                                                    </td>
                                                                                                    <td>
                                                                                                        <?php 
                                                                                                        $remaining = isset($balance['remaining_days']) ? $balance['remaining_days'] : 0;
                                                                                                        $requested = $application['total_days'];
                                                                                                        
                                                                                                        if ($remaining >= $requested): ?>
                                                                                                            <span class="badge badge-success">Sufficient Balance</span>
                                                                                                        <?php else: ?>
                                                                                                            <span class="badge badge-danger">Insufficient Balance</span>
                                                                                                        <?php endif; ?>
                                                                                                    </td>
                                                                                                </tr>
                                                                                                <?php endif; endforeach; ?>
                                                                                            </tbody>
                                                                                        </table>
                                                                                    </div>
                                                                                    <div class="p-2 text-right">
                                                                                        <button type="button" class="btn btn-sm btn-outline-info" data-toggle="modal" data-target="#allLeavesModal<?php echo $application['application_id']; ?>">
                                                                                            <i class="fas fa-list mr-1"></i> View All Leave Balances
                                                                                        </button>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                            
                                                                            <?php if (isset($applicationAdjustments[$application['application_id']]) && !empty($applicationAdjustments[$application['application_id']])): ?>
                                                                        <div class="card mb-3">
                                                                            <div class="card-header bg-light">
                                                                                <h6 class="mb-0">Class Adjustments</h6>
                                                                            </div>
                                                                            <div class="card-body p-0">
                                                                                <div class="table-responsive">
                                                                                    <table class="table table-sm table-bordered mb-0">
                                                                                        <thead class="thead-light">
                                                                                            <tr>
                                                                                                <th>Date</th>
                                                                                                <th>Time</th>
                                                                                                <th>Subject</th>
                                                                                                <th>Status</th>
                                                                                                <th>Adjusted By</th>
                                                                                            </tr>
                                                                                        </thead>
                                                                                        <tbody>
                                                                                            <?php foreach ($applicationAdjustments[$application['application_id']] as $adjustment): ?>
                                                                                            <tr>
                                                                                                <td><?php echo date('d-m-Y', strtotime($adjustment['class_date'])); ?></td>
                                                                                                <td><?php echo htmlspecialchars($adjustment['class_time'] ?? 'N/A'); ?></td>
                                                                                                <td>
                                                                                                    <?php 
                                                                                                    if (!empty($adjustment['subject'])) {
                                                                                                        echo htmlspecialchars($adjustment['subject']);
                                                                                                    } else {
                                                                                                        echo '<span class="text-muted">No subject</span>';
                                                                                                    }
                                                                                                    ?>
                                                                                                </td>
                                                                                                <td>
                                                                                                    <?php
                                                                                                    $status = $adjustment['status'] ?? 'pending';
                                                                                                    switch ($status) {
                                                                                                        case 'pending':
                                                                                                            echo '<span class="badge badge-warning">Pending</span>';
                                                                                                            break;
                                                                                                        case 'accepted':
                                                                                                            echo '<span class="badge badge-success">Accepted</span>';
                                                                                                            break;
                                                                                                        case 'rejected':
                                                                                                            echo '<span class="badge badge-danger">Rejected</span>';
                                                                                                            break;
                                                                                                        default:
                                                                                                            echo '<span class="badge badge-secondary">' . htmlspecialchars($status) . '</span>';
                                                                                                    }
                                                                                                    ?>
                                                                                                </td>
                                                                                                <td><?php echo htmlspecialchars(($adjustment['first_name'] ?? '') . ' ' . ($adjustment['last_name'] ?? '')); ?></td>
                                                                                            </tr>
                                                                                            <?php endforeach; ?>
                                                                                        </tbody>
                                                                                    </table>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                    
                                                                    <hr>
                                                                    <form method="post" action="">
                                                                        <input type="hidden" name="application_id" value="<?php echo $application['application_id']; ?>">
                                                                        
                                                                        <div class="card mb-3">
                                                                            <div class="card-header bg-light">
                                                                                <h6 class="mb-0">Your Decision</h6>
                                                                            </div>
                                                                            <div class="card-body">
                                                                                <div class="form-group">
                                                                                    <label for="status<?php echo $application['application_id']; ?>">Decision:</label>
                                                                                    <div class="btn-group btn-group-toggle w-100" data-toggle="buttons">
                                                                                        <label class="btn btn-outline-success">
                                                                                            <input type="radio" name="status" id="approve<?php echo $application['application_id']; ?>" value="approved" required> 
                                                                                            <i class="fas fa-check-circle mr-1"></i> Approve
                                                                                        </label>
                                                                                        <label class="btn btn-outline-danger">
                                                                                            <input type="radio" name="status" id="reject<?php echo $application['application_id']; ?>" value="rejected"> 
                                                                                            <i class="fas fa-times-circle mr-1"></i> Reject
                                                                                        </label>
                                                                                    </div>
                                                                                </div>
                                                                                
                                                                                <div class="form-group">
                                                                                    <label for="remarks<?php echo $application['application_id']; ?>">Remarks:</label>
                                                                                    <textarea class="form-control" name="remarks" id="remarks<?php echo $application['application_id']; ?>" rows="3" required></textarea>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        
                                                                        <div class="text-right">
                                                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">
                                                                                <i class="fas fa-times mr-1"></i> Close
                                                                            </button>
                                                                            <button type="submit" name="update_application" class="btn btn-primary">
                                                                                <i class="fas fa-paper-plane mr-1"></i> Submit Decision
                                                                            </button>
                                                                        </div>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                </main>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.25/js/jquery.dataTables.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize DataTables only if it hasn't been initialized
            if ($.fn.DataTable.isDataTable('#leaveApplicationsTable')) {
                $('#leaveApplicationsTable').DataTable().destroy();
            }
            
            $('#leaveApplicationsTable').DataTable({
                "order": [[0, "desc"]], // Order by application_id DESC
                "pageLength": 10,
                "language": {
                    "emptyTable": "No pending leave applications found"
                }
            });
        });
    </script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
