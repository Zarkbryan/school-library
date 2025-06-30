<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

require_once '../includes/db_connection.php';

// Handle book deletion
if (isset($_GET['delete_id'])) {
    $book_id = $_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM books WHERE id = ?");
    $stmt->bind_param("i", $book_id);
    if ($stmt->execute()) {
        header("Location: manage_books.php?success=Book deleted successfully");
        exit();
    } else {
        header("Location: manage_books.php?error=Error deleting book");
        exit();
    }
}

// Initial page load - get all books
$books = [];
$query = "SELECT * FROM books ORDER BY title";
$stmt = $conn->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
if ($result) $books = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Books</title>
    <link rel="stylesheet" href="../Styling/dashboard_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .table-container {
            overflow-x: auto;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            background: #fff;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 15px;
            border-radius: 10px;
            overflow: hidden;
        }

        thead {
            background-color: #2c3e50;
            color: #fff;
        }

        th, td {
            padding: 14px 18px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        tbody tr:hover {
            background-color: #f5f5f5;
            transition: 0.3s ease;
        }

        .btn-edit {
            background-color: #3498db;
            color: white;
            padding: 6px 10px;
            border-radius: 6px;
            margin-right: 6px;
        }

        .btn-delete {
            background-color: #e74c3c;
            color: white;
            padding: 6px 10px;
            border-radius: 6px;
        }

        .btn-edit:hover {
            background-color: #2980b9;
        }

        .btn-delete:hover {
            background-color: #c0392b;
        }

        .no-data {
            text-align: center;
            padding: 20px;
            font-style: italic;
            color: #666;
        }
        /* Search Box Styling */
        .search-box {
            position: relative;
            flex-grow: 1;
            margin-right: 15px;
        }

        .search-box form {
            display: flex;
            align-items: center;
        }

        .search-box input {
            width: 100%;
            padding: 12px 20px;
            padding-left: 45px;
            border: 2px solid #ddd;
            border-radius: 30px;
            font-size: 16px;
            transition: all 0.3s ease;
            background-color: #f8f9fa;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .search-box input:focus {
            outline: none;
            border-color: #3498db;
            background-color: white;
            box-shadow: 0 4px 8px rgba(52, 152, 219, 0.2);
        }

        .search-box button {
            position: absolute;
            left: 15px;
            background: transparent;
            border: none;
            color: #7f8c8d;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .search-box input:focus + button {
            color: #3498db;
        }

        /* Action Bar Styling */
        .action-bar {
            display: flex;
            align-items: center;
            margin-bottom: 25px;
            padding: 15px 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .btn-primary {
            background-color: #2ecc71;
            color: white;
            padding: 12px 20px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(46, 204, 113, 0.3);
        }

        .btn-primary:hover {
            background-color: #27ae60;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(46, 204, 113, 0.4);
        }

        .btn-primary i {
            margin-right: 8px;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .action-bar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-box {
                margin-right: 0;
                margin-bottom: 15px;
            }
        }
    </style>
</head>
<body>
<div class="dashboard-container">
    <aside class="sidebar">
        <h2>Library System</h2>
          <ul>
            <li class="active"><a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="manage_librarians.php"><i class="fas fa-users-cog"></i> Manage Librarians</a></li>
            <li class="active"><a href="add_book.php"><i class="fas fa-book-medical"></i> Add Book</a></li>
            <li><a href="manage_books.php"><i class="fas fa-book"></i> Manage Books</a></li>
            <li><a href="system_logs.php"><i class="fas fa-clipboard-list"></i> System Logs</a></li>
            <li><a href="profile.php"><i class="fas fa-user-cog"></i> Profile</a></li>
            <li><a href="../db_operations.php?action=logout"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <header>
            <h1>Manage Books</h1>
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

        <section class="action-bar">
            <div class="search-box">
                <form id="searchForm">
                    <input type="text" id="searchInput" name="search" placeholder="Search books..." autocomplete="off">
                    <button type="submit"><i class="fas fa-search"></i></button>
                </form>
            </div>
            <a href="add_book.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add New Book</a>
        </section>

        <section class="content-section">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Author</th>
                            <th>Quantity</th>
                            <th>Available</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="booksTableBody">
                        <?php foreach ($books as $book): ?>
                            <tr>
                                <td><?= htmlspecialchars($book['id']) ?></td>
                                <td><?= htmlspecialchars($book['title']) ?></td>
                                <td><?= htmlspecialchars($book['author']) ?></td>
                                <td><?= htmlspecialchars($book['quantity'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($book['available'] ?? 'N/A') ?></td>
                                <td class="actions">
                                    <a href="edit_books.php?id=<?= $book['id'] ?>" class="btn btn-edit"><i class="fas fa-edit"></i></a>
                                    <a href="../db_operations.php?action=delete_book&id=<?= $book['id'] ?>" 
                                       class="btn btn-danger" 
                                       onclick="return confirm('Are you sure you want to delete this book?');">
                                       <i class="fas fa-trash-alt"></i> Delete
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($books)): ?>
                            <tr>
                                <td colspan="6" class="no-data">No books found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    // Real-time search functionality
    $('#searchInput').on('input', function() {
        const searchTerm = $(this).val().trim();
        
        if (searchTerm.length === 0) {
            // If search is empty, reload the original books
            location.reload();
            return;
        }
        
        // Show loading indicator
        $('#booksTableBody').html('<tr><td colspan="6" class="no-data">Searching...</td></tr>');
        
        // Debounce the search to avoid too many requests
        clearTimeout($(this).data('timer'));
        $(this).data('timer', setTimeout(function() {
            fetchBooks(searchTerm);
        }, 300));
    });

    // Prevent form submission
    $('#searchForm').on('submit', function(e) {
        e.preventDefault();
        fetchBooks($('#searchInput').val().trim());
    });

    // Function to fetch books via AJAX
    function fetchBooks(searchTerm) {
        $.ajax({
            url: 'search_books.php',
            type: 'GET',
            data: { search: searchTerm },
            success: function(response) {
                $('#booksTableBody').html(response);
            },
            error: function(xhr, status, error) {
                console.error("AJAX Error:", status, error);
                $('#booksTableBody').html(
                    '<tr><td colspan="6" class="no-data">Error loading books. ' + 
                    'Please try again or refresh the page.</td></tr>'
                );
            }
        });
    }
});
</script>
</body>
</html>