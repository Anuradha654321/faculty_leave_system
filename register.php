<?php
require_once 'config/config.php';

// Check if admin is logged in
if (isLoggedIn() && $_SESSION['role'] !== 'admin') {
    // Only admin can access this page directly
    redirect(BASE_URL . 'unauthorized.php');
}

// Get departments
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

// Get roles
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

// Process registration form
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    $firstName = sanitizeInput($_POST['first_name']);
    $lastName = sanitizeInput($_POST['last_name']);
    $email = sanitizeInput($_POST['email']);
    $phone = sanitizeInput($_POST['phone']);
    $roleId = intval($_POST['role_id']);
    $deptId = intval($_POST['dept_id']);
    
    // Validate input
    if (empty($username) || empty($password) || empty($confirmPassword) || empty($firstName) || 
        empty($lastName) || empty($email) || empty($phone) || empty($roleId)) {
        $error = 'Please fill in all required fields.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $conn = connectDB();
        
        // Check if username already exists
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = 'Username already exists. Please choose a different username.';
        } else {
            // Check if email already exists
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error = 'Email already exists. Please use a different email address.';
            } else {
                // Check role restrictions
                $roleQuery = "SELECT role_name FROM roles WHERE role_id = ?";
                $roleStmt = $conn->prepare($roleQuery);
                $roleStmt->bind_param("i", $roleId);
                $roleStmt->execute();
                $roleResult = $roleStmt->get_result();
                $roleRow = $roleResult->fetch_assoc();
                $roleName = $roleRow['role_name'];
                $roleRestrictionError = false;
                
                // Department is not required for admin and central_admin
                $requireDept = !in_array($roleName, ['admin', 'central_admin']);
                
                // For admin and central_admin, if no department is selected, get the first available department
                if (!$requireDept && empty($deptId)) {
                    $deptQuery = "SELECT dept_id FROM departments ORDER BY dept_id LIMIT 1";
                    $deptResult = $conn->query($deptQuery);
                    if ($deptResult && $deptResult->num_rows > 0) {
                        $deptRow = $deptResult->fetch_assoc();
                        $deptId = $deptRow['dept_id'];
                    } else {
                        // If no departments exist, we need to create one
                        $error = 'No departments found in the system. Please create at least one department first.';
                    }
                }
                
                // Check if trying to create Central Admin
                if ($roleName === 'central_admin') {
                    $centralAdminCheck = $conn->prepare("SELECT COUNT(*) as count FROM users u JOIN roles r ON u.role_id = r.role_id WHERE r.role_name = 'central_admin'");
                    $centralAdminCheck->execute();
                    $centralAdminResult = $centralAdminCheck->get_result();
                    $centralAdminCount = $centralAdminResult->fetch_assoc()['count'];
                    
                    if ($centralAdminCount > 0) {
                        $error = 'There can only be one Central Admin in the system.';
                        $roleRestrictionError = true;
                    }
                }
                
                // Check if trying to create Admin
                if (!$roleRestrictionError && $roleName === 'admin') {
                    $adminCheck = $conn->prepare("SELECT COUNT(*) as count FROM users u JOIN roles r ON u.role_id = r.role_id WHERE r.role_name = 'admin'");
                    $adminCheck->execute();
                    $adminResult = $adminCheck->get_result();
                    $adminCount = $adminResult->fetch_assoc()['count'];
                    
                    if ($adminCount > 0) {
                        $error = 'There can only be one Admin in the system.';
                        $roleRestrictionError = true;
                    }
                }
                
                // Check if trying to create HOD for a department that already has one
                if (!$roleRestrictionError && $roleName === 'hod') {
                    $hodCheck = $conn->prepare("SELECT COUNT(*) as count FROM users u JOIN roles r ON u.role_id = r.role_id WHERE r.role_name = 'hod' AND u.dept_id = ?");
                    $hodCheck->bind_param("i", $deptId);
                    $hodCheck->execute();
                    $hodResult = $hodCheck->get_result();
                    $hodCount = $hodResult->fetch_assoc()['count'];
                    
                    if ($hodCount > 0) {
                        $error = 'This department already has a HOD assigned.';
                        $roleRestrictionError = true;
                    }
                }
                
                $roleStmt->close();
                
                // Only proceed with user creation if there are no role restriction errors
                if (!$roleRestrictionError) {
                    // Hash the password
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Insert new user
                    $stmt = $conn->prepare("INSERT INTO users (username, password, first_name, last_name, email, phone, role_id, dept_id, date_joined, status) 
                                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), 'active')");
                    $stmt->bind_param("ssssssii", $username, $hashedPassword, $firstName, $lastName, $email, $phone, $roleId, $deptId);
                    
                    if ($stmt->execute()) {
                        $userId = $conn->insert_id;
                        
                        // Initialize leave balances for the new user
                        $year = date('Y');
                        
                        // Get leave types
                        $leaveTypesQuery = "SELECT type_id, default_balance FROM leave_types";
                        $leaveTypesResult = $conn->query($leaveTypesQuery);
                        
                        while ($leaveType = $leaveTypesResult->fetch_assoc()) {
                            $leaveTypeId = $leaveType['type_id'];
                            $defaultBalance = $leaveType['default_balance'] ?: 0;
                            
                            $balanceStmt = $conn->prepare("INSERT INTO leave_balances (user_id, leave_type_id, year, total_days, used_days) 
                                                          VALUES (?, ?, ?, ?, 0)");
                            $balanceStmt->bind_param("iiid", $userId, $leaveTypeId, $year, $defaultBalance);
                            $balanceStmt->execute();
                        }
                        
                        $success = 'User registered successfully.';
                        
                        // If not logged in (self-registration), redirect to login
                        if (!isLoggedIn()) {
                            setFlashMessage('success', 'Registration successful. Please login with your credentials.');
                            header('Location: ' . BASE_URL . 'login.php');
                            exit();
                        }
                    } else {
                        $error = 'Error registering user: ' . $conn->error;
                    }
                }
            }
        }
        
        closeDB($conn);
    }
}

