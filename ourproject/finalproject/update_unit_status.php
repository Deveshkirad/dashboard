<?php
require_once 'db.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit();
}

// Get and validate input
$teacherId = isset($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : 0;
$unitId = isset($_POST['unit_id']) ? (int)$_POST['unit_id'] : 0;
$isCompleted = isset($_POST['is_completed']) ? filter_var($_POST['is_completed'], FILTER_VALIDATE_BOOLEAN) : null;
$batchId = isset($_POST['batch_id']) ? (int)$_POST['batch_id'] : 0;

if ($teacherId <= 0 || $unitId <= 0 || $isCompleted === null || $batchId <= 0) {
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => 'Missing or invalid parameters. A batch must be selected.']);
    exit();
}

if ($isCompleted) {
    // Add the unit to the completed list. INSERT IGNORE prevents errors on duplicates.
    $stmt = $conn->prepare("INSERT IGNORE INTO teacher_completed_units (teacher_id, unit_id, batch_id) VALUES (?, ?, ?)");
    $message = 'Unit marked as complete.';
} else {
    // Remove the unit from the completed list.
    $stmt = $conn->prepare("DELETE FROM teacher_completed_units WHERE teacher_id = ? AND unit_id = ? AND batch_id = ?");
    $message = 'Unit marked as incomplete.';
}

if (!$stmt) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['status' => 'error', 'message' => 'Database statement preparation failed.']);
    exit();
}

$stmt->bind_param("iii", $teacherId, $unitId, $batchId);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => $message]);
} else {
    http_response_code(500); // Internal Server Error
    echo json_encode(['status' => 'error', 'message' => 'Database update failed.']);
}

$stmt->close();
$conn->close();
?>