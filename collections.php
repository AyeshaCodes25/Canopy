<?php
require_once __DIR__ . '/includes/functions.php';
require_login();
$user = current_user();
$uid = $user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['action'] ?? '') === 'add') {
        $name = trim($_POST['name'] ?? '');
        $color = $_POST['color'] ?? '#0A3323';
        if ($name !== '') {
            $stmt = mysqli_prepare($mysqli, "INSERT INTO collections (user_id, name, color) VALUES (?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "iss", $uid, $name, $color);
            mysqli_stmt_execute($stmt);
            flash('success', 'Collection created.');
        }
    } elseif (($_POST['action'] ?? '') === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        $stmt = mysqli_prepare($mysqli, "DELETE FROM collections WHERE id=? AND user_id=?");
        mysqli_stmt_bind_param($stmt, "ii", $id, $uid);
        mysqli_stmt_execute($stmt);
        flash('success', 'Collection deleted.');
    }
    redirect('/collections.php');
}

$collections = mysqli_fetch_all(mysqli_query($mysqli, "
    SELECT c.*, (SELECT COUNT(*) FROM notes n WHERE n.collection_id=c.id) AS note_count
    FROM collections c WHERE c.user_id=$uid ORDER BY c.name"), MYSQLI_ASSOC);

$pageTitle = 'Collections';
$active = 'collections';
include __DIR__ . '/includes/header.php';
?>
<div class="page-header"><div><div class="page-title">Collections</div><div class="page-sub">Organize notes into folders.</div></div></div>

<div class="panel">
  <form method="POST" style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="action" value="add">
    <div class="field" style="margin:0; flex:1; min-width:200px;">
      <label class="label">Name</label>
      <input type="text" name="name" placeholder="e.g. Programming" required>
    </div>
    <div class="field" style="margin:0;">
      <label class="label">Color</label>
      <input type="color" name="color" value="#0A3323" style="height:44px; width:60px; padding:4px;">
    </div>
    <button type="submit" class="btn">+ Add Collection</button>
  </form>
</div>

<div class="note-grid">
  <?php if ($collections): foreach ($collections as $c): ?>
    <div class="stat-card">
      <div style="display:flex; justify-content:space-between; align-items:center;">
        <span class="dot" style="width:16px; height:16px; background:<?= h($c['color']) ?>"></span>
        <form method="POST" onsubmit="return confirm('Delete this collection?');">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= $c['id'] ?>">
          <button type="submit" style="background:none; border:none; color:var(--ink-dim); cursor:pointer; font-size:15px;">✕</button>
        </form>
      </div>
      <a href="<?= BASE_URL ?>/notes/index.php?collection=<?= $c['id'] ?>" style="font-family:var(--serif); font-weight:600; font-size:17px; margin-top:10px; display:block; color:var(--green-950);"><?= h($c['name']) ?></a>
      <div class="stat-label" style="margin-top:6px;"><?= $c['note_count'] ?> note<?= $c['note_count']!=1?'s':'' ?></div>
    </div>
  <?php endforeach; else: ?>
    <div class="empty">No collections yet — create one above.</div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
