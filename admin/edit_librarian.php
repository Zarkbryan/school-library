<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

require_once '../includes/db_connection.php';

// Get librarian ID from URL
$librarian_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch librarian data from users and librarians tables
$librarian = null;
$stmt = $conn->prepare("
    SELECT u.*, l.employee_number 
    FROM users u 
    LEFT JOIN librarians l ON u.id = l.user_id 
    WHERE u.id = ? AND u.role = 'librarian'
");
$stmt->bind_param("i", $librarian_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $librarian = $result->fetch_assoc();
} else {
    header("Location: manage_librarians.php?error=Librarian not found");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $employee_number = trim($_POST['employee_number']);
    
    // Validate inputs
    $errors = [];
    
    if (empty($name)) $errors[] = "Name is required";
    if (empty($username)) $errors[] = "Username is required";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required";
    if (empty($employee_number)) $errors[] = "Employee number is required";
    
    // Check if username or email already exists (excluding current librarian)
    $stmt = $conn->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
    $stmt->bind_param("ssi", $username, $email, $librarian_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) $errors[] = "Username or email already exists";
    
    // Handle profile picture upload
    $profile_pic = $librarian['profile_pic'];
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../images/profiles/';
        $file_name = uniqid() . '_' . basename($_FILES['profile_pic']['name']);
        $target_path = $upload_dir . $file_name;
        
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_type = $_FILES['profile_pic']['type'];
        $file_size = $_FILES['profile_pic']['size'];
        
        if (in_array($file_type, $allowed_types) && $file_size < 5000000) {
            if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $target_path)) {
                if ($profile_pic !== 'images/default_profile.png') {
                    @unlink('../' . $profile_pic);
                }
                $profile_pic = 'images/profiles/' . $file_name;
            } else {
                $errors[] = "Failed to upload profile picture";
            }
        } else {
            $errors[] = "Invalid file type or size (max 5MB allowed)";
        }
    }
    
    // Update database if no errors
    if (empty($errors)) {
        try {
            $conn->begin_transaction();
            
            // Update users table
            $stmt = $conn->prepare("UPDATE users SET name = ?, username = ?, email = ?, profile_pic = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $name, $username, $email, $profile_pic, $librarian_id);
            $stmt->execute();
            
            // Update librarians table
            // Check if record exists first
            $check_stmt = $conn->prepare("SELECT user_id FROM librarians WHERE user_id = ?");
            $check_stmt->bind_param("i", $librarian_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                // Update existing record
                $update_stmt = $conn->prepare("UPDATE librarians SET employee_number = ? WHERE user_id = ?");
                $update_stmt->bind_param("si", $employee_number, $librarian_id);
                $update_stmt->execute();
            } else {
                // Insert new record
                $insert_stmt = $conn->prepare("INSERT INTO librarians (user_id, employee_number) VALUES (?, ?)");
                $insert_stmt->bind_param("is", $librarian_id, $employee_number);
                $insert_stmt->execute();
            }
            
            $conn->commit();
            
            // Log the activity
            $action = "updated librarian $username";
            $log_stmt = $conn->prepare("INSERT INTO system_logs (username, action) VALUES (?, ?)");
            $log_stmt->bind_param("ss", $_SESSION['user']['username'], $action);
            $log_stmt->execute();
            
            // Return JSON response for AJAX or redirect
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                // AJAX request - return JSON
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => 'Librarian updated successfully',
                    'data' => [
                        'id' => $librarian_id,
                        'name' => $name,
                        'username' => $username,
                        'email' => $email,
                        'employee_number' => $employee_number,
                        'profile_pic' => $profile_pic,
                        'is_fully_registered' => true
                    ]
                ]);
                exit();
            } else {
                // Regular form submission - redirect
                header("Location: manage_librarians.php?success=Librarian updated successfully");
                exit();
            }
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "Database error: " . $e->getMessage();
            
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])
                && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'errors' => $errors]);
                exit();
            }
        }
    } elseif (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'errors' => $errors]);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Librarian | Library System</title>
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
            color: #555;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        .form-group input:focus {
            border-color: #4e73df;
            outline: none;
        }
        .profile-pic-preview {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 15px;
            border: 3px solid #eee;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-primary {
            background-color: #4e73df;
            color: white;
        }
        .btn-primary:hover {
            background-color: #3a5bbf;
        }
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        .error {
            color: #e74a3b;
            margin-bottom: 20px;
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
            <h1>Edit Librarian</h1>
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

        <section class="form-container">
            <form id="editLibrarianForm" action="edit_librarian.php?id=<?= $librarian_id ?>" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="profile_pic">Profile Picture</label>
                    <img src="../<?= htmlspecialchars($librarian['profile_pic'] ?? 'images/default_profile.png') ?>" 
                         alt="Profile" class="profile-pic-preview" id="profilePicPreview">
                    <input type="file" id="profile_pic" name="profile_pic" accept="image/*">
                </div>
                
                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" value="<?= htmlspecialchars($librarian['name']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" value="<?= htmlspecialchars($librarian['username']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($librarian['email']) ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="employee_number">Employee Number</label>
                    <input type="text" id="employee_number" name="employee_number" 
                           value="<?= htmlspecialchars($librarian['employee_number'] ?? '') ?>" required>
                </div>
                
                <div class="form-group" style="display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary">Update Librarian</button>
                    <a href="manage_librarians.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </section>
    </main>
</div>

<script>
// Preview profile picture before upload
document.getElementById('profile_pic').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('profilePicPreview').src = e.target.result;
        }
        reader.readAsDataURL(file);
    }
});

// Handle form submission with AJAX
document.getElementById('editLibrarianForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch(this.action, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update the parent window's table with the new data
            if (window.opener) {
                window.opener.updateLibrarianRow(data.data);
            }
            // Show success message and close the window after a short delay
            alert('Librarian updated successfully');
            window.close();
        } else {
            // Show errors
            alert(data.errors.join('\n'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while updating the librarian');
    });
});
</script>
</body>
</html>