<?php
require __DIR__ . '/db.php';

if (!is_logged_in() || ($_SESSION['role'] ?? '') !== 'admin') {
    redirect_to('login.php');
}

$db = db();

// Handle Actions
$action = $_GET['action'] ?? 'list';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_course'])) {
        save_course($db, $_POST);
        redirect_to('admin.php?message=Course saved successfully');
    }
    if (isset($_POST['delete_id'])) {
        delete_course($db, (int)$_POST['delete_id']);
        redirect_to('admin.php?message=Course deleted');
    }
}

$courses = courses($db);
$stats = get_admin_stats($db);
$editCourse = ($action === 'edit' && isset($_GET['id'])) ? course_by_id($db, (int)$_GET['id']) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Panel | EARIST Learn</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .admin-grid { display: grid; grid-template-columns: 1fr 350px; gap: 24px; }
        table { width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; }
        th, td { text-align: left; padding: 12px; border-bottom: 1px solid var(--line); }
        th { background: var(--maroon); color: white; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 8px; border: 1px solid var(--line); border-radius: 4px; }
    </style>
</head>
<body>
    <header class="site-header">
        <nav class="nav">
            <a class="brand" href="index.php">
                <img src="https://earist.edu.ph/wp-content/uploads/earist-logo-1.png" alt="Logo">
                <span>EARIST Admin</span>
            </a>
            <div class="nav-links">
                <a href="index.php">View Site</a>
                <a href="admin.php">Dashboard</a>
            </div>
        </nav>
    </header>

    <main class="main">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h1>Management Dashboard</h1>
            <a href="download-docs.php" class="btn" style="background: var(--ink); color: white;">Download Project MD Docs</a>
        </div>
        
        <section class="summary" style="margin-bottom: 30px;">
            <div class="stat"><strong><?= $stats['total_courses'] ?></strong><span>Total Courses</span></div>
            <div class="stat"><strong><?= $stats['total_enrollments'] ?></strong><span>Active Students</span></div>
            <div class="stat"><strong><?= $stats['completed_enrollments'] ?></strong><span>Certificates Issued</span></div>
        </section>

        <div class="admin-grid">
            <section class="panel">
                <h2>Course List</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Instructor</th>
                            <th>Level</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($courses as $c): ?>
                        <tr>
                            <td><?= e($c['title']) ?></td>
                            <td><?= e($c['instructor']) ?></td>
                            <td><?= e($c['level']) ?></td>
                            <td>
                                <a href="admin.php?action=edit&id=<?= $c['id'] ?>" style="color: var(--maroon);">Edit</a> | 
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this course?');">
                                    <input type="hidden" name="delete_id" value="<?= $c['id'] ?>">
                                    <button type="submit" style="background:none; border:none; color:var(--red); cursor:pointer; font-weight:bold; padding:0;">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>

            <aside class="panel">
                <h2><?= $editCourse ? 'Edit Course' : 'Add New Course' ?></h2>
                <form method="POST">
                    <?php if ($editCourse): ?>
                        <input type="hidden" name="id" value="<?= $editCourse['id'] ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label>Course Title</label>
                        <input type="text" name="title" value="<?= e($editCourse['title'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category">
                            <option value="technology" <?= ($editCourse['category'] ?? '') === 'technology' ? 'selected' : '' ?>>Technology</option>
                            <option value="business" <?= ($editCourse['category'] ?? '') === 'business' ? 'selected' : '' ?>>Business</option>
                            <option value="education" <?= ($editCourse['category'] ?? '') === 'education' ? 'selected' : '' ?>>Education</option>
                            <option value="campus" <?= ($editCourse['category'] ?? '') === 'campus' ? 'selected' : '' ?>>Campus</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Instructor</label>
                        <input type="text" name="instructor" value="<?= e($editCourse['instructor'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Video Embed URL</label>
                        <input type="text" name="video_url" value="<?= e($editCourse['video_url'] ?? 'https://www.youtube.com/embed/dQw4w9WgXcQ') ?>">
                    </div>
                    <div class="form-group">
                        <label>Level</label>
                        <input type="text" name="level" value="<?= e($editCourse['level'] ?? 'Beginner') ?>">
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" rows="4"><?= e($editCourse['description'] ?? '') ?></textarea>
                    </div>
                    
                    <!-- Hidden defaults for fields not in form but required by DB schema -->
                    <input type="hidden" name="keywords" value="<?= e($editCourse['keywords'] ?? 'course') ?>">
                    <input type="hidden" name="thumb" value="<?= e($editCourse['thumb'] ?? 'LEARN') ?>">
                    <input type="hidden" name="rating" value="<?= e($editCourse['rating'] ?? 4.8) ?>">
                    <input type="hidden" name="reviews" value="<?= e($editCourse['reviews'] ?? 0) ?>">
                    <input type="hidden" name="meta" value="<?= e($editCourse['meta'] ?? 'Course Module') ?>">

                    <button type="submit" name="save_course" class="btn primary" style="width: 100%;"><?= $editCourse ? 'Update Course' : 'Create Course' ?></button>
                    <?php if ($editCourse): ?>
                        <a href="admin.php" class="btn" style="width:100%; margin-top:10px; text-align:center;">Cancel</a>
                    <?php endif; ?>
                </form>
            </aside>
        </div>
    </main>
</body>
</html>