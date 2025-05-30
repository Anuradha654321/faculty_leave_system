<?php
require_once 'config/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect(BASE_URL . 'login.php');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unauthorized Access - <?php echo APP_TITLE; ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ml-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-center align-items-center" style="min-height: 70vh;">
                    <div class="text-center">
                        <div class="mb-4">
                            <i class="fas fa-exclamation-triangle text-warning" style="font-size: 5rem;"></i>
                        </div>
                        <h1 class="h2 mb-3">Unauthorized Access</h1>
                        <p class="lead mb-4">You do not have permission to access this page.</p>
                        <a href="<?php echo BASE_URL; ?>index.php" class="btn btn-primary">
                            <i class="fas fa-home mr-2"></i> Return to Dashboard
                        </a>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="assets/js/script.js"></script>
</body>
</html>
