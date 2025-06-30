<?php
require_once '../includes/auth_check.php';
require_once '../includes/db_connection.php';

// Get stats
$stats = [
    'students' => $conn->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetch_row()[0],
    'librarians' => $conn->query("SELECT COUNT(*) FROM users WHERE role='librarian'")->fetch_row()[0],
    'books' => $conn->query("SELECT COUNT(*) FROM books")->fetch_row()[0],
    'borrowed' => $conn->query("SELECT COUNT(*) FROM borrowed_books WHERE returned=0")->fetch_row()[0]
];

include '../includes/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    <div class="dashboard-container">
    <h1>Admin Dashboard</h1>
    
    <div class="stats-grid">
        <div class="stat-card">
            <h3>Students</h3>
            <p><?= $stats['students'] ?></p>
        </div>
        <div class="stat-card">
            <h3>Librarians</h3>
            <p><?= $stats['librarians'] ?></p>
        </div>
        <div class="stat-card">
            <h3>Books</h3>
            <p><?= $stats['books'] ?></p>
        </div>
        <div class="stat-card">
            <h3>Borrowed Books</h3>
            <p><?= $stats['borrowed'] ?></p>
        </div>
    </div>
    
    <div class="recent-activity">
        <h2>Recent Activity</h2>
        <table>
            <thead>
                <tr>
                    <th>Action</th>
                    <th>User</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $activities = $conn->query("SELECT * FROM system_logs ORDER BY created_at DESC LIMIT 5");
                while ($activity = $activities->fetch_assoc()):
                ?>
                <tr>
                    <td><?= htmlspecialchars($activity['action']) ?></td>
                    <td><?= htmlspecialchars($activity['user_id']) ?></td>
                    <td><?= date('M d, Y H:i', strtotime($activity['created_at'])) ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>

<?php include '../includes/footer.php'; ?>