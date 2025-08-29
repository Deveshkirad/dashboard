<?php
require_once 'db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit();
}

$teacherId = isset($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : 0;
$status = isset($_POST['status']) ? $_POST['status'] : '';

// Validate the received status to ensure it's one of the allowed values
$allowed_statuses = ['active', 'on_leave'];
if ($teacherId <= 0 || !in_array($status, $allowed_statuses)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid teacher ID or status provided.']);
    exit();
}

$stmt = $conn->prepare("UPDATE teachers SET status = ? WHERE id = ?");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database statement preparation failed.']);
    exit();
}

$stmt->bind_param("si", $status, $teacherId);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Faculty status updated successfully.']);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database update failed.']);
}

$stmt->close();
$conn->close();
?>