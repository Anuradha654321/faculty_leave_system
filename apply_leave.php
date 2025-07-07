<?php
require_once 'config/config.php';
require_once 'includes/functions.php';
require_once 'includes/EmailHelper.php';

// Require login to access this page
requireLogin();

// Only faculty, HOD, central admin, and admin can apply for leave
if (!in_array($_SESSION['role'], ['faculty', 'hod', 'central_admin', 'admin'])) {
    redirect(BASE_URL . 'unauthorized.php');
}

// Get leave types
function getLeaveTypes() {
    $conn = connectDB();
    
    $query = "SELECT type_id, type_name, description, default_balance 
              FROM leave_types 
              ORDER BY type_name";
    
    $result = $conn->query($query);
    
    $leaveTypes = [];
    while ($row = $result->fetch_assoc()) {
        $leaveTypes[] = $row;
    }
    
    closeDB($conn);
    
    return $leaveTypes;
}

// Get faculty members for class adjustment
function getFacultyMembers() {
    $conn = connectDB();
    $currentUserId = $_SESSION['user_id'];
    $deptId = $_SESSION['dept_id'];
    
    $query = "SELECT user_id, first_name, last_name 
              FROM users 
              WHERE role_id = (SELECT role_id FROM roles WHERE role_name = 'faculty') 
              AND dept_id = ? 
              AND user_id != ? 
              AND status = 'active'
              ORDER BY first_name, last_name";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $deptId, $currentUserId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $faculty = [];
    while ($row = $result->fetch_assoc()) {
        $faculty[] = $row;
    }
    
    $stmt->close();
    closeDB($conn);
    
    return $faculty;
}

// Get user's leave balances
function getUserLeaveBalances($userId) {
    $conn = connectDB();
    $currentYear = date('Y');
    
    $query = "SELECT lt.type_id, lt.type_name, lb.total_days, lb.used_days, (lb.total_days - lb.used_days) as remaining_days
              FROM leave_types lt
              LEFT JOIN leave_balances lb ON lt.type_id = lb.leave_type_id AND lb.user_id = ? AND lb.year = ?
              ORDER BY lt.type_name";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $userId, $currentYear);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $balances = [];
    while ($row = $result->fetch_assoc()) {
        $balances[$row['type_id']] = $row;
    }
    
    $stmt->close();
    closeDB($conn);
    
    return $balances;
}

