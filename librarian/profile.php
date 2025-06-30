<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'librarian') {
    header("Location: ../index.php");
    exit();
}

require_once '../includes/db_connection.php';

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
            $filename = "librarian_{$user_id}_" . time() . ".$ext";
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Librarian Profile | Library System</title>
    <link rel="stylesheet" href="../Styling/dashboard_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .profile-container {
            background: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-width: 800px;
            margin: 20px auto;
        }
        
        .profile-picture-container {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .profile-image {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #4e73df;
            margin-bottom: 15px;
        }
        
        .change-picture-btn {
            background: #4e73df;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #495057;
        }
        
        .form-group input {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #d1d3e2;
            border-radius: 4px;
            font-size: 1rem;
        }
        
        .form-group input:focus {
            border-color: #4e73df;
            outline: none;
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
        }
        
        .password-strength {
            height: 4px;
            background: #e9ecef;
            margin-top: 5px;
            border-radius: 2px;
            overflow: hidden;
        }
        
        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: width 0.3s;
        }
        
        .form-section-title {
            color: #4e73df;
            margin: 1.5rem 0 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #e3e6f0;
        }
    </style>
</head>
<body>
<div class="dashboard-container">
    <aside class="sidebar">
        <h2>Library System</h2>
        <ul>
            <li><a href="librarian_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="add_book.php"><i class="fas fa-book-medical"></i> Add Book</a></li>
            <li><a href="manage_books.php"><i class="fas fa-book"></i> Manage Books</a></li>
            <li><a href="manage_reservations.php"><i class="fas fa-calendar-check"></i> Reservations</a></li>
            <li class="active"><a href="manage_borrowings.php"><i class="fas fa-book-reader"></i> Borrowings</a></li>
            <li><a href="profile.php"><i class="fas fa-user-cog"></i> Profile</a></li>
            <li><a href="../db_operations.php?action=logout"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <header>
            <h1>Librarian Profile</h1>
                <div class="user-info">
                <a href="profile.php">
                <img src="<?= htmlspecialchars('../' . ($user['profile_pic'] ?? 'images/default_profile.png')) ?>" 
                     alt="Profile" class="header-profile-image">
                </a>
                <span><?= htmlspecialchars($_SESSION['user']['username']) ?></span>
            </div>
        </header>

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

        <div class="profile-container">
            <form method="POST" enctype="multipart/form-data">
                <div class="profile-picture-container">
                    <img src="../<?= htmlspecialchars($user['profile_pic'] ?? 'images/default_profile.png') ?>" 
                         id="profile-preview" class="profile-image">
                    <input type="file" name="profile_pic" id="profile_pic" 
                           style="display: none;" onchange="previewImage(this)">
                    <button type="button" class="change-picture-btn" 
                            onclick="document.getElementById('profile_pic').click()">
                        <i class="fas fa-camera"></i> Change Picture
                    </button>
                    <small>Max 2MB (JPG, PNG, GIF)</small>
                </div>
                
                <div class="form-group">
                    <label for="username"><i class="fas fa-user-tag"></i> Username</label>
                    <input type="text" id="username" name="username" 
                           value="<?= htmlspecialchars($user['username']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="email"><i class="fas fa-envelope"></i> Email</label>
                    <input type="email" id="email" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                    <small>Contact admin to change email</small>
                </div>
                
                <h3 class="form-section-title"><i class="fas fa-key"></i> Change Password</h3>
                
                <div class="form-group">
                    <label for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password">
                </div>
                
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" oninput="checkPasswordStrength()">
                    <div class="password-strength">
                        <div class="password-strength-bar" id="password-strength-bar"></div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password">
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </form>
        </div>
    </main>
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

function checkPasswordStrength() {
    const password = document.getElementById('new_password').value;
    const strengthBar = document.getElementById('password-strength-bar');
    let strength = 0;
    
    if (password.length >= 8) strength += 1;
    if (password.match(/([a-z].*[A-Z])|([A-Z].*[a-z])/)) strength += 1;
    if (password.match(/([0-9])/)) strength += 1;
    if (password.match(/([!,%,&,@,#,$,^,*,?,_,~])/)) strength += 1;
    
    switch(strength) {
        case 0:
            strengthBar.style.width = "0%";
            strengthBar.style.background = "#dc3545";
            break;
        case 1:
            strengthBar.style.width = "25%";
            strengthBar.style.background = "#dc3545";
            break;
        case 2:
            strengthBar.style.width = "50%";
            strengthBar.style.background = "#ffc107";
            break;
        case 3:
            strengthBar.style.width = "75%";
            strengthBar.style.background = "#28a745";
            break;
        case 4:
            strengthBar.style.width = "100%";
            strengthBar.style.background = "#28a745";
            break;
    }
}
</script>

<?php include '../includes/footer.php'; ?>
</body>
</html>