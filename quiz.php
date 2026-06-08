<?php
require __DIR__ . '/db.php';
if (!is_logged_in()) redirect_to('login.php');

$db = db();
$courseId = (int)$_GET['course_id'];
$userId = get_user_id();

// Prevent retakes if already submitted
$existing = get_quiz_attempt($db, $userId, $courseId);
if ($existing) redirect_to('student-dashboard.php?course_id=' . $courseId);

$course = course_by_id($db, $courseId);
$questions = get_course_questions($db, $courseId);

if (empty($questions)) {
    die("Quiz questions for this course have not been set up yet. Please contact the administrator.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $score = 0;
    $responses = [];
    
    foreach ($questions as $q) {
        $answer = $_POST['q' . $q['id']] ?? '';
        $isCorrect = ($answer === $q['correct_option']) ? 1 : 0;
        if ($isCorrect) $score++;
        $responses[] = ['id' => $q['id'], 'answer' => $answer, 'correct' => $isCorrect];
    }
    
    $total = count($questions);
    $stmt = $db->prepare("INSERT INTO quiz_attempts (user_id, course_id, score, total_items, status) VALUES (?, ?, ?, ?, 'pending')");
    $stmt->bind_param('iiii', $userId, $courseId, $score, $total);
    $stmt->execute();
    $attemptId = $db->insert_id;

    $respStmt = $db->prepare("INSERT INTO quiz_responses (attempt_id, question_id, selected_option, is_correct) VALUES (?, ?, ?, ?)");
    foreach ($responses as $r) {
        $respStmt->bind_param('iisi', $attemptId, $r['id'], $r['answer'], $r['correct']);
        $respStmt->execute();
    }
    
    redirect_to('quiz-results.php?attempt_id=' . $attemptId);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><title>Quiz: <?= e($course['title']) ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        .quiz-item { margin-bottom: 25px; padding: 15px; border-bottom: 1px solid #eee; }
        .options label { display: block; margin: 8px 0; cursor: pointer; }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="nav"><a class="brand" href="index.php"><span>EARIST Quiz Center</span></a></div>
    </header>
    <main class="main" style="max-width: 800px;">
        <h1><?= e($course['title']) ?>: Comprehensive Quiz</h1>
        <p class="muted">Please answer all 30 questions. Your results will be available after faculty review.</p>
        
        <form method="POST" class="panel">
            <?php foreach ($questions as $index => $q): ?>
                <div class="quiz-item">
                    <p><strong><?= $index + 1 ?>. <?= e($q['question']) ?></strong></p>
                    <div class="options">
                        <label><input type="radio" name="q<?= $q['id'] ?>" value="A" required> <?= e($q['option_a']) ?></label>
                        <label><input type="radio" name="q<?= $q['id'] ?>" value="B"> <?= e($q['option_b']) ?></label>
                        <label><input type="radio" name="q<?= $q['id'] ?>" value="C"> <?= e($q['option_c']) ?></label>
                        <label><input type="radio" name="q<?= $q['id'] ?>" value="D"> <?= e($q['option_d']) ?></label>
                    </div>
                </div>
            <?php endforeach; ?>
            <button type="submit" class="btn primary" style="width: 100%; margin-top: 20px;">Submit Final Answers</button>
        </form>
    </main>
</body>
</html>
