<?php
session_start();
require_once '../includes/db_connection.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'librarian') {
    header("Location: ../index.php");
    exit();
}

// Handle borrowing actions with transactions
if (isset($_GET['action'])) {
    $borrowing_id = $_GET['id'];
    $conn->begin_transaction();

    try {
        // First get borrowing details
        $stmt = $conn->prepare("SELECT bb.*, b.title as book_title, u.name as user_name 
                              FROM borrowed_books bb
                              JOIN books b ON bb.book_id = b.id
                              JOIN users u ON bb.user_id = u.id
                              WHERE bb.id = ?");
        $stmt->bind_param("i", $borrowing_id);
        $stmt->execute();
        $borrowing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$borrowing) {
            throw new Exception("Borrowing record not found");
        }

        if ($_GET['action'] == 'confirm') {
            // Confirm borrowing
            $stmt = $conn->prepare("UPDATE borrowed_books SET returned = FALSE WHERE id = ?");
            $stmt->bind_param("i", $borrowing_id);
            $stmt->execute();
            $stmt->close();

            // Make book unavailable
            $stmt = $conn->prepare("UPDATE books SET available = FALSE WHERE id = ?");
            $stmt->bind_param("i", $borrowing['book_id']);
            $stmt->execute();
            $stmt->close();

            // Notify student
            $message = "Your borrowing of '{$borrowing['book_title']}' has been confirmed. Due date: " . date('M d, Y', strtotime($borrowing['due_date']));
            $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type, related_book_id) 
                                   VALUES (?, ?, 'borrowing_confirmed', ?)");
            $stmt->bind_param("isi", $borrowing['user_id'], $message, $borrowing['book_id']);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            header("Location: manage_borrowings.php?success=Borrowing confirmed successfully");
            exit();

        } elseif ($_GET['action'] == 'return') {
            // Mark book as returned
            $stmt = $conn->prepare("UPDATE borrowed_books SET returned = TRUE, return_date = NOW() WHERE id = ?");
            $stmt->bind_param("i", $borrowing_id);
            $stmt->execute();
            $stmt->close();

            // Make book available again
            $stmt = $conn->prepare("UPDATE books SET available = TRUE WHERE id = ?");
            $stmt->bind_param("i", $borrowing['book_id']);
            $stmt->execute();
            $stmt->close();

            // Check if overdue and calculate fine if needed
            $fine = 0;
            $today = new DateTime();
            $due_date = new DateTime($borrowing['due_date']);
            
            if ($today > $due_date) {
                $days_late = $today->diff($due_date)->days;
                $fine = $days_late * 0.50; // $0.50 per day late
                
                if ($fine > 0) {
                    $stmt = $conn->prepare("INSERT INTO fines (user_id, borrowing_id, amount, reason, status) 
                                          VALUES (?, ?, ?, 'Late return', 'unpaid')");
                    $reason = "Late return by " . $days_late . " days";
                    $stmt->bind_param("iid", $borrowing['user_id'], $borrowing_id, $fine);
                    $stmt->execute();
                    $stmt->close();
                    
                    // Notify student about fine
                    $message = "You have a fine of $" . number_format($fine, 2) . " for late return of '{$borrowing['book_title']}'";
                    $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type, related_book_id) 
                                          VALUES (?, ?, 'fine_issued', ?)");
                    $stmt->bind_param("isi", $borrowing['user_id'], $message, $borrowing['book_id']);
                    $stmt->execute();
                    $stmt->close();
                }
            }

            // Notify student about return
            $message = "Your return of '{$borrowing['book_title']}' has been recorded" . ($fine > 0 ? " (Late fine: $" . number_format($fine, 2) . ")" : "");
            $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type, related_book_id) 
                                  VALUES (?, ?, 'book_returned', ?)");
            $stmt->bind_param("isi", $borrowing['user_id'], $message, $borrowing['book_id']);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            header("Location: manage_borrowings.php?success=Book return recorded" . ($fine > 0 ? " (Fine issued)" : ""));
            exit();

        } elseif ($_GET['action'] == 'renew') {
            // Check if already renewed
            $current_due_date = new DateTime($borrowing['due_date']);
            $today = new DateTime();
            
            if ($today < $current_due_date) {
                throw new Exception("Cannot renew before due date");
            }
            
            // Calculate new due date (extend by 2 weeks from current date)
            $new_due_date = date('Y-m-d', strtotime('+14 days'));
            
            // Renew borrowing
            $stmt = $conn->prepare("UPDATE borrowed_books 
                                   SET due_date = ?, returned = FALSE
                                   WHERE id = ?");
            $stmt->bind_param("si", $new_due_date, $borrowing_id);
            $stmt->execute();
            $stmt->close();

            // Notify student
            $message = "Your borrowing of '{$borrowing['book_title']}' has been renewed. New due date: " . date('M d, Y', strtotime($new_due_date));
            $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type, related_book_id) 
                                  VALUES (?, ?, 'borrowing_renewed', ?)");
            $stmt->bind_param("isi", $borrowing['user_id'], $message, $borrowing['book_id']);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            header("Location: manage_borrowings.php?success=Borrowing renewed successfully");
            exit();
        }
    } catch (Exception $e) {
        $conn->rollback();
        header("Location: manage_borrowings.php?error=" . urlencode($e->getMessage()));
        exit();
    }
}

