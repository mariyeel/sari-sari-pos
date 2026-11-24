<?php
$filter = choice($_GET['filter'] ?? null, ['low', 'all'], 'all');
$products = $db->query("SELECT p.*, c.name AS category FROM products p JOIN categories c ON c.id=p.category_id ORDER BY p.active DESC, p.name")->fetchAll();
$shown = $filter === 'low' ? array_filter($products, fn($product) => $product['active'] && $product['stock'] <= $product['low_stock']) : $products;
$movements = $db->query("SELECT m.*, p.name, p.unit, u.name AS staff FROM stock_movements m
    JOIN products p ON p.id=m.product_id JOIN users u ON u.id=m.user_id ORDER BY m.id DESC LIMIT 50")->fetchAll();
?>
<section class="page-heading"><div><p class="eyebrow">STOCK CONTROL</p><h1>Inventory</h1><p>Check quantities, low-stock alerts, and recent stock movements.</p></div></section>
<div class="<?= $owner ? 'management-grid' : 'single-grid' ?>">
    <section class="panel table-panel">
        <div class="panel-head"><div><h2>Stock levels</h2><p><?= e(count($shown)) ?> products shown</p></div><div class="heading-actions"><a class="filter-pill <?= $filter === 'all' ? 'active' : '' ?>" href="?page=inventory">All stock</a><a class="filter-pill <?= $filter === 'low' ? 'active' : '' ?>" href="?page=inventory&amp;filter=low">Low stock</a></div></div>
        <?php if ($shown) { ?>
        <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Product</th><th>On hand</th><th>Alert level</th><th>Status</th></tr></thead><tbody>
        <?php foreach ($shown as $item) {
            $status = !$item['active'] ? ['Archived', 'neutral'] : (!$item['stock'] ? ['Out of stock', 'out'] : ($item['stock'] <= $item['low_stock'] ? ['Low stock', ''] : ['In stock', 'in-stock']));
            ?><tr><td class="fw-semibold"><?= e($item['name']) ?><small class="d-block text-secondary"><?= e($item['sku']) ?> · <?= e($item['category']) ?></small></td><td><?= e($item['stock']) ?> <small class="text-secondary"><?= e($item['unit']) ?></small></td><td><?= e($item['low_stock']) ?></td><td><span class="stock-pill <?= e($status[1]) ?>"><?= e($status[0]) ?></span></td></tr>
        <?php } ?></tbody></table></div>
        <?php } else { emptyState($filter === 'low' ? 'No low-stock products' : 'No stock to display', '', 'layers'); } ?>
    </section>
    <?php if ($owner) { ?>
    <section class="panel">
        <div class="panel-head"><div><h2>Update stock</h2><p>Record a delivery or correct an inventory count.</p></div></div>
        <?php if ($products) { ?>
        <form method="post" class="panel-body form-stack" data-stock-form>
            <?= formAction('stock', true) ?>
            <label class="form-label">Product<select class="form-select" name="product_id" required><option value="">Choose a product</option><?php foreach ($products as $item) { ?><option value="<?= e($item['id']) ?>"><?= e($item['name'] . ' · ' . $item['sku']) ?></option><?php } ?></select></label>
            <div class="field-pair"><label class="form-label">Reason<select class="form-select" name="reason"><option value="replenishment">Restock</option><option value="adjustment">Adjustment</option></select></label><label class="form-label">Direction<select class="form-select" name="direction"><option value="add">Add stock</option><option value="remove" disabled>Remove stock</option></select></label></div>
            <label class="form-label">Quantity<input type="number" min="1" max="1000000" name="quantity" class="form-control" required></label>
            <label class="form-label">Note<textarea name="note" class="form-control" maxlength="255" rows="2" placeholder="Required when removing stock"></textarea></label>
            <button class="btn btn-primary"><?= icon('check2') ?> Update stock</button>
        </form>
        <?php } else { ?><div class="panel-body"><a class="table-link" href="?page=products">Add a product to manage stock</a></div><?php } ?>
    </section>
    <?php } ?>
    <section class="panel table-panel span-grid">
        <div class="panel-head"><div><h2>Recent stock movements</h2><p>Latest 50 stock changes</p></div></div>
        <?php if ($movements) { ?>
        <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Product</th><th>Change</th><th>Reason</th><th>Recorded by</th><th>Date &amp; time</th></tr></thead><tbody>
        <?php foreach ($movements as $item) { ?><tr><td class="fw-semibold"><?= e($item['name']) ?></td><td><span class="method <?= $item['quantity_change'] > 0 ? 'method-cash' : 'method-utang' ?>"><?= $item['quantity_change'] > 0 ? '+' : '' ?><?= e($item['quantity_change']) ?></span></td><td><?= e(ucfirst($item['reason'])) ?><?php if ($item['note']) { ?><small class="d-block text-secondary"><?= e($item['note']) ?></small><?php } ?></td><td><?= e($item['staff']) ?></td><td><?= e(webDate($item['created_at'])) ?></td></tr><?php } ?>
        </tbody></table></div>
        <?php } else { emptyState('No movements yet', 'Stock changes and sales will appear here.', 'clock-history'); } ?>
    </section>
</div>
