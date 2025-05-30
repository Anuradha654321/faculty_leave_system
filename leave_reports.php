<?php
require_once 'config/config.php';

// Require login to access this page
requireLogin();

// Only admin can access this page
if ($_SESSION['role'] !== 'admin') {
    redirect(BASE_URL . 'unauthorized.php');
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];

// Handle report generation
$success = '';
$error = '';
$reportData = [];

// Function to get leave report data
function getLeaveReport($departmentId = null, $startDate = null, $endDate = null, $leaveType = null) {
    $conn = connectDB();
    
    $query = "SELECT la.application_id, u.first_name, u.last_name, d.dept_name, lt.type_name, 
              la.start_date, la.end_date, la.total_days, la.status, la.application_date,
              la.hod_approval, la.admin_approval
              FROM leave_applications la
              JOIN users u ON la.user_id = u.user_id
              JOIN departments d ON u.dept_id = d.dept_id
              JOIN leave_types lt ON la.leave_type_id = lt.type_id
              WHERE 1=1";
    
    $params = [];
    $types = "";
    
    if ($departmentId) {
        $query .= " AND d.dept_id = ?";
        $params[] = $departmentId;
        $types .= "i";
    }
    
    if ($startDate) {
        $query .= " AND la.start_date >= ?";
        $params[] = $startDate;
        $types .= "s";
    }
    
    if ($endDate) {
        $query .= " AND la.end_date <= ?";
        $params[] = $endDate;
        $types .= "s";
    }
    
    if ($leaveType) {
        $query .= " AND lt.type_id = ?";
        $params[] = $leaveType;
        $types .= "i";
    }
    
    $query .= " ORDER BY la.application_date DESC";
    
    $stmt = $conn->prepare($query);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $reports = [];
    while ($row = $result->fetch_assoc()) {
        $reports[] = $row;
    }
    
    $stmt->close();
    closeDB($conn);
    
    return $reports;
}

// Function to get all departments
function getAllDepartments() {
    $conn = connectDB();
    
    $query = "SELECT dept_id, dept_name FROM departments ORDER BY dept_name";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $departments = [];
    while ($row = $result->fetch_assoc()) {
        $departments[] = $row;
    }
    
    $stmt->close();
    closeDB($conn);
    
    return $departments;
}

// Function to get all leave types
function getAllLeaveTypes() {
    $conn = connectDB();
    
    $query = "SELECT type_id, type_name FROM leave_types ORDER BY type_name";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $leaveTypes = [];
    while ($row = $result->fetch_assoc()) {
        $leaveTypes[] = $row;
    }
    
    $stmt->close();
    closeDB($conn);
    
    return $leaveTypes;
}

// Get all departments and leave types for the filter
$departments = getAllDepartments();
$leaveTypes = getAllLeaveTypes();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_report'])) {
    $departmentId = !empty($_POST['department_id']) ? intval($_POST['department_id']) : null;
    $startDate = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
    $endDate = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
    $leaveType = !empty($_POST['leave_type']) ? intval($_POST['leave_type']) : null;
    
    $reportData = getLeaveReport($departmentId, $startDate, $endDate, $leaveType);
    
    if (empty($reportData)) {
        $error = 'No data found for the selected criteria.';
    } else {
        $success = 'Report generated successfully.';
    }
}

