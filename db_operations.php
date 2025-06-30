<?php
session_start();
require_once 'includes/db_connection.php';

// Handle Registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    // Get form data
    $username = trim($_POST['username']);
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = 'student'; // Fixed role for registration
    $profile_pic = null;

    // Validate inputs
    $errors = [];

    if (empty($username) || empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
        $errors[] = "All fields are required";
    }

    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }

    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }

    // Check if username/email exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $errors[] = "Username or email already exists";
    }
    $stmt->close();

    // Handle file upload
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg', 'image/png', 'image/jpg'];
        $filetype = $_FILES['profile_picture']['type'];
        $filesize = $_FILES['profile_picture']['size'];
        
        if (!in_array($filetype, $allowed)) {
            $errors[] = "Only JPG, JPEG, PNG files allowed";
        }
        
        if ($filesize > 2097152) { // 2MB
            $errors[] = "File too large (max 2MB)";
        }

        // Check for errors                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 
        if (empty($errors)) {
            $upload_dir = 'uploads/profile_pics/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $ext = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '.' . $ext;
            $destination = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $destination)) {
                $profile_pic = $destination;
            } else {
                $errors[] = "Failed to upload profile picture";
            }
        }
    }
    // Handle book borrowing
if (isset($_POST['borrow_book'])) {
    $book_id = intval($_POST['book_id']);
    $user_id = $_SESSION['user']['id'];
    $due_date = date('Y-m-d H:i:s', strtotime('+14 days')); // 2 weeks loan period
    
    // Check if book is available
    $stmt = $conn->prepare("SELECT * FROM books WHERE id = ? AND id NOT IN (
        SELECT book_id FROM borrowed_books WHERE returned = FALSE
    ) AND id NOT IN (
        SELECT book_id FROM reserved_books WHERE fulfilled = FALSE
    )");
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Borrow the book
        $stmt = $conn->prepare("INSERT INTO borrowed_books (book_id, user_id, due_date) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $book_id, $user_id, $due_date);
        
        if ($stmt->execute()) {
            header("Location: student_dashboard.php?success=Book borrowed successfully");
            exit();
        } else {
            header("Location: student_dashboard.php?error=Failed to borrow book");
            exit();
        }
    } else {
        header("Location: student_dashboard.php?error=Book is not available for borrowing");
        exit();
    }
}

