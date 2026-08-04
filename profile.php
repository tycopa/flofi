<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
$me = require_login();

$flash = $_SESSION['flash'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['flash'], $_SESSION['error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    must_post_csrf();
    $act = $_POST['action'] ?? '';

    if ($act === 'update_profile') {
        $full_name = trim($_POST['full_name'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $phone     = trim($_POST['phone'] ?? '');
        $country   = $_POST['country'] ?? $me['country'];
        $theme     = $_POST['theme'] ?? 'orange';

        if ($full_name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error'] = 'Full name and a valid email are required.';
        } else {
            try {
                $upd = db()->prepare(
                    "UPDATE accounts SET full_name=:fn, email=:e, phone=:ph, country=:co, theme=:th WHERE id=:id"
                );
                $upd->execute([
                    ':fn' => $full_name,
                    ':e'  => $email,
                    ':ph' => $phone,
                    ':co' => $country,
                    ':th' => $theme,
                    ':id' => $me['id'],
                ]);
                $_SESSION['flash'] = 'Profile updated.';
            } catch (PDOException $e) {
                $_SESSION['error'] = 'Update failed. Email may already be in use.';
                error_log($e->getMessage());
            }
        }
        header('Location: profile.php'); exit;
    }

    if ($act === 'change_password') {
        $current  = $_POST['current_password'] ?? '';
        $new      = $_POST['new_password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        $st = db()->prepare("SELECT password_hash FROM accounts WHERE id=:id");
        $st->execute([':id' => $me['id']]);
        $hash = $st->fetchColumn();

        if (!password_verify($current, $hash)) {
            $_SESSION['error'] = 'Current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $_SESSION['error'] = 'New password must be at least 8 characters.';
        } elseif ($new !== $confirm) {
            $_SESSION['error'] = 'New passwords do not match.';
        } else {
            $upd = db()->prepare("UPDATE accounts SET password_hash=:h WHERE id=:id");
            $upd->execute([':h' => password_hash($new, PASSWORD_BCRYPT), ':id' => $me['id']]);
            $_SESSION['flash'] = 'Password changed successfully.';
        }
        header('Location: profile.php'); exit;
    }
}

$themes = ['orange', 'red', 'blue', 'green', 'purple', 'gray', 'black'];
$pageTitle = 'Profile';
include __DIR__ . '/header.php';
?>
<main class="wrap" style="padding:18px 0 36px">
<?php if ($flash): ?><div class="card" style="background:#eaffea;border-color:#8bd98b"><?= $flash ?></div><?php endif; ?>
<?php if ($error): ?><div class="card" style="background:#ffecec;border-color:#f28b82"><strong><?= h($error) ?></strong></div><?php endif; ?>

<div class="card">
  <h2>Profile</h2>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="action" value="update_profile">

    <div class="field"><label>Full Name</label>
      <input type="text" name="full_name" value="<?= h($me['full_name']) ?>" required>
    </div>
    <div class="field"><label>Email</label>
      <input type="email" name="email" value="<?= h($me['email']) ?>" required>
    </div>
    <div class="field">
      <label>Country / Phone Code</label>
      <select name="country">
        <?php foreach ($COUNTRIES as $code => $label): ?>
          <option value="<?= h($code) ?>"<?= $code === $me['country'] ? ' selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field"><label>Phone</label>
      <input type="tel" name="phone" value="<?= h($me['phone'] ?? '') ?>">
    </div>
    <div class="field">
      <label>Theme</label>
      <select name="theme">
        <?php foreach ($themes as $t): ?>
          <option value="<?= h($t) ?>"<?= ($me['theme'] ?? 'orange') === $t ? ' selected' : '' ?>>
            <?= h(ucfirst($t)) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <button class="btn">Save Profile</button>
  </form>
</div>

<div class="card">
  <h2>Change Password</h2>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="action" value="change_password">
    <div class="field"><label>Current Password</label>
      <input type="password" name="current_password" required autocomplete="current-password">
    </div>
    <div class="field"><label>New Password</label>
      <input type="password" name="new_password" required autocomplete="new-password" minlength="8">
    </div>
    <div class="field"><label>Confirm New Password</label>
      <input type="password" name="confirm_password" required autocomplete="new-password" minlength="8">
    </div>
    <button class="btn">Change Password</button>
  </form>
</div>
</main>
<?php include __DIR__ . '/footer.php'; ?>
