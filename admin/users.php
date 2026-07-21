<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();
$me = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $id = intval($_POST['id'] ?? 0);

    if ($action === 'toggle-admin') {
        $u = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT role FROM users WHERE id=$id"));
        if ($u) {
            $newRole = $u['role'] === 'admin' ? 'user' : 'admin';
            $stmt = mysqli_prepare($mysqli, "UPDATE users SET role=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, "si", $newRole, $id);
            mysqli_stmt_execute($stmt);
            flash('success', 'User role updated.');
        }
    } elseif ($action === 'delete') {
        if ($id === $me['id']) {
            flash('error', "You can't delete your own account.");
        } else {
            $stmt = mysqli_prepare($mysqli, "DELETE FROM users WHERE id=?");
            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);
            flash('success', 'User removed.');
        }
    }
    redirect('/admin/users.php');
}

$users = mysqli_fetch_all(mysqli_query($mysqli, "
    SELECT u.*, (SELECT COUNT(*) FROM notes n WHERE n.user_id=u.id) AS note_count
    FROM users u ORDER BY u.created_at DESC"), MYSQLI_ASSOC);

$pageTitle = 'Manage Users';
$active = 'admin-users';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-header"><div><div class="page-title">Manage Users</div></div></div>

<div class="panel" style="padding:0; overflow:hidden;">
  <table>
    <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Notes</th><th>Joined</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): ?>
      <tr>
        <td style="font-weight:600;"><?= h($u['name']) ?></td>
        <td style="color:var(--ink-dim);"><?= h($u['email']) ?></td>
        <td><span class="badge <?= $u['role']==='admin'?'admin':'' ?>"><?= $u['role'] ?></span></td>
        <td><?= $u['note_count'] ?></td>
        <td style="color:var(--ink-dim);"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
        <td style="text-align:right; white-space:nowrap;">
          <form method="POST" style="display:inline;">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="toggle-admin">
            <input type="hidden" name="id" value="<?= $u['id'] ?>">
            <button type="submit" style="background:none; border:none; color:var(--green-800); font-weight:600; font-size:12px; cursor:pointer;">
              <?= $u['role']==='admin' ? 'Revoke admin' : 'Make admin' ?>
            </button>
          </form>
          <?php if ($u['id'] !== $me['id']): ?>
          <form method="POST" style="display:inline; margin-left:10px;" onsubmit="return confirm('Delete this user?');">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $u['id'] ?>">
            <button type="submit" style="background:none; border:none; color:var(--rust); font-weight:600; font-size:12px; cursor:pointer;">Delete</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
