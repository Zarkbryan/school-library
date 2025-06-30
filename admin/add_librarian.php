<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

require_once '../includes/db_connection.php';

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_number = trim($_POST['employee_number']);
    $name = trim($_POST['name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $hire_date = date('Y-m-d');
    $profile_pic = 'images/default_profile.png';

    // Basic validation
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    }

    // Check if username or email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $errors[] = "Username or email already exists.";
    }
    $stmt->close();

    // Handle profile picture upload
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (in_array($_FILES['profile_pic']['type'], $allowed_types)) {
            $ext = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . "." . $ext;
            $destination = '../uploads/' . $filename;
            if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $destination)) {
                $profile_pic = 'uploads/' . $filename;
            }
        } else {
            $errors[] = "Invalid image format. Only JPG, PNG, and GIF allowed.";
        }
    }

    if (empty($errors)) {
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Insert into users table
        $stmt = $conn->prepare("INSERT INTO users (username, name, email, password, role, profile_pic) VALUES (?, ?, ?, ?, 'librarian', ?)");
        $stmt->bind_param("sssss", $username, $name, $email, $hashed_password, $profile_pic);
        if ($stmt->execute()) {
            $user_id = $stmt->insert_id;
            $stmt->close();

            // Insert into librarians table
            $stmt = $conn->prepare("INSERT INTO librarians (user_id, employee_number, hire_date) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $user_id, $employee_number, $hire_date);
            if ($stmt->execute()) {
                $success = "Librarian added successfully.";
            } else {
                $errors[] = "Failed to insert into librarians table.";
            }
            $stmt->close();
        } else {
            $errors[] = "Failed to insert into users table.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Librarian | Admin Dashboard</title>
    <link rel="stylesheet" href="../Styling/dashboard_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .form-container {
            background: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-width: 800px;
            margin: 0 auto;
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
        .btn-primary {
            background-color: #4e73df;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            transition: background-color 0.3s;
        }
        .btn-primary:hover {
            background-color: #2e59d9;
        }
        .profile-picture-preview {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #e3e6f0;
            margin-bottom: 15px;
        }
        .alert.success {
             background-color: #d4edda;
                color: #155724;
                padding: 10px;
                margin-bottom: 20px;
                border-left: 5px solid #28a745;
            }

        .alert.error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            margin-bottom: 20px;
            border-left: 5px solid #dc3545;
        }

    </style>
</head>
<body>
<div class="dashboard-container">
    <aside class="sidebar">
        <h2>Library Admin</h2>
        <ul>
            <li class="active"><a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="manage_librarians.php"><i class="fas fa-users-cog"></i> Manage Librarians</a></li>
            <li class="active"><a href="add_book.php"><i class="fas fa-book-medical"></i> Add Book</a></li>
            <li><a href="manage_books.php"><i class="fas fa-book"></i> Manage Books</a></li>
            <li><a href="system_logs.php"><i class="fas fa-clipboard-list"></i> System Logs</a></li>
            <li><a href="profile.php"><i class="fas fa-user-cog"></i> Profile</a></li>
            <li><a href="../db_operations.php?action=logout"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <header>
            <h1>Add New Librarian</h1>
            <div class="user-info">
                <a href="profile.php">
                    <img src="<?= htmlspecialchars('../' . ($_SESSION['user']['profile_pic'] ?? 'images/default_profile.png')) ?>" 
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

        <?php if (!empty($success)): ?>
            <div class="alert success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <div class="form-container">
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group text-center">
                    <img src="../images/default_profile.png" id="profile-preview" class="profile-picture-preview">
                    <input type="file" name="profile_pic" id="profile_pic" accept="image/*" 
                           style="display: none;" onchange="previewImage(this)">
                    <button type="button" class="btn btn-secondary" 
                            onclick="document.getElementById('profile_pic').click()">
                        <i class="fas fa-camera"></i> Choose Profile Picture
                    </button>
                    <small class="text-muted">Max 2MB (JPG, PNG, GIF)</small>
                </div>

                <div class="form-group">
                    <label for="employee_number"><i class="fas fa-id-card"></i> Employee Number</label>
                    <input type="text" id="employee_number" name="employee_number" 
                           value="<?= htmlspecialchars($_POST['employee_number'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label for="name"><i class="fas fa-user"></i> Full Name</label>
                    <input type="text" id="name" name="name" 
                           value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label for="username"><i class="fas fa-user-tag"></i> Username</label>
                    <input type="text" id="username" name="username" 
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label for="email"><i class="fas fa-envelope"></i> Email</label>
                    <input type="email" id="email" name="email" 
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label for="password"><i class="fas fa-lock"></i> Password</label>
                    <input type="password" id="password" name="password" required>
                    <small class="text-muted">Minimum 8 characters</small>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-user-plus"></i> Add Librarian
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
</script>

<?php include '../includes/footer.php'; ?>
</body>
</html>
