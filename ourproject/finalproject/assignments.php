<?php
require_once 'db.php';

// --- 1. GET FILTERS & SORTING ---
$filter_batch_id = isset($_GET['batch_id']) ? (int)$_GET['batch_id'] : 0;
$filter_subject = isset($_GET['subject']) ? trim($_GET['subject']) : '';
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : '';
$sort_order = isset($_GET['sort']) ? trim($_GET['sort']) : 'due_date_desc';

// --- 2. FETCH DATA FOR DROPDOWNS ---
$batchesResult = $conn->query("SELECT id, batch_name FROM batches ORDER BY batch_name");
$subjectsResult = $conn->query("SELECT name FROM subjects ORDER BY name");

// --- 3. BUILD DYNAMIC QUERY PARTS ---
$where_clauses = [];
$params = [];
$types = '';

if ($filter_batch_id > 0) {
    $where_clauses[] = "a.batch_id = ?";
    $params[] = $filter_batch_id;
    $types .= 'i';
}
if (!empty($filter_subject)) {
    $where_clauses[] = "a.subject = ?";
    $params[] = $filter_subject;
    $types .= 's';
}
if (!empty($filter_status)) {
    $where_clauses[] = "a.status = ?";
    $params[] = $filter_status;
    $types .= 's';
}
$where_sql = empty($where_clauses) ? '' : 'WHERE ' . implode(' AND ', $where_clauses);

// --- Build ORDER BY clause ---
$order_by_sql = "ORDER BY a.due_date DESC"; // Default
$sort_options = [
    'due_date_desc' => 'ORDER BY a.due_date DESC',
    'due_date_asc' => 'ORDER BY a.due_date ASC',
    'status' => 'ORDER BY a.status ASC, a.due_date DESC', // Pending first
    'created_at_desc' => 'ORDER BY a.created_at DESC',
];
if (array_key_exists($sort_order, $sort_options)) {
    $order_by_sql = $sort_options[$sort_order];
}

// --- 4. EXECUTE QUERIES ---
// Summary Query
$summaryQuery = "
    SELECT
        COUNT(a.id) as total,
        SUM(CASE WHEN a.status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN a.status = 'graded' THEN 1 ELSE 0 END) as graded
    FROM assignments a
    $where_sql
";
$stmt_summary = $conn->prepare($summaryQuery);
if (!empty($types)) {
    $stmt_summary->bind_param($types, ...$params);
}
$stmt_summary->execute();
$summaryResult = $stmt_summary->get_result();
$summaryCounts = $summaryResult->fetch_assoc();
$totalAssignments = $summaryCounts['total'] ?? 0;
$pendingGrading = $summaryCounts['pending'] ?? 0;
$gradedAssignments = $summaryCounts['graded'] ?? 0;

// Main List Query
$assignmentsQuery = "
    SELECT a.id, a.title, b.batch_name, a.subject, a.due_date, a.status
    FROM assignments a
    JOIN batches b ON a.batch_id = b.id
    $where_sql
    $order_by_sql
";
$stmt_assignments = $conn->prepare($assignmentsQuery);
if (!empty($types)) {
    $stmt_assignments->bind_param($types, ...$params);
}
$stmt_assignments->execute();
$assignmentsResult = $stmt_assignments->get_result();

// --- 5. FETCH DATA FOR SUBMISSION ANALYTICS CHART ---
// This query calculates submission stats for each assignment, respecting the active filters.
$analyticsQuery = "
    SELECT
        a.title,
        sc.student_count AS total_students,
        IFNULL(sub_stats.submitted_count, 0) AS submitted_count,
        IFNULL(sub_stats.late_count, 0) AS late_count
    FROM
        assignments a
    JOIN
        (SELECT batch_id, COUNT(id) AS student_count FROM students GROUP BY batch_id) sc ON a.batch_id = sc.batch_id
    LEFT JOIN
        (
            SELECT
                assignment_id,
                COUNT(id) AS submitted_count,
                SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) AS late_count
            FROM
                assignment_submissions
            GROUP BY
                assignment_id
        ) sub_stats ON a.id = sub_stats.assignment_id
    $where_sql
    $order_by_sql
