<?php
require_once 'db.php';

// --- 1. FETCH DATA FOR FILTERING ---
$batchesResult = $conn->query("SELECT id, batch_name FROM batches ORDER BY batch_name");

// --- 2. GET SELECTED FILTERS ---
$selectedBatchId = isset($_GET['batch_id']) ? (int) $_GET['batch_id'] : 0;
$selectedMonthYear = isset($_GET['month']) ? $_GET['month'] : '2025-07'; // Default to July 2025
$selectedSubject = isset($_GET['subject_dw']) ? trim($_GET['subject_dw']) : '';
$selectedDate = isset($_GET['date_dw']) ? trim($_GET['date_dw']) : '';

// --- Determine which tab should be active: 'upload' or 'view' ---
$activeTab = 'upload'; // Default tab
// If a daily view was requested (and it wasn't a redirect from an upload error)
if (!empty($selectedSubject) && !empty($selectedDate) && !isset($_GET['upload_status'])) {
    $activeTab = 'view';
}


// --- Define date range for the month filter for the 2025 academic year
$months = [];
$start = new DateTime('2025-07-01');
$end = new DateTime('2026-01-01'); // Go up to Jan 2026 to include Dec 2025
$interval = new DateInterval('P1M');
$period = new DatePeriod($start, $interval, $end);

foreach ($period as $dt) {
    $months[$dt->format('Y-m')] = $dt->format('F Y');
}

// --- Fetch All Subjects from DB for the table header ---
$subjectsResultDb = $conn->query("SELECT name FROM subjects ORDER BY name");
$subjectsList = [];
if ($subjectsResultDb) {
    while ($row = $subjectsResultDb->fetch_assoc()) {
        $subjectsList[] = $row['name'];
    }
}

// Initialize array to hold data
$pivotAttendance = [];
$dateWiseAttendance = [];

// --- 3. FETCH ATTENDANCE DATA IF A BATCH IS SELECTED ---
if ($selectedBatchId > 0) {
    list($year, $month) = explode('-', $selectedMonthYear);
    $attendanceQuery = "
        SELECT 
            s.name, 
            ar.subject,
            COUNT(ar.id) as total_classes,
            SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) as attended_classes
        FROM students s
        LEFT JOIN attendance_records ar ON s.id = ar.student_id AND YEAR(ar.attendance_date) = ? AND MONTH(ar.attendance_date) = ?
        WHERE s.batch_id = ?
        GROUP BY s.id, s.name, ar.subject
        ORDER BY s.name, ar.subject
    ";
    $stmt_attendance = $conn->prepare($attendanceQuery);
    $stmt_attendance->bind_param("iii", $year, $month, $selectedBatchId);
    $stmt_attendance->execute();
    $attendanceResult = $stmt_attendance->get_result();
    while ($row = $attendanceResult->fetch_assoc()) {
        if (!empty($row['subject'])) { // Only process records that have a subject
            $pivotAttendance[$row['name']][$row['subject']] = ['total' => $row['total_classes'], 'attended' => $row['attended_classes']];
        }
    }

    // --- 4. FETCH DATE-WISE ATTENDANCE IF FILTERS ARE SET ---
    if (!empty($selectedSubject) && !empty($selectedDate)) {
        $dateWiseQuery = "
            SELECT s.name, s.univ_roll_no, ar.status
            FROM attendance_records ar
            JOIN students s ON ar.student_id = s.id
            WHERE s.batch_id = ?
            AND ar.subject = ?
            AND ar.attendance_date = ?
            ORDER BY s.name
        ";
        $stmt_date_wise = $conn->prepare($dateWiseQuery);
        $stmt_date_wise->bind_param("iss", $selectedBatchId, $selectedSubject, $selectedDate);
        $stmt_date_wise->execute();
        $dateWiseResult = $stmt_date_wise->get_result();
        while ($row = $dateWiseResult->fetch_assoc()) {
            $dateWiseAttendance[] = $row;
        }
    }
}
?>
<?php
$pageTitle = 'Attendance - B.Tech Admin Dashboard';
$pageStylesheets = ['assest/css/attendance.css'];
require_once 'header.php';
?>
<style>
    /* Custom styles for action buttons for better visual feedback */
    .btn-success,
    .btn-primary {
        transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
    }

    .btn-success:hover,
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
    }
