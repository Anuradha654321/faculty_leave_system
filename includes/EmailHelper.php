<?php
// Direct PHPMailer includes
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';
require_once __DIR__ . '/../config/smtp.php';

class EmailHelper {
    private $mailer;

    public function __construct() {
        $this->mailer = new PHPMailer\PHPMailer\PHPMailer(true);
        
        try {
            // Disable debug output to prevent headers already sent warning
            $this->mailer->SMTPDebug = 0; // 0 = no output
            $this->mailer->Debugoutput = 'error_log'; // Send to error log instead of output
            
            // Server settings
            $this->mailer->isSMTP();
            $this->mailer->Host = SMTP_HOST;
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = SMTP_USERNAME;
            $this->mailer->Password = SMTP_PASSWORD;
            $this->mailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $this->mailer->Port = SMTP_PORT;
            
            // Disable SSL certificate verification
            $this->mailer->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];
            
            // Sender settings
            $this->mailer->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            
            // Test connection
            if (!$this->mailer->smtpConnect()) {
                error_log("SMTP Connection Error: " . $this->mailer->ErrorInfo);
                throw new Exception("Failed to connect to SMTP server: " . $this->mailer->ErrorInfo);
            }
            
        } catch (Exception $e) {
            error_log("Email Service Initialization Error: " . $e->getMessage());
            throw new Exception("Failed to initialize email service: " . $e->getMessage());
        }
    }

    public function sendLeaveAppliedEmail($to, $leaveId, $leaveType, $startDate, $endDate, $reason) {
        try {
            // Reset mailer instance
            $this->mailer->clearAllRecipients();
            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();
            $this->mailer->clearCustomHeaders();
            
            // Set recipient
            $this->mailer->addAddress($to);
            
            // Email content
            $this->mailer->isHTML(true);
            $this->mailer->Subject = "Leave Application Received (ID: $leaveId)";
            
            // Create email body
            $body = "<h2>Leave Application Received</h2>";
            $body .= "<p>Dear Faculty Member,</p>";
            $body .= "<p>Your leave application has been submitted successfully. Here are the details:</p>";
            $body .= "<ul>";
            $body .= "<li><strong>Leave Type:</strong> $leaveType</li>";
            $body .= "<li><strong>Start Date:</strong> " . date('d-m-Y', strtotime($startDate)) . "</li>";
            $body .= "<li><strong>End Date:</strong> " . date('d-m-Y', strtotime($endDate)) . "</li>";
            $body .= "<li><strong>Reason:</strong> $reason</li>";
            $body .= "</ul>";
            $body .= "<p>Please review and take appropriate action.</p>";
            $body .= "<p>Best regards,<br>Faculty Leave System</p>";
            
            $this->mailer->Body = $body;
            
            // Send email
            $result = $this->mailer->send();
            
            if (!$result) {
                error_log("Email sending failed: " . $this->mailer->ErrorInfo);
                return false;
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            return false;
        }
    }

    public function sendLeaveApprovedEmail($to, $leaveId, $leaveType, $startDate, $endDate, $approver) {
        try {
            // Reset mailer instance
            $this->mailer->clearAllRecipients();
            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();
            $this->mailer->clearCustomHeaders();
            
            // Set recipient
            $this->mailer->addAddress($to);
            
            // Email content
            $this->mailer->isHTML(true);
            $this->mailer->Subject = "Leave Application Approved (ID: $leaveId)";
            
            // Create email body
            $body = "<h2>Leave Application Approved</h2>";
            $body .= "<p>Dear Faculty Member,</p>";
            $body .= "<p>Your leave application has been approved by $approver. Here are the details:</p>";
            $body .= "<ul>";
            $body .= "<li><strong>Leave Type:</strong> $leaveType</li>";
            $body .= "<li><strong>Start Date:</strong> " . date('d-m-Y', strtotime($startDate)) . "</li>";
            $body .= "<li><strong>End Date:</strong> " . date('d-m-Y', strtotime($endDate)) . "</li>";
            $body .= "</ul>";
            $body .= "<p>Best regards,<br>Faculty Leave System</p>";
            
            $this->mailer->Body = $body;
            
            // Send email
            $result = $this->mailer->send();
            
            if (!$result) {
                error_log("Email sending failed: " . $this->mailer->ErrorInfo);
                return false;
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            return false;
        }
    }

    public function sendLeaveRejectedEmail($to, $leaveId, $leaveType, $startDate, $endDate, $reason) {
        try {
            // Reset mailer instance
            $this->mailer->clearAllRecipients();
            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();
            $this->mailer->clearCustomHeaders();
            
            // Set recipient
            $this->mailer->addAddress($to);
            
            // Email content
            $this->mailer->isHTML(true);
            $this->mailer->Subject = "Leave Application Rejected (ID: $leaveId)";
            
            // Create email body
            $body = "<h2>Leave Application Rejected</h2>";
            $body .= "<p>Dear Faculty Member,</p>";
            $body .= "<p>Your leave application has been rejected. Here are the details:</p>";
            $body .= "<ul>";
            $body .= "<li><strong>Leave Type:</strong> $leaveType</li>";
            $body .= "<li><strong>Start Date:</strong> " . date('d-m-Y', strtotime($startDate)) . "</li>";
            $body .= "<li><strong>End Date:</strong> " . date('d-m-Y', strtotime($endDate)) . "</li>";
            $body .= "<li><strong>Reason:</strong> $reason</li>";
            $body .= "</ul>";
            $body .= "<p>Best regards,<br>Faculty Leave System</p>";
            
            $this->mailer->Body = $body;
            
            // Send email
            $result = $this->mailer->send();
            
            if (!$result) {
                error_log("Email sending failed: " . $this->mailer->ErrorInfo);
                return false;
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            return false;
        }
    }

    public function sendClassAdjustmentRequestEmail($to, $facultyName, $className, $date, $time) {
        $data = [
            'faculty_name' => $facultyName,
            'class_name' => $className,
            'date' => $date,
            'time' => $time
        ];
        return $this->sendEmail($to, 'CLASS_ADJUSTMENT_REQUEST', $data);
    }

    public function sendClassAdjustmentApprovedEmail($to, $facultyName, $className, $date, $time) {
        $data = [
            'faculty_name' => $facultyName,
            'class_name' => $className,
            'date' => $date,
            'time' => $time
        ];
        return $this->sendEmail($to, 'CLASS_ADJUSTMENT_APPROVED', $data);
    }

    public function sendPasswordResetEmail($to, $resetLink) {
        $data = [
            'reset_link' => $resetLink
        ];
        return $this->sendEmail($to, 'PASSWORD_RESET', $data);
    }
}
