<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

// Only allow this to be run from command line or localhost for security
$allowed = false;
if (php_sapi_name() === 'cli') {
    $allowed = true;
} else {
    $whitelist = array('127.0.0.1', '::1');
    if (in_array($_SERVER['REMOTE_ADDR'], $whitelist)) {
        $allowed = true;
    }
}

if (!$allowed) {
    die('Access denied. This script can only be run from localhost or command line.');
}

echo "Starting database update for permission leave feature...\n";

try {
    $conn = connectDB();
    
    if (!$conn) {
        throw new Exception("Database connection failed: " . mysqli_connect_error());
    }
    
    // Read the SQL file
    $sql = file_get_contents(__DIR__ . '/database/update_permission_leave.sql');
    
    if ($sql === false) {
        throw new Exception("Failed to read SQL file");
    }
    
    // Execute the SQL statements
    $queries = explode(';', $sql);
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($queries as $query) {
        $query = trim($query);
        if (!empty($query)) {
            try {
                $result = $conn->query($query);
                if ($result === false) {
                    throw new Exception($conn->error);
                }
                $successCount++;
                echo "Executed: " . substr($query, 0, 100) . (strlen($query) > 100 ? '...' : '') . "\n";
            } catch (Exception $e) {
                $errorCount++;
                echo "Error executing query: " . $e->getMessage() . "\n";
                echo "Query: " . substr($query, 0, 200) . (strlen($query) > 200 ? '...' : '') . "\n\n";
            }
        }
    }
    
    echo "\nDatabase update completed.\n";
    echo "Successful queries: $successCount\n";
    echo "Failed queries: $errorCount\n";
    
    if ($errorCount > 0) {
        echo "\nWARNING: Some queries failed to execute. Please check the errors above.\n";
    } else {
        echo "\nPermission leave feature has been successfully installed!\n";
    }
    
    closeDB($conn);
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
