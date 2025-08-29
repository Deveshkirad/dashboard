<?php
require_once 'db.php';

// --- 1. FETCH DATA FOR FILTERING ---
$batchesResult = $conn->query("SELECT id, batch_name FROM batches ORDER BY batch_name");

// --- 2. GET SELECTED FILTERS ---
$selectedBatchId = isset($_GET['batch_id']) ? (int)$_GET['batch_id'] : 0;
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';

// --- Fetch All Subjects from DB for the marks table header ---
$subjectsResultDb = $conn->query("SELECT name FROM subjects ORDER BY name");
$subjectsList = [];
if ($subjectsResultDb) {
    while ($row = $subjectsResultDb->fetch_assoc()) {
        $subjectsList[] = $row['name'];
    }
}

// --- 3. BUILD AND EXECUTE STUDENT LIST QUERY ---
$studentsQuery = "
    SELECT 
        s.id, s.name, s.univ_roll_no, s.year, s.parent_contact, b.batch_name 
    FROM students s
    JOIN batches b ON s.batch_id = b.id
    WHERE 1=1
";
$params = [];
$types = '';

if ($selectedBatchId > 0) {
    $studentsQuery .= " AND s.batch_id = ?";
    $params[] = $selectedBatchId;
    $types .= 'i';
}

if (!empty($searchQuery)) {
    $studentsQuery .= " AND (s.name LIKE ? OR s.univ_roll_no LIKE ?)";
    $searchTerm = "%{$searchQuery}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'ss';
}
$studentsQuery .= " ORDER BY s.name";

$stmt_students = $conn->prepare($studentsQuery);
if (!empty($types)) {
    $stmt_students->bind_param($types, ...$params);
}
$stmt_students->execute();
$studentsResult = $stmt_students->get_result();

// --- 4. FETCH PERFORMANCE DATA IF A BATCH IS SELECTED ---
$marksData = [];
$avgMarksData = ['labels' => [], 'data' => []];
$pivotMarks = [];

if ($selectedBatchId > 0) {
    // Fetch Marks Data and pivot it
    $marksQuery = "
        SELECT s.name, sm.subject, sm.sgpa
        FROM student_marks sm
        JOIN students s ON sm.student_id = s.id
        WHERE s.batch_id = ?
        ORDER BY s.name, sm.subject
    ";
    $stmt_marks = $conn->prepare($marksQuery);
    $stmt_marks->bind_param("i", $selectedBatchId);
    $stmt_marks->execute();
    $marksResult = $stmt_marks->get_result();
    while ($row = $marksResult->fetch_assoc()) {
        $pivotMarks[$row['name']][$row['subject']] = $row['sgpa'];
    }

    // Fetch Data for Average Marks Chart
    $avgMarksQuery = "
        SELECT sm.subject, AVG(sm.sgpa) as average_sgpa
        FROM student_marks sm
        JOIN students s ON sm.student_id = s.id
        WHERE s.batch_id = ?
        GROUP BY sm.subject
        ORDER BY sm.subject
    ";
    $stmt_avg_marks = $conn->prepare($avgMarksQuery);
    $stmt_avg_marks->bind_param("i", $selectedBatchId);
    $stmt_avg_marks->execute();
    $avgMarksResult = $stmt_avg_marks->get_result();
    while ($row = $avgMarksResult->fetch_assoc()) {
        $avgMarksData['labels'][] = $row['subject'];
        $avgMarksData['data'][] = round($row['average_sgpa'], 2);
    }
}

$pageTitle = 'Students - B.Tech Admin Dashboard';
$pageStylesheets = ['assest/css/students.css']; // Assuming this file exists or will be created
require_once 'header.php';
?>
<h2 class="mb-4">Student List & Performance</h2>

