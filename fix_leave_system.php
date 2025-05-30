<?php
require_once 'config/config.php';

// Require login to access this page
requireLogin();

echo "<h2>Faculty Leave System - Quick Fix</h2>";

// Connect to database
$conn = connectDB();
if (!$conn) {
    die("Database connection failed");
}

try {
    // 1. Fix the leave application approval process
    $conn->query("UPDATE leave_applications SET status = 'approved' WHERE hod_approval = 'approved' AND status = 'pending'");
    echo "<p>Fixed leave applications with pending status but approved by HOD.</p>";
    
    // 2. Update leave balances for all approved applications
    $query = "SELECT la.application_id, la.user_id, la.leave_type_id, la.total_days 
              FROM leave_applications la 
              WHERE la.status = 'approved'";
    $result = $conn->query($query);
    
    $currentYear = date('Y');
    $updatedCount = 0;
    
    while ($row = $result->fetch_assoc()) {
        // Check if balance exists
        $checkQuery = "SELECT id, used_days FROM leave_balances 
                      WHERE user_id = ? AND leave_type_id = ? AND year = ?";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bind_param("iii", $row['user_id'], $row['leave_type_id'], $currentYear);
        $checkStmt->execute();
        $balanceResult = $checkStmt->get_result();
        
        if ($balanceResult->num_rows > 0) {
            // Update existing balance
            $balanceRow = $balanceResult->fetch_assoc();
            $updateQuery = "UPDATE leave_balances SET used_days = used_days + ? WHERE id = ?";
            $updateStmt = $conn->prepare($updateQuery);
            $updateStmt->bind_param("di", $row['total_days'], $balanceRow['id']);
            $updateStmt->execute();
        } else {
            // Create new balance
            $insertQuery = "INSERT INTO leave_balances (user_id, leave_type_id, year, total_days, used_days) 
                          VALUES (?, ?, ?, 30, ?)"; // Default 30 days
            $insertStmt = $conn->prepare($insertQuery);
            $insertStmt->bind_param("iiid", $row['user_id'], $row['leave_type_id'], $currentYear, $row['total_days']);
            $insertStmt->execute();
        }
        
        $updatedCount++;
    }
    
    echo "<p>Updated leave balances for $updatedCount approved applications.</p>";
    
    // 3. Fix the pending_approvals.php file to not use hod_id
    $pendingApprovalsFile = file_get_contents('pending_approvals.php');
    $pendingApprovalsFile = str_replace(
        "UPDATE leave_applications SET status = ?, last_updated = NOW(), hod_id = ? WHERE application_id = ?", 
        "UPDATE leave_applications SET status = ?, last_updated = NOW() WHERE application_id = ?", 
        $pendingApprovalsFile
    );
    $pendingApprovalsFile = str_replace(
        '$updateStatusStmt->bind_param("sii", $newStatus, $hodId, $applicationId);', 
        '$updateStatusStmt->bind_param("si", $newStatus, $applicationId);', 
        $pendingApprovalsFile
    );
    file_put_contents('pending_approvals.php', $pendingApprovalsFile);
    
    echo "<p>Fixed pending_approvals.php file to not use hod_id column.</p>";
    
    echo "<p style='color:green;'>All fixes completed successfully!</p>";
    echo "<p><a href='notifications.php'>Return to Notifications</a> | <a href='pending_approvals.php'>View Pending Approvals</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
}

closeDB($conn);
?>
