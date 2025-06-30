<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    http_response_code(403);
    exit("Access denied.");
}

require_once '../includes/db_connection.php';

// Fetch latest 10 activities
$recent_activities = [];
$query = "SELECT * FROM system_logs ORDER BY timestamp DESC LIMIT 10";
$stmt = $conn->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
if ($result) $recent_activities = $result->fetch_all(MYSQLI_ASSOC);

function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;

    if ($diff < 0) {
        return 'just now';
    }

    // Calculate time difference in various units
    $seconds = $diff;
    $minutes = round($diff / 60);
    $hours = round($diff / 3600);
    $days = round($diff / 86400);
    $weeks = round($diff / 604800);
    $months = round($diff / 2629440);
    $years = round($diff / 31553280);

    if ($seconds < 60) {
        return $seconds == 1 ? '1 second ago' : "$seconds seconds ago";
    } elseif ($minutes < 60) {
        return $minutes == 1 ? '1 minute ago' : "$minutes minutes ago";
    } elseif ($hours < 24) {
        return $hours == 1 ? '1 hour ago' : "$hours hours ago";
    } elseif ($days < 7) {
        return $days == 1 ? '1 day ago' : "$days days ago";
    } elseif ($weeks < 4.3) {  // 4.3 weeks = ~1 month
        return $weeks == 1 ? '1 week ago' : "$weeks weeks ago";
    } elseif ($months < 12) {
        return $months == 1 ? '1 month ago' : "$months months ago";
    } else {
        return $years == 1 ? '1 year ago' : "$years years ago";
    }
}

function getActivityIcon($action) {
    if (stripos($action, 'book') !== false) {
        return 'book';
    } elseif (stripos($action, 'librarian') !== false) {
        return 'user-tie';
    } elseif (stripos($action, 'student') !== false) {
        return 'user-graduate';
    } elseif (stripos($action, 'login') !== false) {
        return 'sign-in-alt';
    } elseif (stripos($action, 'logout') !== false) {
        return 'sign-out-alt';
    } elseif (stripos($action, 'reservation') !== false) {
        return 'calendar-check';
    } else {
        return 'info-circle';
    }
}

function formatActivityMessage($activity) {
    $action = htmlspecialchars($activity['action']);
    $username = htmlspecialchars($activity['username']);
    $time_ago = timeAgo($activity['timestamp']);
    return "$username $action ($time_ago)";
}

if (!empty($recent_activities)) {
    foreach ($recent_activities as $activity) {
        $icon = getActivityIcon($activity['action']);
        echo '<div class="activity-item">';
        echo '<i class="fas fa-' . $icon . '"></i>';
        echo '<div>';
        echo '<p>' . formatActivityMessage($activity) . '</p>';
        echo '<small>' . date('M d, Y h:i A', strtotime($activity['timestamp'])) . '</small>';
        echo '</div>';
        echo '</div>';
    }
} else {
    echo '<div class="no-activity">No recent activity found</div>';
}
?>