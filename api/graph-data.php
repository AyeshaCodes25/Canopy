<?php
// First-party endpoint for the in-app graph view — uses the normal logged-in
// session (not an API token), since this powers Canopy's own UI rather than
// third-party integrations.
require_once __DIR__ . '/../includes/functions.php';
require_login();
header('Content-Type: application/json');

$uid = current_user()['id'];

$notes = mysqli_fetch_all(mysqli_query($mysqli, "
    SELECT n.id, n.title, n.type, n.collection_id, c.name AS collection_name, c.color AS collection_color
    FROM notes n LEFT JOIN collections c ON c.id = n.collection_id
    WHERE n.user_id = $uid
"), MYSQLI_ASSOC);

$edgesRaw = mysqli_fetch_all(mysqli_query($mysqli, "
    SELECT r.note_id, r.related_note_id, r.reason
    FROM note_relationships r
    JOIN notes n ON n.id = r.note_id
    WHERE n.user_id = $uid
"), MYSQLI_ASSOC);

// De-duplicate mirrored pairs (A->B and B->A both exist by design) for a cleaner graph
$seen = [];
$edges = [];
foreach ($edgesRaw as $e) {
    $key = min($e['note_id'], $e['related_note_id']) . '-' . max($e['note_id'], $e['related_note_id']);
    if (isset($seen[$key])) continue;
    $seen[$key] = true;
    $edges[] = ['source' => (int)$e['note_id'], 'target' => (int)$e['related_note_id'], 'reason' => $e['reason']];
}

$nodes = array_map(function ($n) {
    return [
        'id' => (int)$n['id'],
        'title' => $n['title'],
        'type' => $n['type'],
        'collection' => $n['collection_name'],
        'color' => $n['collection_color'] ?: '#0A3323',
    ];
}, $notes);

echo json_encode(['nodes' => $nodes, 'links' => $edges]);