";
$stmt_analytics = $conn->prepare($analyticsQuery);
if (!empty($types)) {
    $stmt_analytics->bind_param($types, ...$params);
}
$stmt_analytics->execute();
$analyticsResult = $stmt_analytics->get_result();

$submissionAnalyticsData = ['labels' => [], 'datasets' => [['label' => 'Submission Rate (%)', 'data' => [], 'backgroundColor' => [], 'yAxisID' => 'y',], ['label' => 'Late Submissions', 'data' => [], 'backgroundColor' => '#dc3545', 'yAxisID' => 'y1',]]];
$lowSubmissionThreshold = 75; // Highlight assignments with submission rate below 75%

if ($analyticsResult) {
    while ($row = $analyticsResult->fetch_assoc()) {
        $rate = ($row['total_students'] > 0) ? round(($row['submitted_count'] / $row['total_students']) * 100) : 0;
        $submissionAnalyticsData['labels'][] = $row['title'];
        $submissionAnalyticsData['datasets'][0]['data'][] = $rate;
        $submissionAnalyticsData['datasets'][0]['backgroundColor'][] = ($rate < $lowSubmissionThreshold) ? 'rgba(255, 193, 7, 0.7)' : 'rgba(37, 117, 252, 0.7)';
        $submissionAnalyticsData['datasets'][1]['data'][] = $row['late_count'];
    }
}
$submissionAnalyticsJson = json_encode($submissionAnalyticsData);

$pageTitle = 'Assignments - B.Tech Admin Dashboard';
$pageStylesheets = ['assest/css/assignments.css', 'assest/css/dashboard.css']; // Include dashboard styles for cards
require_once 'header.php';
?>
<h2 class="mb-4">Assignment Management</h2>

<!-- Alert Messages -->
<?php if (isset($_GET['status'])): ?>
    <div class="alert <?php echo $_GET['status'] == 'success' ? 'alert-success' : 'alert-danger'; ?> alert-dismissible fade show" role="alert">
        <?php
        if ($_GET['status'] == 'success') {
            echo "<strong>Success!</strong> The new assignment has been created.";
        } else {
            echo "<strong>Error!</strong> " . htmlspecialchars($_GET['message'] ?? 'An unknown error occurred.');
        }
        ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>


