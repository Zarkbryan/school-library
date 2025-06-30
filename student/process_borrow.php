<?php
session_start();
require_once '../includes/db_connection.php';

// Debug: Show all errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verify user is logged in as student
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'student') {
    $_SESSION['error'] = "You must be logged in as a student to borrow books.";
    header("Location: ../index.php");
    exit();
}

$student_id = $_SESSION['user']['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_id'])) {
    $book_id = (int)$_POST['book_id'];
    $borrow_date = $_POST['borrow_date'];
    $due_date = $_POST['due_date'];
    
    // Debug: Show the query we're about to execute
    $debug_query = "INSERT INTO borrowed_books 
                   (user_id, book_id, borrow_date, due_date, returned) 
                   VALUES (?, ?, ?, ?, FALSE)";
    error_log("DEBUG: Query to execute: " . $debug_query);
    error_log("DEBUG: Values: user_id=$student_id, book_id=$book_id, borrow_date=$borrow_date, due_date=$due_date");

    // Start transaction
    $conn->begin_transaction();
    
    try {
        // 1. Verify book exists and is available
        $stmt = $conn->prepare("SELECT id, title, available FROM books WHERE id = ? FOR UPDATE");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("i", $book_id);
        $stmt->execute();
        $book = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$book) {
            throw new Exception("Book not found in our system.");
        }
        
        if (!$book['available']) {
            throw new Exception("This book is no longer available.");
        }

        // 2. Check if student already has this book borrowed
        $check_query = "SELECT id FROM borrowed_books 
                       WHERE user_id = ? AND book_id = ? AND returned = FALSE";
        error_log("DEBUG: Checking existing borrow with: " . $check_query);
        
        $stmt = $conn->prepare($check_query);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("ii", $student_id, $book_id);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows > 0) {
            throw new Exception("You already have this book checked out.");
        }
        $stmt->close();
        // 3. Check if book is reserved by another student
        $reservation_query = "SELECT id FROM reserved_books 
                              WHERE book_id = ? AND fulfilled = FALSE"; 
        error_log("DEBUG: Checking reservations with: " . $reservation_query);
        $stmt = $conn->prepare($reservation_query);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("i", $book_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            throw new Exception("This book is reserved by another student.");
        }
        $stmt->close();

        // After successful borrowing
        $username = $_SESSION['user']['username'];
        $action = "borrowed a book (Book ID: $book_id)";
        $log = $conn->prepare("INSERT INTO system_logs (username, action, timestamp) VALUES (?, ?, NOW())");
        $log->bind_param("ss", $username, $action);
        $log->execute();


        // 3. Check student's borrowing limit
        $limit_query = "SELECT COUNT(*) as count FROM borrowed_books 
                       WHERE user_id = ? AND returned = FALSE";
        error_log("DEBUG: Checking borrow limit with: " . $limit_query);
        
        $stmt = $conn->prepare($limit_query);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($result['count'] >= 3) {
            throw new Exception("You've reached your borrowing limit of 3 books.");
        }

        // 4. Create the borrow record
        $insert_query = "INSERT INTO borrowed_books 
                        (user_id, book_id, borrow_date, due_date, returned) 
                        VALUES (?, ?, ?, ?, FALSE)";
        error_log("DEBUG: Inserting with: " . $insert_query);
        
        $stmt = $conn->prepare($insert_query);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("iiss", $student_id, $book_id, $borrow_date, $due_date);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to record the borrow: " . $stmt->error);
        }
        $borrow_id = $stmt->insert_id;
        $stmt->close();

        // 5. Update book availability
        $update_query = "UPDATE books SET available = FALSE WHERE id = ?";
        error_log("DEBUG: Updating availability with: " . $update_query);
        
        $stmt = $conn->prepare($update_query);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("i", $book_id);
        $stmt->execute();
        $stmt->close();

        // Commit transaction
        $conn->commit();
        
        $_SESSION['success'] = "Successfully borrowed '{$book['title']}'. Due back on " . date('F j, Y', strtotime($due_date));
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = $e->getMessage();
        error_log("ERROR: " . $e->getMessage());
    }
    
    header("Location: student_dashboard.php");
    exit();
} else {
    $_SESSION['error'] = "Invalid request.";
    header("Location: student_dashboard.php");
    exit();
}
?>