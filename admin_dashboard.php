<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

require_once 'db_connection.php';

// Get all librarians
$librarians = [];
$stmt = $conn->prepare("SELECT u.id, u.username, u.name, u.email, l.employee_number 
                       FROM users u 
                       JOIN librarians l ON u.id = l.user_id 
                       WHERE u.role = 'librarian'");
$stmt->execute();
$result = $stmt->get_result();
if ($result) $librarians = $result->fetch_all(MYSQLI_ASSOC);

// Get system stats
$stats = [
    'students' => 0,
    'librarians' => 0,
    'books' => 0
];

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'student'");
$stmt->execute();
$result = $stmt->get_result();
if ($result) $stats['students'] = $result->fetch_assoc()['count'];

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'librarian'");
$stmt->execute();
$result = $stmt->get_result();
if ($result) $stats['librarians'] = $result->fetch_assoc()['count'];

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM books");
$stmt->execute();
$result = $stmt->get_result();
if ($result) $stats['books'] = $result->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="Styling/dashboard_style.css">
</head>
<body>
<div class="dashboard-container">
    <aside class="sidebar">
        <h2>Library Admin</h2>
        <ul>
            <li class="active"><a href="admin_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="manage_librarians.php"><i class="fas fa-users-cog"></i> Manage Librarians</a></li>
            <li><a href="system_logs.php"><i class="fas fa-clipboard-list"></i> System Logs</a></li>
            <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
            <li><a href="db_operations.php?action=logout"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <header>
            <h1>Welcome, Admin <?= htmlspecialchars($_SESSION['user']['name']) ?>!</h1>
            <div class="user-info">
                <img src="<?= htmlspecialchars($_SESSION['user']['profile_pic'] ?? 'images/default_profile.png') ?>" alt="Profile">
                <span><?= htmlspecialchars($_SESSION['user']['username']) ?></span>
            </div>
        </header>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert success"><?= htmlspecialchars($_GET['success']) ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <div class="alert error"><?= htmlspecialchars($_GET['error']) ?></div>
        <?php endif; ?>

        <section class="dashboard-stats">
            <div class="card">
                <h3>Total Students</h3>
                <p><?= $stats['students'] ?></p>
            </div>
            <div class="card">
                <h3>Total Librarians</h3>
                <p><?= $stats['librarians'] ?></p>
            </div>
            <div class="card">
                <h3>Total Books</h3>
                <p><?= $stats['books'] ?></p>
            </div>
        </section>

        <section class="manage-librarians">
            <h2>Librarians</h2>
            <a href="add_librarian.php" class="btn">Add New Librarian</a>
            <table>
                <thead>
                    <tr>
                        <th>Employee #</th>
                        <th>Name</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($librarians as $librarian): ?>
                        <tr>
                            <td><?= htmlspecialchars($librarian['employee_number']) ?></td>
                            <td><?= htmlspecialchars($librarian['name']) ?></td>
                            <td><?= htmlspecialchars($librarian['username']) ?></td>
                            <td><?= htmlspecialchars($librarian['email']) ?></td>
                            <td>
                                <a href="edit_librarian.php?id=<?= $librarian['id'] ?>" class="btn small">Edit</a>
                                <a href="db_operations.php?action=delete_librarian&id=<?= $librarian['id'] ?>" 
                                   class="btn small danger" 
                                   onclick="return confirm('Are you sure you want to delete this librarian?')">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    </main>
</div>
</body>
</html>