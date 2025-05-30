<?php
// Set include path
set_include_path(dirname(__DIR__));

// Application configuration
session_start();

// Base URL - change this according to your server configuration
define('BASE_URL', 'http://localhost/faculty_leave_system/');

// Application title
define('APP_TITLE', 'Faculty Leave Management System');

// Include database configuration
require_once 'database.php';

// Include SMTP configuration
require_once 'smtp.php';

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Time zone
date_default_timezone_set('Asia/Kolkata');

// Authentication functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . 'login.php');
        exit();
    }
}

function hasRole($role) {
    if (!isLoggedIn()) {
        return false;
    }
    return $_SESSION['role'] === $role;
}

function checkPermission($requiredRoles) {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . 'login.php');
        exit();
    }
    
    if (!in_array($_SESSION['role'], $requiredRoles)) {
        header('Location: ' . BASE_URL . 'unauthorized.php');
        exit();
    }
}

// Utility functions
function redirect($url) {
    // If URL is not absolute, prepend BASE_URL
    if (!preg_match('#^(?:f|ht)tps?://#', $url)) {
        $url = BASE_URL . $url;
    }
    header('Location: ' . $url);
    exit();
}

function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

// Flash messages
function setFlashMessage($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

function getFlashMessage() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// Calculate working days between two dates (excluding weekends)
function getWorkingDays($startDate, $endDate) {
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    $end->modify('+1 day');
    
    $interval = new DateInterval('P1D');
    $dateRange = new DatePeriod($start, $interval, $end);
    
    $workingDays = 0;
    foreach ($dateRange as $date) {
        $dayOfWeek = $date->format('N');
        if ($dayOfWeek < 6) { // 1 (Monday) to 5 (Friday)
            $workingDays++;
        }
    }
    
    return $workingDays;
}
?>
