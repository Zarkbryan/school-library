<?php
session_start();
require_once '../includes/db_connection.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'student') {
    header("Location: ../index.php");
    exit();
}

$student_id = $_SESSION['user']['id'];

// Get student's borrowed books
$borrowed_books = [];
$stmt = $conn->prepare("SELECT b.*, bb.id as borrow_id, bb.due_date 
                       FROM borrowed_books bb 
                       JOIN books b ON bb.book_id = b.id 
                       WHERE bb.user_id = ? AND bb.returned = FALSE");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result) $borrowed_books = $result->fetch_all(MYSQLI_ASSOC);

// Get student's reserved books
$reserved_books = [];
$stmt = $conn->prepare("SELECT b.*, rb.id as reservation_id, rb.reservation_date 
                       FROM reserved_books rb 
                       JOIN books b ON rb.book_id = b.id 
                       WHERE rb.user_id = ? AND rb.fulfilled = FALSE");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result) $reserved_books = $result->fetch_all(MYSQLI_ASSOC);

// Get all books with status indicators
$all_books = [];
$stmt = $conn->prepare("SELECT b.*,
                       (SELECT COUNT(*) FROM borrowed_books WHERE book_id = b.id AND returned = FALSE) as is_borrowed,
                       (SELECT COUNT(*) FROM reserved_books WHERE book_id = b.id AND fulfilled = FALSE) as is_reserved
                       FROM books b");
$stmt->execute();
$result = $stmt->get_result();
if ($result) $all_books = $result->fetch_all(MYSQLI_ASSOC);

// Get reading progress
$reading_progress = [];
$stmt = $conn->prepare("SELECT book_id, pages_read FROM reading_progress WHERE student_id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $reading_progress[$row['book_id']] = $row['pages_read'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Dashboard</title>
    <link rel="stylesheet" href="../Styling/dashboard_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* ... (keep all your existing CSS styles) ... */
    </style>
</head>
<body>
<!-- Mobile Menu Toggle Button -->
<button class="menu-toggle" aria-label="Toggle menu">
    <i class="fas fa-bars"></i>
</button>
<div class="dashboard-container">
    <aside class="sidebar">
        <h2>Library</h2>
        <ul>
            <li class="active"><a href="student_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="view_books.php"><i class="fas fa-book-open"></i> View Books</a></li>
            <li><a href="reserve_book.php"><i class="fas fa-calendar-plus"></i> Reserve Book</a></li>
            <li><a href="borrow_history.php"><i class="fas fa-history"></i> Borrow History</a></li>
            <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
            <li><a href="../db_operations.php?action=logout"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <header>
            <h1>Welcome, <?= htmlspecialchars($_SESSION['user']['name']) ?>!</h1>
            <div class="user-info">
                <a href="profile.php">
                    <img src="<?= htmlspecialchars('../' . ($_SESSION['user']['profile_pic'] ?? 'images/default_profile.png')) ?>" 
                         alt="Profile" class="header-profile-image">
                </a>
                <span><?= htmlspecialchars($_SESSION['user']['username']) ?></span>
            </div>
        </header>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert success"><?= htmlspecialchars($_SESSION['success']) ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert error"><?= htmlspecialchars($_SESSION['error']) ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <section class="dashboard-stats">
            <div class="card">
                <h3>Books Borrowed</h3>
                <p><?= count($borrowed_books) ?></p>
            </div>
            <div class="card">
                <h3>Books Reserved</h3>
                <p><?= count($reserved_books) ?></p>
            </div>
            <div class="card">
                <h3>Books Available</h3>
                <p><?= count(array_filter($all_books, function($book) { return !$book['is_borrowed'] && !$book['is_reserved']; })) ?></p>
            </div>
        </section>

        <section class="recent-books">
            <h2>Library Books</h2>
            <div class="books-grid">
                <?php foreach ($all_books as $book): 
                    $cover_image = !empty($book['cover_image']) ? '../' . $book['cover_image'] : '../images/default_book.png';
                    $is_borrowed = $book['is_borrowed'] > 0;
                    $is_reserved = $book['is_reserved'] > 0;
                    $is_available = !$is_borrowed && !$is_reserved;
                    
                    // Check if current student has borrowed this book
                    $student_has_borrowed = false;
                    $borrow_id = null;
                    $due_date = null;
                    
                    foreach ($borrowed_books as $borrowed) {
                        if ($borrowed['id'] == $book['id']) {
                            $student_has_borrowed = true;
                            $borrow_id = $borrowed['borrow_id'];
                            $due_date = $borrowed['due_date'];
                            break;
                        }
                    }
                ?>
                    <div class="book-card">
                        <?php if ($student_has_borrowed): ?>
                            <span class="status-badge status-borrowed">
                                <i class="fas fa-check-circle"></i> Borrowed by You
                            </span>
                            <div class="due-date">
                                Due: <?= date('M j, Y', strtotime($due_date)) ?>
                            </div>
                        <?php elseif ($is_borrowed): ?>
                            <span class="status-badge status-borrowed">
                                <i class="fas fa-times-circle"></i> Currently Borrowed
                            </span>
                        <?php elseif ($is_reserved): ?>
                            <span class="status-badge status-reserved">
                                <i class="fas fa-clock"></i> Reserved
                            </span>
                        <?php endif; ?>
                        
                        <img src="<?= htmlspecialchars($cover_image) ?>" alt="Book Cover">
                        <h3><?= htmlspecialchars($book['title']) ?></h3>
                        <p>by <?= htmlspecialchars($book['author']) ?></p>
                        
                        <div class="book-actions">
                            <!-- Read Button -->
                            <?php if (!empty($book['pdf_file'])): ?>
                                <a href="../<?= htmlspecialchars($book['pdf_file']) ?>" 
                                   target="_blank" 
                                   class="btn btn-primary">
                                    <i class="fas fa-book-open"></i> Read
                                </a>
                            <?php else: ?>
                                <button class="btn btn-primary btn-disabled" disabled>
                                    <i class="fas fa-book-open"></i> Read
                                </button>
                            <?php endif; ?>
                            
                            <!-- Borrow/Return Buttons -->
                            <?php if ($student_has_borrowed): ?>
                                    <form action="return_book.php" method="POST">
                                        <input type="hidden" name="borrow_id" value="<?= $borrow_id ?>">
                                        <button type="submit" class="btn btn-danger">
                                            <i class="fas fa-undo"></i> Return Book
                                        </button>
                                    </form>
                            <?php elseif ($is_available): ?>
                                <a href="borrow_book.php?book_id=<?= $book['id'] ?>" class="btn btn-success">
                                    <i class="fas fa-hand-holding"></i> Borrow Book
                                </a>
                            <?php else: ?>
                                <button class="btn btn-secondary btn-disabled" disabled>
                                    <i class="fas fa-ban"></i> Not Available
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const menuToggle = document.querySelector('.menu-toggle');
    const sidebar = document.querySelector('.sidebar');
    
    menuToggle.addEventListener('click', function() {
        sidebar.classList.toggle('active');
        this.classList.toggle('active');
    });

    // Close sidebar when clicking outside
    document.addEventListener('click', function(event) {
        if (!sidebar.contains(event.target) && !menuToggle.contains(event.target)) {
            sidebar.classList.remove('active');
            menuToggle.classList.remove('active');
        }
    });

    // Track reading progress (simplified example)
    document.querySelectorAll('a[target="_blank"]').forEach(link => {
        link.addEventListener('click', function() {
            const bookId = this.closest('.book-card').querySelector('[name="book_id"]')?.value;
            if (bookId) {
                fetch('../db_operations.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=track_reading&book_id=${bookId}`
                });
            }
        });
    });
});
</script>
</body>
</html>