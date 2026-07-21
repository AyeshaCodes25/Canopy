<?php
require_once __DIR__ . '/includes/functions.php';
require_login();
$user = current_user();
$uid = $user['id'];

$totalNotes = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) c FROM notes WHERE user_id=$uid"))['c'];
$totalCollections = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) c FROM collections WHERE user_id=$uid"))['c'];
$totalFiles = mysqli_fetch_assoc(mysqli_query($mysqli, "
    SELECT COUNT(*) c FROM note_files nf JOIN notes n ON n.id=nf.note_id WHERE n.user_id=$uid"))['c'];

// growth: last 14 days
$growth = [];
$res = mysqli_query($mysqli, "
    SELECT DATE(created_at) d, COUNT(*) c FROM notes
    WHERE user_id=$uid AND created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
    GROUP BY DATE(created_at)");
while ($row = mysqli_fetch_assoc($res)) $growth[$row['d']] = (int)$row['c'];

$labels = []; $data = [];
for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $labels[] = date('M j', strtotime($d));
    $data[] = $growth[$d] ?? 0;
}

$topTags = mysqli_query($mysqli, "
    SELECT t.name, COUNT(*) c FROM tags t
    JOIN note_tag nt ON nt.tag_id=t.id
    JOIN notes n ON n.id=nt.note_id
    WHERE n.user_id=$uid
    GROUP BY t.id ORDER BY c DESC LIMIT 6");
$topTagsArr = mysqli_fetch_all($topTags, MYSQLI_ASSOC);
$maxTagCount = $topTagsArr ? max(array_column($topTagsArr, 'c')) : 1;

$recent = mysqli_query($mysqli, "SELECT * FROM notes WHERE user_id=$uid ORDER BY created_at DESC LIMIT 6");
$recentArr = mysqli_fetch_all($recent, MYSQLI_ASSOC);

$pageTitle = 'Dashboard';
$active = 'dashboard';
include __DIR__ . '/includes/header.php';
?>
<div class="page-header">
  <div>
    <div class="page-title">Dashboard</div>
    <div class="page-sub">Your knowledge, at a glance.</div>
  </div>
  <a href="<?= BASE_URL ?>/notes/create.php" class="btn">+ New Note</a>
</div>

<div class="stat-grid">
  <div class="stat-card"><div class="stat-label">Total Notes</div><div class="stat-num"><?= $totalNotes ?></div></div>
  <div class="stat-card"><div class="stat-label">Collections</div><div class="stat-num"><?= $totalCollections ?></div></div>
  <div class="stat-card"><div class="stat-label">Files Stored</div><div class="stat-num"><?= $totalFiles ?></div></div>
</div>

<div class="grid-2">
  <div class="panel">
    <div class="panel-title">Knowledge Growth <span style="font-family:var(--mono); font-size:10px; color:var(--ink-dim); font-weight:400;">last 14 days</span></div>
    <canvas id="growthChart" height="110"></canvas>
  </div>
  <div class="panel">
    <div class="panel-title">Top Tags</div>
    <?php if ($topTagsArr): foreach ($topTagsArr as $t): ?>
      <div class="bar-row">
        <span class="bar-label">#<?= h($t['name']) ?></span>
        <div class="bar-track"><div class="bar-fill" style="width:<?= ($t['c']/$maxTagCount*100) ?>%"></div></div>
        <span class="bar-count"><?= $t['c'] ?></span>
      </div>
    <?php endforeach; else: ?>
      <div class="empty">Tag some notes to see stats here.</div>
    <?php endif; ?>
  </div>
</div>

<div class="panel">
  <div class="panel-title">Recent Uploads</div>
  <div class="note-grid">
    <?php if ($recentArr): foreach ($recentArr as $n): ?>
      <a href="<?= BASE_URL ?>/notes/view.php?id=<?= $n['id'] ?>" class="note-card">
        <div class="stamp"><?= h(ucfirst($n['type'])) ?></div>
        <h3><?= h($n['title']) ?></h3>
        <p><?= h(strip_tags($n['content'])) ?></p>
        <div class="note-meta"><?= time_ago($n['created_at']) ?></div>
      </a>
    <?php endforeach; else: ?>
      <div class="empty" style="grid-column:1/-1;">No notes yet — <a href="<?= BASE_URL ?>/notes/create.php" style="color:var(--green-800); font-weight:600;">create your first one</a>.</div>
    <?php endif; ?>
  </div>
</div>

<script>
new Chart(document.getElementById('growthChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($labels) ?>,
        datasets: [{
            data: <?= json_encode($data) ?>,
            borderColor: '#2C7A52',
            backgroundColor: 'rgba(44,122,82,0.12)',
            fill: true, tension: 0.35, pointRadius: 2
        }]
    },
    options: {
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { precision: 0, color:'#4C5F55' }, grid: { color:'#E1DAAE' } },
            x: { ticks: { color:'#4C5F55' }, grid: { display:false } }
        }
    }
});
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
