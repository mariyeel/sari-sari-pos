<?php
$expenses = $db->query("SELECT e.*, u.name AS staff FROM expenses e JOIN users u ON u.id=e.user_id ORDER BY e.expense_date DESC, e.id DESC LIMIT 100")->fetchAll();
?>
<section class="page-heading"><div><p class="eyebrow">OPERATING COSTS</p><h1>Expenses</h1><p>Record store expenses for your net profit reports.</p></div><a href="?page=reports" class="btn btn-light"><?= icon('bar-chart-line') ?> Reports</a></section>
<div class="management-grid">
    <section class="panel table-panel">
        <div class="panel-head"><div><h2>Expense records</h2><p>Latest 100 entries</p></div></div>
        <?php if ($expenses) { ?>
        <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Description</th><th>Date</th><th>Recorded by</th><th class="text-end">Amount</th></tr></thead><tbody>
        <?php foreach ($expenses as $expense) { ?><tr><td class="fw-semibold"><?= e($expense['description']) ?><small class="d-block text-secondary"><?= e($expense['category']) ?></small></td><td><?= e(webDate($expense['expense_date'], false)) ?></td><td><?= e($expense['staff']) ?></td><td class="text-end"><?= e(money($expense['amount_cents'])) ?></td></tr><?php } ?>
        </tbody></table></div>
        <?php } else { emptyState('No expenses yet', 'Recorded expenses will appear here.', 'wallet2'); } ?>
    </section>
    <section class="panel">
        <div class="panel-head"><div><h2>Record expense</h2><p>Enter an operating cost such as delivery or utilities.</p></div></div>
        <form method="post" class="panel-body form-stack">
            <?= formAction('expense', true) ?>
            <label class="form-label">Description<input class="form-control" name="description" maxlength="140" required></label>
            <label class="form-label">Category<input class="form-control" name="category" maxlength="60" list="expense-categories" required></label>
            <datalist id="expense-categories"><option value="Delivery"><option value="Utilities"><option value="Store supplies"><option value="Rent"><option value="Other"></datalist>
            <div class="field-pair">
                <label class="form-label">Amount (₱)<input class="form-control" name="amount" inputmode="decimal" maxlength="10" required></label>
                <label class="form-label">Date<input class="form-control" type="date" name="expense_date" value="<?= e(date('Y-m-d')) ?>" required></label>
            </div>
            <button class="btn btn-primary"><?= icon('check2') ?> Save expense</button>
        </form>
    </section>
</div>
