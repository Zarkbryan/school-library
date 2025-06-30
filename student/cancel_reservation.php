<?php
require_once '../includes/auth_check.php';
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';

$reservation_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get reservation details
$reservation = $conn->query("SELECT * FROM reserved_books WHERE id = $reservation_id AND user_id = {$_SESSION['user']['id']}")->fetch_assoc();

if (!$reservation) {
    redirect_with_message("borrow_history.php", "error", "Reservation not found");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Delete reservation
    $conn->query("DELETE FROM reserved_books WHERE id = $reservation_id");
    
    // Update book status
    $conn->query("UPDATE books SET status = 'available' WHERE id = {$reservation['book_id']}");
    
    redirect_with_message("borrow_history.php", "success", "Reservation cancelled successfully");
}

$book = $conn->query("SELECT * FROM books WHERE id = {$reservation['book_id']}")->fetch_assoc();
$page_title = "Cancel Reservation";

include '../includes/header.php';
?>

<div class="container">
    <h2>Cancel Reservation</h2>
    <div class="alert alert-warning">
        Are you sure you want to cancel your reservation for <strong><?php echo $book['title']; ?></strong>?
    </div>
    
    <form method="post">
        <button type="submit" class="btn btn-danger">Yes, Cancel Reservation</button>
        <a href="borrow_history.php" class="btn btn-secondary">No, Keep Reservation</a>
    </form>
</div>

<?php include '../includes/footer.php'; ?>