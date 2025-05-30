<?php
require_once 'config/config.php';

// Require login to access this page
requireLogin();

// Check if application ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setFlashMessage('danger', 'Invalid application ID.');
    redirect(BASE_URL . 'my_applications.php');
}

$applicationId = intval($_GET['id']);
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];

// Get application details
function getApplicationDetails($applicationId, $userId, $userRole) {
    $conn = connectDB();
    
    // Different queries based on user role
    if (in_array($userRole, ['hod', 'central_admin', 'admin'])) {
        // Admins, HODs, and central admins can view all applications
        $query = "SELECT la.*, lt.type_name, lt.description as leave_type_description,
                  u.first_name, u.last_name, u.email, u.phone, d.dept_name
                  FROM leave_applications la
                  JOIN leave_types lt ON la.leave_type_id = lt.type_id
                  JOIN users u ON la.user_id = u.user_id
                  JOIN departments d ON u.dept_id = d.dept_id
                  WHERE la.application_id = ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $applicationId);
    } else {
        // Regular faculty can only view their own applications
        $query = "SELECT la.*, lt.type_name, lt.description as leave_type_description,
                  u.first_name, u.last_name, u.email, u.phone, d.dept_name
                  FROM leave_applications la
                  JOIN leave_types lt ON la.leave_type_id = lt.type_id
                  JOIN users u ON la.user_id = u.user_id
                  JOIN departments d ON u.dept_id = d.dept_id
                  WHERE la.application_id = ? AND la.user_id = ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $applicationId, $userId);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return null;
    }
    
    $application = $result->fetch_assoc();
    $stmt->close();
    closeDB($conn);
    
    return $application;
}

