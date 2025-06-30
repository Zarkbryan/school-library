// Common JavaScript for all pages
document.addEventListener('DOMContentLoaded', function() {
    // Toggle mobile menu
    const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
    const mainNav = document.querySelector('.main-nav');
    
    if (mobileMenuBtn && mainNav) {
        mobileMenuBtn.addEventListener('click', function() {
            mainNav.classList.toggle('show');
        });
    }
    
    // Confirm before destructive actions
    document.querySelectorAll('.confirm-action').forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm(this.dataset.confirmMessage || 'Are you sure?')) {
                e.preventDefault();
            }
        });
    });
    
    // Image preview for file inputs
    document.querySelectorAll('[data-image-preview]').forEach(input => {
        input.addEventListener('change', function() {
            const previewId = this.dataset.imagePreview;
            const preview = document.getElementById(previewId);
            
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(this.files[0]);
            }
        });
    });
});