<?php
require_once 'db.php';

header('Content-Type: application/json');

// Although we are not using batch_id yet, it's good practice to expect it for future-proofing.
// This allows the frontend to be built correctly for when batch-specific subjects are implemented.
$batchId = isset($_GET['batch_id']) ? (int)$_GET['batch_id'] : 0;

if ($batchId <= 0) {
    // In a real scenario with batch-specific subjects, you might return an error or an empty array here.
    // For this implementation, we proceed to return all subjects regardless.
}

// In a future, more complex schema, you would use the $batchId to query a linking table
// like `batch_subjects`. For now, we return all subjects as per the current project design.
$subjectsQuery = "SELECT name FROM subjects ORDER BY name";
$result = $conn->query($subjectsQuery);

$subjects = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $subjects[] = $row['name'];
    }
}

echo json_encode(['subjects' => $subjects]);

$conn->close();
?>