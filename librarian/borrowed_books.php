<?php
require_once '../includes/header.php';
require_once '../includes/functions.php';
require_once '../includes/db_connection.php';

session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'librarian') {
    header("Location: index.php");
    exit();
}
// Fetch borrowed books that haven't been returned
$stmt = $conn->prepare("SELECT 
                        bb.id as borrow_id, 
                        b.title, 
                        b.author, 
                        u.username as borrower, 
                        bb.borrow_date, 
                        bb.due_date,
                        DATEDIFF(bb.due_date, CURDATE()) as days_remaining
                    FROM borrowed_books bb
                    JOIN books b ON bb.book_id = b.id
                    JOIN users u ON bb.user_id = u.id
                    WHERE bb.returned = 0
                    ORDER BY bb.due_date ASC");
$stmt->execute();
$borrowed_books = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<div class="container mt-4">
    <h2 class="mb-4">Borrowed Books</h2>
    
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_GET['success']); ?></div>
    <?php endif; ?>
    
    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($_GET['error']); ?></div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead class="thead-dark">
                <tr>
                    <th>Title</th>
                    <th>Author</th>
                    <th>Borrower</th>
                    <th>Borrow Date</th>
                    <th>Due Date</th>
                    <th>Days Remaining</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($borrowed_books) > 0): ?>
                    <?php foreach ($borrowed_books as $book): ?>
                        <tr class="<?php echo $book['days_remaining'] < 0 ? 'table-danger' : ''; ?>">
                            <td><?php echo htmlspecialchars($book['title']); ?></td>
                            <td><?php echo htmlspecialchars($book['author']); ?></td>
                            <td><?php echo htmlspecialchars($book['borrower']); ?></td>
                            <td><?php echo date('M j, Y', strtotime($book['borrow_date'])); ?></td>
                            <td><?php echo date('M j, Y', strtotime($book['due_date'])); ?></td>
                            <td>
                                <span class="badge <?php echo $book['days_remaining'] < 0 ? 'badge-danger' : ($book['days_remaining'] <= 3 ? 'badge-warning' : 'badge-success'); ?>">
                                    <?php echo $book['days_remaining']; ?> days
                                </span>
                            </td>
                            <td>
                                <form action="../includes/return_book.php" method="post" class="d-inline">
                                    <input type="hidden" name="borrow_id" value="<?php echo $book['borrow_id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-success">
                                        <i class="fas fa-check"></i> Mark Returned
                                    </button>
                                </form>
                                <?php if ($book['days_remaining'] < 0): ?>
                                    <button class="btn btn-sm btn-warning ml-1" data-toggle="modal" data-target="#remindModal<?php echo $book['borrow_id']; ?>">
                                        <i class="fas fa-bell"></i> Remind
                                    </button>
                                    
                                    <!-- Remind Modal -->
                                    <div class="modal fade" id="remindModal<?php echo $book['borrow_id']; ?>" tabindex="-1" role="dialog">
                                        <div class="modal-dialog" role="document">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Send Reminder</h5>
                                                    <button type="button" class="close" data-dismiss="modal">
                                                        <span>&times;</span>
                                                    </button>
                                                </div>
                                                <div class="modal-body">
                                                    <p>Send a reminder to <?php echo htmlspecialchars($book['borrower']); ?> about the overdue book "<?php echo htmlspecialchars($book['title']); ?>"?</p>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                                    <form action="../includes/send_reminder.php" method="post">
                                                        <input type="hidden" name="borrow_id" value="<?php echo $book['borrow_id']; ?>">
                                                        <button type="submit" class="btn btn-primary">Send Reminder</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center">No books are currently borrowed</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>