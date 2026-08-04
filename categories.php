<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
$me = require_login();
[$THEME, $THEME_DK] = theme_colors($me['theme'] ?? 'orange');

$flash = $_SESSION['flash'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['flash'], $_SESSION['error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    must_post_csrf();
    try {
        $act = $_POST['action'] ?? '';

        if ($act === 'add') {
            $type = $_POST['type'] ?? '';
            $name = trim($_POST['name'] ?? '');
            if (!in_array($type, ['revenue', 'expense'], true)) throw new RuntimeException('Invalid type.');
            if ($name === '') throw new RuntimeException('Category name required.');

            try {
                $q = db()->prepare("INSERT INTO categories (account_id, type, name) VALUES (:a, :t, :n)");
                $q->execute([':a' => $me['id'], ':t' => $type, ':n' => $name]);
                $_SESSION['flash'] = 'Category <b>' . h($name) . '</b> added to <b>' . ucfirst($type) . '</b>.';
            } catch (PDOException $e) {
                if ($e->getCode() === '23505') {
                    $_SESSION['error'] = '⚠️ Category <b>' . h($name) . '</b> already exists under <b>' . ucfirst($type) . '</b>.';
                } else {
                    $_SESSION['error'] = $e->getMessage();
                }
            }
            header('Location: categories.php'); exit;
        }

        if ($act === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            $q = db()->prepare("DELETE FROM categories WHERE id=:id AND account_id=:a");
            $q->execute([':id' => $id, ':a' => $me['id']]);
            $_SESSION['flash'] = 'Category deleted.';
            header('Location: categories.php'); exit;
        }

        throw new RuntimeException('Unknown action.');
    } catch (Throwable $e) {
        $_SESSION['error'] = $e->getMessage();
        header('Location: categories.php'); exit;
    }
}

$list = db()->prepare("SELECT * FROM categories WHERE account_id=:a ORDER BY type, name");
$list->execute([':a' => $me['id']]);
$cats = $list->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Categories';
include __DIR__ . '/header.php';
?>
<main class="wrap" style="padding:18px 0 36px">
<?php if ($flash): ?><div class="card" style="background:#eaffea;border-color:#8bd98b"><?= $flash ?></div><?php endif; ?>
<?php if ($error): ?><div class="card" style="background:#ffecec;border-color:#f28b82"><strong><?= $error ?></strong></div><?php endif; ?>

<div class="card">
  <h2>Add Category</h2>
  <form method="post" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
    <?= csrf_field() ?><input type="hidden" name="action" value="add">

    <div class="field" style="flex:1;max-width:200px">
      <label>Type</label>
      <select name="type" required>
        <option value="revenue">Revenue</option>
        <option value="expense">Expense</option>
      </select>
    </div>

    <div class="field" style="flex:1;max-width:200px">
      <label>Category Name</label>
      <input type="text" name="name" placeholder="Category Name" required>
    </div>

    <button class="btn" style="margin-left:10px">Add</button>
  </form>
</div>

<div class="card">
  <h2>Your Categories</h2>
  <table class="table">
    <thead><tr><th>Type</th><th>Name</th><th></th></tr></thead>
    <tbody>
    <?php if (!$cats): ?>
      <tr><td colspan="3">None yet.</td></tr>
    <?php endif; ?>
    <?php foreach ($cats as $c): ?>
      <tr>
        <td><?= h(ucfirst($c['type'])) ?></td>
        <td><?= h($c['name']) ?></td>
        <td>
          <form method="post" style="display:inline" onsubmit="return confirm('Delete this category?')">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $c['id'] ?>">
            <button class="btn ghost">🗑️</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
</main>
<?php include __DIR__ . '/footer.php'; ?>
