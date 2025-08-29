document.addEventListener('DOMContentLoaded', function () {
    // Live stats simulation
    function setDashboardStats() {
        document.getElementById('teachersCount').textContent = 10;
        document.getElementById('classesCount').textContent = 3;
        document.getElementById('studentsCount').textContent = 180;
        document.getElementById('assignmentsCount').textContent = 45;
    }
    setDashboardStats();

    // Teachers by Department Chart
    const deptCtx = document.getElementById('deptChart').getContext('2d');
    new Chart(deptCtx, {
        type: 'doughnut',
        data: {
            labels: ['Data Structures', 'OOP', 'Digital Logic', 'Mathematics III', 'Economics'],
            datasets: [{
                data: [3, 2, 2, 1, 1],
                backgroundColor: ['#6a11cb', '#2575fc', '#34e89e', '#ffc107', '#f7797d'],
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { color: '#333' }
                }
            }
        }
    });

    // Monthly Student Enrollment Chart
    const enrollCtx = document.getElementById('enrollChart').getContext('2d');
    new Chart(enrollCtx, {
        type: 'line',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
            datasets: [{
                label: 'Enrollments',
                data: [170, 175, 180, 178, 180, 180],
                borderColor: 'rgba(37, 117, 252, 1)',
                backgroundColor: 'rgba(37, 117, 252, 0.1)',
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    ticks: { color: '#555' },
                    grid: { color: '#e9ecef' }
                },
                x: {
                    ticks: { color: '#555' },
                    grid: { color: '#e9ecef' }
                }
            }
        }
    });
});