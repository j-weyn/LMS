<?php
require __DIR__ . '/db.php';
$courseId = (int)$_GET['course_id'];
$course = course_by_id(db(), $courseId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><title>Module Notes: <?= e($course['title']) ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body style="padding: 50px; line-height: 1.6;">
    <div style="max-width: 800px; margin: 0 auto; background: white; padding: 40px; border: 1px solid #ddd;">
        <a href="student-dashboard.php?course_id=<?= $courseId ?>">← Back to Dashboard</a>
        <h1><?= e($course['title']) ?>: Study Guide</h1>
        <p>This module covers the fundamental concepts of <?= e($course['category']) ?> as taught at EARIST.</p>
        <div style="background: #f9f9f9; padding: 20px; border-left: 5px solid #004b23; margin: 20px 0;">
            <h3>Key Takeaways</h3>
            <p><?= e($course['description']) ?></p>
        </div>
        <button onclick="alert('Download started...');" class="btn primary">Download Full PDF Module</button>
    </div>
</body>
</html>