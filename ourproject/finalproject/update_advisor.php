<?php
require_once 'db.php';

// Set content type to JSON
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit();
}

$batchId = $_POST['batch_id'] ?? 0;
$teacherId = $_POST['teacher_id'] ?? 0;

if (empty($batchId) || empty($teacherId)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing batch or teacher ID.']);
    exit();
}

$stmt = $conn->prepare("UPDATE batches SET faculty_advisor_id = ? WHERE id = ?");
$stmt->bind_param("ii", $teacherId, $batchId);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Faculty advisor updated successfully.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Database update failed.']);
}

$stmt->close();
$conn->close();
?>