// Real-time updates for librarian dashboard
document.addEventListener('DOMContentLoaded', function() {
    // Update borrowed books count in real-time
    function updateBorrowedCount() {
        fetch('../api/get_borrowed_count.php')
            .then(response => response.json())
            .then(data => {
                document.getElementById('borrowed-count').textContent = data.count;
            });
    }
    
    // Update reservations count
    function updateReservationsCount() {
        fetch('../api/get_reservations_count.php')
            .then(response => response.json())
            .then(data => {
                document.getElementById('reservations-count').textContent = data.count;
            });
    }
    
    // Update every 30 seconds
    setInterval(updateBorrowedCount, 30000);
    setInterval(updateReservationsCount, 30000);
    
    // Initial update
    updateBorrowedCount();
    updateReservationsCount();
    
    // Handle book return
    document.querySelectorAll('.return-book').forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('Mark this book as returned?')) {
                e.preventDefault();
            }
        });
    });
});