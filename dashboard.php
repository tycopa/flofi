<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
$me = require_login();

$flash = $_SESSION['flash'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['flash'], $_SESSION['error']);

/* ---- Quick-add transaction ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    must_post_csrf();
    $act = $_POST['action'] ?? '';

    if ($act === 'add_tx') {
        $type     = $_POST['type'] ?? '';
        $cat_id   = (int)($_POST['category_id'] ?? 0);
        $amount   = (int)round((float)($_POST['amount'] ?? 0) * 100);
        $note     = trim($_POST['note'] ?? '');
        $tx_date  = $_POST['tx_date'] ?? date('Y-m-d');

        if (!in_array($type, ['revenue', 'expense'], true) || $cat_id === 0 || $amount <= 0) {
            $_SESSION['error'] = 'Please fill in all required fields.';
        } else {
            $ins = db()->prepare(
                "INSERT INTO transactions (account_id, category_id, type, amount_cents, note, tx_date)
                 VALUES (:a, :c, :t, :am, :n, :d)"
            );
            $ins->execute([
                ':a'  => $me['id'],
                ':c'  => $cat_id,
                ':t'  => $type,
                ':am' => $amount,
                ':n'  => $note,
                ':d'  => $tx_date,
            ]);
            $_SESSION['flash'] = 'Transaction added.';
        }
        header('Location: dashboard.php');
        exit;
    }

    if ($act === 'delete_tx') {
        $id = (int)($_POST['tx_id'] ?? 0);
        $del = db()->prepare("DELETE FROM transactions WHERE id=:id AND account_id=:a");
        $del->execute([':id' => $id, ':a' => $me['id']]);
        $_SESSION['flash'] = 'Transaction deleted.';
        header('Location: dashboard.php');
        exit;
    }
}

/* ---- Load data ---- */
$month = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month)) $month = date('Y-m');

$cats_st = db()->prepare("SELECT * FROM categories WHERE account_id=:a ORDER BY type, name");
$cats_st->execute([':a' => $me['id']]);
$cats = $cats_st->fetchAll(PDO::FETCH_ASSOC);

$rev_cats  = array_filter($cats, fn($c) => $c['type'] === 'revenue');
$exp_cats  = array_filter($cats, fn($c) => $c['type'] === 'expense');

$tx_st = db()->prepare(
    "SELECT t.*, c.name AS cat_name, c.type AS cat_type
     FROM transactions t
     JOIN categories c ON c.id = t.category_id
     WHERE t.account_id = :a
       AND to_char(t.tx_date, 'YYYY-MM') = :m
     ORDER BY t.tx_date DESC, t.id DESC"
);
$tx_st->execute([':a' => $me['id'], ':m' => $month]);
$transactions = $tx_st->fetchAll(PDO::FETCH_ASSOC);

$total_revenue = 0;
$total_expense = 0;
foreach ($transactions as $tx) {
    if ($tx['cat_type'] === 'revenue') $total_revenue += $tx['amount_cents'];
    else $total_expense += $tx['amount_cents'];
}
$net = $total_revenue - $total_expense;

/* Prev/next month nav */
$dt    = new DateTime($month . '-01');
$prev  = (clone $dt)->modify('-1 month')->format('Y-m');
$next  = (clone $dt)->modify('+1 month')->format('Y-m');
$label = $dt->format('F Y');

$pageTitle = 'Dashboard';
include __DIR__ . '/header.php';
?>
<main class="wrap" style="padding:18px 0 36px">
<?php if ($flash): ?><div class="card" style="background:#eaffea;border-color:#8bd98b"><?= $flash ?></div><?php endif; ?>
<?php if ($error): ?><div class="card" style="background:#ffecec;border-color:#f28b82"><strong><?= h($error) ?></strong></div><?php endif; ?>

<!-- Summary bar -->
<div class="card" style="display:flex;flex-wrap:wrap;gap:16px;align-items:center">
  <div style="flex:1;min-width:120px">
    <div class="small">Revenue</div>
    <div style="font-size:20px;font-weight:800;color:#2e7d32"><?= dollars($total_revenue) ?></div>
  </div>
  <div style="flex:1;min-width:120px">
    <div class="small">Expenses</div>
    <div style="font-size:20px;font-weight:800;color:#c62828"><?= dollars($total_expense) ?></div>
  </div>
  <div style="flex:1;min-width:120px">
    <div class="small">Net</div>
    <div style="font-size:20px;font-weight:800;color:<?= $net >= 0 ? '#2e7d32' : '#c62828' ?>"><?= dollars($net) ?></div>
  </div>
  <div style="display:flex;align-items:center;gap:8px">
    <a href="?month=<?= h($prev) ?>" class="btn ghost sm">‹</a>
    <strong style="font-size:14px"><?= h($label) ?></strong>
    <a href="?month=<?= h($next) ?>" class="btn ghost sm">›</a>
  </div>
