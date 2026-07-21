<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/notes/index.php');
csrf_check();

$uid = current_user()['id'];
$noteId = intval($_POST['id'] ?? 0);

$stmt = mysqli_prepare($mysqli, "SELECT id FROM notes WHERE id=? AND user_id=?");
mysqli_stmt_bind_param($stmt, "ii", $noteId, $uid);
mysqli_stmt_execute($stmt);
if (!mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))) redirect('/notes/index.php');

$files = mysqli_fetch_all(mysqli_query($mysqli, "SELECT path FROM note_files WHERE note_id=$noteId"), MYSQLI_ASSOC);
foreach ($files as $f) {
    $p = UPLOAD_DIR . '/' . $f['path'];
    if (is_file($p)) unlink($p);
}

$stmt = mysqli_prepare($mysqli, "DELETE FROM notes WHERE id=? AND user_id=?");
mysqli_stmt_bind_param($stmt, "ii", $noteId, $uid);
mysqli_stmt_execute($stmt);

flash('success', 'Note deleted.');
redirect('/notes/index.php');
