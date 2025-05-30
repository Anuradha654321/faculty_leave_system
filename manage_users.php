<?php
require_once 'config/config.php';

// Require login to access this page
requireLogin();

// Only admin can access this page
if ($_SESSION['role'] !== 'admin') {
    redirect(BASE_URL . 'unauthorized.php');
}

// Get all departments for dropdown
function getDepartments() {
    $conn = connectDB();
    
    $query = "SELECT dept_id, dept_name FROM departments ORDER BY dept_name";
    
    $result = $conn->query($query);
    
    $departments = [];
    while ($row = $result->fetch_assoc()) {
        $departments[] = $row;
    }
    
    closeDB($conn);
    
    return $departments;
}

// Get all roles for dropdown
function getRoles() {
    $conn = connectDB();
    
    $query = "SELECT role_id, role_name FROM roles ORDER BY role_id";
    
    $result = $conn->query($query);
    
    $roles = [];
    while ($row = $result->fetch_assoc()) {
        $roles[] = $row;
    }
    
    closeDB($conn);
    
    return $roles;
}

// Get all users with department and role information
function getUsers() {
    $conn = connectDB();
    
    $query = "SELECT u.user_id, u.username, u.first_name, u.last_name, u.email, u.phone, 
                     u.status, r.role_name, d.dept_name, u.created_at
              FROM users u
              JOIN roles r ON u.role_id = r.role_id
              LEFT JOIN departments d ON u.dept_id = d.dept_id
              ORDER BY u.first_name, u.last_name";
    
    $result = $conn->query($query);
    
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    
    closeDB($conn);
    
    return $users;
}

// Get user details by ID
function getUserById($userId) {
    $conn = connectDB();
    
    $stmt = $conn->prepare("SELECT u.user_id, u.username, u.first_name, u.last_name, u.email, 
                                   u.phone, u.role_id, u.dept_id, u.status
                            FROM users u
                            WHERE u.user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return null;
    }
    
    $user = $result->fetch_assoc();
    
    $stmt->close();
    closeDB($conn);
    
    return $user;
}

// Update user
function updateUser($userId, $firstName, $lastName, $email, $phone, $roleId, $deptId, $status) {
    $conn = connectDB();
    
    // Check if email already exists for another user
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
    $stmt->bind_param("si", $email, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return false; // Email already exists
    }
    
    // Update user
    $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, 
                                  role_id = ?, dept_id = ?, status = ? 
                            WHERE user_id = ?");
    $stmt->bind_param("ssssiisi", $firstName, $lastName, $email, $phone, $roleId, $deptId, $status, $userId);
    $success = $stmt->execute();
    
    $stmt->close();
    closeDB($conn);
    
    return $success;
}

// Reset user password
function resetUserPassword($userId) {
    $conn = connectDB();
    
    // Generate a random password
    $newPassword = generateRandomPassword();
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // Update user password
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
    $stmt->bind_param("si", $hashedPassword, $userId);
    $success = $stmt->execute();
    
    $stmt->close();
    closeDB($conn);
    
    if ($success) {
        return $newPassword;
    } else {
        return false;
    }
}

// Generate a random password
function generateRandomPassword($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*()';
    $password = '';
    
    for ($i = 0; $i < $length; $i++) {
        $index = rand(0, strlen($characters) - 1);
        $password .= $characters[$index];
    }
    
    return $password;
}

// Process form submissions
$error = '';
$success = '';
$newPassword = '';

// Update user
if (isset($_POST['update_user'])) {
    $userId = intval($_POST['user_id']);
    $firstName = sanitizeInput($_POST['first_name']);
    $lastName = sanitizeInput($_POST['last_name']);
    $email = sanitizeInput($_POST['email']);
    $phone = sanitizeInput($_POST['phone']);
    $roleId = intval($_POST['role_id']);
    $deptId = intval($_POST['dept_id']);
    $status = sanitizeInput($_POST['status']);
    
    if (empty($firstName) || empty($lastName) || empty($email)) {
        $error = 'First name, last name, and email are required.';
    } else {
        if (updateUser($userId, $firstName, $lastName, $email, $phone, $roleId, $deptId, $status)) {
            $success = 'User updated successfully.';
        } else {
            $error = 'Email already exists or an error occurred.';
        }
    }
}

