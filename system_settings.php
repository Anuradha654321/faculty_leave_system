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

// Handle form submissions for system settings
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    // Process form submission
    // This is where you would update system settings in the database
    $success = 'System settings updated successfully.';
}

// Page title
$pageTitle = "System Settings";
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
                    <h1 class="h2"><i class="fas fa-cogs mr-2"></i><?php echo $pageTitle; ?></h1>
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
                
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">System Configuration</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" action="">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card mb-3">
                                        <div class="card-header bg-light">
                                            <h6 class="mb-0">Leave Settings</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="form-group">
                                                <label for="max_casual_leave">Maximum Casual Leave Days (per year):</label>
                                                <input type="number" class="form-control" id="max_casual_leave" name="max_casual_leave" value="10">
                                            </div>
                                            <div class="form-group">
                                                <label for="max_earned_leave">Maximum Earned Leave Days (per year):</label>
                                                <input type="number" class="form-control" id="max_earned_leave" name="max_earned_leave" value="30">
                                            </div>
                                            <div class="form-group">
                                                <label for="max_medical_leave">Maximum Medical Leave Days (per year):</label>
                                                <input type="number" class="form-control" id="max_medical_leave" name="max_medical_leave" value="15">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card mb-3">
                                        <div class="card-header bg-light">
                                            <h6 class="mb-0">Approval Settings</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="form-group">
                                                <div class="custom-control custom-switch">
                                                    <input type="checkbox" class="custom-control-input" id="require_hod_approval" name="require_hod_approval" checked>
                                                    <label class="custom-control-label" for="require_hod_approval">Require HOD Approval for All Leaves</label>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <div class="custom-control custom-switch">
                                                    <input type="checkbox" class="custom-control-input" id="require_central_admin_approval" name="require_central_admin_approval" checked>
                                                    <label class="custom-control-label" for="require_central_admin_approval">Require Central Admin Approval for Leaves > 2 Days</label>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <div class="custom-control custom-switch">
                                                    <input type="checkbox" class="custom-control-input" id="require_document" name="require_document">
                                                    <label class="custom-control-label" for="require_document">Require Document Upload for Medical Leaves</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card mb-3">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0">Email Notification Settings</h6>
                                </div>
                                <div class="card-body">
                                    <div class="form-group">
                                        <div class="custom-control custom-switch">
                                            <input type="checkbox" class="custom-control-input" id="email_notifications" name="email_notifications" checked>
                                            <label class="custom-control-label" for="email_notifications">Enable Email Notifications</label>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="admin_email">Admin Email Address:</label>
                                        <input type="email" class="form-control" id="admin_email" name="admin_email" value="admin@example.com">
                                    </div>
                                </div>
                            </div>
                            <div class="text-right">
                                <button type="submit" name="update_settings" class="btn btn-primary">
                                    <i class="fas fa-save mr-1"></i> Save Settings
                                </button>
                            </div>
                        </form>
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
