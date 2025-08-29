<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// This variable will be used to set the 'active' class on the correct nav link
$currentPage = basename($_SERVER['SCRIPT_NAME']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- The title will be passed from the parent page -->
    <title><?php echo $pageTitle ?? 'Admin Dashboard'; ?></title>
    <script>
        // Apply theme from localStorage on page load to prevent FOUC (Flash of Unstyled Content)
        (function() {
            const theme = localStorage.getItem('theme');
            if (theme === 'dark') {
                document.documentElement.classList.add('dark-mode');
            }
        })();
    </script>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Shared Layout CSS -->
    <link rel="stylesheet" href="assest/css/layout.css">
    <!-- Page-specific CSS -->
    <?php if (isset($pageStylesheets) && is_array($pageStylesheets)): ?>
        <?php foreach ($pageStylesheets as $stylesheet): ?>
            <link rel="stylesheet" href="<?php echo htmlspecialchars($stylesheet); ?>">
        <?php endforeach; ?>
    <?php endif; ?>
    <!-- Custom Theme CSS (should be loaded last to override other styles) -->
    <link rel="stylesheet" href="assest/css/theme.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top">
        <div class="container-fluid">
            <button id="sidebarToggler" class="sidebar-toggler me-2 d-lg-none"><i class="fa fa-bars"></i></button>
            <span class="navbar-brand fw-bold">
                <i class="fa fa-laptop-code"></i> ADMIN_DB
            </span>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMenu">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="navbarMenu">
                <ul class="navbar-nav align-items-center ms-auto">
                    <li class="nav-item me-3">
                        <div class="theme-switcher form-check form-switch d-flex align-items-center">
                            <input class="form-check-input" type="checkbox" id="themeSwitch" role="switch">
                            <label class="form-check-label" for="themeSwitch">
                                <i class="fas fa-moon"></i>
                            </label>
                        </div>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="me-2 d-none d-lg-inline text-gray-600 small"><?php echo htmlspecialchars($_SESSION['user_email'] ?? 'Admin'); ?></span>
                            <img src="assest/images/me.jpg" alt="Profile" class="rounded-circle" width="32" height="32">
                        </a>
                        <!-- Dropdown - User Information -->
                        <ul class="dropdown-menu dropdown-menu-end shadow animated--grow-in" aria-labelledby="userDropdown">
                            <li>
                                <a class="dropdown-item" href="#">
                                    <i class="fas fa-user fa-sm fa-fw me-2 text-gray-400"></i>
                                    Profile
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="logout.php">
                                    <i class="fas fa-sign-out-alt fa-sm fa-fw me-2 text-gray-400"></i>
                                    Logout
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header p-3">
            <h4>Menu</h4>
        </div>
        <ul class="nav flex-column p-3 sidebar-nav">
            <li class="nav-item">
                <a class="nav-link <?php if ($currentPage == 'dashboard.php') echo 'active'; ?>" href="dashboard.php">
                    <i class="nav-icon fa fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php if ($currentPage == 'teachers.php') echo 'active'; ?>" href="teachers.php">
                    <i class="nav-icon fa fa-user-tie"></i> Faculty
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php if ($currentPage == 'students.php') echo 'active'; ?>" href="students.php">
                    <i class="nav-icon fa fa-user-graduate"></i> Students
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php if ($currentPage == 'assignments.php') echo 'active'; ?>" href="assignments.php">
                    <i class="nav-icon fa fa-tasks"></i> Assignments
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php if ($currentPage == 'attendance.php') echo 'active'; ?>" href="attendance.php">
                    <i class="nav-icon fa fa-calendar-check"></i> Attendance
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php if ($currentPage == 'timetable.php') echo 'active'; ?>" href="timetable.php">
                    <i class="nav-icon fa fa-table-list"></i> Timetable
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">