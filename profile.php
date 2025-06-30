<?php
session_start();
require_once('../includes/db_connection.php');
require_once('../includes/auth_check.php');

$user_id = $_SESSION['user']['id'];
$user = $conn->query("SELECT * FROM users WHERE id = $user_id")->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    
    // Get form data
    $username = trim($_POST['username']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate username
    if (empty($username)) {
        $errors[] = "Username cannot be empty";
    } elseif ($username != $user['username']) {
        // Check if new username is available
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->bind_param("si", $username, $user_id);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $errors[] = "Username already taken";
        }
        $stmt->close();
    }
    
    // Validate password change if requested
    if (!empty($new_password)) {
        if (!password_verify($current_password, $user['password'])) {
            $errors[] = "Current password is incorrect";
        }
        
        if (strlen($new_password) < 8) {
            $errors[] = "New password must be at least 8 characters";
        }
        
        if ($new_password !== $confirm_password) {
            $errors[] = "New passwords don't match";
        }
    }
    
    // Handle profile picture upload
    $profile_pic = $user['profile_pic'];
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg', 'image/png', 'image/gif'];
        $filetype = $_FILES['profile_pic']['type'];
        
        if (!in_array($filetype, $allowed)) {
            $errors[] = "Only JPG, PNG, and GIF images are allowed";
        } elseif ($_FILES['profile_pic']['size'] > 2000000) { // 2MB
            $errors[] = "Image size must be less than 2MB";
        } else {
            $upload_dir = '../uploads/profile_pics/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $ext = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
            $filename = "user_{$user_id}_" . time() . ".$ext";
            $destination = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $destination)) {
                // Delete old profile pic if it exists and isn't the default
                if ($profile_pic && !str_contains($profile_pic, 'default_profile.png')) {
                    @unlink("../$profile_pic");
                }
                $profile_pic = "uploads/profile_pics/$filename";
            } else {
                $errors[] = "Failed to upload profile picture";
            }
        }
    }
    
    // Update database if no errors
    if (empty($errors)) {
        $update_fields = [];
        $params = [];
        $types = '';
        
        // Always update username and profile pic
        $update_fields[] = "username = ?";
        $params[] = $username;
        $types .= 's';
        
        $update_fields[] = "profile_pic = ?";
        $params[] = $profile_pic;
        $types .= 's';
        
        // Update password if changed
        if (!empty($new_password)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_fields[] = "password = ?";
            $params[] = $hashed_password;
            $types .= 's';
        }
        
        // Prepare and execute update query
        $params[] = $user_id;
        $types .= 'i';
        
        $query = "UPDATE users SET " . implode(', ', $update_fields) . " WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            // Update session data
            $_SESSION['user']['username'] = $username;
            $_SESSION['user']['profile_pic'] = $profile_pic;
            
            $success = "Profile updated successfully!";
            // Refresh the page to show changes
            header("Location: profile.php?success=" . urlencode($success));
            exit();
        } else {
            $errors[] = "Database error: " . $conn->error;
        }
        $stmt->close();
    }
}

include '../includes/header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile</title>
    <link rel="stylesheet" href="../Styling/profile.css">
    <style>
        /* Profile Picture Styling */
        .profile-picture {
            text-align: center;
            margin-bottom: 20px;
        }
        .profile-picture img {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #4e73df;
            margin-bottom: 10px;
        }
        .profile-picture button {
            display: block;
            margin: 10px auto;
            padding: 8px 15px;
            background-color: #4e73df;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .profile-picture small {
            display: block;
            font-size: 12px;
            color: #6c757d;
        }
        
        /* Form Styling */
        .profile-container {
            display: flex;
            flex-wrap: wrap;
            gap: 30px;
            margin-top: 20px;
        }
        .profile-details {
            flex: 1;
            min-width: 300px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #d1d3e2;
            border-radius: 4px;
        }
        .btn-primary {
            background-color: #4e73df;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        /* Alerts */
        .alert {
            padding: 10px 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <h1>My Profile</h1>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $error): ?>
                    <p><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($_GET['success']) ?></div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data" class="profile-container">
            <div class="profile-picture">
                <img src="../<?= htmlspecialchars($user['profile_pic'] ?? 'images/default_profile.png') ?>" 
                     alt="Profile Picture" id="profile-preview">
                <input type="file" name="profile_pic" id="profile_pic" accept="image/*" 
                       style="display: none;" onchange="previewImage(this)">
                <button type="button" class="btn btn-secondary" 
                        onclick="document.getElementById('profile_pic').click()">
                    Change Picture
                </button>
                <small>Max 2MB (JPG, PNG, GIF)</small>
            </div>
            
            <div class="profile-details">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" value="<?= htmlspecialchars($user['username']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                    <small>Contact admin to change email</small>
                </div>
                
                <h3>Change Password</h3>
                <div class="form-group">
                    <label for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password" placeholder="Enter current password">
                </div>
                
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" placeholder="Enter new password">
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password">
                </div>
                
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>

    <script>
    function previewImage(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('profile-preview').src = e.target.result;
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
    </script>

    <?php include '../includes/footer.php'; ?>
</body>
</html>