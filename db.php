<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

const DB_HOST = '127.0.0.1';
const DB_USER = 'root';
const DB_PASS = '';
const DB_NAME = 'earist_learn';

// SMTP Server Settings
// Gmail:   Host=smtp.gmail.com, Port=587
// Outlook: Host=smtp-mail.outlook.com, Port=587
// Yahoo:   Host=smtp.mail.yahoo.com, Port=465/587
// iCloud:  Host=smtp.mail.me.com, Port=587

const SMTP_HOST = 'smtp.gmail.com';
const SMTP_PORT = 587;
const SMTP_USER = 'your-email@gmail.com';   // REPLACE with your full email address
const SMTP_PASS = 'your-app-password';      // REPLACE with your App Password

function get_user_id(): int {
    return (int)($_SESSION['user_id'] ?? 0);
}

function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

function db(): mysqli
{
    static $db = null;

    if ($db instanceof mysqli) {
        return $db;
    }

    try {
        // Try connecting. On some XAMPP setups, 'localhost' works better than '127.0.0.1'
        $server = @new mysqli(DB_HOST, DB_USER, DB_PASS);
        
        if ($server->connect_error) {
            throw new mysqli_sql_exception($server->connect_error);
        }

        $server->set_charset('utf8mb4');

        // Only attempt creation/init if we can't select the DB
        if (!$server->select_db(DB_NAME)) {
            $server->query('CREATE DATABASE `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $server->select_db(DB_NAME);
        }

        // Run database initialization. We use 'CREATE TABLE IF NOT EXISTS' 
        // within this function, so it's safe to call even if tables already exist.
        // This ensures new features (like the quiz system) have their tables created.
        try {
            initialize_database($server);
        } catch (mysqli_sql_exception $e) {
            exit('Database Table Setup Error: ' . $e->getMessage());
        }

        // Ensure Admin exists even if table was already there
        ensure_admin_exists($server);

        // If the courses table is empty, seed it
        $check = $server->query("SELECT id FROM courses LIMIT 1");
        if ($check->num_rows === 0) {
            seed_courses($server);
        }

        // Seed the 30-item Career Readiness Quiz if it's empty
        if ($server->query("SELECT id FROM quiz_questions LIMIT 1")->num_rows === 0) {
            seed_career_readiness_quiz($server);
        }

        $db = $server;
        return $db;
    } catch (mysqli_sql_exception $e) {
        http_response_code(500);
        exit('Database connection failed: ' . $e->getMessage() . '. Start MySQL in XAMPP, then refresh this page.');
    }
}

function initialize_database(mysqli $db): void
{
    $db->query("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(160) NOT NULL,
        email VARCHAR(160) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('student', 'admin') DEFAULT 'student',
        is_verified TINYINT(1) DEFAULT 0,
        verification_code VARCHAR(6) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS courses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(160) NOT NULL UNIQUE,
        category VARCHAR(40) NOT NULL,
        keywords VARCHAR(255) NOT NULL,
        thumb VARCHAR(12) NOT NULL,
        instructor VARCHAR(160) NOT NULL,
        rating DECIMAL(2,1) NOT NULL DEFAULT 4.8,
        reviews INT NOT NULL DEFAULT 0,
        meta VARCHAR(160) NOT NULL,
        description TEXT NOT NULL,
        level VARCHAR(80) NOT NULL,
        video_url VARCHAR(255) DEFAULT 'https://www.youtube.com/embed/dQw4w9WgXcQ',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Migration: If video_url is missing from courses (added in later update), add it.
    $checkCourses = $db->query("SHOW COLUMNS FROM courses LIKE 'video_url'");
    if ($checkCourses->num_rows === 0) {
        $db->query("ALTER TABLE courses ADD COLUMN video_url VARCHAR(255) DEFAULT 'https://www.youtube.com/embed/dQw4w9WgXcQ' AFTER level");
    }

    // Create or Update enrollments table
    $db->query("CREATE TABLE IF NOT EXISTS enrollments (id INT AUTO_INCREMENT PRIMARY KEY) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // Migration: If user_id is missing, add it. 
    $check = $db->query("SHOW COLUMNS FROM enrollments LIKE 'user_id'");
    if ($check->num_rows === 0) {
        // If the table was old, it's safer to clear it during migration to avoid constraint conflicts
        $db->query("TRUNCATE TABLE enrollments"); 
        $db->query("ALTER TABLE enrollments 
            ADD COLUMN user_id INT NOT NULL AFTER id,
            ADD COLUMN course_id INT NOT NULL AFTER user_id,
            ADD COLUMN enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER course_id,
            ADD COLUMN status VARCHAR(40) NOT NULL DEFAULT 'In progress' AFTER enrolled_at,
            ADD COLUMN progress INT NOT NULL DEFAULT 0 AFTER status,
            DROP COLUMN IF EXISTS student_key,
            ADD UNIQUE KEY unique_user_course (user_id, course_id),
            ADD CONSTRAINT fk_enroll_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            ADD CONSTRAINT fk_enroll_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
        ");
    }

    $db->query("CREATE TABLE IF NOT EXISTS lesson_progress (
        id INT AUTO_INCREMENT PRIMARY KEY,
        enrollment_id INT NOT NULL,
        lesson_key VARCHAR(80) NOT NULL,
        completed TINYINT(1) NOT NULL DEFAULT 1,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_lesson (enrollment_id, lesson_key),
        CONSTRAINT fk_lesson_enrollment FOREIGN KEY (enrollment_id) REFERENCES enrollments(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS quiz_questions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        course_id INT NOT NULL,
        question TEXT NOT NULL,
        option_a VARCHAR(255) NOT NULL,
        option_b VARCHAR(255) NOT NULL,
        option_c VARCHAR(255) NOT NULL,
        option_d VARCHAR(255) NOT NULL,
        correct_option CHAR(1) NOT NULL,
        CONSTRAINT fk_quiz_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS quiz_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        course_id INT NOT NULL,
        score INT NOT NULL,
        total_items INT NOT NULL,
        status ENUM('pending', 'released') DEFAULT 'pending',
        submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_attempt_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_attempt_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
        UNIQUE KEY unique_attempt (user_id, course_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->query("CREATE TABLE IF NOT EXISTS quiz_responses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        attempt_id INT NOT NULL,
        question_id INT NOT NULL,
        selected_option CHAR(1) NOT NULL,
        is_correct TINYINT(1) NOT NULL,
        CONSTRAINT fk_response_attempt FOREIGN KEY (attempt_id) REFERENCES quiz_attempts(id) ON DELETE CASCADE,
        CONSTRAINT fk_response_question FOREIGN KEY (question_id) REFERENCES quiz_questions(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function ensure_admin_exists(mysqli $db): void
{
    $adminPass = password_hash('1234', PASSWORD_DEFAULT);

    // We check if 'admin' exists. If so, we ensure the password is '1234'.
    // This solves the issue where old test data prevents you from logging in.
    $admins = [
        ['System Administrator', 'admin@earist.edu.ph'],
        ['Admin User', 'admin']
    ];

    foreach ($admins as [$name, $email]) {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            $stmt = $db->prepare("INSERT INTO users (full_name, email, password, role, is_verified) VALUES (?, ?, ?, 'admin', 1)");
            $stmt->bind_param('sss', $name, $email, $adminPass);
            $stmt->execute();
        }
    }
}

function seed_courses(mysqli $db): void
{
    $courses = [
        ['Computer Fundamentals for EARIST Students', 'technology', 'computer fundamentals digital productivity ict', 'ICT', 'EARIST Computer Education Team', 4.8, 1240, '12 lessons | 4 quizzes | Beginner', 'Build confidence using computers, online class tools, productivity apps, and safe digital habits for daily student work.', 'Beginner'],
        ['Web Design with HTML, CSS, and JavaScript', 'technology', 'web design html css javascript programming', 'WEB', 'College of Industrial Technology', 4.9, 980, '18 lessons | 6 activities | Beginner', 'Create responsive web pages, style them with CSS, and add interactive behavior using beginner-friendly JavaScript.', 'Beginner'],
        ['Office Productivity and Business Documents', 'business', 'office business public administration documents', 'OBPA', 'Business Administration Faculty', 4.7, 760, '10 lessons | 3 projects | Intermediate', 'Prepare polished documents, spreadsheets, reports, and presentations for classroom and office requirements.', 'Intermediate'],
        ['Lesson Planning and Teaching Strategies', 'education', 'teaching strategies lesson planning education', 'CTE', 'College of Teacher Education', 4.8, 650, '14 lessons | 5 templates | Intermediate', 'Design clear lesson plans, learning objectives, classroom activities, and assessment tools for teaching practice.', 'Intermediate'],
        ['Technical Drafting and Design Basics', 'technology', 'engineering drafting architecture design cad', 'CAD', 'Engineering and Architecture Faculty', 4.6, 530, '16 lessons | 4 lab tasks | Beginner', 'Learn drafting fundamentals, design conventions, and starter CAD workflows for technical course requirements.', 'Beginner'],
        ['Student Portal and Campus Services Guide', 'campus', 'student portal enrollment campus services forms', 'SVC', 'EARIST Student Services', 4.9, 1500, '8 lessons | Forms guide | Beginner', 'Navigate enrollment, student services, online requests, and important campus processes with fewer wrong turns.', 'Beginner'],
        ['Research Writing and Citation Workshop', 'education', 'research writing thesis citation paper', 'RES', 'EARIST Research Support', 4.7, 890, '11 lessons | 2 papers | Advanced', 'Plan a research paper, organize sources, cite references properly, and improve academic writing structure.', 'Advanced'],
        ['Career Readiness: Resume and Interview Prep', 'business', 'career readiness resume interview job', 'JOB', 'Guidance and Placement Office', 4.8, 720, '9 lessons | 3 checklists | Beginner', 'Prepare a student resume, practice interview answers, and build confidence for internships and first jobs.', 'Beginner', 'https://www.youtube.com/embed/pUPczJGAm1c'],
        ['Student Digital Skills Starter', 'technology', 'student digital skills starter featured', 'EARIST', 'EARIST Learn Team', 4.9, 1800, '6 modules | Certificate-ready | Beginner', 'A fast starter course covering LMS navigation, digital study routines, quizzes, submissions, and progress tracking.', 'Beginner'],
    ];

    $stmt = $db->prepare('INSERT IGNORE INTO courses (title, category, keywords, thumb, instructor, rating, reviews, meta, description, level, video_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    foreach ($courses as $course) {
        $video = $course[10] ?? 'https://www.youtube.com/embed/dQw4w9WgXcQ';
        $stmt->bind_param('sssssdissss', $course[0], $course[1], $course[2], $course[3], $course[4], $course[5], $course[6], $course[7], $course[8], $course[9], $video);
        $stmt->execute();
    }
}

function seed_career_readiness_quiz(mysqli $db): void
{
    $course = course_by_title($db, 'Career Readiness: Resume and Interview Prep');
    if (!$course) return;

    $questions = [
        ["What is the standard length for an entry-level resume?", "1 page", "3 pages", "5 pages", "10 pages", "A"],
        ["Which resume format is best for showing consistent career growth?", "Functional", "Reverse-Chronological", "Creative", "Targeted", "B"],
        ["What does ATS stand for in the hiring process?", "Applicant Tracking System", "Advanced Typing Software", "Automated Task Sender", "Annual Technical Survey", "A"],
        ["Where should your contact information be placed?", "In the footer", "In the middle", "In the header", "On a separate page", "C"],
        ["A professional email address should typically include:", "Nicknames", "Your full name", "Random numbers", "Birth year only", "B"],
        ["What is the primary purpose of a resume?", "To get a job offer immediately", "To list every hobby you have", "To secure an interview", "To show your social media links", "C"],
        ["Which section lists your degrees and certifications?", "Experience", "Education", "Summary", "References", "B"],
        ["Action verbs like 'Implemented' or 'Developed' help describe:", "Hobbies", "Achievements", "Personal life", "Weaknesses", "B"],
        ["Why should you quantify your professional experience?", "To make the text longer", "To provide measurable proof of impact", "To confuse recruiters", "It is not necessary", "B"],
        ["Which of these is considered a 'soft skill'?", "Python Programming", "Leadership", "Data Analysis", "Welding", "B"],
        ["Which of these is considered a 'hard skill'?", "Teamwork", "Public Speaking", "Data Analysis", "Adaptability", "C"],
        ["How far back should your work history usually go?", "5 years", "10-15 years", "Entire lifetime", "Only high school", "B"],
        ["A resume summary is best used for:", "Listing references", "Highlighting key qualifications quickly", "Explaining personal problems", "Describing future salary goals", "B"],
        ["What font size is generally recommended for resume body text?", "6-8 points", "10-12 points", "18-20 points", "24 points", "B"],
        ["What is the first step in interview preparation?", "Buying new shoes", "Researching the company", "Choosing a lunch spot", "Printing business cards", "B"],
        ["In the STAR method, what does 'S' represent?", "Salary", "Situation", "Success", "Speed", "B"],
        ["In the STAR method, what does 'T' represent?", "Task", "Time", "Talk", "Team", "A"],
        ["In the STAR method, what does 'A' represent?", "Answers", "Action", "Altitude", "Account", "B"],
        ["In the STAR method, what does 'R' represent?", "Reason", "Result", "Review", "Rating", "B"],
        ["When asked about a weakness, you should:", "Say you have none", "Share a real one and how you improve it", "Talk about a coworker's weakness", "Complain about your last boss", "B"],
        ["What is appropriate attire for a corporate interview?", "Gym clothes", "Business Professional (Suit/Blazer)", "Pajamas", "Casual t-shirt", "B"],
        ["How early should you arrive for an in-person interview?", "1 hour early", "10-15 minutes early", "Exactly on time", "5 minutes late", "B"],
        ["Good eye contact during an interview conveys:", "Fear", "Confidence and engagement", "Aggression", "Boredom", "B"],
        ["If you don't know the answer to a question during an interview:", "Make something up", "Explain how you would find the answer", "Remain silent", "Cry", "B"],
        ["What should you bring to an in-person interview?", "Lunch", "Copies of your resume and a pen", "A friend for support", "Video games", "B"],
        ["What is a 'behavioral' interview question?", "A question about your favorite color", "A question about past experiences/behaviors", "A question about math", "A question about your parents", "B"],
        ["When should you send a thank-you note after an interview?", "After one month", "Within 24 hours", "Never", "Immediately before the interview", "B"],
        ["The 'Tell me about yourself' answer should focus on:", "Your childhood", "Professional skills and relevance to the role", "Your financial needs", "Your political views", "B"],
        ["Asking questions at the end of an interview shows:", "Lack of knowledge", "Interest and preparation", "Arrogance", "You are in a rush", "B"],
        ["What is the primary goal of a mock interview?", "To get a real job offer", "To practice and get feedback", "To intimidate friends", "To record a podcast", "B"]
    ];

    $stmt = $db->prepare("INSERT INTO quiz_questions (course_id, question, option_a, option_b, option_c, option_d, correct_option) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($questions as $q) {
        $stmt->bind_param('issssss', $course['id'], $q[0], $q[1], $q[2], $q[3], $q[4], $q[5]);
        $stmt->execute();
    }
}

function save_course(mysqli $db, array $data): bool
{
    if (isset($data['id']) && $data['id'] > 0) {
        $stmt = $db->prepare('UPDATE courses SET title=?, category=?, keywords=?, thumb=?, instructor=?, rating=?, reviews=?, meta=?, description=?, level=?, video_url=? WHERE id=?');
        $stmt->bind_param('sssssdissssi', $data['title'], $data['category'], $data['keywords'], $data['thumb'], $data['instructor'], $data['rating'], $data['reviews'], $data['meta'], $data['description'], $data['level'], $data['video_url'], $data['id']);
    } else {
        $stmt = $db->prepare('INSERT INTO courses (title, category, keywords, thumb, instructor, rating, reviews, meta, description, level, video_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('sssssdissss', $data['title'], $data['category'], $data['keywords'], $data['thumb'], $data['instructor'], $data['rating'], $data['reviews'], $data['meta'], $data['description'], $data['level'], $data['video_url']);
    }
    return $stmt->execute();
}

function delete_course(mysqli $db, int $id): bool
{
    $stmt = $db->prepare('DELETE FROM courses WHERE id = ?');
    $stmt->bind_param('i', $id);
    return $stmt->execute();
}

function get_admin_stats(mysqli $db): array
{
    return [
        'total_courses' => $db->query('SELECT COUNT(*) FROM courses')->fetch_row()[0],
        'total_enrollments' => $db->query('SELECT COUNT(*) FROM enrollments')->fetch_row()[0],
        'completed_enrollments' => $db->query("SELECT COUNT(*) FROM enrollments WHERE status = 'Completed'")->fetch_row()[0],
    ];
}

function e(string|int|float|null $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect_to(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function flash_message(): string
{
    if (!isset($_GET['message'])) {
        return '';
    }

    return match ($_GET['message']) {
        'enrolled' => 'Enrollment confirmed. Your course was saved in the database.',
        'removed' => 'Course removed from My Courses.',
        'cleared' => 'All enrolled courses were cleared.',
        'progress' => 'Progress updated.',
        default => '',
    };
}

function courses(mysqli $db, string $category = 'all', string $search = ''): array
{
    $where = [];
    $params = [];
    $types = '';

    if ($category !== 'all') {
        $where[] = 'category = ?';
        $params[] = $category;
        $types .= 's';
    }

    if ($search !== '') {
        $where[] = '(title LIKE ? OR keywords LIKE ? OR instructor LIKE ?)';
        $keyword = '%' . $search . '%';
        array_push($params, $keyword, $keyword, $keyword);
        $types .= 'sss';
    }

    $sql = 'SELECT * FROM courses' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY FIELD(title, "Student Digital Skills Starter") DESC, title ASC';
    $stmt = $db->prepare($sql);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function course_by_id(mysqli $db, int $id): ?array
{
    $stmt = $db->prepare('SELECT * FROM courses WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

function course_by_title(mysqli $db, string $title): ?array
{
    $stmt = $db->prepare('SELECT * FROM courses WHERE title = ?');
    $stmt->bind_param('s', $title);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

function enrolled_course_ids(mysqli $db): array
{
    $userId = get_user_id();
    $stmt = $db->prepare('SELECT course_id FROM enrollments WHERE user_id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    return array_map('intval', array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'course_id'));
}

function enrolled_courses(mysqli $db): array
{
    $userId = get_user_id();
    $stmt = $db->prepare('SELECT e.*, c.title, c.thumb, c.instructor, c.meta FROM enrollments e JOIN courses c ON c.id = e.course_id WHERE e.user_id = ? ORDER BY e.enrolled_at DESC');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function ensure_enrollment(mysqli $db, int $courseId): int
{
    $userId = get_user_id();
    $stmt = $db->prepare('INSERT IGNORE INTO enrollments (user_id, course_id) VALUES (?, ?)');
    $stmt->bind_param('ii', $userId, $courseId);
    $stmt->execute();

    $stmt = $db->prepare('SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?');
    $stmt->bind_param('ii', $userId, $courseId);
    $stmt->execute();
    return (int) $stmt->get_result()->fetch_assoc()['id'];
}

function enrollment_for_course(mysqli $db, int $courseId): ?array
{
    $userId = get_user_id();
    $stmt = $db->prepare('SELECT * FROM enrollments WHERE user_id = ? AND course_id = ?');
    $stmt->bind_param('ii', $userId, $courseId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

function completed_lessons(mysqli $db, int $enrollmentId): array
{
    $stmt = $db->prepare('SELECT lesson_key FROM lesson_progress WHERE enrollment_id = ? AND completed = 1');
    $stmt->bind_param('i', $enrollmentId);
    $stmt->execute();
    return array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'lesson_key');
}

function get_course_questions(mysqli $db, int $courseId): array
{
    $stmt = $db->prepare('SELECT * FROM quiz_questions WHERE course_id = ? LIMIT 30');
    $stmt->bind_param('i', $courseId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function get_quiz_attempt(mysqli $db, int $userId, int $courseId): ?array
{
    $stmt = $db->prepare('SELECT * FROM quiz_attempts WHERE user_id = ? AND course_id = ?');
    $stmt->bind_param('ii', $userId, $courseId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

function get_quiz_responses(mysqli $db, int $attemptId): array
{
    $sql = "SELECT qr.*, qq.question, qq.option_a, qq.option_b, qq.option_c, qq.option_d, qq.correct_option 
            FROM quiz_responses qr 
            JOIN quiz_questions qq ON qr.question_id = qq.id 
            WHERE qr.attempt_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $attemptId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function get_all_quiz_attempts(mysqli $db): array
{
    $sql = "SELECT qa.*, u.full_name, c.title as course_title FROM quiz_attempts qa 
            JOIN users u ON qa.user_id = u.id 
            JOIN courses c ON qa.course_id = c.id 
            ORDER BY qa.submitted_at DESC";
    return $db->query($sql)->fetch_all(MYSQLI_ASSOC);
}

function update_enrollment_status(mysqli $db, int $enrollmentId, int $progress, string $status): void
{
    $stmt = $db->prepare('UPDATE enrollments SET progress = ?, status = ? WHERE id = ?');
    $stmt->bind_param('isi', $progress, $status, $enrollmentId);
    $stmt->execute();
}

/**
 * Sends a verification email.
 * 
 * NOTE: To enable real emails in XAMPP, you must configure 
 * C:\xampp\sendmail\sendmail.ini using the SMTP constants defined above.
 */
function send_auth_email(string $to, string $code): bool
{
    $subject = "Verify your EARIST Learn Account";
    $message = "Your EARIST Learn verification code is: $code\r\n\r\n" . 
               "If you did not request this code, please ignore this email.";
    
    // Professional headers to improve deliverability
    $headers = [
        'From' => SMTP_USER,
        'Reply-To' => SMTP_USER,
        'X-Mailer' => 'PHP/' . phpversion(),
        'MIME-Version' => '1.0',
        'Content-type' => 'text/plain; charset=utf-8'
    ];

    // Removed '@' to allow error reporting if the mail configuration is broken
    return mail($to, $subject, $message, $headers);
}
?>
