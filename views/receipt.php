<section class="page-heading"><div><p class="eyebrow">TRANSACTION DETAILS</p><h1>Sale receipt</h1><p><?= e(receiptNumber($receipt['id'])) ?> · <?= e(webDate($receipt['created_at'])) ?></p></div><div class="heading-actions"><button type="button" class="btn btn-light" data-print><?= icon('printer') ?> Print</button><a href="?page=pos" class="btn btn-primary"><?= icon('plus-lg') ?> New sale</a></div></section>
<section class="panel receipt-panel" <?= ($message[1] ?? '') === 'Sale completed.' ? 'data-sale-completed="' . e($user['id']) . '"' : '' ?>>
    <div class="panel-head"><div><h2><?= e(receiptNumber($receipt['id'])) ?></h2><p>Cashier · <?= e($receipt['cashier']) ?></p></div><?php methodBadge($receipt['method']); ?></div>
    <?php if ($receipt['customer']) { ?><p class="px-4 mb-2">Customer · <strong><?= e($receipt['customer']) ?></strong></p><?php } ?>
    <?php if ($receipt['reference'] !== '') { ?><p class="px-4 mb-2">GCash reference · <strong><?= e($receipt['reference']) ?></strong></p><?php } ?>
    <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Item</th><th>Qty</th><th>Unit price</th><th class="text-end">Amount</th></tr></thead><tbody>
    <?php foreach ($receipt['items'] as $item) { ?><tr><td><?= e($item['product_name']) ?><small class="d-block text-secondary"><?= e($item['unit']) ?></small></td><td><?= e($item['quantity']) ?></td><td><?= e(money($item['price_cents'])) ?></td><td class="text-end"><?= e(money($item['price_cents'] * $item['quantity'])) ?></td></tr><?php } ?>
    </tbody></table></div>
    <div class="receipt-totals">
        <div><span>Total</span><strong><?= e(money($receipt['total_cents'])) ?></strong></div>
        <?php if ($receipt['method'] === 'cash') { ?>
            <div><span>Cash received</span><span><?= e(money($receipt['tendered_cents'])) ?></span></div>
            <div><span>Change</span><span><?= e(money($receipt['change_cents'])) ?></span></div>
        <?php } ?>
    </div>
</section>
