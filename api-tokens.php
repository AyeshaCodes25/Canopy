<?php
require_once __DIR__ . '/includes/functions.php';
require_login();
$uid = current_user()['id'];

$newToken = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '') ?: 'API Token';
        $token = bin2hex(random_bytes(32));
        $stmt = mysqli_prepare($mysqli, "INSERT INTO api_tokens (user_id, name, token) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "iss", $uid, $name, $token);
        mysqli_stmt_execute($stmt);
        $newToken = $token; // shown once, on this request only
    } elseif ($action === 'revoke') {
        $id = intval($_POST['id'] ?? 0);
        $stmt = mysqli_prepare($mysqli, "DELETE FROM api_tokens WHERE id=? AND user_id=?");
        mysqli_stmt_bind_param($stmt, "ii", $id, $uid);
        mysqli_stmt_execute($stmt);
        flash('success', 'Token revoked.');
        redirect('/api-tokens.php');
    }
}

$tokens = mysqli_fetch_all(mysqli_query($mysqli, "SELECT * FROM api_tokens WHERE user_id=$uid ORDER BY created_at DESC"), MYSQLI_ASSOC);

$pageTitle = 'API Access';
$active = 'api-tokens';
include __DIR__ . '/includes/header.php';
?>
<div class="page-header">
  <div>
    <div class="page-title">API Access</div>
    <div class="page-sub">Generate tokens to use the Canopy REST API from your own scripts or apps.</div>
  </div>
</div>

<?php if ($newToken): ?>
<div class="panel" style="border-color:var(--green-700);">
  <div class="panel-title">✅ Token created</div>
  <p style="font-size:13px; color:var(--ink-dim); margin-bottom:12px;">
    Copy this now — for your security, it won't be shown again.
  </p>
  <div style="background:var(--cream-dim); border:1px solid var(--cream-line); border-radius:8px; padding:12px 14px; font-family:var(--mono); font-size:13px; word-break:break-all;">
    <?= h($newToken) ?>
  </div>
</div>
<?php endif; ?>

<div class="panel">
  <div class="panel-title">Generate a New Token</div>
  <form method="POST" style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap;">
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
    <input type="hidden" name="action" value="create">
    <div class="field" style="margin:0; flex:1; min-width:200px;">
      <label class="label">Token name</label>
      <input type="text" name="name" placeholder="e.g. My CLI script">
    </div>
    <button type="submit" class="btn">+ Generate Token</button>
  </form>
</div>

<div class="panel" style="padding:0; overflow:hidden;">
  <table>
    <thead><tr><th>Name</th><th>Created</th><th>Last used</th><th></th></tr></thead>
    <tbody>
    <?php if ($tokens): foreach ($tokens as $t): ?>
      <tr>
        <td style="font-weight:600;"><?= h($t['name']) ?></td>
        <td style="color:var(--ink-dim);"><?= date('M j, Y', strtotime($t['created_at'])) ?></td>
        <td style="color:var(--ink-dim);"><?= $t['last_used_at'] ? time_ago($t['last_used_at']) : 'Never' ?></td>
        <td style="text-align:right;">
          <form method="POST" onsubmit="return confirm('Revoke this token? Anything using it will stop working immediately.');">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="revoke">
            <input type="hidden" name="id" value="<?= $t['id'] ?>">
            <button type="submit" style="background:none; border:none; color:var(--rust); font-weight:600; font-size:12px; cursor:pointer;">Revoke</button>
          </form>
        </td>
      </tr>
    <?php endforeach; else: ?>
      <tr><td colspan="4" class="empty">No tokens yet — generate one above.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<div class="panel">
  <div class="panel-title">Quick Reference</div>
  <p style="font-size:13px; color:var(--ink-dim); margin-bottom:10px;">
    Send your token as a Bearer token in the <code>Authorization</code> header:
  </p>
  <pre style="background:var(--cream-dim); border:1px solid var(--cream-line); border-radius:8px; padding:14px; font-size:12px; overflow-x:auto; font-family:var(--mono);">curl <?= (!empty($_SERVER['HTTPS'])?'https':'http') ?>://<?= h($_SERVER['HTTP_HOST'] ?? 'localhost') ?><?= BASE_URL ?>/api/notes.php \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"</pre>
  <p style="font-size:13px; color:var(--ink-dim); margin-top:14px;">
    Endpoints: <code>GET/POST /api/notes.php</code>, <code>GET/PUT/DELETE /api/notes.php?id=5</code>, <code>GET /api/collections.php</code>
  </p>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
