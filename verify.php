<?php
require __DIR__ . '/db.php';
$db = db();
$email = $_GET['email'] ?? '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = $_POST['code'] ?? '';
    $stmt = $db->prepare('SELECT id FROM users WHERE email = ? AND verification_code = ?');
    $stmt->bind_param('ss', $email, $code);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user) {
        $db->query("UPDATE users SET is_verified = 1, verification_code = NULL WHERE id = " . $user['id']);
        redirect_to('login.php?message=verified');
    } else {
        $error = 'Invalid verification code.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><title>Verify Account</title>
    <link rel="stylesheet" href="style.css">
</head>
<body style="display: flex; align-items: center; justify-content: center; min-height: 100vh;">
    <div class="panel" style="width: 350px; padding: 30px; text-align: center;">
        <h2>Verify Your Email</h2>
        <p>Enter the 6-digit code sent to<br><strong><?= e($email) ?></strong></p>
        <?php if ($error): ?><div class="notice" style="background: var(--red);"><?= e($error) ?></div><?php endif; ?>
        <form method="POST">
            <input type="text" name="code" class="input" maxlength="6" placeholder="000000" style="text-align: center; font-size: 24px; letter-spacing: 5px;" required>
            <button type="submit" class="btn primary" style="width: 100%; margin-top: 20px;">Verify Account</button>
        </form>
        <p style="margin-top: 20px; font-size: 12px; color: #888;">
            Didn't receive the email? Check your spam folder or verify that <strong><?= e($email) ?></strong> is correct. 
            If the email is wrong, you may go back and <a href="signup.php" style="color: var(--maroon);">register again</a>.
        </p>
    </div>
</body>
</html>