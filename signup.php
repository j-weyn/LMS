<?php
require __DIR__ . '/db.php';
$db = db();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['full_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = password_hash($_POST['password'] ?? '', PASSWORD_DEFAULT);
    $code = (string)random_int(100000, 999999);

    try {
        $stmt = $db->prepare('INSERT INTO users (full_name, email, password, verification_code) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('ssss', $name, $email, $password, $code);
        $stmt->execute();
        
        if (send_auth_email($email, $code)) {
            redirect_to('verify.php?email=' . urlencode($email));
        } else {
            $error = 'Email failed. 1. Check C:\xampp\sendmail\error.log 2. Ensure App Password is correct in sendmail.ini.';
        }
    } catch (mysqli_sql_exception) {
        $error = 'Email already registered.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><title>Sign Up | EARIST Learn</title>
    <link rel="stylesheet" href="style.css">
</head>
<body style="display: flex; align-items: center; justify-content: center; min-height: 100vh; background: var(--soft);">
    <div class="panel" style="width: 400px; padding: 30px;">
        <h2>Student Registration</h2>
        <p class="muted">A verification code will be sent to your email address.</p>
        <?php if ($error): ?><div class="notice" style="background: var(--red);"><?= e($error) ?></div><?php endif; ?>
        <form method="POST">
            <div style="margin-bottom: 10px;">
                <label>Full Name</label>
                <input type="text" name="full_name" class="input" required>
            </div>
            <div style="margin-bottom: 10px;">
                <label>Email Address</label>
                <input type="email" name="email" class="input" required placeholder="Email Address">
            </div>
            <div style="margin-bottom: 20px;">
                <label>Password</label>
                <input type="password" name="password" class="input" required>
            </div>
            <button type="submit" class="btn primary" style="width: 100%;">Create Account & Send Code</button>
        </form>
        <div style="margin-top: 20px; font-size: 13px; color: #666; border-top: 1px solid #eee; padding-top: 15px;">
            <strong>Verification Note:</strong> A 6-digit security code will be sent to your inbox via <em><?= e(SMTP_HOST) ?></em>. 
            If you don't see it within a minute, please check your <strong>Spam</strong> or <strong>Junk</strong> folder.
        </div>
    </div>
</body>
</html>