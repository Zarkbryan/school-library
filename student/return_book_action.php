<?php
// This block would be inside db_operations.php
// Ensure session_start(); and require_once 'includes/db_connection.php'; are at the top of db_operations.php

if (isset($_POST['action']) && $_POST['action'] === 'return_book_action') {
    
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'student') {
        $_SESSION['error_message'] = "Authentication required.";
        header("Location: ../student/student_dashboard.php"); // Adjust redirect as needed
        exit();
    }
    $student_id = $_SESSION['user']['id'];

    if (!isset($_POST['borrow_id']) || !filter_var($_POST['borrow_id'], FILTER_VALIDATE_INT)) {
        $_SESSION['error_message'] = "Invalid borrow record ID specified.";
        header("Location: ../student/borrow_history.php"); // Or student_dashboard.php
        exit();
    }
    $borrow_id = (int)$_POST['borrow_id'];

    // Default redirect page (relative to db_operations.php in parent directory)
    $redirect_page = '../student/borrow_history.php'; 

    $conn->begin_transaction();
    try {
        // Verify the borrow record belongs to this student and is not already returned
        $stmt_check = $conn->prepare(
            "SELECT bb.id, bb.book_id, b.title 
             FROM borrowed_books bb 
             JOIN books b ON bb.book_id = b.id 
             WHERE bb.id = ? AND bb.user_id = ? AND bb.returned = FALSE"
        );
        if (!$stmt_check) throw new Exception("Database Error: Could not prepare statement to check borrow record. (" . $conn->errno . ") " . $conn->error);
        
        $stmt_check->bind_param("ii", $borrow_id, $student_id);
        if (!$stmt_check->execute()) {
            throw new Exception("Database Error: Could not execute statement to check borrow record. (" . $stmt_check->errno . ") " . $stmt_check->error);
        }
        
        $result_check = $stmt_check->get_result();
        $borrow_record = $result_check->fetch_assoc();
        $stmt_check->close();

        if (!$borrow_record) {
            // Check if it was already returned, to give a more specific message
            $stmt_already_returned_check = $conn->prepare("SELECT id FROM borrowed_books WHERE id = ? AND user_id = ? AND returned = TRUE");
            if ($stmt_already_returned_check) {
                $stmt_already_returned_check->bind_param("ii", $borrow_id, $student_id);
                $stmt_already_returned_check->execute();
                if ($stmt_already_returned_check->get_result()->num_rows > 0) {
                    throw new Exception("This book has already been marked as returned.");
                }
                $stmt_already_returned_check->close();
            }
            throw new Exception("Borrow record not found, cannot be returned, or does not belong to you.");
        }
        $book_title_for_message = $borrow_record['title'];

        // Update the borrowed_books table
        $return_date = date('Y-m-d H:i:s');
        $stmt_update = $conn->prepare("UPDATE borrowed_books SET returned = TRUE, return_date = ? WHERE id = ?");
        if (!$stmt_update) throw new Exception("Database Error: Could not prepare statement to update return status. (" . $conn->errno . ") " . $conn->error);
        
        $stmt_update->bind_param("si", $return_date, $borrow_id);
        if (!$stmt_update->execute()) {
            throw new Exception("Database Error: Could not update return status. (" . $stmt_update->errno . ") " . $stmt_update->error);
        }
        
        if ($stmt_update->affected_rows === 0) {
            // This is unusual if the above $stmt_check passed, as it means the record matched but wasn't updated.
            // Could indicate it was already returned between the check and update (unlikely with transaction if FOR UPDATE was used on check, but we didn't use it here).
            error_log("Return action: affected_rows was 0 for borrow_id {$borrow_id} after initial checks passed.");
            throw new Exception("No changes made. The book might have just been returned or an issue occurred.");
        }
        $stmt_update->close();
        
        // Optional: Add Notification for the return
        $notification_message = "Book '{$book_title_for_message}' was returned by " . ($_SESSION['user']['username'] ?? 'student') . ".";
        $actor_student_id = $student_id;
        $recipient_admin_id = 1; // Example Admin ID
        $notification_type = 'return';
        $related_book_id = $borrow_record['book_id'];

        $stmt_notify = $conn->prepare(
            "INSERT INTO notifications (recipient_user_id, actor_user_id, message, type, related_book_id, is_read) 
             VALUES (?, ?, ?, ?, ?, FALSE)"
        );
        if ($stmt_notify) { // Proceed even if notification fails to keep core functionality working
            $stmt_notify->bind_param("iisii", $recipient_admin_id, $actor_student_id, $notification_message, $notification_type, $related_book_id);
            if (!$stmt_notify->execute()) {
                error_log("Failed to insert return notification: " . $stmt_notify->error);
            }
            $stmt_notify->close();
        } else {
            error_log("DB Error (Return Notification Prepare): " . $conn->error);
        }

        $conn->commit();
        $_SESSION['success_message'] = "Book '{$book_title_for_message}' returned successfully!";

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = "Return failed: " . $e->getMessage();
        // Log the detailed technical error for your own debugging
        error_log("Book return process error for student_id {$student_id}, borrow_id {$borrow_id}: " . $e->getMessage() . " --- SQL Error (if any): " . $conn->error);
    }

    // After successful return
        $username = $_SESSION['user']['username'];
        $action = "returned a book (Book ID: $book_id)";
        $log = $conn->prepare("INSERT INTO system_logs (username, action, timestamp) VALUES (?, ?, NOW())");
        $log->bind_param("ss", $username, $action);
        $log->execute();
    
    header("Location: " . $redirect_page);
    exit();
}
?>