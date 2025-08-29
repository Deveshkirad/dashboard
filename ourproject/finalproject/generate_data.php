<?php
// This script generates random data for students, marks, and attendance.
// Run it ONCE by visiting it in your browser (e.g., http://localhost/finalproject/generate_data.php).
// You can delete this file after running it successfully.

set_time_limit(300); // Increase execution time limit for safety
require_once 'db.php';

echo "<h1>Student Data Generation Script</h1>";

// --- Helper Functions ---
function getRandomName($firstNames, $lastNames) {
    return $firstNames[array_rand($firstNames)] . ' ' . $lastNames[array_rand($lastNames)];
}

function getRandomDate($startDate, $endDate) {
    $timestamp = mt_rand(strtotime($startDate), strtotime($endDate));
    return date("Y-m-d", $timestamp);
}

// --- Data Arrays for Randomization ---
$firstNames = ["Aarav", "Vivaan", "Aditya", "Vihaan", "Arjun", "Sai", "Reyansh", "Ayaan", "Krishna", "Ishaan", "Ananya", "Diya", "Saanvi", "Aadhya", "Myra", "Aarohi", "Anika", "Navya", "Gauri", "Pari", "Rohan", "Priya", "Vikram", "Sneha", "Karan", "Aryan", "Anjali"];
$lastNames = ["Sharma", "Verma", "Gupta", "Singh", "Kumar", "Patel", "Reddy", "Mehta", "Jain", "Khan", "Rao", "Rathore", "Roy"];

