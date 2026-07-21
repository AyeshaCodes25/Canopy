<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
$user = current_user();
$uid = $user['id'];
$errors = [];

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
    if (!in_array($type, ['note','link','article','code'], true)) $errors[] = 'Invalid type.';

    if (!$errors) {
        $stmt = mysqli_prepare($mysqli, "INSERT INTO notes (user_id, collection_id, title, content, type, source_url, is_pinned) VALUES (?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "iissssi", $uid, $collectionId, $title, $content, $type, $sourceUrl, $isPinned);
        // note: collection_id may be null; mysqli bind_param with "i" and null works fine (sends NULL)
        mysqli_stmt_execute($stmt);
        $noteId = mysqli_insert_id($mysqli);

        // tags
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

        // files
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

        flash('success', 'Note created.');
        redirect('/notes/view.php?id=' . $noteId);
    }
}

$collections = mysqli_fetch_all(mysqli_query($mysqli, "SELECT * FROM collections WHERE user_id=$uid ORDER BY name"), MYSQLI_ASSOC);

$pageTitle = 'New Note';
$active = 'vault';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
  <div><div class="page-title">New Note</div><div class="page-sub">Plant a new idea in your vault.</div></div>
</div>

<?php if ($errors): ?><div class="alert error"><?= implode('<br>', array_map('h', $errors)) ?></div><?php endif; ?>

<div class="panel" style="max-width:760px;">
  <form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <div class="field">
      <label class="label">Title</label>
      <input type="text" name="title" value="<?= h($_POST['title'] ?? '') ?>" required autofocus>
    </div>
    <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:14px;">
      <div class="field">
        <label class="label">Type</label>
        <select name="type">
          <?php foreach (['note'=>'Note','link'=>'Link','article'=>'Article','code'=>'Code Snippet'] as $val=>$label): ?>
            <option value="<?= $val ?>"><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label class="label">Collection</label>
        <select name="collection_id">
          <option value="">Uncategorized</option>
          <?php foreach ($collections as $c): ?>
            <option value="<?= $c['id'] ?>"><?= h($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label class="label">Source URL</label>
        <input type="url" name="source_url" placeholder="https://…">
      </div>
    </div>
    <div class="field">
      <label class="label">Tags (comma-separated)</label>
      <input type="text" name="tags" placeholder="php, mysqli, tutorial">
    </div>
    <div class="field">
      <label class="label">Content</label>
      <div id="editor" style="background:#fff; border:1.5px solid var(--cream-line); border-radius:9px; min-height:220px;"></div>
      <input type="hidden" name="content" id="content-input">
    </div>
    <div class="field">
      <label class="label">Attach Files (PDF, images, videos — max 20MB each)</label>
      <input type="file" name="files[]" multiple>
    </div>
    <label style="display:flex; align-items:center; gap:8px; font-size:13px; margin-bottom:20px;">
      <input type="checkbox" name="is_pinned"> Pin this note
    </label>
    <div style="display:flex; gap:10px;">
      <button type="submit" class="btn">Save Note</button>
      <a href="<?= BASE_URL ?>/notes/index.php" class="btn ghost">Cancel</a>
    </div>
  </form>
</div>

<link href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js"></script>
<script>
  const quill = new Quill('#editor', { theme: 'snow' });
  document.querySelector('form').addEventListener('submit', function () {
      document.getElementById('content-input').value = quill.root.innerHTML;
  });
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
