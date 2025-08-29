<?php
require_once 'db.php';
set_time_limit(300); // Increase execution time for large files

/**
 * Redirects the user back to the students page with an error message.
 * @param string $message The error message to display.
 */
function redirect_with_error($message, $batchId = null, $subject = null, $date = null) {
    $url = 'attendance.php?upload_status=error&message=' . urlencode($message);
    if ($batchId) {
        $url .= '&batch_id=' . $batchId;
    }
    if ($subject) {
        $url .= '&subject_dw=' . urlencode($subject);
    }
    if ($date) {
        $url .= '&date_dw=' . urlencode($date);
    }
    header('Location: ' . $url);
    exit();
}

// --- 1. Basic Validation & Input Gathering ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: attendance.php');
    exit();
}

$batchId = isset($_POST['batch_id']) ? (int)$_POST['batch_id'] : 0;
$subject = isset($_POST['subject']) ? trim($_POST['subject']) : '';
$attendanceDate = isset($_POST['attendance_date']) ? trim($_POST['attendance_date']) : '';
$file = $_FILES['attendance_file'] ?? null;

// --- 2. Comprehensive Input Validation ---
if ($batchId <= 0 || empty($subject) || empty($attendanceDate) || $file === null) {
    redirect_with_error('Missing required fields. Please fill out all form fields.', $batchId, $subject, $attendanceDate);
}

if ($file['error'] !== UPLOAD_ERR_OK) {
    redirect_with_error('File upload error. Code: ' . $file['error'], $batchId, $subject, $attendanceDate);
}

// Validate file extension
$fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if ($fileExtension !== 'csv') {
    redirect_with_error('Invalid file type. Please upload a .csv file.', $batchId, $subject, $attendanceDate);
}

// --- 3. File Processing and Database Update ---
try {
    $conn->begin_transaction();

    // Get all student IDs and roll numbers for the selected batch for quick lookup
    $stmt = $conn->prepare("SELECT id, univ_roll_no FROM students WHERE batch_id = ?");
    $stmt->bind_param("i", $batchId);
    $stmt->execute();
    $result = $stmt->get_result();
    $studentMap = [];
    while ($row = $result->fetch_assoc()) {
        $studentMap[$row['univ_roll_no']] = $row['id'];
    }
    $stmt->close();

    if (empty($studentMap)) {
        throw new Exception("No students found for the selected batch.");
    }

    // Prepare statements for DB operations. This is an "upsert" (update/insert) logic.
    $stmtDelete = $conn->prepare("DELETE FROM attendance_records WHERE student_id = ? AND attendance_date = ? AND subject = ?");
    $stmtInsert = $conn->prepare("INSERT INTO attendance_records (student_id, attendance_date, subject, status) VALUES (?, ?, ?, ?)");

    $handle = fopen($file['tmp_name'], "r");
    if ($handle === false) {
        throw new Exception("Could not open the uploaded file.");
    }

    // Read header row and validate its format
    $header = fgetcsv($handle);
    if ($header === false || count($header) < 2 || strtolower(trim($header[0])) !== 'univ_roll_no' || strtolower(trim($header[1])) !== 'status') {
        throw new Exception("Invalid CSV header. Expected columns: 'univ_roll_no' and 'status'.");
    }

    $insertedCount = 0;
    $skippedCount = 0;

    // Process each data row
    while (($data = fgetcsv($handle)) !== false) {
        // Skip empty or malformed rows (e.g., no roll number)
        if (count($data) < 2 || empty(trim($data[0]))) {
            $skippedCount++;
            continue;
        }

        $univRollNo = trim($data[0]);
        $status = strtolower(trim($data[1]));

        // Skip if the roll number from the file doesn't exist in the selected batch
        if (!isset($studentMap[$univRollNo])) {
            $skippedCount++;
            continue;
        }

        // Skip if the status is not a valid value
        if (!in_array($status, ['present', 'absent'])) {
            $skippedCount++;
            continue;
        }

        $studentId = $studentMap[$univRollNo];

        // Delete any existing record for this student/date/subject to handle re-uploads
        $stmtDelete->bind_param("iss", $studentId, $attendanceDate, $subject);
        $stmtDelete->execute();

        // Insert the new record
        $stmtInsert->bind_param("isss", $studentId, $attendanceDate, $subject, $status);
        $stmtInsert->execute();
        $insertedCount++;
    }

    fclose($handle);
    $stmtDelete->close();
    $stmtInsert->close();

    $conn->commit();
    $redirectUrl = sprintf(
        'attendance.php?upload_status=success&inserted=%d&skipped=%d&batch_id=%d&month=%s&subject_dw=%s&date_dw=%s',
        $insertedCount,
        $skippedCount,
        $batchId,
        substr($attendanceDate, 0, 7),
        urlencode($subject),
        urlencode($attendanceDate)
    );
    header('Location: ' . $redirectUrl);
    exit();

} catch (Exception $e) {
    $conn->rollback();
    redirect_with_error($e->getMessage(), $batchId, $subject, $attendanceDate);
}