<!-- Assignment Stats -->
<div class="row mb-4">
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card stat-card h-100">
            <div class="card-body">
                <div class="stat-card-icon bg-primary">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="stat-card-content">
                    <div class="stat-card-title">Total Assignments</div>
                    <div class="stat-card-value"><?php echo $totalAssignments; ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card stat-card h-100">
            <div class="card-body">
                <div class="stat-card-icon bg-warning">
                    <i class="fas fa-hourglass-half"></i>
                </div>
                <div class="stat-card-content">
                    <div class="stat-card-title">Pending Grading</div>
                    <div class="stat-card-value" id="pendingValue"><?php echo $pendingGrading; ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card stat-card h-100">
            <div class="card-body">
                <div class="stat-card-icon bg-success">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-card-content">
                    <div class="stat-card-title">Graded</div>
                    <div class="stat-card-value" id="gradedValue"><?php echo $gradedAssignments; ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Search & Filter -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fa fa-filter"></i> Filter & Sort Assignments
    </div>
    <div class="card-body">
        <form action="assignments.php" method="GET" class="row g-3 align-items-end">
            <div class="col-lg-3 col-md-6">
                <label for="filter_batch_id" class="form-label">Batch</label>
                <select class="form-select" name="batch_id" id="filter_batch_id">
                    <option value="">All Batches</option>
                    <?php
                    if ($batchesResult && $batchesResult->num_rows > 0) {
                        $batchesResult->data_seek(0);
                        while ($batch = $batchesResult->fetch_assoc()) {
                            $selected = ($batch['id'] == $filter_batch_id) ? 'selected' : '';
                            echo "<option value='{$batch['id']}' {$selected}>" . htmlspecialchars($batch['batch_name']) . "</option>";
                        }
                    }
                    ?>
                </select>
            </div>
            <div class="col-lg-3 col-md-6">
                <label for="filter_subject" class="form-label">Subject</label>
                <select class="form-select" name="subject" id="filter_subject">
                    <option value="">All Subjects</option>
                    <?php
                    if ($subjectsResult && $subjectsResult->num_rows > 0) {
                        while ($subject = $subjectsResult->fetch_assoc()) {
                            $selected = ($subject['name'] == $filter_subject) ? 'selected' : '';
                            echo "<option value='" . htmlspecialchars($subject['name']) . "' {$selected}>" . htmlspecialchars($subject['name']) . "</option>";
                        }
                    }
                    ?>
                </select>
            </div>
            <div class="col-lg-2 col-md-4">
                <label for="filter_status" class="form-label">Status</label>
                <select class="form-select" name="status" id="filter_status">
                    <option value="">All Statuses</option>
                    <option value="pending" <?php if ($filter_status == 'pending') echo 'selected'; ?>>Pending</option>
                    <option value="graded" <?php if ($filter_status == 'graded') echo 'selected'; ?>>Graded</option>
                </select>
            </div>
            <div class="col-lg-2 col-md-4">
                <label for="sort_order" class="form-label">Sort By</label>
                <select class="form-select" name="sort" id="sort_order">
                    <option value="due_date_desc" <?php if ($sort_order == 'due_date_desc') echo 'selected'; ?>>Due Date (Newest)</option>
                    <option value="due_date_asc" <?php if ($sort_order == 'due_date_asc') echo 'selected'; ?>>Due Date (Oldest)</option>
                    <option value="status" <?php if ($sort_order == 'status') echo 'selected'; ?>>Status (Pending First)</option>
                    <option value="created_at_desc" <?php if ($sort_order == 'created_at_desc') echo 'selected'; ?>>Created Date</option>
                </select>
            </div>
            <div class="col-lg-2 col-md-4">
                <button type="submit" class="btn btn-primary w-100">Apply</button>
            </div>
        </form>
    </div>
</div>


