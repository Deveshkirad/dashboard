<?php
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $day = $_POST['day'];
    $time = $_POST['time'];
    $subject = $_POST['subject'];
    $teacher = $_POST['teacher'];
    $room = $_POST['room'];

    // Check if an entry already exists for this day and time
    $stmt = $conn->prepare("SELECT * FROM timetable WHERE day = ? AND time = ?");
    $stmt->bind_param("ii", $day, $time);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Update existing entry
        $stmt = $conn->prepare("UPDATE timetable SET subject = ?, teacher = ?, room = ? WHERE day = ? AND time = ?");
        $stmt->bind_param("sssii", $subject, $teacher, $room, $day, $time);
    } else {
        // Insert new entry
        $stmt = $conn->prepare("INSERT INTO timetable (day, time, subject, teacher, room) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iisss", $day, $time, $subject, $teacher, $room);
    }

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $stmt->error]);
    }

    $stmt->close();
    $conn->close();
}
?>