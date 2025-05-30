<?php
require_once 'config/config.php';

// Require login to access this page
requireLogin();

// Get user details
function getUserDetails($userId) {
    $conn = connectDB();
    
    $query = "SELECT u.*, d.dept_name, r.role_name 
              FROM users u
              JOIN departments d ON u.dept_id = d.dept_id
              JOIN roles r ON u.role_id = r.role_id
              WHERE u.user_id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    $stmt->close();
    closeDB($conn);
    
    return $user;
}

$userId = $_SESSION['user_id'];
$user = getUserDetails($userId);

// Handle form submission
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $firstName = sanitizeInput($_POST['first_name']);
    $lastName = sanitizeInput($_POST['last_name']);
    $email = sanitizeInput($_POST['email']);
    $phone = sanitizeInput($_POST['phone']);
    
    if (empty($firstName) || empty($lastName) || empty($email)) {
        $error = 'First name, last name, and email are required fields.';
    } else {
        $conn = connectDB();
        
        $query = "UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE user_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssssi", $firstName, $lastName, $email, $phone, $userId);
        
        if ($stmt->execute()) {
            $success = 'Profile updated successfully.';
            // Refresh user data
            $user = getUserDetails($userId);
        } else {
            $error = 'Failed to update profile. Please try again.';
        }
        
        $stmt->close();
        closeDB($conn);
    }
}

// Page title
$pageTitle = "My Profile";
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
                    <h1 class="h2"><?php echo $pageTitle; ?></h1>
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
                
                <div class="row">
                    <div class="col-md-4 mb-4">
                        <div class="card shadow-sm">
                            <div class="card-header bg-primary text-white">
                                <h5 class="card-title mb-0"><i class="fas fa-user-circle mr-2"></i>Account Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="text-center mb-4">
                                    <i class="fas fa-user-circle fa-5x text-primary mb-3"></i>
                                    <h4><?php echo $user['first_name'] . ' ' . $user['last_name']; ?></h4>
                                    <p class="text-muted"><?php echo ucwords($user['role_name']); ?></p>
                                </div>
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span><i class="fas fa-user mr-2 text-secondary"></i> Username</span>
                                        <span class="font-weight-bold"><?php echo $user['username']; ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span><i class="fas fa-building mr-2 text-secondary"></i> Department</span>
                                        <span class="font-weight-bold"><?php echo $user['dept_name']; ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span><i class="fas fa-calendar-alt mr-2 text-secondary"></i> Joined</span>
                                        <span class="font-weight-bold"><?php echo date('d M Y', strtotime($user['date_joined'])); ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span><i class="fas fa-toggle-on mr-2 text-secondary"></i> Status</span>
                                        <span class="badge badge-pill badge-<?php echo ($user['status'] == 'active') ? 'success' : 'danger'; ?> px-3 py-2">
                                            <?php echo ucwords($user['status']); ?>
                                        </span>
                                    </li>
                                </ul>
                                <div class="mt-3">
                                    <a href="change_password.php" class="btn btn-outline-primary btn-block">
                                        <i class="fas fa-key mr-2"></i>Change Password
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-8 mb-4">
                        <div class="card shadow-sm">
                            <div class="card-header bg-primary text-white">
                                <h5 class="card-title mb-0"><i class="fas fa-edit mr-2"></i>Edit Profile</h5>
                            </div>
                            <div class="card-body">
                                <form method="post" action="">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="first_name"><i class="fas fa-user mr-2 text-secondary"></i>First Name</label>
                                                <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="last_name"><i class="fas fa-user mr-2 text-secondary"></i>Last Name</label>
                                                <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="email"><i class="fas fa-envelope mr-2 text-secondary"></i>Email</label>
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                                    </div>
                                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="phone"><i class="fas fa-phone mr-2 text-secondary"></i>Phone</label>
                                                <div class="input-group">
                                                    <div class="input-group-prepend">
                                                        <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                                    </div>
                                                    <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle mr-2"></i>
                                            Update your profile information to keep your account details current.
                                        </div>
                                    </div>
                                    
                                    <button type="submit" name="update_profile" class="btn btn-primary">
                                        <i class="fas fa-save mr-2"></i>Update Profile
                                    </button>
                                </form>
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
</body>
</html>
