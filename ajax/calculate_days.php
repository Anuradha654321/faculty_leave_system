<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Check if this is a casual leave with multiple dates
if (isset($_POST['leave_type']) && $_POST['leave_type'] == '1' && isset($_POST['casual_dates'])) {
    $casualDates = $_POST['casual_dates'];
    $totalDays = count($casualDates);
    
    echo json_encode(['success' => true, 'days' => $totalDays]);
    exit;
}

// Get start and end dates from POST for regular leave
$startDate = isset($_POST['start_date']) ? $_POST['start_date'] : null;
$endDate = isset($_POST['end_date']) ? $_POST['end_date'] : null;

// Validate dates
if (empty($startDate) || empty($endDate)) {
    echo json_encode(['success' => false, 'message' => 'Start date and end date are required']);
    exit;
}

try {
    // Calculate working days
    $days = getWorkingDays($startDate, $endDate);
    
    // Return result
    echo json_encode(['success' => true, 'days' => $days]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
