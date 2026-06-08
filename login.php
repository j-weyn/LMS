<?php
require __DIR__ . '/db.php';
$db = db();
$error = '';

if (is_logged_in()) {
    redirect_to(($_SESSION['role'] ?? '') === 'admin' ? 'admin.php' : 'index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $db->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user && password_verify($password, $user['password'])) {
        if (!$user['is_verified']) {
            redirect_to('verify.php?email=' . urlencode($email));
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        redirect_to($user['role'] === 'admin' ? 'admin.php' : 'index.php');
    } else {
        $error = 'Invalid email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><title>Login | EARIST Learn</title>
    <link rel="stylesheet" href="style.css">
</head>
<body style="display: flex; align-items: center; justify-content: center; min-height: 100vh; background: var(--soft);">
    <div class="panel" style="width: 400px; padding: 30px;">
        <div style="text-align: center; margin-bottom: 20px;">
            <img src="https://earist.edu.ph/wp-content/uploads/earist-logo-1.png" width="60">
            <h2>Login to EARIST</h2>
        </div>
        <?php if ($error): ?><div class="notice" style="background: var(--red);"><?= e($error) ?></div><?php endif; ?>
        <form method="POST">
            <div style="margin-bottom: 15px;">
                <label>Email or Username</label>
                <input type="text" name="email" class="input" required autofocus>
            </div>
            <div style="margin-bottom: 20px;">
                <label>Password</label>
                <input type="password" name="password" class="input" required>
            </div>
            <button type="submit" class="btn primary" style="width: 100%;">Sign In</button>
        </form>
        <p style="text-align: center; margin-top: 20px;">
            New applicant? <a href="signup.php" style="color: var(--maroon); font-weight: bold;">Create Account</a>
        </p>
    </div>
</body>
</html>