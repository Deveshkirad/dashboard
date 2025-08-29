<?php
require_once 'db.php';

// Use statements must be in the global scope, at the top of the file.
use Dompdf\Dompdf;
use Dompdf\Options;

// --- 1. Get and validate input parameters ---
$batchId = isset($_GET['batch_id']) ? (int)$_GET['batch_id'] : 0;
$monthYear = isset($_GET['month']) ? $_GET['month'] : '';
$format = isset($_GET['format']) ? strtolower($_GET['format']) : 'csv'; // Get format

if ($batchId <= 0 || empty($monthYear) || !preg_match('/^\d{4}-\d{2}$/', $monthYear) || !in_array($format, ['csv', 'pdf'])) {
    header("Location: attendance.php?error=invalid_params");
    exit();
}

// --- 2. Fetch batch name for the filename ---
$batchNameQuery = $conn->prepare("SELECT batch_name FROM batches WHERE id = ?");
$batchNameQuery->bind_param("i", $batchId);
$batchNameQuery->execute();
$batchResult = $batchNameQuery->get_result();
if ($batchResult->num_rows === 0) {
    header("Location: attendance.php?error=batch_not_found");
    exit();
}
$batchName = $batchResult->fetch_assoc()['batch_name'];
// Dynamic filename based on format
$filename = "attendance_" . str_replace(' ', '_', $batchName) . "_" . $monthYear . "." . $format;

// --- 3. Fetch data (similar logic to attendance.php) ---

// a. Get all subjects to form the header
$subjectsResultDb = $conn->query("SELECT name FROM subjects ORDER BY name");
$subjectsList = [];
if ($subjectsResultDb) {
    while ($row = $subjectsResultDb->fetch_assoc()) {
        $subjectsList[] = $row['name'];
    }
}

// b. Fetch and pivot attendance data
list($year, $month) = explode('-', $monthYear);
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
$stmt_attendance->bind_param("iii", $year, $month, $batchId);
$stmt_attendance->execute();
$attendanceResult = $stmt_attendance->get_result();

$pivotAttendance = [];
while ($row = $attendanceResult->fetch_assoc()) {
    if (!empty($row['subject'])) {
        $pivotAttendance[$row['name']][$row['subject']] = ['total' => $row['total_classes'], 'attended' => $row['attended_classes']];
    }
}

// --- 4. Generate and output the file based on the requested format ---

if ($format === 'pdf') {
    // --- PDF Generation Logic ---

    // --- Add dompdf autoloader only when needed ---
    $dompdfAutoload = __DIR__ . '/vendor/autoload.php';
    if (!file_exists($dompdfAutoload)) {
        // Redirect with an error if dompdf is not installed
        header("Location: attendance.php?error=" . urlencode("PDF generation library (dompdf) is not installed. Please run 'composer require dompdf/dompdf' in your project directory."));
        exit();
    }
    require_once $dompdfAutoload;
    $html = '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Attendance Report for ' . htmlspecialchars($batchName) . '</title>
        <style>
            body { font-family: "Helvetica", "Arial", sans-serif; color: #333; }
            h1 { text-align: center; }
            .report-meta { margin-bottom: 20px; text-align: center; }
            table { width: 100%; border-collapse: collapse; font-size: 10px; }
            th, td { border: 1px solid #ccc; padding: 5px; text-align: center; }
            th { background-color: #f2f2f2; font-weight: bold; }
            .student-name { text-align: left; }
            .total { font-weight: bold; }
            .table-success { background-color: #d4edda !important; }
            .table-warning { background-color: #fff3cd !important; }
            .table-danger  { background-color: #f8d7da !important; }
            .text-muted { color: #6c757d; }
            .d-block { display: block; font-size: 8px; }
        </style>
    </head>
    <body>
        <h1>Attendance Report</h1>
        <div class="report-meta">
            <strong>Batch:</strong> ' . htmlspecialchars($batchName) . '<br>
            <strong>Month:</strong> ' . date("F Y", strtotime($monthYear . "-01")) . '
        </div>
        <table>
            <thead>
                <tr>
                    <th class="student-name">Student Name</th>';
    foreach ($subjectsList as $subject) {
        $html .= '<th>' . htmlspecialchars($subject) . '</th>';
    }
    $html .= '<th>Total Attendance</th></tr></thead><tbody>';

    if (!empty($pivotAttendance)) {
        foreach ($pivotAttendance as $studentName => $subjectAttendance) {
            $html .= '<tr><td class="student-name">' . htmlspecialchars($studentName) . '</td>';
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

                    $html .= "<td class='{$cellClass}'>{$record['attended']} / {$record['total']}<span class='d-block text-muted'>({$percentage}%)</span></td>";
                } else {
                    $html .= '<td>N/A</td>';
                }
            }

            $totalCellClass = '';
            if ($overallTotal > 0) {
                $totalPercentage = round(($overallAttended / $overallTotal) * 100);
                if ($totalPercentage >= 75) $totalCellClass = 'table-success';
                elseif ($totalPercentage >= 50) $totalCellClass = 'table-warning';
                else $totalCellClass = 'table-danger';
                $html .= "<td class='total {$totalCellClass}'>{$overallAttended} / {$overallTotal}<span class='d-block text-muted'>({$totalPercentage}%)</span></td>";
            } else {
                $html .= "<td class='total'>N/A</td>";
            }
            $html .= '</tr>';
        }
    } else {
        $colspan = count($subjectsList) + 2;
        $html .= "<tr><td colspan='{$colspan}' style='text-align:center;'>No attendance data found for the selected criteria.</td></tr>";
    }

    $html .= '</tbody></table></body></html>';

    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();
    $dompdf->stream($filename, ["Attachment" => true]);

} else {
    // --- CSV Generation Logic (Default) ---
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');

    // Write header row
    $header = array_merge(['Student Name'], $subjectsList, ['Total Attendance']);
    fputcsv($output, $header);

    // Write data rows
    if (!empty($pivotAttendance)) {
        foreach ($pivotAttendance as $studentName => $subjectAttendance) {
            $row = [$studentName];
            $overallAttended = 0;
            $overallTotal = 0;

            foreach ($subjectsList as $subject) {
                if (isset($subjectAttendance[$subject])) {
                    $record = $subjectAttendance[$subject];
                    $overallAttended += $record['attended'];
                    $overallTotal += $record['total'];
                    $percentage = ($record['total'] > 0) ? round(($record['attended'] / $record['total']) * 100) : 0;
                    $row[] = "{$record['attended']} / {$record['total']} ({$percentage}%)";
                } else {
                    $row[] = 'N/A';
                }
            }

            $totalPercentage = ($overallTotal > 0) ? round(($overallAttended / $overallTotal) * 100) : 0;
            $row[] = "{$overallAttended} / {$overallTotal} ({$totalPercentage}%)";

            fputcsv($output, $row);
        }
    }

    fclose($output);
}

$conn->close();
exit();