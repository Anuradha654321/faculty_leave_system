-- Add last_updated column to leave_applications table
ALTER TABLE leave_applications 
ADD COLUMN IF NOT EXISTS last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Update existing records to set last_updated to application_date
UPDATE leave_applications SET last_updated = application_date;

-- Make sure the leave_types table has all required columns
ALTER TABLE leave_types 
ADD COLUMN IF NOT EXISTS requires_document TINYINT(1) DEFAULT 0,
ADD COLUMN IF NOT EXISTS requires_hod_approval TINYINT(1) DEFAULT 1,
ADD COLUMN IF NOT EXISTS requires_central_admin_approval TINYINT(1) DEFAULT 0,
ADD COLUMN IF NOT EXISTS requires_admin_approval TINYINT(1) DEFAULT 0;

-- Update existing leave types with proper configurations
UPDATE leave_types SET requires_document = 0, requires_hod_approval = 1, requires_central_admin_approval = 1, requires_admin_approval = 1 WHERE type_name = 'academic_leave';
UPDATE leave_types SET requires_document = 0, requires_hod_approval = 1, requires_central_admin_approval = 0, requires_admin_approval = 0 WHERE type_name = 'casual_leave_prior';
UPDATE leave_types SET requires_document = 0, requires_hod_approval = 1, requires_central_admin_approval = 0, requires_admin_approval = 0 WHERE type_name = 'casual_leave_emergency';
UPDATE leave_types SET requires_document = 0, requires_hod_approval = 1, requires_central_admin_approval = 0, requires_admin_approval = 0 WHERE type_name = 'earned_leave';
UPDATE leave_types SET requires_document = 1, requires_hod_approval = 1, requires_central_admin_approval = 0, requires_admin_approval = 1 WHERE type_name = 'maternity_leave';
UPDATE leave_types SET requires_document = 1, requires_hod_approval = 1, requires_central_admin_approval = 0, requires_admin_approval = 1 WHERE type_name = 'medical_leave';

-- Add missing leave types
INSERT IGNORE INTO leave_types (type_name, description, default_balance) VALUES
('study_leave', 'Leave for study purposes - requires central admin and admin approval', 30),
('on_duty_leave', 'Leave for official college/university work', 15),
('on_other_duty_leave', 'Leave for other official duties not directly related to university work', 15),
('special_leave', 'Special leave for exceptional circumstances', 5),
('paid_leave', 'Leave with payment deduction - when all other leave balances are exhausted', 30);

-- Update the newly added leave types with proper configurations
UPDATE leave_types SET requires_document = 1, requires_hod_approval = 1, requires_central_admin_approval = 1, requires_admin_approval = 1 WHERE type_name = 'study_leave';
UPDATE leave_types SET requires_document = 0, requires_hod_approval = 1, requires_central_admin_approval = 0, requires_admin_approval = 0 WHERE type_name = 'on_duty_leave';
UPDATE leave_types SET requires_document = 0, requires_hod_approval = 1, requires_central_admin_approval = 0, requires_admin_approval = 0 WHERE type_name = 'on_other_duty_leave';
UPDATE leave_types SET requires_document = 0, requires_hod_approval = 1, requires_central_admin_approval = 1, requires_admin_approval = 1 WHERE type_name = 'special_leave';
UPDATE leave_types SET requires_document = 0, requires_hod_approval = 1, requires_central_admin_approval = 0, requires_admin_approval = 1 WHERE type_name = 'paid_leave';

-- Make sure class_adjustments table has faculty_confirmation column
ALTER TABLE class_adjustments 
ADD COLUMN IF NOT EXISTS faculty_confirmation TINYINT(1) DEFAULT 0;

-- Update the status enum in leave_applications table to include approved_by_hod status
ALTER TABLE leave_applications 
MODIFY COLUMN status ENUM('pending', 'approved_by_hod', 'approved', 'rejected', 'cancelled') DEFAULT 'pending';
