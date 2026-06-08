<?php
require __DIR__ . '/db.php';
if (!is_logged_in()) redirect_to('login.php');

$db = db();
$attemptId = (int)$_GET['attempt_id'];
$userId = get_user_id();

// Fetch attempt and verify ownership
$stmt = $db->prepare("SELECT qa.*, c.title FROM quiz_attempts qa JOIN courses c ON qa.course_id = c.id WHERE qa.id = ?");
$stmt->bind_param('i', $attemptId);
$stmt->execute();
$attempt = $stmt->get_result()->fetch_assoc();

if (!$attempt || ((int)$attempt['user_id'] !== $userId && $_SESSION['role'] !== 'admin')) {
    die("Unauthorized access to results.");
}

$responses = get_quiz_responses($db, $attemptId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><title>Quiz Results: <?= e($attempt['title']) ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        .res-item { padding: 15px; border-radius: 8px; margin-bottom: 15px; border: 1px solid var(--line); }
        .res-correct { border-left: 5px solid #27ae60; background: #fafffa; }
        .res-wrong { border-left: 5px solid #c0392b; background: #fffafa; }
        .label-ans { font-weight: bold; font-size: 0.9rem; }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="nav"><a class="brand" href="index.php"><span>EARIST Quiz Results</span></a></div>
    </header>
    <main class="main" style="max-width: 800px;">
        <div class="panel" style="text-align: center; margin-bottom: 30px;">
            <h1>Your Results</h1>
            <div class="progress-number"><?= $attempt['score'] ?> / <?= $attempt['total_items'] ?></div>
            <p>Course: <strong><?= e($attempt['title']) ?></strong></p>
            <a href="student-dashboard.php?course_id=<?= $attempt['course_id'] ?>" class="btn primary">Return to Dashboard</a>
        </div>

        <h2>Review Answers</h2>
        <?php foreach ($responses as $index => $r): ?>
            <div class="res-item <?= $r['is_correct'] ? 'res-correct' : 'res-wrong' ?>">
                <p><strong><?= $index + 1 ?>. <?= e($r['question']) ?></strong></p>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div>
                        <span class="label-ans">Your Answer:</span><br>
                        Option <?= $r['selected_option'] ?>: 
                        <?php 
                            $optKey = 'option_' . strtolower($r['selected_option']);
                            echo e($r[$optKey] ?? 'No answer');
                        ?>
                    </div>
                    <div>
                        <span class="label-ans">Correct Answer:</span><br>
                        Option <?= $r['correct_option'] ?>: 
                        <?php 
                            $corKey = 'option_' . strtolower($r['correct_option']);
                            echo e($r[$corKey]);
                        ?>
                    </div>
                </div>
                <div style="margin-top: 10px; font-weight: bold; color: <?= $r['is_correct'] ? '#27ae60' : '#c0392b' ?>;">
                    <?= $r['is_correct'] ? '✓ Correct' : '✗ Incorrect' ?>
                </div>
            </div>
        <?php endforeach; ?>
        
        <div style="margin-top: 30px; text-align: center;">
            <a href="student-dashboard.php?course_id=<?= $attempt['course_id'] ?>" class="btn">Back to Dashboard</a>
        </div>
    </main>
</body>
</html>