<?php
require __DIR__ . '/db.php';

if (!is_logged_in()) {
    redirect_to('login.php');
}

$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $userId = get_user_id();

    if ($action === 'remove') {
        $courseId = (int) ($_POST['course_id'] ?? 0);
        $stmt = $db->prepare('DELETE FROM enrollments WHERE user_id = ? AND course_id = ?');
        $stmt->bind_param('ii', $userId, $courseId);
        $stmt->execute();
        redirect_to('my-courses.php?message=removed');
    }

    if ($action === 'clear') {
        $stmt = $db->prepare('DELETE FROM enrollments WHERE user_id = ?');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        redirect_to('my-courses.php?message=cleared');
    }
}

$courses = enrolled_courses($db);
$totalProgress = array_sum(array_map(fn ($course) => (int) $course['progress'], $courses));
$average = count($courses) ? (int) round($totalProgress / count($courses)) : 0;
$message = flash_message();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Courses | EARIST Learn Manila</title>
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
        <a href="student-dashboard.php">Dashboard</a>
      </div>
    </nav>
  </header>

  <section class="hero">
    <div class="hero-inner hero-simple">
      <p class="eyebrow">Student Learning Area</p>
      <h1>My Enrolled Courses</h1>
      <p class="lead">See confirmed courses, continue lessons, and manage saved database enrollments.</p>
    </div>
  </section>

  <main class="main">
    <?php if ($message): ?><div class="notice"><?= e($message) ?></div><?php endif; ?>

    <section class="summary" aria-label="Enrollment summary">
      <div class="stat"><strong><?= count($courses) ?></strong><span>Enrolled courses</span></div>
      <div class="stat"><strong><?= count(array_filter($courses, fn ($course) => (int) $course['progress'] < 100)) ?></strong><span>In progress</span></div>
      <div class="stat"><strong><?= $average ?>%</strong><span>Average progress</span></div>
    </section>

    <div class="toolbar">
      <h2>Course List</h2>
      <?php if ($courses): ?>
        <form method="post">
          <input type="hidden" name="action" value="clear">
          <button class="btn danger" type="submit">Clear My Courses</button>
        </form>
      <?php endif; ?>
    </div>

    <?php if (!$courses): ?>
      <div class="empty">You have not enrolled in a course yet. Go back to the course catalog and confirm an enrollment.</div>
    <?php else: ?>
      <section class="course-list" aria-label="Enrolled courses">
        <?php foreach ($courses as $course): ?>
          <article class="course-card">
            <div class="course-top"><?= e($course['thumb']) ?></div>
            <div class="course-body">
              <h3><?= e($course['title']) ?></h3>
              <p>Enrolled: <?= e(date('M j, Y', strtotime($course['enrolled_at']))) ?><br>Status: <?= e($course['status']) ?><br>Progress: <?= (int) $course['progress'] ?>%</p>
              <div class="progress-track" aria-hidden="true"><div class="progress-fill" style="width: <?= (int) $course['progress'] ?>%"></div></div>
              <div class="card-actions">
                <a class="btn primary" href="student-dashboard.php?course_id=<?= (int) $course['course_id'] ?>">Continue</a>
                <form method="post">
                  <input type="hidden" name="action" value="remove">
                  <input type="hidden" name="course_id" value="<?= (int) $course['course_id'] ?>">
                  <button class="btn" type="submit">Remove</button>
                </form>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </section>
    <?php endif; ?>
  </main>
</body>
</html>
