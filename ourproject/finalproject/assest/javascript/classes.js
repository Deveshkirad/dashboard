document.getElementById('sidebarToggler').onclick = function() {
            document.getElementById('sidebar').classList.toggle('open');
        };

        // Close navbar on link click for mobile
        const navLinks = document.querySelectorAll('#navbarMenu .nav-link');
        const menuToggle = document.getElementById('navbarMenu');
        if (menuToggle) { // Ensure the element exists
            const bsCollapse = new bootstrap.Collapse(menuToggle, { toggle: false });
            navLinks.forEach(function(link) {
                link.addEventListener('click', function() {
                    if (menuToggle.classList.contains('show')) {
                        bsCollapse.hide();
                    }
                });
            });
        }

        // Handle advisor update
        document.querySelectorAll('.update-advisor-btn').forEach(button => {
            button.addEventListener('click', function() {
                const row = this.closest('tr');
                const batchName = row.cells[0].textContent;
                const advisorSelect = row.querySelector('select');
                const newAdvisor = advisorSelect.value;

                // In a real application, you would send this to a server.
                // For this demo, we'll just show an alert.
                alert(`Updating advisor for ${batchName} to ${newAdvisor}.`);
                
                // You could also update the UI to reflect the "saved" state if needed.
            });
        });