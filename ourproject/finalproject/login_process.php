<?php
session_start(); // Start the session at the very beginning
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit();
}

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($email) || empty($password)) {
    header('Location: login.php?error=empty');
    exit();
}

// --- DEVELOPMENT LOGIN ---
// Bypassing the database check as requested.
// This will log in with any non-empty email and password.
$_SESSION['user_id'] = 1; // Assign a dummy user ID
$_SESSION['user_email'] = $email; // Use the provided email
header('Location: dashboard.php');
exit();
?>