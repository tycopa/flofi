<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/mailer.php';

$me    = current_user();
$force = !empty($_GET['force']) && $me;   // forced reset after login

$flash = $_SESSION['flash'] ?? null;
$error = '';
unset($_SESSION['flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    must_post_csrf();
    $act = $_POST['action'] ?? '';

    /* ---- Step 1: request reset link ---- */
    if ($act === 'request') {
        $email = trim($_POST['email'] ?? '');
        $st    = db()->prepare("SELECT id, full_name FROM accounts WHERE email=:e LIMIT 1");
        $st->execute([':e' => $email]);
        $u = $st->fetch(PDO::FETCH_ASSOC);
        if ($u) {
            $token = bin2hex(random_bytes(32));
            $exp   = date('Y-m-d H:i:s', strtotime('+1 hour'));
            db()->prepare("DELETE FROM password_resets WHERE account_id=:id")->execute([':id' => $u['id']]);
            db()->prepare("INSERT INTO password_resets (account_id, token, expires_at) VALUES (:id,:t,:e)")
               ->execute([':id' => $u['id'], ':t' => $token, ':e' => $exp]);

            $link = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']
                  . dirname($_SERVER['PHP_SELF']) . '/reset.php?token=' . urlencode($token);

            send_mail($email, $u['full_name'], APP_NAME . ' — Password Reset',
                '<p>Hi ' . h($u['full_name']) . ',</p>'
                . '<p>Click below to reset your password (link expires in 1 hour):</p>'
                . '<p><a href="' . h($link) . '">' . h($link) . '</a></p>'
            );
        }
        /* Always show success to prevent user enumeration */
        $_SESSION['flash'] = 'If that email is in our system, a reset link has been sent.';
        header('Location: reset.php'); exit;
    }

    /* ---- Step 2: set new password via token ---- */
    if ($act === 'do_reset' || $act === 'force_reset') {
        $uid      = (int)($_POST['uid'] ?? ($me['id'] ?? 0));
        $token    = $_POST['token'] ?? '';
        $new      = $_POST['new_password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        if (strlen($new) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($new !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            $valid = false;
            if ($act === 'force_reset' && $me) {
                $valid = true; $uid = (int)$me['id'];
            } else {
                $st = db()->prepare(
                    "SELECT account_id FROM password_resets WHERE token=:t AND expires_at > NOW() LIMIT 1"
                );
                $st->execute([':t' => $token]);
                $row = $st->fetch(PDO::FETCH_ASSOC);
                if ($row) { $valid = true; $uid = (int)$row['account_id']; }
            }

            if ($valid) {
                db()->prepare("UPDATE accounts SET password_hash=:h, must_reset_password=false WHERE id=:id")
                   ->execute([':h' => password_hash($new, PASSWORD_BCRYPT), ':id' => $uid]);
                db()->prepare("DELETE FROM password_resets WHERE account_id=:id")->execute([':id' => $uid]);
                $_SESSION['flash'] = 'Password updated. Please sign in.';
                session_destroy();
                header('Location: index.php'); exit;
            } else {
                $error = 'Invalid or expired reset link.';
            }
        }
    }
}

$token_param = $_GET['token'] ?? '';

$me_nav = null;
$pageTitle = 'Reset Password';
include __DIR__ . '/header.php';
?>
<main class="wrap" style="max-width:500px">
<?php if ($flash): ?><div class="card" style="background:#eaffea;border-color:#8bd98b"><?= $flash ?></div><?php endif; ?>
<?php if ($error): ?><div class="card" style="background:#ffecec;border-color:#f28b82"><strong><?= h($error) ?></strong></div><?php endif; ?>

<div class="card">
<?php if ($force): ?>
  <h2>You must set a new password</h2>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="action" value="force_reset">
    <div class="field"><label>New Password</label><input type="password" name="new_password" required minlength="8" autocomplete="new-password"></div>
    <div class="field"><label>Confirm Password</label><input type="password" name="confirm_password" required minlength="8" autocomplete="new-password"></div>
    <button class="btn">Set Password</button>
  </form>

<?php elseif ($token_param): ?>
  <h2>Set New Password</h2>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="do_reset">
    <input type="hidden" name="token" value="<?= h($token_param) ?>">
    <div class="field"><label>New Password</label><input type="password" name="new_password" required minlength="8" autocomplete="new-password"></div>
    <div class="field"><label>Confirm Password</label><input type="password" name="confirm_password" required minlength="8" autocomplete="new-password"></div>
    <button class="btn">Set Password</button>
  </form>

<?php else: ?>
  <h2>Forgot Password</h2>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="action" value="request">
    <div class="field"><label>Your Email Address</label>
      <input type="email" name="email" required autocomplete="email">
    </div>
    <button class="btn">Send Reset Link</button>
  </form>
  <p class="small" style="margin-top:12px"><a href="index.php">Back to sign in</a></p>
<?php endif; ?>
</div>
</main>
<?php include __DIR__ . '/footer.php'; ?>
