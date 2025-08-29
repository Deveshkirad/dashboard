<?php
require_once 'db.php';

// Set header to JSON for all responses
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

$batch_id = isset($_POST['batch_id']) ? (int)$_POST['batch_id'] : 0;
$day_of_week = isset($_POST['day_of_week']) ? (int)$_POST['day_of_week'] : 0;
$period_number = isset($_POST['period_number']) ? (int)$_POST['period_number'] : 0;

if ($batch_id <= 0 || $day_of_week <= 0 || $period_number <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid slot identifiers provided.']);
    exit;
}

// Handle clearing a slot
if (isset($_POST['clear']) && $_POST['clear'] === 'true') {
    $stmt = $conn->prepare("DELETE FROM timetable_slots WHERE batch_id = ? AND day_of_week = ? AND period_number = ?");
    $stmt->bind_param("iii", $batch_id, $day_of_week, $period_number);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Slot cleared successfully.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to clear slot: ' . $stmt->error]);
    }
    $stmt->close();
    $conn->close();
    exit;
}

// Handle saving/updating a slot
$subject_id = isset($_POST['subject_id']) ? (int)$_POST['subject_id'] : 0;
$teacher_id = isset($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : 0;

if ($subject_id <= 0 || $teacher_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Subject and Teacher are required.']);
    exit;
}

// Use INSERT ... ON DUPLICATE KEY UPDATE for efficiency. This query will either insert a new row
// or update the existing row if a slot with the same batch, day, and period already exists.
$sql = "INSERT INTO timetable_slots (batch_id, day_of_week, period_number, subject_id, teacher_id)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE subject_id = VALUES(subject_id), teacher_id = VALUES(teacher_id)";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iiiii", $batch_id, $day_of_week, $period_number, $subject_id, $teacher_id);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Timetable slot saved successfully.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to save slot: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>