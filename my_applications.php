<?php
require_once 'config/config.php';

// Require login to access this page
requireLogin();

// Get user's leave applications
function getUserApplications($userId) {
    $conn = connectDB();
    
    $query = "SELECT la.application_id, lt.type_name, la.start_date, la.end_date, 
              la.total_days, la.reason, la.status, la.application_date, la.document_path
              FROM leave_applications la
              JOIN leave_types lt ON la.leave_type_id = lt.type_id
              WHERE la.user_id = ?
              ORDER BY la.application_date DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $userId);
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
    
    $query = "SELECT ca.adjustment_id, ca.class_date, ca.class_time, ca.subject, ca.status, 
              ca.remarks, u.first_name, u.last_name
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

// Function to cancel leave application
function cancelLeaveApplication($applicationId, $userId) {
    $conn = connectDB();
    if (!$conn) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }

    try {
        $conn->begin_transaction();

        // Check if the application belongs to the user and is in a cancellable state
        $query = "SELECT la.*, lt.type_name 
                  FROM leave_applications la
                  JOIN leave_types lt ON la.leave_type_id = lt.type_id
                  WHERE la.application_id = ? AND la.user_id = ? 
                  AND la.status IN ('pending', 'approved_by_hod')";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $applicationId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception('Unable to cancel this leave application. It may be already approved or does not belong to you.');
        }

        $application = $result->fetch_assoc();
        
        // Update application status to cancelled
        $updateQuery = "UPDATE leave_applications SET status = 'cancelled' WHERE application_id = ?";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param("i", $applicationId);
        if (!$updateStmt->execute()) {
            throw new Exception('Failed to update leave status.');
        }

        // Add notification for HOD
        $hodQuery = "SELECT user_id FROM users 
                     WHERE role_id = (SELECT role_id FROM roles WHERE role_name = 'hod') 
                     AND dept_id = (SELECT dept_id FROM users WHERE user_id = ?)";
        $hodStmt = $conn->prepare($hodQuery);
        $hodStmt->bind_param("i", $userId);
        $hodStmt->execute();
        $hodResult = $hodStmt->get_result();
        
        if ($hodRow = $hodResult->fetch_assoc()) {
            $notificationQuery = "INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)";
            $notificationStmt = $conn->prepare($notificationQuery);
            
            $title = "Leave Application Cancelled";
            $message = "Leave application #$applicationId has been cancelled by the faculty member.";
            
            $notificationStmt->bind_param("iss", $hodRow['user_id'], $title, $message);
            $notificationStmt->execute();
        }

        // Try to add to leave history if the table exists
        try {
            // Check if leave_history table exists
            $tableCheckQuery = "SHOW TABLES LIKE 'leave_history'";
            $tableExists = $conn->query($tableCheckQuery)->num_rows > 0;
            
            if ($tableExists) {
                $historyQuery = "INSERT INTO leave_history (application_id, status_from, status_to, remarks, updated_by) 
                                VALUES (?, ?, 'cancelled', 'Cancelled by faculty', ?)";
                $historyStmt = $conn->prepare($historyQuery);
                $historyStmt->bind_param("isi", $applicationId, $application['status'], $userId);
                $historyStmt->execute();
            }
        } catch (Exception $e) {
            // Log the error but don't stop the cancellation process
            error_log("Failed to update leave history: " . $e->getMessage());
        }

        $conn->commit();
        return ['success' => true, 'message' => 'Leave application cancelled successfully.'];
    } catch (Exception $e) {
        $conn->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    } finally {
        closeDB($conn);
    }
}

// Handle cancellation request
if (isset($_GET['cancel']) && is_numeric($_GET['cancel'])) {
    $applicationId = intval($_GET['cancel']);
    $userId = $_SESSION['user_id'];
    
    $result = cancelLeaveApplication($applicationId, $userId);
    if ($result['success']) {
        setFlashMessage('success', $result['message']);
    } else {
        setFlashMessage('danger', $result['message']);
    }
    
    // Redirect to prevent resubmission
    redirect(BASE_URL . 'my_applications.php');
}

// Get user's applications
$userId = $_SESSION['user_id'];
$applications = getUserApplications($userId);

