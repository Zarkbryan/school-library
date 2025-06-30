<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

require_once '../includes/db_connection.php';

// Get all librarians - now including those in users table with role 'librarian'
$librarians = [];
$stmt = $conn->prepare("
    SELECT u.id, u.username, u.name, u.email, IFNULL(l.employee_number, 'Not assigned') as employee_number 
    FROM users u 
    LEFT JOIN librarians l ON u.id = l.user_id 
    WHERE u.role = 'librarian'
    ORDER BY u.name ASC
");
$stmt->execute();
$result = $stmt->get_result();
if ($result) $librarians = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Librarians | Admin Dashboard</title>
    <link rel="stylesheet" href="../Styling/dashboard_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .status-active {
            background-color: #d4edda;
            color: #155724;
        }
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
    </style>
</head>
<body>
<div class="dashboard-container">
    <aside class="sidebar">
        <h2>Library Admin</h2>
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
            <h1>Manage Librarians</h1>
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
            <a href="add_librarian.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add New Librarian
            </a>
        </section>

        <section class="data-section">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Employee #</th>
                        <th>Name</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($librarians) > 0): ?>
                        <?php foreach ($librarians as $librarian): 
                            $is_fully_registered = $librarian['employee_number'] !== 'Not assigned';
                        ?>
                        <tr data-id="<?= $librarian['id'] ?>">
                            <td><?= htmlspecialchars($librarian['employee_number']) ?></td>
                            <td><?= htmlspecialchars($librarian['name']) ?></td>
                            <td><?= htmlspecialchars($librarian['username']) ?></td>
                            <td><?= htmlspecialchars($librarian['email']) ?></td>
                            <td>
                                <span class="status-badge <?= $is_fully_registered ? 'status-active' : 'status-pending' ?>">
                                    <?= $is_fully_registered ? 'Active' : 'Pending Setup' ?>
                                </span>
                            </td>
                            <td class="actions">
                                <a href="edit_librarian.php?id=<?= $librarian['id'] ?>" class="btn btn-sm btn-warning">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <a href="../db_operations.php?action=delete_librarian&id=<?= $librarian['id'] ?>" 
                                   class="btn btn-sm btn-danger" 
                                   onclick="return confirm('Are you sure you want to delete this librarian?')">
                                    <i class="fas fa-trash-alt"></i> Delete
                                </a>
                                <?php if (!$is_fully_registered): ?>
                                    <a href="complete_librarian_setup.php?id=<?= $librarian['id'] ?>" 
                                       class="btn btn-sm btn-primary">
                                        <i class="fas fa-user-cog"></i> Complete Setup
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="no-data">No librarians found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
    </main>
</div>
// Add this script to your manage_librarians.php page
<script>
// Function to update a librarian row in the table
function updateLibrarianRow(data) {
    const row = document.querySelector(`tr[data-id="${data.id}"]`) || 
                document.querySelector(`a[href*="id=${data.id}"]`).closest('tr');
    
    if (row) {
        // Update the row cells with new data
        row.cells[0].textContent = data.employee_number;
        row.cells[1].textContent = data.name;
        row.cells[2].textContent = data.username;
        row.cells[3].textContent = data.email;
        
        // Update status badge if needed
        const statusBadge = row.cells[4].querySelector('.status-badge');
        if (statusBadge) {
            statusBadge.className = 'status-badge ' + (data.is_fully_registered ? 'status-active' : 'status-pending');
            statusBadge.textContent = data.is_fully_registered ? 'Active' : 'Pending Setup';
        }
        
        // Update any action buttons if needed
        const completeSetupBtn = row.querySelector('a[href*="complete_librarian_setup"]');
        if (completeSetupBtn && data.is_fully_registered) {
            completeSetupBtn.remove();
        }
    }
}

// Open edit window with reference to this window
function openEditWindow(id) {
    window.open(`edit_librarian.php?id=${id}`, '_blank', 'width=800,height=600');
    return false;
}

// Update all edit links to open in a popup
document.addEventListener('DOMContentLoaded', function() {
    const editLinks = document.querySelectorAll('a[href*="edit_librarian.php"]');
    editLinks.forEach(link => {
        link.onclick = function() {
            const id = new URL(this.href).searchParams.get('id');
            return openEditWindow(id);
        };
    });
});
</script>
</body>
</html>