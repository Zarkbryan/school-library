// Admin-specific scripts
document.addEventListener('DOMContentLoaded', () => {
    // Librarian management
    const deleteButtons = document.querySelectorAll('.delete-librarian');
    deleteButtons.forEach(button => {
        button.addEventListener('click', (e) => {
            if (!confirm('Are you sure you want to delete this librarian?')) {
                e.preventDefault();
            }
        });
    });
});