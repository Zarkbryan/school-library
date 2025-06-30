<?php
session_start();
require_once '../includes/db_connection.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'student') {
    header("Location: ../index.php");
    exit();
}

$student_id = $_SESSION['user']['id'];

// Get all borrowed books with status
$borrow_records = get_borrowed_books($conn, $student_id);

// Calculate stats
$total_borrowed = count($borrow_records);
$total_returned = count(array_filter($borrow_records, function($b) { return $b['returned']; }));
$total_overdue = count(array_filter($borrow_records, function($b) { 
    return !$b['returned'] && strtotime($b['due_date']) < time(); 
}));
$total_on_loan = $total_borrowed - $total_returned - $total_overdue;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borrow History | Student Dashboard</title>
    <link rel="stylesheet" href="../Styling/dashboard_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* Styles for this page, can be moved to dashboard_style.css if preferred */
        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        .card {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            text-align: center;
        }
        .card h3 {
            margin-top: 0;
            font-size: 1rem;
            color: #666;
        }
        .card p {
            font-size: 2rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 0;
        }
        .card.success p { color: #28a745; } /* Green for returned */
        .card.danger p { color: #dc3545; } /* Red for overdue */
        .card.info p { color: #17a2b8; } /* Blue for on loan */

        .history-controls {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap; /* Allow wrapping on smaller screens */
        }
        .search-input, .filter-select {
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #ddd;
            font-size: 0.9rem;
        }
        .search-input { flex-grow: 1; min-width: 200px; }
        .filter-select { min-width: 150px;}

        .table-container { 
            overflow-x: auto; /* Ensures table is scrollable on small screens */
            background-color: #fff;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th, .data-table td {
            padding: 12px 15px;
            border: 1px solid #e0e0e0;
            text-align: left;
            vertical-align: middle;
            color: #333333;
        }
        .data-table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        .data-table tbody tr:nth-child(even) { background-color: #f9f9f9; }
        .data-table tbody tr:hover { background-color: #f1f1f1; }
        
        .badge {
            padding: 0.3em 0.6em;
            font-size: 0.75em;
            font-weight: 700;
            line-height: 1;
            color: #fff;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 0.25rem;
        }
        .badge-success { background-color: #28a745; } /* Green */
        .badge-danger { background-color: #dc3545; }  /* Red */
        .badge-info { background-color: #17a2b8; }    /* Blue */
        .badge-warning { background-color: #ffc107; color: #212529;} /* Yellow for general on loan */

        .overdue-row { /* Example: to highlight entire overdue rows */
            /* background-color: #fff3f3 !important; */ /* Light red, use with care for accessibility */
        }
        .no-data td { text-align: center; padding: 20px; color: #777; }
        .book-cover-history {
            width: 40px;
            height: 60px;
            object-fit: cover;
            border-radius: 3px;
            margin-right: 5px; /* If you want some space if text is next to it */
        }

        /* Mobile Menu Toggle - Consistent with other pages */
        .menu-toggle {
            display: none; 
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1000; 
            background: #4e73df; 
            color: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            font-size: 20px;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }

        @media (max-width: 768px) {
            .menu-toggle { display: block; }
            .sidebar {
                transform: translateX(-100%);
                position: fixed; 
                top: 0; left: 0;
                height: 100vh; 
                z-index: 999; 
                transition: transform 0.3s ease-in-out;
            }
            .sidebar.active { transform: translateX(0); }
            .main-content { padding-top: 15px; /* Reduce padding if menu toggle takes space */ }
            .dashboard-stats { grid-template-columns: 1fr; /* Stack cards on mobile */ }
            .history-controls { flex-direction: column; }
            .search-input, .filter-select { width: 100%; }
             .data-table th, .data-table td {
                font-size: 0.85rem; /* Smaller font for tables on mobile */
                padding: 8px 10px;
            }
            .data-table th:nth-child(3), /* Hide ISBN on smaller screens */
            .data-table td:nth-child(3) {
                 display: none;
            }
        }

    </style>
</head>
<body>
<button class="menu-toggle" aria-label="Toggle menu">
    <i class="fas fa-bars"></i>
</button>

<div class="dashboard-container">
    <aside class="sidebar">
        <h2>Library</h2>
        <ul>
            <li><a href="student_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="view_books.php"><i class="fas fa-book-open"></i> View Books</a></li>
            <li><a href="reserve_book.php"><i class="fas fa-calendar-plus"></i> Reserve Book</a></li>
            <li class="active"><a href="borrow_history.php"><i class="fas fa-history"></i> Borrow History</a></li>
            <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
            <li><a href="../db_operations.php?action=logout"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <header>
            <h1>Your Borrowing History</h1>
           <div class="user-info">
                <a href="profile.php">
                    <img src="<?= htmlspecialchars(isset($_SESSION['user']['profile_pic']) && !empty($_SESSION['user']['profile_pic']) ? '../' . $_SESSION['user']['profile_pic'] : '../images/default_profile.png') ?>" 
                         alt="Profile" class="header-profile-image">
                </a>
                <span><?= htmlspecialchars($_SESSION['user']['username']) ?></span>
            </div>
        </header>

        <?php if (!empty($error)): ?>
            <div class="alert error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['success'])): ?>
            <div class="alert success"><?= htmlspecialchars($_GET['success']) ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['error_msg'])): /* Changed from $_GET['error'] to avoid conflict with $error */ ?>
            <div class="alert error"><?= htmlspecialchars($_GET['error_msg']) ?></div>
        <?php endif; ?>

        <section class="dashboard-stats">
            <div class="card">
                <h3><i class="fas fa-book-reader"></i> Total Borrowed</h3>
                <p><?= $total_borrowed ?></p>
            </div>
            <div class="card success">
                <h3><i class="fas fa-check-circle"></i> Returned</h3>
                <p><?= $total_returned ?></p>
            </div>
             <div class="card info"> <h3><i class="fas fa-hourglass-half"></i> Currently On Loan</h3>
                <p><?= $total_on_loan ?></p>
            </div>
            <div class="card danger">
                <h3><i class="fas fa-exclamation-triangle"></i> Overdue</h3>
                <p><?= $total_overdue ?></p>
            </div>
        </section>

        <div class="history-controls">
            <input type="text" id="historySearch" placeholder="Search by title, author, or ISBN..." class="search-input">
            <select id="statusFilter" class="filter-select">
                <option value="all">All Statuses</option>
                <option value="returned">Returned</option>
                <option value="overdue">Overdue</option>
                <option value="on_loan">On Loan (Not Overdue)</option>
            </select>
        </div>
        
        <div class="table-container">
            <table class="data-table" id="historyTable">
                <thead>
                    <tr>
                        <th>Cover</th>
                        <th>Title</th>
                        <th>Author</th>
                        <th>Borrow Date</th>
                        <th>Due Date</th>
                        <th>Return Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($borrow_records)): ?>
                        <?php 
                        $current_time_for_status = time(); // for consistent status checking
                        foreach ($borrow_records as $record): 
                            $status = '';
                            $status_class = '';
                            $days_overdue_text = '';

                            if ($record['returned'] == 1 || !empty($record['return_date'])) {
                                $status = 'Returned';
                                $status_class = 'success';
                            } else {
                                $due_timestamp = strtotime($record['due_date']);
                                if ($due_timestamp < $current_time_for_status) {
                                    $status = 'Overdue';
                                    $status_class = 'danger';
                                    $days_diff = floor(($current_time_for_status - $due_timestamp) / (60 * 60 * 24));
                                    $days_overdue_text = " ({$days_diff} " . ($days_diff == 1 ? "day" : "days") . ")";
                                } else {
                                    $status = 'On Loan';
                                    $status_class = 'warning'; // Using warning (yellowish) for on loan
                                }
                            }
                            $is_overdue_row = ($status === 'Overdue');
                            
                            // Determine cover image path
                            $cover_image_path = '../images/default_book.png'; // Default
                            if (!empty($record['cover_image'])) {
                                // Assuming cover_image path is relative to project root like 'uploads/book_covers/image.jpg'
                                $potential_path = '../' . $record['cover_image'];
                                if (file_exists($potential_path)) {
                                    $cover_image_path = $potential_path;
                                }
                            }
                        ?>
                        <tr class="<?= $is_overdue_row ? 'overdue-row' : '' ?>" data-status="<?= strtolower(str_replace(' ', '_', $status)) ?>">
                            <td><img src="<?= htmlspecialchars($cover_image_path) ?>" alt="Cover" class="book-cover-history"></td>
                            <td><?= htmlspecialchars($record['title']) ?></td>
                            <td><?= htmlspecialchars($record['author']) ?></td>
                            <td><?= htmlspecialchars(date('M d, Y', strtotime($record['borrow_date']))) ?></td>
                            <td><?= htmlspecialchars(date('M d, Y', strtotime($record['due_date']))) ?></td>
                            <td>
                                <?= !empty($record['return_date']) ? htmlspecialchars(date('M d, Y', strtotime($record['return_date']))) : '-' ?>
                            </td>
                            <td>
                                <span class="badge badge-<?= $status_class ?>">
                                    <?= $status . $days_overdue_text ?>
                                </span>
                            </td>
                        </tr>

                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr class="no-data">
                            <td colspan="8">No borrowing history found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sidebar Toggle
    const menuToggle = document.querySelector('.menu-toggle');
    const sidebar = document.querySelector('.sidebar');
    
    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
        });

        document.addEventListener('click', function(event) {
            if (window.innerWidth <= 768) {
                if (!sidebar.contains(event.target) && !menuToggle.contains(event.target) && sidebar.classList.contains('active')) {
                    sidebar.classList.remove('active');
                }
            }
        });
    }

    // History Table Filtering
    const searchInput = document.getElementById('historySearch');
    const statusFilter = document.getElementById('statusFilter');
    const tableRows = document.querySelectorAll('#historyTable tbody tr');
    
    function filterHistory() {
        if (!searchInput || !statusFilter) return; // Ensure elements exist

        const searchTerm = searchInput.value.toLowerCase().trim();
        const statusValue = statusFilter.value;
        
        tableRows.forEach(row => {
            // Ensure it's not the 'no-data' row
            if (row.classList.contains('no-data')) {
                // If there's only one row and it's no-data, its display depends on whether other rows become visible
                // This logic might need adjustment if you want to hide "no data" when filters would show nothing
                return;
            }

            const title = row.cells[1].textContent.toLowerCase();
            const author = row.cells[2].textContent.toLowerCase();
            const isbn = row.cells[3].textContent.toLowerCase();
            // Get status from data-attribute for more reliable filtering
            const rowStatus = row.dataset.status || ""; 

            const matchesSearch = title.includes(searchTerm) || 
                                  author.includes(searchTerm) || 
                                  isbn.includes(searchTerm);
            
            let matchesStatus = false;
            if (statusValue === 'all') {
                matchesStatus = true;
            } else if (statusValue === 'returned' && rowStatus.includes('returned')) {
                matchesStatus = true;
            } else if (statusValue === 'overdue' && rowStatus.includes('overdue')) {
                matchesStatus = true;
            } else if (statusValue === 'on_loan' && rowStatus.includes('on_loan')) { 
                // This will match "on_loan" which we set for non-overdue, non-returned items
                matchesStatus = true;
            }
            
            row.style.display = (matchesSearch && matchesStatus) ? '' : 'none';
        });
    }
    
    if (searchInput) searchInput.addEventListener('input', filterHistory);
    if (statusFilter) statusFilter.addEventListener('change', filterHistory);

    // Initial filter call in case there are pre-filled values (though unlikely here)
    // filterHistory(); 
});
</script>
</body>
</html>