<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
$user = current_user();
$uid = $user['id'];
$noteId = intval($_GET['id'] ?? 0);
$errors = [];

$stmt = mysqli_prepare($mysqli, "SELECT * FROM notes WHERE id=? AND user_id=?");
mysqli_stmt_bind_param($stmt, "ii", $noteId, $uid);
mysqli_stmt_execute($stmt);
$note = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$note) { http_response_code(404); die('Note not found.'); }

$tagsRes = mysqli_query($mysqli, "SELECT t.name FROM tags t JOIN note_tag nt ON nt.tag_id=t.id WHERE nt.note_id=$noteId");
$existingTags = implode(', ', array_column(mysqli_fetch_all($tagsRes, MYSQLI_ASSOC), 'name'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $title = trim($_POST['title'] ?? '');
    $content = $_POST['content'] ?? '';
    $type = $_POST['type'] ?? 'note';
    $sourceUrl = trim($_POST['source_url'] ?? '');
    $collectionId = $_POST['collection_id'] !== '' ? intval($_POST['collection_id']) : null;
    $tagsRaw = trim($_POST['tags'] ?? '');
    $isPinned = isset($_POST['is_pinned']) ? 1 : 0;

    if ($title === '') $errors[] = 'Title is required.';

    if (!$errors) {
        $stmt = mysqli_prepare($mysqli, "UPDATE notes SET collection_id=?, title=?, content=?, type=?, source_url=?, is_pinned=? WHERE id=? AND user_id=?");
        mysqli_stmt_bind_param($stmt, "issssiii", $collectionId, $title, $content, $type, $sourceUrl, $isPinned, $noteId, $uid);
        mysqli_stmt_execute($stmt);

        mysqli_query($mysqli, "DELETE FROM note_tag WHERE note_id=$noteId");
        if ($tagsRaw !== '') {
            $names = array_unique(array_filter(array_map('trim', explode(',', $tagsRaw))));
            foreach ($names as $name) {
                $stmt = mysqli_prepare($mysqli, "INSERT INTO tags (user_id, name) VALUES (?, ?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
                mysqli_stmt_bind_param($stmt, "is", $uid, $name);
                mysqli_stmt_execute($stmt);
                $tagId = mysqli_insert_id($mysqli);
                $stmt2 = mysqli_prepare($mysqli, "INSERT IGNORE INTO note_tag (note_id, tag_id) VALUES (?, ?)");
                mysqli_stmt_bind_param($stmt2, "ii", $noteId, $tagId);
                mysqli_stmt_execute($stmt2);
            }
        }

        if (!empty($_FILES['files']) && is_array($_FILES['files']['name'])) {
            if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0777, true);
            foreach ($_FILES['files']['name'] as $i => $origName) {
                if ($_FILES['files']['error'][$i] !== UPLOAD_ERR_OK || $origName === '') continue;
                if ($_FILES['files']['size'][$i] > MAX_UPLOAD_BYTES) continue;
                $safeName = uniqid('f_') . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $origName);
                $dest = UPLOAD_DIR . '/' . $safeName;
                if (move_uploaded_file($_FILES['files']['tmp_name'][$i], $dest)) {
                    $stmt = mysqli_prepare($mysqli, "INSERT INTO note_files (note_id, original_name, path, mime_type, size) VALUES (?, ?, ?, ?, ?)");
                    $mime = $_FILES['files']['type'][$i];
                    $size = $_FILES['files']['size'][$i];
                    mysqli_stmt_bind_param($stmt, "isssi", $noteId, $origName, $safeName, $mime, $size);
                    mysqli_stmt_execute($stmt);
                }
            }
        }

        sync_smart_relationships($mysqli, $noteId, $uid);

        flash('success', 'Note updated.');
        redirect('/notes/view.php?id=' . $noteId);
    }
}

$collections = mysqli_fetch_all(mysqli_query($mysqli, "SELECT * FROM collections WHERE user_id=$uid ORDER BY name"), MYSQLI_ASSOC);

$pageTitle = 'Edit Note';
$active = 'vault';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-header"><div><div class="page-title">Edit Note</div></div></div>
<?php if ($errors): ?><div class="alert error"><?= implode('<br>', array_map('h', $errors)) ?></div><?php endif; ?>

<div class="panel" style="max-width:760px;">
  <form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <div class="field">
      <label class="label">Title</label>
      <input type="text" name="title" value="<?= h($note['title']) ?>" required autofocus>
    </div>
    <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:14px;">
      <div class="field">
        <label class="label">Type</label>
        <select name="type">
          <?php foreach (['note'=>'Note','link'=>'Link','article'=>'Article','code'=>'Code Snippet'] as $val=>$label): ?>
            <option value="<?= $val ?>" <?= $note['type']===$val?'selected':'' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label class="label">Collection</label>
        <select name="collection_id">
          <option value="">Uncategorized</option>
          <?php foreach ($collections as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $note['collection_id']==$c['id']?'selected':'' ?>><?= h($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label class="label">Source URL</label>
        <input type="url" name="source_url" value="<?= h($note['source_url']) ?>">
      </div>
    </div>
    <div class="field">
      <label class="label">Tags (comma-separated)</label>
      <input type="text" name="tags" value="<?= h($existingTags) ?>">
    </div>
    <div class="field">
      <label class="label">Content</label>
      <div id="editor" style="background:#fff; border:1.5px solid var(--cream-line); border-radius:9px; min-height:220px;"></div>
      <input type="hidden" name="content" id="content-input">
    </div>
    <div class="field">
      <label class="label">Attach More Files</label>
      <input type="file" name="files[]" multiple>
    </div>
    <label style="display:flex; align-items:center; gap:8px; font-size:13px; margin-bottom:20px;">
      <input type="checkbox" name="is_pinned" <?= $note['is_pinned']?'checked':'' ?>> Pin this note
    </label>
    <div style="display:flex; gap:10px;">
      <button type="submit" class="btn">Update Note</button>
      <a href="<?= BASE_URL ?>/notes/view.php?id=<?= $noteId ?>" class="btn ghost">Cancel</a>
    </div>
  </form>
</div>

<link href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js"></script>
<script>
  const quill = new Quill('#editor', { theme: 'snow' });
  quill.root.innerHTML = <?= json_encode($note['content']) ?>;
  document.querySelector('form').addEventListener('submit', function () {
      document.getElementById('content-input').value = quill.root.innerHTML;
  });
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
