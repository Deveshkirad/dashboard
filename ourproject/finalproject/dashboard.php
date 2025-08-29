<?php
require_once 'db.php';

/**
 * Converts a datetime string into a "time ago" format.
 * e.g., "1 hour ago", "2 days ago", "just now"
 * @param string $datetime The input datetime string (e.g., from the database).
 * @param bool $full Whether to return a full-length description.
 * @return string The formatted time difference.
 */
function time_ago($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = ['y' => 'year', 'm' => 'month', 'w' => 'week', 'd' => 'day', 'h' => 'hour', 'i' => 'minute', 's' => 'second'];
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}

// --- Refactored: Fetch all summary counts in a single query for efficiency ---
$summaryQuery = "
    SELECT
        (SELECT COUNT(id) FROM teachers) as teachers_count,
        (SELECT COUNT(id) FROM batches) as batches_count,
        (SELECT COUNT(id) FROM students) as students_count,
        (SELECT COUNT(id) FROM assignments) as assignments_count
";
$summaryResult = $conn->query($summaryQuery);
$summaryCounts = $summaryResult->fetch_assoc();
$teachersCount = $summaryCounts['teachers_count'] ?? 0;
$classesCount = $summaryCounts['batches_count'] ?? 0;
$studentsCount = $summaryCounts['students_count'] ?? 0;
$assignmentsCount = $summaryCounts['assignments_count'] ?? 0;

// --- Fetch data for charts ---

// 1. Faculty by Subject (Pie Chart)
$facultyBySubjectQuery = "SELECT subject, COUNT(id) as count FROM teachers GROUP BY subject";
$facultyBySubjectResult = $conn->query($facultyBySubjectQuery);
$facultyData = ['labels' => [], 'data' => []];
while ($row = $facultyBySubjectResult->fetch_assoc()) {
    $facultyData['labels'][] = $row['subject'];
    $facultyData['data'][] = $row['count'];
}
$facultyDataJson = json_encode($facultyData);

// 2. Monthly Student Enrollment (Line Chart)
$enrollmentQuery = "SELECT DATE_FORMAT(enrollment_date, '%b %Y') as month, COUNT(id) as count 
                    FROM students 
                    GROUP BY DATE_FORMAT(enrollment_date, '%Y-%m') 
                    ORDER BY DATE_FORMAT(enrollment_date, '%Y-%m')";
$enrollmentResult = $conn->query($enrollmentQuery);
$enrollmentData = ['labels' => [], 'data' => []];
while ($row = $enrollmentResult->fetch_assoc()) {
    $enrollmentData['labels'][] = $row['month'];
    $enrollmentData['data'][] = $row['count'];
}
$enrollmentDataJson = json_encode($enrollmentData);

// --- Fetch data for Recent Activity ---
$activityQuery = "
    (
        SELECT 
            'unit_completed' as type,
            tcu.completed_at as activity_date,
            t.name as actor,
            CONCAT('Unit ', su.unit_number, ': ', su.unit_name) as item,
            CONCAT(su.subject_name, ' for ', b.batch_name) as context
        FROM teacher_completed_units tcu
        JOIN teachers t ON tcu.teacher_id = t.id
        JOIN subject_units su ON tcu.unit_id = su.id
        JOIN batches b ON tcu.batch_id = b.id
    )
    UNION ALL
    (
        SELECT
            'student_enrolled' as type,
            s.enrollment_date as activity_date,
            s.name as actor,
            b.batch_name as item,
            NULL as context
        FROM students s
        JOIN batches b ON s.batch_id = b.id
    )
    UNION ALL
    (
        SELECT
            'assignment_created' as type,
            a.created_at as activity_date, -- Assumes 'created_at' column with a timestamp exists
            'Admin' as actor,
            a.title as item,
            b.batch_name as context
        FROM assignments a
        JOIN batches b ON a.batch_id = b.id
    )
    ORDER BY activity_date DESC
    LIMIT 5
";
$activityResult = $conn->query($activityQuery);
$recentActivities = $activityResult ? $activityResult->fetch_all(MYSQLI_ASSOC) : [];

