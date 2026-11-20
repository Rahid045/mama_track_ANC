<?php
require __DIR__ . '/includes/bootstrap.php';
$u = require_role('admin');
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $do = post('do'); $uid = (int)post('id');
    if ($do === 'create') {
        $email = strtolower(post('email')); $pw = post('password');
        if (mb_strlen(post('name')) < 3) $errors[] = 'Name is required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required.';
        if (!in_array(post('role'), ['admin', 'doctor', 'nurse'], true)) $errors[] = 'Choose a role.';
        if (strlen($pw) < 8) $errors[] = 'Temporary password must be at least 8 characters.';
        if (!$errors) {
            try {
                db()->prepare('INSERT INTO users(name,email,phone,role,password_hash,must_change,created_at) VALUES(?,?,?,?,?,1,?)')
                    ->execute([post('name'), $email, normalize_phone(post('phone')), post('role'), password_hash($pw, PASSWORD_DEFAULT), now()]);
                audit('user_created', $email); flash('User created. They must change the temporary password at first login.'); redirect('users.php');
            } catch (PDOException $e) { $errors[] = 'That email is already registered.'; }
        }
    } elseif ($do === 'toggle' && $uid !== (int)$u['id']) {
        db()->prepare('UPDATE users SET active = 1 - active WHERE id=?')->execute([$uid]); audit('user_toggled', "#$uid"); redirect('users.php');
    } elseif ($do === 'reset' && strlen(post('password')) >= 8) {
        db()->prepare('UPDATE users SET password_hash=?, must_change=1, failed_logins=0, locked_until=NULL WHERE id=?')->execute([password_hash(post('password'), PASSWORD_DEFAULT), $uid]);
        audit('password_reset', "#$uid"); flash('Password reset. User must change it at next login.'); redirect('users.php');
    }
}
$rows = db()->query('SELECT * FROM users ORDER BY role, name')->fetchAll();
page_header('Staff users', 'users'); ?>
<?php foreach ($errors as $e): ?><div class="flash err"><?= h($e) ?></div><?php endforeach; ?>
<div class="card"><div class="tbl"><table><tr><th>Name</th><th>Email / phone</th><th>Role</th><th>Status</th><th></th></tr>
<?php foreach ($rows as $r): ?><tr><td><?= h($r['name']) ?></td><td><?= h($r['email']) ?><br><small><?= h($r['phone']) ?></small></td><td><?= h($r['role']) ?></td>
 <td><?= $r['active'] ? '<span class="badge s-attended">Active</span>' : '<span class="badge s-cancelled">Disabled</span>' ?></td>
 <td class="nowrap"><?php if ((int)$r['id'] !== (int)$u['id']): ?>
  <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="do" value="toggle"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn sm ghost"><?= $r['active'] ? 'Disable' : 'Enable' ?></button></form>
  <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="do" value="reset"><input type="hidden" name="id" value="<?= $r['id'] ?>"><input name="password" placeholder="new temp password" size="14" minlength="8" required><button class="btn sm ghost">Reset</button></form>
 <?php endif; ?></td></tr><?php endforeach; ?></table></div></div>
<form method="post" class="card form"><?= csrf_field() ?><input type="hidden" name="do" value="create"><h2>Add staff member</h2>
  <div class="cols"><label>Full name<input name="name" required></label><label>Email<input type="email" name="email" required></label>
  <label>Phone (for SMS alerts)<input name="phone"></label>
  <label>Role<select name="role"><option value="doctor">Doctor (receives alerts)</option><option value="nurse">Nurse / midwife</option><option value="admin">Administrator</option></select></label>
  <label>Temporary password<input name="password" minlength="8" required></label></div>
  <button class="btn">Create user</button></form>
<hr class="sp"><div class="card"><h2>Data backup</h2><p class="muted">Download a complete, consistent copy of the database (protects against loss of records). Also done automatically by <code>cron/run.php</code>.</p>
<a class="btn" href="backup.php">⬇ Download backup now</a></div>
<?php page_footer();
