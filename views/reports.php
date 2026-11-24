<?php
$period = choice($_GET['period'] ?? null, ['daily', 'weekly', 'monthly'], 'daily');
$current = new DateTimeImmutable('today');
$raw = is_string($_GET['date'] ?? null) ? $_GET['date'] : '';
$selected = preg_match('/^\d{4}-\d{2}-\d{2}$/D', $raw) ? DateTimeImmutable::createFromFormat('!Y-m-d', $raw) : false;
if (!$selected || $selected->format('Y-m-d') !== $raw || $selected->format('Y') < 1000 || $selected->format('Y') > 9998) {
    $selected = $current;
}
$start = match ($period) {
    'weekly' => $selected->modify('monday this week'),
    'monthly' => $selected->modify('first day of this month'),
    default => $selected,
};
$end = $start->modify(match ($period) { 'weekly' => '+7 days', 'monthly' => '+1 month', default => '+1 day' });
$summary = metrics($db, $start, $end);
$series = dailySeries($db, $start, $end);
$label = $period === 'daily' ? $start->format('D, M j, Y') : $start->format('M j') . ' – ' . $end->modify('-1 day')->format('M j, Y');
$cards = [
    ['Total sales', 'sales', 'Cash, GCash & utang', 'cash-stack', 'green'],
    ['Gross profit', 'gross', 'Sales less cost of goods sold', 'graph-up-arrow', 'blue'],
    ['Operating expenses', 'expenses', 'Expenses dated in this period', 'wallet2', 'amber'],
    ['Net profit', 'net', 'Gross profit less expenses', 'bar-chart-line', 'violet'],
];
?>
<section class="page-heading"><div><p class="eyebrow">STORE PERFORMANCE</p><h1>Sales &amp; profit reports</h1><p><?= e($label) ?> · <?= e(number_format($summary['transactions'])) ?> completed transactions</p></div><button class="btn btn-light" type="button" data-print><?= icon('printer') ?> Print report</button></section>
<section class="panel report-filters">
    <form method="get" class="panel-head filter-bar">
        <input type="hidden" name="page" value="reports">
        <label class="form-label">Period<select name="period" class="form-select"><?php foreach (['daily','weekly','monthly'] as $option) { ?><option value="<?= e($option) ?>" <?= $option === $period ? 'selected' : '' ?>><?= e(ucfirst($option)) ?></option><?php } ?></select></label>
        <label class="form-label">Date within period<input class="form-control" type="date" name="date" value="<?= e($selected->format('Y-m-d')) ?>" min="1000-01-01" max="9998-12-31" required></label>
        <button class="btn btn-primary">Run report</button>
    </form>
</section>
<section class="metric-grid report-metrics" aria-label="Report totals">
    <?php foreach ($cards as [$label, $key, $hint, $symbol, $tone]) { ?>
        <article class="metric-card"><div class="metric-icon tone-<?= e($tone) ?>"><?= icon($symbol) ?></div><p><?= e($label) ?></p><strong><?= e(money($summary[$key])) ?></strong><small><?= e($hint) ?></small></article>
    <?php } ?>
</section>
<div class="dashboard-grid">
    <section class="panel"><div class="panel-head"><div><h2>Daily sales</h2><p>Total sales by date</p></div></div><?php bars($series); ?></section>
    <section class="panel"><div class="panel-head"><div><h2>Sales &amp; collections</h2><p>Utang counts as sales on the day items are sold.</p></div></div>
        <dl class="totals-list">
            <div><dt>Cash sales</dt><dd><?= e(money($summary['cash'])) ?></dd></div>
            <div><dt>GCash sales</dt><dd><?= e(money($summary['gcash'])) ?></dd></div>
            <div><dt>Utang sales</dt><dd><?= e(money($summary['utang'])) ?></dd></div>
            <div><dt>Cost of goods sold</dt><dd><?= e(money($summary['cost'])) ?></dd></div>
            <div><dt>Utang collected separately</dt><dd><?= e(money($summary['collections'])) ?></dd></div>
        </dl>
    </section>
</div>
