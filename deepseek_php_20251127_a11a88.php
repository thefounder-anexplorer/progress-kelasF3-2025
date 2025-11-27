<?php
// Mulai session untuk login
session_start();

// Data siswa dan tugas (disimpan di session atau database)
if (!isset($_SESSION['students'])) {
    $_SESSION['students'] = [];
}
if (!isset($_SESSION['assignments'])) {
    $_SESSION['assignments'] = [];
}

// Status login
$isTeacherLoggedIn = isset($_SESSION['is_teacher_logged_in']) && $_SESSION['is_teacher_logged_in'];

// Kredensial login guru
$TEACHER_CREDENTIALS = [
    'username' => 'teacher',
    'password' => 'bismillah'
];

// Grade options lengkap
$LETTER_GRADE_OPTIONS = ['A+', 'A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'C-', 'D'];

// Grade type options
$GRADE_TYPE_OPTIONS = ['Huruf', 'Angka'];

// Variabel untuk fitur input cepat poin
$selectedStudentForQuickPoints = null;
$selectedPointsValue = null;

// Proses form login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'teacher_login') {
        $username = $_POST['teacherUsername'] ?? '';
        $password = $_POST['teacherPassword'] ?? '';
        
        if ($username === $TEACHER_CREDENTIALS['username'] && $password === $TEACHER_CREDENTIALS['password']) {
            $_SESSION['is_teacher_logged_in'] = true;
            $isTeacherLoggedIn = true;
        } else {
            $loginError = 'Username atau password salah!';
        }
    } elseif ($_POST['action'] === 'teacher_logout') {
        $_SESSION['is_teacher_logged_in'] = false;
        $isTeacherLoggedIn = false;
    } elseif ($_POST['action'] === 'add_student') {
        $studentName = trim($_POST['newStudentName'] ?? '');
        if ($studentName) {
            // Cek apakah siswa sudah ada
            $existingStudent = false;
            foreach ($_SESSION['students'] as $student) {
                if (strtolower($student['name']) === strtolower($studentName)) {
                    $existingStudent = true;
                    break;
                }
            }
            
            if (!$existingStudent) {
                $_SESSION['students'][] = [
                    'name' => $studentName,
                    'points' => 0,
                    'completedAssignments' => []
                ];
                $successMessage = "Siswa \"$studentName\" berhasil ditambahkan!";
            } else {
                $errorMessage = "Siswa dengan nama \"$studentName\" sudah ada!";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Progress Kelas - Aplikasi Guru & Murid</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <style>
        /* CSS yang sama seperti sebelumnya */
        :root {
            --primary: #3498db;
            --secondary: #2ecc71;
            --danger: #e74c3c;
            --warning: #f39c12;
            --light: #f5f5f5;
            --dark: #333;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f0f2f5;
            color: var(--dark);
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        header {
            background: linear-gradient(135deg, var(--primary), #2980b9);
            color: white;
            padding: 20px 0;
            text-align: center;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            position: relative;
        }
        
        .login-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            background-color: white;
            color: var(--primary);
            border: none;
            padding: 8px 16px;
            border-radius: 20px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        
        .login-btn:hover {
            background-color: var(--primary);
            color: white;
            transform: translateY(-2px);
        }
        
        h1, h2, h3 {
            margin-bottom: 15px;
        }
        
        .progress-tabs {
            display: flex;
            margin-bottom: 20px;
            background-color: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .tab-btn {
            flex: 1;
            padding: 15px;
            border: none;
            background: none;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        
        .tab-btn.active {
            background-color: var(--primary);
            color: white;
        }
        
        .tab-btn:hover:not(.active) {
            background-color: var(--light);
        }
        
        .progress-section {
            display: none;
            background-color: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        
        .progress-section.active {
            display: block;
        }
        
        .student-progress {
            margin-bottom: 20px;
            padding: 15px;
            border-radius: 8px;
            background-color: var(--light);
        }
        
        .student-name {
            font-weight: bold;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .student-points {
            background-color: var(--warning);
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 14px;
        }
        
        .progress-bar {
            height: 20px;
            background-color: #e0e0e0;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 5px;
        }
        
        .progress-fill {
            height: 100%;
            border-radius: 10px;
            transition: width 0.5s ease;
        }
        
        .points-progress {
            background: linear-gradient(90deg, #f39c12, #e67e22);
        }
        
        .assignment-progress {
            background: linear-gradient(90deg, #2ecc71, #27ae60);
        }
        
        .progress-label {
            display: flex;
            justify-content: space-between;
            margin-top: 5px;
            font-size: 14px;
            color: #666;
        }
        
        .stats {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        
        .stat-card {
            flex: 1;
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            text-align: center;
            margin: 0 10px;
        }
        
        .stat-card h3 {
            color: var(--primary);
            margin-bottom: 10px;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: bold;
            color: var(--dark);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        input, select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        
        button {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            transition: background-color 0.3s ease;
        }
        
        button:hover {
            background-color: #2980b9;
        }
        
        .btn-danger {
            background-color: var(--danger);
        }
        
        .btn-danger:hover {
            background-color: #c0392b;
        }
        
        .btn-secondary {
            background-color: var(--secondary);
        }
        
        .btn-secondary:hover {
            background-color: #27ae60;
        }
        
        .btn-warning {
            background-color: var(--warning);
        }
        
        .btn-warning:hover {
            background-color: #e67e22;
        }
        
        .btn-success {
            background-color: #27ae60;
        }
        
        .btn-success:hover {
            background-color: #219653;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        th {
            background-color: var(--light);
            font-weight: 600;
        }
        
        tr:hover {
            background-color: rgba(52, 152, 219, 0.05);
        }
        
        .star {
            color: var(--warning);
            font-size: 18px;
        }
        
        .completed {
            color: var(--secondary);
            font-weight: bold;
        }
        
        .incomplete {
            color: var(--danger);
            font-weight: bold;
        }
        
        .login-section {
            max-width: 400px;
            margin: 50px auto;
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .login-section h2 {
            text-align: center;
            margin-bottom: 25px;
            color: var(--primary);
        }
        
        .hidden {
            display: none;
        }
        
        .alert {
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .alert-error {
            background-color: #ffebee;
            color: var(--danger);
            border: 1px solid #ffcdd2;
        }
        
        .logout-btn {
            background-color: transparent;
            color: var(--primary);
            border: 1px solid var(--primary);
            padding: 8px 15px;
            font-size: 14px;
        }
        
        .logout-btn:hover {
            background-color: var(--primary);
            color: white;
        }
        
        .input-mode-tabs {
            display: flex;
            margin-bottom: 20px;
            background-color: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .input-mode-btn {
            flex: 1;
            padding: 12px;
            border: none;
            background: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        
        .input-mode-btn.active {
            background-color: var(--primary);
            color: white;
        }
        
        .input-mode-btn:hover:not(.active) {
            background-color: var(--light);
        }
        
        .input-section {
            display: none;
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        
        .input-section.active {
            display: block;
        }
        
        .student-checkbox-list {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            margin-top: 10px;
        }
        
        .checkbox-item {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            padding: 8px;
            border-radius: 5px;
            transition: background-color 0.2s;
        }
        
        .checkbox-item:hover {
            background-color: #f9f9f9;
        }
        
        .checkbox-item input {
            width: auto;
            margin-right: 10px;
        }
        
        .assignment-item {
            background-color: var(--light);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        .assignment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .assignment-title {
            font-weight: bold;
            color: var(--primary);
        }
        
        .assignment-date {
            font-size: 12px;
            color: #666;
        }
        
        .assignment-students {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin-bottom: 10px;
        }
        
        .student-tag {
            background-color: var(--secondary);
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 12px;
        }
        
        .student-management {
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        
        .student-list {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            margin-top: 10px;
        }
        
        .student-list-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px;
            border-bottom: 1px solid #eee;
        }
        
        .student-list-item:last-child {
            border-bottom: none;
        }
        
        .student-actions {
            display: flex;
            gap: 5px;
        }
        
        .student-actions button {
            padding: 4px 8px;
            font-size: 12px;
        }
        
        .assignment-actions {
            display: flex;
            gap: 5px;
            margin-top: 10px;
        }
        
        .assignment-actions button {
            padding: 6px 12px;
            font-size: 12px;
        }
        
        .edit-form {
            display: none;
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-top: 10px;
            border: 1px solid #ddd;
        }
        
        .edit-form.active {
            display: block;
        }
        
        .edit-form .form-group {
            margin-bottom: 10px;
        }
        
        .edit-form input {
            padding: 8px;
            font-size: 14px;
        }
        
        .edit-form-buttons {
            display: flex;
            gap: 10px;
        }
        
        .edit-form-buttons button {
            padding: 8px 15px;
            font-size: 14px;
        }
        
        .download-section {
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        
        .download-options {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 15px;
        }
        
        .download-options button {
            flex: 1;
            min-width: 200px;
        }
        
        .grade-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: bold;
            margin-left: 5px;
        }
        
        .grade-Aplus { background-color: #27ae60; color: white; }
        .grade-A { background-color: #2ecc71; color: white; }
        .grade-Aminus { background-color: #2ecc71; color: white; opacity: 0.9; }
        .grade-Bplus { background-color: #f39c12; color: white; }
        .grade-B { background-color: #f39c12; color: white; opacity: 0.9; }
        .grade-Bminus { background-color: #f39c12; color: white; opacity: 0.8; }
        .grade-Cplus { background-color: #e67e22; color: white; }
        .grade-C { background-color: #e67e22; color: white; opacity: 0.9; }
        .grade-Cminus { background-color: #e67e22; color: white; opacity: 0.8; }
        .grade-D { background-color: #e74c3c; color: white; }
        
        .assignment-details {
            margin-top: 10px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        
        .student-grade-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px;
            border-bottom: 1px solid #eee;
        }
        
        .student-grade-item:last-child {
            border-bottom: none;
        }
        
        .grade-select {
            width: 80px;
            padding: 4px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        
        .grade-input-group {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .grade-type-select {
            width: 120px;
        }
        
        .grade-value-input {
            width: 80px;
        }
        
        .grade-display {
            display: flex;
            gap: 5px;
            align-items: center;
        }
        
        .numeric-grade {
            font-size: 12px;
            color: #666;
            margin-left: 5px;
        }
        
        .incomplete-assignments {
            margin-top: 10px;
            padding: 10px;
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .incomplete-assignment-list {
            margin-top: 5px;
            padding-left: 20px;
        }
        
        .incomplete-assignment-item {
            margin-bottom: 5px;
            color: #856404;
        }
        
        .expand-btn {
            background: none;
            border: none;
            color: var(--primary);
            cursor: pointer;
            font-size: 12px;
            padding: 2px 5px;
            margin-left: 5px;
        }
        
        .expand-btn:hover {
            text-decoration: underline;
        }
        
        /* CSS untuk fitur input cepat poin keaktifan */
        .quick-points-section {
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        
        .quick-points-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .quick-points-instruction {
            color: #666;
            margin-bottom: 15px;
            font-size: 14px;
        }
        
        .quick-points-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .quick-points-student {
            display: flex;
            flex-direction: column;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .quick-points-student:hover {
            background-color: #f9f9f9;
            border-color: var(--primary);
        }
        
        .quick-points-student.active {
            background-color: #e3f2fd;
            border-color: var(--primary);
        }
        
        .quick-points-student-name {
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .quick-points-student-points {
            color: var(--warning);
            font-size: 14px;
        }
        
        .quick-points-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin-top: 10px;
        }
        
        .quick-points-btn {
            flex: 1;
            min-width: 40px;
            padding: 8px 5px;
            background-color: var(--light);
            color: var(--dark);
            border: 1px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.2s;
        }
        
        .quick-points-btn:hover {
            background-color: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .quick-points-btn.active {
            background-color: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .quick-points-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        @media (max-width: 768px) {
            .stats {
                flex-direction: column;
            }
            
            .stat-card {
                margin: 10px 0;
            }
            
            .progress-tabs {
                flex-direction: column;
            }
            
            .login-btn {
                position: static;
                margin-top: 10px;
            }
            
            header {
                text-align: center;
                padding-bottom: 60px;
            }
            
            .input-mode-tabs {
                flex-direction: column;
            }
            
            .student-actions {
                flex-direction: column;
            }
            
            .assignment-actions {
                flex-direction: column;
            }
            
            .download-options {
                flex-direction: column;
            }
            
            .download-options button {
                min-width: 100%;
            }
            
            .grade-input-group {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .grade-type-select, .grade-value-input {
                width: 100%;
            }
            
            .quick-points-grid {
                grid-template-columns: 1fr;
            }
            
            .quick-points-buttons {
                flex-wrap: nowrap;
                overflow-x: auto;
            }
            
            .quick-points-btn {
                min-width: 35px;
                padding: 8px 3px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Progress Kelas</h1>
            <p>Pantau Progress Keaktifan dan Tugas Siswa</p>
            <?php if (!$isTeacherLoggedIn): ?>
                <button id="loginBtn" class="login-btn">Login as Teacher</button>
            <?php endif; ?>
        </header>
        
        <!-- Tampilan Progress untuk Murid -->
        <div id="studentView" <?php echo $isTeacherLoggedIn ? 'class="hidden"' : ''; ?>>
            <div class="progress-tabs">
                <button class="tab-btn active" data-tab="points">Progress Keaktifan</button>
                <button class="tab-btn" data-tab="assignments">Progress Tugas</button>
            </div>
            
            <!-- Progress Keaktifan -->
            <div id="pointsProgress" class="progress-section active">
                <h2>Progress Keaktifan Siswa</h2>
                <p>Berdasarkan poin bintang yang diperoleh dari partisipasi di kelas</p>
                
                <div id="pointsProgressList">
                    <?php
                    if (empty($_SESSION['students'])) {
                        echo '<p style="text-align: center; color: #666;">Belum ada data siswa</p>';
                    } else {
                        // Urutkan berdasarkan poin (tertinggi ke terendah)
                        $students = $_SESSION['students'];
                        usort($students, function($a, $b) {
                            return $b['points'] - $a['points'];
                        });
                        $maxPoints = max(array_column($students, 'points'));
                        $maxPoints = $maxPoints > 0 ? $maxPoints : 1; // Minimal 1 untuk menghindari pembagian 0
                        
                        foreach ($students as $student) {
                            $percentage = ($student['points'] / $maxPoints) * 100;
                            echo '
                            <div class="student-progress">
                                <div class="student-name">
                                    ' . htmlspecialchars($student['name']) . '
                                    <span class="student-points">' . $student['points'] . ' ★</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill points-progress" style="width: ' . $percentage . '%"></div>
                                </div>
                                <div class="progress-label">
                                    <span>0</span>
                                    <span>' . $maxPoints . ' poin</span>
                                </div>
                            </div>';
                        }
                    }
                    ?>
                </div>
            </div>
            
            <!-- Progress Tugas -->
            <div id="assignmentsProgress" class="progress-section">
                <h2>Progress Penyelesaian Tugas</h2>
                <p>Status kelengkapan tugas yang diberikan kepada siswa</p>
                
                <div id="assignmentsProgressList">
                    <?php
                    if (empty($_SESSION['students'])) {
                        echo '<p style="text-align: center; color: #666;">Belum ada data siswa</p>';
                    } else {
                        $totalAssignments = count($_SESSION['assignments']);
                        
                        foreach ($_SESSION['students'] as $student) {
                            $completedCount = isset($student['completedAssignments']) ? count($student['completedAssignments']) : 0;
                            $percentage = $totalAssignments > 0 ? ($completedCount / $totalAssignments) * 100 : 0;
                            $statusText = $totalAssignments > 0 ? $completedCount . '/' . $totalAssignments . ' tugas' : 'Belum ada tugas';
                            
                            // Tugas yang belum dikerjakan
                            $incompleteAssignments = [];
                            foreach ($_SESSION['assignments'] as $assignment) {
                                if (!in_array($student['name'], $assignment['completedStudents'] ?? [])) {
                                    $incompleteAssignments[] = $assignment;
                                }
                            }
                            
                            echo '
                            <div class="student-progress">
                                <div class="student-name">
                                    ' . htmlspecialchars($student['name']) . '
                                    <span class="' . ($percentage === 100 ? 'completed' : 'incomplete') . '">' . $statusText . '</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill assignment-progress" style="width: ' . $percentage . '%"></div>
                                </div>
                                <div class="progress-label">
                                    <span>0%</span>
                                    <span>100%</span>
                                </div>';
                            
                            if (!empty($incompleteAssignments)) {
                                echo '
                                <div class="incomplete-assignments">
                                    <strong>Tugas yang belum dikerjakan:</strong>
                                    <div class="incomplete-assignment-list">';
                                
                                foreach ($incompleteAssignments as $assignment) {
                                    echo '<div class="incomplete-assignment-item">' . htmlspecialchars($assignment['title']) . '</div>';
                                }
                                
                                echo '
                                    </div>
                                </div>';
                            }
                            
                            echo '</div>';
                        }
                    }
                    ?>
                </div>
            </div>
        </div>
        
        <!-- Section Login Guru -->
        <div id="teacherLoginSection" class="login-section <?php echo $isTeacherLoggedIn ? 'hidden' : ''; ?>">
            <h2>Login Guru</h2>
            <?php if (isset($loginError)): ?>
                <div class="alert alert-error"><?php echo $loginError; ?></div>
            <?php endif; ?>
            <form id="teacherLoginForm" method="post">
                <input type="hidden" name="action" value="teacher_login">
                <div class="form-group">
                    <label for="teacherUsername">Username</label>
                    <input type="text" id="teacherUsername" name="teacherUsername" required>
                </div>
                
                <div class="form-group">
                    <label for="teacherPassword">Password</label>
                    <input type="password" id="teacherPassword" name="teacherPassword" required>
                </div>
                
                <button type="submit" style="width: 100%;">Login</button>
            </form>
            <button id="backToStudentView" class="logout-btn" style="width: 100%; margin-top: 15px;">Kembali ke Tampilan Murid</button>
        </div>
        
        <!-- Mode Guru -->
        <div id="teacherView" <?php echo !$isTeacherLoggedIn ? 'class="hidden"' : ''; ?>>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2>Mode Guru - Kelola Data Siswa</h2>
                <form method="post" style="display: inline;">
                    <input type="hidden" name="action" value="teacher_logout">
                    <button type="submit" class="logout-btn">Logout</button>
                </form>
            </div>
            
            <?php
            // Hitung statistik
            $totalStudents = count($_SESSION['students']);
            $totalPoints = 0;
            $totalCompletedTasks = 0;
            
            foreach ($_SESSION['students'] as $student) {
                $totalPoints += $student['points'];
                $totalCompletedTasks += isset($student['completedAssignments']) ? count($student['completedAssignments']) : 0;
            }
            
            $avgPoints = $totalStudents > 0 ? round($totalPoints / $totalStudents, 1) : 0;
            $totalAssignments = count($_SESSION['assignments']);
            $completedTasksPercentage = $totalStudents > 0 && $totalAssignments > 0 ? 
                round(($totalCompletedTasks / ($totalStudents * $totalAssignments)) * 100) : 0;
            ?>
            
            <div class="stats">
                <div class="stat-card">
                    <h3>Total Siswa</h3>
                    <div class="stat-value" id="teacherTotalStudents"><?php echo $totalStudents; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Rata-rata Poin</h3>
                    <div class="stat-value" id="teacherAvgPoints"><?php echo $avgPoints; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Tugas Lengkap</h3>
                    <div class="stat-value" id="teacherCompletedTasks"><?php echo $completedTasksPercentage; ?>%</div>
                </div>
            </div>

            <!-- Section Download Data -->
            <div class="download-section">
                <h3>Download Data</h3>
                <p>Unduh data progress siswa dalam format Excel untuk keperluan dokumentasi atau analisis lebih lanjut.</p>
                <div class="download-options">
                    <button id="downloadStudentsBtn" class="btn-success">
                        📊 Download Data Siswa
                    </button>
                    <button id="downloadAssignmentsBtn" class="btn-success">
                        📝 Download Data Tugas
                    </button>
                    <button id="downloadSummaryBtn" class="btn-success">
                        📋 Download Ringkasan Kelas
                    </button>
                </div>
            </div>

            <!-- Kelola Data Siswa -->
            <div class="student-management">
                <h3>Kelola Data Siswa</h3>
                <?php if (isset($successMessage)): ?>
                    <div class="alert" style="background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb;">
                        <?php echo $successMessage; ?>
                    </div>
                <?php endif; ?>
                <?php if (isset($errorMessage)): ?>
                    <div class="alert alert-error"><?php echo $errorMessage; ?></div>
                <?php endif; ?>
                <form id="addStudentForm" method="post">
                    <input type="hidden" name="action" value="add_student">
                    <div class="form-group">
                        <label for="newStudentName">Tambah Siswa Baru</label>
                        <div style="display: flex; gap: 10px;">
                            <input type="text" id="newStudentName" name="newStudentName" placeholder="Masukkan nama siswa" required>
                            <button type="submit" class="btn-secondary">Tambah Siswa</button>
                        </div>
                    </div>
                </form>
                
                <div class="student-list" id="studentList">
                    <?php
                    if (empty($_SESSION['students'])) {
                        echo '<p style="text-align: center; color: #666;">Belum ada siswa</p>';
                    } else {
                        foreach ($_SESSION['students'] as $student) {
                            $safeName = preg_replace('/\s+/', '-', $student['name']);
                            echo '
                            <div class="student-list-item">
                                <div style="flex: 1;">
                                    <span>' . htmlspecialchars($student['name']) . '</span>
                                    <span style="color: #666; font-size: 14px; margin-left: 10px;">' . $student['points'] . ' poin</span>
                                </div>
                                <div class="student-actions">
                                    <button onclick="showEditStudentForm(\'' . $student['name'] . '\')" class="btn-warning">Edit Nama</button>
                                    <button onclick="removeStudent(\'' . $student['name'] . '\')" class="btn-danger">Hapus</button>
                                </div>
                                <div id="edit-form-' . $safeName . '" class="edit-form">
                                    <div class="form-group">
                                        <label>Edit Nama Siswa:</label>
                                        <input type="text" id="edit-student-' . $safeName . '" value="' . htmlspecialchars($student['name']) . '" required>
                                    </div>
                                    <div class="edit-form-buttons">
                                        <button onclick="updateStudentName(\'' . $student['name'] . '\')" class="btn-secondary">Simpan</button>
                                        <button onclick="hideEditStudentForm(\'' . $student['name'] . '\')" class="logout-btn">Batal</button>
                                    </div>
                                </div>
                            </div>';
                        }
                    }
                    ?>
                </div>
            </div>
            
            <!-- Tabs Mode Input -->
            <div class="input-mode-tabs">
                <button class="input-mode-btn active" data-input-mode="points">Input Poin Keaktifan</button>
                <button class="input-mode-btn" data-input-mode="assignment">Input Kelengkapan Tugas</button>
                <button class="input-mode-btn" data-input-mode="quick">Input Cepat Poin</button>
            </div>
            
            <!-- Input Poin Keaktifan -->
            <div id="pointsInputSection" class="input-section active">
                <h3>Input Poin Keaktifan Siswa</h3>
                <form id="pointsForm">
                    <div class="form-group">
                        <label for="studentNamePoints">Nama Siswa</label>
                        <select id="studentNamePoints" required>
                            <option value="">Pilih Siswa</option>
                            <?php
                            foreach ($_SESSION['students'] as $student) {
                                echo '<option value="' . htmlspecialchars($student['name']) . '">' . htmlspecialchars($student['name']) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="studentPoints">Poin Bintang</label>
                        <input type="number" id="studentPoints" min="0" max="10" required>
                        <small>Masukkan poin antara 0-10</small>
                    </div>
                    
                    <button type="submit" class="btn-secondary">Tambah Poin</button>
                </form>
            </div>
            
            <!-- Input Kelengkapan Tugas -->
            <div id="assignmentInputSection" class="input-section">
                <h3>Input Kelengkapan Tugas</h3>
                <form id="assignmentForm">
                    <div class="form-group">
                        <label for="assignmentName">Nama Tugas</label>
                        <input type="text" id="assignmentName" placeholder="Contoh: Tugas Matematika Bab 1" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Siswa yang Telah Melengkapi Tugas:</label>
                        <div class="student-checkbox-list" id="studentCheckboxList">
                            <?php
                            if (empty($_SESSION['students'])) {
                                echo '<p style="text-align: center; color: #666;">Belum ada siswa</p>';
                            } else {
                                foreach ($_SESSION['students'] as $student) {
                                    $safeName = preg_replace('/\s+/', '-', $student['name']);
                                    echo '
                                    <div class="checkbox-item">
                                        <input type="checkbox" id="student-' . $safeName . '" value="' . htmlspecialchars($student['name']) . '">
                                        <label for="student-' . $safeName . '">' . htmlspecialchars($student['name']) . ' (' . $student['points'] . ' poin)</label>
                                    </div>';
                                }
                            }
                            ?>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-secondary">Simpan Data Tugas</button>
                </form>
            </div>
            
            <!-- Input Cepat Poin Keaktifan -->
            <div id="quickPointsInputSection" class="input-section">
                <div class="quick-points-section">
                    <div class="quick-points-header">
                        <h3>Input Cepat Poin Keaktifan</h3>
                        <button id="resetQuickPoints" class="btn-warning">Reset Pilihan</button>
                    </div>
                    
                    <p class="quick-points-instruction">
                        Klik nama siswa untuk memilih, lalu klik angka poin (1-10) untuk menambahkan poin keaktifan.
                    </p>
                    
                    <div class="quick-points-grid" id="quickPointsGrid">
                        <?php
                        if (empty($_SESSION['students'])) {
                            echo '<p style="text-align: center; color: #666; grid-column: 1 / -1;">Belum ada siswa</p>';
                        } else {
                            foreach ($_SESSION['students'] as $student) {
                                echo '
                                <div class="quick-points-student" data-student-name="' . htmlspecialchars($student['name']) . '">
                                    <div class="quick-points-student-name">' . htmlspecialchars($student['name']) . '</div>
                                    <div class="quick-points-student-points">' . $student['points'] . ' ★</div>
                                </div>';
                            }
                        }
                        ?>
                    </div>
                    
                    <div class="quick-points-buttons" id="quickPointsButtons">
                        <?php
                        for ($i = 1; $i <= 10; $i++) {
                            echo '<button class="quick-points-btn" data-points="' . $i . '">' . $i . '</button>';
                        }
                        ?>
                    </div>
                    
                    <div class="quick-points-actions">
                        <button id="applyQuickPoints" class="btn-secondary">Terapkan Poin</button>
                        <button id="cancelQuickPoints" class="logout-btn">Batal</button>
                    </div>
                </div>
            </div>
            
            <!-- Daftar Tugas yang Telah Dibuat -->
            <h3 style="margin-top: 30px;">Daftar Tugas Kelas</h3>
            <div id="assignmentsList">
                <?php
                if (empty($_SESSION['assignments'])) {
                    echo '<p style="text-align: center; color: #666;">Belum ada tugas yang dibuat</p>';
                } else {
                    foreach ($_SESSION['assignments'] as $assignment) {
                        $completionRate = count($_SESSION['students']) > 0 ? 
                            round((count($assignment['completedStudents']) / count($_SESSION['students'])) * 100) : 0;
                        
                        echo '
                        <div class="assignment-item">
                            <div class="assignment-header">
                                <div class="assignment-title">' . htmlspecialchars($assignment['title']) . '</div>
                                <div>
                                    <span class="assignment-date">' . $assignment['date'] . '</span>
                                    <span style="margin-left: 10px; color: ' . ($completionRate === 100 ? 'var(--secondary)' : 'var(--warning)') . '; font-weight: bold;">
                                        ' . $completionRate . '% selesai
                                    </span>
                                </div>
                            </div>
                            <div class="assignment-students">';
                        
                        if (!empty($assignment['completedStudents'])) {
                            foreach ($assignment['completedStudents'] as $student) {
                                echo '<span class="student-tag">' . htmlspecialchars($student) . '</span>';
                            }
                        } else {
                            echo '<span style="color: #666; font-style: italic;">Belum ada siswa yang menyelesaikan</span>';
                        }
                        
                        echo '
                            </div>
                            <div class="assignment-actions">
                                <button onclick="editAssignment(\'' . $assignment['id'] . '\')" class="btn-warning">Edit Judul</button>
                                <button onclick="deleteAssignment(\'' . $assignment['id'] . '\')" class="btn-danger">Hapus Tugas</button>
                            </div>
                        </div>';
                    }
                }
                ?>
            </div>
            
            <!-- Data Seluruh Siswa -->
            <h3 style="margin-top: 40px;">Data Seluruh Siswa</h3>
            <table id="studentTable">
                <thead>
                    <tr>
                        <th>Nama Siswa</th>
                        <th>Total Poin</th>
                        <th>Tugas Diselesaikan</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody id="studentTableBody">
                    <?php
                    if (empty($_SESSION['students'])) {
                        echo '
                        <tr>
                            <td colspan="4" style="text-align: center; color: #666;">Belum ada data siswa</td>
                        </tr>';
                    } else {
                        $totalAssignments = count($_SESSION['assignments']);
                        
                        foreach ($_SESSION['students'] as $student) {
                            $completedCount = isset($student['completedAssignments']) ? count($student['completedAssignments']) : 0;
                            
                            echo '
                            <tr>
                                <td>' . htmlspecialchars($student['name']) . '</td>
                                <td>' . $student['points'] . ' <span class="star">★</span></td>
                                <td class="' . ($completedCount === $totalAssignments ? 'completed' : 'incomplete') . '">
                                    ' . $completedCount . '/' . $totalAssignments . '
                                </td>
                                <td>
                                    <button onclick="editStudentPoints(\'' . $student['name'] . '\')" class="btn-warning">Edit Poin</button>
                                    <button onclick="removeStudent(\'' . $student['name'] . '\')" class="btn-danger">Hapus</button>
                                </td>
                            </tr>';
                        }
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Data siswa dan tugas (disimpan di session PHP)
        let students = <?php echo json_encode($_SESSION['students']); ?>;
        let assignments = <?php echo json_encode($_SESSION['assignments']); ?>;
        
        // Status login
        let isTeacherLoggedIn = <?php echo $isTeacherLoggedIn ? 'true' : 'false'; ?>;
        
        // Variabel untuk fitur input cepat poin
        let selectedStudentForQuickPoints = null;
        let selectedPointsValue = null;
        
        // Elemen DOM
        const loginBtn = document.getElementById('loginBtn');
        const studentView = document.getElementById('studentView');
        const teacherLoginSection = document.getElementById('teacherLoginSection');
        const teacherView = document.getElementById('teacherView');
        const backToStudentView = document.getElementById('backToStudentView');
        const teacherLoginForm = document.getElementById('teacherLoginForm');
        const logoutBtn = document.querySelector('form[action*="teacher_logout"] button');
        const downloadStudentsBtn = document.getElementById('downloadStudentsBtn');
        const downloadAssignmentsBtn = document.getElementById('downloadAssignmentsBtn');
        const downloadSummaryBtn = document.getElementById('downloadSummaryBtn');
        
        // Form elements
        const addStudentForm = document.getElementById('addStudentForm');
        const pointsForm = document.getElementById('pointsForm');
        const assignmentForm = document.getElementById('assignmentForm');
        const studentNamePoints = document.getElementById('studentNamePoints');
        const studentPoints = document.getElementById('studentPoints');
        const assignmentName = document.getElementById('assignmentName');
        const studentCheckboxList = document.getElementById('studentCheckboxList');
        const studentList = document.getElementById('studentList');
        const newStudentName = document.getElementById('newStudentName');
        
        // Display elements
        const studentTableBody = document.getElementById('studentTableBody');
        const pointsProgressList = document.getElementById('pointsProgressList');
        const assignmentsProgressList = document.getElementById('assignmentsProgressList');
        const assignmentsList = document.getElementById('assignmentsList');
        
        // Stat elements
        const teacherTotalStudentsElement = document.getElementById('teacherTotalStudents');
        const teacherAvgPointsElement = document.getElementById('teacherAvgPoints');
        const teacherCompletedTasksElement = document.getElementById('teacherCompletedTasks');
        
        // Tab buttons
        const tabBtns = document.querySelectorAll('.tab-btn');
        const progressSections = document.querySelectorAll('.progress-section');
        const inputModeBtns = document.querySelectorAll('.input-mode-btn');
        const inputSections = document.querySelectorAll('.input-section');
        
        // Elemen untuk fitur input cepat poin
        const quickPointsGrid = document.getElementById('quickPointsGrid');
        const quickPointsButtons = document.getElementById('quickPointsButtons');
        const applyQuickPoints = document.getElementById('applyQuickPoints');
        const cancelQuickPoints = document.getElementById('cancelQuickPoints');
        const resetQuickPoints = document.getElementById('resetQuickPoints');
        
        // Event Listeners
        if (loginBtn) {
            loginBtn.addEventListener('click', showTeacherLogin);
        }
        if (backToStudentView) {
            backToStudentView.addEventListener('click', showStudentView);
        }
        if (downloadStudentsBtn) {
            downloadStudentsBtn.addEventListener('click', downloadStudentsExcel);
        }
        if (downloadAssignmentsBtn) {
            downloadAssignmentsBtn.addEventListener('click', downloadAssignmentsExcel);
        }
        if (downloadSummaryBtn) {
            downloadSummaryBtn.addEventListener('click', downloadSummaryExcel);
        }
        if (pointsForm) {
            pointsForm.addEventListener('submit', handlePointsSubmit);
        }
        if (assignmentForm) {
            assignmentForm.addEventListener('submit', handleAssignmentSubmit);
        }
        
        // Event Listeners untuk fitur input cepat poin
        if (applyQuickPoints) {
            applyQuickPoints.addEventListener('click', handleApplyQuickPoints);
        }
        if (cancelQuickPoints) {
            cancelQuickPoints.addEventListener('click', handleCancelQuickPoints);
        }
        if (resetQuickPoints) {
            resetQuickPoints.addEventListener('click', handleResetQuickPoints);
        }
        
        // Tab switching untuk progress siswa
        tabBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const tabId = btn.getAttribute('data-tab');
                
                // Update active tab button
                tabBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                
                // Show corresponding section
                progressSections.forEach(section => {
                    section.classList.remove('active');
                    if (section.id === `${tabId}Progress`) {
                        section.classList.add('active');
                    }
                });
            });
        });
        
        // Tab switching untuk mode input guru
        inputModeBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const inputMode = btn.getAttribute('data-input-mode');
                
                // Update active tab button
                inputModeBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                
                // Show corresponding section
                inputSections.forEach(section => {
                    section.classList.remove('active');
                    if (section.id === `${inputMode}InputSection`) {
                        section.classList.add('active');
                    }
                });
                
                // Update form jika perlu
                if (inputMode === 'quick') {
                    renderQuickPointsGrid();
                    renderQuickPointsButtons();
                    resetQuickPointsSelection();
                }
            });
        });
        
        // Fungsi untuk menampilkan form login guru
        function showTeacherLogin() {
            studentView.classList.add('hidden');
            teacherLoginSection.classList.remove('hidden');
        }
        
        // Fungsi kembali ke tampilan murid
        function showStudentView() {
            teacherLoginSection.classList.add('hidden');
            teacherView.classList.add('hidden');
            studentView.classList.remove('hidden');
        }
        
        // Fungsi download data siswa
        function downloadStudentsExcel() {
            if (students.length === 0) {
                alert('Tidak ada data siswa untuk diunduh!');
                return;
            }
            
            const worksheetData = [
                ['Nama Siswa', 'Total Poin', 'Tugas Diselesaikan', 'Total Tugas', 'Persentase Tugas']
            ];
            
            const totalAssignments = assignments.length;
            
            students.forEach(student => {
                const completedAssignments = student.completedAssignments ? student.completedAssignments.length : 0;
                const assignmentPercentage = totalAssignments > 0 ? 
                    Math.round((completedAssignments / totalAssignments) * 100) : 0;
                
                worksheetData.push([
                    student.name,
                    student.points,
                    completedAssignments,
                    totalAssignments,
                    `${assignmentPercentage}%`
                ]);
            });
            
            const worksheet = XLSX.utils.aoa_to_sheet(worksheetData);
            const workbook = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(workbook, worksheet, 'Data Siswa');
            
            // Download file
            const fileName = `Data_Siswa_Kelas_${new Date().toISOString().split('T')[0]}.xlsx`;
            XLSX.writeFile(workbook, fileName);
            
            alert('Data siswa berhasil diunduh!');
        }
        
        // Fungsi download data tugas
        function downloadAssignmentsExcel() {
            if (assignments.length === 0) {
                alert('Tidak ada data tugas untuk diunduh!');
                return;
            }
            
            const worksheetData = [
                ['Nama Tugas', 'Tanggal', 'Jumlah Siswa Selesai', 'Total Siswa', 'Persentase Penyelesaian', 'Siswa yang Menyelesaikan']
            ];
            
            assignments.forEach(assignment => {
                const completionRate = Math.round((assignment.completedStudents.length / students.length) * 100);
                const studentsList = assignment.completedStudents.join(', ');
                
                worksheetData.push([
                    assignment.title,
                    assignment.date,
                    assignment.completedStudents.length,
                    students.length,
                    `${completionRate}%`,
                    studentsList || '-'
                ]);
            });
            
            const worksheet = XLSX.utils.aoa_to_sheet(worksheetData);
            const workbook = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(workbook, worksheet, 'Data Tugas');
            
            // Download file
            const fileName = `Data_Tugas_Kelas_${new Date().toISOString().split('T')[0]}.xlsx`;
            XLSX.writeFile(workbook, fileName);
            
            alert('Data tugas berhasil diunduh!');
        }
        
        // Fungsi download ringkasan kelas
        function downloadSummaryExcel() {
            if (students.length === 0) {
                alert('Tidak ada data untuk diunduh!');
                return;
            }
            
            // Buat workbook dengan multiple sheets
            const workbook = XLSX.utils.book_new();
            
            // Sheet 1: Ringkasan Kelas
            const summaryData = [
                ['RINGKASAN KELAS'],
                [''],
                ['Total Siswa', students.length],
                ['Rata-rata Poin', calculateAveragePoints()],
                ['Total Tugas', assignments.length],
                ['Rata-rata Penyelesaian Tugas', `${calculateAverageAssignmentCompletion()}%`],
                ['Siswa dengan Poin Tertinggi', getTopStudent()],
                [''],
                ['STATISTIK DETAIL'],
                ['Poin Tertinggi', getMaxPoints()],
                ['Poin Terendah', getMinPoints()],
                ['Siswa Aktif (poin > 0)', getActiveStudentsCount()],
                ['Tugas dengan Penyelesaian Tertinggi', getBestAssignment()],
                ['Tugas dengan Penyelesaian Terendah', getWorstAssignment()]
            ];
            
            const summarySheet = XLSX.utils.aoa_to_sheet(summaryData);
            XLSX.utils.book_append_sheet(workbook, summarySheet, 'Ringkasan Kelas');
            
            // Sheet 2: Data Siswa
            const studentsData = [
                ['Nama Siswa', 'Total Poin', 'Tugas Diselesaikan', 'Total Tugas', 'Persentase Tugas', 'Status']
            ];
            
            const totalAssignments = assignments.length;
            
            students.forEach(student => {
                const completedAssignments = student.completedAssignments ? student.completedAssignments.length : 0;
                const assignmentPercentage = totalAssignments > 0 ? 
                    Math.round((completedAssignments / totalAssignments) * 100) : 0;
                const status = assignmentPercentage === 100 ? 'Lengkap' : 
                              assignmentPercentage >= 70 ? 'Baik' :
                              assignmentPercentage >= 50 ? 'Cukup' : 'Perlu Perhatian';
                
                studentsData.push([
                    student.name,
                    student.points,
                    completedAssignments,
                    totalAssignments,
                    `${assignmentPercentage}%`,
                    status
                ]);
            });
            
            const studentsSheet = XLSX.utils.aoa_to_sheet(studentsData);
            XLSX.utils.book_append_sheet(workbook, studentsSheet, 'Data Siswa');
            
            // Sheet 3: Data Tugas
            if (assignments.length > 0) {
                const assignmentsData = [
                    ['Nama Tugas', 'Tanggal', 'Siswa Selesai', 'Total Siswa', 'Persentase']
                ];
                
                assignments.forEach(assignment => {
                    const completionRate = Math.round((assignment.completedStudents.length / students.length) * 100);
                    
                    assignmentsData.push([
                        assignment.title,
                        assignment.date,
                        assignment.completedStudents.length,
                        students.length,
                        `${completionRate}%`
                    ]);
                });
                
                const assignmentsSheet = XLSX.utils.aoa_to_sheet(assignmentsData);
                XLSX.utils.book_append_sheet(workbook, assignmentsSheet, 'Data Tugas');
            }
            
            // Download file
            const fileName = `Laporan_Kelas_${new Date().toISOString().split('T')[0]}.xlsx`;
            XLSX.writeFile(workbook, fileName);
            
            alert('Laporan kelas berhasil diunduh!');
        }
        
        // Fungsi helper untuk ringkasan
        function calculateAveragePoints() {
            const totalPoints = students.reduce((sum, student) => sum + student.points, 0);
            return students.length > 0 ? (totalPoints / students.length).toFixed(1) : 0;
        }
        
        function calculateAverageAssignmentCompletion() {
            const totalAssignments = assignments.length;
            if (totalAssignments === 0) return 0;
            
            const totalCompleted = students.reduce((sum, student) => 
                sum + (student.completedAssignments ? student.completedAssignments.length : 0), 0);
            
            return Math.round((totalCompleted / (students.length * totalAssignments)) * 100);
        }
        
        function getTopStudent() {
            if (students.length === 0) return '-';
            const topStudent = students.reduce((prev, current) => 
                (prev.points > current.points) ? prev : current);
            return `${topStudent.name} (${topStudent.points} poin)`;
        }
        
        function getMaxPoints() {
            return students.length > 0 ? Math.max(...students.map(s => s.points)) : 0;
        }
        
        function getMinPoints() {
            return students.length > 0 ? Math.min(...students.map(s => s.points)) : 0;
        }
        
        function getActiveStudentsCount() {
            return students.filter(s => s.points > 0).length;
        }
        
        function getBestAssignment() {
            if (assignments.length === 0) return '-';
            const bestAssignment = assignments.reduce((prev, current) => 
                (prev.completedStudents.length > current.completedStudents.length) ? prev : current);
            return `${bestAssignment.title} (${Math.round((bestAssignment.completedStudents.length / students.length) * 100)}%)`;
        }
        
        function getWorstAssignment() {
            if (assignments.length === 0) return '-';
            const worstAssignment = assignments.reduce((prev, current) => 
                (prev.completedStudents.length < current.completedStudents.length) ? prev : current);
            return `${worstAssignment.title} (${Math.round((worstAssignment.completedStudents.length / students.length) * 100)}%)`;
        }
        
        // Fungsi handle input poin
        function handlePointsSubmit(e) {
            e.preventDefault();
            
            const studentName = studentNamePoints.value;
            const points = parseInt(studentPoints.value);
            
            if (!studentName) {
                alert('Pilih siswa terlebih dahulu!');
                return;
            }
            
            // Kirim data ke server menggunakan AJAX
            const formData = new FormData();
            formData.append('action', 'add_points');
            formData.append('studentName', studentName);
            formData.append('points', points);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                // Refresh halaman untuk memperbarui data
                window.location.reload();
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat menambahkan poin!');
            });
        }
        
        // Fungsi handle input tugas
        function handleAssignmentSubmit(e) {
            e.preventDefault();
            
            const assignmentTitle = assignmentName.value;
            const completedStudents = [];
            
            // Ambil siswa yang dicentang
            const checkboxes = studentCheckboxList.querySelectorAll('input[type="checkbox"]:checked');
            checkboxes.forEach(checkbox => {
                completedStudents.push(checkbox.value);
            });
            
            if (!assignmentTitle) {
                alert('Masukkan nama tugas terlebih dahulu!');
                return;
            }
            
            if (completedStudents.length === 0) {
                alert('Pilih setidaknya satu siswa yang telah menyelesaikan tugas!');
                return;
            }
            
            // Kirim data ke server menggunakan AJAX
            const formData = new FormData();
            formData.append('action', 'add_assignment');
            formData.append('assignmentTitle', assignmentTitle);
            formData.append('completedStudents', JSON.stringify(completedStudents));
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                // Refresh halaman untuk memperbarui data
                window.location.reload();
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat menambahkan tugas!');
            });
        }
        
        // Fungsi untuk fitur input cepat poin
        function renderQuickPointsGrid() {
            // Reset selection
            resetQuickPointsSelection();
        }
        
        function renderQuickPointsButtons() {
            // Tombol sudah di-render di PHP
        }
        
        function handleApplyQuickPoints() {
            if (!selectedStudentForQuickPoints) {
                alert('Pilih siswa terlebih dahulu!');
                return;
            }
            
            if (!selectedPointsValue) {
                alert('Pilih jumlah poin terlebih dahulu!');
                return;
            }
            
            // Kirim data ke server menggunakan AJAX
            const formData = new FormData();
            formData.append('action', 'add_quick_points');
            formData.append('studentName', selectedStudentForQuickPoints);
            formData.append('points', selectedPointsValue);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                // Refresh halaman untuk memperbarui data
                window.location.reload();
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat menambahkan poin!');
            });
        }
        
        function handleCancelQuickPoints() {
            resetQuickPointsSelection();
        }
        
        function handleResetQuickPoints() {
            resetQuickPointsSelection();
        }
        
        function resetQuickPointsSelection() {
            selectedStudentForQuickPoints = null;
            selectedPointsValue = null;
            
            // Reset UI
            document.querySelectorAll('.quick-points-student.active').forEach(card => {
                card.classList.remove('active');
            });
            
            document.querySelectorAll('.quick-points-btn.active').forEach(btn => {
                btn.classList.remove('active');
            });
        }
        
        // Event listener untuk quick points grid
        if (quickPointsGrid) {
            quickPointsGrid.addEventListener('click', (e) => {
                const studentCard = e.target.closest('.quick-points-student');
                if (studentCard) {
                    // Toggle selection
                    if (selectedStudentForQuickPoints === studentCard.dataset.studentName) {
                        // Deselect if already selected
                        selectedStudentForQuickPoints = null;
                        studentCard.classList.remove('active');
                    } else {
                        // Deselect previous selection
                        document.querySelectorAll('.quick-points-student.active').forEach(card => {
                            card.classList.remove('active');
                        });
                        
                        // Select current
                        selectedStudentForQuickPoints = studentCard.dataset.studentName;
                        studentCard.classList.add('active');
                    }
                }
            });
        }
        
        // Event listener untuk quick points buttons
        if (quickPointsButtons) {
            quickPointsButtons.addEventListener('click', (e) => {
                if (e.target.classList.contains('quick-points-btn')) {
                    const points = parseInt(e.target.dataset.points);
                    
                    // Toggle selection
                    if (selectedPointsValue === points) {
                        // Deselect if already selected
                        selectedPointsValue = null;
                        e.target.classList.remove('active');
                    } else {
                        // Deselect previous selection
                        document.querySelectorAll('.quick-points-btn.active').forEach(btn => {
                            btn.classList.remove('active');
                        });
                        
                        // Select current
                        selectedPointsValue = points;
                        e.target.classList.add('active');
                    }
                }
            });
        }
        
        // Fungsi untuk menampilkan form edit siswa
        function showEditStudentForm(studentName) {
            const safeName = studentName.replace(/\s+/g, '-');
            const editForm = document.getElementById(`edit-form-${safeName}`);
            editForm.classList.add('active');
        }
        
        // Fungsi untuk menyembunyikan form edit siswa
        function hideEditStudentForm(studentName) {
            const safeName = studentName.replace(/\s+/g, '-');
            const editForm = document.getElementById(`edit-form-${safeName}`);
            editForm.classList.remove('active');
        }
        
        // Fungsi untuk update nama siswa
        function updateStudentName(oldName) {
            const safeName = oldName.replace(/\s+/g, '-');
            const newName = document.getElementById(`edit-student-${safeName}`).value.trim();
            
            if (!newName) {
                alert('Nama siswa tidak boleh kosong!');
                return;
            }
            
            // Kirim data ke server menggunakan AJAX
            const formData = new FormData();
            formData.append('action', 'update_student_name');
            formData.append('oldName', oldName);
            formData.append('newName', newName);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                // Refresh halaman untuk memperbarui data
                window.location.reload();
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat mengubah nama siswa!');
            });
        }
        
        // Fungsi hapus siswa
        function removeStudent(name) {
            if (confirm(`Apakah Anda yakin ingin menghapus siswa "${name}"?`)) {
                // Kirim data ke server menggunakan AJAX
                const formData = new FormData();
                formData.append('action', 'remove_student');
                formData.append('studentName', name);
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(data => {
                    // Refresh halaman untuk memperbarui data
                    window.location.reload();
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Terjadi kesalahan saat menghapus siswa!');
                });
            }
        }
        
        // Fungsi edit poin siswa
        function editStudentPoints(name) {
            const student = students.find(s => s.name === name);
            
            if (student) {
                const newPoints = prompt(`Edit poin untuk ${name}:`, student.points);
                if (newPoints !== null && !isNaN(newPoints)) {
                    // Kirim data ke server menggunakan AJAX
                    const formData = new FormData();
                    formData.append('action', 'update_student_points');
                    formData.append('studentName', name);
                    formData.append('points', newPoints);
                    
                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.text())
                    .then(data => {
                        // Refresh halaman untuk memperbarui data
                        window.location.reload();
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Terjadi kesalahan saat mengubah poin siswa!');
                    });
                }
            }
        }
        
        // Fungsi edit tugas
        function editAssignment(assignmentId) {
            const assignment = assignments.find(a => a.id === assignmentId);
            if (assignment) {
                const newTitle = prompt('Edit nama tugas:', assignment.title);
                if (newTitle !== null && newTitle.trim() !== '') {
                    // Kirim data ke server menggunakan AJAX
                    const formData = new FormData();
                    formData.append('action', 'update_assignment');
                    formData.append('assignmentId', assignmentId);
                    formData.append('newTitle', newTitle.trim());
                    
                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.text())
                    .then(data => {
                        // Refresh halaman untuk memperbarui data
                        window.location.reload();
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Terjadi kesalahan saat mengubah tugas!');
                    });
                }
            }
        }
        
        // Fungsi hapus tugas
        function deleteAssignment(assignmentId) {
            if (confirm('Apakah Anda yakin ingin menghapus tugas ini?')) {
                // Kirim data ke server menggunakan AJAX
                const formData = new FormData();
                formData.append('action', 'remove_assignment');
                formData.append('assignmentId', assignmentId);
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(data => {
                    // Refresh halaman untuk memperbarui data
                    window.location.reload();
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Terjadi kesalahan saat menghapus tugas!');
                });
            }
        }
    </script>
</body>
</html>