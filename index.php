<?php
session_start();
if (isset($_SESSION['user'])) {
    // Redirect to appropriate dashboard if already logged in
    switch ($_SESSION['user']['role']) {
        case 'student':
            header('Location: student/student_dashboard.php');
            break;
        case 'librarian':
            header('Location: librarian/librarian_dashboard.php');
            break;
        case 'admin':
            header('Location: admin/admin_dashboard.php');
            break;
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - School Library</title>
    <link rel="stylesheet" href="Styling/indexstyle.css">
</head>
<body>
<div class="register-container">
    <h2>Login to School Library</h2>
    
    <?php if (isset($_GET['error'])): ?>
        <p class="error-message"><?= htmlspecialchars($_GET['error']) ?></p>
    <?php endif; ?>

    <form action="db_operations.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="login">
        
        <div class="input-group">
            <label for="username">Username</label>
            <input type="text" name="username" placeholder="Enter your username" required>
        </div>

        <div class="input-group">
            <label for="password">Password</label>
            <input type="password" name="password" placeholder="Enter your password" required>
            <span class="toggle-password" onclick="togglePassword()">👁️</span>
        </div>

        <button type="submit" name="login" class="register-btn">Login</button>
    </form>

    <div class="register-link">
        <p>Don't have an account? <a href="register.php">Register as Student</a></p>
    </div>
</div>

<script>
    function togglePassword() {
        const passwordField = document.querySelector('input[name="password"]');
        passwordField.type = passwordField.type === 'password' ? 'text' : 'password';
    }
</script>
</body>
</html>