$conn->close();

$pageTitle = 'B.Tech Semester 3 Dashboard';
$pageStylesheets = ['assest/css/dashboard.css'];
require_once 'header.php';
?>

<h2 class="mb-4">B.Tech Semester 3 Dashboard</h2>

<?php if ($studentsCount === 0): ?>
<div class="alert alert-info" role="alert">
    <h4 class="alert-heading"><i class="fas fa-info-circle"></i> Welcome to the Dashboard!</h4>
    <p>It looks like there are no students in the system yet. To see the dashboard with sample data, you need to run the data generation script.</p>
    <hr>
    <p class="mb-0">Please visit the following link in your browser to populate the database: 
        <a href="generate_data.php" class="alert-link" target="_blank" rel="noopener noreferrer">Run Data Generation Script</a>
    </p>
    <p class="small mt-2">After running the script, refresh this page. You can delete the `generate_data.php` file after you've run it once successfully.</p>
</div>
<?php endif; ?>

<div class="row">
    <!-- Faculty Card -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stat-card h-100">
            <div class="card-body">
                <div class="stat-card-icon bg-primary">
                    <i class="fas fa-chalkboard-teacher"></i>
                </div>
                <div class="stat-card-content">
                    <div class="stat-card-title">Faculty</div>
                    <div class="stat-card-value"><?php echo $teachersCount; ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Batches Card -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stat-card h-100">
            <div class="card-body">
                <div class="stat-card-icon bg-success">
                    <i class="fas fa-sitemap"></i>
                </div>
                <div class="stat-card-content">
                    <div class="stat-card-title">Batches</div>
                    <div class="stat-card-value"><?php echo $classesCount; ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Students Card -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stat-card h-100">
            <div class="card-body">
                <div class="stat-card-icon bg-info">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div class="stat-card-content">
                    <div class="stat-card-title">Students</div>
                    <div class="stat-card-value"><?php echo $studentsCount; ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Assignments Card -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stat-card h-100">
            <div class="card-body">
                <div class="stat-card-icon bg-warning">
                    <i class="fas fa-tasks"></i>
                </div>
                <div class="stat-card-content">
                    <div class="stat-card-title">Assignments</div>
                    <div class="stat-card-value"><?php echo $assignmentsCount; ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Section -->
<div class="row">
    <div class="col-xl-7 mb-4">
        <div class="card shadow h-100">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Monthly Student Enrollment</h6>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <?php if (!empty($enrollmentData['data'])): ?>
                        <canvas id="enrollChart"></canvas>
                    <?php else: ?>
                        <div class="text-center text-muted d-flex align-items-center justify-content-center h-100">No enrollment data to display.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-5 mb-4">
        <div class="card shadow h-100">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Faculty by Subject</h6>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <?php if (!empty($facultyData['data'])): ?>
                        <canvas id="deptChart"></canvas>
                    <?php else: ?>
                        <div class="text-center text-muted d-flex align-items-center justify-content-center h-100">No faculty data to display.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions & Recent Activity -->
