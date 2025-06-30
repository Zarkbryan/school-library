<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'Library System'; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <?php if (isset($_SESSION['user']['role'])): ?>
        <link rel="stylesheet" href="../assets/css/<?php echo $_SESSION['user']['role']; ?>.css">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="wrapper">
        <header class="main-header">
            <div class="logo">
                <h1>Library System</h1>
            </div>
            <nav class="main-nav">
                <?php if (isset($_SESSION['user'])): ?>
                    <span class="welcome-msg">Welcome, <?php echo htmlspecialchars($_SESSION['user']['name']); ?></span>
                <?php endif; ?>
            </nav>
        </header>
        <div class="content-wrapper">