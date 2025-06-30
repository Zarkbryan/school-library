<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'librarian') {
    exit("Access denied.");
}

require_once '../includes/db_connection.php';

$search = isset($_GET['search']) ? $_GET['search'] : '';
$search_param = "%$search%";

$query = "SELECT * FROM books WHERE title LIKE ? OR author LIKE ? ORDER BY title";
$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $search_param, $search_param);
$stmt->execute();
$result = $stmt->get_result();
$books = $result->fetch_all(MYSQLI_ASSOC);

if (empty($books)) {
    echo '<tr><td colspan="6" class="no-data">No books found</td></tr>';
} else {
    foreach ($books as $book) {
        echo '<tr>';
        echo '<td>'.htmlspecialchars($book['id']).'</td>';
        echo '<td>'.htmlspecialchars($book['title']).'</td>';
        echo '<td>'.htmlspecialchars($book['author']).'</td>';
        echo '<td>'.htmlspecialchars($book['quantity'] ?? 'N/A').'</td>';
        echo '<td>'.htmlspecialchars($book['available'] ?? 'N/A').'</td>';
        echo '<td class="actions">';
        echo '<a href="edit_books.php?id='.$book['id'].'" class="btn btn-edit"><i class="fas fa-edit"></i></a>';
        echo '<a href="../db_operations.php?action=delete_book&id='.$book['id'].'" class="btn btn-danger" onclick="return confirm(\'Are you sure you want to delete this book?\');">';
        echo '<i class="fas fa-trash-alt"></i> Delete</a>';
        echo '</td>';
        echo '</tr>';
    }
}
?>