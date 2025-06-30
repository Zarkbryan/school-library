// Student-specific JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Book search functionality
    const searchInput = document.getElementById('book-search');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            document.querySelectorAll('.book-card').forEach(card => {
                const title = card.querySelector('h3').textContent.toLowerCase();
                const author = card.querySelector('.author').textContent.toLowerCase();
                card.style.display = (title.includes(searchTerm) || author.includes(searchTerm)) ? 'block' : 'none';
            });
        });
    }
    
    // Handle reservation time remaining
    document.querySelectorAll('.reservation-time').forEach(element => {
        const expiry = new Date(element.dataset.expiry);
        const now = new Date();
        const diff = expiry - now;
        
        if (diff > 0) {
            const hours = Math.floor(diff / (1000 * 60 * 60));
            element.textContent = `${hours} hours remaining`;
            
            if (hours < 24) {
                element.classList.add('text-warning');
            }
        } else {
            element.textContent = 'Expired';
            element.classList.add('text-danger');
        }
    });
});