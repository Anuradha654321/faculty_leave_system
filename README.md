# Faculty Leave Management System

A comprehensive leave management system for educational institutions with support for different types of leaves, class adjustments, and multi-level approval workflows.

## Features

- **Multiple User Roles**: Faculty, HOD, Central Admin (Office), and Admin (Principal/Vice-Principal)
- **Various Leave Types**: 
  - Casual Leave (Prior and Emergency)
  - Earned Leave
  - Medical Leave
  - Maternity Leave
  - Academic Leave
  - Study Leave
  - On Duty Leave
  - On Other Duty Leave
  - Special Leave
  - Paid Leave
- **Class Adjustment System**: For casual leaves with faculty approval workflow
- **Multi-level Approval Process**: Based on leave type and duration
- **Document Upload**: For medical and other leave types requiring documentation
- **Leave Register**: Track all leave balances and history
- **Notifications System**: Automatic notifications for approvals and rejections
- **Responsive Design**: Works on desktop and mobile devices

## System Requirements

- PHP 7.2 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- XAMPP/WAMP/LAMP stack (recommended for easy setup)

## Installation

1. **Clone or download the repository**
   Place the project folder in your web server's document root (e.g., `htdocs` for XAMPP)

2. **Create the database**
   - Open phpMyAdmin or your MySQL client
   - Create a new database named `faculty_leave_system`
   - Import the SQL file from `database/leave_management.sql`

3. **Configure the application**
   - Open `config/database.php`
   - Update the database connection settings if needed:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_USER', 'root');
     define('DB_PASS', '');
     define('DB_NAME', 'faculty_leave_system');
     ```
   - Open `config/config.php`
   - Update the base URL if needed:
     ```php
     define('BASE_URL', 'http://localhost/faculty_leave_system/');
     ```

4. **Set up folder permissions**
   - Make sure the `uploads` directory is writable by the web server

5. **Create initial admin user**
   - Run the following SQL query to create an admin user:
     ```sql
     -- First, add a department
     INSERT INTO departments (dept_name) VALUES ('Administration');
     
     -- Then, create the admin user (password: admin123)
     INSERT INTO users (username, password, first_name, last_name, email, role_id, dept_id, phone, date_joined, status)
     VALUES ('admin', '$2y$10$KXbJJ4/jkO9Hm6WyYP6Y8.YKRoiG/uVWMqVSqHgX7Ol5vwjJU5W16', 'System', 'Administrator', 'admin@example.com', 
     (SELECT role_id FROM roles WHERE role_name = 'admin'), 
     (SELECT dept_id FROM departments WHERE dept_name = 'Administration'), 
     '1234567890', CURDATE(), 'active');
     ```

6. **Access the system**
   - Open your web browser and navigate to: `http://localhost/faculty_leave_system/`
   - Login with the admin credentials:
     - Username: `admin`
     - Password: `admin123`

## System Structure

### Key Directories

- `config/`: Configuration files
- `database/`: Database schema
- `includes/`: Common include files (header, footer, sidebar)
- `assets/`: CSS, JavaScript, and other static files
- `uploads/`: Uploaded documents
- `ajax/`: AJAX handlers

### Key Files

- `index.php`: Dashboard
- `login.php`: User authentication
- `apply_leave.php`: Leave application form
- `my_applications.php`: View user's leave applications
- `view_application.php`: Detailed view of a leave application
- `review_application.php`: For approving/rejecting leave applications
- `leave_register.php`: View leave balances and history
- `class_adjustments.php`: Manage class adjustments

## User Roles and Permissions

1. **Faculty**
   - Apply for leaves
   - View own leave applications
   - Cancel pending leave applications
   - Manage class adjustments

2. **HOD (Head of Department)**
   - All faculty permissions
   - Approve/reject leave applications from department faculty
   - View department leave register

3. **Central Admin (Office)**
   - Approve/reject leave applications forwarded by HODs
   - Generate reports
   - Manage leave balances

4. **Admin (Principal/Vice-Principal)**
   - Final approval for certain leave types
   - Manage users and departments
   - Configure system settings
   - Access all system features

## Leave Application Workflow

1. Faculty submits leave application
2. For casual leave, faculty arranges class adjustments
3. HOD reviews and approves/rejects
4. If approved and required, application goes to Central Admin
5. If required, application goes to Admin for final approval
6. Notifications sent at each stage

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Support

For any issues or questions, please contact the system administrator.
