        </div> <!-- Close content-wrapper -->
        <footer class="main-footer">
            <div class="footer-content">
                <p>&copy; <?php echo date('Y'); ?> School Library System</p>
                <p>Developed by Aloro Isaac Brian</p>
            </div>
        </footer>
    </div> <!-- Close wrapper -->
    <script src="../assets/js/main.js"></script>
    <?php if (isset($_SESSION['user']['role'])): ?>
        <script src="../assets/js/<?php echo $_SESSION['user']['role']; ?>.js"></script>
    <?php endif; ?>
</body>
</html>