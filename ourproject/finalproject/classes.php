<?php
require_once 'db.php';

// --- Logic for Batch List ---
// Fetch all available teachers for the dropdowns
$teachersResult = $conn->query("SELECT id, name FROM teachers WHERE status = 'active' ORDER BY name");
$allTeachers = [];
if ($teachersResult) {
    while ($teacher = $teachersResult->fetch_assoc()) {
        $allTeachers[] = $teacher;
    }
}

// Fetch all batches and their assigned faculty advisor
$batchesQuery = "
    SELECT 
        b.id AS batch_id, 
        b.batch_name, 
        t.id AS teacher_id
    FROM batches b
    LEFT JOIN teachers t ON b.faculty_advisor_id = t.id
    ORDER BY b.batch_name
";
$batchesListResult = $conn->query($batchesQuery);

$pageTitle = 'Batches - B.Tech Admin Dashboard';
$pageStylesheets = ['assest/css/classes.css'];
require_once 'header.php';
?>

<h2 class="mb-4">Batch Management</h2>
<!-- Batch List & Advisor Assignment -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fa fa-list"></i> Batch List & Advisor Assignment
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered align-middle">
                <thead>
                    <tr>
                        <th>Batch</th>
                        <th>Faculty Advisor</th>
                    </tr>
                </thead>
                <tbody id="batchListBody">
                    <?php if ($batchesListResult && $batchesListResult->num_rows > 0): ?>
                        <?php while($batch = $batchesListResult->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($batch['batch_name']); ?></td>
                                <td>
                                    <select class="form-select form-select-sm advisor-select" 
                                            name="faculty_advisor_id" 
                                            data-batch-id="<?php echo $batch['batch_id']; ?>">
                                        <option value="0">-- Select Advisor --</option>
                                        <?php foreach ($allTeachers as $teacher): ?>
                                            <option value="<?php echo $teacher['id']; ?>" <?php if ($teacher['id'] == $batch['teacher_id']) echo 'selected'; ?>>
                                                <?php echo htmlspecialchars($teacher['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="2" class="text-center">No batches found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<!-- Timetable (Static Example) -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fa fa-calendar-alt"></i> Weekly Schedule (Example)
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            This is a static example of a timetable. A dynamic timetable management feature is not yet implemented.
        </div>
        <div class="table-responsive">
            <table class="table table-bordered align-middle">
                <thead>
                    <tr>
                        <th>Batch</th>
                        <th>Day</th>
                        <th>Period 1</th>
                        <th>Period 2</th>
                        <th>Period 3</th>
                        <th>Period 4</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Batch A</td>
                        <td>Monday</td>
                        <td>Data Structures</td>
                        <td>OOP</td>
                        <td>Digital Logic</td>
                        <td>Mathematics III</td>
                    </tr>
                    <tr>
                        <td>Batch B</td>
                        <td>Monday</td>
                        <td>OOP</td>
                        <td>Data Structures</td>
                        <td>Economics</td>
                        <td>Digital Logic</td>
                    </tr>
                    <tr>
                        <td>Batch C</td>
                        <td>Monday</td>
                        <td>Data Structures</td>
                        <td>Mathematics III</td>
                        <td>OOP</td>
                        <td>Web Tech Lab</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Toast container for notifications -->
<div class="toast-container position-fixed bottom-0 end-0 p-3"></div>

<?php
// Capture page-specific JavaScript into a variable
ob_start();
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    /**
     * Shows a toast notification.
     * @param {string} message The message to display.
     * @param {string} type 'success' or 'error'.
     */
    function showToast(message, type = 'success') {
        const toastContainer = document.querySelector('.toast-container');
        const toastId = 'toast-' + Date.now();
        const toastBg = type === 'success' ? 'bg-success' : 'bg-danger';

        const toastHTML = `
            <div id="${toastId}" class="toast align-items-center text-white ${toastBg} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        `;

        toastContainer.insertAdjacentHTML('beforeend', toastHTML);
        const toastEl = document.getElementById(toastId);
        const toast = new bootstrap.Toast(toastEl, { delay: 3000 });
        toast.show();
        toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
    }

    // Add event listener for advisor dropdowns
    document.querySelectorAll('.advisor-select').forEach(select => {
        select.addEventListener('change', function(event) {
            const batchId = event.target.dataset.batchId;
            const teacherId = event.target.value;

            const formData = new FormData();
            formData.append('batch_id', batchId);
            formData.append('teacher_id', teacherId);

            fetch('update_advisor.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showToast(data.message, 'success');
                } else {
                    showToast(data.message, 'error');
                    // Optional: revert the selection on error
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('An unexpected error occurred.', 'error');
            });
        });
    });
});
</script>
<?php
$pageScriptBlock = ob_get_clean();
require_once 'footer.php';
?>