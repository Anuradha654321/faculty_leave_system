<?php
require_once 'config/config.php';

// Destroy the session
session_start();
$_SESSION = array();
session_destroy();

// Redirect to login page
redirect(BASE_URL . 'login.php');
?>