// Process form submission
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = connectDB();
    if (!$conn) {
        $error = "Database connection failed: " . mysqli_connect_error();
        return;
    }
    
    try {
        // Check if this is a permission leave
        $isPermission = isset($_POST['is_permission']) && $_POST['is_permission'] == 1;
        
        if ($isPermission) {
            // Handle permission leave
            $permissionDate = $_POST['permission_date'];
            $permissionSlot = $_POST['permission_slot'];
            $reason = $_POST['reason'];
            
            // Validate permission date
            if (empty($permissionDate)) {
                throw new Exception('Please select a date for the permission.');
            }
            
            // Set start and end dates to the same day for permission leave
            $startDate = date('Y-m-d', strtotime($permissionDate));
            $endDate = $startDate;
            $totalDays = 0.5; // Half day for permission leave
            $leaveTypeId = 0; // Will be set to permission leave type ID
            
            // Get permission leave type ID
            $leaveTypeQuery = "SELECT type_id FROM leave_types WHERE type_name = 'permission_leave' LIMIT 1";
            $result = $conn->query($leaveTypeQuery);
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $leaveTypeId = $row['type_id'];
            } else {
                throw new Exception('Permission leave type not found in the system.');
            }
        } else {
            // Handle regular leave
            $leaveTypeId = $_POST['leave_type_id'];
            $dateRange = $_POST['date_range'];
            $reason = $_POST['reason'];
            $totalDays = floatval($_POST['total_days']);
            
            // Check if it's a casual leave type (assuming IDs 2 and 3 are for casual leave)
            $isCasualLeave = ($leaveTypeId == '2' || $leaveTypeId == '3'); // Casual leave prior or emergency
            
            if ($isCasualLeave && strpos($dateRange, ',') !== false) {
                // For casual leave with multiple individual dates
                $individualDates = explode(',', $dateRange);
                $startDate = date('Y-m-d', strtotime($individualDates[0])); // First date as start date
                $endDate = date('Y-m-d', strtotime($individualDates[count($individualDates)-1])); // Last date as end date
            } else {
                // For other leave types or casual leave with date range
                $dates = explode(' to ', $dateRange);
                $startDate = date('Y-m-d', strtotime($dates[0]));
                // Check if second date exists before using it
                if (isset($dates[1])) {
                    $endDate = date('Y-m-d', strtotime($dates[1]));
                } else {
                    // If no end date is provided, use the start date
                    $endDate = $startDate;
                }
            }
        
            // Validate input
            if (empty($leaveTypeId) || empty($dateRange) || empty($reason) || $totalDays <= 0) {
                throw new Exception('Please fill in all required fields.');
            }
        
            $userId = $_SESSION['user_id'];
            
            // Check if there's an existing leave application for the same period
            $query = "SELECT COUNT(*) as count FROM leave_applications 
                      WHERE user_id = ? 
                      AND DATE(start_date) <= DATE(?)
                      AND DATE(end_date) >= DATE(?)
                      AND status != 'rejected'";
            
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Failed to prepare statement: " . $conn->error);
            }
            
            $stmt->bind_param("iss", $userId, $endDate, $startDate);
            if (!$stmt->execute()) {
                throw new Exception("Failed to execute query: " . $stmt->error);
            }
            
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            if ($row['count'] > 0) {
                throw new Exception('You already have a leave application for this period.');
            }
        
            // Check leave balance (skip for permission leave as it has its own balance)
            if (!$isPermission) {
                $balances = getUserLeaveBalances($userId);
                
                if (isset($balances[$leaveTypeId]) && $balances[$leaveTypeId]['remaining_days'] < $totalDays) {
                    throw new Exception('You do not have enough leave balance for this leave type.');
                }
            }
        
        // Begin transaction
        $conn->begin_transaction();
        
        // Insert leave application
        $query = "INSERT INTO leave_applications (user_id, leave_type_id, start_date, end_date, total_days, reason, is_permission, permission_slot) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $isPermissionInt = $isPermission ? 1 : 0;
        $permissionSlotValue = $isPermission ? $permissionSlot : null;
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iissdiss", $userId, $leaveTypeId, $startDate, $endDate, $totalDays, $reason, $isPermissionInt, $permissionSlotValue);
        $stmt->execute();
        
        // Get leave application ID
        $applicationId = $conn->insert_id;
        
        // Handle class adjustments for permission leave
        if ($isPermission && isset($_POST['adjustment_dates'])) {
            $adjustmentDates = $_POST['adjustment_dates'];
            $adjustmentTimes = $_POST['adjustment_times'] ?? [];
            $adjustmentSubjects = $_POST['adjustment_subjects'] ?? [];
            $adjustmentFaculty = $_POST['adjustment_faculty'] ?? [];
            
            $adjustmentQuery = "INSERT INTO class_adjustments 
                               (application_id, class_date, class_time, subject, adjusted_by, status) 
                               VALUES (?, ?, ?, ?, ?, 'pending')";
            $stmt = $conn->prepare($adjustmentQuery);
            
            foreach ($adjustmentDates as $index => $date) {
                if (!empty($date) && !empty($adjustmentTimes[$index]) && !empty($adjustmentSubjects[$index]) && !empty($adjustmentFaculty[$index])) {
                    $classDate = date('Y-m-d', strtotime($date));
                    $time = $adjustmentTimes[$index];
                    $subject = $adjustmentSubjects[$index];
                    $facultyId = $adjustmentFaculty[$index];
                    
                    $stmt->bind_param("isssi", $applicationId, $classDate, $time, $subject, $facultyId);
                    $stmt->execute();
                }
            }
        }
        
        // Get leave type name for notifications
        $leaveTypeQuery = "SELECT type_name FROM leave_types WHERE type_id = ?";
        $leaveTypeStmt = $conn->prepare($leaveTypeQuery);
        $leaveTypeStmt->bind_param("i", $leaveTypeId);
        $leaveTypeStmt->execute();
        $leaveTypeResult = $leaveTypeStmt->get_result();
        $leaveType = $leaveTypeResult->fetch_assoc();
        
        // Send notification to HOD and email
        $hodQuery = "SELECT u.user_id, u.email FROM users u 
                     JOIN roles r ON u.role_id = r.role_id 
                     WHERE r.role_name = 'hod' AND u.dept_id = ?";
        
        $stmt = $conn->prepare($hodQuery);
        $stmt->bind_param("i", $_SESSION['dept_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $hodId = $row['user_id'];
            $hodEmail = $row['email'];
            
            // Format notification message based on leave type
            if ($isPermission) {
                $slotText = ($permissionSlot == 'morning') ? 'Morning (8:40 AM – 10:20 AM)' : 'Evening (3:20 PM – 5:00 PM)';
                $notificationTitle = 'New Permission Leave Application';
                $notificationMessage = $_SESSION['name'] . ' has applied for permission leave on ' . 
                                    date('d-m-Y', strtotime($startDate)) . ' (' . $slotText . '). Please review.';
            } else {
                $notificationTitle = 'New Leave Application';
                $notificationMessage = $_SESSION['name'] . ' has applied for leave from ' . 
                                    date('d-m-Y', strtotime($startDate)) . ' to ' . 
                                    date('d-m-Y', strtotime($endDate)) . '. Please review.';
            }
            
            $query = "INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("iss", $hodId, $notificationTitle, $notificationMessage);
            $stmt->execute();
            
            // Send email notification
            $emailHelper = new EmailHelper();
            $emailHelper->sendLeaveAppliedEmail(
                $hodEmail,
                $applicationId,
                $leaveType['type_name'],
                $startDate,
                $endDate,
                $reason,
                $isPermission,
                $permissionSlot ?? null
            );
        }
        
        // Special notifications for specific leave types
        if ($isPermission) {
            // For permission leave, we don't need additional notifications
        } else if ($leaveTypeId == 4) { // Medical leave
            // Notify admin and send email
            $adminQuery = "SELECT u.user_id, u.email FROM users u 
                          JOIN roles r ON u.role_id = r.role_id 
                          WHERE r.role_name = 'admin' LIMIT 1";
            
            $result = $conn->query($adminQuery);
            
            if ($row = $result->fetch_assoc()) {
                $adminId = $row['user_id'];
                $adminEmail = $row['email'];
                
                // Send database notification
                $notificationTitle = 'Medical Leave Application';
                $notificationMessage = $_SESSION['name'] . ' has applied for medical leave from ' . date('d-m-Y', strtotime($startDate)) . ' to ' . date('d-m-Y', strtotime($endDate)) . '.';
                
                $query = "INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("iss", $adminId, $notificationTitle, $notificationMessage);
                $stmt->execute();
                
                // Send email notification
                $emailHelper = new EmailHelper();
                $emailHelper->sendLeaveAppliedEmail(
                    $adminEmail,
                    $applicationId,
                    $leaveType['type_name'],
                    $startDate,
                    $endDate,
                    $reason,
                    $isPermission,
                    $permissionSlot ?? null
                );
            }
        }
        }

        // Commit transaction
        try {
            $conn->commit();
        } catch (Exception $e) {
            // If commit fails, rollback
            $conn->rollback();
            throw new Exception("Transaction commit failed: " . $e->getMessage());
        }
        
        // Set success message
        $success = 'Leave application submitted successfully.';
        
        // Redirect to prevent form resubmission
        setFlashMessage('success', $success);
        redirect(BASE_URL . 'my_applications.php');
    } catch (Exception $e) {
        // Rollback transaction on error
        if (isset($conn)) {
            $conn->rollback();
        }
        $error = 'An error occurred: ' . $e->getMessage();
    } finally {
        if (isset($conn)) {
            closeDB($conn);
        }
    }
} // End of POST request handling

