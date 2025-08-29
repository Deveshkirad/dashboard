<?php
require_once 'db.php';

// --- 1. GET AND VALIDATE PARAMETERS ---
$batchId = isset($_GET['batch_id']) ? (int)$_GET['batch_id'] : 0;
$subject = isset($_GET['subject']) ? trim($_GET['subject']) : '';
$date = isset($_GET['date']) ? trim($_GET['date']) : '';
$format = isset($_GET['format']) ? strtolower(trim($_GET['format'])) : 'csv';

if ($batchId <= 0 || empty($subject) || empty($date) || !in_array($format, ['csv', 'pdf'])) {
    die("Invalid export parameters. Please go back and try again.");
}

// --- PDF Export Placeholder ---
if ($format == 'pdf') {
    // Note: A library like FPDF or TCPDF is required for robust PDF generation.
    // The existing `export_attendance.php` for monthly reports likely contains the PDF generation logic.
    // That logic should be adapted here to complete this feature.
    header("Content-Type: text/plain");
    die("PDF export for daily attendance is not yet fully implemented. Please use the CSV export option for now.");
}

// --- 2. FETCH BATCH INFO FOR THE FILENAME ---
$batchStmt = $conn->prepare("SELECT batch_name FROM batches WHERE id = ?");
$batchStmt->bind_param("i", $batchId);
$batchStmt->execute();
$batchResult = $batchStmt->get_result();
$batchName = ($batchRow = $batchResult->fetch_assoc()) ? $batchRow['batch_name'] : 'UnknownBatch';

// --- 3. FETCH THE DAILY ATTENDANCE DATA ---
$query = "
    SELECT s.name, s.univ_roll_no, ar.status
    FROM attendance_records ar
    JOIN students s ON ar.student_id = s.id
    WHERE s.batch_id = ?
    AND ar.subject = ?
    AND ar.attendance_date = ?
    ORDER BY s.name
";
$stmt = $conn->prepare($query);
$stmt->bind_param("iss", $batchId, $subject, $date);
$stmt->execute();
$result = $stmt->get_result();

// --- 4. GENERATE THE CSV FILE ---
$filename = "daily_attendance_" . str_replace(' ', '_', $batchName) . "_" . str_replace(' ', '_', $subject) . "_" . $date . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// Add a Byte Order Mark (BOM) for better Excel compatibility with UTF-8 characters
fwrite($output, "\xEF\xBB\xBF");

// Add headers
fputcsv($output, ['Student Name', 'University Roll No.', 'Status']);

// Add data rows
if ($result->num_rows > 0) {
    while ($record = $result->fetch_assoc()) {
        fputcsv($output, [
            $record['name'],
            $record['univ_roll_no'],
            ucfirst($record['status'])
        ]);
    }
} else {
    fputcsv($output, ['No records found for the selected criteria.']);
}

fclose($output);
$conn->close();
exit();
?>