<?php
session_start();
require_once '../includes/db_connection.php';

// Verify user is logged in as student
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'student') {
    $_SESSION['error'] = "You must be logged in as a student to borrow books.";
    header("Location: ../index.php");
    exit();
}

$student_id = $_SESSION['user']['id'];

if (isset($_GET['book_id'])) {
    $book_id = (int)$_GET['book_id'];
    
    // Get book details
    $stmt = $conn->prepare("SELECT id, title FROM books WHERE id = ?");
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $book = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$book) {
        $_SESSION['error'] = "Book not found.";
        header("Location: student_dashboard.php");
        exit();
    }
    
    // Check if book is available
    $stmt = $conn->prepare("SELECT available FROM books WHERE id = ?");
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$result['available']) {
        $_SESSION['error'] = "This book is currently unavailable.";
        header("Location: student_dashboard.php");
        exit();
    }
    
    // Check if student already has this book borrowed
    $stmt = $conn->prepare("SELECT id FROM borrowed_books 
                          WHERE user_id = ? AND book_id = ? AND returned = FALSE");
    $stmt->bind_param("ii", $student_id, $book_id);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        $_SESSION['error'] = "You already have this book checked out.";
        header("Location: student_dashboard.php");
        exit();
    }
    $stmt->close();
    
    // Check student's borrowing limit (max 3 books)
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM borrowed_books 
                          WHERE user_id = ? AND returned = FALSE");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($result['count'] >= 3) {
        $_SESSION['error'] = "You've reached your borrowing limit of 3 books.";
        header("Location: student_dashboard.php");
        exit();
    }
    
    // Display borrow form
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Borrow Book</title>
        <link rel="stylesheet" href="../Styling/dashboard_style.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
        <style>
            .borrow-form-container {
                max-width: 600px;
                margin: 20px auto;
                padding: 20px;
                background: white;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            .borrow-form-container h2 {
                margin-top: 0;
                color: #4e73df;
            }
            .form-group {
                margin-bottom: 15px;
            }
            .form-group label {
                display: block;
                margin-bottom: 5px;
                font-weight: 600;
            }
            .form-group input, .form-group select {
                width: 100%;
                padding: 8px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-size: 16px;
            }
            .form-actions {
                display: flex;
                justify-content: flex-end;
                gap: 10px;
                margin-top: 20px;
            }
            .btn {
                padding: 8px 15px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 16px;
                font-weight: 600;
            }
            .btn-primary {
                background-color: #4e73df;
                color: white;
            }
            .btn-secondary {
                background-color: #6c757d;
                color: white;
            }
            .book-info {
                display: flex;
                margin-bottom: 20px;
                align-items: center;
            }
            .book-info img {
                width: 100px;
                height: 150px;
                object-fit: cover;
                margin-right: 20px;
                border-radius: 4px;
            }
            .book-details h3 {
                margin: 0 0 5px;
            }
            .book-details p {
                margin: 0;
                color: #666;
            }
        </style>
    </head>
    <body>
    <div class="dashboard-container">
        <aside class="sidebar">
            <h2>Library</h2>
            <ul>
                <li><a href="student_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="view_books.php"><i class="fas fa-book-open"></i> View Books</a></li>
                <li><a href="reserve_book.php"><i class="fas fa-calendar-plus"></i> Reserve Book</a></li>
                <li><a href="borrow_history.php"><i class="fas fa-history"></i> Borrow History</a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                <li><a href="../db_operations.php?action=logout"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <header>
                <h1>Borrow Book</h1>
                <div class="user-info">
                    <a href="profile.php">
                        <img src="<?= htmlspecialchars('../' . ($_SESSION['user']['profile_pic'] ?? 'images/default_profile.png')) ?>" 
                             alt="Profile" class="header-profile-image">
                    </a>
                    <span><?= htmlspecialchars($_SESSION['user']['username']) ?></span>
                </div>
            </header>

            <div class="borrow-form-container">
                <h2>Confirm Book Borrow</h2>
                
                <div class="book-info">
                    <?php 
                    $cover_image = !empty($book['cover_image']) ? '../' . $book['cover_image'] : '../images/default_book.png';
                    ?>
                    <img src="<?= htmlspecialchars($cover_image) ?>" alt="Book Cover">
                    <div class="book-details">
                        <h3><?= htmlspecialchars($book['title']) ?></h3>
                        <p>Book ID: <?= htmlspecialchars($book['id']) ?></p>
                    </div>
                </div>
                
                <form action="process_borrow.php" method="POST">
                    <input type="hidden" name="book_id" value="<?= $book['id'] ?>">
                    
                    <div class="form-group">
                        <label for="borrow_date">Borrow Date</label>
                        <input type="date" id="borrow_date" name="borrow_date" 
                               value="<?= date('Y-m-d') ?>" required readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="due_date">Due Date (14 days from today)</label>
                        <input type="date" id="due_date" name="due_date" 
                               value="<?= date('Y-m-d', strtotime('+14 days')) ?>" required readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="return_date">Expected Return Date</label>
                        <input type="date" id="return_date" name="return_date" 
                               min="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d', strtotime('+30 days')) ?>" 
                               value="<?= date('Y-m-d', strtotime('+14 days')) ?>" required>
                    </div>
                    
                    <div class="form-actions">
                        <a href="student_dashboard.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Confirm Borrow</button>
                    </div>
                </form>
            </div>
        </main>
    </div>
    </body>
    </html>
    <?php
    exit();
}


// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_id'])) {
    $book_id = (int)$_POST['book_id'];
    $borrow_date = $_POST['borrow_date'];
    $due_date = $_POST['due_date'];
    $return_date = $_POST['return_date'];
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // 1. Verify book exists and lock row
        $stmt = $conn->prepare("SELECT id, title FROM books WHERE id = ? FOR UPDATE");
        $stmt->bind_param("i", $book_id);
        $stmt->execute();
        $book = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$book) {
            throw new Exception("Book not found in our system.");
        }

        // 2. Check if student already has this book borrowed
        $stmt = $conn->prepare("SELECT id FROM borrowed_books 
                              WHERE user_id = ? AND book_id = ? AND returned = FALSE");
        $stmt->bind_param("ii", $student_id, $book_id);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows > 0) {
            throw new Exception("You already have this book checked out.");
        }
        $stmt->close();

        // 3. Check student's borrowing limit (max 3 books)
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM borrowed_books 
                              WHERE user_id = ? AND returned = FALSE");
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($result['count'] >= 3) {
            throw new Exception("You've reached your borrowing limit of 3 books.");
        }

        // 4. Check if book is currently available
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM borrowed_books 
                              WHERE book_id = ? AND returned = FALSE");
        $stmt->bind_param("i", $book_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($result['count'] > 0) {
            throw new Exception("This book is currently checked out by another student.");
        }

        // 5. Create the borrow record
        $stmt = $conn->prepare("INSERT INTO borrowed_books 
                              (user_id, book_id, borrow_date, due_date, return_date, returned) 
                              VALUES (?, ?, ?, ?, ?, FALSE)");
        $stmt->bind_param("iisss", $student_id, $book_id, $borrow_date, $due_date, $return_date);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to record the borrow. Please try again.");
        }
        $borrow_id = $stmt->insert_id;
        $stmt->close();

        // 6. Update book status
        
        $stmt->bind_param("i", $book_id);
        $stmt->execute();
        $stmt->close();

        // 7. Create notification for admin
        $message = "Student {$_SESSION['user']['name']} borrowed '{$book['title']}'";
        $stmt = $conn->prepare("INSERT INTO notifications 
                              (user_id, message, type, related_book_id) 
                              VALUES (?, ?, 'borrow', ?)");
        $admin_id = 1; // Assuming admin ID is 1
        $stmt->bind_param("isi", $admin_id, $message, $book_id);
        $stmt->execute();
        $stmt->close();

        // Commit transaction
        $conn->commit();
        
        $_SESSION['success'] = "Successfully borrowed '{$book['title']}'. Due back on " . date('F j, Y', strtotime($due_date));
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = $e->getMessage();
    }
    
    header("Location: student_dashboard.php");
    exit();
} else {
    $_SESSION['error'] = "Invalid request.";
    header("Location: student_dashboard.php");
    exit();
}

?>