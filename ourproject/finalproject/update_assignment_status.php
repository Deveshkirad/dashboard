<?php
require_once 'db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

$assignmentId = isset($_POST['assignment_id']) ? (int)$_POST['assignment_id'] : 0;
$newStatus = isset($_POST['new_status']) ? $_POST['new_status'] : '';

$allowed_statuses = ['pending', 'graded'];
if ($assignmentId <= 0 || !in_array($newStatus, $allowed_statuses)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid assignment ID or status provided.']);
    exit;
}

$stmt = $conn->prepare("UPDATE assignments SET status = ? WHERE id = ?");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database statement preparation failed.']);
    exit;
}

$stmt->bind_param("si", $newStatus, $assignmentId);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode(['status' => 'success', 'message' => 'Assignment status updated successfully.']);
    } else {
        echo json_encode(['status' => 'success', 'message' => 'Assignment status was already set to this value.']);
    }
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database update failed.']);
}

$stmt->close();
$conn->close();
?>