<?php
$customers = $db->query("SELECT c.*, COALESCE((SELECT SUM(d.balance_cents) FROM sales s JOIN debts d ON d.sale_id=s.id WHERE s.customer_id=c.id),0) AS balance
    FROM customers c ORDER BY c.name, c.id")->fetchAll();
$customerId = is_string($_GET['id'] ?? null) && ctype_digit($_GET['id']) ? (int)$_GET['id'] : 0;
$customer = $customerId ? query($db, "SELECT * FROM customers WHERE id=?", [$customerId])->fetch() : false;
$debts = $customer ? query($db, "SELECT d.*, s.created_at FROM debts d JOIN sales s ON s.id=d.sale_id
    WHERE s.customer_id=? ORDER BY (d.balance_cents>0) DESC, s.id DESC", [$customerId])->fetchAll() : [];
$payments = $customer ? query($db, "SELECT dp.*, d.sale_id, u.name AS staff FROM debt_payments dp JOIN debts d ON d.id=dp.debt_id
    JOIN sales s ON s.id=d.sale_id JOIN users u ON u.id=dp.user_id WHERE s.customer_id=? ORDER BY dp.id DESC LIMIT 50", [$customerId])->fetchAll() : [];
?>
<section class="page-heading"><div><p class="eyebrow">CUSTOMER LEDGER</p><h1>Customers &amp; utang</h1><p>Track outstanding balances and record partial or full payments.</p></div><?php if ($customer) { ?><a href="?page=customers" class="btn btn-light"><?= icon('person-plus') ?> Add customer</a><?php } ?></section>
<div class="management-grid">
    <section class="panel table-panel">
        <div class="panel-head"><div><h2>Customers</h2><p><?= e(count($customers)) ?> customer records</p></div></div>
        <?php if ($customers) { ?>
        <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Customer</th><th>Outstanding</th><th class="text-end">Ledger</th></tr></thead><tbody>
        <?php foreach ($customers as $item) { ?><tr><td class="fw-semibold"><?= e($item['name']) ?><?php if ($item['phone']) { ?><small class="d-block text-secondary"><?= e($item['phone']) ?></small><?php } ?></td><td><?= e(money($item['balance'])) ?></td><td class="text-end"><a class="btn btn-light btn-sm" href="?page=customers&amp;id=<?= e($item['id']) ?>#ledger">View ledger</a></td></tr><?php } ?>
        </tbody></table></div>
        <?php } else { emptyState('No customers yet', 'Add a customer before recording an utang sale.', 'people'); } ?>
    </section>
    <section class="panel">
        <div class="panel-head"><div><h2><?= $customer ? 'Edit customer' : 'Add customer' ?></h2><p>Name is required. Contact details are optional.</p></div></div>
        <form method="post" class="panel-body form-stack">
            <?= formAction('customer') ?><input type="hidden" name="id" value="<?= e($customer['id'] ?? 0) ?>">
            <label class="form-label">Full name<input class="form-control" name="name" maxlength="100" value="<?= e($customer['name'] ?? '') ?>" required></label>
            <label class="form-label">Phone<input class="form-control" type="tel" name="phone" maxlength="35" value="<?= e($customer['phone'] ?? '') ?>"></label>
            <label class="form-label">Notes<textarea class="form-control" name="notes" maxlength="255" rows="2"><?= e($customer['notes'] ?? '') ?></textarea></label>
            <button class="btn btn-primary"><?= icon('check2') ?> Save customer</button>
        </form>
    </section>
    <?php if ($customer) { ?>
        <section class="panel table-panel" id="ledger">
            <div class="panel-head"><div><h2><?= e($customer['name']) ?>’s utang</h2><p>Open and settled credit sales</p></div><strong><?= e(money(array_sum(array_column($debts, 'balance_cents')))) ?> due</strong></div>
            <?php if ($debts) { ?>
            <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Sale</th><th>Original</th><th>Balance</th></tr></thead><tbody>
            <?php foreach ($debts as $debt) { ?><tr><td class="fw-semibold"><?= e(receiptNumber($debt['sale_id'])) ?><small class="d-block text-secondary"><?= e(webDate($debt['created_at'], false)) ?></small></td><td><?= e(money($debt['original_cents'])) ?></td><td><?= $debt['balance_cents'] ? e(money($debt['balance_cents'])) : '<span class="method method-cash">Paid</span>' ?></td></tr><?php } ?>
            </tbody></table></div>
            <?php } else { emptyState('No utang sales yet', '', 'journal-text'); } ?>
        </section>
        <section class="panel">
            <div class="panel-head"><div><h2>Record payment</h2><p>Apply a payment to one open utang sale.</p></div></div>
            <?php $openDebts = array_filter($debts, fn($debt) => $debt['balance_cents'] > 0); ?>
            <?php if ($openDebts) { ?>
            <form method="post" class="panel-body form-stack" data-debt-form>
                <?= formAction('debt_payment', true) ?>
                <label class="form-label">Utang sale<select class="form-select" name="debt_id" required><option value="">Choose a sale</option><?php foreach ($openDebts as $debt) { ?><option value="<?= e($debt['id']) ?>"><?= e(receiptNumber($debt['sale_id']) . ' · ' . money($debt['balance_cents']) . ' due') ?></option><?php } ?></select></label>
                <div class="field-pair">
                    <label class="form-label">Payment amount (₱)<input class="form-control" name="amount" inputmode="decimal" maxlength="15" required></label>
                    <label class="form-label">Method<select class="form-select" name="method"><option value="cash">Cash</option><option value="gcash">GCash</option></select></label>
                </div>
                <label class="form-label" data-debt-reference hidden>GCash reference<input class="form-control" name="reference" maxlength="80" disabled></label>
                <button class="btn btn-primary"><?= icon('check2') ?> Record payment</button>
            </form>
            <?php } else { emptyState('No outstanding balance', '', 'check2-circle'); } ?>
        </section>
        <section class="panel table-panel span-grid">
            <div class="panel-head"><div><h2>Payment history</h2><p>Latest 50 utang payments for this customer</p></div></div>
            <?php if ($payments) { ?>
            <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Sale</th><th>Date &amp; time</th><th>Method</th><th>Recorded by</th><th class="text-end">Amount</th></tr></thead><tbody>
            <?php foreach ($payments as $payment) { ?><tr><td><?= e(receiptNumber($payment['sale_id'])) ?></td><td><?= e(webDate($payment['created_at'])) ?></td><td><?php methodBadge($payment['method']); ?><?php if ($payment['reference'] !== '') { ?><small class="d-block text-secondary"><?= e($payment['reference']) ?></small><?php } ?></td><td><?= e($payment['staff']) ?></td><td class="text-end fw-semibold"><?= e(money($payment['amount_cents'])) ?></td></tr><?php } ?>
            </tbody></table></div>
            <?php } else { emptyState('No payments recorded', '', 'receipt'); } ?>
        </section>
    <?php } ?>
</div>
