<?php
require_once 'config/config.php';

// Require login to access this page
requireLogin();

// Only faculty, HOD, and admin can access this page
if (!in_array($_SESSION['role'], ['faculty', 'hod', 'admin'])) {
    redirect(BASE_URL . 'unauthorized.php');
}

$userId = $_SESSION['user_id'];

// Get pending class adjustments for the user
function getPendingAdjustments($userId) {
    $conn = connectDB();
    
    $query = "SELECT ca.adjustment_id, ca.class_date, ca.class_details, ca.adjustment_status, 
              la.application_id, u.first_name, u.last_name
              FROM class_adjustments ca
              JOIN leave_applications la ON ca.application_id = la.application_id
              JOIN users u ON la.user_id = u.user_id
              WHERE ca.adjusted_by = ? AND ca.adjustment_status = 'pending'
              ORDER BY ca.class_date ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $adjustments = [];
    while ($row = $result->fetch_assoc()) {
        $adjustments[] = $row;
    }
    
    $stmt->close();
    closeDB($conn);
    
    return $adjustments;
}

// Get all adjustments for the user
function getAllAdjustments($userId) {
    $conn = connectDB();
    
    $query = "SELECT ca.adjustment_id, ca.class_date, ca.class_details, ca.adjustment_status, 
              ca.remarks, la.application_id, u.first_name, u.last_name
              FROM class_adjustments ca
              JOIN leave_applications la ON ca.application_id = la.application_id
              JOIN users u ON la.user_id = u.user_id
              WHERE ca.adjusted_by = ?
              ORDER BY ca.class_date DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $adjustments = [];
    while ($row = $result->fetch_assoc()) {
        $adjustments[] = $row;
    }
    
    $stmt->close();
    closeDB($conn);
    
    return $adjustments;
}

// Handle adjustment status update
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_adjustment'])) {
    $adjustmentId = intval($_POST['adjustment_id']);
    $status = sanitizeInput($_POST['status']);
    $remarks = sanitizeInput($_POST['remarks']);
    
    if (!in_array($status, ['accepted', 'rejected'])) {
        $error = 'Invalid status value.';
    } else {
        $conn = connectDB();
        
        $query = "UPDATE class_adjustments SET adjustment_status = ?, remarks = ? WHERE adjustment_id = ? AND adjusted_by = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssii", $status, $remarks, $adjustmentId, $userId);
        
        if ($stmt->execute()) {
            $success = 'Class adjustment updated successfully.';
        } else {
            $error = 'Failed to update class adjustment.';
        }
        
        $stmt->close();
        closeDB($conn);
    }
}

$pendingAdjustments = getPendingAdjustments($userId);
$allAdjustments = getAllAdjustments($userId);

// Page title
$pageTitle = "Class Adjustments";
include 'includes/header.php';
?>

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
            
            <!-- Pending Adjustments -->
            <div class="card mb-4 shadow">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">Pending Class Adjustments</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pendingAdjustments)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> No pending class adjustments found.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Faculty</th>
                                        <th>Class Date</th>
                                        <th>Class Details</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pendingAdjustments as $adjustment): ?>
                                        <tr>
                                            <td><strong><?php echo $adjustment['first_name'] . ' ' . $adjustment['last_name']; ?></strong></td>
                                            <td><span class="badge badge-info"><?php echo date('d-m-Y', strtotime($adjustment['class_date'])); ?></span></td>
                                            <td><?php echo $adjustment['class_details']; ?></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#adjustmentModal<?php echo $adjustment['adjustment_id']; ?>">
                                                    <i class="fas fa-reply"></i> Respond
                                                </button>
                                                
                                                <!-- Modal for responding to adjustment -->
                                                <div class="modal fade" id="adjustmentModal<?php echo $adjustment['adjustment_id']; ?>" tabindex="-1" role="dialog" aria-labelledby="adjustmentModalLabel" aria-hidden="true">
                                                    <div class="modal-dialog" role="document">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="adjustmentModalLabel">Respond to Class Adjustment</h5>
                                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                                    <span aria-hidden="true">&times;</span>
                                                                </button>
                                                            </div>
                                                            <form method="post" action="">
                                                                <div class="modal-body">
                                                                    <input type="hidden" name="adjustment_id" value="<?php echo $adjustment['adjustment_id']; ?>">
                                                                    
                                                                    <div class="form-group">
                                                                        <label>Faculty:</label>
                                                                        <p class="form-control-static"><?php echo $adjustment['first_name'] . ' ' . $adjustment['last_name']; ?></p>
                                                                    </div>
                                                                    
                                                                    <div class="form-group">
                                                                        <label>Class Date:</label>
                                                                        <p class="form-control-static"><?php echo date('d-m-Y', strtotime($adjustment['class_date'])); ?></p>
                                                                    </div>
                                                                    
                                                                    <div class="form-group">
                                                                        <label>Class Details:</label>
                                                                        <p class="form-control-static"><?php echo $adjustment['class_details']; ?></p>
                                                                    </div>
                                                                    
                                                                    <div class="form-group">
                                                                        <label for="status">Your Response:</label>
                                                                        <select class="form-control" id="status" name="status" required>
                                                                            <option value="">-- Select --</option>
                                                                            <option value="accepted">Accept</option>
                                                                            <option value="rejected">Reject</option>
                                                                        </select>
                                                                    </div>
                                                                    
                                                                    <div class="form-group">
                                                                        <label for="remarks">Remarks:</label>
                                                                        <textarea class="form-control" id="remarks" name="remarks" rows="3"></textarea>
                                                                    </div>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                                                    <button type="submit" name="update_adjustment" class="btn btn-primary">Submit Response</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- All Adjustments -->
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0"><i class="fas fa-list"></i> All Class Adjustments</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($allAdjustments)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> No class adjustments found.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Faculty</th>
                                        <th>Class Date</th>
                                        <th>Class Details</th>
                                        <th>Status</th>
                                        <th>Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($allAdjustments as $adjustment): ?>
                                        <tr>
                                            <td><strong><?php echo $adjustment['first_name'] . ' ' . $adjustment['last_name']; ?></strong></td>
                                            <td><span class="badge badge-info"><?php echo date('d-m-Y', strtotime($adjustment['class_date'])); ?></span></td>
                                            <td><?php echo $adjustment['class_details']; ?></td>
                                            <td>
                                                <span class="badge badge-<?php 
                                                    echo ($adjustment['adjustment_status'] == 'accepted') ? 'success' : 
                                                        (($adjustment['adjustment_status'] == 'rejected') ? 'danger' : 'warning'); 
                                                ?>">
                                                    <?php echo ucfirst($adjustment['adjustment_status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $adjustment['remarks']; ?></td>
                                        </tr>
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
