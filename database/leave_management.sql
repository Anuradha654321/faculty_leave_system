-- Create database if not exists
CREATE DATABASE IF NOT EXISTS faculty_leave_system;
USE faculty_leave_system;

-- Roles table
CREATE TABLE IF NOT EXISTS roles (
    role_id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL UNIQUE
);

-- Insert default roles (only if table is empty)
INSERT INTO roles (role_name)
SELECT 'admin' WHERE NOT EXISTS (SELECT 1 FROM roles WHERE role_name = 'admin');

INSERT INTO roles (role_name)
SELECT 'hod' WHERE NOT EXISTS (SELECT 1 FROM roles WHERE role_name = 'hod');

INSERT INTO roles (role_name)
SELECT 'faculty' WHERE NOT EXISTS (SELECT 1 FROM roles WHERE role_name = 'faculty');

INSERT INTO roles (role_name)
SELECT 'central_admin' WHERE NOT EXISTS (SELECT 1 FROM roles WHERE role_name = 'central_admin');

-- Departments table
CREATE TABLE IF NOT EXISTS departments (
    dept_id INT AUTO_INCREMENT PRIMARY KEY,
    dept_name VARCHAR(100) NOT NULL UNIQUE
);

-- Insert some sample departments
INSERT INTO departments (dept_name) VALUES 
('Computer Science'),
('Information Technology'),
('Electronics'),
('Mechanical'),
('Civil'),
('Electrical'),
('CSE(AI-ML)');

-- Users table
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    role_id INT NOT NULL,
    dept_id INT,
    phone VARCHAR(15),
    date_joined DATE NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(role_id),
    FOREIGN KEY (dept_id) REFERENCES departments(dept_id)
);

-- Leave types table
CREATE TABLE IF NOT EXISTS leave_types (
    type_id INT AUTO_INCREMENT PRIMARY KEY,
    type_name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    default_balance FLOAT NOT NULL,
    requires_document TINYINT(1) DEFAULT 0,
    requires_hod_approval TINYINT(1) DEFAULT 1,
    requires_central_admin_approval TINYINT(1) DEFAULT 0,
    requires_admin_approval TINYINT(1) DEFAULT 0
);

-- Insert leave types
INSERT INTO leave_types (type_name, description, default_balance, requires_document) VALUES
('academic_leave', 'Leave for academic purposes such as attending conferences, workshops, etc.', 10, 0),
('casual_leave_prior', 'Casual leave with prior notice (at least 3 days in advance)', 6, 0),
('casual_leave_emergency', 'Emergency casual leave for urgent matters (less than 3 days notice)', 6, 0),
('earned_leave', 'Leave earned by working on holidays or overtime', 30, 0),
('maternity_leave', 'Leave for female employees for childbirth and childcare', 180, 1),
('medical_leave', 'Leave for medical reasons with supporting documents', 15, 1),
('on_duty_leave', 'Leave for official duty outside the institution', 5, 0),
('special_leave', 'Special leave granted for exceptional circumstances', 5, 0),
('study_leave', 'Leave for higher studies or professional development', 730, 1);

-- Leave applications table
CREATE TABLE IF NOT EXISTS leave_applications (
    application_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    leave_type_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    total_days FLOAT NOT NULL,
    working_days FLOAT NOT NULL,
    reason TEXT NOT NULL,
    document_path VARCHAR(255),
    application_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
    hod_approval ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    hod_remarks TEXT,
    hod_action_date DATETIME,
    admin_approval ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    admin_remarks TEXT,
    admin_action_date DATETIME,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (leave_type_id) REFERENCES leave_types(type_id)
);

-- Leave balances table
CREATE TABLE IF NOT EXISTS leave_balances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    leave_type_id INT NOT NULL,
    year INT NOT NULL,
    total_days FLOAT NOT NULL,
    used_days FLOAT DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (leave_type_id) REFERENCES leave_types(type_id),
    UNIQUE KEY (user_id, leave_type_id, year)
);

-- Class adjustments table (for casual leaves)
CREATE TABLE IF NOT EXISTS class_adjustments (
    adjustment_id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL,
    class_date DATE NOT NULL,
    class_time VARCHAR(50) NOT NULL,
    subject VARCHAR(100) NOT NULL,
    adjusted_by INT,
    status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
    remarks TEXT,
    FOREIGN KEY (application_id) REFERENCES leave_applications(application_id),
    FOREIGN KEY (adjusted_by) REFERENCES users(user_id)
);

-- System settings table
CREATE TABLE IF NOT EXISTS system_settings (
    setting_id INT AUTO_INCREMENT PRIMARY KEY,
    setting_name VARCHAR(50) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Notifications table
CREATE TABLE IF NOT EXISTS notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- Insert default settings
INSERT INTO system_settings (setting_name, setting_value, description) VALUES
('app_name', 'Faculty Leave Management System', 'Name of the application'),
('academic_year_start', '06-01', 'Start date of academic year (MM-DD)'),
('academic_year_end', '05-31', 'End date of academic year (MM-DD)'),
('weekend_days', '0,6', 'Weekend days (0=Sunday, 6=Saturday)'),
('notification_email', 'admin@example.com', 'Email for sending notifications');

-- Create a default admin user (username: admin, password: password)
INSERT INTO users (username, password, first_name, last_name, email, role_id, dept_id, date_joined, status)
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System', 'Administrator', 'admin@example.com', 1, 1, CURDATE(), 'active');
