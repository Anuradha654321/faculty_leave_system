<?php
require_once 'config/config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect(BASE_URL . 'index.php');
}

// Initialize error variable
$error = '';
$showError = false;

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $showError = true; // Only show errors after form submission
    $username = sanitizeInput($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
    } else {
        $conn = connectDB();
        
        // Prepare statement to prevent SQL injection
        $stmt = $conn->prepare("SELECT u.user_id, u.username, u.password, u.first_name, u.last_name, r.role_name, u.dept_id 
                               FROM users u 
                               JOIN roles r ON u.role_id = r.role_id 
                               WHERE u.username = ? AND u.status = 'active'");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Verify password
            if (password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['name'] = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['role'] = $user['role_name'];
                $_SESSION['dept_id'] = $user['dept_id'];
                
                // Redirect to dashboard
                redirect(BASE_URL . 'index.php');
            } else {
                $error = 'Invalid username or password';
            }
        } else {
            $error = 'Invalid username or password';
        }
        
        $stmt->close();
        closeDB($conn);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_TITLE; ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white text-center">
                        <h4><?php echo APP_TITLE; ?></h4>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($error) && $showError): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <?php
                        // Display flash message if any
                        $flash = getFlashMessage();
                        if ($flash) {
                            echo '<div class="alert alert-' . $flash['type'] . ' alert-dismissible fade show" role="alert">';
                            echo $flash['message'];
                            echo '<button type="button" class="close" data-dismiss="alert" aria-label="Close">';
                            echo '<span aria-hidden="true">&times;</span>';
                            echo '</button>';
                            echo '</div>';
                        }
                        ?>
                        
                        <form method="POST" action="">
                            <div class="form-group">
                                <label for="username">Username</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            <div class="form-group">
                                <label for="password">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <button type="submit" class="btn btn-primary btn-block">Login</button>
                        </form>
                    </div>
                    <div class="card-footer text-center">
                        <p class="mb-0">Faculty Leave Management System</p>
                        <p class="mt-2 mb-0">Don't have an account? <a href="<?php echo BASE_URL; ?>register.php">Register here</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
