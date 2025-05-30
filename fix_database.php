<?php
require_once 'config/config.php';

// Connect to the database
$conn = connectDB();

if (!$conn) {
    die("Database connection failed");
}

echo "<h1>Faculty Leave System Database Fix</h1>";

// Check if the last_updated column exists in leave_applications table
$checkColumnQuery = "SHOW COLUMNS FROM leave_applications LIKE 'last_updated'";
$checkResult = $conn->query($checkColumnQuery);

if ($checkResult->num_rows == 0) {
    // Column doesn't exist, add it
    echo "<p>Adding 'last_updated' column to leave_applications table...</p>";
    
    $alterQuery = "ALTER TABLE leave_applications 
                  ADD COLUMN last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
    
    if ($conn->query($alterQuery) === TRUE) {
        echo "<p>Successfully added 'last_updated' column to leave_applications table.</p>";
        
        // Update existing records to set last_updated to application_date
        $updateQuery = "UPDATE leave_applications SET last_updated = application_date";
        if ($conn->query($updateQuery) === TRUE) {
            echo "<p>Successfully updated existing records with application_date values.</p>";
        } else {
            echo "<p>Error updating existing records: " . $conn->error . "</p>";
        }
    } else {
        echo "<p>Error adding column: " . $conn->error . "</p>";
    }
} else {
    echo "<p>'last_updated' column already exists in leave_applications table.</p>";
}

// Check if the last_updated column exists in leave_balances table
$checkColumnQuery = "SHOW COLUMNS FROM leave_balances LIKE 'last_updated'";
$checkResult = $conn->query($checkColumnQuery);

if ($checkResult->num_rows == 0) {
    // Column doesn't exist, add it
    echo "<p>Adding 'last_updated' column to leave_balances table...</p>";
    
    $alterQuery = "ALTER TABLE leave_balances 
                  ADD COLUMN last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
    
    if ($conn->query($alterQuery) === TRUE) {
        echo "<p>Successfully added 'last_updated' column to leave_balances table.</p>";
    } else {
        echo "<p>Error adding column: " . $conn->error . "</p>";
    }
} else {
    echo "<p>'last_updated' column already exists in leave_balances table.</p>";
}

// Close the connection
closeDB($conn);

echo "<p><a href='index.php' class='btn btn-primary'>Return to Dashboard</a></p>";
?>
