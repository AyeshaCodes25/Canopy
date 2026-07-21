<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
$user = current_user();
$uid = $user['id'];

$collectionFilter = $_GET['collection'] ?? '';
$typeFilter = $_GET['type'] ?? '';

$conds = ["n.user_id = $uid"];
if ($collectionFilter !== '') $conds[] = "n.collection_id = " . intval($collectionFilter);
if ($typeFilter !== '') $conds[] = "n.type = '" . mysqli_real_escape_string($mysqli, $typeFilter) . "'";
$where = implode(' AND ', $conds);

$notes = mysqli_query($mysqli, "
    SELECT n.*, c.name AS collection_name, c.color AS collection_color
    FROM notes n LEFT JOIN collections c ON c.id = n.collection_id
    WHERE $where ORDER BY n.is_pinned DESC, n.created_at DESC");
$notesArr = mysqli_fetch_all($notes, MYSQLI_ASSOC);

// attach tags
foreach ($notesArr as &$n) {
    $tagsRes = mysqli_query($mysqli, "SELECT t.name FROM tags t JOIN note_tag nt ON nt.tag_id=t.id WHERE nt.note_id={$n['id']}");
    $n['tags'] = mysqli_fetch_all($tagsRes, MYSQLI_ASSOC);
}
unset($n);

$collections = mysqli_fetch_all(mysqli_query($mysqli, "SELECT * FROM collections WHERE user_id=$uid ORDER BY name"), MYSQLI_ASSOC);

$pageTitle = 'Knowledge Vault';
$active = 'vault';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
  <div>
    <div class="page-title">Knowledge Vault</div>
    <div class="page-sub"><?= count($notesArr) ?> note<?= count($notesArr) !== 1 ? 's' : '' ?></div>
  </div>
  <a href="<?= BASE_URL ?>/notes/create.php" class="btn">+ New Note</a>
</div>

<div class="pill-row">
  <a href="?<?= http_build_query(['type'=>$typeFilter]) ?>" class="pill <?= $collectionFilter===''?'active':'' ?>">All</a>
  <?php foreach ($collections as $c): ?>
    <a href="?<?= http_build_query(['collection'=>$c['id'], 'type'=>$typeFilter]) ?>" class="pill <?= $collectionFilter == $c['id'] ? 'active' : '' ?>">
      <span class="dot" style="background:<?= h($c['color']) ?>"></span><?= h($c['name']) ?>
    </a>
  <?php endforeach; ?>
  <span style="width:1px; background:var(--cream-line); margin:2px 4px;"></span>
  <?php foreach (['note','link','article','code'] as $t): ?>
    <a href="?<?= http_build_query(['collection'=>$collectionFilter, 'type'=> $typeFilter===$t ? '' : $t]) ?>" class="pill <?= $typeFilter===$t?'active':'' ?>"><?= ucfirst($t) ?></a>
  <?php endforeach; ?>
</div>

<div class="note-grid">
  <?php if ($notesArr): foreach ($notesArr as $n): ?>
    <a href="<?= BASE_URL ?>/notes/view.php?id=<?= $n['id'] ?>" class="note-card">
      <?php if ($n['is_pinned']): ?><div class="pin">📌</div><?php endif; ?>
      <div class="stamp"><?= h(ucfirst($n['type'])) ?></div>
      <h3><?= h($n['title']) ?></h3>
      <p><?= h(strip_tags($n['content'])) ?></p>
      <div class="tag-row">
        <?php foreach ($n['tags'] as $t): ?><span class="tag-chip">#<?= h($t['name']) ?></span><?php endforeach; ?>
      </div>
      <div class="note-meta"><?= h($n['collection_name'] ?? 'Uncategorized') ?> · <?= time_ago($n['created_at']) ?></div>
    </a>
  <?php endforeach; else: ?>
    <div class="empty" style="grid-column:1/-1;">No notes found. <a href="<?= BASE_URL ?>/notes/create.php" style="color:var(--green-800); font-weight:600;">Create one</a>.</div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
