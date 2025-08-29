document.addEventListener('DOMContentLoaded', function() {
    // Simulate teacher stats
    function updateFacultyStats() {
        document.getElementById('totalTeachers').textContent = 10;
        document.getElementById('activeTeachers').textContent = 9;
        document.getElementById('leaveTeachers').textContent = 1;
    }
    updateFacultyStats();
});