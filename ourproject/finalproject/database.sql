-- Create the database if it doesn't exist
CREATE DATABASE IF NOT EXISTS `finalproject_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `finalproject_db`;

--
-- Table structure for table `teachers`
--
CREATE TABLE IF NOT EXISTS `teachers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `status` enum('active','on_leave') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `teachers`
--
INSERT IGNORE INTO `teachers` (`id`, `name`, `subject`, `status`) VALUES
(1, 'Prof. Sharma', 'Data Structures', 'active'),
(2, 'Dr. Gupta', 'Object-Oriented Programming', 'active'),
(3, 'Prof. Verma', 'Digital Logic Design', 'active'),
(4, 'Dr. Khan', 'Mathematics III', 'on_leave'),
(5, 'Dr. Singh', 'Economics', 'active'),
(6, 'Prof. Roy', 'Web Tech Lab', 'active');

--
-- Table structure for table `batches`
CREATE TABLE IF NOT EXISTS `batches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `batch_name` varchar(255) NOT NULL,
  `faculty_advisor_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `faculty_advisor_id` (`faculty_advisor_id`),
  CONSTRAINT `batches_ibfk_1` FOREIGN KEY (`faculty_advisor_id`) REFERENCES `teachers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `batches`
--
INSERT IGNORE INTO `batches` (`id`, `batch_name`, `faculty_advisor_id`) VALUES
(1, 'Batch A', 1),
(2, 'Batch B', 2),
(3, 'Batch C', 3);

--
-- Table structure for table `subjects`
--
CREATE TABLE IF NOT EXISTS `subjects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `subjects`
--
INSERT IGNORE INTO `subjects` (`id`, `name`) VALUES
(5, 'Economics'),
(1, 'Data Structures'),
(3, 'Digital Logic Design'),
(4, 'Mathematics III'),
(2, 'Object-Oriented Programming'),
(6, 'Web Tech Lab');

--
-- Table structure for table `subject_units`
--
CREATE TABLE IF NOT EXISTS `subject_units` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subject_name` varchar(255) NOT NULL,
  `unit_number` int(11) NOT NULL,
  `unit_name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `subject_name_idx` (`subject_name`),
  CONSTRAINT `subject_units_ibfk_1` FOREIGN KEY (`subject_name`) REFERENCES `subjects` (`name`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `subject_units`
--
INSERT IGNORE INTO `subject_units` (`id`, `subject_name`, `unit_number`, `unit_name`) VALUES
(1, 'Data Structures', 1, 'Introduction to Data Structures'),
(2, 'Data Structures', 2, 'Arrays and Linked Lists'),
(3, 'Data Structures', 3, 'Stacks and Queues'),
(4, 'Data Structures', 4, 'Trees and Graphs'),
(5, 'Data Structures', 5, 'Hashing and Heaps'),
(6, 'Object-Oriented Programming', 1, 'Principles of OOP'),
(7, 'Object-Oriented Programming', 2, 'Classes and Objects'),
(8, 'Object-Oriented Programming', 3, 'Inheritance and Polymorphism'),
(9, 'Object-Oriented Programming', 4, 'Exception Handling'),
(10, 'Object-Oriented Programming', 5, 'Templates and STL'),
(11, 'Digital Logic Design', 1, 'Number Systems'),
(12, 'Digital Logic Design', 2, 'Boolean Algebra and Logic Gates'),
(13, 'Digital Logic Design', 3, 'Combinational Logic'),
(14, 'Digital Logic Design', 4, 'Sequential Logic'),
(15, 'Digital Logic Design', 5, 'Memory and Programmable Logic'),
(16, 'Mathematics III', 1, 'Laplace Transforms'),
(17, 'Mathematics III', 2, 'Fourier Series'),
(18, 'Mathematics III', 3, 'Complex Variables'),
(19, 'Mathematics III', 4, 'Probability and Statistics');

--
-- Table structure for table `teacher_completed_units`
--
CREATE TABLE IF NOT EXISTS `teacher_completed_units` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `unit_id` int(11) NOT NULL,
  `batch_id` int(11) NOT NULL,
  `completed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `teacher_unit_batch` (`teacher_id`,`unit_id`,`batch_id`),
  KEY `teacher_id` (`teacher_id`),
  KEY `unit_id` (`unit_id`),
  KEY `batch_id` (`batch_id`),
  CONSTRAINT `teacher_completed_units_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `teacher_completed_units_ibfk_2` FOREIGN KEY (`unit_id`) REFERENCES `subject_units` (`id`) ON DELETE CASCADE,
  CONSTRAINT `teacher_completed_units_ibfk_3` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `students`
--
CREATE TABLE IF NOT EXISTS `students` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `univ_roll_no` varchar(50) NOT NULL,
  `year` int(11) NOT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `parent_contact` varchar(20) DEFAULT NULL,
  `enrollment_date` date NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `univ_roll_no` (`univ_roll_no`),
  KEY `batch_id` (`batch_id`),
  CONSTRAINT `students_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `assignments`
--
CREATE TABLE IF NOT EXISTS `assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `batch_id` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `due_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','graded') NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`id`),
  KEY `batch_id` (`batch_id`),
  CONSTRAINT `assignments_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `assignment_submissions`
--
CREATE TABLE IF NOT EXISTS `assignment_submissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `assignment_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `submission_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('on_time','late') NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `assignment_student` (`assignment_id`,`student_id`),
  CONSTRAINT `assignment_submissions_ibfk_1` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `assignment_submissions_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `assignments`
--
INSERT IGNORE INTO `assignments` (`id`, `title`, `batch_id`, `subject`, `due_date`, `status`) VALUES
(1, 'Linked List Implementation', 1, 'Data Structures', '2024-10-15', 'pending'),
(2, 'OOP Project Phase 1', 2, 'Object-Oriented Programming', '2024-10-25', 'pending'),
(3, 'K-Map Simplification', 3, 'Digital Logic Design', '2024-09-30', 'graded'),
(4, 'Matrix Operations', 1, 'Mathematics III', '2024-11-05', 'pending');

--
-- Table structure for table `student_marks`
--
CREATE TABLE IF NOT EXISTS `student_marks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `sgpa` decimal(4,2) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_subject` (`student_id`,`subject`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `student_marks_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `attendance_records`
--
CREATE TABLE IF NOT EXISTS `attendance_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `attendance_date` date NOT NULL,
  `subject` varchar(255) NOT NULL,
  `status` enum('present','absent') NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_date_subject` (`student_id`,`attendance_date`,`subject`),
  KEY `student_id` (`student_id`),
  KEY `attendance_date` (`attendance_date`),
  CONSTRAINT `attendance_records_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `timetable_slots`
--
CREATE TABLE IF NOT EXISTS `timetable_slots` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `batch_id` int(11) NOT NULL,
  `day_of_week` int(11) NOT NULL COMMENT '1=Monday, 7=Sunday',
  `period_number` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slot_unique` (`batch_id`,`day_of_week`,`period_number`),
  KEY `batch_id` (`batch_id`),
  KEY `subject_id` (`subject_id`),
  KEY `teacher_id` (`teacher_id`),
  CONSTRAINT `timetable_slots_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `timetable_slots_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `timetable_slots_ibfk_3` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;