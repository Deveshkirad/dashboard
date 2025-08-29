<?php
require_once 'db.php';

// --- 1. GET SELECTED FILTERS & FETCH BATCHES FOR DROPDOWN ---
$selectedBatchId = isset($_GET['batch_id']) ? (int)$_GET['batch_id'] : 0;
$batchesResult = $conn->query("SELECT id, batch_name FROM batches ORDER BY batch_name");

// --- 2. BUILD DYNAMIC WHERE CLAUSE FOR FILTERING ---
$whereClause = '';
$params = [];
$types = '';
if ($selectedBatchId > 0) {
    // This subquery finds all subjects that are taught in the selected batch
    // by checking which subjects have marks recorded for students in that batch.
    $whereClause = "WHERE subject IN (SELECT DISTINCT sm.subject FROM student_marks sm JOIN students s ON sm.student_id = s.id WHERE s.batch_id = ?)";
    $params[] = $selectedBatchId;
    $types .= 'i';
}

// --- 3. Fetch data for summary cards with filter ---
$summaryQuery = "
    SELECT
        COUNT(id) as total,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN status = 'on_leave' THEN 1 ELSE 0 END) as on_leave
    FROM teachers
    $whereClause
";
$stmt_summary = $conn->prepare($summaryQuery);
if (!empty($types)) {
    $stmt_summary->bind_param($types, ...$params);
}
$stmt_summary->execute();
$summaryResult = $stmt_summary->get_result();
$summaryCounts = $summaryResult->fetch_assoc();

$totalTeachers = $summaryCounts['total'] ?? 0;
$activeTeachers = $summaryCounts['active'] ?? 0;
$onLeaveTeachers = $summaryCounts['on_leave'] ?? 0;


// --- 4. Fetch teachers for the progression list with filter ---
$teachersQuery = "SELECT id, name, subject, status FROM teachers $whereClause ORDER BY subject, name";
$stmt_teachers = $conn->prepare($teachersQuery);
if (!empty($types)) {
    // Use a temporary variable for bind_param as it expects references
    $bind_params = $params;
    $stmt_teachers->bind_param($types, ...$bind_params);
}
$stmt_teachers->execute();
$allTeachersResult = $stmt_teachers->get_result();

// --- 5. Fetch all units and group them by subject ---
$allUnitsResult = $conn->query("SELECT id, subject_name, unit_number, unit_name FROM subject_units ORDER BY subject_name, unit_number");
$subjectUnits = [];
if ($allUnitsResult) {
    while ($unit = $allUnitsResult->fetch_assoc()) {
        $subjectUnits[$unit['subject_name']][] = $unit;
    }
}

// --- 6. Fetch batch-specific completed units and group them by teacher ---
$completedUnitsMap = [];
if ($selectedBatchId > 0) {
    $completedUnitsQuery = "SELECT teacher_id, unit_id FROM teacher_completed_units WHERE batch_id = ?";
    $stmt_completed = $conn->prepare($completedUnitsQuery);
    $stmt_completed->bind_param("i", $selectedBatchId);
    $stmt_completed->execute();
    $completedUnitsResult = $stmt_completed->get_result();

    if ($completedUnitsResult) {
        while ($row = $completedUnitsResult->fetch_assoc()) {
            $completedUnitsMap[$row['teacher_id']][] = $row['unit_id'];
        }
    }
    $stmt_completed->close();
}

$pageTitle = 'Faculty - B.Tech Admin Dashboard';
require_once 'header.php';
?>
<h2 class="mb-4">Faculty Stats & Course Progression</h2>

