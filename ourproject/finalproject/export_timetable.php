<?php
require_once 'db.php';

// --- 1. Get and validate the batch ID ---
$batchId = isset($_GET['batch_id']) ? (int)$_GET['batch_id'] : 0;

if ($batchId <= 0) {
    // A more user-friendly error would be a redirect, but for an API-like script, die() is okay.
    die("Invalid or missing Batch ID.");
}

// --- 2. Fetch batch name for the filename ---
$batchNameQuery = $conn->prepare("SELECT batch_name FROM batches WHERE id = ?");
$batchNameQuery->bind_param("i", $batchId);
$batchNameQuery->execute();
$batchResult = $batchNameQuery->get_result();
if ($batchResult->num_rows === 0) {
    die("Batch not found.");
}
$batchName = $batchResult->fetch_assoc()['batch_name'];
$filename = "timetable_" . str_replace(' ', '_', $batchName) . "_" . date('Y-m-d') . ".csv";

// --- 3. Fetch timetable data for the given batch ---
$sql = "SELECT 
            ts.day_of_week, 
            ts.period_number, 
            s.name as subject_name, 
            t.name as teacher_name
        FROM timetable_slots ts
        JOIN subjects s ON ts.subject_id = s.id
        JOIN teachers t ON ts.teacher_id = t.id
        WHERE ts.batch_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $batchId);
$stmt->execute();
$result = $stmt->get_result();
$slotsData = $result->fetch_all(MYSQLI_ASSOC);

// --- 4. Structure data into a grid for CSV export ---
$timeSlots = [
    1 => "09:00 - 10:00", 2 => "10:00 - 11:00", 3 => "11:00 - 12:00",
    4 => "12:00 - 01:00", 5 => "02:00 - 03:00", 6 => "03:00 - 04:00"
];
$daysOfWeek = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday'];

// Initialize an empty grid
$timetableGrid = [];
foreach ($timeSlots as $period => $time) {
    foreach ($daysOfWeek as $day => $dayName) {
        $timetableGrid[$period][$day] = '';
    }
}

// Populate the grid with fetched data
foreach ($slotsData as $slot) {
    $day = $slot['day_of_week'];
    $period = $slot['period_number'];
    if (isset($timetableGrid[$period][$day])) {
        // Format cell content with subject and teacher
        $timetableGrid[$period][$day] = $slot['subject_name'] . "\n(" . $slot['teacher_name'] . ")";
    }
}

// --- 5. Set headers and write data to CSV ---
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// Add a Byte Order Mark (BOM) for better Excel compatibility
fwrite($output, "\xEF\xBB\xBF");

// Write header row
fputcsv($output, array_merge(['Time'], $daysOfWeek));

// Write data rows
foreach ($timeSlots as $period => $time) {
    fputcsv($output, array_merge([$time], $timetableGrid[$period]));
}

fclose($output);
$conn->close();
exit();