// Reset password
if (isset($_POST['reset_password'])) {
    $userId = intval($_POST['user_id']);
    
    $newPassword = resetUserPassword($userId);
    
    if ($newPassword) {
        $success = 'Password reset successfully. New password: ' . $newPassword;
    } else {
        $error = 'An error occurred while resetting the password.';
    }
}

// Get user for editing
$editUser = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editUser = getUserById(intval($_GET['edit']));
    
    if (!$editUser) {
        $error = 'User not found.';
    }
}

// Get all users
$users = getUsers();

// Get all departments and roles for dropdowns
$departments = getDepartments();
$roles = getRoles();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - <?php echo APP_TITLE; ?></title>
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
                    <h1 class="h2">Manage Users</h1>
                    <a href="<?php echo BASE_URL; ?>register.php" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> Add New User
                    </a>
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
                
                <?php if ($editUser): ?>
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Edit User: <?php echo $editUser['first_name'] . ' ' . $editUser['last_name']; ?></h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <input type="hidden" name="user_id" value="<?php echo $editUser['user_id']; ?>">
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="username">Username</label>
                                            <input type="text" class="form-control" id="username" value="<?php echo $editUser['username']; ?>" readonly>
                                            <small class="form-text text-muted">Username cannot be changed</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="status">Status</label>
                                            <select class="form-control" id="status" name="status">
                                                <option value="active" <?php echo $editUser['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                                <option value="inactive" <?php echo $editUser['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="first_name">First Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo $editUser['first_name']; ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="last_name">Last Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo $editUser['last_name']; ?>" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="email">Email <span class="text-danger">*</span></label>
                                            <input type="email" class="form-control" id="email" name="email" value="<?php echo $editUser['email']; ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="phone">Phone</label>
                                            <input type="text" class="form-control" id="phone" name="phone" value="<?php echo $editUser['phone']; ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="role_id">Role <span class="text-danger">*</span></label>
                                            <select class="form-control" id="role_id" name="role_id" required>
                                                <?php foreach ($roles as $role): ?>
                                                    <option value="<?php echo $role['role_id']; ?>" <?php echo $editUser['role_id'] == $role['role_id'] ? 'selected' : ''; ?>>
                                                        <?php echo $role['role_name']; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="dept_id">Department <span class="text-danger">*</span></label>
                                            <select class="form-control" id="dept_id" name="dept_id" required>
                                                <?php foreach ($departments as $department): ?>
                                                    <option value="<?php echo $department['dept_id']; ?>" <?php echo $editUser['dept_id'] == $department['dept_id'] ? 'selected' : ''; ?>>
                                                        <?php echo $department['dept_name']; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group text-right">
                                    <a href="<?php echo BASE_URL; ?>manage_users.php" class="btn btn-secondary">Cancel</a>
                                    <button type="submit" name="update_user" class="btn btn-primary">Update User</button>
                                </div>
                            </form>
                            
                            <hr>
                            
                            <h5>Reset Password</h5>
                            <p>Use this option to reset the user's password. A new random password will be generated.</p>
                            <form method="POST" action="" onsubmit="return confirm('Are you sure you want to reset the password for this user?');">
                                <input type="hidden" name="user_id" value="<?php echo $editUser['user_id']; ?>">
                                <button type="submit" name="reset_password" class="btn btn-warning">
                                    <i class="fas fa-key"></i> Reset Password
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover data-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Department</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?php echo $user['user_id']; ?></td>
                                            <td><?php echo $user['first_name'] . ' ' . $user['last_name']; ?></td>
                                            <td><?php echo $user['username']; ?></td>
                                            <td><?php echo $user['email']; ?></td>
                                            <td><?php echo $user['role_name']; ?></td>
                                            <td><?php echo $user['dept_name']; ?></td>
                                            <td>
                                                <?php if ($user['status'] === 'active'): ?>
                                                    <span class="badge badge-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge badge-danger">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="<?php echo BASE_URL; ?>manage_users.php?edit=<?php echo $user['user_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
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
                    { "orderable": false, "targets": 7 } // Disable sorting on Actions column
                ]
            });
        });
    </script>
</body>
</html>
