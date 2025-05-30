<?php
require_once 'config/config.php';

// Require login to access this page
requireLogin();

// Get user's leave balances
function getUserLeaveBalances($userId) {
    $conn = connectDB();
    $currentYear = date('Y');
    
    $query = "SELECT lt.type_name, lt.description, lb.total_days, lb.used_days, (lb.total_days - lb.used_days) as remaining_days
              FROM leave_balances lb
              JOIN leave_types lt ON lb.leave_type_id = lt.type_id
              WHERE lb.user_id = ? AND lb.year = ?
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

// Get pending leave applications for approval (for HOD, Central Admin, Admin)
function getPendingApplications() {
    $conn = connectDB();
    $userRole = $_SESSION['role'];
    $deptId = $_SESSION['dept_id'];
    
    $query = "";
    
    if ($userRole === 'hod') {
        // HODs see pending applications from their department
        $query = "SELECT la.application_id, u.first_name, u.last_name, lt.type_name, 
                  la.start_date, la.end_date, la.total_days, la.status, la.application_date
                  FROM leave_applications la
                  JOIN users u ON la.user_id = u.user_id
                  JOIN leave_types lt ON la.leave_type_id = lt.type_id
                  WHERE u.dept_id = ? AND la.status = 'pending'
                  ORDER BY la.application_date DESC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $deptId);
    } elseif ($userRole === 'central_admin') {
        // Central admin sees applications approved by HOD that require central admin approval
        $query = "SELECT la.application_id, u.first_name, u.last_name, lt.type_name, 
                  la.start_date, la.end_date, la.total_days, la.status, la.application_date,
                  d.dept_name
                  FROM leave_applications la
                  JOIN users u ON la.user_id = u.user_id
                  JOIN leave_types lt ON la.leave_type_id = lt.type_id
                  JOIN departments d ON u.dept_id = d.dept_id
                  WHERE la.status = 'approved_by_hod' 
                  ORDER BY la.application_date DESC";
        $stmt = $conn->prepare($query);
    } elseif ($userRole === 'admin') {
        // Admin sees applications approved by central admin or those requiring direct admin approval
        $query = "SELECT la.application_id, u.first_name, u.last_name, lt.type_name, 
                  la.start_date, la.end_date, la.total_days, la.status, la.application_date,
                  d.dept_name
                  FROM leave_applications la
                  JOIN users u ON la.user_id = u.user_id
                  JOIN leave_types lt ON la.leave_type_id = lt.type_id
                  JOIN departments d ON u.dept_id = d.dept_id
                  WHERE la.status = 'approved_by_hod'
                  ORDER BY la.application_date DESC";
        $stmt = $conn->prepare($query);
    }
    
    if (!empty($query)) {
        $stmt->execute();
        $result = $stmt->get_result();
        
        $applications = [];
        while ($row = $result->fetch_assoc()) {
            $applications[] = $row;
        }
        
        $stmt->close();
    } else {
        $applications = [];
    }
    
    closeDB($conn);
    
    return $applications;
}

