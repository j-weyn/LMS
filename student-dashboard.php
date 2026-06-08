<?php
require __DIR__ . '/db.php';

if (!is_logged_in()) {
    redirect_to('login.php');
}

$db = db();
$allEnrollments = enrolled_courses($db);
$courseId = (int) ($_GET['course_id'] ?? $_POST['course_id'] ?? 0);

if (!$courseId && isset($_GET['course'])) {
    $legacyCourse = course_by_title($db, trim($_GET['course']));
    $courseId = (int) ($legacyCourse['id'] ?? 0);
}

$courseId = $courseId ?: (int) ($allEnrollments[0]['course_id'] ?? 0);

if (!$courseId) {
    redirect_to('index.php');
}

$course = course_by_id($db, $courseId);
if (!$course) {
    redirect_to('index.php');
}

$enrollmentId = ensure_enrollment($db, $courseId);

$lessons = [
    'lesson-video' => ['Watch the lesson video', 'Review the recorded lecture and take notes.'],
    'reading-notes' => ['Read the module notes', 'Study the downloadable class material.'],
    'short-quiz' => ['Answer the short quiz', 'Check your understanding before the activity.'],
    'submit-activity' => ['Submit the activity', 'Upload your work for faculty review.'],
    'final-check' => ['Complete final check', 'Review your output and prepare for certification.'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $completed = array_intersect(array_keys($lessons), $_POST['lessons'] ?? []);
    $db->begin_transaction();

    $delete = $db->prepare('DELETE FROM lesson_progress WHERE enrollment_id = ?');
    $delete->bind_param('i', $enrollmentId);
    $delete->execute();

    $insert = $db->prepare('INSERT INTO lesson_progress (enrollment_id, lesson_key, completed) VALUES (?, ?, 1)');
    foreach ($completed as $lessonKey) {
        $insert->bind_param('is', $enrollmentId, $lessonKey);
        $insert->execute();
    }

    $progress = (int) round((count($completed) / count($lessons)) * 100);
    $status = $progress === 100 ? 'Completed' : 'In progress';
    $update = $db->prepare('UPDATE enrollments SET progress = ?, status = ? WHERE id = ?');
    $update->bind_param('isi', $progress, $status, $enrollmentId);
    $update->execute();

    $db->commit();
    redirect_to('student-dashboard.php?course_id=' . $courseId . '&message=progress');
}

$enrollment = enrollment_for_course($db, $courseId);
$completed = completed_lessons($db, $enrollmentId);
$progress = (int) ($enrollment['progress'] ?? 0);
$quizAttempt = get_quiz_attempt($db, get_user_id(), $courseId);
$message = flash_message();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($course['title']) ?> | Student Dashboard</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <header class="site-header">
    <nav class="nav" aria-label="Main navigation">
      <a class="brand" href="index.php">
        <img src="https://earist.edu.ph/wp-content/uploads/earist-logo-1.png" alt="EARIST seal">
        <span>EARIST Learn</span>
      </a>
      <div class="nav-links">
        <a href="index.php">Course Catalog</a>
        <a href="my-courses.php">My Courses</a>
      </div>
    </nav>
  </header>

  <section class="hero">
    <div class="hero-inner hero-simple">
      <p class="eyebrow">Course Workspace</p>
      <h1><?= e($course['title']) ?></h1>
      <p class="lead">Track lessons, open learning tools, and update progress saved in MySQL.</p>
    </div>
  </section>

  <main class="main">
    <?php if ($message): ?><div class="notice"><?= e($message) ?></div><?php endif; ?>

    <section class="dashboard">
      <aside class="panel">
        <h2>Your Progress</h2>
        <div class="progress-number"><?= $progress ?>%</div>
        <div class="progress-track" aria-hidden="true"><div class="progress-fill" style="width: <?= $progress ?>%"></div></div>
        <p><?= $progress === 100 ? 'All lesson tasks are complete. Certificate status is ready.' : count($completed) . ' of ' . count($lessons) . ' lesson tasks completed.' ?></p>
        <a class="btn primary" href="my-courses.php">Back to My Courses</a>
        <div class="quick-grid" style="margin-top: 18px;">
          <div class="stat"><strong>Due</strong><span>Module activity this week</span></div>
          <div class="stat"><strong><?= $progress === 100 ? 'Ready' : 'Locked' ?></strong><span>Certificate</span></div>
          <div class="stat"><strong>Quiz</strong><span>Practice quiz after lesson 2</span></div>
          <div class="stat"><strong><?= e($course['level']) ?></strong><span>Level</span></div>
        </div>
      </aside>

      <section class="panel">
        <div class="video-container" style="margin-bottom: 24px; background: #000; aspect-ratio: 16/9; border-radius: 8px; overflow: hidden;">
            <iframe width="100%" height="100%" src="<?= e($course['video_url'] ?: 'https://www.youtube.com/embed/dQw4w9WgXcQ') ?>" title="Lesson Video" frameborder="0" allowfullscreen></iframe>
        </div>

        <h2>Lessons</h2>
        <p>Check each item as you finish it, then save your progress.</p>
        <form method="post">
          <input type="hidden" name="course_id" value="<?= (int) $course['id'] ?>">
          <div class="lesson-list">
            <?php foreach ($lessons as $key => [$title, $text]): ?>
              <label class="lesson" style="display: flex; align-items: flex-start; gap: 12px; padding: 12px; border-bottom: 1px solid #eee;">
                <input type="checkbox" name="lessons[]" value="<?= e($key) ?>" <?= in_array($key, $completed, true) ? 'checked' : '' ?>>
                <span>
                    <strong><?= e($title) ?></strong><br>
                    <small style="color: #666;"><?= e($text) ?></small>
                    <?php if ($key === 'reading-notes'): ?>
                        <br><a href="module-view.php?course_id=<?= $courseId ?>" target="_blank" style="font-size: 0.8rem; color: #007bff;">Open & Download Module</a>
                    <?php endif; ?>
                    <?php if ($key === 'short-quiz'): ?>
                        <br>
                        <?php if (!$quizAttempt): ?>
                            <a href="quiz.php?course_id=<?= $courseId ?>" style="font-size: 0.8rem; color: #007bff;">Take 30-Item Quiz</a>
                        <?php elseif ($quizAttempt['status'] === 'pending'): ?>
                            <span style="font-size: 0.8rem; color: #f39c12;">Quiz Submitted (Awaiting Faculty Review)</span>
                        <?php else: ?>
                            <span style="font-size: 0.8rem; color: #27ae60;">Quiz Result: <strong><?= $quizAttempt['score'] ?>/<?= $quizAttempt['total_items'] ?></strong></span>
                        <?php endif; ?>
                    <?php endif; ?>
                </span>
              </label>
            <?php endforeach; ?>
          </div>
          <div class="actions" style="margin-top: 16px;">
            <button class="btn primary" type="submit">Save Progress</button>
          </div>
        </form>
      </section>
    </section>
  </main>
</body>
</html>
