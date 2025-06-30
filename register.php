<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register</title>
    <link rel="stylesheet" href="Styling/registerstyle.css">
</head>
<body>
<div class="register-container">
    <h2>Register into Students Library</h2>

    <?php
        if (isset($_GET['error'])) {
            echo "<p style='color:red;'>" . htmlspecialchars($_GET['error']) . "</p>";
        }
        if (isset($_GET['success'])) {
            echo "<p style='color:green;'>" . htmlspecialchars($_GET['success']) . "</p>";
        }
    ?>

<form action="db_operations.php" method="POST" enctype="multipart/form-data">

        <input type="hidden" name="action" value="register">
        <input type="hidden" name="role" value="student">

        <div class="input-group">
            <label for="username" class="input-label">Username</label>
            <input type="text" name="username" placeholder="Enter your username" required>
        </div>

        <div class="input-group">
            <label for="profile_picture" class="input-label">Profile Picture</label>
            <input type="file" name="profile_picture" accept=".jpg, .jpeg, .png">
        </div>
        <div class="input-group">

        <div class="input-group">
            <label for="name" class="input-label">Full Name</label>
            <input type="text" name="name" placeholder="Enter your full name" required>
        </div>

        <div class="input-group">
            <label for="email" class="input-label">Email</label>
            <input type="email" name="email" placeholder="Enter your Email" required>
        </div>

        <div class="input-group">
            <label for="password" class="input-label">Password</label>
            <input type="password" name="password" placeholder="Enter your password" required>
        </div>

        <div class="input-group">
            <label for="confirm_password" class="input-label">Confirm Password</label>
            <input type="password" name="confirm_password" placeholder="Confirm your password" required>
        </div>

        <div>
            <button type="submit" name="register" class="register-btn">Register</button>
        </div>
    </form>

    <div class="register-link">
        <p>Already have an account? <a href="index.php">Login</a></p>
    </div>
</div>
</body>
</html>
<script>
    function togglePassword() {
        const passwordField = document.querySelector('input[name="password"]');
        const confirmPasswordField = document.querySelector('input[name="confirm_password"]');
        const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordField.setAttribute('type', type);
        confirmPasswordField.setAttribute('type', type);

    }   
</script>