</div>

<!-- Quick-add -->
<div class="card">
  <h2 style="margin-top:0">Add Transaction</h2>
  <?php if (!$cats): ?>
    <p>No categories yet. <a href="categories.php">Add some categories</a> first.</p>
  <?php else: ?>
  <form method="post" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end">
    <?= csrf_field() ?><input type="hidden" name="action" value="add_tx">

    <div class="field" style="flex:1;min-width:120px;max-width:160px">
      <label>Type</label>
      <select name="type" id="tx-type" onchange="filterCats(this.value)" required>
        <option value="revenue">Revenue</option>
        <option value="expense">Expense</option>
      </select>
    </div>

    <div class="field" style="flex:1;min-width:160px;max-width:220px">
      <label>Category</label>
      <select name="category_id" id="cat-select" required>
        <optgroup label="Revenue" id="grp-revenue">
          <?php foreach ($rev_cats as $c): ?>
            <option value="<?= $c['id'] ?>"><?= h($c['name']) ?></option>
          <?php endforeach; ?>
        </optgroup>
        <optgroup label="Expense" id="grp-expense">
          <?php foreach ($exp_cats as $c): ?>
            <option value="<?= $c['id'] ?>"><?= h($c['name']) ?></option>
          <?php endforeach; ?>
        </optgroup>
      </select>
    </div>

    <div class="field" style="flex:1;min-width:120px;max-width:160px">
      <label>Amount ($)</label>
      <input type="number" name="amount" min="0.01" step="0.01" placeholder="0.00" required>
    </div>

    <div class="field" style="flex:1;min-width:120px;max-width:160px">
      <label>Date</label>
      <input type="date" name="tx_date" value="<?= date('Y-m-d') ?>" required>
    </div>

    <div class="field" style="flex:2;min-width:160px">
      <label>Note</label>
      <input type="text" name="note" placeholder="Optional note" maxlength="255">
    </div>

    <button class="btn">Add</button>
  </form>
  <?php endif; ?>
</div>

<!-- Transaction list -->
<div class="card">
  <h2 style="margin-top:0">Transactions — <?= h($label) ?></h2>
  <table class="table">
    <thead><tr><th>Date</th><th>Type</th><th>Category</th><th>Amount</th><th>Note</th><th></th></tr></thead>
    <tbody>
    <?php if (!$transactions): ?>
      <tr><td colspan="6">No transactions this month.</td></tr>
    <?php endif; ?>
    <?php foreach ($transactions as $tx): ?>
      <tr>
        <td><?= h($tx['tx_date']) ?></td>
        <td style="color:<?= $tx['cat_type'] === 'revenue' ? '#2e7d32' : '#c62828' ?>;font-weight:700">
          <?= h(ucfirst($tx['cat_type'])) ?>
        </td>
        <td><?= h($tx['cat_name']) ?></td>
        <td style="font-weight:700"><?= dollars((int)$tx['amount_cents']) ?></td>
        <td><?= h($tx['note']) ?></td>
        <td>
          <form method="post" style="display:inline" onsubmit="return confirm('Delete this transaction?')">
            <?= csrf_field() ?><input type="hidden" name="action" value="delete_tx">
            <input type="hidden" name="tx_id" value="<?= $tx['id'] ?>">
            <button class="btn ghost sm">🗑️</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
</main>
<?php include __DIR__ . '/footer.php'; ?>
<script>
function filterCats(type) {
  var sel = document.getElementById('cat-select');
  var grpR = document.getElementById('grp-revenue');
  var grpE = document.getElementById('grp-expense');
  if (type === 'revenue') {
    grpR.style.display = ''; grpE.style.display = 'none';
    if (grpR.options.length) sel.value = grpR.options[0].value;
  } else {
    grpE.style.display = ''; grpR.style.display = 'none';
    if (grpE.options.length) sel.value = grpE.options[0].value;
  }
}
filterCats('revenue');
</script>
