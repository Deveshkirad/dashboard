<?php
session_start();
require_once 'db.php';

// Check if the user is logged in, otherwise redirect
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

/**
 * Redirects the user back to the assignments page with a status message.
 * @param string $status 'success' or 'error'.
 * @param string $message The message to display (only for errors).
 */
function redirect_with_message($status, $message = '') {
    $url = 'assignments.php?status=' . $status;
    if (!empty($message)) {
        $url .= '&message=' . urlencode($message);
    }
    header('Location: ' . $url);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_with_message('error', 'Invalid request method.');
}

// Sanitize and validate inputs
$title = trim($_POST['title'] ?? '');
$subject = trim($_POST['subject'] ?? '');
$batch_id = (int)($_POST['batch_id'] ?? 0);
$due_date = trim($_POST['due_date'] ?? '');
$instructions = trim($_POST['instructions'] ?? '');
$status = 'pending'; // Default status for new assignments

if (empty($title) || empty($subject) || $batch_id <= 0 || empty($due_date)) {
    redirect_with_message('error', 'Please fill in all required fields.');
}

$stmt = $conn->prepare("INSERT INTO assignments (title, subject, batch_id, due_date, instructions, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
$stmt->bind_param("ssisss", $title, $subject, $batch_id, $due_date, $instructions, $status);

if ($stmt->execute()) {
    redirect_with_message('success');
} else {
    redirect_with_message('error', 'Failed to create the assignment. Please try again.');
}

$stmt->close();
$conn->close();