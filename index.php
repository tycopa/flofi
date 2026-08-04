<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/mailer.php';

/* Already logged in → go to app */
if (current_user()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    must_post_csrf();
    $act = $_POST['action'] ?? '';

    /* ---- Login ---- */
    if ($act === 'login') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = !empty($_POST['remember']);

        $st = db()->prepare(
            "SELECT id, username, full_name, password_hash, is_disabled, must_reset_password, theme
             FROM accounts WHERE username=:u LIMIT 1"
        );
        $st->execute([':u' => $username]);
        $user = $st->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            if ($user['is_disabled']) {
                $error = 'This account has been disabled.';
            } else {
                $_SESSION['uid'] = (int)$user['id'];

                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    $exp   = date('Y-m-d H:i:s', strtotime('+30 days'));
                    $ins   = db()->prepare(
                        "INSERT INTO remember_tokens (account_id, token, expires_at) VALUES (:a, :t, :e)"
                    );
                    $ins->execute([':a' => $user['id'], ':t' => $token, ':e' => $exp]);
                    setcookie('remember_me', $token, strtotime('+30 days'), '/', '', !empty($_SERVER['HTTPS']), true);
                }

                if ($user['must_reset_password']) {
                    header('Location: reset.php?force=1');
                } else {
                    header('Location: dashboard.php');
                }
                exit;
            }
        } else {
            $error = 'Invalid username or password.';
        }
    }

    /* ---- Signup ---- */
    if ($act === 'signup') {
        $username  = trim($_POST['username'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $phone     = trim($_POST['phone'] ?? '');
        $country   = $_POST['country'] ?? 'US';
        $password  = $_POST['password'] ?? '';
        $password2 = $_POST['password2'] ?? '';

        if ($username === '' || $full_name === '' || $email === '' || $password === '') {
            $error = 'All fields are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($password !== $password2) {
            $error = 'Passwords do not match.';
        } else {
            try {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $ins  = db()->prepare(
                    "INSERT INTO accounts (username, full_name, email, phone, country, password_hash)
                     VALUES (:u, :fn, :e, :ph, :co, :pw) RETURNING id"
                );
                $ins->execute([
                    ':u'  => $username,
                    ':fn' => $full_name,
                    ':e'  => $email,
                    ':ph' => $phone,
                    ':co' => $country,
                    ':pw' => $hash,
                ]);
                $uid = (int)$ins->fetchColumn();
                $_SESSION['uid'] = $uid;
                $_SESSION['flash'] = 'Welcome to ' . APP_NAME . ', ' . h($full_name) . '!';
                header('Location: dashboard.php');
                exit;
            } catch (PDOException $e) {
                if ($e->getCode() === '23505') {
                    $error = 'That username or email is already taken.';
                } else {
                    $error = 'Registration error. Please try again.';
                    error_log($e->getMessage());
                }
            }
        }
    }

    /* ---- Logout ---- */
    if ($act === 'logout') {
        if (!empty($_COOKIE['remember_me'])) {
            $del = db()->prepare("DELETE FROM remember_tokens WHERE token=:t");
            $del->execute([':t' => $_COOKIE['remember_me']]);
            setcookie('remember_me', '', time() - 3600, '/');
        }
        session_destroy();
        header('Location: index.php');
        exit;
    }
}

$me = null;
$pageTitle = 'Welcome';
include __DIR__ . '/header.php';
?>
<main class="wrap" style="max-width:600px">
<?php if ($flash): ?>
  <div class="card" style="background:#eaffea;border-color:#8bd98b"><?= $flash ?></div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="card" style="background:#ffecec;border-color:#f28b82"><strong><?= h($error) ?></strong></div>
<?php endif; ?>

<div class="card landing" style="text-align:center;padding:32px 18px">
  <div style="font-size:48px;margin-bottom:8px">💰</div>
  <h1 style="margin:0 0 8px;font-size:28px;color:var(--burnt)">FloFi</h1>
  <p style="margin:0;color:#555;font-size:14px">Your personal budget tracker. Track income &amp; expenses, set categories, and see where your money goes.</p>
</div>

<div class="card">
  <div class="auth-tabs" style="display:flex;gap:8px;margin-bottom:16px">
    <button class="btn active" id="tab-login" onclick="showTab('login')">Sign In</button>
    <button class="btn ghost" id="tab-signup" onclick="showTab('signup')">Create Account</button>
  </div>

  <!-- Login -->
  <form method="post" id="form-login" class="auth-form">
    <?= csrf_field() ?><input type="hidden" name="action" value="login">
    <div class="field"><label>Username</label><input type="text" name="username" required autocomplete="username"></div>
    <div class="field"><label>Password</label><input type="password" name="password" required autocomplete="current-password"></div>
    <label style="font-size:12px;display:flex;align-items:center;gap:6px;margin:8px 0">
      <input type="checkbox" name="remember"> Remember me for 30 days
    </label>
    <button class="btn" style="margin-top:8px">Sign In</button>
  </form>

  <!-- Signup -->
  <form method="post" id="form-signup" class="auth-form" style="display:none">
    <?= csrf_field() ?><input type="hidden" name="action" value="signup">
    <div class="field"><label>Full Name</label><input type="text" name="full_name" required></div>
    <div class="field"><label>Username</label><input type="text" name="username" required autocomplete="username"></div>
    <div class="field"><label>Email</label><input type="email" name="email" required></div>
    <div class="field">
      <label>Country / Phone Code</label>
      <select name="country">
        <?php foreach ($COUNTRIES as $code => $label): ?>
          <option value="<?= h($code) ?>"<?= $code === 'US' ? ' selected' : '' ?>><?= h($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field"><label>Phone (optional)</label><input type="tel" name="phone"></div>
    <div class="field"><label>Password</label><input type="password" name="password" required autocomplete="new-password" minlength="8"></div>
    <div class="field"><label>Confirm Password</label><input type="password" name="password2" required autocomplete="new-password" minlength="8"></div>
    <button class="btn" style="margin-top:8px">Create Account</button>
  </form>
</div>
</main>
<?php include __DIR__ . '/footer.php'; ?>
<script>
function showTab(id) {
  document.querySelectorAll('.auth-form').forEach(f => f.style.display = 'none');
  document.getElementById('form-' + id).style.display = '';
  document.getElementById('tab-login').className  = 'btn' + (id === 'login'  ? ' active' : ' ghost');
  document.getElementById('tab-signup').className = 'btn' + (id === 'signup' ? ' active' : ' ghost');
}
</script>
