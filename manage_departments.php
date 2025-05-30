<?php
require_once 'config/config.php';

// Require login to access this page
requireLogin();

// Only admin can access this page
if ($_SESSION['role'] !== 'admin') {
    redirect(BASE_URL . 'unauthorized.php');
}

// Get all departments
function getDepartments() {
    $conn = connectDB();
    
    $query = "SELECT d.dept_id, d.dept_name, COUNT(u.user_id) as user_count
              FROM departments d
              LEFT JOIN users u ON d.dept_id = u.dept_id
              GROUP BY d.dept_id
              ORDER BY d.dept_name";
    
    $result = $conn->query($query);
    
    $departments = [];
    while ($row = $result->fetch_assoc()) {
        $departments[] = $row;
    }
    
    closeDB($conn);
    
    return $departments;
}

// Add new department
function addDepartment($deptName) {
    $conn = connectDB();
    
    // Check if department already exists
    $stmt = $conn->prepare("SELECT dept_id FROM departments WHERE dept_name = ?");
    $stmt->bind_param("s", $deptName);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return false; // Department already exists
    }
    
    // Insert new department
    $stmt = $conn->prepare("INSERT INTO departments (dept_name) VALUES (?)");
    $stmt->bind_param("s", $deptName);
    $success = $stmt->execute();
    
    $stmt->close();
    closeDB($conn);
    
    return $success;
}

// Update department
function updateDepartment($deptId, $deptName) {
    $conn = connectDB();
    
    // Check if department name already exists for another department
    $stmt = $conn->prepare("SELECT dept_id FROM departments WHERE dept_name = ? AND dept_id != ?");
    $stmt->bind_param("si", $deptName, $deptId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return false; // Department name already exists
    }
    
    // Update department
    $stmt = $conn->prepare("UPDATE departments SET dept_name = ? WHERE dept_id = ?");
    $stmt->bind_param("si", $deptName, $deptId);
    $success = $stmt->execute();
    
    $stmt->close();
    closeDB($conn);
    
    return $success;
}

// Delete department
function deleteDepartment($deptId) {
    $conn = connectDB();
    
    // Check if department has users
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE dept_id = ?");
    $stmt->bind_param("i", $deptId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return false; // Department has users, cannot delete
    }
    
    // Delete department
    $stmt = $conn->prepare("DELETE FROM departments WHERE dept_id = ?");
    $stmt->bind_param("i", $deptId);
    $success = $stmt->execute();
    
    $stmt->close();
    closeDB($conn);
    
    return $success;
}

// Process form submissions
$error = '';
$success = '';

// Add department
if (isset($_POST['add_department'])) {
    $deptName = sanitizeInput($_POST['dept_name']);
    
    if (empty($deptName)) {
        $error = 'Department name is required.';
    } else {
        if (addDepartment($deptName)) {
            $success = 'Department added successfully.';
        } else {
            $error = 'Department already exists or an error occurred.';
        }
    }
}

// Update department
if (isset($_POST['update_department'])) {
    $deptId = intval($_POST['dept_id']);
    $deptName = sanitizeInput($_POST['dept_name']);
    
    if (empty($deptName)) {
        $error = 'Department name is required.';
    } else {
        if (updateDepartment($deptId, $deptName)) {
            $success = 'Department updated successfully.';
        } else {
            $error = 'Department name already exists or an error occurred.';
        }
    }
}

// Delete department
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $deptId = intval($_GET['delete']);
    
    if (deleteDepartment($deptId)) {
        $success = 'Department deleted successfully.';
    } else {
        $error = 'Cannot delete department that has users assigned to it.';
    }
}

// Get all departments
$departments = getDepartments();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Departments - <?php echo APP_TITLE; ?></title>
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
                    <h1 class="h2">Manage Departments</h1>
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addDepartmentModal">
                        <i class="fas fa-plus"></i> Add Department
                    </button>
                </div>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success; ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>
                
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover data-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Department Name</th>
                                        <th>Users</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($departments as $department): ?>
                                        <tr>
                                            <td><?php echo $department['dept_id']; ?></td>
                                            <td><?php echo $department['dept_name']; ?></td>
                                            <td><?php echo $department['user_count']; ?></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline-primary edit-department" 
                                                        data-id="<?php echo $department['dept_id']; ?>" 
                                                        data-name="<?php echo $department['dept_name']; ?>">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                
                                                <?php if ($department['user_count'] == 0): ?>
                                                    <a href="manage_departments.php?delete=<?php echo $department['dept_id']; ?>" 
                                                       class="btn btn-sm btn-outline-danger confirm-action" 
                                                       data-confirm="Are you sure you want to delete this department?">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </a>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" disabled title="Cannot delete department with users">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Add Department Modal -->
    <div class="modal fade" id="addDepartmentModal" tabindex="-1" role="dialog" aria-labelledby="addDepartmentModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addDepartmentModalLabel">Add Department</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="dept_name">Department Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="dept_name" name="dept_name" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_department" class="btn btn-primary">Add Department</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Department Modal -->
    <div class="modal fade" id="editDepartmentModal" tabindex="-1" role="dialog" aria-labelledby="editDepartmentModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editDepartmentModalLabel">Edit Department</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" id="edit_dept_id" name="dept_id">
                        <div class="form-group">
                            <label for="edit_dept_name">Department Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_dept_name" name="dept_name" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_department" class="btn btn-primary">Update Department</button>
                    </div>
                </form>
            </div>
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
                "pageLength": 10,
                "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]],
                "columnDefs": [
                    { "orderable": false, "targets": 3 } // Disable sorting on Actions column
                ]
            });
            
            // Edit department
            $('.edit-department').on('click', function() {
                const deptId = $(this).data('id');
                const deptName = $(this).data('name');
                
                $('#edit_dept_id').val(deptId);
                $('#edit_dept_name').val(deptName);
                
                $('#editDepartmentModal').modal('show');
            });
            
            // Confirmation dialog for delete action
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