<!-- Filter by Batch -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fa fa-filter"></i> Filter Faculty by Batch
    </div>
    <div class="card-body">
        <form action="teachers.php" method="GET" class="row g-3 align-items-end">
            <div class="col-md-10">
                <label for="batch_id" class="form-label">Select Batch</label>
                <select class="form-select" name="batch_id" id="batch_id" onchange="this.form.submit()">
                    <option value="0">-- Show All Faculty --</option>
                    <?php
                    if ($batchesResult && $batchesResult->num_rows > 0) {
                        // Reset pointer in case it was used before
                        $batchesResult->data_seek(0);
                        while ($batch = $batchesResult->fetch_assoc()) {
                            $selected = ($batch['id'] == $selectedBatchId) ? 'selected' : '';
                            echo "<option value='{$batch['id']}' {$selected}>" . htmlspecialchars($batch['batch_name']) . "</option>";
                        }
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
        </form>
    </div>
</div>


<!-- Teacher Stats -->
<div class="row text-center mb-4">
    <div class="col-md-4 mb-3">
        <div class="card text-white bg-primary h-100">
            <div class="card-body">
                <h5 class="card-title">Total Faculty</h5>
                <p class="card-text fs-3" id="totalTeachers"><?php echo $totalTeachers; ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card text-white bg-success h-100">
            <div class="card-body">
                <h5 class="card-title">Active</h5>
                <p class="card-text fs-3" id="activeTeachers"><?php echo $activeTeachers; ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <div class="card text-white bg-warning h-100">
            <div class="card-body">
                <h5 class="card-title">On Leave</h5>
                <p class="card-text fs-3" id="onLeaveTeachers"><?php echo $onLeaveTeachers; ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Subject Progression -->
<div class="row">
    <?php if ($allTeachersResult && $allTeachersResult->num_rows > 0) : ?>
        <?php
        while ($teacher = $allTeachersResult->fetch_assoc()) :
            // Get units for this teacher's subject
            $currentSubject = trim($teacher['subject']);
            $unitsForThisSubject = $subjectUnits[$currentSubject] ?? [];
            $totalUnitsForSubject = count($unitsForThisSubject);
            $completedUnitIds = $completedUnitsMap[$teacher['id']] ?? [];
            $completedUnitsCount = count($completedUnitIds);

            // Calculate progress based on units completed
            $progress = ($totalUnitsForSubject > 0) ? round(($completedUnitsCount / $totalUnitsForSubject) * 100) : 0;

            $progressColor = 'primary'; // Default color
            if ($progress >= 100) $progressColor = 'success';
            elseif ($progress >= 75) $progressColor = 'info';
            elseif ($progress >= 50) $progressColor = 'warning';
        ?>
            <div class="col-md-6 mb-4">
                <div class="card progression-card h-100">
                    <div class="card-body">
                        <div>
                            <h5 class="card-title"><?php echo htmlspecialchars($teacher['subject']); ?> Progression</h5>
                            <div class="progress mb-2" style="height: 20px;">
                                <div id="progress-bar-<?php echo $teacher['id']; ?>" class="progress-bar bg-<?php echo $progressColor; ?>" role="progressbar" style="width: <?php echo $progress; ?>%;" aria-valuenow="<?php echo $progress; ?>" aria-valuemin="0" aria-valuemax="100">
                                    <span id="progress-text-<?php echo $teacher['id']; ?>"><?php echo $progress; ?>%</span>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <small>Faculty: <?php echo htmlspecialchars($teacher['name']); ?></small>
                                <div class="form-check form-switch">
                                    <input class="form-check-input status-toggle" type="checkbox" role="switch" id="status-toggle-<?php echo $teacher['id']; ?>" data-teacher-id="<?php echo $teacher['id']; ?>" <?php if ($teacher['status'] == 'active') echo 'checked'; ?>>
                                    <label class="form-check-label small" for="status-toggle-<?php echo $teacher['id']; ?>">
                                        <?php echo ($teacher['status'] == 'active') ? 'Active' : 'On Leave'; ?>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3 unit-selection-container" data-total-units="<?php echo $totalUnitsForSubject; ?>">
                            <label class="form-label fw-bold">Select Completed Units:</label>
                            <?php if ($selectedBatchId <= 0) : ?>
                                <div class="alert alert-info small p-2">Please select a batch to view and update course progression.</div>
                            <?php endif; ?>
                            <div class="unit-checkbox-list">
                                <?php if (!empty($unitsForThisSubject)) : ?>
                                    <?php foreach ($unitsForThisSubject as $unit) : ?>
                                        <div class="form-check">
                                            <input class="form-check-input unit-checkbox" type="checkbox" 
                                                id="unit-check-T<?php echo $teacher['id']; ?>-U<?php echo $unit['id']; ?>" data-teacher-id="<?php echo $teacher['id']; ?>" data-unit-id="<?php echo $unit['id']; ?>" 
                                                <?php if (in_array($unit['id'], $completedUnitIds)) echo 'checked'; ?> <?php if ($teacher['status'] == 'on_leave' || $selectedBatchId <= 0) echo 'disabled'; ?>>
                                            <label class="form-check-label" for="unit-check-T<?php echo $teacher['id']; ?>-U<?php echo $unit['id']; ?>">
                                                Unit <?php echo $unit['unit_number']; ?>: <?php echo htmlspecialchars($unit['unit_name']); ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <small class="text-muted">No units defined for this subject.</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else : ?>
        <div class="col-12">
            <div class="alert alert-warning text-center">
                <?php
                if ($selectedBatchId > 0) {
                    echo "No faculty found for the selected batch. This may be because no marks have been recorded yet for subjects in this batch.";
                } else {
                    echo "No faculty data found in the system.";
                }
                ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
// Capture page-specific JavaScript into a variable
ob_start();
?>
<script>
    const selectedBatchId = <?php echo $selectedBatchId; ?>;
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
            const toast = new bootstrap.Toast(toastEl, {
                delay: 3000
            });
            toast.show();
            toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
        }

        // Add event listener for status toggles
        document.querySelectorAll('.status-toggle').forEach(toggle => {
            toggle.addEventListener('change', function(event) {
                const teacherId = event.target.dataset.teacherId;
                const isChecked = event.target.checked;
                const newStatus = isChecked ? 'active' : 'on_leave';
                const label = document.querySelector(`label[for="status-toggle-${teacherId}"]`);
                const unitCheckboxes = document.querySelectorAll(`.unit-checkbox[data-teacher-id="${teacherId}"]`);

                // --- 1. Optimistic UI Update for instant feedback ---
                const activeCountEl = document.getElementById('activeTeachers');
                const onLeaveCountEl = document.getElementById('onLeaveTeachers');
                const originalActiveCount = parseInt(activeCountEl.textContent, 10);
                const originalOnLeaveCount = parseInt(onLeaveCountEl.textContent, 10);

                // Update label text and summary cards
                label.textContent = isChecked ? 'Active' : 'On Leave';
                if (isChecked) { // Was on_leave, now active
                    activeCountEl.textContent = originalActiveCount + 1;
                    onLeaveCountEl.textContent = originalOnLeaveCount - 1;
                } else { // Was active, now on_leave
                    activeCountEl.textContent = originalActiveCount - 1;
                    onLeaveCountEl.textContent = originalOnLeaveCount + 1;
                }

                // Enable/disable unit checkboxes
                unitCheckboxes.forEach(checkbox => {
                    checkbox.disabled = !isChecked;
                });

                // --- 2. Send update to server ---
                const formData = new FormData();
                formData.append('teacher_id', teacherId);
                formData.append('status', newStatus);

                fetch('update_teacher_status.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            showToast(data.message, 'success');
                        } else {
                            showToast(data.message, 'error');
                            // Revert UI on error
                            event.target.checked = !isChecked;
                            label.textContent = isChecked ? 'On Leave' : 'Active';
                            activeCountEl.textContent = originalActiveCount;
                            onLeaveCountEl.textContent = originalOnLeaveCount;
                            unitCheckboxes.forEach(checkbox => {
                                checkbox.disabled = isChecked;
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showToast('An unexpected error occurred.', 'error');
                    });
            });
        });

        // Add event listener to all unit checkboxes
        document.querySelectorAll('.unit-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function(event) {
                const teacherId = event.target.dataset.teacherId;
                const unitId = event.target.dataset.unitId;
                const isCompleted = event.target.checked;

                // --- 1. Update UI immediately for responsiveness ---
                const container = event.target.closest('.unit-selection-container');
                const totalUnits = parseInt(container.dataset.totalUnits, 10) || 0;
                const completedCount = container.querySelectorAll('.unit-checkbox:checked').length;

                const progressBar = document.getElementById(`progress-bar-${teacherId}`);
                const progressText = document.getElementById(`progress-text-${teacherId}`);

                if (progressBar && progressText) {
                    const newProgress = (totalUnits > 0) ? Math.round((completedCount / totalUnits) * 100) : 0;

                    // Update progress bar width and text
                    progressBar.style.width = newProgress + '%';
                    progressBar.setAttribute('aria-valuenow', newProgress);
                    progressText.textContent = newProgress + '%';

                    // Update progress bar color based on new progress
                    progressBar.classList.remove('bg-primary', 'bg-success', 'bg-info', 'bg-warning');
                    if (newProgress >= 100) progressBar.classList.add('bg-success');
                    else if (newProgress >= 75) progressBar.classList.add('bg-info');
                    else if (newProgress >= 50) progressBar.classList.add('bg-warning');
                    else progressBar.classList.add('bg-primary');
                }

                // --- 2. Send update to the server via AJAX ---
                const formData = new FormData();
                formData.append('teacher_id', teacherId);
                formData.append('unit_id', unitId);
                formData.append('is_completed', isCompleted);
                formData.append('batch_id', selectedBatchId);

                fetch('update_unit_status.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            // Don't show a toast for every single click, it can be annoying.
                            // A visual checkmark is enough. We'll only show toasts for errors.
                        } else {
                            showToast(data.message, 'error');
                            // Revert the checkbox state on error
                            event.target.checked = !isCompleted;
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
