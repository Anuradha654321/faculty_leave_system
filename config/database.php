<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'faculty_leave_system');

// Create database connection
function connectDB() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Check connection
    if ($conn->connect_error) {
        error_log("Database connection failed: " . $conn->connect_error);
        return false;
    }
    
    // Set character set
    $conn->set_charset("utf8mb4");
    
    // Check if connection is still alive
    if (!$conn->ping()) {
        error_log("Database connection lost");
        return false;
    }
    
    return $conn;
}

// Close database connection
function closeDB($conn) {
    $conn->close();
}
?>
