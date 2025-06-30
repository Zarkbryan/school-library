<?php
session_start();

// Redirect to login if not authenticated
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

// Database connection (if needed in auth check)
require_once 'db_connection.php';

// Role-based access control
$current_role = $_SESSION['user']['role'];
$current_page = basename($_SERVER['PHP_SELF']);

// Define allowed pages for each role
$allowed_pages = [
    'admin' => ['dashboard.php', 'manage_librarians.php', 'add_librarian.php', 'system_logs.php', 'profile.php'],
    'librarian' => ['dashboard.php', 'add_book.php', 'view_books.php', 'borrowed_books.php', 'profile.php'],
    'student' => ['dashboard.php', 'view_books.php', 'reserve_book.php', 'borrow_history.php', 'profile.php']
];

// Check if current page is allowed for user's role
if (!in_array($current_page, $allowed_pages[$current_role])) {
    header("Location: ../{$current_role}/dashboard.php");
    exit();
}

// Additional security checks can be added here
?>