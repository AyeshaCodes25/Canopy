<?php
/**
 * Canopy REST API — /api/collections.php
 * Auth: Authorization: Bearer <token>
 *
 *   GET /api/collections.php   List your collections
 */

require_once __DIR__ . '/../includes/api_auth.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$user = api_authenticate($mysqli);
$uid = $user['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    api_error(405, 'Method not allowed.');
}

$collections = mysqli_fetch_all(mysqli_query($mysqli, "
    SELECT c.*, (SELECT COUNT(*) FROM notes n WHERE n.collection_id=c.id) AS note_count
    FROM collections c WHERE c.user_id=$uid ORDER BY c.name"), MYSQLI_ASSOC);

api_json(['data' => $collections]);
