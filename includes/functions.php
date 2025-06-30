<?php
// Make sure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Sanitize input
function sanitize_input($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

// Redirect with message using session
function redirect_with_message($url, $type, $message) {
    $_SESSION["{$type}_message"] = $message;
    header("Location: $url");
    exit();
}

// Simple redirect
function redirect($url) {
    header("Location: $url");
    exit();
}

// Check if user is logged in
function is_logged_in() {
    return isset($_SESSION['user']);
}

// Enforce specific user role
function require_role($role) {
    if (!is_logged_in() || $_SESSION['user']['role'] !== $role) {
        redirect('../index.php');
    }
}

// Get book status by ID
function get_book_status($book_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT status FROM books WHERE id = ?");
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row ? $row['status'] : null;
}

// Get borrowed books for a user
function get_borrowed_books($conn, $user_id) {
    $stmt = $conn->prepare("SELECT b.*, bb.id as borrow_id, bb.borrow_date, bb.due_date, bb.returned
                            FROM borrowed_books bb
                            JOIN books b ON bb.book_id = b.id
                            WHERE bb.user_id = ?
                            ORDER BY bb.borrow_date DESC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get reserved books for a user
function get_reserved_books($conn, $user_id) {
    $stmt = $conn->prepare("SELECT b.*, rb.id as reservation_id, rb.reservation_date 
                            FROM reserved_books rb
                            JOIN books b ON rb.book_id = b.id
                            WHERE rb.user_id = ? AND rb.fulfilled = FALSE
                            ORDER BY rb.reservation_date DESC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get all available books (not reserved and with copies)
function get_available_books($conn) {
    $stmt = $conn->prepare("SELECT b.* FROM books b
                            WHERE b.copies_available > 0
                            AND b.id NOT IN (
                                SELECT book_id FROM reserved_books WHERE fulfilled = FALSE
                            )");
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get reading progress for a user
function get_reading_progress($conn, $user_id) {
    $stmt = $conn->prepare("SELECT book_id, pages_read, progress FROM reading_progress 
                            WHERE student_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $progress = [];
    while ($row = $result->fetch_assoc()) {
        $progress[$row['book_id']] = $row;
    }
    return $progress;
}

// Check if book is currently borrowed by the user
function is_book_borrowed_by_user($conn, $book_id, $user_id) {
    $stmt = $conn->prepare("SELECT id FROM borrowed_books 
                            WHERE book_id = ? AND user_id = ? AND returned = FALSE");
    $stmt->bind_param("ii", $book_id, $user_id);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

// Check if book is reserved by the user
function is_book_reserved_by_user($conn, $book_id, $user_id) {
    $stmt = $conn->prepare("SELECT id FROM reserved_books 
                            WHERE book_id = ? AND user_id = ? AND fulfilled = FALSE");
    $stmt->bind_param("ii", $book_id, $user_id);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}
?>
