<?php
// SMTP Configuration

// For Gmail SMTP
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USERNAME', 'gayathrisistu.gcsj@gmail.com');
define('SMTP_PASSWORD', 'rysn esiv uqit daen');
define('SMTP_PORT', 587);
define('SMTP_FROM_EMAIL', 'gayathrisistu.gcsj@gmail.com');
define('SMTP_FROM_NAME', 'Faculty Leave System');

// For Office 365 SMTP
// define('SMTP_HOST', 'smtp.office365.com');
// define('SMTP_USERNAME', 'your-email@domain.com');
// define('SMTP_PASSWORD', 'your-password');
// define('SMTP_PORT', 587);
// define('SMTP_FROM_EMAIL', 'your-email@domain.com');
// define('SMTP_FROM_NAME', 'Faculty Leave System');

// For custom SMTP server
// define('SMTP_HOST', 'your-smtp-server.com');
// define('SMTP_USERNAME', 'your-username');
// define('SMTP_PASSWORD', 'your-password');
// define('SMTP_PORT', 587);
// define('SMTP_FROM_EMAIL', 'noreply@your-domain.com');
// define('SMTP_FROM_NAME', 'Faculty Leave System');

// Email Templates
$EMAIL_TEMPLATES = [
    'LEAVE_APPLIED' => [
        'subject' => 'Leave Application Received',
        'template' => 'leave_applied.html'
    ],
    'LEAVE_APPROVED' => [
        'subject' => 'Leave Application Approved',
        'template' => 'leave_approved.html'
    ],
    'LEAVE_REJECTED' => [
        'subject' => 'Leave Application Rejected',
        'template' => 'leave_rejected.html'
    ],
    'CLASS_ADJUSTMENT_REQUEST' => [
        'subject' => 'Class Adjustment Request',
        'template' => 'class_adjustment_request.html'
    ],
    'CLASS_ADJUSTMENT_APPROVED' => [
        'subject' => 'Class Adjustment Approved',
        'template' => 'class_adjustment_approved.html'
    ],
    'PASSWORD_RESET' => [
        'subject' => 'Password Reset Request',
        'template' => 'password_reset.html'
    ]
];