// Get user's recent leave applications
function getUserApplications($userId) {
    $conn = connectDB();
    
    $query = "SELECT la.application_id, lt.type_name, la.start_date, la.end_date, 
              la.total_days, la.status, la.application_date
              FROM leave_applications la
              JOIN leave_types lt ON la.leave_type_id = lt.type_id
              WHERE la.user_id = ?
              ORDER BY la.application_date DESC
              LIMIT 10";
    
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

// Get user's notifications
function getUserNotifications($userId) {
    $conn = connectDB();
    
    $query = "SELECT notification_id, title, message, is_read, created_at
              FROM notifications
              WHERE user_id = ?
              ORDER BY created_at DESC
              LIMIT 5";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    
    $stmt->close();
    closeDB($conn);
    
    return $notifications;
}

// Function to get faculty members for HOD dashboard
function getDepartmentFaculty($deptId) {
    $conn = connectDB();
    
    $query = "SELECT u.user_id, u.first_name, u.last_name, u.email, u.phone, u.date_joined, u.status,
              COUNT(CASE WHEN la.status = 'pending' THEN 1 ELSE NULL END) as pending_leaves,
              COUNT(CASE WHEN la.status = 'approved' THEN 1 ELSE NULL END) as approved_leaves,
              SUM(CASE WHEN la.status = 'approved' THEN la.total_days ELSE 0 END) as spent_leave_days
              FROM users u
              LEFT JOIN leave_applications la ON u.user_id = la.user_id
              JOIN roles r ON u.role_id = r.role_id
              WHERE u.dept_id = ? AND r.role_name = 'faculty'
              GROUP BY u.user_id
              ORDER BY u.first_name, u.last_name";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $deptId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $faculty = [];
    while ($row = $result->fetch_assoc()) {
        $faculty[] = $row;
    }
    
    $stmt->close();
    closeDB($conn);
    
    return $faculty;
}

// Function to get faculty leave details
function getFacultyLeaveDetails($userId) {
    $conn = connectDB();
    $currentYear = date('Y');
    
    // Get leave balances
    $balanceQuery = "SELECT lt.type_name, lb.total_days, lb.used_days, (lb.total_days - lb.used_days) as remaining_days
                     FROM leave_balances lb
                     JOIN leave_types lt ON lb.leave_type_id = lt.type_id
                     WHERE lb.user_id = ? AND lb.year = ?
                     ORDER BY lt.type_name";
    
    $balanceStmt = $conn->prepare($balanceQuery);
    $balanceStmt->bind_param("ii", $userId, $currentYear);
    $balanceStmt->execute();
    $balanceResult = $balanceStmt->get_result();
    
    $balances = [];
    while ($row = $balanceResult->fetch_assoc()) {
        $balances[] = $row;
    }
    
    // Get recent applications
    $appQuery = "SELECT la.application_id, lt.type_name, la.start_date, la.end_date, 
                 la.total_days, la.status, la.application_date
                 FROM leave_applications la
                 JOIN leave_types lt ON la.leave_type_id = lt.type_id
                 WHERE la.user_id = ?
                 ORDER BY la.application_date DESC
                 LIMIT 5";
    
    $appStmt = $conn->prepare($appQuery);
    $appStmt->bind_param("i", $userId);
    $appStmt->execute();
    $appResult = $appStmt->get_result();
    
    $applications = [];
    while ($row = $appResult->fetch_assoc()) {
        $applications[] = $row;
    }
    
    $balanceStmt->close();
    $appStmt->close();
    closeDB($conn);
    
    return [
        'balances' => $balances,
        'applications' => $applications
    ];
}

// Get data for the dashboard
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];
$leaveBalances = getUserLeaveBalances($userId);
$userApplications = getUserApplications($userId);
$notifications = getUserNotifications($userId);

// Get pending applications for approval (for HOD, Central Admin, Admin)
$pendingApplications = [];
if (in_array($userRole, ['hod', 'central_admin', 'admin'])) {
    $pendingApplications = getPendingApplications();
}

