<?php
session_start();
require_once '../includes/db_connection.php';

// Redirect if not logged in or not a librarian
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$bookId = $_GET['id'] ?? null;
$error = '';

// Fetch book data
if ($bookId) {
    $stmt = $conn->prepare("SELECT * FROM books WHERE id = ?");
    $stmt->bind_param("i", $bookId);
    $stmt->execute();
    $result = $stmt->get_result();
    $book = $result->fetch_assoc();

    if (!$book) {
        $_SESSION['error'] = "Book not found";
        header("Location: manage_books.php");
        exit();
    }
    $stmt->close();
} else {
    $_SESSION['error'] = "Invalid book ID";
    header("Location: manage_books.php");
    exit();
}

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $author = trim($_POST['author'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $total_pages = (int)($_POST['total_pages'] ?? 0);

    if (empty($title) || empty($author) || empty($description) || $total_pages < 1) {
        $error = "Please fill all fields correctly.";
    } else {
        $coverPath = $book['cover_image'];
        $pdfPath = $book['pdf_file'];

        // Handle cover image
        if (!empty($_FILES['cover_image']['name']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../uploads/book_covers/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $coverName = uniqid('cover_', true) . '_' . basename($_FILES['cover_image']['name']);
            $targetCover = $uploadDir . $coverName;
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

            if (in_array($_FILES['cover_image']['type'], $allowedTypes)) {
                if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $targetCover)) {
                    if (!empty($coverPath) && strpos($coverPath, 'default_cover') === false) {
                        @unlink('../' . $coverPath);
                    }
                    $coverPath = 'uploads/book_covers/' . $coverName;
                } else {
                    $error = "Failed to upload cover image.";
                }
            } else {
                $error = "Invalid image format.";
            }
        }

        // Handle PDF upload
        if (empty($error) && !empty($_FILES['pdf_file']['name']) && $_FILES['pdf_file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../uploads/book_pdfs/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $pdfName = uniqid('pdf_', true) . '_' . basename($_FILES['pdf_file']['name']);
            $targetPDF = $uploadDir . $pdfName;

            if ($_FILES['pdf_file']['type'] === 'application/pdf') {
                if (move_uploaded_file($_FILES['pdf_file']['tmp_name'], $targetPDF)) {
                    if (!empty($pdfPath)) {
                        @unlink('../' . $pdfPath);
                    }
                    $pdfPath = 'uploads/book_pdfs/' . $pdfName;
                } else {
                    $error = "Failed to upload PDF file.";
                }
            } else {
                $error = "Only PDF files are allowed.";
            }
        }

        if (empty($error)) {
            $stmt = $conn->prepare("UPDATE books SET title=?, author=?, description=?, total_pages=?, cover_image=?, pdf_file=?, updated_at=NOW() WHERE id=?");
            $stmt->bind_param("ssssssi", $title, $author, $description, $total_pages, $coverPath, $pdfPath, $bookId);
            if ($stmt->execute()) {
                $_SESSION['success'] = "Book updated successfully!";
                header("Location: manage_books.php");
                exit();
            } else {
                $error = "Database error: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Book | Library System</title>
    <link rel="stylesheet" href="../Styling/dashboard_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .form-container {
            background: #fff;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            margin: 20px auto;
            max-width: 800px;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #333;
        }
        
        .required:after {
            content: " *";
            color: #e74c3c;
        }
        
        input[type="text"],
        input[type="number"],
        textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        input[type="text"]:focus,
        input[type="number"]:focus,
        textarea:focus {
            outline: none;
            border-color: #3498db;
        }
        
        textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        .file-upload-wrapper {
            position: relative;
            margin-top: 0.5rem;
        }
        
        .file-upload-input {
            width: 0.1px;
            height: 0.1px;
            opacity: 0;
            overflow: hidden;
            position: absolute;
            z-index: -1;
        }
        
        .file-upload-label {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 1rem;
            background-color: #f8f9fa;
            color: #495057;
            border: 2px dashed #dee2e6;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .file-upload-label:hover {
            background-color: #e9ecef;
            border-color: #adb5bd;
        }
        
        .file-upload-label i {
            margin-right: 0.5rem;
            color: #3498db;
        }
        
        .file-name {
            margin-top: 0.5rem;
            font-size: 0.875rem;
            color: #6c757d;
        }
        
        .cover-preview-container {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        
        .book-cover-preview {
            width: 180px;
            height: 270px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #ddd;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 4px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }
        
        .btn i {
            margin-right: 0.5rem;
        }
        
        .btn-primary {
            background-color: #3498db;
            color: white;
            border: none;
        }
        
        .btn-primary:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background-color: #f8f9fa;
            color: #212529;
            border: 1px solid #dee2e6;
        }
        
        .btn-secondary:hover {
            background-color: #e9ecef;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
<div class="dashboard-container">
    <aside class="sidebar">
        <h2>Library System</h2>
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
            <h1>Edit Book: <?= htmlspecialchars($book['title']) ?></h1>
            <div class="user-info">
                <a href="profile.php">
                    <img src="<?= htmlspecialchars('../' . ($_SESSION['user']['profile_pic'] ?? 'images/default_profile.png')) ?>" 
                         alt="Profile" class="header-profile-image">
                </a>
                <span><?= htmlspecialchars($_SESSION['user']['username']) ?></span>
            </div>
        </header>

        <?php if (!empty($error)): ?>
            <div class="alert error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <section class="content-section">
            <div class="form-container">
                <form method="post" enctype="multipart/form-data">
                    <div class="cover-preview-container">
                        <img id="cover-preview" src="<?= htmlspecialchars('../' . ($book['cover_image'] ?? 'images/default_cover.jpg')) ?>" 
                             alt="Book Cover" class="book-cover-preview">
                    </div>

                    <div class="form-group">
                        <label for="title" class="required">Title</label>
                        <input type="text" id="title" name="title" 
                               value="<?= htmlspecialchars($book['title']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="author" class="required">Author</label>
                        <input type="text" id="author" name="author" 
                               value="<?= htmlspecialchars($book['author']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="description" class="required">Description</label>
                        <textarea id="description" name="description" required><?= htmlspecialchars($book['description'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="total_pages" class="required">Total Pages</label>
                        <input type="number" id="total_pages" name="total_pages" min="1" 
                               value="<?= htmlspecialchars($book['total_pages'] ?? 1) ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Cover Image</label>
                        <div class="file-upload-wrapper">
                            <input type="file" id="cover-upload" name="cover_image" class="file-upload-input" 
                                   accept="image/jpeg, image/png, image/gif, image/webp">
                            <label for="cover-upload" class="file-upload-label">
                                <i class="fas fa-image"></i> Choose New Cover Image
                            </label>
                        </div>
                        <div class="file-name" id="cover-file-name">
                            Current: <?= basename($book['cover_image'] ?? 'default_cover.jpg') ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Book PDF File</label>
                        <div class="file-upload-wrapper">
                            <input type="file" id="pdf-upload" name="pdf_file" class="file-upload-input" accept=".pdf">
                            <label for="pdf-upload" class="file-upload-label">
                                <i class="fas fa-file-pdf"></i> Choose New PDF File
                            </label>
                        </div>
                        <div class="file-name" id="pdf-file-name">
                            Current: <?= !empty($book['pdf_file']) ? basename($book['pdf_file']) : 'No PDF uploaded' ?>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Book
                        </button>
                        <a href="manage_books.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </section>
    </main>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Cover image preview
        document.getElementById('cover-upload').addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('cover-preview').src = e.target.result;
                }
                reader.readAsDataURL(this.files[0]);
                document.getElementById('cover-file-name').textContent = 'Selected: ' + this.files[0].name;
            }
        });

        // PDF file name display
        document.getElementById('pdf-upload').addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                document.getElementById('pdf-file-name').textContent = 'Selected: ' + this.files[0].name;
            }
        });

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const totalPages = document.getElementById('total_pages').value;
            if (totalPages < 1) {
                alert('Total pages must be at least 1');
                e.preventDefault();
            }
        });
    });
</script>
</body>
</html>