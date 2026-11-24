<?php
$sales = saleRows($db, $user, true);
$pageNumber = max(1, min(50000, (int)($_GET['n'] ?? 1)));
$filters = ['page' => 'transactions', 'find' => is_string($_GET['find'] ?? null) ? $_GET['find'] : '',
    'method' => choice($_GET['method'] ?? null, ['cash', 'gcash', 'utang'], '')];
?>
<section class="page-heading"><div><p class="eyebrow">SALES RECORDS</p><h1>Transactions</h1><p>Find completed sales and open their receipts.</p></div><a class="btn btn-primary" href="?page=pos"><?= icon('plus-lg') ?> New sale</a></section>
<section class="panel table-panel">
    <form method="get" class="panel-head filter-bar">
        <input type="hidden" name="page" value="transactions">
        <label class="search-field"><?= icon('search') ?><input name="find" class="form-control" type="search" aria-label="Find receipt or customer" placeholder="Receipt number or customer" maxlength="100" value="<?= e($filters['find']) ?>"></label>
        <select class="form-select" name="method" aria-label="Payment method"><option value="">All payments</option><?php foreach (['cash','gcash','utang'] as $method) { ?><option value="<?= e($method) ?>" <?= $filters['method'] === $method ? 'selected' : '' ?>><?= e(methodName($method)) ?></option><?php } ?></select>
        <button class="btn btn-light">Filter</button>
    </form>
    <?php transactionTable(array_slice($sales, 0, 25)); ?>
    <?php if ($pageNumber > 1 || count($sales) > 25) { ?>
        <div class="panel-head pager">
            <?php if ($pageNumber > 1) { ?><a class="btn btn-light btn-sm" href="?<?= e(http_build_query($filters + ['n' => $pageNumber - 1])) ?>">Previous page</a><?php } ?>
            <span>Page <?= e($pageNumber) ?></span>
            <?php if (count($sales) > 25 && $pageNumber < 50000) { ?><a class="btn btn-light btn-sm" href="?<?= e(http_build_query($filters + ['n' => $pageNumber + 1])) ?>">Next page</a><?php } ?>
        </div>
    <?php } ?>
</section>
