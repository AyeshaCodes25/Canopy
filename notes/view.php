<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
$user = current_user();
$uid = $user['id'];
$noteId = intval($_GET['id'] ?? 0);

$stmt = mysqli_prepare($mysqli, "
    SELECT n.*, c.name AS collection_name FROM notes n
    LEFT JOIN collections c ON c.id = n.collection_id
    WHERE n.id=? AND n.user_id=?");
mysqli_stmt_bind_param($stmt, "ii", $noteId, $uid);
mysqli_stmt_execute($stmt);
$note = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$note) { http_response_code(404); die('Note not found.'); }

$tags = mysqli_fetch_all(mysqli_query($mysqli, "SELECT t.name FROM tags t JOIN note_tag nt ON nt.tag_id=t.id WHERE nt.note_id=$noteId"), MYSQLI_ASSOC);
$files = mysqli_fetch_all(mysqli_query($mysqli, "SELECT * FROM note_files WHERE note_id=$noteId ORDER BY created_at DESC"), MYSQLI_ASSOC);
$related = mysqli_fetch_all(mysqli_query($mysqli, "
    SELECT n2.id, n2.title, r.reason FROM note_relationships r
    JOIN notes n2 ON n2.id = IF(r.note_id = $noteId, r.related_note_id, r.note_id)
    WHERE r.note_id = $noteId OR r.related_note_id = $noteId"), MYSQLI_ASSOC);

function human_size($bytes) {
    $units = ['B','KB','MB','GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units)-1) { $bytes /= 1024; $i++; }
    return round($bytes, 1) . ' ' . $units[$i];
}

$pageTitle = $note['title'];
$active = 'vault';
include __DIR__ . '/../includes/header.php';
?>
<div class="grid-2">
  <div class="note-detail">
    <div class="note-detail-head">
      <div>
        <div style="font-family:var(--mono); font-size:11px; text-transform:uppercase; letter-spacing:.08em; color:var(--green-700);">
          <?= h(ucfirst($note['type'])) ?> · <?= h($note['collection_name'] ?? 'Uncategorized') ?>
        </div>
        <h1><?= h($note['title']) ?></h1>
        <div style="font-size:12px; color:var(--ink-dim);">Updated <?= time_ago($note['updated_at']) ?></div>
      </div>
      <div style="display:flex; gap:8px; flex-shrink:0;">
        <a href="<?= BASE_URL ?>/notes/edit.php?id=<?= $note['id'] ?>" class="btn ghost sm">Edit</a>
        <form method="POST" action="<?= BASE_URL ?>/notes/delete.php" onsubmit="return confirm('Delete this note?');">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <input type="hidden" name="id" value="<?= $note['id'] ?>">
          <button type="submit" class="btn danger sm">Delete</button>
        </form>
      </div>
    </div>

    <?php if ($note['source_url']): ?>
      <a href="<?= h($note['source_url']) ?>" target="_blank" style="display:block; color:var(--green-800); font-size:13px; margin-bottom:16px;">🔗 <?= h($note['source_url']) ?></a>
    <?php endif; ?>

    <div class="note-body"><?= $note['content'] ?></div>

    <div class="tag-row" style="margin-top:20px;">
      <?php foreach ($tags as $t): ?><span class="tag-chip">#<?= h($t['name']) ?></span><?php endforeach; ?>
    </div>

    <?php if ($files): ?>
    <div style="margin-top:24px; border-top:1px solid var(--cream-line); padding-top:16px;">
      <div class="panel-title" style="font-size:14px; margin-bottom:12px;">Attachments</div>
      <?php foreach ($files as $f): ?>
        <a href="<?= UPLOAD_URL . '/' . h($f['path']) ?>" target="_blank" class="file-row">
          <span>📎 <?= h($f['original_name']) ?></span>
          <span style="color:var(--ink-dim); font-family:var(--mono); font-size:11px;"><?= human_size($f['size']) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <div class="panel" style="height:fit-content;">
    <div class="panel-title">🕸️ Related Notes</div>
    <?php if ($related): foreach ($related as $r): ?>
      <a href="<?= BASE_URL ?>/notes/view.php?id=<?= $r['id'] ?>" class="related-item">
        <div class="t"><?= h($r['title']) ?></div>
        <div class="r"><?= h($r['reason']) ?></div>
      </a>
    <?php endforeach; else: ?>
      <div class="empty">No related notes yet. Add shared tags to link notes together.</div>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
