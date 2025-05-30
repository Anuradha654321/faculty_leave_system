<?php
require_once 'config/config.php';

// Require login to access this page
requireLogin();

$userId = $_SESSION['user_id'];
$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    // Validate input
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $error = 'All fields are required.';
    } elseif (strlen($newPassword) < 6) {
        $error = 'New password must be at least 6 characters long.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'New password and confirm password do not match.';
    } else {
        $conn = connectDB();
        
        // Verify current password
        $query = "SELECT password FROM users WHERE user_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if (!password_verify($currentPassword, $user['password'])) {
            $error = 'Current password is incorrect.';
        } else {
            // Update password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $updateQuery = "UPDATE users SET password = ? WHERE user_id = ?";
            $updateStmt = $conn->prepare($updateQuery);
            $updateStmt->bind_param("si", $hashedPassword, $userId);
            
            if ($updateStmt->execute()) {
                $success = 'Password changed successfully.';
            } else {
                $error = 'Failed to change password. Please try again.';
            }
            
            $updateStmt->close();
        }
        
        $stmt->close();
        closeDB($conn);
    }
}

// Page title
$pageTitle = "Change Password";
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
                
                <div class="row justify-content-center">
                    <div class="col-md-8">
                        <div class="card shadow-sm">
                            <div class="card-header bg-primary text-white">
                                <h5 class="card-title mb-0"><i class="fas fa-key mr-2"></i>Change Your Password</h5>
                            </div>
                            <div class="card-body">
                                <div class="text-center mb-4">
                                    <i class="fas fa-lock fa-4x text-primary mb-3"></i>
                                    <h5>Update Your Password</h5>
                                    <p class="text-muted">Ensure your account stays secure by using a strong password</p>
                                </div>
                                
                                <form method="post" action="">
                                    <div class="form-group">
                                        <label for="current_password"><i class="fas fa-unlock-alt mr-2 text-secondary"></i>Current Password <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                            </div>
                                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="new_password"><i class="fas fa-key mr-2 text-secondary"></i>New Password <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                            </div>
                                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="confirm_password"><i class="fas fa-check-circle mr-2 text-secondary"></i>Confirm New Password <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                            </div>
                                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                        </div>
                                    </div>
                                    
                                    <div class="alert alert-info">
                                        <h6><i class="fas fa-info-circle mr-2"></i>Password Requirements:</h6>
                                        <ul class="mb-0">
                                            <li>At least 6 characters long</li>
                                            <li>Include a mix of letters, numbers, and special characters</li>
                                            <li>Avoid using easily guessable information</li>
                                        </ul>
                                    </div>
                                    
                                    <div class="form-group text-center">
                                        <button type="submit" name="change_password" class="btn btn-primary btn-lg">
                                            <i class="fas fa-save mr-2"></i>Update Password
                                        </button>
                                    </div>
                                    
                                    <div class="text-center mt-3">
                                        <a href="profile.php" class="btn btn-outline-secondary">
                                            <i class="fas fa-arrow-left mr-2"></i>Back to Profile
                                        </a>
                                    </div>
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
