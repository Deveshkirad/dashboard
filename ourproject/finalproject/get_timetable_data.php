<?php
require_once 'db.php';

header('Content-Type: application/json');

if (!isset($_GET['batch_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Batch ID is required.']);
    exit;
}

$batch_id = (int)$_GET['batch_id'];

if ($batch_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Batch ID.']);
    exit;
}

$sql = "SELECT 
            ts.day_of_week, 
            ts.period_number, 
            ts.subject_id, 
            s.name as subject_name, 
            ts.teacher_id, 
            t.name as teacher_name
        FROM timetable_slots ts
        JOIN subjects s ON ts.subject_id = s.id
        JOIN teachers t ON ts.teacher_id = t.id
        WHERE ts.batch_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $batch_id);
$stmt->execute();
$result = $stmt->get_result();
$slots = $result->fetch_all(MYSQLI_ASSOC);

echo json_encode(['status' => 'success', 'slots' => $slots]);

$stmt->close();
$conn->close();
?>