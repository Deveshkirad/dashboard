<?php
require_once 'db.php';

// --- 1. Get and validate the batch ID ---
$batchId = isset($_GET['batch_id']) ? (int)$_GET['batch_id'] : 0;

if ($batchId <= 0) {
    // Redirect or show an error if the batch ID is invalid
    header("Location: students.php?error=invalid_batch");
    exit();
}

// --- 2. Fetch batch name for the filename ---
$batchNameQuery = $conn->prepare("SELECT batch_name FROM batches WHERE id = ?");
$batchNameQuery->bind_param("i", $batchId);
$batchNameQuery->execute();
$batchResult = $batchNameQuery->get_result();
if ($batchResult->num_rows === 0) {
    header("Location: students.php?error=batch_not_found");
    exit();
}
$batchName = $batchResult->fetch_assoc()['batch_name'];
$filename = "marks_overview_" . str_replace(' ', '_', $batchName) . "_" . date('Y-m-d') . ".csv";

// --- 3. Fetch data (similar logic to students.php) ---

// a. Get all subjects to form the header
$subjectsResultDb = $conn->query("SELECT name FROM subjects ORDER BY name");
$subjectsList = [];
if ($subjectsResultDb) {
    while ($row = $subjectsResultDb->fetch_assoc()) {
        $subjectsList[] = $row['name'];
    }
}

// b. Fetch and pivot marks data
$marksQuery = "
    SELECT s.name, sm.subject, sm.sgpa
    FROM student_marks sm
    JOIN students s ON sm.student_id = s.id
    WHERE s.batch_id = ?
    ORDER BY s.name, sm.subject
";
$stmt_marks = $conn->prepare($marksQuery);
$stmt_marks->bind_param("i", $batchId);
$stmt_marks->execute();
$marksResult = $stmt_marks->get_result();

$pivotMarks = [];
while ($row = $marksResult->fetch_assoc()) {
    $pivotMarks[$row['name']][$row['subject']] = $row['sgpa'];
}

// --- 4. Set headers for CSV download ---
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// --- 5. Write data to CSV ---
$output = fopen('php://output', 'w');

// Write header row
$header = array_merge(['Student Name'], $subjectsList, ['SGPA']);
fputcsv($output, $header);

// Write data rows
if (!empty($pivotMarks)) {
    foreach ($pivotMarks as $studentName => $marks) {
        $row = [$studentName];
        $totalSgpa = 0;
        $subjectCount = 0;

        foreach ($subjectsList as $subject) {
            $mark = isset($marks[$subject]) ? $marks[$subject] : 'N/A';
            $row[] = $mark;
            if (is_numeric($mark)) {
                $totalSgpa += $mark;
                $subjectCount++;
            }
        }
        
        $avgSgpa = ($subjectCount > 0) ? round($totalSgpa / $subjectCount, 2) : 'N/A';
        $row[] = $avgSgpa;
        
        fputcsv($output, $row);
    }
}

fclose($output);
$conn->close();
exit();