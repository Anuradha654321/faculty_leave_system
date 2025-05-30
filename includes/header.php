<header class="navbar navbar-dark sticky-top bg-dark flex-md-nowrap p-0 shadow">
    <a class="navbar-brand col-md-3 col-lg-2 mr-0 px-3" href="<?php echo BASE_URL; ?>">
        <?php echo APP_TITLE; ?>
    </a>
    <button class="navbar-toggler position-absolute d-md-none collapsed" type="button" data-toggle="collapse" data-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>
    
    <ul class="navbar-nav px-3 ml-auto">
        <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="fas fa-bell"></i>
                <?php
                // Count unread notifications
                $conn = connectDB();
                $userId = $_SESSION['user_id'];
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                $unreadCount = $result->fetch_assoc()['count'];
                $stmt->close();
                closeDB($conn);
                
                if ($unreadCount > 0) {
                    echo '<span class="badge badge-danger">' . $unreadCount . '</span>';
                }
                ?>
            </a>
            <div class="dropdown-menu dropdown-menu-right" aria-labelledby="navbarDropdown">
                <?php
                $conn = connectDB();
                $stmt = $conn->prepare("SELECT notification_id, title, created_at FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 5");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        echo '<div class="dropdown-item">';
                        echo '<small class="text-muted">' . date('d-m-Y H:i', strtotime($row['created_at'])) . '</small><br>';
                        echo $row['title'];
                        echo '</div>';
                        if ($result->num_rows > 1) {
                            echo '<div class="dropdown-divider"></div>';
                        }
                    }
                } else {
                    echo '<span class="dropdown-item">No new notifications</span>';
                }
                
                $stmt->close();
                closeDB($conn);
                ?>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item text-center" href="<?php echo BASE_URL; ?>notifications.php?action=all">View All Notifications</a>
            </div>
        </li>
        <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="fas fa-user-circle"></i> <?php echo $_SESSION['name']; ?>
            </a>
            <div class="dropdown-menu dropdown-menu-right" aria-labelledby="userDropdown">
                <a class="dropdown-item" href="profile.php">
                    <i class="fas fa-user fa-sm fa-fw mr-2 text-gray-400"></i> Profile
                </a>
                <a class="dropdown-item" href="change_password.php">
                    <i class="fas fa-key fa-sm fa-fw mr-2 text-gray-400"></i> Change Password
                </a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item" href="logout.php">
                    <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i> Logout
                </a>
            </div>
        </li>
    </ul>
</header>
