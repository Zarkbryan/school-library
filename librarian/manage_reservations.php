<?php
session_start();
require_once '../includes/db_connection.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'librarian') {
    header("Location: ../index.php");
    exit();
}

// Handle reservation actions with transactions
if (isset($_GET['action'])) {
    $reservation_id = $_GET['id'];
    $conn->begin_transaction();

    try {
        // First get reservation details
        $stmt = $conn->prepare("SELECT rb.*, b.title as book_title, u.name as user_name 
                              FROM reserved_books rb
                              JOIN books b ON rb.book_id = b.id
                              JOIN users u ON rb.user_id = u.id
                              WHERE rb.id = ?");
        $stmt->bind_param("i", $reservation_id);
        $stmt->execute();
        $reservation = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$reservation) {
            throw new Exception("Reservation not found");
        }

        if ($_GET['action'] == 'approve') {
            // Approve reservation
            $stmt = $conn->prepare("UPDATE reserved_books SET fulfilled = TRUE WHERE id = ?");
            $stmt->bind_param("i", $reservation_id);
            $stmt->execute();
            $stmt->close();

            // Update book status to borrowed (not available)
            $stmt = $conn->prepare("UPDATE books SET available = FALSE WHERE id = ?");
            $stmt->bind_param("i", $reservation['book_id']);
            $stmt->execute();
            $stmt->close();

            // Create a borrow record
            $due_date = date('Y-m-d', strtotime('+14 days')); // 2 weeks loan period
            $stmt = $conn->prepare("INSERT INTO borrowed_books (user_id, book_id, borrow_date, due_date) 
                                  VALUES (?, ?, NOW(), ?)");
            $stmt->bind_param("iis", $reservation['user_id'], $reservation['book_id'], $due_date);
            $stmt->execute();
            $stmt->close();

            // Notify student
            $message = "Your reservation for '{$reservation['book_title']}' has been approved. Please pick up by {$reservation['pickup_date']}";
            $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type, related_book_id) 
                                   VALUES (?, ?, 'reservation_approved', ?)");
            $stmt->bind_param("isi", $reservation['user_id'], $message, $reservation['book_id']);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            header("Location: manage_reservations.php?success=Reservation approved successfully");
            exit();

        } elseif ($_GET['action'] == 'reject') {
            // Reject reservation
            $stmt = $conn->prepare("UPDATE reserved_books SET fulfilled = FALSE, cancelled = TRUE WHERE id = ?");
            $stmt->bind_param("i", $reservation_id);
            $stmt->execute();
            $stmt->close();

            // Make book available again
            $stmt = $conn->prepare("UPDATE books SET available = TRUE WHERE id = ?");
            $stmt->bind_param("i", $reservation['book_id']);
            $stmt->execute();
            $stmt->close();

            // Notify student
            $message = "Your reservation for '{$reservation['book_title']}' has been rejected by the librarian";
            $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type, related_book_id) 
                                  VALUES (?, ?, 'reservation_rejected', ?)");
            $stmt->bind_param("isi", $reservation['user_id'], $message, $reservation['book_id']);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            header("Location: manage_reservations.php?success=Reservation rejected");
            exit();

        } elseif ($_GET['action'] == 'cancel') {
            // Cancel reservation (admin-initiated cancellation)
            $stmt = $conn->prepare("DELETE FROM reserved_books WHERE id = ?");
            $stmt->bind_param("i", $reservation_id);
            $stmt->execute();
            $stmt->close();

            // Make book available again
            $stmt = $conn->prepare("UPDATE books SET available = TRUE WHERE id = ?");
            $stmt->bind_param("i", $reservation['book_id']);
            $stmt->execute();
            $stmt->close();

            // Notify student
            $message = "Your reservation for '{$reservation['book_title']}' has been cancelled by the librarian";
            $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type, related_book_id) 
                                  VALUES (?, ?, 'reservation_cancelled', ?)");
            $stmt->bind_param("isi", $reservation['user_id'], $message, $reservation['book_id']);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            header("Location: manage_reservations.php?success=Reservation cancelled");
            exit();
        }
    } catch (Exception $e) {
        $conn->rollback();
        header("Location: manage_reservations.php?error=" . urlencode($e->getMessage()));
        exit();
    }
}

// Fetch all reservations with joins
$reservations = [];
$query = "SELECT rb.*, b.title AS book_title, u.name AS user_name, 
          CASE 
            WHEN rb.cancelled = TRUE THEN 'cancelled'
            WHEN rb.fulfilled = TRUE THEN 'approved'
            ELSE 'pending'
          END AS status
          FROM reserved_books rb
          JOIN books b ON rb.book_id = b.id
          JOIN users u ON rb.user_id = u.id
          ORDER BY rb.reservation_date DESC";
$stmt = $conn->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
if ($result) $reservations = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Manage Reservations</title>
    <link rel="stylesheet" href="../Styling/dashboard_style.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" />
    <style>
        .status-badge {
            padding: 5px 10px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .status-badge.pending {
            background-color: #fff3cd;
            color: #856404;
        }
        .status-badge.approved {
            background-color: #d4edda;
            color: #155724;
        }
        .status-badge.cancelled, .status-badge.rejected {
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
        .btn-success {
            background-color: #28a745;
        }
        .btn-warning {
            background-color: #ffc107;
        }
        .btn-danger {
            background-color: #dc3545;
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
            <h1>Manage Reservations</h1>
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
                            <th>Reservation Date</th>
                            <th>Pickup Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reservations)): ?>
                            <tr>
                                <td colspan="7" class="no-data">No reservations found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($reservations as $reservation): ?>
                                <tr>
                                    <td><?= htmlspecialchars($reservation['id']) ?></td>
                                    <td><?= htmlspecialchars($reservation['book_title']) ?></td>
                                    <td><?= htmlspecialchars($reservation['user_name']) ?></td>
                                    <td><?= date('M d, Y', strtotime($reservation['reservation_date'])) ?></td>
                                    <td><?= $reservation['pickup_date'] ? date('M d, Y', strtotime($reservation['pickup_date'])) : 'N/A' ?></td>
                                    <td>
                                        <span class="status-badge <?= htmlspecialchars($reservation['status']) ?>">
                                            <?= htmlspecialchars(ucfirst($reservation['status'])) ?>
                                        </span>
                                    </td>
                                    <td class="actions">
                                        <?php if ($reservation['status'] === 'pending'): ?>
                                            <a href="manage_reservations.php?action=approve&id=<?= $reservation['id'] ?>" class="btn btn-success" title="Approve">
                                                <i class="fas fa-check"></i>
                                            </a>
                                            <a href="manage_reservations.php?action=reject&id=<?= $reservation['id'] ?>" class="btn btn-warning" title="Reject">
                                                <i class="fas fa-times"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="manage_reservations.php?action=cancel&id=<?= $reservation['id'] ?>" class="btn btn-danger" title="Cancel" onclick="return confirm('Are you sure you want to cancel this reservation?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
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