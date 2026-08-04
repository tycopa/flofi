<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
$me = require_login();

$year = (int)($_GET['year'] ?? date('Y'));

/* Monthly totals */
$monthly = db()->prepare(
    "SELECT to_char(tx_date,'MM') AS mon,
            SUM(CASE WHEN c.type='revenue' THEN t.amount_cents ELSE 0 END) AS revenue,
            SUM(CASE WHEN c.type='expense' THEN t.amount_cents ELSE 0 END) AS expense
     FROM transactions t
     JOIN categories c ON c.id = t.category_id
     WHERE t.account_id = :a AND EXTRACT(YEAR FROM t.tx_date) = :y
     GROUP BY mon ORDER BY mon"
);
$monthly->execute([':a' => $me['id'], ':y' => $year]);
$rows = $monthly->fetchAll(PDO::FETCH_ASSOC);

/* Index by month number */
$by_month = [];
foreach ($rows as $r) {
    $by_month[(int)$r['mon']] = $r;
}

/* Per-category yearly totals */
$cat_totals = db()->prepare(
    "SELECT c.name, c.type,
            SUM(t.amount_cents) AS total
     FROM transactions t
     JOIN categories c ON c.id = t.category_id
     WHERE t.account_id = :a AND EXTRACT(YEAR FROM t.tx_date) = :y
     GROUP BY c.name, c.type ORDER BY c.type, total DESC"
);
$cat_totals->execute([':a' => $me['id'], ':y' => $year]);
$cat_rows = $cat_totals->fetchAll(PDO::FETCH_ASSOC);

$month_names = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

$pageTitle = 'Recap ' . $year;
include __DIR__ . '/header.php';
?>
<main class="wrap" style="padding:18px 0 36px">

<div class="card" style="display:flex;align-items:center;gap:12px">
  <a href="?year=<?= $year - 1 ?>" class="btn ghost sm">‹ <?= $year - 1 ?></a>
  <h2 style="margin:0;flex:1;text-align:center"><?= $year ?> Year in Review</h2>
  <a href="?year=<?= $year + 1 ?>" class="btn ghost sm"><?= $year + 1 ?> ›</a>
</div>

<!-- Monthly table -->
<div class="card">
  <h2 style="margin-top:0">Monthly Breakdown</h2>
  <div style="overflow-x:auto">
  <table class="table">
    <thead>
      <tr>
        <th>Month</th>
        <th style="color:#2e7d32">Revenue</th>
        <th style="color:#c62828">Expenses</th>
        <th>Net</th>
      </tr>
    </thead>
    <tbody>
    <?php
    $yr_rev = 0; $yr_exp = 0;
    for ($m = 1; $m <= 12; $m++):
        $rev = (int)($by_month[$m]['revenue'] ?? 0);
        $exp = (int)($by_month[$m]['expense'] ?? 0);
        $net = $rev - $exp;
        $yr_rev += $rev; $yr_exp += $exp;
    ?>
      <tr>
        <td><a href="dashboard.php?month=<?= $year . '-' . str_pad((string)$m, 2, '0', STR_PAD_LEFT) ?>"><?= $month_names[$m-1] ?></a></td>
        <td style="color:#2e7d32;font-weight:700"><?= dollars($rev) ?></td>
        <td style="color:#c62828;font-weight:700"><?= dollars($exp) ?></td>
        <td style="font-weight:700;color:<?= $net >= 0 ? '#2e7d32' : '#c62828' ?>"><?= dollars($net) ?></td>
      </tr>
    <?php endfor; ?>
    </tbody>
    <tfoot>
      <tr style="font-weight:800;background:#fafafa">
        <td>Total</td>
        <td style="color:#2e7d32"><?= dollars($yr_rev) ?></td>
        <td style="color:#c62828"><?= dollars($yr_exp) ?></td>
        <td style="color:<?= ($yr_rev-$yr_exp) >= 0 ? '#2e7d32' : '#c62828' ?>"><?= dollars($yr_rev - $yr_exp) ?></td>
      </tr>
    </tfoot>
  </table>
  </div>
</div>

<!-- Category breakdown -->
<?php if ($cat_rows): ?>
<div class="card">
  <h2 style="margin-top:0">By Category</h2>
  <div style="overflow-x:auto">
  <table class="table">
    <thead><tr><th>Category</th><th>Type</th><th>Total</th></tr></thead>
    <tbody>
    <?php foreach ($cat_rows as $cr): ?>
      <tr>
        <td><?= h($cr['name']) ?></td>
        <td style="color:<?= $cr['type'] === 'revenue' ? '#2e7d32' : '#c62828' ?>;font-weight:700"><?= h(ucfirst($cr['type'])) ?></td>
        <td style="font-weight:700"><?= dollars((int)$cr['total']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<?php endif; ?>

</main>
<?php include __DIR__ . '/footer.php'; ?>