// Page title
$pageTitle = "Leave Reports";
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
            
            <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-chart-bar mr-2"></i><?php echo $pageTitle; ?></h1>
                </div>
                
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
                
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">Generate Report</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" action="">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="department_id">Department:</label>
                                        <select class="form-control" id="department_id" name="department_id">
                                            <option value="">All Departments</option>
                                            <?php foreach ($departments as $department): ?>
                                                <option value="<?php echo $department['dept_id']; ?>"><?php echo $department['dept_name']; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="leave_type">Leave Type:</label>
                                        <select class="form-control" id="leave_type" name="leave_type">
                                            <option value="">All Leave Types</option>
                                            <?php foreach ($leaveTypes as $leaveType): ?>
                                                <option value="<?php echo $leaveType['type_id']; ?>"><?php echo $leaveType['type_name']; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="start_date">Start Date:</label>
                                        <input type="date" class="form-control" id="start_date" name="start_date">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="end_date">End Date:</label>
                                        <input type="date" class="form-control" id="end_date" name="end_date">
                                    </div>
                                </div>
                            </div>
                            <div class="text-right">
                                <button type="submit" name="generate_report" class="btn btn-primary">
                                    <i class="fas fa-search mr-1"></i> Generate Report
                                </button>
                                <?php if (!empty($reportData)): ?>
                                    <button type="button" class="btn btn-success ml-2" onclick="exportToExcel()">
                                        <i class="fas fa-file-excel mr-1"></i> Export to Excel
                                    </button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
                
                <?php if (!empty($reportData)): ?>
                    <div class="card shadow-sm">
                        <div class="card-header bg-success text-white">
                            <h5 class="card-title mb-0">Report Results</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover" id="reportTable">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>ID</th>
                                            <th>Faculty</th>
                                            <th>Department</th>
                                            <th>Leave Type</th>
                                            <th>From</th>
                                            <th>To</th>
                                            <th>Days</th>
                                            <th>Status</th>
                                            <th>HOD Approval</th>
                                            <th>Admin Approval</th>
                                            <th>Applied On</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($reportData as $report): ?>
                                            <tr>
                                                <td><?php echo $report['application_id']; ?></td>
                                                <td><?php echo $report['first_name'] . ' ' . $report['last_name']; ?></td>
                                                <td><?php echo $report['dept_name']; ?></td>
                                                <td><?php echo $report['type_name']; ?></td>
                                                <td><?php echo date('d-m-Y', strtotime($report['start_date'])); ?></td>
                                                <td><?php echo date('d-m-Y', strtotime($report['end_date'])); ?></td>
                                                <td><?php echo $report['total_days']; ?></td>
                                                <td>
                                                    <?php
                                                    if ($report['status'] == 'pending') {
                                                        echo '<span class="badge badge-warning text-dark">Pending</span>';
                                                    } elseif ($report['status'] == 'approved_by_hod') {
                                                        echo '<span class="badge badge-info">HOD Approved</span>';
                                                    } elseif ($report['status'] == 'approved') {
                                                        echo '<span class="badge badge-success">Approved</span>';
                                                    } elseif ($report['status'] == 'rejected') {
                                                        echo '<span class="badge badge-danger">Rejected</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    if ($report['hod_approval'] == 'approved') {
                                                        echo '<span class="badge badge-success">Approved</span>';
                                                    } elseif ($report['hod_approval'] == 'rejected') {
                                                        echo '<span class="badge badge-danger">Rejected</span>';
                                                    } else {
                                                        echo '<span class="badge badge-secondary">Pending</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    if ($report['admin_approval'] == 'approved') {
                                                        echo '<span class="badge badge-success">Approved</span>';
                                                    } elseif ($report['admin_approval'] == 'rejected') {
                                                        echo '<span class="badge badge-danger">Rejected</span>';
                                                    } else {
                                                        echo '<span class="badge badge-secondary">Pending</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td><?php echo date('d-m-Y', strtotime($report['application_date'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <!-- Script for exporting to Excel -->
    <script>
        function exportToExcel() {
            // Simple export to CSV function
            var table = document.getElementById("reportTable");
            var html = table.outerHTML;
            
            // Convert to CSV
            var csv = [];
            var rows = table.querySelectorAll("tr");
            
            for (var i = 0; i < rows.length; i++) {
                var row = [], cols = rows[i].querySelectorAll("td, th");
                
                for (var j = 0; j < cols.length; j++) {
                    // Clean the text content
                    var data = cols[j].textContent.replace(/(\r\n|\n|\r)/gm, "").trim();
                    // Escape double-quotes with double-quotes
                    data = data.replace(/"/g, '""');
                    // Push the data
                    row.push('"' + data + '"');
                }
                csv.push(row.join(","));
            }
            
            var csvFile = csv.join("\n");
            var filename = "leave_report_" + new Date().toISOString().slice(0, 10) + ".csv";
            
            var link = document.createElement("a");
            link.setAttribute("href", "data:text/csv;charset=utf-8," + encodeURIComponent(csvFile));
            link.setAttribute("download", filename);
            link.style.display = "none";
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>
</body>
</html>
