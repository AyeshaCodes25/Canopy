<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';
if (is_logged_in()) redirect('/dashboard.php');

$resetLink = null;
$emailSent = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = trim($_POST['email'] ?? '');

    $stmt = mysqli_prepare($mysqli, "SELECT id, name FROM users WHERE email = ?");
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$user) {
        $errors[] = 'No account found with that email.';
    } else {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + 3600);
        $stmt = mysqli_prepare($mysqli, "INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "sss", $email, $token, $expires);
        mysqli_stmt_execute($stmt);

        // Build an absolute URL for the email (relative paths don't make sense in a mail client)
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $resetLink = $scheme . '://' . $host . BASE_URL . '/reset-password.php?token=' . $token . '&email=' . urlencode($email);

        if (mail_is_configured()) {
            $emailSent = send_reset_email($email, $user['name'], $resetLink);
            if (!$emailSent) {
                $errors[] = 'We could not send the reset email right now. Please try again shortly.';
                $resetLink = null;
            }
        }
        // If mail isn't configured, $emailSent stays false and $resetLink is
        // shown on-screen below — this keeps local/dev environments usable
        // without real SMTP credentials.
    }
}

$pageTitle = 'Forgot Password';
include __DIR__ . '/includes/header.php';
?>
<div class="auth-shell">
  <div class="auth-card">
    <div class="auth-title">Forgot your password?</div>
    <div class="auth-sub">Enter your email and we'll send you a reset link.</div>

    <?php if ($errors): ?><div class="alert error"><?= implode('<br>', array_map('h', $errors)) ?></div><?php endif; ?>

    <?php if ($emailSent): ?>
      <div class="alert success">
        ✅ Check your inbox — we've emailed a password reset link to that
        address. It expires in 1 hour.
      </div>
    <?php elseif ($resetLink): ?>
      <div class="alert success">
        SMTP isn't configured on this install (see <code>config.php</code>),
        so here's your reset link directly instead of by email:
        <br><br>
        <a href="<?= h($resetLink) ?>" style="font-weight:700; word-break:break-all;"><?= h($resetLink) ?></a>
      </div>
    <?php else: ?>
    <form method="POST">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <div class="field">
        <label class="label">Email</label>
        <input type="email" name="email" required autofocus>
      </div>
      <button type="submit" class="btn block">Send reset link</button>
    </form>
    <?php endif; ?>
    <div class="auth-foot"><a href="<?= BASE_URL ?>/login.php">Back to login</a></div>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
