<?php
session_start();
require_once '../includes/db_connection.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'student') {
    header("Location: ../index.php");
    exit();
}

$student_id = $_SESSION['user']['id'];

// Process reservation form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reserve_book'])) {
    $book_id = (int)$_POST['book_id'];
    $pickup_date = $_POST['pickup_date'];
    $reservation_date = date('Y-m-d H:i:s');
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // 1. Verify book is available
        $stmt = $conn->prepare("SELECT id, title FROM books WHERE id = ? AND available = TRUE");
        $stmt->bind_param("i", $book_id);
        $stmt->execute();
        $book = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$book) {
            throw new Exception("This book is no longer available for reservation.");
        }

        // 2. Check if student already has a reservation for this book
        $stmt = $conn->prepare("SELECT id FROM reserved_books WHERE user_id = ? AND book_id = ? AND fulfilled = FALSE");
        $stmt->bind_param("ii", $student_id, $book_id);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows > 0) {
            throw new Exception("You already have a pending reservation for this book.");
        }
        $stmt->close();

        // 3. Create reservation record
        $stmt = $conn->prepare("INSERT INTO reserved_books (user_id, book_id, reservation_date, pickup_date, fulfilled) VALUES (?, ?, ?, ?, FALSE)");
        $stmt->bind_param("isss", $student_id, $book_id, $reservation_date, $pickup_date);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to create reservation. Please try again.");
        }
        $stmt->close();

        // 4. Update book availability
        $stmt = $conn->prepare("UPDATE books SET available = FALSE WHERE id = ?");
        $stmt->bind_param("i", $book_id);
        $stmt->execute();
        $stmt->close();

        // 5. Notify admin
        $message = "Student {$_SESSION['user']['name']} reserved '{$book['title']}' (Pickup by: $pickup_date)";
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type, related_book_id) VALUES (?, ?, 'reservation', ?)");
        $admin_id = 1; // Assuming admin ID is 1
        $stmt->bind_param("isi", $admin_id, $message, $book_id);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        $_SESSION['success'] = "Successfully reserved '{$book['title']}'. Please pick up by $pickup_date.";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = $e->getMessage();
    }
    header("Location: reserve_book.php");
    exit();
}

// Process reservation cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_reservation'])) {
    $reservation_id = (int)$_POST['reservation_id'];
    
    $conn->begin_transaction();
    try {
        // 1. Get reservation details
        $stmt = $conn->prepare("SELECT rb.*, b.title FROM reserved_books rb JOIN books b ON rb.book_id = b.id WHERE rb.id = ? AND rb.user_id = ?");
        $stmt->bind_param("ii", $reservation_id, $student_id);
        $stmt->execute();
        $reservation = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$reservation) {
            throw new Exception("Reservation not found or already cancelled.");
        }

        // 2. Delete reservation
        $stmt = $conn->prepare("DELETE FROM reserved_books WHERE id = ?");
        $stmt->bind_param("i", $reservation_id);
        $stmt->execute();
        $stmt->close();

        // 3. Update book availability
        $stmt = $conn->prepare("UPDATE books SET available = TRUE WHERE id = ?");
        $stmt->bind_param("i", $reservation['book_id']);
        $stmt->execute();
        $stmt->close();

        // 4. Notify admin
        $message = "Student {$_SESSION['user']['name']} cancelled reservation for '{$reservation['title']}'";
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type, related_book_id) VALUES (?, ?, 'reservation_cancelled', ?)");
        $admin_id = 1; // Assuming admin ID is 1
        $stmt->bind_param("isi", $admin_id, $message, $reservation['book_id']);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        $_SESSION['success'] = "Reservation for '{$reservation['title']}' has been cancelled.";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = $e->getMessage();
    }
    header("Location: reserve_book.php");
    exit();
}

// Get available books
$available_books = [];
$stmt = $conn->prepare("SELECT b.* FROM books b WHERE b.available = TRUE");
$stmt->execute();
$result = $stmt->get_result();
if ($result) $available_books = $result->fetch_all(MYSQLI_ASSOC);