// Get class adjustments for a leave application
function getClassAdjustments($applicationId) {
    $conn = connectDB();
    if (!$conn) {
        return [];
    }
    
    $query = "SELECT ca.adjustment_id, ca.class_date, ca.class_details, ca.adjustment_status, 
              ca.remarks, u.first_name, u.last_name, u.email
              FROM class_adjustments ca
              JOIN users u ON ca.adjusted_by = u.user_id
              WHERE ca.application_id = ?
              ORDER BY ca.class_date";
    
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

// Get leave history
function getLeaveHistory($applicationId) {
    $conn = connectDB();
    if (!$conn) {
        return [];
    }
    
    // Use leave_applications table instead of leave_history
    $query = "SELECT la.application_id, la.status, la.last_updated as updated_at, 
              CASE 
                WHEN la.hod_approval = 'approved' THEN CONCAT('Approved by HOD: ', la.hod_remarks)
                WHEN la.hod_approval = 'rejected' THEN CONCAT('Rejected by HOD: ', la.hod_remarks)
                WHEN la.admin_approval = 'approved' THEN CONCAT('Approved by Admin: ', la.admin_remarks)
                WHEN la.admin_approval = 'rejected' THEN CONCAT('Rejected by Admin: ', la.admin_remarks)
                ELSE 'Status updated'
              END as action_details,
              COALESCE(h.user_id, a.user_id, la.user_id) as updated_by,
              u.first_name, u.last_name
              FROM leave_applications la
              LEFT JOIN users u ON la.user_id = u.user_id
              LEFT JOIN users h ON la.hod_id = h.user_id
              LEFT JOIN users a ON la.admin_id = a.user_id
              WHERE la.application_id = ?
              ORDER BY la.last_updated DESC";
    
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
    
    $history = [];
    while ($row = $result->fetch_assoc()) {
        $history[] = $row;
    }
    
    $stmt->close();
    closeDB($conn);
    
    return $history;
}

// Get application details
$application = getApplicationDetails($applicationId, $userId, $userRole);

// Check if application exists and user has permission to view it
if (!$application) {
    setFlashMessage('danger', 'Application not found or you do not have permission to view it.');
    redirect(BASE_URL . 'my_applications.php');
}

// Get class adjustments if it's a casual leave
$classAdjustments = [];
if (strpos($application['type_name'], 'casual_leave') !== false) {
    $classAdjustments = getClassAdjustments($applicationId);
}

// Get leave history
$leaveHistory = getLeaveHistory($applicationId);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Leave Application - <?php echo APP_TITLE; ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ml-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Leave Application Details</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group mr-2">
                            <a href="my_applications.php" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Applications
                            </a>
                            <button class="btn btn-sm btn-outline-primary print-btn">
                                <i class="fas fa-print"></i> Print
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">Application #<?php echo $application['application_id']; ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-muted">Basic Information</h6>
                                <table class="table table-sm">
                                    <tr>
                                        <th width="35%">Application ID</th>
                                        <td><?php echo $application['application_id']; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Status</th>
                                        <td>
                                            <?php
                                            switch ($application['status']) {
                                                case 'pending':
                                                    echo '<span class="badge badge-warning">Pending</span>';
                                                    break;
                                                case 'approved_by_hod':
                                                    echo '<span class="badge badge-info">Approved by HOD</span>';
                                                    break;
                                                case 'approved_by_central_admin':
                                                    echo '<span class="badge badge-info">Approved by Office</span>';
                                                    break;
                                                case 'approved':
                                                    echo '<span class="badge badge-success">Approved</span>';
                                                    break;
                                                case 'rejected':
                                                    echo '<span class="badge badge-danger">Rejected</span>';
                                                    break;
                                                case 'cancelled':
                                                    echo '<span class="badge badge-secondary">Cancelled</span>';
                                                    break;
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Applied On</th>
                                        <td><?php echo date('d-m-Y H:i', strtotime($application['application_date'])); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Faculty Name</th>
                                        <td><?php echo $application['first_name'] . ' ' . $application['last_name']; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Department</th>
                                        <td><?php echo $application['dept_name']; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Contact</th>
                                        <td>
                                            Email: <?php echo $application['email']; ?><br>
                                            Phone: <?php echo $application['phone']; ?>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            
                            <div class="col-md-6">
                                <h6 class="text-muted">Leave Details</h6>
                                <table class="table table-sm">
                                    <tr>
                                        <th width="35%">Leave Type</th>
                                        <td><?php echo ucwords(str_replace('_', ' ', $application['type_name'])); ?></td>
                                    </tr>
                                    <tr>
                                        <th>From Date</th>
                                        <td><?php echo date('d-m-Y', strtotime($application['start_date'])); ?></td>
                                    </tr>
                                    <tr>
                                        <th>To Date</th>
                                        <td><?php echo date('d-m-Y', strtotime($application['end_date'])); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Total Days</th>
                                        <td><?php echo $application['total_days']; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Reason</th>
                                        <td><?php echo $application['reason']; ?></td>
                                    </tr>
                                    <?php if (!empty($application['documents'])): ?>
                                    <tr>
                                        <th>Documents</th>
                                        <td>
                                            <a href="<?php echo $application['documents']; ?>" target="_blank" class="btn btn-sm btn-outline-info">
                                                <i class="fas fa-file-alt"></i> View Document
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                        </div>
                        
                        <?php if (!empty($classAdjustments)): ?>
                        <div class="row mt-4">
                            <div class="col-12">
                                <h6 class="text-muted">Class Adjustments</h6>
                                <table class="table table-sm table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Class Details</th>
                                            <th>Adjusted By</th>
                                            <th>Status</th>
                                            <th>Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($classAdjustments as $adjustment): ?>
                                            <tr>
                                                <td><?php echo date('d-m-Y', strtotime($adjustment['class_date'])); ?></td>
                                                <td><?php echo $adjustment['class_details']; ?></td>
                                                <td>
                                                    <?php echo $adjustment['first_name'] . ' ' . $adjustment['last_name']; ?><br>
                                                    <small class="text-muted"><?php echo $adjustment['email']; ?></small>
                                                </td>
                                                <td>
                                                    <?php
                                                    switch ($adjustment['adjustment_status']) {
                                                        case 'pending':
                                                            echo '<span class="badge badge-warning">Pending</span>';
                                                            break;
                                                        case 'approved':
                                                            echo '<span class="badge badge-success">Approved</span>';
                                                            break;
                                                        case 'rejected':
                                                            echo '<span class="badge badge-danger">Rejected</span>';
                                                            break;
                                                    }
                                                    ?>
                                                </td>
                                                <td><?php echo $adjustment['remarks']; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($leaveHistory)): ?>
                        <div class="row mt-4">
                            <div class="col-12">
                                <h6 class="text-muted">Application History</h6>
                                <table class="table table-sm table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Date & Time</th>
                                            <th>Status Change</th>
                                            <th>Updated By</th>
                                            <th>Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($leaveHistory as $history): ?>
                                            <tr>
                                                <td><?php echo !empty($history['updated_at']) ? date('d-m-Y H:i', strtotime($history['updated_at'])) : 'N/A'; ?></td>
                                                <td>
                                                    <?php echo ucwords(str_replace('_', ' ', $history['status'])); ?>
                                                </td>
                                                <td><?php echo $history['first_name'] . ' ' . $history['last_name']; ?></td>
                                                <td><?php echo $history['action_details']; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Approval/Rejection Remarks -->
                        <?php if (!empty($application['hod_remarks']) || !empty($application['central_admin_remarks']) || !empty($application['admin_remarks'])): ?>
                        <div class="row mt-4">
                            <div class="col-12">
                                <h6 class="text-muted">Approval/Rejection Remarks</h6>
                                <table class="table table-sm table-bordered">
                                    <?php if (!empty($application['hod_remarks'])): ?>
                                    <tr>
                                        <th width="20%">HOD Remarks</th>
                                        <td><?php echo $application['hod_remarks']; ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($application['central_admin_remarks'])): ?>
                                    <tr>
                                        <th>Office Remarks</th>
                                        <td><?php echo $application['central_admin_remarks']; ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($application['admin_remarks'])): ?>
                                    <tr>
                                        <th>Admin Remarks</th>
                                        <td><?php echo $application['admin_remarks']; ?></td>
                                    </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Action Buttons -->
                        <div class="row mt-4">
                            <div class="col-12 text-center">
                                <?php if ($application['user_id'] == $userId && ($application['status'] === 'pending' || $application['status'] === 'approved_by_hod')): ?>
                                    <a href="my_applications.php?cancel=<?php echo $application['application_id']; ?>" class="btn btn-danger confirm-action" data-confirm="Are you sure you want to cancel this leave application?">
                                        <i class="fas fa-times"></i> Cancel Application
                                    </a>
                                <?php endif; ?>
                                
                                <?php if ($userRole === 'hod' && $application['status'] === 'pending'): ?>
                                    <a href="review_application.php?id=<?php echo $application['application_id']; ?>" class="btn btn-primary">
                                        <i class="fas fa-check-circle"></i> Review Application
                                    </a>
                                <?php endif; ?>
                                
                                <?php if ($userRole === 'central_admin' && $application['status'] === 'approved_by_hod'): ?>
                                    <a href="review_application.php?id=<?php echo $application['application_id']; ?>" class="btn btn-primary">
                                        <i class="fas fa-check-circle"></i> Review Application
                                    </a>
                                <?php endif; ?>
                                
                                <?php if ($userRole === 'admin' && ($application['status'] === 'approved_by_central_admin' || $application['status'] === 'approved_by_hod')): ?>
                                    <a href="review_application.php?id=<?php echo $application['application_id']; ?>" class="btn btn-primary">
                                        <i class="fas fa-check-circle"></i> Review Application
                                    </a>
                                <?php endif; ?>
                                
                                <a href="my_applications.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Applications
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="assets/js/script.js"></script>
    
    <script>
        $(document).ready(function() {
            // Print functionality
            $('.print-btn').on('click', function() {
                window.print();
                return false;
            });
            
            // Confirmation dialog for cancel action
            $('.confirm-action').on('click', function(e) {
                e.preventDefault();
                const message = $(this).data('confirm');
                const href = $(this).attr('href');
                
                if (confirm(message)) {
                    window.location.href = href;
                }
            });
        });
    </script>
</body>
</html>
