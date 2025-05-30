<?php
require_once 'config/config.php';

// Require login to access this page
requireLogin();

// Get user's leave balances
function getUserLeaveBalances($userId) {
    $conn = connectDB();
    $currentYear = date('Y');
    
    $query = "SELECT lt.type_id, lt.type_name, lt.description, lb.total_days, lb.used_days, (lb.total_days - lb.used_days) as remaining_days
              FROM leave_types lt
              LEFT JOIN leave_balances lb ON lt.type_id = lb.leave_type_id AND lb.user_id = ? AND lb.year = ?
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

$userId = $_SESSION['user_id'];
$leaveBalances = getUserLeaveBalances($userId);

// Page title
$pageTitle = "Leave Balance";
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
                    <h1 class="h2"><i class="fas fa-calculator mr-2"></i><?php echo $pageTitle; ?></h1>
                </div>
                
                <div class="row">
                    <div class="col-md-12">
                        <div class="card shadow-sm">
                            <div class="card-header bg-primary text-white">
                                <h5 class="card-title mb-0"><i class="fas fa-balance-scale mr-2"></i>Your Leave Balances for <?php echo date('Y'); ?></h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($leaveBalances)): ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle mr-2"></i> No leave balances found for the current year.
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped table-hover">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th><i class="fas fa-tag mr-1"></i> Leave Type</th>
                                                    <th><i class="fas fa-info-circle mr-1"></i> Description</th>
                                                    <th class="text-center"><i class="fas fa-calendar-plus mr-1"></i> Total Days</th>
                                                    <th class="text-center"><i class="fas fa-calendar-minus mr-1"></i> Used Days</th>
                                                    <th class="text-center"><i class="fas fa-calendar-check mr-1"></i> Remaining Days</th>
                                                    <th class="text-center"><i class="fas fa-chart-pie mr-1"></i> Usage</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($leaveBalances as $balance): ?>
                                                    <tr>
                                                        <td><strong><?php echo htmlspecialchars($balance['type_name']); ?></strong></td>
                                                        <td><?php echo htmlspecialchars($balance['description']); ?></td>
                                                        <td class="text-center">
                                                            <span class="badge badge-info badge-pill px-3 py-2">
                                                                <?php echo $balance['total_days'] ?? 0; ?>
                                                            </span>
                                                        </td>
                                                        <td class="text-center">
                                                            <span class="badge badge-warning badge-pill px-3 py-2">
                                                                <?php echo $balance['used_days'] ?? 0; ?>
                                                            </span>
                                                        </td>
                                                        <td class="text-center">
                                                            <span class="badge badge-success badge-pill px-3 py-2">
                                                                <?php echo $balance['remaining_days'] ?? 0; ?>
                                                            </span>
                                                        </td>
                                                        <td class="text-center">
                                                            <?php 
                                                            $totalDays = $balance['total_days'] ?? 0;
                                                            $usedDays = $balance['used_days'] ?? 0;
                                                            $percentage = ($totalDays > 0) ? ($usedDays / $totalDays) * 100 : 0;
                                                            ?>
                                                            <div class="progress" style="height: 20px;">
                                                                <div class="progress-bar bg-primary" role="progressbar" 
                                                                     style="width: <?php echo $percentage; ?>%;" 
                                                                     aria-valuenow="<?php echo $percentage; ?>" 
                                                                     aria-valuemin="0" 
                                                                     aria-valuemax="100">
                                                                    <?php echo round($percentage); ?>%
                                                                </div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="alert alert-info mt-3">
                                        <i class="fas fa-info-circle mr-2"></i>
                                        <strong>Note:</strong> Leave balances are updated automatically when your leave applications are approved.
                                    </div>
                                <?php endif; ?>
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
