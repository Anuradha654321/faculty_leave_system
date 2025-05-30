<?php
require_once __DIR__ . '/../config/database.php';

// Get user role name
function getUserRoleName($roleId) {
    $conn = connectDB();
    $query = "SELECT role_name FROM roles WHERE role_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $roleId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row['role_name'];
    }
    
    return "Unknown";
}
