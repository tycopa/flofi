<?php
if (!isset($pageTitle)) $pageTitle = APP_NAME;
[$THEME,$THEME_DK] = theme_colors($me['theme'] ?? 'orange');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?=h(APP_NAME)?> — <?=h($pageTitle)?></title>
<meta name="theme-color" content="<?=h($THEME)?>">
<style>
:root {
  --burnt: <?=h($THEME)?>;
  --burnt-dk: <?=h($THEME_DK)?>;
  --gray:#e6e6e6;
  --ink:#2a2a2a;
  --paper:#f7f7f7;
}
body {
  margin:0;
  font-family:"IBM Plex Mono",ui-monospace,Menlo,Consolas,monospace;
  background:var(--paper);
  color:var(--ink);
}
a {
  color:var(--burnt-dk);
  text-decoration:none;
  border-bottom:2px dotted var(--burnt-dk);
  font-weight:700
}
a:hover { color:var(--burnt) }
.btn {
  appearance:none;
  border:2px solid var(--burnt);
  background:var(--burnt);
  color:#fff;
  padding:12px 16px;
  border-radius:10px;
  font-weight:800;
  cursor:pointer
}
.btn.ghost { background:transparent; color:var(--burnt) }
.btn.sm { padding:6px 10px; font-size:12px; border-radius:6px }
.header {
  position:sticky;
  top:0; z-index:3;
  background:repeating-linear-gradient(45deg,var(--burnt),var(--burnt) 10px,var(--burnt-dk) 10px,var(--burnt-dk) 20px);
  color:#fff;
  padding:14px 18px;
  display:flex;
  align-items:center;
  justify-content:space-between;
  text-shadow:1px 1px 0 rgba(0,0,0,.25)
}
.brand { font-weight:900; letter-spacing:1px; font-size:20px }
.wrap { max-width:1100px; margin:0 auto; padding:0 16px }
.card {
  background:#fff;
  border:3px solid var(--gray);
  border-radius:12px;
  box-shadow:6px 6px 0 var(--gray);
  padding:18px;
  margin:16px 0
}
.small { font-size:12px; color:#666 }

/* Inputs & selects */
.field input,
.field select {
  width:100%;
  max-width:70%;
  padding:8px;
  border:2px solid var(--gray);
  border-radius:8px;
  font-family:inherit;
  font-size:12px;
  background:#fff;
  box-shadow:2px 2px 0 var(--gray);
  transition:border-color .2s, box-shadow .2s;
}
.field input:focus,
.field select:focus {
  border-color:var(--burnt);
  box-shadow:3px 3px 0 var(--burnt);
  outline:none;
}
.field label {
  display:block;
  margin-bottom:4px;
  font-size:12px;
  font-weight:700;
}

/* Tables */
.table {
  width:100%;
  border-collapse:collapse;
  font-size:13px;
}
.table th,.table td {
  border:1px solid var(--gray);
  padding:6px;
  text-align:left;
}
.table th {
  background:#fafafa;
  font-weight:800;
}

/* Category popup */
#addCatForm {
  background:#fff;
  border:2px solid var(--gray);
  border-radius:8px;
  padding:10px;
  box-shadow:4px 4px 0 var(--gray);
}
@media (max-width:700px) {
  #addCatForm {
    flex-direction:column;
    width:100%;
    gap:8px;
  }
  #addCatForm input[type="text"],
  #addCatForm button {
    width:100%;
  }
}

/* Hamburger */
.hamburger {
  display:none;
  font-size:24px;
  background:none;
  border:none;
  color:#fff;
  cursor:pointer
}
.mobile-menu {
  display:none;
  flex-direction:column;
  gap:6px;
  padding:10px;
  background:var(--burnt-dk)
}
.mobile-menu.show { display:flex }

@media (max-width:700px) {
  .nav-links { display:none }
  .hamburger { display:inline-block }
}
</style>
</head>
<body>
<header class="header">
  <div class="brand">◆ <?=h(APP_NAME)?> ◆</div>
  <nav class="nav-links">
    <?php if (!empty($me)): ?>
      <a href="dashboard.php" class="btn">📊 Dashboard</a>
      <a href="recap.php" class="btn">📈 Recap</a>
      <a href="categories.php" class="btn">📂 Categories</a>
      <a href="profile.php" class="btn">👤 Profile</a>
      <?php if (!empty($me['is_admin'])): ?><a href="admin.php" class="btn">⚙️ Admin</a><?php endif; ?>
      <form method="post" action="index.php" style="display:inline">
        <?=csrf_field()?><input type="hidden" name="action" value="logout">
        <button class="btn">🚪 Sign out</button>
      </form>
    <?php endif; ?>
  </nav>
  <?php if (!empty($me)): ?>
    <button class="hamburger" onclick="document.getElementById('mobileNav').classList.toggle('show')">☰</button>
  <?php endif; ?>
</header>

<nav id="mobileNav" class="mobile-menu">
  <?php if (!empty($me)): ?>
    <a href="dashboard.php" class="btn">📊 Dashboard</a>
    <a href="recap.php" class="btn">📈 Recap</a>
    <a href="categories.php" class="btn">📂 Categories</a>
    <a href="profile.php" class="btn">👤 Profile</a>
    <?php if (!empty($me['is_admin'])): ?><a href="admin.php" class="btn">⚙️ Admin</a><?php endif; ?>
    <form method="post" action="index.php" style="display:inline">
      <?=csrf_field()?><input type="hidden" name="action" value="logout">
      <button class="btn">🚪 Sign out</button>
    </form>
  <?php endif; ?>
</nav>
