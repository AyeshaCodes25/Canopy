<?php
/**
 * Canopy REST API — /api/notes.php
 *
 * Auth: Authorization: Bearer <token>   (generate tokens at /api-tokens.php)
 *
 *   GET    /api/notes.php            List all your notes (paginated)
 *   GET    /api/notes.php?id=5       Get one note, with tags/files/related notes
 *   POST   /api/notes.php            Create a note (JSON body)
 *   PUT    /api/notes.php?id=5       Update a note (JSON body)
 *   DELETE /api/notes.php?id=5       Delete a note
 *
 * JSON body fields (POST/PUT): title, content, type, source_url,
 * collection_id, tags (array of strings)
 */

require_once __DIR__ . '/../includes/api_auth.php';

// Basic CORS support so this can genuinely be called from another origin
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$user = api_authenticate($mysqli);
$uid = $user['id'];
$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? intval($_GET['id']) : null;

function fetch_note_full($mysqli, $noteId, $uid) {
    $stmt = mysqli_prepare($mysqli, "
        SELECT n.*, c.name AS collection_name FROM notes n
        LEFT JOIN collections c ON c.id = n.collection_id
        WHERE n.id=? AND n.user_id=?");
    mysqli_stmt_bind_param($stmt, "ii", $noteId, $uid);
    mysqli_stmt_execute($stmt);
    $note = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    if (!$note) return null;

    $note['tags'] = array_column(mysqli_fetch_all(mysqli_query($mysqli,
        "SELECT t.name FROM tags t JOIN note_tag nt ON nt.tag_id=t.id WHERE nt.note_id=$noteId"), MYSQLI_ASSOC), 'name');

    $note['files'] = mysqli_fetch_all(mysqli_query($mysqli,
        "SELECT id, original_name, mime_type, size, created_at FROM note_files WHERE note_id=$noteId"), MYSQLI_ASSOC);

    $note['related_notes'] = mysqli_fetch_all(mysqli_query($mysqli, "
        SELECT n2.id, n2.title, r.reason FROM note_relationships r
        JOIN notes n2 ON n2.id = r.related_note_id
        WHERE r.note_id=$noteId"), MYSQLI_ASSOC);

    return $note;
}

function sync_tags_api($mysqli, $noteId, $uid, array $tagNames) {
    mysqli_query($mysqli, "DELETE FROM note_tag WHERE note_id=$noteId");
    foreach (array_unique(array_filter(array_map('trim', $tagNames))) as $name) {
        $stmt = mysqli_prepare($mysqli, "INSERT INTO tags (user_id, name) VALUES (?, ?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
        mysqli_stmt_bind_param($stmt, "is", $uid, $name);
        mysqli_stmt_execute($stmt);
        $tagId = mysqli_insert_id($mysqli);
        $stmt2 = mysqli_prepare($mysqli, "INSERT IGNORE INTO note_tag (note_id, tag_id) VALUES (?, ?)");
        mysqli_stmt_bind_param($stmt2, "ii", $noteId, $tagId);
        mysqli_stmt_execute($stmt2);
    }
}

switch ($method) {

    case 'GET':
        if ($id) {
            $note = fetch_note_full($mysqli, $id, $uid);
            if (!$note) api_error(404, 'Note not found.');
            api_json($note);
        } else {
            $page = max(1, intval($_GET['page'] ?? 1));
            $perPage = min(50, max(1, intval($_GET['per_page'] ?? 15)));
            $offset = ($page - 1) * $perPage;

            $total = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) c FROM notes WHERE user_id=$uid"))['c'];

            $stmt = mysqli_prepare($mysqli, "
                SELECT n.*, c.name AS collection_name FROM notes n
                LEFT JOIN collections c ON c.id = n.collection_id
                WHERE n.user_id=? ORDER BY n.created_at DESC LIMIT ? OFFSET ?");
            mysqli_stmt_bind_param($stmt, "iii", $uid, $perPage, $offset);
            mysqli_stmt_execute($stmt);
            $notes = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);

            foreach ($notes as &$n) {
                $n['tags'] = array_column(mysqli_fetch_all(mysqli_query($mysqli,
                    "SELECT t.name FROM tags t JOIN note_tag nt ON nt.tag_id=t.id WHERE nt.note_id={$n['id']}"), MYSQLI_ASSOC), 'name');
            }
            unset($n);

            api_json([
                'data' => $notes,
                'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => (int)$total],
            ]);
        }
        break;

    case 'POST':
        $body = api_body();
        $title = trim($body['title'] ?? '');
        if ($title === '') api_error(422, 'The "title" field is required.');

        $type = in_array($body['type'] ?? 'note', ['note', 'link', 'article', 'code'], true) ? $body['type'] : 'note';
        $content = $body['content'] ?? '';
        $sourceUrl = $body['source_url'] ?? '';
        $collectionId = !empty($body['collection_id']) ? intval($body['collection_id']) : null;

        $stmt = mysqli_prepare($mysqli, "INSERT INTO notes (user_id, collection_id, title, content, type, source_url) VALUES (?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "iissss", $uid, $collectionId, $title, $content, $type, $sourceUrl);
        mysqli_stmt_execute($stmt);
        $noteId = mysqli_insert_id($mysqli);

        if (!empty($body['tags']) && is_array($body['tags'])) {
            sync_tags_api($mysqli, $noteId, $uid, $body['tags']);
        }

        sync_smart_relationships($mysqli, $noteId, $uid);

        api_json(fetch_note_full($mysqli, $noteId, $uid), 201);
        break;

    case 'PUT':
        if (!$id) api_error(400, 'Missing ?id= for update.');
        $existing = fetch_note_full($mysqli, $id, $uid);
        if (!$existing) api_error(404, 'Note not found.');

        $body = api_body();
        $title = trim($body['title'] ?? $existing['title']);
        $type = in_array($body['type'] ?? $existing['type'], ['note', 'link', 'article', 'code'], true) ? ($body['type'] ?? $existing['type']) : $existing['type'];
        $content = $body['content'] ?? $existing['content'];
        $sourceUrl = $body['source_url'] ?? $existing['source_url'];
        $collectionId = array_key_exists('collection_id', $body)
            ? (!empty($body['collection_id']) ? intval($body['collection_id']) : null)
            : $existing['collection_id'];

        $stmt = mysqli_prepare($mysqli, "UPDATE notes SET collection_id=?, title=?, content=?, type=?, source_url=? WHERE id=? AND user_id=?");
        mysqli_stmt_bind_param($stmt, "issssii", $collectionId, $title, $content, $type, $sourceUrl, $id, $uid);
        mysqli_stmt_execute($stmt);

        if (isset($body['tags']) && is_array($body['tags'])) {
            sync_tags_api($mysqli, $id, $uid, $body['tags']);
        }

        sync_smart_relationships($mysqli, $id, $uid);

        api_json(fetch_note_full($mysqli, $id, $uid));
        break;

    case 'DELETE':
        if (!$id) api_error(400, 'Missing ?id= for delete.');
        $stmt = mysqli_prepare($mysqli, "SELECT id FROM notes WHERE id=? AND user_id=?");
        mysqli_stmt_bind_param($stmt, "ii", $id, $uid);
        mysqli_stmt_execute($stmt);
        if (!mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))) api_error(404, 'Note not found.');

        $files = mysqli_fetch_all(mysqli_query($mysqli, "SELECT path FROM note_files WHERE note_id=$id"), MYSQLI_ASSOC);
        foreach ($files as $f) {
            $p = UPLOAD_DIR . '/' . $f['path'];
            if (is_file($p)) unlink($p);
        }

        $stmt = mysqli_prepare($mysqli, "DELETE FROM notes WHERE id=? AND user_id=?");
        mysqli_stmt_bind_param($stmt, "ii", $id, $uid);
        mysqli_stmt_execute($stmt);

        api_json(['message' => 'Note deleted.']);
        break;

    default:
        api_error(405, 'Method not allowed.');
}
