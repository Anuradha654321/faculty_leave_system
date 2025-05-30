<?php
// Email configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-specific-password');
define('SMTP_FROM_EMAIL', 'your-email@gmail.com');
define('SMTP_FROM_NAME', 'Faculty Leave System');

define('EMAIL_TEMPLATES', [
    'LEAVE_APPLIED' => [
        'subject' => 'Leave Application Submitted',
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
]);