// Get data for the form
try {
    $leaveTypes = getLeaveTypes();
    $facultyMembers = getFacultyMembers();
    $leaveBalances = getUserLeaveBalances($_SESSION['user_id']);
} catch (Exception $e) {
    $error = 'Error loading form data: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply for Leave - <?php echo APP_TITLE; ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ml-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Apply for Leave</h1>
                </div>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <div class="card shadow-sm mb-4">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Apply for Leave</h5>
                            <button type="button" class="btn btn-outline-primary" id="apply_permission_btn">
                                <i class="fas fa-clock"></i> Apply for Permission Leave
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <form id="leave_application_form" method="POST" action="" enctype="multipart/form-data" class="leave-form">
                            <input type="hidden" name="is_permission" id="is_permission" value="0">
                            <!-- Leave Type Section -->
                            <div class="form-section">
                                <h5 class="form-section-title">Leave Type</h5>
                                
                                <div class="form-group">
                                    <label for="leave_type_id">Select Leave Type <span class="text-danger">*</span></label>
                                    <select class="form-control" id="leave_type_id" name="leave_type_id" required>
                                        <option value="">-- Select Leave Type --</option>
                                        <optgroup label="Casual Leave">
                                            <?php foreach ($leaveTypes as $type): ?>
                                                <?php if (strpos($type['type_name'], 'casual_leave') !== false): ?>
                                                    <option value="<?php echo $type['type_id']; ?>" data-max-days="<?php echo $type['default_balance']; ?>" data-type="<?php echo $type['type_name']; ?>">
                                                        <?php echo ucwords(str_replace('_', ' ', $type['type_name'])); ?>
                                                        <?php if (isset($leaveBalances[$type['type_id']])): ?>
                                                            (Balance: <?php echo $leaveBalances[$type['type_id']]['remaining_days']; ?> days)
                                                        <?php endif; ?>
                                                    </option>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </optgroup>
                                        <optgroup label="Other Leave Types">
                                            <?php foreach ($leaveTypes as $type): ?>
                                                <?php if (strpos($type['type_name'], 'casual_leave') === false): ?>
                                                    <option value="<?php echo $type['type_id']; ?>" data-max-days="<?php echo $type['default_balance']; ?>" data-type="<?php echo $type['type_name']; ?>">
                                                        <?php echo ucwords(str_replace('_', ' ', $type['type_name'])); ?>
                                                        <?php if (isset($leaveBalances[$type['type_id']])): ?>
                                                            (Balance: <?php echo $leaveBalances[$type['type_id']]['remaining_days']; ?> days)
                                                        <?php endif; ?>
                                                    </option>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    </select>
                                    <small class="form-text text-muted mt-2">
                                        <strong>Note:</strong>
                                        <ul class="mb-0 pl-3">
                                            <li>Casual Leave (Prior): For planned absences with prior notice</li>
                                            <li>Casual Leave (Emergency): For unexpected absences</li>
                                            <li>Earned Leave: Accumulated based on service</li>
                                            <li>Medical Leave: Requires supporting documents</li>
                                            <li>Maternity Leave: Must be applied in advance</li>
                                            <li>Academic/Study Leave: Requires central admin and admin approval</li>
                                            <li>On Duty/Other Duty: For official assignments</li>
                                            <li>Paid Leave: When all other leave balances are exhausted</li>
                                        </ul>
                                    </small>
                                </div>
                            </div>
                            
                            <!-- Leave Period Section -->
                            <div class="form-section">
                                <h5 class="form-section-title">Leave Period</h5>
                                
                                <!-- Casual Leave Date Picker (Multiple Individual Dates) -->
                                <div id="casual_leave_container" style="display: none;">
                                    <div class="form-group">
                                        <label>Select Individual Dates <span class="text-danger">*</span></label>
                                        <div id="casual_dates_container">
                                            <div class="date-input-container mb-2">
                                                <input type="text" class="form-control casual-date-picker" name="casual_dates[]" placeholder="Select date">
                                                <button type="button" class="btn btn-sm btn-danger remove-date" style="display: none;"><i class="fas fa-times"></i></button>
                                            </div>
                                        </div>
                                        <button type="button" id="add_casual_date" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-plus"></i> Add Another Date
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Regular Leave Date Range Picker -->
                                <div id="regular_leave_container">
                                    <div class="form-group">
                                        <label for="date_range">Leave Date Range <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control date-range-picker" id="date_range" name="date_range" placeholder="Select date range" required>
                                        <small class="form-text text-muted">Select the start and end dates for your leave.</small>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="total_days">Total Days</label>
                                    <input type="number" class="form-control" id="total_days" name="total_days" readonly>
                                    <small class="form-text text-muted">This will be calculated automatically based on your selected date range.</small>
                                </div>
                            </div>
                            
                            <!-- Class Adjustment Section (for All Leave Types) -->
                            <div class="form-section class-adjustment-section">
                                <h5 class="form-section-title">Class Adjustments</h5>
                                <p class="text-muted">Please provide details of classes that need to be adjusted during your leave period.</p>
                                
                                <div id="class_adjustments_container">
                                    <!-- Class adjustment rows will be added here -->
                                </div>
                                
                                <button type="button" id="add_class_adjustment" class="btn btn-sm btn-outline-primary mt-2">
                                    <i class="fas fa-plus"></i> Add Class Adjustment
                                </button>
                                
                                <div class="alert alert-info mt-3">
                                    <i class="fas fa-info-circle mr-2"></i> <strong>Note:</strong> Please ensure that you have obtained consent from the faculty member who will be handling your class during your absence.
                                </div>
                            </div>
                            
                            <!-- Document Upload Section (for Medical Leave) -->
                            <div class="form-section document-upload-section d-none">
                                <h5 class="form-section-title">Supporting Documents</h5>
                                
                                <div class="form-group">
                                    <label for="document" id="document-label">Upload Medical Certificate</label>
                                    <div class="custom-file">
                                        <input type="file" class="custom-file-input" id="document" name="document">
                                        <label class="custom-file-label" for="document">Choose file</label>
                                    </div>
                                    <small class="form-text text-muted">Upload medical certificate or other supporting documents (PDF, JPG, PNG).</small>
                                </div>
                            </div>
                            
                            <!-- Reason Section -->
                            <div class="form-section">
                                <h5 class="form-section-title">Reason for Leave</h5>
                                
                                <div class="form-group">
                                    <label for="reason">Reason <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="reason" name="reason" rows="3" required></textarea>
                                </div>
                            </div>
                            
                            <!-- Submit Button -->
                            <div class="form-group text-center mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane"></i> Submit Leave Application
                                </button>
                                <a href="<?php echo BASE_URL; ?>" class="btn btn-secondary ml-2">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Permission Leave Modal -->
    <div class="modal fade" id="permissionLeaveModal" tabindex="-1" role="dialog" aria-labelledby="permissionLeaveModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="permissionLeaveModalLabel">Apply for Permission Leave</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="permission_leave_form">
                        <div class="form-group">
                            <label for="permission_date">Date <span class="text-danger">*</span></label>
                            <input type="text" class="form-control datepicker" id="permission_date" name="permission_date" required>
                        </div>
                        <div class="form-group">
                            <label>Time Slot <span class="text-danger">*</span></label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="permission_slot" id="morning_slot" value="morning" required>
                                <label class="form-check-label" for="morning_slot">
                                    8:40 AM – 10:20 AM (Morning)
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="permission_slot" id="evening_slot" value="afternoon" required>
                                <label class="form-check-label" for="evening_slot">
                                    3:20 PM – 5:00 PM (Evening)
                                </label>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="permission_reason">Reason <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="permission_reason" name="reason" rows="3" required></textarea>
                        </div>
                        <div id="permission_class_adjustments">
                            <h6>Class Adjustments</h6>
                            <div id="permission_class_adjustment_container">
                                <!-- Class adjustment fields will be added here -->
                            </div>
                            <button type="button" id="add_permission_class_adjustment" class="btn btn-sm btn-outline-secondary mt-2">
                                <i class="fas fa-plus"></i> Add Class Adjustment
                            </button>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="submit_permission_leave">Submit Permission</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Class Adjustment Template (Hidden) -->
    <template id="class_adjustment_template">
        <div class="class-adjustment-row mt-3 border rounded p-3 bg-light shadow-sm">
            <div class="row">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0"><i class="fas fa-chalkboard-teacher mr-2"></i>Class <span class="badge badge-primary row-number">1</span></h6>
                        <button type="button" class="btn btn-sm btn-outline-danger remove-class-adjustment">
                            <i class="fas fa-times"></i> Remove
                        </button>
                    </div>
                    <hr class="my-2">
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label><i class="fas fa-calendar-day mr-1"></i> Class Date</label>
                        <input type="text" class="form-control class-date-picker" name="class_date[]" placeholder="Select date" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label><i class="fas fa-user mr-1"></i> Adjusted With Faculty</label>
                        <input type="text" class="form-control faculty-autosuggest" name="adjusted_faculty_name[]" placeholder="Type faculty name...">
                        <input type="hidden" name="adjusted_faculty_id[]" value="">
                        <div class="faculty-suggestions"></div>
                    </div>
                </div>
            </div>
            <div class="row class-details-row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label><i class="fas fa-graduation-cap mr-1"></i> Year</label>
                        <input type="text" class="form-control" name="class_year[]" placeholder="Year" required>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label><i class="fas fa-code-branch mr-1"></i> Branch</label>
                        <input type="text" class="form-control" name="class_branch[]" placeholder="Branch" required>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label><i class="fas fa-users mr-1"></i> Section</label>
                        <input type="text" class="form-control" name="class_section[]" placeholder="Section" required>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label><i class="fas fa-clock mr-1"></i> Time</label>
                        <input type="text" class="form-control" name="class_time[]" placeholder="Time" required>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label><i class="fas fa-book mr-1"></i> Subject <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="class_subject[]" placeholder="Subject" required>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12">
                    <div class="form-group mb-0">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input faculty-confirmation" id="faculty_confirmation_new" name="faculty_confirmation[]" required>
                            <label class="custom-control-label" for="faculty_confirmation_new">
                                <span class="text-danger">*</span> I confirm that I have obtained consent from the faculty member who will be handling my class
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.3/dist/jquery.validate.min.js"></script>
    <link rel="stylesheet" href="assets/css/leave-form.css">
    
    <!-- Permission Leave Class Adjustment Template -->
    <template id="permission_class_adjustment_template">
        <div class="class-adjustment-row mt-2 border rounded p-2 bg-light">
            <div class="row">
                <div class="col-md-5">
                    <div class="form-group mb-2">
                        <label>Date</label>
                        <input type="text" class="form-control adjustment-date" name="adjustment_dates[]" required>
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="form-group mb-2">
                        <label>Time</label>
                        <select class="form-control adjustment-time" name="adjustment_times[]" required>
                            <option value="">Select Time</option>
                            <option value="8:40 AM - 10:20 AM">8:40 AM - 10:20 AM</option>
                            <option value="10:30 AM - 12:10 PM">10:30 AM - 12:10 PM</option>
                            <option value="12:20 PM - 2:00 PM">12:20 PM - 2:00 PM</option>
                            <option value="2:10 PM - 3:50 PM">2:10 PM - 3:50 PM</option>
                            <option value="4:00 PM - 5:40 PM">4:00 PM - 5:40 PM</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="button" class="btn btn-sm btn-danger remove-permission-adjustment"><i class="fas fa-times"></i></button>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Subject</label>
                        <input type="text" class="form-control" name="adjustment_subjects[]" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Adjusted By</label>
                        <select class="form-control" name="adjustment_faculty[]" required>
                            <option value="">Select Faculty</option>
                            <?php foreach ($facultyMembers as $faculty): ?>
                                <option value="<?php echo $faculty['user_id']; ?>">
                                    <?php echo htmlspecialchars($faculty['first_name'] . ' ' . $faculty['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </template>

    <script>
        // Function to initialize datepicker for permission leave
        function initializePermissionDatePicker() {
            $('#permission_date').daterangepicker({
                singleDatePicker: true,
                autoUpdateInput: true,
                minDate: moment(),
                locale: {
                    format: 'YYYY-MM-DD'
                }
            });
        }
        
        // Function to handle permission leave submission
        function submitPermissionLeave() {
            const formData = {
                is_permission: 1,
                permission_date: $('#permission_date').val(),
                permission_slot: $('input[name="permission_slot"]:checked').val(),
                reason: $('#permission_reason').val(),
                adjustments: []
            };
            
            // Validate required fields
            if (!formData.permission_date || !formData.permission_slot || !formData.reason) {
                alert('Please fill in all required fields');
                return;
            }
            
            // Collect class adjustments
            $('.permission-adjustment-row').each(function() {
                const row = $(this);
                formData.adjustments.push({
                    date: row.find('.adjustment-date').val(),
                    time: row.find('.adjustment-time').val(),
                    subject: row.find('input[name^="adjustment_subjects"]').val(),
                    faculty_id: row.find('select[name^="adjustment_faculty"]').val()
                });
            });
            
            // Add adjustments to form data
            const $form = $('#leave_application_form');
            $form.find('input[name="is_permission"]').val('1');
            $form.find('input[name="leave_type_id"]').val('');
            $form.find('input[name="date_range"]').val(formData.permission_date);
            $form.find('textarea[name="reason"]').val(formData.reason);
            $form.find('input[name="permission_slot"]').remove();
            $form.append(`<input type="hidden" name="permission_slot" value="${formData.permission_slot}">`);
            
            // Submit the form
            $form.submit();
        }
        
        // Function to calculate working days
        function calculateWorkingDays(startDate, endDate, leaveTypeId) {
            $.ajax({
                url: 'ajax/calculate_days.php',
                type: 'POST',
                data: {
                    start_date: startDate,
                    end_date: endDate,
                    leave_type: leaveTypeId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#total_days').val(response.days);
                    }
                }
            });
        }
        
        // Function to calculate total days for casual leave
        function calculateCasualLeaveDays() {
            let dates = [];
            $('.casual-date-picker').each(function() {
                if ($(this).val()) {
                    dates.push($(this).val());
                }
            });
            
            if (dates.length > 0) {
                $('#total_days').val(dates.length);
                
                // Update hidden date_range field with concatenated dates for form submission
                $('#date_range').val(dates.join(','));
            } else {
                $('#total_days').val('0');
                $('#date_range').val('');
            }
        }
        
        // Function to update leave period based on class date
        function updateLeavePeriodFromClassDate(classDate) {
            const leaveTypeId = $('#leave_type_id').val();
            
            // If casual leave, add the date to the casual dates
            if (leaveTypeId === '2' || leaveTypeId === '3') { // Casual leave prior or emergency
                let dateExists = false;
                
                // Check if date already exists in casual dates
                $('.casual-date-picker').each(function() {
                    if ($(this).val() === classDate) {
                        dateExists = true;
                        return false;
                    }
                });
                
                // If date doesn't exist, add it
                if (!dateExists) {
                    // If there's an empty input, use that
                    let emptyInput = $('.casual-date-picker').filter(function() {
                        return !$(this).val();
                    }).first();
                    
                    if (emptyInput.length) {
                        emptyInput.val(classDate);
                    } else {
                        // Add new date input
                        $('#add_casual_date').click();
                        $('.casual-date-picker:last').val(classDate);
                    }
                    
                    // Recalculate total days
                    calculateCasualLeaveDays();
                }
            } else {
                // For other leave types, update the date range if empty or add to range
                const currentRange = $('#date_range').val();
                
                if (!currentRange) {
                    // If no date range set, set both start and end to the class date
                    $('#date_range').val(classDate + ' to ' + classDate);
                    calculateWorkingDays(classDate, classDate, leaveTypeId);
                } else {
                    // If date range exists, check if we need to expand it
                    const dates = currentRange.split(' to ');
                    const startDate = moment(dates[0], 'DD-MM-YYYY');
                    const endDate = moment(dates[1], 'DD-MM-YYYY');
                    const newDate = moment(classDate, 'DD-MM-YYYY');
                    
                    let updated = false;
                    
                    // If new date is before start date, update start date
                    if (newDate.isBefore(startDate)) {
                        startDate.set({
                            year: newDate.year(),
                            month: newDate.month(),
                            date: newDate.date()
                        });
                        updated = true;
                    }
                    
                    // If new date is after end date, update end date
                    if (newDate.isAfter(endDate)) {
                        endDate.set({
                            year: newDate.year(),
                            month: newDate.month(),
                            date: newDate.date()
                        });
                        updated = true;
                    }
                    
                    // If range was updated, update the input and recalculate days
                    if (updated) {
                        const newRange = startDate.format('DD-MM-YYYY') + ' to ' + endDate.format('DD-MM-YYYY');
                        $('#date_range').val(newRange);
                        calculateWorkingDays(startDate.format('YYYY-MM-DD'), endDate.format('YYYY-MM-DD'), leaveTypeId);
                    }
                }
            }
        }
        
        $(document).ready(function() {
            // Initialize permission date picker
            initializePermissionDatePicker();
            
            // Show permission leave modal
            $('#apply_permission_btn').on('click', function() {
                $('#permissionLeaveModal').modal('show');
            });
            
            // Submit permission leave
            $('#submit_permission_leave').on('click', submitPermissionLeave);
            
            // Add permission class adjustment
            $('#add_permission_class_adjustment').on('click', function() {
                const template = document.querySelector('#permission_class_adjustment_template');
                const clone = document.importNode(template.content, true);
                
                // Initialize date picker for the new row
                const dateInput = clone.querySelector('.adjustment-date');
                $(dateInput).daterangepicker({
                    singleDatePicker: true,
                    autoUpdateInput: true,
                    locale: {
                        format: 'YYYY-MM-DD'
                    }
                });
                
                // Add remove button functionality
                clone.querySelector('.remove-permission-adjustment').addEventListener('click', function() {
                    this.closest('.class-adjustment-row').remove();
                });
                
                // Append the new row
                document.getElementById('permission_class_adjustment_container').appendChild(clone);
            });
            
            // Remove permission class adjustment
            $(document).on('click', '.remove-permission-adjustment', function() {
                $(this).closest('.class-adjustment-row').remove();
            });
            
            // Reset permission form when modal is hidden
            $('#permissionLeaveModal').on('hidden.bs.modal', function() {
                $('#permission_leave_form')[0].reset();
                $('#permission_class_adjustment_container').empty();
            });
            // Initialize date range picker for regular leave
            $('.date-range-picker').daterangepicker({
                opens: 'left',
                autoUpdateInput: false,
                locale: {
                    cancelLabel: 'Clear',
                    format: 'DD-MM-YYYY'
                },
                minDate: moment().startOf('day')
            });
            
            $('.date-range-picker').on('apply.daterangepicker', function(ev, picker) {
                $(this).val(picker.startDate.format('DD-MM-YYYY') + ' to ' + picker.endDate.format('DD-MM-YYYY'));
                
                // Calculate working days
                calculateWorkingDays(
                    picker.startDate.format('YYYY-MM-DD'),
                    picker.endDate.format('YYYY-MM-DD'),
                    $('#leave_type_id').val()
                );
            });
            
            $('.date-range-picker').on('cancel.daterangepicker', function(ev, picker) {
                $(this).val('');
                $('#total_days').val('');
            });
            
            // Initialize single date picker for casual leave
            function initCasualDatePicker(element) {
                $(element).daterangepicker({
                    singleDatePicker: true,
                    showDropdowns: true,
                    autoUpdateInput: false,
                    minDate: moment().startOf('day'),
                    locale: {
                        format: 'DD-MM-YYYY'
                    }
                });
                
                $(element).on('apply.daterangepicker', function(ev, picker) {
                    $(this).val(picker.startDate.format('DD-MM-YYYY'));
                    calculateCasualLeaveDays();
                });
                
                $(element).on('cancel.daterangepicker', function(ev, picker) {
                    $(this).val('');
                    calculateCasualLeaveDays();
                });
            }
            
            // Initialize the first casual date picker
            initCasualDatePicker('.casual-date-picker');
            
            // Add more casual date inputs
            $('#add_casual_date').on('click', function() {
                const container = $('#casual_dates_container');
                const newRow = container.find('.date-input-container:first').clone();
                
                // Clear the input value
                newRow.find('input').val('');
                
                // Show the remove button
                newRow.find('.remove-date').show();
                
                // Add to container
                container.append(newRow);
                
                // Initialize date picker on the new input
                initCasualDatePicker(newRow.find('.casual-date-picker'));
            });
            
            // Remove casual date input
            $(document).on('click', '.remove-date', function() {
                $(this).closest('.date-input-container').remove();
                calculateCasualLeaveDays();
            });
            
            // Toggle between casual leave and regular leave date pickers
            $('#leave_type_id').on('change', function() {
                const leaveTypeId = $(this).val();
                const leaveType = $(this).find('option:selected').data('type');
                
                // Reset total days
                $('#total_days').val('');
                
                // Toggle date picker based on leave type
                if (leaveTypeId === '2' || leaveTypeId === '3') { // Casual leave prior or emergency
                    $('#casual_leave_container').show();
                    $('#regular_leave_container').hide();
                    $('#date_range').prop('required', false);
                    calculateCasualLeaveDays();
                } else {
                    $('#casual_leave_container').hide();
                    $('#regular_leave_container').show();
                    $('#date_range').prop('required', true);
                }
                
                // Show document upload for medical, maternity, academic and study leave
                if (leaveType && (leaveType.includes('medical_leave') || leaveType.includes('maternity_leave') || 
                    leaveType.includes('academic_leave') || leaveType.includes('study_leave'))) {
                    $('.document-upload-section').removeClass('d-none');
                    
                    // Update document upload label based on leave type
                    if (leaveType.includes('medical_leave') || leaveType.includes('maternity_leave')) {
                        $('#document-label').text('Upload Medical Certificate');
                    } else if (leaveType.includes('academic_leave') || leaveType.includes('study_leave')) {
                        $('#document-label').text('Upload Supporting Documents');
                    }
                } else {
                    $('.document-upload-section').addClass('d-none');
                }
                
                // Show warning for earned leave if balance is low
                if (leaveType && leaveType.includes('earned_leave')) {
                    const balanceText = $(this).find('option:selected').text();
                    const match = balanceText.match(/Balance: ([\d.]+)/);
                    if (match && parseFloat(match[1]) < 5) {
                        $('#earned-leave-warning').removeClass('d-none');
                    } else {
                        $('#earned-leave-warning').addClass('d-none');
                    }
                } else {
                    $('#earned-leave-warning').addClass('d-none');
                }
                
                // Show warning for paid leave
                if (leaveType && leaveType.includes('paid_leave')) {
                    $('#paid-leave-warning').removeClass('d-none');
                } else {
                    $('#paid-leave-warning').addClass('d-none');
                }
            });
            
            // Add class adjustment row
            $('#add_class_adjustment').on('click', function() {
                const template = document.querySelector('#class_adjustment_template');
                const clone = document.importNode(template.content, true);
                
                // Update row number and IDs
                const rowCount = $('#class_adjustments_container .class-adjustment-row').length + 1;
                $(clone).find('.row-number').text(rowCount);
                
                // Update checkbox ID to make it unique
                const uniqueId = 'faculty_confirmation_' + Date.now() + '_' + rowCount;
                $(clone).find('.faculty-confirmation').attr('id', uniqueId);
                $(clone).find('.custom-control-label').attr('for', uniqueId);
                
                $('#class_adjustments_container').append(clone);
                
                // Initialize date picker for the new row
                const newDatePicker = $('#class_adjustments_container .class-date-picker:last');
                newDatePicker.daterangepicker({
                    singleDatePicker: true,
                    showDropdowns: true,
                    autoUpdateInput: false,
                    minDate: moment().startOf('day'),
                    locale: {
                        format: 'DD-MM-YYYY'
                    }
                });
                
                newDatePicker.on('apply.daterangepicker', function(ev, picker) {
                    const selectedDate = picker.startDate.format('DD-MM-YYYY');
                    $(this).val(selectedDate);
                    
                    // Auto-populate leave period based on class date
                    updateLeavePeriodFromClassDate(selectedDate);
                });
                
                // Initialize faculty auto-suggest
                $(clone).find('.faculty-autosuggest').on('input', function() {
                    const input = $(this);
                    const suggestionsContainer = input.siblings('.faculty-suggestions');
                    const query = input.val().trim();
                    
                    if (query.length >= 2) {
                        $.ajax({
                            url: 'ajax/search_faculty.php',
                            type: 'GET',
                            data: { search: query },
                            dataType: 'json',
                            success: function(data) {
                                if (data.length > 0) {
                                    let html = '<ul>';
                                    data.forEach(function(faculty) {
                                        html += '<li data-id="' + faculty.id + '">' + faculty.name + '</li>';
                                    });
                                    html += '</ul>';
                                    suggestionsContainer.html(html).show();
                                } else {
                                    suggestionsContainer.hide();
                                }
                            }
                        });
                    } else {
                        suggestionsContainer.hide();
                    }
                });
                
                // Update row numbers
                updateClassAdjustmentRows();
                
                return false;
            });
            
            // Handle faculty suggestion selection
            $(document).on('click', '.faculty-suggestions li', function() {
                const facultyId = $(this).data('id');
                const facultyName = $(this).text();
                const container = $(this).closest('.form-group');
                
                container.find('.faculty-autosuggest').val(facultyName);
                container.find('input[name="adjusted_faculty_id[]"]').val(facultyId);
                container.find('.faculty-suggestions').hide();
            });
            
            // Hide suggestions when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.faculty-autosuggest, .faculty-suggestions').length) {
                    $('.faculty-suggestions').hide();
                }
            });
            
            // Remove class adjustment row
            $(document).on('click', '.remove-class-adjustment', function() {
                $(this).closest('.class-adjustment-row').remove();
                
                // Update row numbers
                updateClassAdjustmentRows();
                
                return false;
            });
            
            // Update row numbers function
            function updateClassAdjustmentRows() {
                $('.class-adjustment-row').each(function(index) {
                    $(this).find('.row-number').text(index + 1);
                });
            }
            
            // Form validation
            $('#leave_application_form').validate({
                rules: {
                    leave_type_id: {
                        required: true
                    },
                    date_range: {
                        required: function() {
                            const leaveTypeId = $('#leave_type_id').val();
                            return leaveTypeId !== '2' && leaveTypeId !== '3'; // Not required for casual leave types
                        }
                    },
                    reason: {
                        required: true,
                        minlength: 10
                    }
                },
                messages: {
                    leave_type_id: {
                        required: "Please select a leave type"
                    },
                    date_range: {
                        required: "Please select the leave date range"
                    },
                    reason: {
                        required: "Please provide a reason for your leave",
                        minlength: "Your reason must be at least 10 characters long"
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
                },
                submitHandler: function(form) {
                    // Validate casual leave dates
                    const leaveTypeId = $('#leave_type_id').val();
                    if (leaveTypeId === '2' || leaveTypeId === '3') { // Casual leave prior or emergency
                        let hasDate = false;
                        $('.casual-date-picker').each(function() {
                            if ($(this).val()) {
                                hasDate = true;
                                return false;
                            }
                        });
                        
                        if (!hasDate) {
                            alert('Please select at least one date for casual leave');
                            return false;
                        }
                    }
                    
                    // Validate class adjustments
                    if ($('.class-adjustment-row').length > 0) {
                        let valid = true;
                        $('.class-adjustment-row').each(function() {
                            const row = $(this);
                            
                            // Check if faculty is selected
                            if (!row.find('.faculty-autosuggest').val()) {
                                alert('Please select a faculty member for class adjustment');
                                valid = false;
                                return false;
                            }
                            
                            // Check if class date is selected
                            if (!row.find('.class-date-picker').val()) {
                                alert('Please select a date for class adjustment');
                                valid = false;
                                return false;
                            }
                            
                            // Check if all class details are filled
                            if (!row.find('input[name="class_year[]"]').val() ||
                                !row.find('input[name="class_branch[]"]').val() ||
                                !row.find('input[name="class_section[]"]').val() ||
                                !row.find('input[name="class_time[]"]').val() ||
                                !row.find('input[name="class_subject[]"]').val()) {
                                alert('Please fill all class details (Year, Branch, Section, Time, Subject)');
                                valid = false;
                                return false;
                            }
                        });
                        
                        if (!valid) return false;
                    }
                    
                    form.submit();
                }
            });
            
            // File input customization
            $('.custom-file-input').on('change', function() {
                let fileName = $(this).val().split('\\').pop();
                $(this).next('.custom-file-label').addClass("selected").html(fileName);
            });
        });
    </script>
</body>
</html>
