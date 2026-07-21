<?php
require_once __DIR__ . '/functions.php';

/**
 * Authenticates a REST API request via "Authorization: Bearer <token>".
 * On success, returns the user row (id, name, email, role).
 * On failure, sends a 401 JSON error and terminates the script.
 */
function api_authenticate($mysqli): array {
    $header = $_SERVER['HTTP_AUTHORIZATION']
        ?? apache_request_headers()['Authorization']
        ?? '';

    if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
        api_error(401, 'Missing or malformed Authorization header. Expected: Bearer <token>');
    }
    $token = trim($m[1]);

    $stmt = mysqli_prepare($mysqli, "
        SELECT u.id, u.name, u.email, u.role
        FROM api_tokens t
        JOIN users u ON u.id = t.user_id
        WHERE t.token = ?
    ");
    mysqli_stmt_bind_param($stmt, "s", $token);
    mysqli_stmt_execute($stmt);
    $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$user) {
        api_error(401, 'Invalid API token.');
    }

    $upd = mysqli_prepare($mysqli, "UPDATE api_tokens SET last_used_at = NOW() WHERE token = ?");
    mysqli_stmt_bind_param($upd, "s", $token);
    mysqli_stmt_execute($upd);

    return $user;
}

function api_json($data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function api_error(int $status, string $message): void {
    api_json(['error' => $message], $status);
}

function api_body(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}
