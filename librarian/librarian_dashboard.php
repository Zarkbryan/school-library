<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'librarian') {
    header("Location: index.php");
    exit();
}

require_once '../includes/db_connection.php';

// Get all borrowed books
$borrowed_books = [];
$stmt = $conn->prepare("SELECT b.title, b.author, u.name as borrower, bb.borrow_date, bb.return_date 
                       FROM borrowed_books bb 
                       JOIN books b ON bb.book_id = b.id 
                       JOIN users u ON bb.user_id = u.id 
                       WHERE bb.returned = FALSE");
$stmt->execute();
$result = $stmt->get_result();
if ($result) $borrowed_books = $result->fetch_all(MYSQLI_ASSOC);

// Get book count
$book_count = 0;
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM books");
$stmt->execute();
$result = $stmt->get_result();
if ($result) $book_count = $result->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Librarian Dashboard</title>
    <link rel="stylesheet" href="../Styling/dashboard_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
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
            <h1>Welcome, Librarian <?= htmlspecialchars($_SESSION['user']['name']) ?>!</h1>
            <div class="user-info">
                <a href="profile.php">
                    <img src="<?= htmlspecialchars('../' . ($_SESSION['user']['profile_pic'] ?? 'images/default_profile.png')) ?>" 
                         alt="Profile" class="header-profile-image">
                </a>
                <span><?= htmlspecialchars($_SESSION['user']['username']) ?></span>
            </div>
        </header>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert success"><?= htmlspecialchars($_GET['success']) ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <div class="alert error"><?= htmlspecialchars($_GET['error']) ?></div>
        <?php endif; ?>

        <section class="dashboard-stats">
            <div class="card">
                <h3>Total Books</h3>
                <p><?= $book_count ?></p>
            </div>
            <div class="card">
                <h3>Books Borrowed</h3>
                <p><?= count($borrowed_books) ?></p>
            </div>
            <div class="card">
                <h3>Overdue Books</h3>
                <p><?= count(array_filter($borrowed_books, function($book) {
                    return strtotime($book['return_date']) < time();
                })) ?></p>
            </div>
        </section>

        <section class="recent-activity">
            <h2>Currently Borrowed Books</h2>
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Author</th>
                        <th>Borrower</th>
                        <th>Borrow Date</th>
                        <th>Return Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($borrowed_books as $book): ?>
                        <tr>
                            <td><?= htmlspecialchars($book['title']) ?></td>
                            <td><?= htmlspecialchars($book['author']) ?></td>
                            <td><?= htmlspecialchars($book['borrower']) ?></td>
                            <td><?= htmlspecialchars($book['borrow_date']) ?></td>
                            <td><?= htmlspecialchars($book['return_date']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
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