// Fetch all borrowings with joins, sorted by borrow_date descending (newest first)
$borrowings = [];
$query = "SELECT bb.*, b.title AS book_title, u.name AS user_name, 
          CASE 
            WHEN bb.returned = TRUE THEN 'returned'
            WHEN bb.due_date < CURDATE() THEN 'overdue'
            ELSE 'borrowed'
          END AS display_status
          FROM borrowed_books bb
          JOIN books b ON bb.book_id = b.id
          JOIN users u ON bb.user_id = u.id
          ORDER BY bb.borrow_date DESC, bb.id DESC";  // Sorted by most recent first
$stmt = $conn->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
if ($result) $borrowings = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Manage Borrowings</title>
    <link rel="stylesheet" href="../Styling/dashboard_style.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" />
    <style>
        .status-badge {
            padding: 5px 10px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .status-badge.borrowed {
            background-color: #d4edda;
            color: #155724;
        }
        .status-badge.returned {
            background-color: #cce5ff;
            color: #004085;
        }
        .status-badge.overdue {
            background-color: #f8d7da;
            color: #721c24;
        }
        .actions {
            display: flex;
            gap: 5px;
        }
        .actions a {
            padding: 5px 8px;
            border-radius: 4px;
            color: white;
            text-decoration: none;
        }
        .btn-primary {
            background-color: #007bff;
        }
        .btn-success {
            background-color: #28a745;
        }
        .btn-warning {
            background-color: #ffc107;
        }
        .btn-danger {
            background-color: #dc3545;
        }
        .btn-info {
            background-color: #17a2b8;
        }
        .overdue-text {
            color: #dc3545;
            font-weight: bold;
        }
    </style>
</head>
<body>
<div class="dashboard-container">
    <aside class="sidebar">
        <h2>Library System</h2>
        <ul>
            <li><a href="librarian_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="add_book.php"><i class="fas fa-book-medical"></i> Add Book</a></li>
            <li><a href="manage_books.php"><i class="fas fa-book"></i> Manage Books</a></li>
            <li><a href="manage_reservations.php"><i class="fas fa-calendar-check"></i> Reservations</a></li>
            <li class="active"><a href="manage_borrowings.php"><i class="fas fa-book-reader"></i> Borrowings</a></li>
            <li><a href="profile.php"><i class="fas fa-user-cog"></i> Profile</a></li>
            <li><a href="../db_operations.php?action=logout"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <header>
            <h1>Manage Borrowings</h1>
            <div class="user-info">
                <a href="profile.php">
                    <img src="<?= htmlspecialchars('../' . ($_SESSION['user']['profile_pic'] ?? 'images/default_profile.png')) ?>" alt="Profile" class="header-profile-image" />
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

        <section class="content-section">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Book</th>
                            <th>User</th>
                            <th>Borrow Date</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($borrowings)): ?>
                            <tr>
                                <td colspan="7" class="no-data">No borrowings found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($borrowings as $borrowing): ?>
                                <tr>
                                    <td><?= htmlspecialchars($borrowing['id']) ?></td>
                                    <td><?= htmlspecialchars($borrowing['book_title']) ?></td>
                                    <td><?= htmlspecialchars($borrowing['user_name']) ?></td>
                                    <td><?= date('M d, Y', strtotime($borrowing['borrow_date'])) ?></td>
                                    <td class="<?= ($borrowing['display_status'] === 'overdue') ? 'overdue-text' : '' ?>">
                                        <?= date('M d, Y', strtotime($borrowing['due_date'])) ?>
                                        <?php if ($borrowing['display_status'] === 'overdue'): ?>
                                            <br><small>(Overdue by <?= (new DateTime())->diff(new DateTime($borrowing['due_date']))->days ?> days)</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge <?= htmlspecialchars($borrowing['display_status']) ?>">
                                            <?= htmlspecialchars(ucfirst($borrowing['display_status'])) ?>
                                        </span>
                                    </td>
                                    <td class="actions">
                                        <?php if ($borrowing['display_status'] === 'overdue' || $borrowing['display_status'] === 'borrowed'): ?>
                                            <a href="manage_borrowings.php?action=return&id=<?= $borrowing['id'] ?>" class="btn btn-primary" title="Return">
                                                <i class="fas fa-book"></i>
                                            </a>
                                            <a href="manage_borrowings.php?action=renew&id=<?= $borrowing['id'] ?>" class="btn btn-info" title="Renew">
                                                <i class="fas fa-sync-alt"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
</body>
</html>