<?php
$user = current_user();
$active = $active ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? h($pageTitle) . ' — Canopy' : 'Canopy' ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/style.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<?php if ($user): ?>
<div class="app">
  <aside class="sidebar">
    <div class="brand">🌿 Canopy</div>
    <nav class="nav">
      <a href="<?= BASE_URL ?>/dashboard.php" class="<?= $active==='dashboard'?'active':'' ?>">◆ Dashboard</a>
      <a href="<?= BASE_URL ?>/notes/index.php" class="<?= $active==='vault'?'active':'' ?>">▤ Knowledge Vault</a>
      <a href="<?= BASE_URL ?>/collections.php" class="<?= $active==='collections'?'active':'' ?>">▢ Collections</a>
      <a href="<?= BASE_URL ?>/search.php" class="<?= $active==='search'?'active':'' ?>">⌕ Search</a>
      <a href="<?= BASE_URL ?>/graph.php" class="<?= $active==='graph'?'active':'' ?>">🕸 Graph View</a>
      <div class="nav-section">Developer</div>
      <a href="<?= BASE_URL ?>/api-tokens.php" class="<?= $active==='api-tokens'?'active':'' ?>">🔌 API Access</a>
      <?php if ($user['role'] === 'admin'): ?>
      <div class="nav-section">Admin</div>
      <a href="<?= BASE_URL ?>/admin/index.php" class="<?= $active==='admin'?'active':'' ?>">🛠 Admin Panel</a>
      <a href="<?= BASE_URL ?>/admin/users.php" class="<?= $active==='admin-users'?'active':'' ?>">👥 Manage Users</a>
      <?php endif; ?>
    </nav>
    <div class="side-profile">
      <div class="who"><?= h($user['name']) ?></div>
      <div class="email"><?= h($user['email']) ?></div>
      <form method="POST" action="<?= BASE_URL ?>/logout.php">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <button type="submit">🚪 Log out</button>
      </form>
    </div>
  </aside>
  <main class="main">
    <?php if ($msg = flash('success')): ?><div class="alert success"><?= h($msg) ?></div><?php endif; ?>
    <?php if ($msg = flash('error')): ?><div class="alert error"><?= h($msg) ?></div><?php endif; ?>
<?php else: ?>
<?php if ($msg = flash('success')): ?><div class="alert success" style="max-width:420px; margin:20px auto 0;"><?= h($msg) ?></div><?php endif; ?>
<?php endif; ?>
