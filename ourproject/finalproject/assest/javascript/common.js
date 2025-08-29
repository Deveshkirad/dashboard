document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggler = document.getElementById('sidebarToggler');

    if (sidebarToggler && sidebar) {
        sidebarToggler.addEventListener('click', () => {
            sidebar.classList.toggle('active');
        });
    }

    // Optional: Close sidebar when clicking on the main content area on mobile
    const mainContent = document.querySelector('.main-content');
    if (mainContent && sidebar) {
        mainContent.addEventListener('click', () => {
            if (window.innerWidth < 992 && sidebar.classList.contains('active')) {
                sidebar.classList.remove('active');
            }
        });
    }
});