<!-- Search & Filter -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fa fa-filter"></i> Filter Data
    </div>
    <div class="card-body">
        <form action="students.php" method="GET" class="row g-3 align-items-end">
            <div class="col-lg-5 col-md-6">
                <label for="search" class="form-label">Search by Name or Roll No.</label>
                <input type="search" class="form-control" name="search" id="search" placeholder="Enter name or roll number..." value="<?php echo htmlspecialchars($searchQuery); ?>">
            </div>
            <div class="col-lg-5 col-md-6">
                <label for="batch_id" class="form-label">Filter by Batch</label>
                <select class="form-select" name="batch_id" id="batch_id">
                    <option value="">-- Select a Batch --</option>
                    <?php
                    if ($batchesResult && $batchesResult->num_rows > 0) {
                        // Reset result set pointer to loop through it again
                        $batchesResult->data_seek(0);
                        while ($batch = $batchesResult->fetch_assoc()) {
                            $selected = ($batch['id'] == $selectedBatchId) ? 'selected' : '';
                            echo "<option value='{$batch['id']}' {$selected}>" . htmlspecialchars($batch['batch_name']) . "</option>";
                        }
                    }
                    ?>
                </select>
            </div>
            <div class="col-lg-2 col-md-12">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
        </form>
    </div>
</div>

