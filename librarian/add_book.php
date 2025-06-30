<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'librarian') {
    header("Location: ../index.php");
    exit();
}

require_once '../includes/db_connection.php';

// Enable MySQLi error reporting
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$success = $error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $title = trim($_POST['title']);
        $author = trim($_POST['author']);
        $description = trim($_POST['description']);
        $total_pages = intval($_POST['total_pages']);
        $added_by = $_SESSION['user']['id'];
        $created_at = date('Y-m-d H:i:s');

        if (!$title || !$author || !$description || $total_pages < 1) {
            throw new Exception("All fields are required and total pages must be a positive number.");
        }

        // Handle cover image
        if (!isset($_FILES['cover_image']) || $_FILES['cover_image']['error'] !== 0) {
            throw new Exception("Cover image is required.");
        }

        $cover_dir = '../uploads/covers/';
        if (!file_exists($cover_dir)) mkdir($cover_dir, 0777, true);

        $cover_image_name = uniqid() . '_' . basename($_FILES['cover_image']['name']);
        $cover_image_path = $cover_dir . $cover_image_name;
        $cover_db_path = 'uploads/covers/' . $cover_image_name;
        $image_type = strtolower(pathinfo($cover_image_path, PATHINFO_EXTENSION));
        $allowed_images = ['jpg', 'jpeg', 'png', 'gif'];

        if (!in_array($image_type, $allowed_images)) {
            throw new Exception("Invalid image format. Allowed: jpg, jpeg, png, gif.");
        }

        if (!move_uploaded_file($_FILES['cover_image']['tmp_name'], $cover_image_path)) {
            throw new Exception("Failed to upload cover image.");
        }

        // Handle PDF upload
        if (!isset($_FILES['pdf_file']) || $_FILES['pdf_file']['error'] !== 0) {
            throw new Exception("PDF file is required.");
        }

        $pdf_dir = '../uploads/pdfs/';
        if (!file_exists($pdf_dir)) mkdir($pdf_dir, 0777, true);

        $pdf_file_name = uniqid() . '_' . basename($_FILES['pdf_file']['name']);
        $pdf_file_path = $pdf_dir . $pdf_file_name;
        $pdf_db_path = 'uploads/pdfs/' . $pdf_file_name;

        if (strtolower(pathinfo($pdf_file_path, PATHINFO_EXTENSION)) !== 'pdf') {
            throw new Exception("Only PDF files are allowed.");
        }

        if (!move_uploaded_file($_FILES['pdf_file']['tmp_name'], $pdf_file_path)) {
            throw new Exception("Failed to upload PDF.");
        }

        // Insert into database
        $stmt = $conn->prepare("INSERT INTO books (title, author, description, total_pages, cover_image, pdf_file, added_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssissss", $title, $author, $description, $total_pages, $cover_db_path, $pdf_db_path, $added_by, $created_at);
        $stmt->execute();

        $success = "Book added successfully!";
        
        // Log librarian action - after book is successfully inserted
        $username = $_SESSION['user']['username'];
        $action = "added a new book titled '$title'";
        $log = $conn->prepare("INSERT INTO system_logs (username, action, timestamp) VALUES (?, ?, NOW())");
        $log->bind_param("ss", $username, $action);
        $log->execute();

        // Clear input fields
        $title = $author = $description = '';
        $total_pages = '';
    } catch (Exception $e) {
        $error = $e->getMessage();

        // Rollback files if they were uploaded
        if (!empty($cover_image_path) && file_exists($cover_image_path)) unlink($cover_image_path);
        if (!empty($pdf_file_path) && file_exists($pdf_file_path)) unlink($pdf_file_path);
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Book | Library System</title>
    <link rel="stylesheet" href="../Styling/dashboard_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .form-container {
            background: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-width: 800px;
            margin: 30px auto;
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
        .form-group input, 
        .form-group textarea, 
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        .form-group input:focus, 
        .form-group textarea:focus, 
        .form-group select:focus {
            border-color: #4e73df;
            outline: none;
            box-shadow: 0 0 0 3px rgba(78, 115, 223, 0.1);
        }
        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }
        .file-upload-wrapper {
            position: relative;
            margin-bottom: 20px;
        }
        .file-upload-label {
            display: block;
            padding: 12px;
            border: 1px dashed #ddd;
            border-radius: 4px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        .file-upload-label:hover {
            border-color: #4e73df;
            background-color: #f8f9fa;
        }
        .file-upload-label i {
            font-size: 24px;
            color: #6c757d;
            margin-bottom: 10px;
        }
        .file-upload-input {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        .file-upload-name {
            margin-top: 10px;
            font-size: 14px;
            color: #6c757d;
        }
        .btn {
            padding: 12px 24px;
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
            margin-left: 10px;
        }
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        .preview-image {
            max-width: 200px;
            max-height: 200px;
            margin-top: 10px;
            display: none;
            border-radius: 4px;
            border: 1px solid #ddd;
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
            <h1>Add New Book</h1>
            <div class="user-info">
                <a href="profile.php">
                    <img src="<?= htmlspecialchars('../' . ($_SESSION['user']['profile_pic'] ?? 'images/default_profile.png')) ?>" 
                         alt="Profile" class="header-profile-image">
                </a>
                <span><?= htmlspecialchars($_SESSION['user']['username']) ?></span>
            </div>
        </header>

        <?php if ($success): ?>
            <div class="alert success">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert error">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <section class="form-container">
            <form method="POST" enctype="multipart/form-data" id="addBookForm">
                <div class="form-group">
                    <label for="title">Book Title *</label>
                    <input type="text" id="title" name="title" value="<?= htmlspecialchars($title ?? '') ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="author">Author *</label>
                    <input type="text" id="author" name="author" value="<?= htmlspecialchars($author ?? '') ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="description">Description *</label>
                    <textarea id="description" name="description" required><?= htmlspecialchars($description ?? '') ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="total_pages">Total Pages *</label>
                    <input type="number" id="total_pages" name="total_pages" min="1" value="<?= htmlspecialchars($total_pages ?? '') ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Cover Image *</label>
                    <div class="file-upload-wrapper">
                        <label class="file-upload-label" for="cover_image">
                            <i class="fas fa-image"></i><br>
                            <span>Click to upload cover image</span>
                            <input type="file" id="cover_image" name="cover_image" class="file-upload-input" accept="image/*" required>
                        </label>
                        <div class="file-upload-name" id="cover-image-name">No file chosen</div>
                        <img id="cover-image-preview" class="preview-image" src="#" alt="Cover preview">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Book PDF File *</label>
                    <div class="file-upload-wrapper">
                        <label class="file-upload-label" for="pdf_file">
                            <i class="fas fa-file-pdf"></i><br>
                            <span>Click to upload PDF file</span>
                            <input type="file" id="pdf_file" name="pdf_file" class="file-upload-input" accept="application/pdf" required>
                        </label>
                        <div class="file-upload-name" id="pdf-file-name">No file chosen</div>
                    </div>
                </div>
                
                <div class="form-group" style="margin-top: 30px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Add Book
                    </button>
                    <button type="reset" class="btn btn-secondary">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                </div>
            </form>
        </section>
    </main>
</div>

<script>
// Show file name when selected
document.getElementById('cover_image').addEventListener('change', function(e) {
    const fileName = this.files[0] ? this.files[0].name : 'No file chosen';
    document.getElementById('cover-image-name').textContent = fileName;
    
    // Show image preview
    if (this.files && this.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('cover-image-preview');
            preview.src = e.target.result;
            preview.style.display = 'block';
        }
        reader.readAsDataURL(this.files[0]);
    }
});

document.getElementById('pdf_file').addEventListener('change', function(e) {
    const fileName = this.files[0] ? this.files[0].name : 'No file chosen';
    document.getElementById('pdf-file-name').textContent = fileName;
});

// Form validation
document.getElementById('addBookForm').addEventListener('submit', function(e) {
    const totalPages = document.getElementById('total_pages').value;
    if (totalPages < 1) {
        alert('Total pages must be at least 1');
        e.preventDefault();
        return false;
    }
    return true;
});
</script>
</body>
</html>