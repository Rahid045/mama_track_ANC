<?php
require __DIR__ . '/includes/bootstrap.php';
if (current_user()) redirect('dashboard.php');
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $s = db()->prepare('SELECT * FROM users WHERE email=? AND active=1');
    $s->execute([strtolower(post('email'))]);
    $u = $s->fetch();
    if ($u && $u['locked_until'] && $u['locked_until'] > now()) {
        $err = 'Account temporarily locked after too many failed attempts. Try again in a few minutes.';
    } elseif ($u && password_verify($_POST['password'] ?? '', $u['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['uid'] = (int)$u['id']; $_SESSION['last'] = time();
        db()->prepare('UPDATE users SET failed_logins=0, locked_until=NULL WHERE id=?')->execute([$u['id']]);
        audit('login', $u['email']);
        redirect('dashboard.php');
    } else {
        if ($u) {
            $f = $u['failed_logins'] + 1;
            db()->prepare('UPDATE users SET failed_logins=?, locked_until=? WHERE id=?')
                ->execute([$f, $f >= 5 ? date('Y-m-d H:i:s', time() + 600) : null, $u['id']]);
        }
        usleep(500000);
        $err = 'Incorrect email or password.';
    }
}
page_header('Sign in'); ?>
<div class="card narrow">
  <p class="muted">Web-based maternal health record, trend-based risk assessment and missed-appointment system for antenatal care.</p>
  <?php if ($err): ?><div class="flash err"><?= h($err) ?></div><?php endif; ?>
  <form method="post"><?= csrf_field() ?>
    <label>Email<input type="email" name="email" required autofocus></label>
    <label>Password<input type="password" name="password" required></label>
    <button class="btn">Sign in</button>
  </form>
</div>
<?php page_footer();