// Get faculty members for HOD dashboard
$departmentFaculty = [];
if ($userRole === 'hod') {
    $departmentFaculty = getDepartmentFaculty($_SESSION['dept_id']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo APP_TITLE; ?></title>
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
                    <h1 class="h2">Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group mr-2">
                            <?php if ($userRole === 'faculty'): ?>
                            <a href="apply_leave.php" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-plus"></i> Apply for Leave
                            </a>
                            <?php endif; ?>
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
                
                <div class="row">
                    <!-- Leave Balance Summary (Only for Faculty) -->
                    <?php if ($userRole === 'faculty'): ?>
                    <div class="col-md-6 mb-4">
                        <div class="card shadow-sm">
                            <div class="card-header bg-primary text-white">
                                <h5 class="card-title mb-0">Leave Balance</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($leaveBalances)): ?>
                                    <p class="text-muted">No leave balances found for the current year.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Leave Type</th>
                                                    <th>Total</th>
                                                    <th>Used</th>
                                                    <th>Remaining</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($leaveBalances as $balance): ?>
                                                    <tr>
                                                        <td><?php echo ucwords(str_replace('_', ' ', $balance['type_name'])); ?></td>
                                                        <td><?php echo $balance['total_days']; ?></td>
                                                        <td><?php echo $balance['used_days']; ?></td>
                                                        <td>
                                                            <?php if ($balance['remaining_days'] <= 0): ?>
                                                                <span class="text-danger"><?php echo $balance['remaining_days']; ?></span>
                                                            <?php else: ?>
                                                                <span class="text-success"><?php echo $balance['remaining_days']; ?></span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <a href="leave_balance.php" class="btn btn-sm btn-outline-primary mt-2">View Full Leave Balance</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Recent Applications -->
                    <div class="col-md-6 mb-4">
                        <div class="card shadow-sm">
                            <div class="card-header bg-info text-white">
                                <h5 class="card-title mb-0">Recent Leave Applications</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($userApplications)): ?>
                                    <p class="text-muted">No recent leave applications found.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Type</th>
                                                    <th>From</th>
                                                    <th>To</th>
                                                    <th>Days</th>
                                                    <th>Status</th>
                                                    <th></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($userApplications as $application): ?>
                                                    <tr>
                                                        <td><?php echo ucwords(str_replace('_', ' ', $application['type_name'])); ?></td>
                                                        <td><?php echo date('d-m-Y', strtotime($application['start_date'])); ?></td>
                                                        <td><?php echo date('d-m-Y', strtotime($application['end_date'])); ?></td>
                                                        <td><?php echo $application['total_days']; ?></td>
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
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <a href="my_applications.php" class="btn btn-sm btn-outline-info mt-2">View All Applications</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($pendingApplications)): ?>
                <!-- Pending Approvals (for HOD, Central Admin, Admin) -->
                <div class="row">
                    <div class="col-12 mb-4">
                        <div class="card shadow-sm">
                            <div class="card-header bg-warning text-dark">
                                <h5 class="card-title mb-0">Pending Approvals</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Faculty</th>
                                                <?php if (in_array($userRole, ['central_admin', 'admin'])): ?>
                                                    <th>Department</th>
                                                <?php endif; ?>
                                                <th>Type</th>
                                                <th>From</th>
                                                <th>To</th>
                                                <th>Days</th>
                                                <th>Status</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($pendingApplications as $application): ?>
                                                <tr>
                                                    <td><?php echo $application['first_name'] . ' ' . $application['last_name']; ?></td>
                                                    <?php if (in_array($userRole, ['central_admin', 'admin'])): ?>
                                                        <td><?php echo $application['dept_name']; ?></td>
                                                    <?php endif; ?>
                                                    <td><?php echo ucwords(str_replace('_', ' ', $application['type_name'])); ?></td>
                                                    <td><?php echo date('d-m-Y', strtotime($application['start_date'])); ?></td>
                                                    <td><?php echo date('d-m-Y', strtotime($application['end_date'])); ?></td>
                                                    <td><?php echo $application['total_days']; ?></td>
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
                                                        }
                                                        ?>
                                                    </td>
                                                    <td>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($userRole === 'hod' && !empty($departmentFaculty)): ?>
                <!-- Faculty Members Section (for HOD) -->
                <div class="row">
                    <div class="col-12 mb-4">
                        <div class="card shadow-sm">
                            <div class="card-header bg-primary text-white">
                                <h5 class="card-title mb-0">Faculty Members</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>Name</th>
                                                <th>Email</th>
                                                <th>Phone</th>
                                                <th>Pending Leaves</th>
                                                <th>Spent Leave Days</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($departmentFaculty as $faculty): ?>
                                                <tr>
                                                    <td><?php echo $faculty['first_name'] . ' ' . $faculty['last_name']; ?></td>
                                                    <td><?php echo $faculty['email']; ?></td>
                                                    <td><?php echo $faculty['phone']; ?></td>
                                                    <td>
                                                        <span class="badge badge-<?php echo $faculty['pending_leaves'] > 0 ? 'warning' : 'secondary'; ?>">
                                                            <?php echo $faculty['pending_leaves']; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge badge-info">
                                                            <?php echo $faculty['spent_leave_days'] ?: 0; ?> days
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge badge-<?php echo $faculty['status'] === 'active' ? 'success' : 'danger'; ?>">
                                                            <?php echo ucfirst($faculty['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <button type="button" class="btn btn-sm btn-info" data-toggle="modal" data-target="#facultyModal<?php echo $faculty['user_id']; ?>">
                                                            View Details
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Faculty Details Modals -->
                <?php foreach ($departmentFaculty as $faculty): 
                    $facultyDetails = getFacultyLeaveDetails($faculty['user_id']);
                ?>
                <div class="modal fade" id="facultyModal<?php echo $faculty['user_id']; ?>" tabindex="-1" role="dialog" aria-labelledby="facultyModalLabel<?php echo $faculty['user_id']; ?>" aria-hidden="true">
                    <div class="modal-dialog modal-lg" role="document">
                        <div class="modal-content">
                            <div class="modal-header bg-info text-white">
                                <h5 class="modal-title" id="facultyModalLabel<?php echo $faculty['user_id']; ?>">
                                    <?php echo $faculty['first_name'] . ' ' . $faculty['last_name']; ?> - Leave Details
                                </h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="card mb-3">
                                            <div class="card-header bg-light">
                                                <h6 class="mb-0">Personal Information</h6>
                                            </div>
                                            <div class="card-body">
                                                <p><strong>Email:</strong> <?php echo $faculty['email']; ?></p>
                                                <p><strong>Phone:</strong> <?php echo $faculty['phone']; ?></p>
                                                <p><strong>Joined:</strong> <?php echo date('d-m-Y', strtotime($faculty['date_joined'])); ?></p>
                                                <p><strong>Status:</strong> 
                                                    <span class="badge badge-<?php echo $faculty['status'] === 'active' ? 'success' : 'danger'; ?>">
                                                        <?php echo ucfirst($faculty['status']); ?>
                                                    </span>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card mb-3">
                                            <div class="card-header bg-light">
                                                <h6 class="mb-0">Leave Balances</h6>
                                            </div>
                                            <div class="card-body">
                                                <?php if (empty($facultyDetails['balances'])): ?>
                                                    <p class="text-muted">No leave balance information available.</p>
                                                <?php else: ?>
                                                    <div class="table-responsive">
                                                        <table class="table table-sm">
                                                            <thead>
                                                                <tr>
                                                                    <th>Leave Type</th>
                                                                    <th>Total</th>
                                                                    <th>Used</th>
                                                                    <th>Remaining</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php foreach ($facultyDetails['balances'] as $balance): ?>
                                                                    <tr>
                                                                        <td><?php echo ucwords(str_replace('_', ' ', $balance['type_name'])); ?></td>
                                                                        <td><?php echo $balance['total_days']; ?></td>
                                                                        <td><?php echo $balance['used_days']; ?></td>
                                                                        <td>
                                                                            <span class="badge badge-<?php echo $balance['remaining_days'] > 0 ? 'success' : 'danger'; ?>">
                                                                                <?php echo $balance['remaining_days']; ?>
                                                                            </span>
                                                                        </td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                 
                                <div class="card">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0">Recent Leave Applications</h6>
                                    </div>
                                    <div class="card-body">
                                        <?php if (empty($facultyDetails['applications'])): ?>
                                            <p class="text-muted">No recent leave applications found.</p>
                                        <?php else: ?>
                                            <div class="table-responsive">
                                                <table class="table table-sm">
                                                    <thead>
                                                        <tr>
                                                            <th>ID</th>
                                                            <th>Leave Type</th>
                                                            <th>Period</th>
                                                            <th>Days</th>
                                                            <th>Status</th>
                                                            <th>Applied On</th>
                                                            <th></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($facultyDetails['applications'] as $app): ?>
                                                            <tr>
                                                                <td><?php echo $app['application_id']; ?></td>
                                                                <td><?php echo ucwords(str_replace('_', ' ', $app['type_name'])); ?></td>
                                                                <td>
                                                                    <?php echo date('d-m-Y', strtotime($app['start_date'])); ?>
                                                                    <?php if ($app['start_date'] != $app['end_date']): ?>
                                                                        <br>to<br>
                                                                        <?php echo date('d-m-Y', strtotime($app['end_date'])); ?>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td><?php echo $app['total_days']; ?></td>
                                                                <td>
                                                                    <span class="badge badge-<?php 
                                                                        echo $app['status'] === 'approved' ? 'success' : 
                                                                            ($app['status'] === 'pending' ? 'warning' : 
                                                                                ($app['status'] === 'rejected' ? 'danger' : 'info')); 
                                                                    ?>">
                                                                        <?php echo ucfirst(str_replace('_', ' ', $app['status'])); ?>
                                                                    </span>
                                                                </td>
                                                                <td><?php echo date('d-m-Y', strtotime($app['application_date'])); ?></td>
                                                                <td>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
                
                <!-- Notifications -->
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <div class="card shadow-sm">
                            <div class="card-header bg-secondary text-white">
                                <h5 class="card-title mb-0">Recent Notifications</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($notifications)): ?>
                                    <p class="text-muted">No notifications found.</p>
                                <?php else: ?>
                                    <div class="list-group">
                                        <?php foreach ($notifications as $notification): ?>
                                            <a href="view_notification.php?id=<?php echo $notification['notification_id']; ?>" class="list-group-item list-group-item-action <?php echo $notification['is_read'] ? '' : 'list-group-item-light'; ?>">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h6 class="mb-1"><?php echo $notification['title']; ?></h6>
                                                    <small><?php echo date('d-m-Y', strtotime($notification['created_at'])); ?></small>
                                                </div>
                                                <p class="mb-1 text-truncate"><?php echo $notification['message']; ?></p>
                                                <?php if (!$notification['is_read']): ?>
                                                    <span class="badge badge-primary">New</span>
                                                <?php endif; ?>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                    <a href="notifications.php" class="btn btn-sm btn-outline-secondary mt-2">View All Notifications</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Links -->
                    <div class="col-md-6 mb-4">
                        <div class="card shadow-sm">
                            <div class="card-header bg-success text-white">
                                <h5 class="card-title mb-0">Quick Links</h5>
                            </div>
                            <div class="card-body">
                                <div class="list-group">
                                    <?php if ($userRole === 'faculty'): ?>
                                    <a href="apply_leave.php" class="list-group-item list-group-item-action">
                                        <i class="fas fa-plus-circle mr-2"></i> Apply for Leave
                                    </a>
                                    <a href="my_applications.php" class="list-group-item list-group-item-action">
                                        <i class="fas fa-list mr-2"></i> My Applications
                                    </a>
                                    <a href="leave_balance.php" class="list-group-item list-group-item-action">
                                        <i class="fas fa-calculator mr-2"></i> Leave Balance
                                    </a>
                                    <?php endif; ?>
                                    <?php if (in_array($userRole, ['hod', 'central_admin', 'admin'])): ?>
                                        <a href="pending_approvals.php" class="list-group-item list-group-item-action">
                                            <i class="fas fa-check-circle mr-2"></i> Pending Approvals
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($userRole === 'admin'): ?>
                                        <a href="manage_users.php" class="list-group-item list-group-item-action">
                                            <i class="fas fa-users-cog mr-2"></i> Manage Users
                                        </a>
                                        <a href="system_settings.php" class="list-group-item list-group-item-action">
                                            <i class="fas fa-cogs mr-2"></i> System Settings
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="assets/js/script.js"></script>
</body>
</html>
