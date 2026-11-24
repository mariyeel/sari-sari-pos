<?php
$today = new DateTimeImmutable('today');
$tomorrow = $today->modify('+1 day');
$low = $db->query("SELECT name, stock, unit, low_stock FROM products WHERE active=1 AND stock<=low_stock ORDER BY stock, name LIMIT 4")->fetchAll();
$lowCount = (int)$db->query("SELECT COUNT(*) FROM products WHERE active=1 AND stock<=low_stock")->fetchColumn();
$week = $today->modify('-6 days');
if ($owner) {
    $summary = metrics($db, $today, $tomorrow);
} else {
    $summary = query($db, "SELECT COUNT(*) AS transactions, COALESCE(SUM(total_cents),0) AS sales FROM sales WHERE user_id=? AND created_at>=? AND created_at<?", [$user['id'], $today->format('Y-m-d'), $tomorrow->format('Y-m-d')])->fetch();
}
$series = dailySeries($db, $week, $tomorrow, $owner ? null : (int)$user['id']);
$widgets = [
    [$owner ? 'Sales today' : 'My sales today', money($summary['sales']), 'Cash, GCash & utang', 'cash-stack', 'green'],
    ['Transactions today', number_format($summary['transactions']), 'Completed sales', 'receipt', 'blue'],
    $owner ? ['Net profit today', money($summary['net']), 'After product costs & expenses', 'graph-up-arrow', 'violet']
        : ['Active products', number_format($db->query("SELECT COUNT(*) FROM products WHERE active=1")->fetchColumn()), 'Available in the catalog', 'box-seam', 'violet'],
    ['Low-stock items', number_format($lowCount), 'At or below stock alert level', 'exclamation-triangle', 'amber'],
];
?>
<section class="page-heading">
    <div><p class="eyebrow">STORE OVERVIEW</p><h1>Dashboard</h1><p>Here’s what’s happening in your store today.</p></div>
    <a class="btn btn-primary" href="?page=pos"><?= icon('plus-lg') ?> New sale</a>
</section>
<section class="metric-grid" aria-label="Today at a glance">
    <?php foreach ($widgets as [$label, $value, $hint, $symbol, $tone]) { ?>
        <article class="metric-card">
            <div class="metric-icon tone-<?= e($tone) ?>"><?= icon($symbol) ?></div>
            <p><?= e($label) ?></p><strong><?= e($value) ?></strong><small><?= e($hint) ?></small>
        </article>
    <?php } ?>
</section>
<div class="dashboard-grid">
    <section class="panel">
        <div class="panel-head"><div><h2>Sales overview</h2><p><?= $owner ? 'Sales recorded each day' : 'Your sales recorded each day' ?></p></div><span class="period-chip"><?= icon('calendar3') ?> Last 7 days</span></div>
        <?php bars($series); ?>
    </section>
    <section class="panel">
        <div class="panel-head"><div><h2>Low stock</h2><p>Products that need attention</p></div><span class="stock-count"><?= e($lowCount) ?></span></div>
        <?php if (!$low) { emptyState('Stock looks good', 'Low-stock products will appear here.', 'box-seam'); } else { ?>
            <ul class="stock-list">
            <?php foreach ($low as $item) { ?>
                <li><span class="stock-item-icon"><?= icon('box-seam') ?></span><div><strong><?= e($item['name']) ?></strong><small>Alert at <?= e($item['low_stock']) ?></small></div><span class="stock-pill <?= $item['stock'] ? '' : 'out' ?>"><?= e($item['stock']) ?> left</span></li>
            <?php } ?>
            </ul>
        <?php } ?>
        <div class="panel-foot"><a href="?page=inventory&amp;filter=low">View inventory</a></div>
    </section>
</div>
<section class="panel table-panel">
    <div class="panel-head"><div><h2>Recent transactions</h2><p>Latest completed sales</p></div><a class="btn btn-light btn-sm" href="?page=transactions">View all</a></div>
    <?php transactionTable(saleRows($db, $user)); ?>
</section>
