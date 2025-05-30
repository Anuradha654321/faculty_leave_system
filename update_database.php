<?php
require_once 'config/config.php';

// Connect to database
$conn = connectDB();

if (!$conn) {
    die("Database connection failed");
}

echo "<h2>Faculty Leave System - Database Update</h2>";

// Execute SQL updates
try {
    // Add hod_id column to leave_applications if it doesn't exist
    $checkColumnQuery = "SHOW COLUMNS FROM leave_applications LIKE 'hod_id'";
    $result = $conn->query($checkColumnQuery);
    
    if ($result->num_rows == 0) {
        $conn->query("ALTER TABLE leave_applications ADD COLUMN hod_id INT NULL");
        echo "<p>Added hod_id column to leave_applications table.</p>";
    } else {
        echo "<p>hod_id column already exists in leave_applications table.</p>";
    }
    
    // Add last_updated column to leave_balances if it doesn't exist
    $checkColumnQuery = "SHOW COLUMNS FROM leave_balances LIKE 'last_updated'";
    $result = $conn->query($checkColumnQuery);
    
    if ($result->num_rows == 0) {
        $conn->query("ALTER TABLE leave_balances ADD COLUMN last_updated DATETIME NULL");
        echo "<p>Added last_updated column to leave_balances table.</p>";
    } else {
        echo "<p>last_updated column already exists in leave_balances table.</p>";
    }
    
    echo "<p style='color:green;'>Database update completed successfully!</p>";
    echo "<p><a href='index.php'>Return to Dashboard</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color:red;'>Error updating database: " . $e->getMessage() . "</p>";
}

closeDB($conn);
?>
