<?php
require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Require login to access this page
requireLogin();

// Only faculty, HOD, central admin, and admin can search faculty
if (!in_array($_SESSION['role'], ['faculty', 'hod', 'central_admin', 'admin'])) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}

$search = $_GET['search'] ?? '';
if (empty($search)) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}

$conn = connectDB();
if (!$conn) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}

// Search faculty members
$query = "SELECT user_id, first_name, last_name 
          FROM users 
          WHERE role_id = (SELECT role_id FROM roles WHERE role_name = 'faculty') 
          AND dept_id = ? 
          AND (first_name LIKE ? OR last_name LIKE ?)
          AND status = 'active'
          LIMIT 10";

$searchTerm = "%" . $search . "%";
$stmt = $conn->prepare($query);
$stmt->bind_param("iss", $_SESSION['dept_id'], $searchTerm, $searchTerm);
$stmt->execute();
$result = $stmt->get_result();

$faculty = [];
while ($row = $result->fetch_assoc()) {
    $faculty[] = [
        'id' => $row['user_id'],
        'name' => $row['first_name'] . ' ' . $row['last_name']
    ];
}

$stmt->close();
$conn->close();

header('Content-Type: application/json');
echo json_encode($faculty);
