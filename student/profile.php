<?php

require_once('../includes/db_connection.php');
require_once('../includes/auth_check.php');

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'student') {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user']['id'];
$user = $conn->query("SELECT * FROM users WHERE id = $user_id")->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    $username = trim($_POST['username']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($username)) {
        $errors[] = "Username cannot be empty";
    } elseif ($username != $user['username']) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->bind_param("si", $username, $user_id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors[] = "Username already taken";
        }
        $stmt->close();
    }

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

    $profile_pic = $user['profile_pic'];
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg', 'image/png', 'image/gif'];
        $filetype = $_FILES['profile_pic']['type'];
        if (!in_array($filetype, $allowed)) {
            $errors[] = "Only JPG, PNG, and GIF images are allowed";
        } elseif ($_FILES['profile_pic']['size'] > 2000000) {
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
                if ($profile_pic && !str_contains($profile_pic, 'default_profile.png')) {
                    @unlink("../$profile_pic");
                }
                $profile_pic = "uploads/profile_pics/$filename";
            } else {
                $errors[] = "Failed to upload profile picture";
            }
        }
    }

    if (empty($errors)) {
        $update_fields = ["username = ?", "profile_pic = ?"];
        $params = [$username, $profile_pic];
        $types = 'ss';

        if (!empty($new_password)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_fields[] = "password = ?";
            $params[] = $hashed_password;
            $types .= 's';
        }

        $params[] = $user_id;
        $types .= 'i';
        $query = "UPDATE users SET " . implode(', ', $update_fields) . " WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);

        if ($stmt->execute()) {
            $_SESSION['user']['username'] = $username;
            $_SESSION['user']['profile_pic'] = $profile_pic;
            header("Location: profile.php?success=" . urlencode("Profile updated successfully!"));
            exit();
        } else {
            $errors[] = "Database error: " . $conn->error;
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Profile</title>
    <link rel="stylesheet" href="../Styling/dashboard_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .profile-page {
            padding: 20px;
        }
        .form-section {
            max-width: 700px;
            margin: auto;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label { font-weight: bold; display: block; margin-bottom: 5px; }
        input[type="text"], input[type="email"], input[type="password"], input[type="file"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #d1d3e2;
            border-radius: 5px;
        }
        .btn-primary {
            background: #4e73df;
            color: #fff;
            border: none;
            padding: 10px 20px;
            margin-top: 10px;
            border-radius: 5px;
            cursor: pointer;
            align-content: center;
            margin: auto;
        }
        .alert.success { background: #d4edda; padding: 10px; color: #155724; margin-bottom: 10px; }
        .alert.error { background: #f8d7da; padding: 10px; color: #721c24; margin-bottom: 10px; }
        .profile-picture {
            text-align: center;
            margin-bottom: 15px;
        }
        .profile-picture img {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid #4e73df;
        }
        #password-strength {
            font-size: 0.9em;
            margin-top: 5px;
        }
        
    </style>
</head>
<body>

<button class="menu-toggle"><i class="fas fa-bars"></i></button>
<div class="dashboard-container">
    <aside class="sidebar">
        <h2>Library</h2>
        <ul>
            <li><a href="student_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="view_books.php"><i class="fas fa-book-open"></i> View Books</a></li>
            <li><a href="reserve_book.php"><i class="fas fa-calendar-plus"></i> Reserve Book</a></li>
            <li><a href="borrow_history.php"><i class="fas fa-history"></i> Borrow History</a></li>
            <li class="active"><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
            <li><a href="../db_operations.php?action=logout"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <header>
            <h1>My Profile</h1>
           <div class="user-info">
                <a href="profile.php">
                    <img src="<?= htmlspecialchars('../' . ($_SESSION['user']['profile_pic'] ?? 'images/default_profile.png')) ?>" 
                         alt="Profile" class="header-profile-image">
                </a>
                <span><?= htmlspecialchars($_SESSION['user']['username']) ?></span>
            </div>
        </header>

        <div class="profile-page">
            <div class="form-section">
                <?php if (!empty($errors)): ?>
                    <div class="alert error">
                        <?php foreach ($errors as $error): ?>
                            <p><?= htmlspecialchars($error) ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert success"><?= htmlspecialchars($_GET['success']) ?></div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <div class="profile-picture">
                <div class="profile-img-wrapper">
                    <img src="../<?= htmlspecialchars($user['profile_pic'] ?? 'images/default_profile.png') ?>" id="profile-preview" alt="Profile Picture">
                    <label for="profile_pic" class="camera-icon">
                        <i class="fas fa-camera"></i>
                    </label>
                </div>
                <input type="file" name="profile_pic" id="profile_pic" style="display: none;" onchange="previewImage(this)">
                <small>Max 2MB (JPG, PNG, GIF)</small>
</div>


                    <div class="form-group">
                        <label for="username">Username:</label>
                        <input type="text" name="username" id="username" value="<?= htmlspecialchars($user['username']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email:</label>
                        <input type="email" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                        <small>Contact admin to change email</small>
                    </div>
                    <hr>
                    <h3>Change Password</h3>
                    <div class="form-group">
                        <label for="current_password">Current Password:</label>
                        <input type="password" name="current_password" id="current_password">
                    </div>
                    <div class="form-group">
                        <label for="new_password">New Password:</label>
                        <input type="password" name="new_password" id="new_password">
                        <div id="password-strength"></div>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password:</label>
                        <input type="password" name="confirm_password" id="confirm_password">
                    </div>

                    <button type="submit" class="btn-primary">Save Changes</button>
                </form>
            </div>
        </div>
    </main>
</div>

<script>
function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('profile-preview').src = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
    }
}

document.getElementById('new_password').addEventListener('input', function () {
    const strengthText = document.getElementById('password-strength');
    const password = this.value;
    let strength = '';
    let color = 'red';

    if (password.length >= 8 && /[A-Z]/.test(password) &&
        /[a-z]/.test(password) && /\d/.test(password) && /[@$!%*?&#]/.test(password)) {
        strength = 'Strong';
        color = 'green';
    } else if (password.length >= 6) {
        strength = 'Medium';
        color = 'orange';
    } else if (password.length > 0) {
        strength = 'Weak';
        color = 'red';
    }

    strengthText.textContent = strength ? `Strength: ${strength}` : '';
    strengthText.style.color = color;
});
</script>

</body>
</html>
