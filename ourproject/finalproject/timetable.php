<?php
require_once 'db.php';

// --- 1. Fetch data for filters and modal dropdowns ---
$batchesResult = $conn->query("SELECT id, batch_name FROM batches ORDER BY batch_name");
$subjectsResult = $conn->query("SELECT id, name FROM subjects ORDER BY name");
$teachersResult = $conn->query("
    SELECT t.id, t.name, s.id as subject_id
    FROM teachers t
    JOIN subjects s ON t.subject = s.name
    WHERE t.status = 'active'
    ORDER BY t.name
");

// Store subjects and teachers in arrays for easy access in the modal
$allSubjects = $subjectsResult ? $subjectsResult->fetch_all(MYSQLI_ASSOC) : [];
$allTeachersWithSubjects = $teachersResult ? $teachersResult->fetch_all(MYSQLI_ASSOC) : [];

// --- 2. Get selected batch ---
$selectedBatchId = 0;
if (isset($_GET['batch_id'])) {
    $selectedBatchId = (int)$_GET['batch_id'];
} elseif ($batchesResult && $batchesResult->num_rows > 0) {
    // Default to the first batch if none is selected
    $batchesResult->data_seek(0);
    $firstBatch = $batchesResult->fetch_assoc();
    $selectedBatchId = $firstBatch['id'];
}

$pageTitle = 'Timetable Management';
$pageStylesheets = ['assest/css/timetable.css'];
require_once 'header.php';
?>

<h2 class="mb-4">Timetable Management</h2>

<!-- Batch Selector -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fa fa-filter"></i> Select Batch
    </div>
    <div class="card-body">
        <form action="timetable.php" method="GET" id="batchFilterForm">
            <div class="input-group">
                <select class="form-select" name="batch_id" id="batch_id" onchange="this.form.submit()">
                    <option value="0">-- Select a Batch --</option>
                    <?php
                    if ($batchesResult && $batchesResult->num_rows > 0) {
                        $batchesResult->data_seek(0);
                        while ($batch = $batchesResult->fetch_assoc()) {
                            $selected = ($batch['id'] == $selectedBatchId) ? 'selected' : '';
                            echo "<option value='{$batch['id']}' {$selected}>" . htmlspecialchars($batch['batch_name']) . "</option>";
                        }
                    }
                    ?>
                </select>
                <button type="submit" class="btn btn-primary"><i class="fa fa-sync-alt me-1"></i> Load Timetable</button>
                <?php if ($selectedBatchId > 0): ?>
                    <a href="export_timetable.php?batch_id=<?php echo $selectedBatchId; ?>" class="btn btn-secondary">
                        <i class="fa fa-download me-1"></i> Export as CSV
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php if ($selectedBatchId > 0): ?>
    <div class="timetable-container">
        <div class="timetable-grid">
            <!-- Headers -->
            <div class="timetable-header">Time</div>
            <div class="timetable-header">Monday</div>
            <div class="timetable-header">Tuesday</div>
            <div class="timetable-header">Wednesday</div>
            <div class="timetable-header">Thursday</div>
            <div class="timetable-header">Friday</div>

            <?php
            $timeSlots = [
                1 => "09:00 - 10:00",
                2 => "10:00 - 11:00",
                3 => "11:00 - 12:00",
                4 => "12:00 - 01:00",
                5 => "02:00 - 03:00",
                6 => "03:00 - 04:00"
            ];
            $daysOfWeek = 5; // Monday to Friday

            for ($period = 1; $period <= count($timeSlots); $period++) {
                echo '<div class="timetable-time">' . $timeSlots[$period] . '</div>';
                for ($day = 1; $day <= $daysOfWeek; $day++) {
                    // data-period is the correct term now, not time
                    echo "<div class='timetable-slot empty-slot' data-day='{$day}' data-period='{$period}'>Click to add</div>";
                }
            }
            ?>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="timetableModal" tabindex="-1" aria-labelledby="timetableModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="timetableModalLabel">Edit Timetable Slot</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="timetableSlotForm">
                        <input type="hidden" id="slotDay" name="day_of_week">
                        <input type="hidden" id="slotPeriod" name="period_number">
                        <input type="hidden" name="batch_id" value="<?php echo $selectedBatchId; ?>">

                        <div class="mb-3">
                            <label for="subjectId" class="form-label">Subject</label>
                            <select class="form-select" id="subjectId" name="subject_id" required>
                                <option value="">-- Select Subject --</option>
                                <?php foreach ($allSubjects as $subject): ?>
                                    <option value="<?php echo $subject['id']; ?>"><?php echo htmlspecialchars($subject['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="teacherId" class="form-label">Teacher</label>
                            <select class="form-select" id="teacherId" name="teacher_id" required>
                                <option value="">-- Select a Subject First --</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger me-auto" id="clearSlotButton">Clear Slot</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveTimetableChanges">Save changes</button>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="alert alert-info">Please select a batch to view or manage its timetable.</div>
<?php endif; ?>

<?php
// Capture page-specific JavaScript into a variable
ob_start();
?>
<script>
    // Pass PHP variables to JavaScript. This is crucial for the JS file to know which batch to work with.
    const selectedBatchId = <?php echo $selectedBatchId; ?>;
    const teachersWithSubjects = <?php echo json_encode($allTeachersWithSubjects); ?>;
</script>
<?php
$pageScriptBlock = ob_get_clean();
// The main timetable logic is in timetable.js, which is loaded globally in the footer.
require_once 'footer.php';
?>
