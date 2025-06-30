<?php
session_start();
require_once '../includes/db_connection.php';

// Verify user is logged in as student
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'student') {
    $_SESSION['error'] = "You must be logged in as a student to return books.";
    header("Location: ../index.php");
    exit();
}

// Debug database connection (remove after testing)
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$student_id = $_SESSION['user']['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['borrow_id'])) {
    $borrow_id = (int)$_POST['borrow_id'];
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // 1. Get the borrow record with explicit column names
        $stmt = $conn->prepare("SELECT 
                bb.`id`,
                bb.`user_id`,
                bb.`book_id`,
                bb.`borrow_date`,
                bb.`due_date`,
                bb.`returned`,
                b.`title`,
                b.`available`
            FROM `borrowed_books` bb
            JOIN `books` b ON bb.`book_id` = b.`id`
            WHERE bb.`id` = ? AND bb.`user_id` = ? AND bb.`returned` = FALSE FOR UPDATE");
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("ii", $borrow_id, $student_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        $borrow = $result->fetch_assoc();
        $stmt->close();
        
        if (!$borrow) {
            throw new Exception("Borrow record not found or already returned.");
        }

        // 2. Update the borrow record as returned
        $return_date = date('Y-m-d H:i:s');
        $stmt = $conn->prepare("UPDATE `borrowed_books` 
                               SET `returned` = TRUE, `return_date` = ?
                               WHERE `id` = ?");
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("si", $return_date, $borrow_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Update failed: " . $stmt->error);
        }
        $stmt->close();

        // 3. Update book availability
        $stmt = $conn->prepare("UPDATE `books` SET `available` = TRUE WHERE `id` = ?");
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("i", $borrow['book_id']);
        
        if (!$stmt->execute()) {
            throw new Exception("Update failed: " . $stmt->error);
        }
        $stmt->close();

    // After successful return
        $username = $_SESSION['user']['username'];
        $action = "returned a book (Book ID: $book_id)";
        $log = $conn->prepare("INSERT INTO system_logs (username, action, timestamp) VALUES (?, ?, NOW())");
        $log->bind_param("ss", $username, $action);
        $log->execute();

        // 4. Create notification for admin
        $message = "Student {$_SESSION['user']['name']} returned '{$borrow['title']}'";
        $stmt = $conn->prepare("INSERT INTO `notifications` 
                              (`user_id`, `message`, `type`, `related_book_id`) 
                              VALUES (?, ?, 'return', ?)");
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $admin_id = 1; // Assuming admin ID is 1
        $stmt->bind_param("isi", $admin_id, $message, $borrow['book_id']);
        
        if (!$stmt->execute()) {
            throw new Exception("Insert failed: " . $stmt->error);
        }
        $stmt->close();

        // Commit transaction
        $conn->commit();
        
        $_SESSION['success'] = "Successfully returned '{$borrow['title']}'.";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Return failed: " . $e->getMessage();
        error_log("Return Book Error: " . $e->getMessage());
    }
    
    header("Location: student_dashboard.php");
    exit();
}

// Display return confirmation form
if (isset($_GET['borrow_id'])) {
    $borrow_id = (int)$_GET['borrow_id'];
    
    // Get borrow details with explicit column names
    $stmt = $conn->prepare("SELECT 
            bb.`id`,
            bb.`book_id`,
            bb.`borrow_date`,
            bb.`due_date`,
            bb.`return_date`,
            bb.`returned`,
            b.`title`,
            b.`author`,
            b.`cover_image`
        FROM `borrowed_books` bb
        JOIN `books` b ON bb.`book_id` = b.`id`
        WHERE bb.`id` = ? AND bb.`user_id` = ? AND bb.`returned` = FALSE");
    
    if (!$stmt) {
        $_SESSION['error'] = "Database error: " . $conn->error;
        header("Location: student_dashboard.php");
        exit();
    }
    
    $stmt->bind_param("ii", $borrow_id, $student_id);
    
    if (!$stmt->execute()) {
        $_SESSION['error'] = "Database error: " . $stmt->error;
        header("Location: student_dashboard.php");
        exit();
    }
    
    $result = $stmt->get_result();
    $borrow = $result->fetch_assoc();
    $stmt->close();
    
    if (!$borrow) {
        $_SESSION['error'] = "Borrow record not found or already returned.";
        header("Location: student_dashboard.php");
        exit();
    }
    
    // After successful return
        $username = $_SESSION['user']['username'];
        $action = "returned a book (Book ID: $book_id)";
        $log = $conn->prepare("INSERT INTO system_logs (username, action, timestamp) VALUES (?, ?, NOW())");
        $log->bind_param("ss", $username, $action);
        $log->execute();

    // Display return confirmation page
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Return Book</title>
        <link rel="stylesheet" href="../Styling/dashboard_style.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
        <style>
            .return-form-container {
                max-width: 600px;
                margin: 20px auto;
                padding: 20px;
                background: white;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            .book-info {
                display: flex;
                margin-bottom: 20px;
                align-items: center;
            }
            .book-info img {
                width: 100px;
                height: 150px;
                object-fit: cover;
                margin-right: 20px;
                border-radius: 4px;
            }
            .status-message {
                padding: 10px;
                margin: 10px 0;
                border-radius: 4px;
            }
            .success {
                background-color: #d4edda;
                color: #155724;
            }
            .error {
                background-color: #f8d7da;
                color: #721c24;
            }
        </style>
    </head>
    <body>
    <div class="dashboard-container">
        <!-- Your existing sidebar and header HTML remains the same -->
        
        <main class="main-content">
            <header>
                <h1>Return Book</h1>
                <div class="user-info">
                    <span><?= htmlspecialchars($_SESSION['user']['username']) ?></span>
                </div>
            </header>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="status-message error">
                    <?= htmlspecialchars($_SESSION['error']); ?>
                    <?php unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <div class="return-form-container">
                <h2>Confirm Book Return</h2>
                
                <div class="book-info">
                    <img src="<?= htmlspecialchars(!empty($borrow['cover_image']) ? '../' . $borrow['cover_image'] : '../images/default_book.png') ?>" 
                         alt="Book Cover">
                    <div>
                        <h3><?= htmlspecialchars($borrow['title']) ?></h3>
                        <p>by <?= htmlspecialchars($borrow['author']) ?></p>
                        <p>Borrowed on: <?= date('M j, Y', strtotime($borrow['borrow_date'])) ?></p>
                        <p>Due date: <?= date('M j, Y', strtotime($borrow['due_date'])) ?></p>
                    </div>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="borrow_id" value="<?= $borrow['id'] ?>">
                    <div style="text-align: right; margin-top: 20px;">
                        <a href="student_dashboard.php" class="btn">Cancel</a>
                        <button type="submit" class="btn btn-primary">Confirm Return</button>
                    </div>
                </form>
            </div>
        </main>
    </div>
    </body>
    </html>
    <?php
    exit();
}

// Invalid request
$_SESSION['error'] = "Invalid request.";
header("Location: student_dashboard.php");
exit();
?>