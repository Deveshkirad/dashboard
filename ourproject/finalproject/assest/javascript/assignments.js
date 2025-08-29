document.addEventListener('DOMContentLoaded', function() {
    // Simulate assignment stats
    function updateAssignmentStats() {
        document.getElementById('totalAssignments').textContent = 45;
        document.getElementById('pendingGrading').textContent = 15;
        document.getElementById('gradedAssignments').textContent = 30;
    }
    updateAssignmentStats();
    
    // Submission Rate Chart
    const subCtx = document.getElementById('submissionRateChart').getContext('2d');
    new Chart(subCtx, {
        type: 'pie',
        data: {
            labels: ['Data Structures', 'OOP', 'Digital Logic', 'Maths III'],
            datasets: [{
                label: 'Submission Rate',
                data: [90, 85, 100, 95],
                backgroundColor: ['#6a11cb', '#2575fc', '#34e89e', '#ffc107'],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                    labels: { color: '#333' }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.parsed !== null) {
                                label += context.parsed + '%';
                            }
                            return label;
                        }
                    }
                }
            }
        }
    });
});