// Handle book returning
if (isset($_POST['return_book'])) {
    $borrow_id = intval($_POST['borrow_id']);
    $user_id = $_SESSION['user']['id'];
    
    $stmt = $conn->prepare("UPDATE borrowed_books SET returned = TRUE, returned_date = NOW() WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $borrow_id, $user_id);
    
    if ($stmt->execute()) {
        header("Location: student_dashboard.php?success=Book returned successfully");
        exit();
    } else {
        header("Location: student_dashboard.php?error=Failed to return book");
        exit();
    }
}

// Handle book reservation
if (isset($_POST['reserve_book'])) {
    $book_id = intval($_POST['book_id']);
    $user_id = $_SESSION['user']['id'];
    
    // Check if book is available for reservation
    $stmt = $conn->prepare("SELECT * FROM books WHERE id = ? AND id NOT IN (
        SELECT book_id FROM reserved_books WHERE fulfilled = FALSE
    )");
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Reserve the book
        $stmt = $conn->prepare("INSERT INTO reserved_books (book_id, user_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $book_id, $user_id);
        
        if ($stmt->execute()) {
            header("Location: student_dashboard.php?success=Book reserved successfully");
            exit();
        } else {
            header("Location: student_dashboard.php?error=Failed to reserve book");
            exit();
        }
    } else {
        header("Location: student_dashboard.php?error=Book is not available for reservation");
        exit();
    }
}

// Handle reservation cancellation
if (isset($_POST['cancel_reservation'])) {
    $reservation_id = intval($_POST['reservation_id']);
    $user_id = $_SESSION['user']['id'];
    
    $stmt = $conn->prepare("DELETE FROM reserved_books WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $reservation_id, $user_id);
    
    if ($stmt->execute()) {
        header("Location: student_dashboard.php?success=Reservation cancelled successfully");
        exit();
    } else {
        header("Location: student_dashboard.php?error=Failed to cancel reservation");
        exit();
    }
}
    // Add this at the top with other includes
require_once 'db_connection.php';

function logAction($userId, $username, $role, $action) {
    global $conn;
    
    $stmt = $conn->prepare("INSERT INTO system_logs (user_id, username, role, action) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $userId, $username, $role, $action);
    $stmt->execute();
    $stmt->close();
}

    if (isset($_POST['read_book']) && isset($_SESSION['user']) && $_SESSION['user']['role'] === 'student') {
    $book_id = intval($_POST['book_id']);
    $student_id = $_SESSION['user']['id'];

    // Check if reading progress already exists
    $stmt = $conn->prepare("SELECT id FROM reading_progress WHERE student_id = ? AND book_id = ?");
    $stmt->bind_param("ii", $student_id, $book_id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        // No existing record, insert new progress entry
        $insert = $conn->prepare("INSERT INTO reading_progress (student_id, book_id, pages_read) VALUES (?, ?, 0)");
        $insert->bind_param("ii", $student_id, $book_id);
        if ($insert->execute()) {
            header("Location: student/student_dashboard.php?success=Reading started");
        } else {
            header("Location: student/student_dashboard.php?error=Could not start reading");
        }
    } else {
        // Already tracked, redirect
        header("Location: student/student_dashboard.php");
    }
    // Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: ../index.php");
    exit();
}

// Handle book borrowing
if (isset($_POST['borrow_book'])) {
    $book_id = intval($_POST['book_id']);
    $user_id = $_SESSION['user']['id'];
    $due_date = date('Y-m-d H:i:s', strtotime('+14 days')); // 2 weeks loan period
    
    // Check if book is available
    $stmt = $conn->prepare("SELECT * FROM books WHERE id = ? AND id NOT IN (
        SELECT book_id FROM borrowed_books WHERE returned = FALSE
    ) AND id NOT IN (
        SELECT book_id FROM reserved_books WHERE fulfilled = FALSE
    )");
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Borrow the book
        $stmt = $conn->prepare("INSERT INTO borrowed_books (book_id, user_id, due_date) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $book_id, $user_id, $due_date);
        
        if ($stmt->execute()) {
            // Log the activity
            $book_title = $conn->query("SELECT title FROM books WHERE id = $book_id")->fetch_assoc()['title'];
            $action = "borrowed book: " . $book_title;
            $log_stmt = $conn->prepare("INSERT INTO system_logs (username, action) VALUES (?, ?)");
            $log_stmt->bind_param("ss", $_SESSION['user']['username'], $action);
            $log_stmt->execute();
            
            header("Location: ../student/student_dashboard.php?success=Book borrowed successfully");
            exit();
        } else {
            header("Location: ../student/student_dashboard.php?error=Failed to borrow book");
            exit();
        }
    } else {
        header("Location: ../student/student_dashboard.php?error=Book is not available for borrowing");
        exit();
    }
}

// Handle book returning
if (isset($_POST['return_book'])) {
    $borrow_id = intval($_POST['borrow_id']);
    $user_id = $_SESSION['user']['id'];
    
    $stmt = $conn->prepare("UPDATE borrowed_books SET returned = TRUE, returned_date = NOW() WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $borrow_id, $user_id);
    
    if ($stmt->execute()) {
        // Log the activity
        $book_id = $conn->query("SELECT book_id FROM borrowed_books WHERE id = $borrow_id")->fetch_assoc()['book_id'];
        $book_title = $conn->query("SELECT title FROM books WHERE id = $book_id")->fetch_assoc()['title'];
        $action = "returned book: " . $book_title;
        $log_stmt = $conn->prepare("INSERT INTO system_logs (username, action) VALUES (?, ?)");
        $log_stmt->bind_param("ss", $_SESSION['user']['username'], $action);
        $log_stmt->execute();
        
        header("Location: ../student/student_dashboard.php?success=Book returned successfully");
        exit();
    } else {
        header("Location: ../student/student_dashboard.php?error=Failed to return book");
        exit();
    }
}

// Handle book reservation
if (isset($_POST['reserve_book'])) {
    $book_id = intval($_POST['book_id']);
    $user_id = $_SESSION['user']['id'];
    
    // Check if book is available for reservation
    $stmt = $conn->prepare("SELECT * FROM books WHERE id = ? AND id NOT IN (
        SELECT book_id FROM reserved_books WHERE fulfilled = FALSE
    )");
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Reserve the book
        $stmt = $conn->prepare("INSERT INTO reserved_books (book_id, user_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $book_id, $user_id);
        
        if ($stmt->execute()) {
            // Log the activity
            $book_title = $conn->query("SELECT title FROM books WHERE id = $book_id")->fetch_assoc()['title'];
            $action = "reserved book: " . $book_title;
            $log_stmt = $conn->prepare("INSERT INTO system_logs (username, action) VALUES (?, ?)");
            $log_stmt->bind_param("ss", $_SESSION['user']['username'], $action);
            $log_stmt->execute();
            
            header("Location: ../student/student_dashboard.php?success=Book reserved successfully");
            exit();
        } else {
            header("Location: ../student/student_dashboard.php?error=Failed to reserve book");
            exit();
        }
    } else {
        header("Location: ../student/student_dashboard.php?error=Book is not available for reservation");
        exit();
    }
}

// Handle reservation cancellation
if (isset($_POST['cancel_reservation'])) {
    $reservation_id = intval($_POST['reservation_id']);
    $user_id = $_SESSION['user']['id'];
    
    $stmt = $conn->prepare("DELETE FROM reserved_books WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $reservation_id, $user_id);
    
    if ($stmt->execute()) {
        // Log the activity
        $book_id = $conn->query("SELECT book_id FROM reserved_books WHERE id = $reservation_id")->fetch_assoc()['book_id'];
        $book_title = $conn->query("SELECT title FROM books WHERE id = $book_id")->fetch_assoc()['title'];
        $action = "canceled reservation for: " . $book_title;
        $log_stmt = $conn->prepare("INSERT INTO system_logs (username, action) VALUES (?, ?)");
        $log_stmt->bind_param("ss", $_SESSION['user']['username'], $action);
        $log_stmt->execute();
        
        header("Location: ../student/student_dashboard.php?success=Reservation cancelled successfully");
        exit();
    } else {
        header("Location: ../student/student_dashboard.php?error=Failed to cancel reservation");
        exit();
    }
}
    // Case: 'return_book' inside db_operations.php or as student/return_book.php
// Requires: $_POST['borrow_id']

if (isset($_POST['action']) && $_POST['action'] === 'return_book') { // If in db_operations.php
// Or if in student/return_book.php, check: if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['borrow_id']))

    if (!isset($_POST['borrow_id']) || !filter_var($_POST['borrow_id'], FILTER_VALIDATE_INT)) {
        $_SESSION['error_message'] = "Invalid borrow record ID.";
        header("Location: student_dashboard.php"); // Or redirect_to
        exit();
    }
    $borrow_id = $_POST['borrow_id'];
    $student_id = $_SESSION['user']['id']; // Ensure student owns this borrow record

    $conn->begin_transaction();
    try {
        // Verify the borrow record belongs to the student and is not already returned
        $stmt_check = $conn->prepare("SELECT bb.id, bb.book_id, b.title FROM borrowed_books bb JOIN books b ON bb.book_id = b.id WHERE bb.id = ? AND bb.user_id = ? AND bb.returned = FALSE");
        if(!$stmt_check) throw new Exception("DB Error (Check Return): " . $conn->error);
        $stmt_check->bind_param("ii", $borrow_id, $student_id);
        $stmt_check->execute();
        $borrow_record = $stmt_check->get_result()->fetch_assoc();
        $stmt_check->close();

        if (!$borrow_record) {
            throw new Exception("Borrow record not found, already returned, or does not belong to you.");
        }

        // Update borrowed_books table
        $return_date = date('Y-m-d H:i:s');
        $stmt_update = $conn->prepare("UPDATE borrowed_books SET returned = TRUE, return_date = ? WHERE id = ?");
        if(!$stmt_update) throw new Exception("DB Error (Update Return): " . $conn->error);
        $stmt_update->bind_param("si", $return_date, $borrow_id);
        if (!$stmt_update->execute()) {
            throw new Exception("Failed to update book return status.");
        }
        $stmt_update->close();
        
        // (Optional) Add Notification for return
        $book_title = $borrow_record['title'];
        $notification_message = "Book '{$book_title}' returned by " . ($_SESSION['user']['username'] ?? 'student') . ".";
        // ... (similar notification insertion as in borrow_book.php) ...


        $conn->commit();
        $_SESSION['success_message'] = "Book '{$book_title}' returned successfully!";

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = "Return failed: " . $e->getMessage();
    }
    // header("Location: " . $redirect_to_from_form_or_default); // e.g., borrow_history.php or student_dashboard.php
    header("Location: borrow_history.php"); // Redirect to history page
    exit();
}
    // Case: 'reserve_book' inside db_operations.php
// Requires: $_POST['book_id']
if (isset($_POST['action']) && $_POST['action'] === 'reserve_book') {
    if (!isset($_POST['book_id']) || !filter_var($_POST['book_id'], FILTER_VALIDATE_INT)) {
        $_SESSION['error_message'] = "Invalid book ID for reservation.";
        header("Location: reserve_book.php"); exit();
    }
    $book_id = $_POST['book_id'];
    $student_id = $_SESSION['user']['id'];

    $conn->begin_transaction();
    try {
        $stmt_book = $conn->prepare("SELECT title FROM books WHERE id = ? FOR UPDATE");
        // ... (fetch book title) ...
        $book_title = $stmt_book->get_result()->fetch_assoc()['title'];
        $stmt_book->close();

        // Check if student already borrowed this book (active)
        $stmt_check_borrow = $conn->prepare("SELECT id FROM borrowed_books WHERE user_id = ? AND book_id = ? AND returned = FALSE");
        // ... (execute and check rows) ...
        if ($stmt_check_borrow->get_result()->num_rows > 0) throw new Exception("You have already borrowed '{$book_title}'.");
        $stmt_check_borrow->close();

        // Check if student already reserved this book (active)
        $stmt_check_reserve = $conn->prepare("SELECT id FROM reserved_books WHERE user_id = ? AND book_id = ? AND fulfilled = FALSE");
        // ... (execute and check rows) ...
        if ($stmt_check_reserve->get_result()->num_rows > 0) throw new Exception("You have already reserved '{$book_title}'.");
        $stmt_check_reserve->close();

        // Check if book is currently borrowed by anyone (making it reservable)
        $stmt_is_borrowed = $conn->prepare("SELECT COUNT(*) as count FROM borrowed_books WHERE book_id = ? AND returned = FALSE");
        // ... (execute) ...
        $is_book_borrowed_count = $stmt_is_borrowed->get_result()->fetch_assoc()['count'];
        $stmt_is_borrowed->close();

        if ($is_book_borrowed_count == 0) { // Book is not currently borrowed
             // Check if it's also not reserved by anyone else
            $stmt_is_reserved_overall = $conn->prepare("SELECT COUNT(*) as count FROM reserved_books WHERE book_id = ? AND fulfilled = FALSE");
            $stmt_is_reserved_overall->bind_param("i", $book_id);
            $stmt_is_reserved_overall->execute();
            $is_book_reserved_count_overall = $stmt_is_reserved_overall->get_result()->fetch_assoc()['count'];
            $stmt_is_reserved_overall->close();
            if ($is_book_reserved_count_overall == 0) {
                 throw new Exception("'{$book_title}' is currently available to borrow, not reserve.");
            }
        }
        // Else (book is borrowed by someone), it's okay to reserve.

        // Reservation limit for a specific book (e.g., max 5 reservations per book)
        // Reservation limit per student (e.g., max 2 active reservations) - similar to borrow limit check

        $reservation_date = date('Y-m-d H:i:s');
        $stmt_insert_res = $conn->prepare("INSERT INTO reserved_books (user_id, book_id, reservation_date, fulfilled) VALUES (?, ?, ?, FALSE)");
        // ... (bind and execute) ...
        $stmt_insert_res->execute();
        $stmt_insert_res->close();

        $conn->commit();
        $_SESSION['success_message'] = "'{$book_title}' reserved successfully!";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = "Reservation failed: " . $e->getMessage();
    }
    header("Location: reserve_book.php"); exit();
}
    

// Handle reading progress tracking
if (isset($_POST['action']) && $_POST['action'] === 'track_reading') {
    $book_id = intval($_POST['book_id']);
    $student_id = $_SESSION['user']['id'];
    
    // Get current progress
    $stmt = $conn->prepare("SELECT pages_read FROM reading_progress WHERE student_id = ? AND book_id = ?");
    $stmt->bind_param("ii", $student_id, $book_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update existing progress (simulate reading 1 more page)
        $stmt = $conn->prepare("UPDATE reading_progress SET pages_read = pages_read + 1 WHERE student_id = ? AND book_id = ?");
    } else {
        // Create new progress record
        $stmt = $conn->prepare("INSERT INTO reading_progress (student_id, book_id, pages_read) VALUES (?, ?, 1)");
    }
    $stmt->bind_param("ii", $student_id, $book_id);
    $stmt->execute();

    if (isset($_POST['return_book'])) {
    require_once 'includes/db_connection.php';

    $borrow_id = intval($_POST['borrow_id']);

    // Mark the book as returned
    $stmt = $conn->prepare("UPDATE borrowed_books SET returned = TRUE, return_date = NOW() WHERE id = ?");
    $stmt->bind_param("i", $borrow_id);

    if ($stmt->execute()) {
        header("Location: student/student_dashboard.php?success=Book returned successfully.");
    } else {
        header("Location: student/student_dashboard.php?error=Failed to return book.");
    }

    exit();
}

    
    // Return updated progress
    $total_pages = $conn->query("SELECT total_pages FROM books WHERE id = $book_id")->fetch_assoc()['total_pages'];
    $pages_read = $conn->query("SELECT pages_read FROM reading_progress WHERE student_id = $student_id AND book_id = $book_id")->fetch_assoc()['pages_read'];
    $percentage = $total_pages > 0 ? min(round(($pages_read / $total_pages) * 100), 100) : 0;
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'progress' => $percentage,
        'pages_read' => $pages_read,
        'total_pages' => $total_pages
    ]);
    exit();
}

// Default redirect if no action matched
header("Location: ../index.php");
exit();
}

// Example of how to use it in other operations:
// logAction($_SESSION['user']['id'], $_SESSION['user']['username'], $_SESSION['user']['role'], "Added new librarian");

    // Process registration if no errors
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("INSERT INTO users (username, name, email, password, role, profile_pic) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $username, $name, $email, $hashed_password, $role, $profile_pic);
        
        if ($stmt->execute()) {
            header("Location: index.php?success=Registration+successful.+Please+login.");
            exit();
        } else {
            $errors[] = "Database error: " . $conn->error;
        }
        $stmt->close();
    }

    // If errors, redirect back with errors
    if (!empty($errors)) {
        $error_string = implode("|", $errors);
        header("Location: register.php?error=" . urlencode($error_string));
        exit();
    }
}


// Handle Login
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // Validate inputs
    if (empty($username) || empty($password)) {
        header("Location: index.php?error=Username+and+password+are+required");
        exit();
    }

    // Check user credentials
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        header("Location: index.php?error=Invalid+username+or+password");
        exit();
    }

    $user = $result->fetch_assoc();

    if (!password_verify($password, $user['password'])) {
        header("Location: index.php?error=Invalid+username+or+password");
        exit();
    }

    // Set session and redirect
    $_SESSION['user'] = [
        'id' => $user['id'],
        'username' => $user['username'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'],
        'profile_pic' => $user['profile_pic']
    ];

    // Redirect based on role
    switch ($user['role']) {
        case 'admin':
            header("Location: admin/admin_dashboard.php");
            break;
        case 'librarian':
            header("Location: librarian/librarian_dashboard.php");
            break;
        default:
            header("Location: student/student_dashboard.php");
    }
    exit();
}

require_once 'includes/db_connection.php';

// Handle Add Book
if ($_POST['action'] === 'add_book') {
    // Process book addition
    // Handle file upload
    // Insert into database
    // Redirect with success message
}

// Handle Borrow Book
if ($_GET['action'] === 'borrow_book') {
    // Check book availability
    // Create borrowing record
    // Update book count
    // Redirect with success message
}

// Handle Reserve Book
if ($_GET['action'] === 'reserve_book') {
    // Create reservation record
    // Notify librarian
    // Redirect with success message
}

// Handle Add Librarian
if ($_POST['action'] === 'add_librarian') {
    // Similar to student registration but:
    // 1. Add to users table with role=librarian
    // 2. Add to librarians table with employee number
    // 3. Redirect with success message
}


// Other actions...
// Handle Logout
elseif (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: index.php");
    exit();
}

// Invalid request
else {
    header("Location: index.php");
    exit();
}
session_start();
session_unset();
session_destroy();
header("Location: index.php");
exit();

?>