try {
    // Start a transaction for populating student data
    $conn->begin_transaction();

    // 1. Clear existing student-related data to prevent duplicates
    echo "Clearing old student data...<br>";
    $conn->query("SET FOREIGN_KEY_CHECKS = 0;");
    $conn->query("TRUNCATE TABLE attendance_records;");
    $conn->query("TRUNCATE TABLE student_marks;");
    $conn->query("TRUNCATE TABLE assignment_submissions;");
    $conn->query("TRUNCATE TABLE students;");
    $conn->query("SET FOREIGN_KEY_CHECKS = 1;");
    echo "Done.<br><br>";

    // 2. Fetch batches from the database
    $batchesResult = $conn->query("SELECT id, batch_name FROM batches");
    if (!$batchesResult || $batchesResult->num_rows === 0) {
        die("<strong>Error:</strong> No batches found. Please add batches to the 'batches' table first.");
    }
    $batches = $batchesResult->fetch_all(MYSQLI_ASSOC);

    // 2b. Fetch subjects from the database
    $subjectsResult = $conn->query("SELECT name FROM subjects ORDER BY name");
    if (!$subjectsResult || $subjectsResult->num_rows === 0) {
        die("<strong>Error:</strong> No subjects found. Please add subjects to the 'subjects' table first.");
    }
    $subjects = array_column($subjectsResult->fetch_all(MYSQLI_ASSOC), 'name');
    echo "Found subjects: " . htmlspecialchars(implode(', ', $subjects)) . "<br><br>";

    // 3. Prepare SQL statements for efficient insertion
    $stmtStudent = $conn->prepare("INSERT INTO students (name, univ_roll_no, year, batch_id, parent_contact, enrollment_date) VALUES (?, ?, ?, ?, ?, ?)");
    $stmtMarks = $conn->prepare("INSERT INTO student_marks (student_id, subject, sgpa) VALUES (?, ?, ?)");
    $stmtAttendance = $conn->prepare("INSERT INTO attendance_records (student_id, attendance_date, subject, status) VALUES (?, ?, ?, ?)");
    $stmtSubmission = $conn->prepare("INSERT INTO assignment_submissions (assignment_id, student_id, submission_date, status) VALUES (?, ?, ?, ?)");

    if (!$stmtStudent || !$stmtMarks || !$stmtAttendance || !$stmtSubmission) {
        throw new Exception("Error preparing statements: " . $conn->error);
    }

    $rollNoCounter = 101; // Start roll numbers from 101

    // 4. Loop through each batch and generate 50 students
    foreach ($batches as $batch) {
        echo "<strong>Generating 50 students for " . htmlspecialchars($batch['batch_name']) . "...</strong><br>";
        for ($i = 0; $i < 50; $i++) {
            // Generate student data
            $name = getRandomName($firstNames, $lastNames);
            $univ_roll_no = 'R' . str_pad($rollNoCounter++, 3, '0', STR_PAD_LEFT);
            $year = 2;
            $batch_id = $batch['id'];
            $parent_contact = '9' . mt_rand(100000000, 999999999);
            // Set enrollment date to the previous year (2024) for 2nd-year students
            $enrollment_date = getRandomDate('2024-07-01', '2024-08-31');

            // Insert student and get the new ID
            $stmtStudent->bind_param("ssiiss", $name, $univ_roll_no, $year, $batch_id, $parent_contact, $enrollment_date);
            $stmtStudent->execute();
            $student_id = $conn->insert_id;

            // Insert marks for each subject for the new student
            foreach ($subjects as $subject) {
                $sgpa = number_format(mt_rand(600, 950) / 100, 2);
                $stmtMarks->bind_param("isd", $student_id, $subject, $sgpa);
                $stmtMarks->execute();
            }

            // --- Generate fixed subject-wise attendance data for July 2025 ---
            $attendance_year = 2025;
            $attendance_month = 7; // July

            // Define target attendance percentages for each subject.
            $targetPercentages = [
                'Data Structures' => 85, 'OOP' => 95, 'Digital Logic' => 70,
                'Mathematics III' => 88, 'Economics' => 92, 'Web Tech Lab' => 100,
            ];

            // Get all weekdays in July 2025
            $weekdaysInJuly = [];
            $days_in_july = cal_days_in_month(CAL_GREGORIAN, $attendance_month, $attendance_year);
            for ($day = 1; $day <= $days_in_july; $day++) {
                $current_date = new DateTime("$attendance_year-$attendance_month-$day");
                if ($current_date->format('N') < 6) { // Monday to Friday
                    $weekdaysInJuly[] = $current_date->format('Y-m-d');
                }
            }

            // For each subject, generate attendance to meet the target percentage
            foreach ($subjects as $subject) {
                // Get the base target and add a random variation for each student to make it unique
                $basePercentage = $targetPercentages[$subject] ?? 75;
                $studentVariation = mt_rand(-10, 5); // Add a random variance between -10% and +5%
                $targetPercentage = $basePercentage + $studentVariation;
                $targetPercentage = max(60, min(100, $targetPercentage)); // Clamp the final value between 60% and 100%

                $totalClasses = 20; // Assume 20 classes per subject for simplicity
                $presentClasses = round($totalClasses * ($targetPercentage / 100));
                $statuses = array_merge(array_fill(0, $presentClasses, 'present'), array_fill(0, $totalClasses - $presentClasses, 'absent'));
                shuffle($statuses);
                $class_day_keys = array_rand($weekdaysInJuly, $totalClasses);
                $class_dates = array_map(fn($key) => $weekdaysInJuly[$key], (array)$class_day_keys);
                for ($k = 0; $k < $totalClasses; $k++) {
                    $stmtAttendance->bind_param("isss", $student_id, $class_dates[$k], $subject, $statuses[$k]);
                    $stmtAttendance->execute();
                }
            }
        }
        echo "Done.<br>";
    }

    $stmtStudent->close();
    $stmtMarks->close();
    $stmtAttendance->close();

    // 5. Generate Assignment Submissions
    echo "<br><strong>Generating assignment submissions...</strong><br>";
    $assignmentsResult = $conn->query("SELECT id, batch_id, due_date FROM assignments");
    $allAssignments = $assignmentsResult->fetch_all(MYSQLI_ASSOC);

    foreach ($allAssignments as $assignment) {
        $assignmentId = $assignment['id'];
        $batchId = $assignment['batch_id'];
        $dueDate = new DateTime($assignment['due_date']);

        // Get all students for this assignment's batch
        $studentsResult = $conn->query("SELECT id FROM students WHERE batch_id = $batchId");
        $studentIds = array_column($studentsResult->fetch_all(MYSQLI_ASSOC), 'id');

        if (empty($studentIds)) continue;

        // Have a random number of students (70-95%) submit the assignment
        $submissionCount = floor(count($studentIds) * (mt_rand(70, 95) / 100));
        $submittingStudentKeys = (array)array_rand($studentIds, $submissionCount);

        foreach ($submittingStudentKeys as $studentKey) {
            $studentId = $studentIds[$studentKey];

            // Decide if submission is late (20% chance)
            $isLate = (mt_rand(1, 100) <= 20);
            
            $submissionDate = clone $dueDate;
            if ($isLate) {
                $submissionDate->add(new DateInterval('P' . mt_rand(1, 5) . 'D'));
                $status = 'late';
            } else {
                $submissionDate->sub(new DateInterval('P' . mt_rand(0, 7) . 'D'));
                $status = 'on_time';
            }
            $submissionTimestamp = $submissionDate->format('Y-m-d H:i:s');

            $stmtSubmission->bind_param("iiss", $assignmentId, $studentId, $submissionTimestamp, $status);
            $stmtSubmission->execute();
        }
    }
    $stmtSubmission->close();
    echo "Done.<br>";

    // If all went well, commit the transaction
    $conn->commit();
    $conn->close();

    echo "<br><h2>Success!</h2>";
    echo "<p>Database has been populated with random data. You can now safely delete this script.</p>";

} catch (Exception $e) {
    // If an error occurred, roll back the transaction
    $conn->rollback();
    die("An error occurred: " . $e->getMessage() . ". The database has been rolled back to its previous state.");
}
?>