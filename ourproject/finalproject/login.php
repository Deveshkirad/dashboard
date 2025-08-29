<?php
session_start();
// If the user is already logged in, redirect them to the dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

// A more organized way to handle error messages
$errorMessages = [
    'invalid' => 'Invalid email or password. Please try again.',
    'wrong_password' => 'Invalid email or password. Please try again.',
    'no_user' => 'Invalid email or password. Please try again.',
    'empty' => 'Please fill in both email and password fields.',
];
$error = $_GET['error'] ?? '';
$errorMessage = $errorMessages[$error] ?? null;
if ($error && !$errorMessage) {
    $errorMessage = 'An unknown error occurred.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Admin Dashboard</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom Login CSS -->
    <link rel="stylesheet" href="assest/css/login.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-logo text-center mb-3">
                <i class="fa fa-laptop-code fa-3x text-primary"></i>
            </div>
            <div class="login-header text-center">
                <h2>Welcome Back!</h2>
                <p class="text-muted">Please sign in to continue.</p>
            </div>
            <div class="login-body p-4">
                <?php if ($errorMessage): ?>
                    <div class="alert alert-danger d-flex align-items-center mb-3" role="alert">
                        <i class="fa fa-exclamation-triangle me-2"></i>
                        <div>
                            <?php echo htmlspecialchars($errorMessage); ?>
                        </div>
                    </div>
                <?php endif; ?>
                <form action="login_process.php" method="POST">
                    <div class="mb-3">
                        <label for="email" class="form-label">Email address</label>
                        <input type="email" class="form-control" id="email" name="email" placeholder="name@example.com" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="position-relative">
                            <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                            <span class="password-toggle-icon" id="togglePassword">
                                <i class="fa fa-eye-slash"></i>
                            </span>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="1" id="rememberMe" name="remember">
                            <label class="form-check-label" for="rememberMe">
                                Remember me
                            </label>
                        </div>
                        <a href="#" class="form-text text-decoration-none small">Forgot Password?</a>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 login-btn">
                        <i class="fa fa-sign-in-alt me-2"></i>Login
                    </button>
                </form>
            </div>
        </div> <!-- .login-card -->
    </div> <!-- .login-container -->
    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const togglePassword = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');

            if (togglePassword && passwordInput) {
                togglePassword.addEventListener('click', function () {
                    // toggle the type attribute
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    
                    // toggle the icon
                    const icon = this.querySelector('i');
                    if (type === 'password') {
                        icon.classList.remove('fa-eye');
                        icon.classList.add('fa-eye-slash');
                    } else {
                        icon.classList.remove('fa-eye-slash');
                        icon.classList.add('fa-eye');
                    }
                });
            }
        });
    </script>
</body>
</html>