</style>
<h2 class="mb-4">Attendance Management</h2>

        <!-- Alert Messages -->
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <strong>Error!</strong> <?php echo htmlspecialchars($_GET['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['upload_status'])): ?>
            <div class="alert <?php echo $_GET['upload_status'] == 'success' ? 'alert-success' : 'alert-danger'; ?> alert-dismissible fade show"
                role="alert">
                <?php
                if ($_GET['upload_status'] == 'success') {
                    $inserted = isset($_GET['inserted']) ? (int)$_GET['inserted'] : 0;
                    $skipped = isset($_GET['skipped']) ? (int)$_GET['skipped'] : 0;
                    $message = "<strong>Success!</strong> {$inserted} attendance records were imported.";
                    if ($skipped > 0) {
                        $message .= " {$skipped} rows were skipped due to invalid data or non-existent roll numbers.";
                    }
                    echo $message;
                } else {
                    echo "<strong>Error!</strong> " . htmlspecialchars($_GET['message'] ?? 'An unknown error occurred.');
                }
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Actions: Upload & View Daily -->
        <div class="card mb-4">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs" id="attendanceActionsTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php if ($activeTab == 'upload') echo 'active'; ?>" id="upload-tab" data-bs-toggle="tab" data-bs-target="#upload-pane" type="button" role="tab" aria-controls="upload-pane" aria-selected="<?php echo $activeTab == 'upload' ? 'true' : 'false'; ?>">
                            <i class="fa fa-upload me-1"></i> Upload Attendance
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php if ($activeTab == 'view') echo 'active'; ?>" id="view-daily-tab" data-bs-toggle="tab" data-bs-target="#view-daily-pane" type="button" role="tab" aria-controls="view-daily-pane" aria-selected="<?php echo $activeTab == 'view' ? 'true' : 'false'; ?>">
                            <i class="fa fa-search me-1"></i> View Daily Attendance
                        </button>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content" id="attendanceActionsTabContent">
                    <!-- Upload Pane -->
                    <div class="tab-pane fade <?php if ($activeTab == 'upload') echo 'show active'; ?>" id="upload-pane" role="tabpanel" aria-labelledby="upload-tab">
                        <form action="upload_attendance.php" method="POST" enctype="multipart/form-data" class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label for="upload_batch_id" class="form-label">For Batch</label>
                                <select class="form-select" name="batch_id" id="upload_batch_id" required>
                                    <option value="">-- Select a Batch --</option>
                                    <?php
                                    if ($batchesResult && $batchesResult->num_rows > 0) {
                                        $batchesResult->data_seek(0); // Reset pointer for re-use
                                        while ($batch = $batchesResult->fetch_assoc()) {
                                            $selected = ($batch['id'] == $selectedBatchId) ? 'selected' : '';
                                            echo "<option value='{$batch['id']}' {$selected}>" . htmlspecialchars($batch['batch_name']) . "</option>";
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="upload_subject" class="form-label">For Subject</label>
                                <select class="form-select" name="subject" id="upload_subject" required disabled>
                                    <option value="">-- Select a Batch First --</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="attendance_date" class="form-label">Date</label>
                                <input type="date" class="form-control" name="attendance_date" id="attendance_date" value="<?php echo htmlspecialchars($selectedDate); ?>" required>
                            </div>
                            <div class="col-md-2">
                                <label for="attendance_file" class="form-label">CSV File</label>
                                <input type="file" class="form-control" name="attendance_file" id="attendance_file" accept=".csv" required>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-success w-100">Upload</button>
                            </div>
                        </form>
                        <div class="form-text mt-2">
                            <i class="fa fa-info-circle"></i> CSV format must have two columns with headers: <code>univ_roll_no</code> and <code>status</code> (values: present/absent).
                        </div>
                    </div>
                    <!-- View Daily Pane -->
                    <div class="tab-pane fade <?php if ($activeTab == 'view') echo 'show active'; ?>" id="view-daily-pane" role="tabpanel" aria-labelledby="view-daily-tab">
                        <form action="attendance.php" method="GET" class="row g-3 align-items-end">
                            <input type="hidden" name="month" value="<?php echo htmlspecialchars($selectedMonthYear); ?>">
                            <div class="col-md-4">
                                <label for="dw_batch_id" class="form-label">Batch</label>
                                <select class="form-select" name="batch_id" id="dw_batch_id" required>
                                    <option value="">-- Select a Batch --</option>
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
                            </div>
                            <div class="col-md-3">
                                <label for="subject_dw" class="form-label">Subject</label>
                                <select class="form-select" name="subject_dw" id="subject_dw" required disabled>
                                    <option value="">-- Select a Batch First --</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="date_dw" class="form-label">Date</label>
                                <input type="date" class="form-control" name="date_dw" id="date_dw" value="<?php echo htmlspecialchars($selectedDate); ?>" required>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">View Daily</button>
                            </div>
                        </form>
                        <?php if (!empty($dateWiseAttendance)): ?>
                            <hr class="my-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="mb-0">Attendance for '<?php echo htmlspecialchars($selectedSubject); ?>' on <?php echo date("F d, Y", strtotime($selectedDate)); ?></h5>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-sm btn-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="fa fa-download me-1"></i> Export Daily
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <a class="dropdown-item" href="export_daily_attendance.php?batch_id=<?php echo $selectedBatchId; ?>&subject=<?php echo urlencode($selectedSubject); ?>&date=<?php echo $selectedDate; ?>&format=csv">
                                                <i class="fa fa-file-csv me-2"></i> Export as CSV
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="export_daily_attendance.php?batch_id=<?php echo $selectedBatchId; ?>&subject=<?php echo urlencode($selectedSubject); ?>&date=<?php echo $selectedDate; ?>&format=pdf">
                                                <i class="fa fa-file-pdf me-2"></i> Export as PDF
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                <table class="table table-striped align-middle attendance-table">
                                    <thead class="table-light">
                                        <tr><th>Student Name</th><th>Univ. Roll No.</th><th>Status</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($dateWiseAttendance as $record): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($record['name']); ?></td>
                                                <td><?php echo htmlspecialchars($record['univ_roll_no']); ?></td>
                                                <td><?php $status = $record['status']; $badgeClass = ($status == 'present') ? 'bg-success' : 'bg-danger'; echo "<span class='badge {$badgeClass}'>" . ucfirst($status) . "</span>"; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php elseif (!empty($selectedSubject) && !empty($selectedDate)): ?>
                            <hr class="my-4">
                            <div class="alert alert-warning">No attendance records found for the selected subject and date.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="fa fa-filter"></i> Filter Attendance Data
            </div>
            <div class="card-body">
                <form action="attendance.php" method="GET" class="row g-3 align-items-end">
                    <div class="col-lg-5 col-md-5">
                        <label for="batch_id" class="form-label">Filter by Batch</label>
                        <select class="form-select" name="batch_id" id="batch_id">
                            <option value="">-- Select a Batch --</option>
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
                    </div>
                    <div class="col-lg-5 col-md-5">
                        <label for="month" class="form-label">Filter by Month</label>
                        <select name="month" id="month" class="form-select">
                            <?php foreach ($months as $value => $label): ?>
                                <option value="<?php echo $value; ?>" <?php if ($value == $selectedMonthYear)
                                       echo 'selected'; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Filter</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Attendance Record -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fa fa-calendar-check"></i> Attendance Record</span>
                <?php if ($selectedBatchId > 0) : ?>
                    <div class="btn-group">
                        <button type="button" class="btn btn-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa fa-download me-1"></i> Export
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item" href="export_attendance.php?batch_id=<?php echo $selectedBatchId; ?>&month=<?php echo $selectedMonthYear; ?>&format=csv">
                                    <i class="fa fa-file-csv me-2"></i> Export as CSV
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="export_attendance.php?batch_id=<?php echo $selectedBatchId; ?>&month=<?php echo $selectedMonthYear; ?>&format=pdf">
                                    <i class="fa fa-file-pdf me-2"></i> Export as PDF
                                </a>
                            </li>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                <div class="table-responsive">
                    <table class="table table-striped align-middle attendance-table">
                        <thead class="table-light">
                            <tr>
                                <th>Student Name</th>
                                <?php foreach ($subjectsList as $subject): ?>
                                    <th><?php echo $subject; ?></th>
                                <?php endforeach; ?>
                                <th>Total Attendance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if (!empty($pivotAttendance)) {
                                foreach ($pivotAttendance as $studentName => $subjectAttendance) {
                                    echo "<tr>";
                                    echo "<td>" . htmlspecialchars($studentName) . "</td>";

                                    $overallAttended = 0;
                                    $overallTotal = 0;

                                    foreach ($subjectsList as $subject) {
                                        if (isset($subjectAttendance[$subject]) && $subjectAttendance[$subject]['total'] > 0) {
                                            $record = $subjectAttendance[$subject];
                                            $overallAttended += $record['attended'];
                                            $overallTotal += $record['total'];
                                            $percentage = round(($record['attended'] / $record['total']) * 100);

                                            $cellClass = '';
                                            if ($percentage >= 75) $cellClass = 'table-success';
                                            elseif ($percentage >= 50) $cellClass = 'table-warning';
                                            else $cellClass = 'table-danger';

                                            echo "<td class='{$cellClass} text-center'>";
                                            echo "  <span class='fw-bold'>{$record['attended']} / {$record['total']}</span>";
                                            echo "  <span class='small text-muted d-block'>({$percentage}%)</span>";
                                            echo "</td>";
                                        } else {
                                            if (isset($subjectAttendance[$subject])) {
                                                $overallAttended += $subjectAttendance[$subject]['attended'];
                                                $overallTotal += $subjectAttendance[$subject]['total'];
                                            }
                                            echo "<td class='text-center text-muted'>N/A</td>";
                                        }
                                    }

                                    $totalCellClass = '';
                                    if ($overallTotal > 0) {
                                        $totalPercentage = round(($overallAttended / $overallTotal) * 100);
                                        if ($totalPercentage >= 75) $totalCellClass = 'table-success';
                                        elseif ($totalPercentage >= 50) $totalCellClass = 'table-warning';
                                        else $totalCellClass = 'table-danger';

                                        echo "<td class='{$totalCellClass} text-center fw-bold'>";
                                        echo "  <span>{$overallAttended} / {$overallTotal}</span>";
                                        echo "  <span class='small text-muted d-block'>({$totalPercentage}%)</span>";
                                        echo "</td>";
                                    } else {
                                        echo "<td class='text-center text-muted'>N/A</td>";
                                    }
                                    echo "</tr>";
                                }
                            } else {
                                $colspan = count($subjectsList) + 2;
                                $message = $selectedBatchId > 0
                                    ? "No attendance records found for the selected batch and month."
                                    : "Please select a batch to view attendance records.";
                                echo "<tr><td colspan='{$colspan}' class='text-center p-5 text-muted'>{$message}</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
<?php
$conn->close();
// Capture page-specific JavaScript into a variable
ob_start();
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Get references to the elements for both forms
    const uploadBatchSelect = document.getElementById('upload_batch_id');
    const uploadSubjectSelect = document.getElementById('upload_subject');
    const dailyViewBatchSelect = document.getElementById('dw_batch_id');
    const dailyViewSubjectSelect = document.getElementById('subject_dw');

    /**
     * Fetches subjects for a given batch and populates a select dropdown.
     * @param {string} batchId The ID of the selected batch.
     * @param {HTMLSelectElement} subjectSelectElement The subject dropdown to populate.
     * @param {string} [selectedSubjectValue=''] An optional subject name to pre-select.
     */
    async function updateSubjectDropdown(batchId, subjectSelectElement, selectedSubjectValue = '') {
        // Clear current options and show loading state
        subjectSelectElement.innerHTML = '<option value="">Loading...</option>';
        subjectSelectElement.disabled = true;

        if (!batchId || batchId === '0' || batchId === '') {
            subjectSelectElement.innerHTML = '<option value="">-- Select a Batch First --</option>';
            return;
        }

        try {
            const response = await fetch(`get_subjects.php?batch_id=${batchId}`);
            if (!response.ok) {
                throw new Error(`Network response was not ok, status: ${response.status}`);
            }
            const data = await response.json();

            // Clear loading message
            subjectSelectElement.innerHTML = '<option value="">-- Select a Subject --</option>';

            if (data.subjects && data.subjects.length > 0) {
                data.subjects.forEach(subject => {
                    const option = document.createElement('option');
                    option.value = subject;
                    option.textContent = subject;
                    // If a subject was pre-selected (e.g., from a previous form submission), select it.
                    if (subject === selectedSubjectValue) {
                        option.selected = true;
                    }
                    subjectSelectElement.appendChild(option);
                });
                subjectSelectElement.disabled = false; // Enable the dropdown
            } else {
                subjectSelectElement.innerHTML = '<option value="">-- No Subjects Found --</option>';
            }
        } catch (error) {
            console.error('Failed to fetch subjects:', error);
            subjectSelectElement.innerHTML = '<option value="">-- Error Loading --</option>';
        }
    }

    // --- Event Listeners ---
    uploadBatchSelect.addEventListener('change', () => updateSubjectDropdown(uploadBatchSelect.value, uploadSubjectSelect));
    dailyViewBatchSelect.addEventListener('change', () => updateSubjectDropdown(dailyViewBatchSelect.value, dailyViewSubjectSelect));

    // --- Initial Load ---
    // Check if a batch is already selected on page load (e.g., from a GET request)
    // and trigger the subject loading for the appropriate forms.
    const preselectedSubject = "<?php echo htmlspecialchars($selectedSubject, ENT_QUOTES); ?>";

    if (uploadBatchSelect.value) {
        updateSubjectDropdown(uploadBatchSelect.value, uploadSubjectSelect, preselectedSubject);
    }
    if (dailyViewBatchSelect.value) {
        updateSubjectDropdown(dailyViewBatchSelect.value, dailyViewSubjectSelect, preselectedSubject);
    }
});
</script>
<?php
$pageScriptBlock = ob_get_clean();
require_once 'footer.php';
?>