// Get departments and roles for the form
$departments = getDepartments();
$roles = getRoles();

// Determine if this is self-registration or admin adding a user
$isSelfRegistration = !isLoggedIn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo APP_TITLE; ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-light">
    <?php if (isLoggedIn()): ?>
        <?php include 'includes/header.php'; ?>
        
        <div class="container-fluid mt-4">
            <div class="row">
                <?php include 'includes/sidebar.php'; ?>
                
                <main class="col-md-9 ml-sm-auto col-lg-10 px-md-4">
                    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                        <h1 class="h2">Register New User</h1>
                    </div>
    <?php else: ?>
        <div class="container mt-5">
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-primary text-white text-center">
                            <h4><?php echo APP_TITLE; ?> - Registration</h4>
                        </div>
    <?php endif; ?>
                    
    <?php if (!isLoggedIn()): ?>
        <div class="card-body">
    <?php endif; ?>
                        
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" action="" id="register-form">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="username">Username <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="username" name="username" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="email">Email <span class="text-danger">*</span></label>
                                        <input type="email" class="form-control" id="email" name="email" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="password">Password <span class="text-danger">*</span></label>
                                        <input type="password" class="form-control" id="password" name="password" required>
                                        <small class="form-text text-muted">Password must be at least 6 characters long.</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="confirm_password">Confirm Password <span class="text-danger">*</span></label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="first_name">First Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="first_name" name="first_name" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="last_name">Last Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="last_name" name="last_name" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="phone">Phone <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="phone" name="phone" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="dept_id">Department <span class="dept-required text-danger">*</span></label>
                                        <select class="form-control" id="dept_id" name="dept_id">
                                            <option value="">-- Select Department --</option>
                                            <?php foreach ($departments as $department): ?>
                                                <option value="<?php echo $department['dept_id']; ?>"><?php echo $department['dept_name']; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="form-text text-muted dept-note" style="display:none;">Not required for Admin and Central Admin roles.</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="role_id">Role <span class="text-danger">*</span></label>
                                <select class="form-control" id="role_id" name="role_id" required>
                                    <option value="">-- Select Role --</option>
                                    <?php foreach ($roles as $role): ?>
                                        <option value="<?php echo $role['role_id']; ?>"><?php echo ucfirst($role['role_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group text-center mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-user-plus"></i> Register
                                </button>
                                
                                <?php if (isLoggedIn()): ?>
                                    <a href="<?php echo BASE_URL; ?>manage_users.php" class="btn btn-secondary ml-2">
                                        <i class="fas fa-times"></i> Cancel
                                    </a>
                                <?php else: ?>
                                    <a href="<?php echo BASE_URL; ?>login.php" class="btn btn-secondary ml-2" onclick="window.location.href='<?php echo BASE_URL; ?>login.php'; return false;">
                                        <i class="fas fa-sign-in-alt"></i> Back to Login
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                        
    <?php if (!isLoggedIn()): ?>
        </div>
        <div class="card-footer text-center">
            <p class="mb-0">Already have an account? <a href="javascript:void(0);" onclick="window.location.href='<?php echo BASE_URL; ?>login.php';">Login here</a></p>
        </div>
    <?php endif; ?>
                    
    <?php if (isLoggedIn()): ?>
                </main>
            </div>
        </div>
        
        <?php include 'includes/footer.php'; ?>
    <?php else: ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.3/dist/jquery.validate.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Handle role change to toggle department requirement
            $('#role_id').on('change', function() {
                const roleId = $(this).val();
                // Check if admin (1) or central_admin (4)
                if (roleId === '1' || roleId === '4') {
                    // Not required for admin and central_admin
                    $('#dept_id').prop('required', false);
                    $('#dept_id').rules('remove', 'required');
                    $('.dept-required').hide();
                    $('.dept-note').show();
                } else {
                    // Required for other roles
                    $('#dept_id').prop('required', true);
                    $('#dept_id').rules('add', {required: true});
                    $('.dept-required').show();
                    $('.dept-note').hide();
                }
            });

            // Trigger the change event on page load to set initial state
            $('#role_id').trigger('change');

            // Form validation
            $('#register-form').validate({
                rules: {
                    username: {
                        required: true,
                        minlength: 3
                    },
                    password: {
                        required: true,
                        minlength: 6
                    },
                    confirm_password: {
                        required: true,
                        equalTo: "#password"
                    },
                    email: {
                        required: true,
                        email: true
                    },
                    first_name: {
                        required: true
                    },
                    last_name: {
                        required: true
                    },
                    phone: {
                        required: true
                    },
                    dept_id: {
                        required: function(element) {
                            var roleId = $('#role_id').val();
                            return roleId !== '1' && roleId !== '4';
                        }
                    },
                    role_id: {
                        required: true
                    }
                },
                messages: {
                    username: {
                        required: "Please enter a username",
                        minlength: "Username must be at least 3 characters long"
                    },
                    password: {
                        required: "Please provide a password",
                        minlength: "Password must be at least 6 characters long"
                    },
                    confirm_password: {
                        required: "Please confirm your password",
                        equalTo: "Passwords do not match"
                    },
                    email: {
                        required: "Please enter your email address",
                        email: "Please enter a valid email address"
                    },
                    first_name: {
                        required: "Please enter your first name"
                    },
                    last_name: {
                        required: "Please enter your last name"
                    },
                    phone: {
                        required: "Please enter your phone number"
                    },
                    dept_id: {
                        required: "Please select a department"
                    },
                    role_id: {
                        required: "Please select a role"
                    }
                },
                errorElement: 'div',
                errorPlacement: function(error, element) {
                    error.addClass('invalid-feedback');
                    element.closest('.form-group').append(error);
                },
                highlight: function(element, errorClass, validClass) {
                    $(element).addClass('is-invalid').removeClass('is-valid');
                },
                unhighlight: function(element, errorClass, validClass) {
                    $(element).removeClass('is-invalid').addClass('is-valid');
                }
            });
        });
    </script>
</body>
</html>
