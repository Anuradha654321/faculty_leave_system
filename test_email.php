<?php
require_once 'config/config.php';
require_once 'includes/EmailHelper.php';

try {
    // Create email helper instance
    $emailHelper = new EmailHelper();
    
    // Test email data
    $testEmail = 'gayathri.sistu@gmail.com'; // Using one of the existing email addresses
    $testSubject = 'Test Email from Faculty Leave System';
    $testMessage = 'This is a test email to verify that the email system is working properly.';
    
    // Send test email
    $result = $emailHelper->sendLeaveAppliedEmail(
        $testEmail,
        '12345', // leaveId
        'Test Leave', // leaveType
        date('Y-m-d'), // startDate
        date('Y-m-d', strtotime('+1 day')), // endDate
        $testMessage // reason
    );
    
    if ($result) {
        echo "Email sent successfully!\n";
    } else {
        echo "Email failed to send.\n";
        echo "Error: " . $emailHelper->mailer->ErrorInfo . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    error_log("Email test failed: " . $e->getMessage());
}
?>
