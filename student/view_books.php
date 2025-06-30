<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'student') {
    header("Location: ../index.php");
    exit();
}

require_once '../includes/db_connection.php';

$student_id = $_SESSION['user']['id'];

// Get all books with status indicators
$books = [];
$stmt = $conn->prepare("SELECT b.*,
                       (SELECT COUNT(*) FROM borrowed_books WHERE book_id = b.id AND returned = FALSE) as is_borrowed,
                       (SELECT COUNT(*) FROM reserved_books WHERE book_id = b.id AND fulfilled = FALSE) as is_reserved
                       FROM books b");
$stmt->execute();
$result = $stmt->get_result();
if ($result) $books = $result->fetch_all(MYSQLI_ASSOC);

// Get student's borrowed books (for showing return button)
$borrowed_books = [];
$stmt = $conn->prepare("SELECT book_id, id as borrow_id FROM borrowed_books WHERE user_id = ? AND returned = FALSE");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $borrowed_books[$row['book_id']] = $row['borrow_id'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Books | Library System</title>
    <link rel="stylesheet" href="../Styling/dashboard_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        .search-container {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }
        
        .search-container input {
            flex: 1;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        
        .books-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .book-card {
            background: white;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        
        .book-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .book-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        
        .book-card h3 {
            margin: 0 0 5px;
            font-size: 1.1rem;
            color: #333;
        }
        
        .book-card p {
            margin: 0 0 10px;
            color: #666;
            font-size: 0.9rem;
        }
        
        .book-actions {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .btn {
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            text-align: center;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background-color: #4e73df;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #3a5bbf;
        }
        
        .btn-success {
            background-color: #1cc88a;
            color: white;
        }
        
        .btn-success:hover {
            background-color: #17a673;
        }
        
        .btn-danger {
            background-color: #e74a3b;
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c03526;
        }
        
        .btn-disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .status-borrowed {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-reserved {
            background-color: #fff3cd;
            color: #856404;
        }
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
            <li><a href="student_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li class="active"><a href="view_books.php"><i class="fas fa-book-open"></i> View Books</a></li>
            <li><a href="reserve_book.php"><i class="fas fa-calendar-plus"></i> Reserve Book</a></li>
            <li><a href="borrow_history.php"><i class="fas fa-history"></i> Borrow History</a></li>
            <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
            <li><a href="../db_operations.php?action=logout"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <header>
            <h1>View Books</h1>
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

        <div class="search-container">
            <input type="text" id="searchInput" placeholder="Search by title or author..." 
                   oninput="filterBooks()">
        </div>

        <div class="books-grid" id="booksContainer">
            <?php foreach ($books as $book): 
                $cover_image = !empty($book['cover_image']) ? '../' . $book['cover_image'] : '../images/default_book.png';
                $is_borrowed = $book['is_borrowed'] > 0;
                $is_reserved = $book['is_reserved'] > 0;
                $is_available = !$is_borrowed && !$is_reserved;
                
                $student_has_borrowed = array_key_exists($book['id'], $borrowed_books);
            ?>
                <div class="book-card" data-title="<?= htmlspecialchars(strtolower($book['title'])) ?>" 
                     data-author="<?= htmlspecialchars(strtolower($book['author'])) ?>">
                    <?php if ($is_borrowed): ?>
                        <span class="status-badge status-borrowed">
                            <?= $student_has_borrowed ? '<i class="fas fa-check-circle"></i> Borrowed by You' : '<i class="fas fa-times-circle"></i> Currently Borrowed' ?>
                        </span>
                        <?php if ($student_has_borrowed): ?>
                            <div class="due-date">
                                Return Due: <?= date('M j, Y', strtotime('+14 days')) ?>
                            </div>
                        <?php endif; ?>
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
                        
                        <!-- Borrow/Return Button -->
                        <?php if ($student_has_borrowed): ?>
                            <form action="return_book.php" method="POST">
                                <input type="hidden" name="borrow_id" value="<?= $borrowed_books[$book['id']] ?>">
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
});

function filterBooks() {
    const input = document.getElementById('searchInput');
    const filter = input.value.toLowerCase();
    const booksContainer = document.getElementById('booksContainer');
    const books = booksContainer.getElementsByClassName('book-card');

    for (let i = 0; i < books.length; i++) {
        const title = books[i].getAttribute('data-title');
        const author = books[i].getAttribute('data-author');
        
        if (title.includes(filter) || author.includes(filter)) {
            books[i].style.display = "";
        } else {
            books[i].style.display = "none";
        }
    }
}
</script>
</body>
</html>