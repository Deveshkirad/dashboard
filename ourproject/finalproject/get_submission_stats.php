<?php
require_once 'db.php';

header('Content-Type: application/json');

$assignmentId = isset($_GET['assignment_id']) ? (int)$_GET['assignment_id'] : 0;

if ($assignmentId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Assignment ID.']);
    exit;
}

try {
    // 1. Get assignment details (batch_id)
    $stmt_assignment = $conn->prepare("SELECT batch_id FROM assignments WHERE id = ?");
    $stmt_assignment->bind_param("i", $assignmentId);
    $stmt_assignment->execute();
    $assignmentResult = $stmt_assignment->get_result();
    if ($assignmentResult->num_rows === 0) {
        throw new Exception('Assignment not found.');
    }
    $assignment = $assignmentResult->fetch_assoc();
    $batchId = $assignment['batch_id'];
    $stmt_assignment->close();

    // 2. Get total number of students in the batch
    $stmt_students = $conn->prepare("SELECT COUNT(id) as total_students FROM students WHERE batch_id = ?");
    $stmt_students->bind_param("i", $batchId);
    $stmt_students->execute();
    $studentsResult = $stmt_students->get_result();
    $totalStudents = $studentsResult->fetch_assoc()['total_students'] ?? 0;
    $stmt_students->close();

    // 3. Get submission counts
    $stmt_submissions = $conn->prepare("
        SELECT 
            COUNT(id) as submission_count,
            SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_count
        FROM assignment_submissions 
        WHERE assignment_id = ?
    ");
    $stmt_submissions->bind_param("i", $assignmentId);
    $stmt_submissions->execute();
    $submissionsResult = $stmt_submissions->get_result();
    $submissionCounts = $submissionsResult->fetch_assoc();
    $stmt_submissions->close();

    $stats = [
        'total_students' => (int)$totalStudents,
        'submission_count' => (int)($submissionCounts['submission_count'] ?? 0),
        'late_count' => (int)($submissionCounts['late_count'] ?? 0)
    ];

    echo json_encode(['status' => 'success', 'stats' => $stats]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
?>