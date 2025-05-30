<nav id="sidebar" class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
    <div class="sidebar-sticky pt-3">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>index.php">
                    <i class="fas fa-tachometer-alt mr-2"></i> Dashboard
                </a>
            </li>
            
            <!-- Apply for Leave - Only for Faculty -->
            <?php if ($_SESSION['role'] == 'faculty'): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'apply_leave.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>apply_leave.php">
                        <i class="fas fa-file-alt mr-2"></i> Apply for Leave
                    </a>
                </li>
            <?php endif; ?>
            
            <!-- My Applications and Leave Balance - Only for Faculty -->
            <?php if ($_SESSION['role'] == 'faculty'): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'my_applications.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>my_applications.php">
                        <i class="fas fa-list-alt mr-2"></i> My Applications
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'leave_balance.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>leave_balance.php">
                        <i class="fas fa-calculator mr-2"></i> Leave Balance
                    </a>
                </li>
            <?php endif; ?>
            
            <!-- HOD Links -->
            <?php if ($_SESSION['role'] == 'hod' || $_SESSION['role'] == 'admin'): ?>
                <li class="nav-header">
                    <div class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                        <span>Department Management</span>
                    </div>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'pending_approvals.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>pending_approvals.php">
                        <i class="fas fa-clock mr-2"></i> Pending Approvals
                    </a>
                </li>
            <?php endif; ?>
            
            <!-- Admin Links -->
            <?php if ($_SESSION['role'] == 'admin'): ?>
                <li class="nav-header">
                    <div class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                        <span>Administration</span>
                    </div>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'register.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>register.php">
                        <i class="fas fa-user-plus mr-2"></i> Register User
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'manage_users.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>manage_users.php">
                        <i class="fas fa-users-cog mr-2"></i> Manage Users
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'manage_departments.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>manage_departments.php">
                        <i class="fas fa-building mr-2"></i> Manage Departments
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'manage_leave_types.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>manage_leave_types.php">
                        <i class="fas fa-clipboard-list mr-2"></i> Manage Leave Types
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'leave_reports.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>leave_reports.php">
                        <i class="fas fa-chart-bar mr-2"></i> Leave Reports
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'system_settings.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>system_settings.php">
                        <i class="fas fa-cogs mr-2"></i> System Settings
                    </a>
                </li>
            <?php endif; ?>
        </ul>
        
        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
            <span>User Account</span>
        </h6>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'profile.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>profile.php">
                    <i class="fas fa-user mr-2"></i> My Profile
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'change_password.php') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>change_password.php">
                    <i class="fas fa-key mr-2"></i> Change Password
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="<?php echo BASE_URL; ?>logout.php">
                    <i class="fas fa-sign-out-alt mr-2"></i> Logout
                </a>
            </li>
        </ul>
    </div>
</nav>