<!-- Assignment List -->
<div class="card mb-4">
    <div class="card-header">📋 Current Assignments</div>
    <div class="card-body">
        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
            <table class="table table-bordered align-middle">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Batch</th>
                        <th>Subject</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($assignmentsResult && $assignmentsResult->num_rows > 0): ?>
                        <?php while($assignment = $assignmentsResult->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($assignment['title']); ?></td>
                                <td><?php echo htmlspecialchars($assignment['batch_name']); ?></td>
                                <td><?php echo htmlspecialchars($assignment['subject']); ?></td>
                                <td><?php echo date("M d, Y", strtotime($assignment['due_date'])); ?></td>
                                <td>
                                    <?php if ($assignment['status'] == 'pending'): ?>
                                        <span class="badge bg-warning text-dark"><i class="fas fa-hourglass-half me-1"></i> Pending</span>
                                    <?php else: ?>
                                        <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i> Graded</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group">
                                        <button class="btn btn-sm btn-info view-submissions-btn" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#submissionsModal" 
                                                data-assignment-id="<?php echo $assignment['id']; ?>"
                                                data-assignment-title="<?php echo htmlspecialchars($assignment['title']); ?>">
                                            <i class="fa fa-eye"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-info dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                                            <span class="visually-hidden">Toggle Dropdown</span>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li><a class="dropdown-item update-status-btn" href="#" data-assignment-id="<?php echo $assignment['id']; ?>" data-new-status="graded"><i class="fas fa-check-circle me-2 text-success"></i> Mark as Graded</a></li>
                                            <li><a class="dropdown-item update-status-btn" href="#" data-assignment-id="<?php echo $assignment['id']; ?>" data-new-status="pending"><i class="fas fa-hourglass-half me-2 text-warning"></i> Mark as Pending</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item send-reminder-btn" href="#" data-assignment-id="<?php echo $assignment['id']; ?>"><i class="fas fa-bell me-2 text-primary"></i> Send Reminder</a></li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center">No assignments found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create New Assignment -->
<div class="card mb-4">
    <div class="card-header">➕ Create New Assignment</div>
    <div class="card-body">
        <form action="create_assignment.php" method="POST">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="assignmentTitle" class="form-label">Assignment Title</label>
                    <input type="text" class="form-control" id="assignmentTitle" name="title" placeholder="e.g., Chapter 5 Questions" required>
                </div>
                <div class="col-md-6">
                    <label for="assignmentSubject" class="form-label">Subject</label>
                    <select id="assignmentSubject" name="subject" class="form-select" required disabled>
                        <option value="">-- Select a Batch First --</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="assignmentClass" class="form-label">Batch</label>
                    <select id="assignmentClass" name="batch_id" class="form-select" required>
                        <option value="" selected>Choose a batch...</option>
                        <?php if ($batchesResult && $batchesResult->num_rows > 0): ?>
                            <?php $batchesResult->data_seek(0); ?>
                            <?php while($batch = $batchesResult->fetch_assoc()): ?>
                                <option value="<?php echo $batch['id']; ?>"><?php echo htmlspecialchars($batch['batch_name']); ?></option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="assignmentDueDate" class="form-label">Due Date</label>
                    <input type="date" class="form-control" id="assignmentDueDate" name="due_date" required>
                </div>
                <div class="col-12">
                    <label for="assignmentInstructions" class="form-label">Instructions</label>
                    <textarea class="form-control" id="assignmentInstructions" name="instructions" rows="3" placeholder="Add any specific instructions for the assignment..."></textarea>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Create Assignment</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Submission Rate Chart -->
<div class="card mb-4">
    <div class="card-header">📊 Submission Analytics</div>
    <div class="card-body d-flex justify-content-center">
        <div style="max-width: 450px; width: 100%;">
            <canvas id="submissionRateChart"></canvas>
        </div>
    </div>
</div>

<!-- View Submissions Modal -->
<div class="modal fade" id="submissionsModal" tabindex="-1" aria-labelledby="submissionsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="submissionsModalLabel">Submission Stats</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="submissionsModalBody">
                <!-- Stats will be loaded here via JS -->
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php
// Capture page-specific JavaScript into a variable
ob_start();
?>
<script>
    // Data from PHP for the chart
    const submissionAnalyticsData = <?php echo $submissionAnalyticsJson; ?>;

document.addEventListener('DOMContentLoaded', function() {
    // --- Dynamic Subject Dropdown for Create Form ---
    const batchSelect = document.getElementById('assignmentClass');
    const subjectSelect = document.getElementById('assignmentSubject');

    async function updateSubjects(batchId) {
        subjectSelect.innerHTML = '<option value="">Loading...</option>';
        subjectSelect.disabled = true;

        if (!batchId) {
            subjectSelect.innerHTML = '<option value="">-- Select a Batch First --</option>';
            return;
        }

        try {
            const response = await fetch(`get_subjects.php?batch_id=${batchId}`);
            if (!response.ok) {
                throw new Error(`Network response was not ok, status: ${response.status}`);
            }
            const data = await response.json();

            subjectSelect.innerHTML = '<option value="">-- Select a Subject --</option>';

            if (data.subjects && data.subjects.length > 0) {
                data.subjects.forEach(subject => {
                    const option = document.createElement('option');
                    option.value = subject;
                    option.textContent = subject;
                    subjectSelect.appendChild(option);
                });
                subjectSelect.disabled = false;
            } else {
                subjectSelect.innerHTML = '<option value="">-- No Subjects Found --</option>';
            }
        } catch (error) {
            console.error('Failed to fetch subjects:', error);
            subjectSelect.innerHTML = '<option value="">-- Error Loading --</option>';
        }
    }

    batchSelect.addEventListener('change', () => {
        updateSubjects(batchSelect.value);
    });

    // If a batch is already selected (e.g., due to form validation error re-population, though not implemented here yet),
    // trigger the subject load.
    if (batchSelect.value) {
        updateSubjects(batchSelect.value);
    }

    // --- View Submissions Modal Logic ---
    const submissionsModal = document.getElementById('submissionsModal');
    if (submissionsModal) {
        submissionsModal.addEventListener('show.bs.modal', async function(event) {
            const button = event.relatedTarget;
            const assignmentId = button.dataset.assignmentId;
            const assignmentTitle = button.dataset.assignmentTitle;

            const modalTitle = submissionsModal.querySelector('.modal-title');
            const modalBody = submissionsModal.querySelector('.modal-body');

            modalTitle.textContent = `Stats for: ${assignmentTitle}`;
            modalBody.innerHTML = `<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>`;

            try {
                const response = await fetch(`get_submission_stats.php?assignment_id=${assignmentId}`);
                if (!response.ok) {
                    throw new Error('Network response was not ok.');
                }
                const data = await response.json();

                if (data.status === 'success') {
                    const stats = data.stats;
                    const submissionRate = stats.total_students > 0 ? ((stats.submission_count / stats.total_students) * 100).toFixed(1) : 0;
                    
                    modalBody.innerHTML = `
                        <ul class="list-group">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Submissions
                                <span class="badge bg-primary rounded-pill">${stats.submission_count} / ${stats.total_students}</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Submission Rate
                                <span class="badge bg-info rounded-pill">${submissionRate}%</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                Late Submissions
                                <span class="badge bg-danger rounded-pill">${stats.late_count}</span>
                            </li>
                        </ul>
                    `;
                } else {
                    modalBody.innerHTML = `<div class="alert alert-danger">${data.message || 'Could not load stats.'}</div>`;
                }
            } catch (error) {
                console.error('Error fetching submission stats:', error);
                modalBody.innerHTML = `<div class="alert alert-danger">An error occurred while loading the stats. Please try again.</div>`;
            }
        });
    }

    let submissionChartInstance = null;

    function initializeSubmissionChart() {
        if (submissionChartInstance) submissionChartInstance.destroy();

        const isDarkMode = document.documentElement.classList.contains('dark-mode');
        const gridColor = isDarkMode ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)';
        const fontColor = isDarkMode ? '#c7c7c7' : '#5a5c69';
        Chart.defaults.color = fontColor;
        Chart.defaults.font.family = "'Poppins', sans-serif";

        const submissionCtx = document.getElementById('submissionRateChart')?.getContext('2d');
        if (submissionCtx && submissionAnalyticsData.labels.length > 0) {
            submissionChartInstance = new Chart(submissionCtx, {
                type: 'bar',
                data: {
                    labels: submissionAnalyticsData.labels,
                    datasets: submissionAnalyticsData.datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.parsed.y !== null) {
                                        // Add '%' for the submission rate dataset
                                        if (context.dataset.yAxisID === 'y') {
                                            label += context.parsed.y + '%';
                                        } else {
                                            label += context.parsed.y;
                                        }
                                    }
                                    return label;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            stacked: false,
                            grid: { color: gridColor }
                        },
                        y: { // Left Y-axis for Submission Rate
                            type: 'linear',
                            display: true,
                            position: 'left',
                            min: 0,
                            max: 100,
                            title: { display: true, text: 'Submission Rate (%)' },
                            grid: { color: gridColor }
                        },
                        y1: { // Right Y-axis for Late Count
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: { display: true, text: 'Late Submissions (Count)' },
                            grid: { drawOnChartArea: false }, // only want the grid lines for one axis to avoid clutter
                            ticks: { precision: 0 }
                        }
                    }
                }
            });
        } else if (submissionCtx) {
            const chartContainer = submissionCtx.canvas.parentElement;
            chartContainer.innerHTML = '<div class="text-center text-muted p-4">No submission data available to generate analytics for the current filters.</div>';
        }
    }

    initializeSubmissionChart();

    const themeSwitch = document.getElementById('themeSwitch');
    if (themeSwitch) {
        themeSwitch.addEventListener('change', () => setTimeout(initializeSubmissionChart, 50));
    }
});
</script>
<?php
