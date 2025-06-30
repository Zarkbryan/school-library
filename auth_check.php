<?php
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

// Role-based access control
$allowed_roles = [
    'admin' => ['/admin/'],
    'librarian' => ['/librarian/'],
    'student' => ['/student/']
];

$current_role = $_SESSION['user']['role'];
$current_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$allowed = false;
foreach ($allowed_roles[$current_role] as $path) {
    if (strpos($current_path, $path) === 0) {
        $allowed = true;
        break;
    }
}

if (!$allowed) {
    header("Location: ../{$current_role}/dashboard.php");
    exit();
}
?>