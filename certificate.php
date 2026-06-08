<?php
require __DIR__ . '/db.php';

if (!is_logged_in()) {
    redirect_to('login.php');
}

$db = db();
$courseId = (int)$_GET['course_id'];
$enrollment = enrollment_for_course($db, $courseId);
if (!$enrollment || $enrollment['progress'] < 100) exit('Course not completed yet.');
$course = course_by_id($db, $courseId);

$user = $db->query("SELECT full_name FROM users WHERE id = " . get_user_id())->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Certificate of Completion</title>
    <style>
        body { text-align: center; padding: 50px; font-family: 'Segoe UI', serif; background: #555; }
        .cert { background: white; border: 15px double #004b23; padding: 50px; width: 800px; margin: 0 auto; box-shadow: 0 0 20px rgba(0,0,0,0.5); position: relative; }
        .logo { width: 100px; }
        h1 { font-size: 3rem; color: #004b23; margin: 20px 0; }
        .name { font-size: 2.5rem; text-decoration: underline; margin: 30px 0; font-style: italic; }
    </style>
</head>
<body>
    <div class="cert">
        <img src="https://earist.edu.ph/wp-content/uploads/earist-logo-1.png" class="logo">
        <p>EULOGIO "AMANG" RODRIGUEZ INSTITUTE OF SCIENCE AND TECHNOLOGY</p>
        <p>This certifies that</p>
        <div class="name"><?= e($user['full_name'] ?? 'Student') ?></div>
        <p>has successfully completed the online course</p>
        <h1><?= e($course['title']) ?></h1>
        <p>Issued on <?= date('F j, Y') ?> via EARIST Learn Manila</p>
        <button onclick="window.print()" style="margin-top: 40px;" class="no-print">Print Certificate</button>
    </div>
</body>
</html>