<div class="row">
    <!-- Quick Actions -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow">
            <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6></div>
            <div class="card-body">
                <a href="students.php" class="btn btn-light btn-icon-split mb-2"><span class="icon"><i class="fas fa-users-cog"></i></span><span class="text">Manage Students</span></a>
                <a href="assignments.php" class="btn btn-light btn-icon-split mb-2"><span class="icon"><i class="fas fa-plus-circle"></i></span><span class="text">Create Assignment</span></a>
                <a href="attendance.php" class="btn btn-light btn-icon-split mb-2"><span class="icon"><i class="fas fa-upload"></i></span><span class="text">Upload Attendance</span></a>
            </div>
        </div>
    </div>
    <!-- Recent Activity -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow">
            <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Recent Activity</h6></div>
            <div class="card-body">
                <div class="activity-feed">
                    <?php if (!empty($recentActivities)): ?>
                        <?php foreach ($recentActivities as $activity): ?>
                            <?php
                            $icon = 'fas fa-bell';
                            $icon_bg = 'bg-secondary';
                            $text = 'An activity occurred.';

                            switch ($activity['type']) {
                                case 'unit_completed':
                                    $icon = 'fas fa-check-circle';
                                    $icon_bg = 'bg-success';
                                    $text = "<strong>" . htmlspecialchars($activity['actor']) . "</strong> completed <strong>" . htmlspecialchars($activity['item']) . "</strong> in " . htmlspecialchars($activity['context']) . ".";
                                    break;
                                case 'student_enrolled':
                                    $icon = 'fas fa-user-plus';
                                    $icon_bg = 'bg-info';
                                    $text = "<strong>" . htmlspecialchars($activity['actor']) . "</strong> was enrolled in <strong>" . htmlspecialchars($activity['item']) . "</strong>.";
                                    break;
                                case 'assignment_created':
                                    $icon = 'fas fa-file-alt';
                                    $icon_bg = 'bg-warning';
                                    $text = "A new assignment <strong>" . htmlspecialchars($activity['item']) . "</strong> was created for <strong>" . htmlspecialchars($activity['context']) . "</strong>.";
                                    break;
                            }
                            ?>
                            <div class="activity-item d-flex mb-3">
                                <div class="activity-icon me-3">
                                    <div class="icon-circle <?php echo $icon_bg; ?>">
                                        <i class="<?php echo $icon; ?> text-white"></i>
                                    </div>
                                </div>
                                <div class="activity-content">
                                    <div class="small text-muted"><?php echo time_ago($activity['activity_date']); ?></div>
                                    <div><?php echo $text; ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted text-center">No recent activity to display.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Capture page-specific JavaScript into a variable
ob_start();
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Data from PHP
    const facultyData = <?php echo $facultyDataJson; ?>;
    const enrollmentData = <?php echo $enrollmentDataJson; ?>;

    let enrollChartInstance = null;
    let deptChartInstance = null;

    function initializeCharts() {
        if (enrollChartInstance) enrollChartInstance.destroy();
        if (deptChartInstance) deptChartInstance.destroy();

        const isDarkMode = document.documentElement.classList.contains('dark-mode');
        const gridColor = isDarkMode ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)';
        const fontColor = isDarkMode ? '#c7c7c7' : '#5a5c69';
        Chart.defaults.color = fontColor;
        Chart.defaults.font.family = "'Poppins', sans-serif";

        // Enrollment Chart (Line)
        if (document.getElementById('enrollChart') && enrollmentData.labels.length > 0) {
            const enrollCtx = document.getElementById('enrollChart').getContext('2d');
            enrollChartInstance = new Chart(enrollCtx, {
                type: 'line',
                data: { labels: enrollmentData.labels, datasets: [{ label: 'Enrollments', data: enrollmentData.data, borderColor: '#2575fc', backgroundColor: 'rgba(37, 117, 252, 0.1)', fill: true, tension: 0.3 }] },
                options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: gridColor } }, x: { grid: { display: false } } }, plugins: { legend: { display: false } } }
            });
        }

        // Faculty by Subject Chart (Doughnut)
        if (document.getElementById('deptChart') && facultyData.labels.length > 0) {
            const deptCtx = document.getElementById('deptChart').getContext('2d');
            deptChartInstance = new Chart(deptCtx, {
                type: 'doughnut',
                data: { labels: facultyData.labels, datasets: [{ data: facultyData.data, backgroundColor: ['#6a11cb', '#2575fc', '#34e89e', '#ffc107', '#dc3545', '#6f42c1'], hoverOffset: 4 }] },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, padding: 15 } } } }
            });
        }
    }

    initializeCharts();

    const themeSwitch = document.getElementById('themeSwitch');
    if (themeSwitch) {
        themeSwitch.addEventListener('change', () => setTimeout(initializeCharts, 50));
    }
});
</script>
<?php
$pageScriptBlock = ob_get_clean();
require_once 'footer.php';
?>