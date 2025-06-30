<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}
date_default_timezone_set('Africa/Kampala'); // or your correct timezone
require_once '../includes/db_connection.php';

// Pagination variables
$limit = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Get total number of logs
$totalLogs = $conn->query("SELECT COUNT(*) FROM system_logs")->fetch_row()[0];
$totalPages = ceil($totalLogs / $limit);

// Search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where = '';
$params = [];
$types = '';

if (!empty($search)) {
    $where = "WHERE username LIKE ? OR action LIKE ?";
    $searchTerm = "%$search%";
    $params = [$searchTerm, $searchTerm];
    $types = 'ss';
}

// Get total number of logs based on search
$totalQuery = "SELECT COUNT(*) FROM system_logs $where";
$stmt = $conn->prepare($totalQuery);
if (!empty($search)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$totalLogs = $stmt->get_result()->fetch_row()[0];
$stmt->close();
$totalPages = ceil($totalLogs / $limit);

// Get logs for current page
$query = "SELECT * FROM system_logs $where ORDER BY timestamp DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($query);

if (!empty($search)) {
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';
    $stmt->bind_param($types, ...$params);
} else {
    $stmt->bind_param("ii", $limit, $offset);
}

$stmt->execute();
$result = $stmt->get_result();
$logs = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Log the activity of viewing system logs
$admin_username = $_SESSION['user']['username'];
$action_msg = "viewed system logs";
$log_stmt = $conn->prepare("INSERT INTO system_logs (username, action) VALUES (?, ?)");
$log_stmt->bind_param("ss", $admin_username, $action_msg);
$log_stmt->execute();
$log_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Logs | Admin Dashboard</title>
    <link rel="stylesheet" href="../Styling/dashboard_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .logs-container {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .logs-table {
            width: 100%;
            border-collapse: collapse;
        }

        .logs-table th, .logs-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e3e6f0;
        }

        .logs-table th {
            background-color: #f8f9fc;
            color: #4e73df;
            font-weight: 600;
        }

        .logs-table tr:hover {
            background-color: #f8f9fc;
        }

        .log-action {
            max-width: 400px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }

        .pagination a {
            color: #4e73df;
            padding: 8px 16px;
            text-decoration: none;
            border: 1px solid #d1d3e2;
            margin: 0 4px;
            border-radius: 4px;
        }

        .pagination a.active {
            background-color: #4e73df;
            color: white;
            border: 1px solid #4e73df;
        }

        .pagination a:hover:not(.active) {
            background-color: #f8f9fc;
        }

        .search-container {
            margin-bottom: 20px;
        }

        .search-input {
            padding: 10px 15px;
            border: 1px solid #d1d3e2;
            border-radius: 4px;
            width: 300px;
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
            <h1>System Logs</h1>
            <div class="user-info">
                <a href="profile.php">
                    <img src="<?= htmlspecialchars('../' . ($_SESSION['user']['profile_pic'] ?? 'images/default_profile.png')) ?>" 
                         alt="Profile" class="header-profile-image">
                </a>
                <span><?= htmlspecialchars($_SESSION['user']['username']) ?></span>
            </div>
        </header>

        <div class="logs-container">
            <div class="search-container">
                <form method="GET" action="system_logs.php">
                    <input type="text" name="search" class="search-input" placeholder="Search logs..." 
                           value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Search
                    </button>
                </form>
            </div>

            <table class="logs-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($logs) > 0): ?>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?= htmlspecialchars($log['id']) ?></td>
                            <td><?= htmlspecialchars($log['username']) ?></td>
                            <td class="log-action" title="<?= htmlspecialchars($log['action']) ?>">
                                <?= htmlspecialchars($log['action']) ?>
                            </td>
                            <td><?= date('M d, Y H:i:s', strtotime($log['timestamp'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align: center;">No logs found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="system_logs.php?page=<?= $page - 1 ?><?= isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : '' ?>">
                        &laquo; Previous
                    </a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="system_logs.php?page=<?= $i ?><?= isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : '' ?>"
                       <?= $i === $page ? 'class="active"' : '' ?>>
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="system_logs.php?page=<?= $page + 1 ?><?= isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : '' ?>">
                        Next &raquo;
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php include '../includes/footer.php'; ?>
</body>
</html>
