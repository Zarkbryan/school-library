<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

require_once '../includes/db_connection.php';

// Get system stats
$stats = [
    'students' => 0,
    'librarians' => 0,
    'books' => 0,
    'pending_reservations' => 0
];

$queries = [
    'students' => "SELECT COUNT(*) as count FROM users WHERE role = 'student'",
    'librarians' => "SELECT COUNT(*) as count FROM users WHERE role = 'librarian'",
    'books' => "SELECT COUNT(*) as count FROM books",
    'pending_reservations' => "SELECT COUNT(*) as count FROM reserved_books WHERE fulfilled = FALSE"
];

foreach ($queries as $key => $query) {
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) $stats[$key] = $result->fetch_assoc()['count'];
}

// Get recent activities (last 10 activities)
$recent_activities = [];
$activity_query = "SELECT * FROM system_logs ORDER BY timestamp DESC LIMIT 10";
$stmt = $conn->prepare($activity_query);
$stmt->execute();
$result = $stmt->get_result();
if ($result) $recent_activities = $result->fetch_all(MYSQLI_ASSOC);

// Function to format activity messages
function formatActivityMessage($activity) {
    $action = htmlspecialchars($activity['action']);
    $username = htmlspecialchars($activity['username']);
    $time_ago = timeAgo($activity['timestamp']);
    return "$username $action ($time_ago)";
}

// Function to convert timestamp to time ago
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $time_difference = time() - $time;
    if ($time_difference < 1) return 'just now';

    $condition = [
        12 * 30 * 24 * 60 * 60 => 'year',
        30 * 24 * 60 * 60 => 'month',
        24 * 60 * 60 => 'day',
        60 * 60 => 'hour',
        60 => 'minute',
        1 => 'second'
    ];

    foreach ($condition as $secs => $str) {
        $d = $time_difference / $secs;
        if ($d >= 1) {
            $t = round($d);
            return $t . ' ' . $str . ($t > 1 ? 's' : '') . ' ago';
        }
    }
}

// Function to determine icon based on action type
function getActivityIcon($action) {
    if (stripos($action, 'book') !== false) return 'book';
    elseif (stripos($action, 'librarian') !== false) return 'user-tie';
    elseif (stripos($action, 'student') !== false) return 'user-graduate';
    elseif (stripos($action, 'login') !== false) return 'sign-in-alt';
    elseif (stripos($action, 'logout') !== false) return 'sign-out-alt';
    elseif (stripos($action, 'reservation') !== false) return 'calendar-check';
    else return 'info-circle';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Library System</title>
    <link rel="stylesheet" href="../Styling/dashboard_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-card h3 {
            margin-top: 0;
            color: #6c757d;
            font-size: 1rem;
        }
        .stat-card p {
            font-size: 2rem;
            margin: 10px 0 0;
            color: #4e73df;
            font-weight: bold;
        }
        .activity-list {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .activity-item {
            display: flex;
            align-items: flex-start;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .activity-item:last-child {
            border-bottom: none;
        }
        .activity-item i {
            font-size: 1.2rem;
            color: #4e73df;
            margin-right: 15px;
            margin-top: 3px;
        }
        .activity-item div {
            flex: 1;
        }
        .activity-item p {
            margin: 0;
            color: #333;
        }
        .activity-item small {
            color: #6c757d;
            font-size: 0.85rem;
        }
        .no-activity {
            color: #6c757d;
            text-align: center;
            padding: 20px;
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
            <li><a href="add_book.php"><i class="fas fa-book-medical"></i> Add Book</a></li>
            <li><a href="manage_books.php"><i class="fas fa-book"></i> Manage Books</a></li>
            <li><a href="system_logs.php"><i class="fas fa-clipboard-list"></i> System Logs</a></li>
            <li><a href="profile.php"><i class="fas fa-user-cog"></i> Profile</a></li>
            <li><a href="../db_operations.php?action=logout"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <header>
            <h1>Welcome, <?= htmlspecialchars($_SESSION['user']['name']) ?>!</h1>
            <div class="user-info">
                <a href="profile.php">
                    <img src="<?= htmlspecialchars('../' . ($_SESSION['user']['profile_pic'] ?? 'images/default_profile.png')) ?>" alt="Profile" class="header-profile-image">
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

        <section class="stats-container">
            <div class="stat-card">
                <h3><i class="fas fa-users"></i> Total Students</h3>
                <p><?= $stats['students'] ?></p>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-user-tie"></i> Total Librarians</h3>
                <p><?= $stats['librarians'] ?></p>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-book"></i> Total Books</h3>
                <p><?= $stats['books'] ?></p>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-clock"></i> Pending Reservations</h3>
                <p><?= $stats['pending_reservations'] ?></p>
            </div>
        </section>

        <section class="recent-activity">
            <h2><i class="fas fa-history"></i> Recent Activity</h2>
            <div class="activity-list">
                <?php if (!empty($recent_activities)): ?>
                    <?php foreach ($recent_activities as $activity): ?>
                        <?php $icon = getActivityIcon($activity['action']); ?>
                        <div class="activity-item">
                            <i class="fas fa-<?= $icon ?>"></i>
                            <div>
                                <p><?= formatActivityMessage($activity) ?></p>
                                <small><?= date('M d, Y h:i A', strtotime($activity['timestamp'])) ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-activity">No recent activity found</div>
                <?php endif; ?>
            </div>
        </section>
    </main>
</div>
<script>
setInterval(function() {
    fetch('get_recent_activities.php')
        .then(response => response.text())
        .then(data => {
            document.querySelector('.activity-list').innerHTML = data;
        });
}, 30000);

setInterval(function() {
    fetch('get_recent_activities.php')
        .then(response => response.text())
        .then(data => {
            document.querySelector('.activity-list').innerHTML = data;
        });
}, 30000); // Every 30 seconds

</script>
</body>
</html>