// Get student's reservations
$reserved_books = [];
$stmt = $conn->prepare("SELECT b.*, rb.id as reservation_id, rb.reservation_date, rb.pickup_date 
                       FROM reserved_books rb 
                       JOIN books b ON rb.book_id = b.id 
                       WHERE rb.user_id = ? AND rb.fulfilled = FALSE");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result) $reserved_books = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reserve Books | Library System</title>
    <link rel="stylesheet" href="../Styling/dashboard_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* Dashboard Stats */
        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .card h3 {
            margin-top: 0;
            color: #6c757d;
            font-size: 1rem;
        }
        
        .card p {
            font-size: 2rem;
            margin: 10px 0 0;
            color: #4e73df;
            font-weight: bold;
        }
        
        /* Books Grid */
        .books-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .book-card {
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 15px;
            transition: transform 0.3s;
            position: relative;
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
            font-size: 1rem;
            color: #333;
        }
        
        .book-card p {
            margin: 0 0 10px;
            color: #666;
            font-size: 0.9rem;
        }
        
        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .status-reserved {
            background-color: #fff3cd;
            color: #856404;
        }
        
        /* Book Actions */
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
        
        /* Reservation Info */
        .reservation-info {
            font-size: 0.8rem;
            color: #6c757d;
            margin-bottom: 10px;
        }
        
        /* Search Bar */
        .search-container {
            margin-bottom: 20px;
            position: relative;
        }
        
        .search-container input {
            width: 100%;
            padding: 10px 15px 10px 40px;
            border: 1px solid #ddd;
            border-radius: 30px;
            font-size: 0.95rem;
        }
        
        .search-container::before {
            content: "\f002";
            font-family: "Font Awesome 5 Free";
            font-weight: 900;
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #aaa;
        }
        
        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .reservation-form {
            background: white;
            border-radius: 8px;
            padding: 25px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        
        .form-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
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
            <li><a href="view_books.php"><i class="fas fa-book-open"></i> View Books</a></li>
            <li class="active"><a href="reserve_book.php"><i class="fas fa-calendar-plus"></i> Reserve Book</a></li>
            <li><a href="borrow_history.php"><i class="fas fa-history"></i> Borrow History</a></li>
            <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
            <li><a href="../db_operations.php?action=logout"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <header>
            <h1>Reserve Books</h1>
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
                <h3>Books Available</h3>
                <p><?= count($available_books) ?></p>
            </div>
            <div class="card">
                <h3>Your Reservations</h3>
                <p><?= count($reserved_books) ?></p>
            </div>
        </section>

        <div class="search-container">
            <input type="text" id="searchInput" placeholder="Search books..." oninput="filterBooks()">
        </div>

        <section class="recent-books">
            <h2>Available for Reservation</h2>
            <div class="books-grid" id="availableBooks">
                <?php foreach ($available_books as $book): 
                    $cover_image = !empty($book['cover_image']) ? '../' . $book['cover_image'] : '../images/default_book.png';
                ?>
                    <div class="book-card" data-title="<?= htmlspecialchars(strtolower($book['title'])) ?>" 
                         data-author="<?= htmlspecialchars(strtolower($book['author'])) ?>">
                        <img src="<?= htmlspecialchars($cover_image) ?>" alt="Book Cover">
                        <h3><?= htmlspecialchars($book['title']) ?></h3>
                        <p>by <?= htmlspecialchars($book['author']) ?></p>
                        
                        <div class="book-actions">
                            <button class="btn btn-primary" onclick="showReservationForm(<?= $book['id'] ?>, '<?= htmlspecialchars(addslashes($book['title'])) ?>')">
                                <i class="fas fa-calendar-plus"></i> Reserve Book
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <h2 style="margin-top: 30px;">Your Current Reservations</h2>
            <div class="books-grid" id="reservedBooks">
                <?php foreach ($reserved_books as $book): 
                    $cover_image = !empty($book['cover_image']) ? '../' . $book['cover_image'] : '../images/default_book.png';
                    $reservation_date = date('M j, Y', strtotime($book['reservation_date']));
                    $pickup_date = date('M j, Y', strtotime($book['pickup_date']));
                ?>
                    <div class="book-card" data-title="<?= htmlspecialchars(strtolower($book['title'])) ?>" 
                         data-author="<?= htmlspecialchars(strtolower($book['author'])) ?>">
                        <span class="status-badge status-reserved">
                            <i class="fas fa-clock"></i> Reserved
                        </span>
                        
                        <img src="<?= htmlspecialchars($cover_image) ?>" alt="Book Cover">
                        <h3><?= htmlspecialchars($book['title']) ?></h3>
                        <p>by <?= htmlspecialchars($book['author']) ?></p>
                        
                        <div class="reservation-info">
                            <p>Reserved: <?= $reservation_date ?></p>
                            <p>Pickup by: <?= $pickup_date ?></p>
                        </div>
                        
                        <div class="book-actions">
                            <form action="reserve_book.php" method="POST">
                                <input type="hidden" name="reservation_id" value="<?= $book['reservation_id'] ?>">
                                <button type="submit" name="cancel_reservation" class="btn btn-danger">
                                    <i class="fas fa-times"></i> Cancel Reservation
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </main>
</div>

<!-- Reservation Form Modal -->
<div class="modal-overlay" id="reservationModal">
    <div class="reservation-form">
        <h3>Reserve <span id="reserveBookTitle"></span></h3>
        <form method="POST" action="reserve_book.php">
            <input type="hidden" name="book_id" id="reserveBookId">
            
            <div class="form-group">
                <label for="pickup_date">Pickup Date</label>
                <input type="date" id="pickup_date" name="pickup_date" required>
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="hideReservationForm()">Cancel</button>
                <button type="submit" name="reserve_book" class="btn btn-primary">Confirm Reservation</button>
            </div>
        </form>
    </div>
</div>

<script>
// Toggle sidebar
document.querySelector('.menu-toggle').addEventListener('click', function() {
    document.querySelector('.sidebar').classList.toggle('active');
    this.classList.toggle('active');
});

// Close sidebar when clicking outside
document.addEventListener('click', function(event) {
    const sidebar = document.querySelector('.sidebar');
    const menuToggle = document.querySelector('.menu-toggle');
    
    if (!sidebar.contains(event.target) && !menuToggle.contains(event.target)) {
        sidebar.classList.remove('active');
        menuToggle.classList.remove('active');
    }
});

// Show reservation form
function showReservationForm(bookId, bookTitle) {
    const modal = document.getElementById('reservationModal');
    document.getElementById('reserveBookId').value = bookId;
    document.getElementById('reserveBookTitle').textContent = bookTitle;
    
    // Set date picker (min tomorrow, default +3 days)
    const dateInput = document.getElementById('pickup_date');
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    
    const defaultDate = new Date();
    defaultDate.setDate(defaultDate.getDate() + 3);
    
    dateInput.min = tomorrow.toISOString().split('T')[0];
    dateInput.value = defaultDate.toISOString().split('T')[0];
    
    modal.style.display = 'flex';
}

// Hide reservation form
function hideReservationForm() {
    document.getElementById('reservationModal').style.display = 'none';
}

// Close modal when clicking outside
document.addEventListener('click', function(event) {
    if (event.target === document.getElementById('reservationModal')) {
        hideReservationForm();
    }
});

// Real-time search filtering
function filterBooks() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    
    // Filter available books
    document.querySelectorAll('#availableBooks .book-card').forEach(book => {
        const title = book.getAttribute('data-title');
        const author = book.getAttribute('data-author');
        book.style.display = (title.includes(searchTerm) || author.includes(searchTerm)) ? 'block' : 'none';
    });
    
    // Filter reserved books
    document.querySelectorAll('#reservedBooks .book-card').forEach(book => {
        const title = book.getAttribute('data-title');
        const author = book.getAttribute('data-author');
        book.style.display = (title.includes(searchTerm) || author.includes(searchTerm)) ? 'block' : 'none';
    });
}
</script>
</body>
</html>