-- Add permission leave type if it doesn't exist
INSERT IGNORE INTO leave_types (type_name, description, default_balance, requires_document, requires_hod_approval, requires_central_admin_approval, requires_admin_approval)
VALUES ('permission_leave', 'Short duration permission for specific time slots', 30, 0, 1, 0, 0);

-- Add columns to leave_applications table if they don't exist
SET @dbname = DATABASE();
SET @tablename = 'leave_applications';
SET @columnname = 'is_permission';
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE (table_schema = @dbname)
        AND (table_name = @tablename)
        AND (column_name = @columnname)
    ) = 0,
    "ALTER TABLE leave_applications ADD COLUMN is_permission TINYINT(1) DEFAULT 0 AFTER total_days",
    'SELECT 1;'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @columnname = 'permission_slot';
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE (table_schema = @dbname)
        AND (table_name = @tablename)
        AND (column_name = @columnname)
    ) = 0,
    "ALTER TABLE leave_applications ADD COLUMN permission_slot VARCHAR(20) NULL AFTER is_permission",
    'SELECT 1;'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;
