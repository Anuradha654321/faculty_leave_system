-- Add hod_id column to leave_applications table if it doesn't exist
ALTER TABLE leave_applications ADD COLUMN IF NOT EXISTS hod_id INT NULL;

-- Add last_updated column to leave_balances table if it doesn't exist
ALTER TABLE leave_balances ADD COLUMN IF NOT EXISTS last_updated DATETIME NULL;
