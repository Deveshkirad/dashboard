    </div> <!-- End of .main-content -->

    <!-- Footer -->
    <footer class="sticky-footer">
        <div class="container my-auto">
            <div class="copyright text-center my-auto">
                <span>Copyright &copy; B.Tech Admin Dashboard 2024</span>
            </div>
        </div>
    </footer>
    <!-- End of Footer -->

    <!-- Global Toast Container for notifications -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1100"></div>

    <!-- ===================================================================
       Global Scripts
    =================================================================== -->
    <!-- Bootstrap JS Bundle (includes Popper for dropdowns) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Chart.js (required for dashboard charts) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Theme Switcher Logic -->
    <script src="assest/javascript/theme-switcher.js"></script>

    <!-- Sidebar Toggler Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const sidebarToggler = document.getElementById('sidebarToggler'); // This is in header.php

            if (sidebar && sidebarToggler) {
                sidebarToggler.addEventListener('click', function() {
                    document.body.classList.toggle('sidebar-toggled');
                });
            }
        });
    </script>

    <script src="assest/javascript/timetable.js"></script>
    <?php
    // Output page-specific JavaScript block if it exists
    if (isset($pageScriptBlock)) {
        echo $pageScriptBlock;
    }
    ?>
</body>
</html>