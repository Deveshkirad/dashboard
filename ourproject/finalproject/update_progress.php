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
$unitsCompleted = isset($_POST['units_completed']) ? (int)$_POST['units_completed'] : -1;

if ($teacherId <= 0 || $unitsCompleted < 0) {
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => 'Missing or invalid teacher ID or units completed.']);
    exit();
}

// Prepare and execute the update statement
$stmt = $conn->prepare("UPDATE teachers SET units_completed = ? WHERE id = ?");
if (!$stmt) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['status' => 'error', 'message' => 'Database statement preparation failed.']);
    exit();
}

$stmt->bind_param("ii", $unitsCompleted, $teacherId);

if ($stmt->execute()) {
    // Check if any row was actually updated
    if ($stmt->affected_rows > 0) {
        echo json_encode(['status' => 'success', 'message' => 'Course progression updated successfully.']);
    } else {
        echo json_encode(['status' => 'success', 'message' => 'No change detected or teacher not found.']);
    }
} else {
    http_response_code(500); // Internal Server Error
    echo json_encode(['status' => 'error', 'message' => 'Database update failed.']);
}

$stmt->close();
$conn->close();
?>