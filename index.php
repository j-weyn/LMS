<?php
require __DIR__ . '/db.php';

if (!is_logged_in()) {
    redirect_to('login.php');
}

$db = db();
$allowedCategories = ['all', 'technology', 'business', 'education', 'campus'];
$category = $_GET['category'] ?? 'all';
$category = in_array($category, $allowedCategories, true) ? $category : 'all';
$search = trim($_GET['search'] ?? '');
$courses = courses($db, $category, $search);
$enrolledIds = enrolled_course_ids($db);

$user = is_logged_in() ? $db->query("SELECT * FROM users WHERE id = " . get_user_id())->fetch_assoc() : null;

$featured = courses($db, 'technology', 'Student Digital Skills Starter')[0] ?? $courses[0] ?? null;
$message = flash_message();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>EARIST Learn Manila</title>
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
        <a href="index.php#courses">Courses</a>
        <a href="my-courses.php">My Courses (<?= count($enrolledIds) ?>)</a>
        <a href="index.php#learningPath">Learning Path</a>
        <?php if ($user): ?>
          <?php if ($user['role'] === 'admin'): ?>
            <a href="admin.php" class="btn" style="min-height: 30px; padding: 5px 15px; background: var(--maroon); color: var(--yellow); border: 1px solid var(--yellow);">Admin Panel</a>
          <?php endif; ?>
          <span style="font-size: 12px; color: var(--muted);">Hi, <?= e($user['full_name']) ?></span>
          <a href="logout.php" style="color: var(--red);">Logout</a>
        <?php else: ?>
          <a href="login.php" class="btn primary" style="min-height: 30px; padding: 5px 15px;">Login</a>
        <?php endif; ?>
      </div>
    </nav>
  </header>

  <section class="hero">
    <div class="hero-inner">
      <div>
        <p class="eyebrow">EARIST College Manila Online Learning</p>
        <h1>Build your skills with database-powered course enrollment.</h1>
        <p class="lead">Browse courses, confirm enrollment, and track progress using PHP and MySQL instead of browser-only storage.</p>
        <div class="actions" style="margin-top: 24px;">
          <a class="btn primary" href="#courses">Browse Courses</a>
          <a class="btn" href="my-courses.php">Open My Courses</a>
        </div>
      </div>
      <?php if ($featured): ?>
        <aside class="preview-card" aria-label="Featured course preview">
          <div class="preview-top"><?= e($featured['thumb']) ?></div>
          <div class="preview-body">
            <h2>Featured: <?= e($featured['title']) ?></h2>
            <p><?= e($featured['description']) ?></p>
            <div class="actions">
              <a class="btn primary" href="course-details.php?course_id=<?= (int) $featured['id'] ?>">
                <?= in_array((int) $featured['id'], $enrolledIds, true) ? 'View Course' : 'Enroll Now' ?>
              </a>
            </div>
          </div>
        </aside>
      <?php endif; ?>
    </div>
  </section>

  <main class="main">
    <?php if ($message): ?><div class="notice"><?= e($message) ?></div><?php endif; ?>

    <form class="searchbar" method="get" action="index.php#courses">
      <input class="input" type="search" name="search" value="<?= e($search) ?>" placeholder="Search for courses, skills, or modules" aria-label="Search courses">
      <select class="select" name="category" aria-label="Course category">
        <?php foreach ($allowedCategories as $item): ?>
          <option value="<?= e($item) ?>" <?= $category === $item ? 'selected' : '' ?>><?= e(ucwords($item === 'all' ? 'all courses' : $item)) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn" type="submit">Search</button>
    </form>

    <section id="courses">
      <div class="section-head">
        <div>
          <h2>Popular Courses</h2>
          <p class="muted">Short, practical courses for student learning, faculty support, and campus readiness.</p>
        </div>
        <span class="result-count"><?= count($courses) ?> <?= count($courses) === 1 ? 'course' : 'courses' ?></span>
      </div>

      <?php if (!$courses): ?>
        <div class="empty">No courses found. Try another keyword or category.</div>
      <?php else: ?>
        <div class="course-grid">
          <?php foreach ($courses as $course): ?>
            <?php $isEnrolled = in_array((int) $course['id'], $enrolledIds, true); ?>
            <article class="course-card">
              <div class="course-top"><?= e($course['thumb']) ?></div>
              <div class="course-body">
                <h3><?= e($course['title']) ?></h3>
                <p><?= e($course['instructor']) ?></p>
                <p class="rating"><?= e($course['rating']) ?> stars (<?= number_format((int) $course['reviews']) ?>)</p>
                <p><?= e($course['meta']) ?></p>
                <div class="card-actions">
                  <span class="free">Free</span>
                  <a class="btn <?= $isEnrolled ? '' : 'primary' ?>" href="<?= $isEnrolled ? 'student-dashboard.php' : 'course-details.php' ?>?course_id=<?= (int) $course['id'] ?>">
                    <?= $isEnrolled ? 'View Course' : 'Enroll' ?>
                  </a>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="dashboard" id="learningPath" style="margin-top: 42px;">
      <div class="panel">
        <h2>How The LMS Works</h2>
        <p>Students enroll in a course, follow each module, complete activities, then track progress toward completion.</p>
        <div class="lesson-list">
          <div class="lesson"><span><strong>1. Watch Lesson</strong>Video and notes</span></div>
          <div class="lesson"><span><strong>2. Answer Quiz</strong>Auto-checked practice</span></div>
          <div class="lesson"><span><strong>3. Submit Activity</strong>Faculty review</span></div>
          <div class="lesson"><span><strong>4. Get Certificate</strong>Completion proof</span></div>
        </div>
      </div>
      <div class="panel">
        <h2>Database Features</h2>
        <p>Courses, enrollments, and lesson progress are saved in MySQL. Refresh the browser or open another page and the student record stays available.</p>
      </div>
    </section>
  </main>
</body>
</html>
