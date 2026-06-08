<?php
require __DIR__ . '/db.php';

if (!is_logged_in()) {
    redirect_to('login.php');
}

$db = db();
$courseId = (int) ($_GET['course_id'] ?? $_POST['course_id'] ?? 0);
$course = $courseId ? course_by_id($db, $courseId) : null;

if (!$course && isset($_GET['course'])) {
    $course = course_by_title($db, trim($_GET['course']));
    $courseId = (int) ($course['id'] ?? 0);
}

if (!$course) {
    redirect_to('index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ensure_enrollment($db, $courseId);
    redirect_to('student-dashboard.php?course_id=' . $courseId . '&message=enrolled');
}

$isEnrolled = enrollment_for_course($db, $courseId) !== null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($course['title']) ?> | EARIST Course Enrollment</title>
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
      <p class="eyebrow">Selected Course</p>
      <h1><?= e($course['title']) ?></h1>
      <p class="lead"><?= e($course['description']) ?></p>
    </div>
  </section>

  <main class="main">
    <section class="dashboard">
      <div class="panel">
        <h2>Course Details</h2>
        <div class="info-grid">
          <div class="stat"><strong>Free</strong><span>Fee</span></div>
          <div class="stat" style="min-width: 120px; flex: 1;"><strong style="font-size: 0.9rem; white-space: nowrap;"><?= e($course['level']) ?></strong><span>Level</span></div>
          <div class="stat"><strong><?= e($course['rating']) ?></strong><span>Rating</span></div>
        </div>
        <p><?= e($course['meta']) ?></p>
        <p>Instructor: <strong><?= e($course['instructor']) ?></strong></p>
        <div class="actions">
          <?php if ($isEnrolled): ?>
            <a class="btn primary" href="student-dashboard.php?course_id=<?= (int) $course['id'] ?>">Open Dashboard</a>
          <?php else: ?>
            <form method="post">
              <input type="hidden" name="course_id" value="<?= (int) $course['id'] ?>">
              <button class="btn primary" type="submit">Confirm Enrollment</button>
            </form>
          <?php endif; ?>
          <a class="btn" href="index.php#courses">Back to Courses</a>
        </div>
      </div>
      <div class="panel">
        <h2>What You Will Do</h2>
        <div class="lesson-list">
          <div class="lesson"><span><strong>1. Interactive Video</strong>Watch high-quality web-based lectures.</span></div>
          <div class="lesson"><span><strong>2. Downloadable Notes</strong>Get PDF/HTML modules for offline study.</span></div>
          <div class="lesson"><span><strong>3. Graded Quizzes</strong>Test your knowledge with instant statistics.</span></div>
          <div class="lesson"><span><strong>4. Digital Certification</strong>Earn a verifiable certificate upon completion.</span></div>
          <div class="lesson"><span><strong>5. Complete final check</strong>Prepare for certification.</span></div>
        </div>
      </div>
    </section>
  </main>
</body>
</html>
