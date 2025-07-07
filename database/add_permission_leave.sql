-- Add permission leave type
INSERT INTO leave_types (type_name, description, default_balance, requires_document, requires_hod_approval, requires_central_admin_approval, requires_admin_approval)
SELECT 'permission_leave', 'Short duration permission for specific time slots', 30, 0, 1, 0, 0
WHERE NOT EXISTS (SELECT 1 FROM leave_types WHERE type_name = 'permission_leave');

-- Add permission_slot column to leave_applications table
ALTER TABLE leave_applications 
ADD COLUMN permission_slot ENUM('morning', 'afternoon') NULL DEFAULT NULL AFTER total_days,
ADD COLUMN is_permission TINYINT(1) DEFAULT 0 AFTER permission_slot;
