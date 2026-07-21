<?php
require_once __DIR__ . '/includes/functions.php';
if (is_logged_in()) redirect('/dashboard.php');

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = mysqli_prepare($mysqli, "SELECT id, password FROM users WHERE email = ?");
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if ($row && password_verify($password, $row['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $row['id'];
        redirect('/dashboard.php');
    } else {
        $errors[] = 'Those credentials don\'t match our records.';
    }
}

$pageTitle = 'Log in';
include __DIR__ . '/includes/header.php';
?>
<div class="auth-shell">
  <div class="auth-card">
    <div class="auth-brand"><span class="leaf">🌿</span> Canopy</div>
    <div class="auth-tag">Where your ideas take root and branch.</div>
    <div class="auth-title">Welcome back</div>
    <?php if ($errors): ?>
      <div class="alert error"><?= implode('<br>', array_map('h', $errors)) ?></div>
    <?php endif; ?>
    <form method="POST">
      <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <div class="field">
        <label class="label">Email</label>
        <input type="email" name="email" value="<?= h($_POST['email'] ?? '') ?>" required autofocus>
      </div>
      <div class="field">
        <label class="label">Password</label>
        <input type="password" name="password" required>
      </div>
      <div style="text-align:right; margin-bottom:20px;">
        <a href="<?= BASE_URL ?>/forgot-password.php" style="font-size:12.5px; color:var(--green-800); font-weight:600;">Forgot password?</a>
      </div>
      <button type="submit" class="btn block">Log in</button>
    </form>
    <div class="auth-foot">Don't have an account? <a href="<?= BASE_URL ?>/register.php">Sign up</a></div>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
