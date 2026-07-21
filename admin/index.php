<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$totalUsers = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) c FROM users"))['c'];
$totalNotes = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) c FROM notes"))['c'];
$totalStorage = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COALESCE(SUM(size),0) c FROM note_files"))['c'];

$recentUsers = mysqli_fetch_all(mysqli_query($mysqli, "SELECT * FROM users ORDER BY created_at DESC LIMIT 8"), MYSQLI_ASSOC);

$byType = mysqli_fetch_all(mysqli_query($mysqli, "SELECT type, COUNT(*) c FROM notes GROUP BY type"), MYSQLI_ASSOC);
$typeLabels = array_column($byType, 'type');
$typeCounts = array_column($byType, 'c');

$pageTitle = 'Admin Panel';
$active = 'admin';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-header"><div><div class="page-title">Admin Panel</div><div class="page-sub">Platform-wide overview.</div></div></div>

<div class="stat-grid">
  <div class="stat-card"><div class="stat-label">Total Users</div><div class="stat-num"><?= $totalUsers ?></div></div>
  <div class="stat-card"><div class="stat-label">Total Notes</div><div class="stat-num"><?= $totalNotes ?></div></div>
  <div class="stat-card"><div class="stat-label">Storage Used</div><div class="stat-num"><?= round($totalStorage/1048576, 1) ?> MB</div></div>
</div>

<div class="grid-2">
  <div class="panel">
    <div class="panel-title">Recent Users <a href="<?= BASE_URL ?>/admin/users.php" style="font-size:12px; color:var(--green-800); font-weight:600;">Manage all →</a></div>
    <?php foreach ($recentUsers as $u): ?>
      <div style="display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid var(--cream-line);">
        <div>
          <div style="font-weight:600; font-size:13.5px;"><?= h($u['name']) ?></div>
          <div style="font-size:11.5px; color:var(--ink-dim);"><?= h($u['email']) ?></div>
        </div>
        <span class="badge <?= $u['role']==='admin'?'admin':'' ?>"><?= $u['role'] ?></span>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="panel">
    <div class="panel-title">Notes by Type</div>
    <canvas id="typeChart" height="180"></canvas>
  </div>
</div>

<script>
new Chart(document.getElementById('typeChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($typeLabels) ?>,
        datasets: [{ data: <?= json_encode($typeCounts) ?>, backgroundColor: ['#0A3323','#2C7A52','#C9A24B','#B5654F'] }]
    },
    options: { plugins: { legend: { labels: { color:'#4C5F55' } } } }
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
