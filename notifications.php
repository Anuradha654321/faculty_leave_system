<?php
require_once 'config/config.php';

// Require login to access this page
requireLogin();

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];

// Get parameters
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$notificationId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get all notifications for the user
function getAllNotifications($userId) {
    $conn = connectDB();
    
    // Get notifications
    $stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($notification = $result->fetch_assoc()) {
        // Check if this is a leave application notification
        $isLeaveNotification = (strpos($notification['title'], 'Leave Application') !== false);
        $applicationId = null;
        
        // Try to extract application ID from the message
        if ($isLeaveNotification) {
            if (preg_match('/application ID: (\d+)/', $notification['message'], $matches)) {
                $applicationId = $matches[1];
            }
        }
        
        $notification['is_leave_notification'] = $isLeaveNotification;
        $notification['application_id'] = $applicationId;
        $notifications[] = $notification;
    }
    
    $stmt->close();
    closeDB($conn);
    
    return $notifications;
}

// Get notifications
$notifications = getAllNotifications($userId);

// Handle view action
if ($action === 'view' && $notificationId) {
    // Mark notification as read
    $conn = connectDB();
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notificationId, $userId);
    $stmt->execute();
    $stmt->close();
    closeDB($conn);
    
    // Redirect back to notifications page
    header("Location: " . BASE_URL . "notifications.php");
    exit;
}

// Mark all notifications as read
if (isset($_GET['mark_all_read']) && $_GET['mark_all_read'] == 1) {
    $conn = connectDB();
    $query = "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->close();
    closeDB($conn);
    
    // Redirect to remove the query parameter
    redirect(BASE_URL . 'notifications.php');
}

$pageTitle = "All Notifications";
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

<style>
    /* Additional styles to fix modal issues */
    .modal {
        z-index: 1050;
    }
    .modal-backdrop {
        z-index: 1040;
    }
</style>
    
    <div class="container-fluid">
        <!-- Alert container for notifications -->
        <div id="alert-container" class="mt-3"></div>
        
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main role="main" class="col-md-9 ml-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-bell mr-2"></i><?php echo $pageTitle; ?></h1>
                    <?php if (!empty($notifications)): ?>
                        <button type="button" class="btn btn-outline-secondary" id="markAllAsRead">
                            <i class="fas fa-check-double mr-1"></i> Mark All as Read
                        </button>
                    <?php endif; ?>
                </div>
                
                <?php if (isset($success) && !empty($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success; ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error) && !empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>
                
                <div class="card shadow-sm">
                    <div class="card-body">
                        <?php if (empty($notifications)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-bell-slash fa-4x text-muted mb-3"></i>
                                <p class="lead text-muted">No notifications found.</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($notifications as $notification): ?>
                                    <div class="list-group-item list-group-item-action <?php echo $notification['is_read'] ? '' : 'list-group-item-light'; ?>">
                                        <div class="d-flex w-100 justify-content-between align-items-start">
                                            <div>
                                                <h5 class="mb-1">
                                                    <?php if (!$notification['is_read']): ?>
                                                        <span class="badge badge-primary mr-2">New</span>
                                                    <?php endif; ?>
                                                    <?php echo htmlspecialchars($notification['title']); ?>
                                                </h5>
                                                <p class="mb-1"><?php echo htmlspecialchars($notification['message']); ?></p>
                                            </div>
                                            
                                             <div class="d-flex flex-column align-items-end">
                                                <small class="text-muted mb-2"><?php echo date('d-m-Y H:i', strtotime($notification['created_at'])); ?></small>
                                            </div>
                                        </div>
                                        
                                         <?php 
                                        // Show View Details button and action buttons
                                        if ($notification['is_leave_notification'] && $notification['application_id']): 
                                        ?>
                                            <div class="mt-2 d-flex justify-content-between align-items-center w-100">
                                                <div>
                                                    <?php if (in_array($_SESSION['role'], ['hod', 'central_admin', 'admin']) && $notification['application_id']): ?>
                                                        <a href="pending_approvals.php?action=approve&id=<?php echo $notification['application_id']; ?>" class="btn btn-success">
                                                            <i class="fas fa-check-circle"></i> Accept
                                                        </a>
                                                        <a href="pending_approvals.php?action=reject&id=<?php echo $notification['application_id']; ?>" class="btn btn-danger ml-2">
                                                            <i class="fas fa-times-circle"></i> Reject
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <a href="view_application.php?id=<?php echo $notification['application_id']; ?>" class="btn btn-info" style="min-width: 120px;">
                                                        <i class="fas fa-eye"></i> View Details
                                                    </a>
                                                </div>
                                            </div>
                                            

                                        <?php elseif (!empty($notification['link'])): ?>
                                            <a href="<?php echo htmlspecialchars($notification['link']); ?>" class="btn btn-outline-primary mt-2">
                                                <i class="fas fa-external-link-alt"></i> View Details
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // Simple script to handle marking all notifications as read
    $(document).ready(function() {
        $('#markAllAsRead').click(function() {
            window.location.href = 'notifications.php?mark_all_read=1';
        });
    });
    </script>
</body>
</html>
