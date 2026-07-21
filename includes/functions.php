<?php
require_once __DIR__ . '/db.php';

function h($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect($path) {
    header('Location: ' . BASE_URL . $path);
    exit;
}

function flash($key, $msg = null) {
    if ($msg !== null) {
        $_SESSION['flash'][$key] = $msg;
        return;
    }
    if (!empty($_SESSION['flash'][$key])) {
        $val = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $val;
    }
    return null;
}

function current_user() {
    if (empty($_SESSION['user_id'])) return null;
    global $mysqli;
    static $cached = null;
    if ($cached) return $cached;
    $stmt = mysqli_prepare($mysqli, "SELECT id, name, email, role, created_at FROM users WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $cached = mysqli_fetch_assoc($result);
    return $cached;
}

function require_login() {
    if (!current_user()) redirect('/login.php');
}

function require_admin() {
    require_login();
    $u = current_user();
    if ($u['role'] !== 'admin') {
        http_response_code(403);
        die('<div style="font-family:sans-serif; padding:60px; text-align:center;">
                <h2>403 — Admins only</h2>
                <p><a href="' . BASE_URL . '/dashboard.php">Back to dashboard</a></p>
             </div>');
    }
}

function is_logged_in() {
    return !empty($_SESSION['user_id']);
}

function csrf_token() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check() {
    $token = $_POST['csrf'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(419);
        die('Session expired. Please go back and try again.');
    }
}

function time_ago($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 2592000) return floor($diff / 86400) . 'd ago';
    return date('M j, Y', strtotime($datetime));
}

/**
 * Recompute smart relationship links for a note: shares a tag, or shares a
 * significant (4+ letter) title word with another note by the same user.
 */
function sync_smart_relationships($mysqli, $note_id, $user_id) {
    mysqli_query($mysqli, "DELETE FROM note_relationships WHERE note_id = $note_id OR related_note_id = $note_id");

    $stmt = mysqli_prepare($mysqli, "SELECT title FROM notes WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $note_id);
    mysqli_stmt_execute($stmt);
    $title = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['title'] ?? '';

    $candidates = []; // related_id => reason

    // Shared tags
    $stmt = mysqli_prepare($mysqli, "
        SELECT DISTINCT n2.id, GROUP_CONCAT(DISTINCT t.name SEPARATOR ', ') AS shared
        FROM note_tag nt1
        JOIN note_tag nt2 ON nt1.tag_id = nt2.tag_id AND nt2.note_id != nt1.note_id
        JOIN notes n2 ON n2.id = nt2.note_id
        JOIN tags t ON t.id = nt1.tag_id
        WHERE nt1.note_id = ? AND n2.user_id = ?
        GROUP BY n2.id
    ");
    mysqli_stmt_bind_param($stmt, "ii", $note_id, $user_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $candidates[$row['id']] = 'shared tag: ' . $row['shared'];
    }

    // Title keyword overlap (words 4+ letters)
    preg_match_all('/\w{4,}/u', strtolower($title), $matches);
    $words = array_unique($matches[0]);
    if ($words) {
        $conds = [];
        $types = 'i';
        $params = [$user_id];
        foreach ($words as $w) {
            $conds[] = "title LIKE CONCAT('%', ?, '%')";
            $types .= 's';
            $params[] = $w;
        }
        $sql = "SELECT id FROM notes WHERE user_id = ? AND id != $note_id AND (" . implode(' OR ', $conds) . ") LIMIT 10";
        $stmt = mysqli_prepare($mysqli, $sql);
        $refs = [];
        foreach ($params as $k => $v) { $refs[$k] = &$params[$k]; }
        array_unshift($refs, $types);
        call_user_func_array([$stmt, 'bind_param'], $refs);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($res)) {
            if (!isset($candidates[$row['id']])) {
                $candidates[$row['id']] = 'related topic';
            }
        }
    }

    foreach ($candidates as $related_id => $reason) {
        $stmt = mysqli_prepare($mysqli, "INSERT IGNORE INTO note_relationships (note_id, related_note_id, reason) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "iis", $note_id, $related_id, $reason);
        mysqli_stmt_execute($stmt);
    }
}