<!-- Student List & Profile -->
<div class="card mb-4">
    <div class="card-header"><i class="fa fa-users"></i> Student List</div>
    <div class="card-body px-4" style="max-height: 600px; overflow-y: auto;">
        <div class="table-responsive">
            <table class="table table-bordered align-middle">
                <thead>
                    <tr>
                        <th>Photo</th>
                        <th>Name</th>
                        <th>Univ. Roll No.</th>
                        <th>Year</th>
                        <th>Batch</th>
                        <th>Parent Contact</th>
                    </tr>
                </thead>
                <tbody id="studentListBody">
                    <?php
                    if ($studentsResult && $studentsResult->num_rows > 0) {
                        while ($student = $studentsResult->fetch_assoc()) {
                            // Generate a random user photo for demonstration
                            $gender = rand(0, 1) ? 'men' : 'women';
                            $photoId = rand(1, 99);
                            $photoUrl = "https://randomuser.me/api/portraits/{$gender}/{$photoId}.jpg";
                    ?>
                            <tr>
                                <td><img src="<?php echo $photoUrl; ?>" class="rounded-circle" width="40" height="40" alt="<?php echo htmlspecialchars($student['name']); ?>"></td>
                                <td><?php echo htmlspecialchars($student['name']); ?></td>
                                <td><?php echo htmlspecialchars($student['univ_roll_no']); ?></td>
                                <td><?php echo htmlspecialchars($student['year']); ?>nd Year</td>
                                <td><?php echo htmlspecialchars($student['batch_name']); ?></td>
                                <td><?php echo htmlspecialchars($student['parent_contact']); ?></td>
                            </tr>
                    <?php
                        } // end while
                    } else {
                        $message = "Use the filters above to find students.";
                        if ($selectedBatchId > 0 || !empty($searchQuery)) {
                            $message = "No students found matching your criteria.";
                        }
                        echo "<tr><td colspan='6' class='text-center'>{$message}</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Marks & Grades Overview -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fa fa-chart-bar"></i> Previous Semester Marks (Semester 2)</span>
        <?php if ($selectedBatchId > 0) : ?>
            <a href="export_marks.php?batch_id=<?php echo $selectedBatchId; ?>" class="btn btn-secondary">
                <i class="fa fa-download me-1"></i> Export to CSV
            </a>
        <?php endif; ?>
    </div>
    <div class="card-body" style="max-height: 500px; overflow-y: auto;">
        <div class="table-responsive">
            <table class="table table-bordered table-striped align-middle">
                <thead>
                    <tr>
                        <th>Student Name</th>
                        <?php foreach ($subjectsList as $subject) : ?>
                            <th><?php echo $subject; ?></th>
                        <?php endforeach; ?>
                        <th>SGPA</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (!empty($pivotMarks)) {
                        foreach ($pivotMarks as $studentName => $marks) {
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($studentName) . "</td>";

                            $totalSgpa = 0;
                            $subjectCount = 0;
                            foreach ($subjectsList as $subject) {
                                $mark = isset($marks[$subject]) ? $marks[$subject] : 'N/A';
                                echo "<td>" . htmlspecialchars($mark) . "</td>";
                                if (is_numeric($mark)) {
                                    $totalSgpa += $mark;
                                    $subjectCount++;
                                }
                            }
                            $avgSgpa = ($subjectCount > 0) ? round($totalSgpa / $subjectCount, 2) : 'N/A';
                            echo "<td><strong>" . htmlspecialchars($avgSgpa) . "</strong></td>";
                            echo "</tr>";
                        }
                    } else {
                        $colspan = count($subjectsList) + 2;
                        echo "<tr><td colspan='{$colspan}' class='text-center'>Select a batch to view marks.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<!-- Performance & Marks Charts -->
<div class="row">
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <i class="fa fa-chart-line"></i> Performance Trends
            </div>
            <div class="card-body">
                <canvas id="performanceTrendChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <i class="fa fa-chart-bar"></i> Average Marks by Subject
            </div>
            <div class="card-body">
                <canvas id="avgMarksChart"></canvas>
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
    // Data passed from PHP
    const avgMarksData = <?php echo json_encode($avgMarksData); ?>;

    let trendChartInstance = null;
    let avgMarksChartInstance = null;

    function initializeCharts() {
        if (trendChartInstance) trendChartInstance.destroy();
        if (avgMarksChartInstance) avgMarksChartInstance.destroy();

        const isDarkMode = document.documentElement.classList.contains('dark-mode');
        const gridColor = isDarkMode ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)';
        const fontColor = isDarkMode ? '#c7c7c7' : '#5a5c69';
        Chart.defaults.color = fontColor;
        Chart.defaults.font.family = "'Poppins', sans-serif";

        // For demonstration, performance trend data is static.
        const performanceData = {
            labels: ['Sem 1', 'Sem 2', 'Sem 3 (Mid)'],
            data: [7.8, 8.2, 8.5] // Example data
        };

        // Only initialize charts if there is data to display
        if (avgMarksData.labels.length > 0) {
            // Performance trend chart
            const trendCtx = document.getElementById('performanceTrendChart')?.getContext('2d');
            if (trendCtx) {
                trendChartInstance = new Chart(trendCtx, {
                    type: 'line',
                    data: {
                        labels: performanceData.labels,
                        datasets: [{
                            label: 'Average SGPA Trend',
                            data: performanceData.data,
                            borderColor: 'rgba(106, 17, 203, 1)',
                            backgroundColor: 'rgba(106, 17, 203, 0.1)',
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: { y: { max: 10, beginAtZero: true, grid: { color: gridColor } }, x: { grid: { color: gridColor } } }
                    }
                });
            }

            // Average Marks by Subject Chart
            const avgMarksCtx = document.getElementById('avgMarksChart')?.getContext('2d');
            if (avgMarksCtx) {
                avgMarksChartInstance = new Chart(avgMarksCtx, {
                    type: 'bar',
                    data: {
                        labels: avgMarksData.labels,
                        datasets: [{
                            label: 'Average Marks (SGPA)',
                            data: avgMarksData.data,
                            backgroundColor: ['rgba(106, 17, 203, 0.7)', 'rgba(37, 117, 252, 0.7)', 'rgba(52, 232, 158, 0.7)', 'rgba(255, 193, 7, 0.7)'],
                            borderColor: ['rgba(106, 17, 203, 1)', 'rgba(37, 117, 252, 1)', 'rgba(52, 232, 158, 1)', 'rgba(255, 193, 7, 1)'],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: { legend: { display: false } },
                        scales: { y: { beginAtZero: true, max: 10, grid: { color: gridColor } }, x: { grid: { color: gridColor } } }
                    }
                });
            }
        }
    }

    initializeCharts();

    // Re-initialize charts when theme is switched
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