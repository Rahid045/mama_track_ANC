<?php
require __DIR__ . '/includes/bootstrap.php';
$u = require_login(true);
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $new = $_POST['new'] ?? '';
    if (!password_verify($_POST['current'] ?? '', $u['password_hash'])) $errors[] = 'Current password is incorrect.';
    if (strlen($new) < 8 || !preg_match('/[A-Za-z]/', $new) || !preg_match('/\d/', $new)) $errors[] = 'New password must be at least 8 characters with letters and numbers.';
    if ($new !== ($_POST['confirm'] ?? '')) $errors[] = 'New passwords do not match.';
    if (!$errors) {
        db()->prepare('UPDATE users SET password_hash=?, must_change=0 WHERE id=?')->execute([password_hash($new, PASSWORD_DEFAULT), $u['id']]);
        audit('password_changed');
        flash('Password updated.'); redirect('dashboard.php');
    }
}
page_header('Change password'); ?>
<div class="card narrow">
  <?php if ($u['must_change']): ?><div class="flash warn">You must set a new password before continuing.</div><?php endif; ?>
  <?php foreach ($errors as $e): ?><div class="flash err"><?= h($e) ?></div><?php endforeach; ?>
  <form method="post"><?= csrf_field() ?>
    <label>Current password<input type="password" name="current" required></label>
    <label>New password<input type="password" name="new" required minlength="8"></label>
    <label>Confirm new password<input type="password" name="confirm" required></label>
    <button class="btn">Update password</button>
  </form>
</div>
<?php page_footer();
