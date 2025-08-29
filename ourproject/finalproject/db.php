<?php
// Database configuration for XAMPP
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', ''); // Default XAMPP password is empty
define('DB_NAME', 'finalproject_db');

// 1. Attempt to connect to the MySQL server without selecting a database.
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD);

// Check connection
if ($conn->connect_error) {
    // Stop execution if a connection to the server can't be established.
    die("ERROR: Could not connect to the MySQL server. " . $conn->connect_error);
}

// 2. Create the database if it doesn't exist. This makes the initial setup easier.
if (!$conn->query("CREATE DATABASE IF NOT EXISTS " . DB_NAME)) {
    die("ERROR: Failed to create database. " . $conn->error);
}

// 3. Now, select the database for use in the application.
$conn->select_db(DB_NAME);

// Set the character set to utf8mb4 for full Unicode support.
$conn->set_charset("utf8mb4");

// --- Automatic Table Creation ---
// 4. Check if a key table (e.g., the most recently added one) exists. If not, it's safe to assume
// the schema is outdated or missing, so we run the import script. This check makes the setup
// process resilient to schema updates.
$tableCheck = $conn->query("SHOW TABLES LIKE 'assignment_submissions'");
if ($tableCheck && $tableCheck->num_rows === 0) {
    // The tables do not exist, so we need to import the schema from database.sql.
    $sqlFilePath = __DIR__ . '/database.sql';
    if (file_exists($sqlFilePath)) {
        // Read the entire SQL file.
        $sql = file_get_contents($sqlFilePath);
 
        // Execute the multi-query.
        if ($conn->multi_query($sql)) {
            // Loop through and clear all results to avoid "Commands out of sync" error.
            do {
                if ($result = $conn->store_result()) {
                    $result->free();
                }
            } while ($conn->next_result());
        }
        // After the loop, check for any final error from multi_query. This is crucial
        // because next_result() can return false on error, breaking the loop silently.
        if ($conn->error) {
            die("ERROR: An error occurred during database schema import: " . $conn->error);
        }
    } else {
        die("ERROR: Database schema file 'database.sql' not found.");
    }
}
?>