// Get class adjustments for applications
$applicationAdjustments = [];
foreach ($applications as $application) {
    if (strpos($application['type_name'], 'casual_leave') !== false) {
        $applicationAdjustments[$application['application_id']] = getClassAdjustments($application['application_id']);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Leave Applications - <?php echo APP_TITLE; ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables.net-bs4/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ml-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">My Leave Applications</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group mr-2">
                            <a href="apply_leave.php" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-plus"></i> Apply for Leave
                            </a>
                        </div>
                    </div>
                </div>
                
                <?php
                // Display flash message if any
                $flash = getFlashMessage();
                if ($flash) {
                    echo '<div class="alert alert-' . $flash['type'] . ' alert-dismissible fade show" role="alert">';
                    echo $flash['message'];
                    echo '<button type="button" class="close" data-dismiss="alert" aria-label="Close">';
                    echo '<span aria-hidden="true">&times;</span>';
                    echo '</button>';
                    echo '</div>';
                }
                ?>
                
                <div class="card shadow-sm">
                    <div class="card-body">
                        <?php if (empty($applications)): ?>
                            <div class="alert alert-info">
                                You have not applied for any leave yet. <a href="apply_leave.php">Apply for leave</a> now.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover data-table">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Leave Type</th>
                                            <th>From</th>
                                            <th>To</th>
                                            <th>Days</th>
                                            <th>Applied On</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($applications as $application): ?>
                                            <tr>
                                                <td><?php echo $application['application_id']; ?></td>
                                                <td><?php echo ucwords(str_replace('_', ' ', $application['type_name'])); ?></td>
                                                <td><?php echo date('d-m-Y', strtotime($application['start_date'])); ?></td>
                                                <td><?php echo date('d-m-Y', strtotime($application['end_date'])); ?></td>
                                                <td><?php echo $application['total_days']; ?></td>
                                                <td><?php echo date('d-m-Y', strtotime($application['application_date'])); ?></td>
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
                                                <td>
                                                    <?php if (in_array($application['status'], ['pending', 'approved_by_hod'])): ?>
                                                        <a href="#" class="btn btn-sm btn-danger" 
                                                           onclick="confirmCancel(<?php echo $application['application_id']; ?>)">
                                                            <i class="fas fa-times"></i> Cancel
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            
                                            <?php if (strpos($application['type_name'], 'casual_leave') !== false && isset($applicationAdjustments[$application['application_id']]) && !empty($applicationAdjustments[$application['application_id']])): ?>
                                                <tr>
                                                    <td colspan="7" class="bg-light">
                                                        <div class="ml-4">
                                                            <h6 class="mb-2">Class Adjustments</h6>
                                                            <table class="table table-sm table-bordered">
                                                                <thead>
                                                                    <tr>
                                                                        <th>Date</th>
                                                                        <th>Time</th>
                                                                        <th>Subject</th>
                                                                        <th>Adjusted By</th>
                                                                        <th>Status</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    <?php foreach ($applicationAdjustments[$application['application_id']] as $adjustment): ?>
                                                                        <tr>
                                                                            <td><?php echo date('d-m-Y', strtotime($adjustment['class_date'])); ?></td>
                                                                            <td><?php echo $adjustment['class_time']; ?></td>
                                                                            <td><?php echo $adjustment['subject']; ?></td>
                                                                            <td><?php echo $adjustment['first_name'] . ' ' . $adjustment['last_name']; ?></td>
                                                                            <td>
                                                                                <?php
                                                                                switch ($adjustment['status']) {
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
                                                                        </tr>
                                                                    <?php endforeach; ?>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
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
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/datatables.net/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/datatables.net-bs4/js/dataTables.bootstrap4.min.js"></script>
    <script src="assets/js/script.js"></script>
    
    <script>
        $(document).ready(function() {
            // Initialize DataTable
            $('.data-table').DataTable({
                "order": [[0, "desc"]], // Sort by ID (newest first)
                "pageLength": 10,
                "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]],
                "columnDefs": [
                    { "orderable": false, "targets": 6 } // Disable sorting on Status column
                ]
            });
        });

        function confirmCancel(applicationId) {
            if (confirm('Are you sure you want to cancel this leave application?')) {
                window.location.href = '<?php echo BASE_URL; ?>my_applications.php?cancel=' + applicationId;
            }
        }
    </script>
</body>
</html>
