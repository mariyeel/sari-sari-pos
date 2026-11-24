<?php
require_once __DIR__ . '/components.php';
$nav = [
    'Workspace' => ['dashboard' => ['Dashboard', 'grid'], 'pos' => ['Point of Sale', 'cart3'], 'transactions' => ['Transactions', 'receipt']],
    'Store management' => ['products' => ['Products & Categories', 'box-seam'], 'inventory' => ['Inventory', 'layers'], 'customers' => ['Customers & Utang', 'people'], 'expenses' => ['Expenses', 'wallet2'], 'reports' => ['Reports', 'bar-chart-line']],
    'Settings' => ['staff' => ['Staff Accounts', 'person-gear'], 'account' => ['My Account', 'person-circle']],
];
$titles = array_merge(...array_values($nav));
$title = $titles[$page][0] ?? ['receipt' => 'Sale receipt', 'missing' => 'Receipt unavailable', 'forbidden' => 'Owner access required'][$page] ?? 'SariSari';
$initials = mb_strtoupper(mb_substr($user['name'], 0, 1));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> · SariSari</title>
    <link rel="stylesheet" href="assets/bootstrap.min.css">
    <link rel="stylesheet" href="assets/icons.css">
    <link rel="stylesheet" href="assets/store.css">
    <script src="assets/store.js" defer></script>
</head>
<body>
<div class="workspace">
    <aside class="sidebar" id="sidebar" aria-label="Store navigation">
        <a href="index.php" class="brand-link" aria-label="SariSari dashboard"><?php brand(); ?></a>
        <nav id="store-nav">
            <?php foreach ($nav as $group => $items) { ?>
                <div class="nav-group">
                    <div class="nav-caption"><?= e($group) ?></div>
                    <?php foreach ($items as $target => [$label, $symbol]) {
                        if (!$owner && in_array($target, $adminSections, true)) { continue; }
                        $selected = $page === $target || ($target === 'transactions' && $page === 'receipt');
                        ?>
                        <a href="?page=<?= e($target) ?>" class="side-link <?= $selected ? 'selected' : '' ?>" <?= $selected ? 'aria-current="page"' : '' ?>>
                            <?= icon($symbol) ?><span><?= e($label) ?></span>
                        </a>
                    <?php } ?>
                </div>
            <?php } ?>
        </nav>
        <div class="side-footer">
            <div class="side-user"><span class="avatar"><?= e($initials) ?></span><div><strong><?= e($user['name']) ?></strong><small><?= $owner ? 'Store owner' : 'Cashier' ?></small></div></div>
            <form method="post" action="index.php"><?= formAction('logout') ?><button class="logout-button" title="Sign out" aria-label="Sign out"><?= icon('box-arrow-right') ?></button></form>
        </div>
    </aside>
    <button class="mobile-shade" type="button" aria-label="Close menu" hidden></button>
    <div class="main-shell">
        <header class="topbar">
            <div class="topbar-location">
                <button type="button" class="menu-button" aria-controls="sidebar" aria-expanded="false" aria-label="Open menu"><?= icon('list') ?></button>
                <span class="top-icon"><?= icon('shop-window') ?></span><span class="top-store">My Store</span><span class="top-divider">/</span><strong><?= e($title) ?></strong>
            </div>
            <div class="topbar-tools">
                <span class="top-date"><?= icon('calendar3') ?> <?= e(date('D, M j, Y')) ?></span>
                <a class="avatar top-avatar" href="?page=account" aria-label="My account"><?= e($initials) ?></a>
            </div>
        </header>
        <main id="content" class="main-content">
            <?php alertMessage($message); ?>
            <?php if (in_array($page, ['missing', 'forbidden'], true)) { ?>
                <section class="panel"><?php emptyState($title, 'Return to the dashboard to continue.', 'shield-lock'); ?><div class="text-center pb-4"><a class="btn btn-light" href="index.php">Dashboard</a></div></section>
            <?php } else { require __DIR__ . '/' . $page . '.php'; } ?>
        </main>
        <footer class="main-footer"><span>SariSari Store Manager</span><span>Philippine peso · Asia/Manila</span></footer>
    </div>
</div>
</body>
</html>
