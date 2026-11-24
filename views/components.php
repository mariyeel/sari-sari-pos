<?php
function icon(string $name): string
{
    return '<i class="bi bi-' . e($name) . '" aria-hidden="true"></i>';
}

function brand(): void
{ ?>
    <div class="brand">
        <span class="brand-icon"><?= icon('shop') ?></span>
        <span><strong>SariSari<span class="brand-dot">.</span></strong><small>STORE MANAGER</small></span>
    </div>
<?php }

function alertMessage(?array $message): void
{
    if ($message) { ?>
        <div class="alert alert-<?= e($message[0]) ?>" role="status"><?= e($message[1]) ?></div>
    <?php }
}

function emptyState(string $title, string $body = '', string $symbol = 'inbox'): void
{ ?>
    <div class="empty-state">
        <div class="empty-icon"><?= icon($symbol) ?></div>
        <strong><?= e($title) ?></strong>
        <?php if ($body !== '') { ?><p><?= e($body) ?></p><?php } ?>
    </div>
<?php }

function methodBadge(string $method): void
{ ?>
    <span class="method method-<?= e($method) ?>"><?= e(methodName($method)) ?></span>
<?php }

function transactionTable(array $sales): void
{
    if (!$sales) {
        emptyState('No transactions yet', 'Completed sales will appear here.', 'receipt');
        return;
    }
    ?>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th scope="col">Transaction</th><th scope="col">Date &amp; time</th><th scope="col">Cashier</th><th scope="col">Payment</th><th scope="col" class="text-end">Amount</th></tr></thead>
            <tbody>
            <?php foreach ($sales as $sale) { ?>
                <tr>
                    <td><a class="table-link" href="?page=receipt&amp;id=<?= e($sale['id']) ?>"><?= e(receiptNumber($sale['id'])) ?></a>
                        <?php if ($sale['customer']) { ?><small class="d-block text-secondary"><?= e($sale['customer']) ?></small><?php } ?>
                    </td>
                    <td><?= e(webDate($sale['created_at'])) ?></td>
                    <td><?= e($sale['cashier']) ?></td>
                    <td><?php methodBadge($sale['method']); ?></td>
                    <td class="text-end fw-semibold"><?= e(money($sale['total_cents'])) ?></td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
<?php }

function bars(array $series): void
{
    $peak = max(100, ...array_column($series, 1));
    $step = max(100, (int)(ceil($peak / 400) * 100));
    $max = $step * 4;
    ?>
    <div class="trend-chart">
        <div class="chart-scale" aria-hidden="true">
            <?php for ($i = 4; $i >= 0; $i--) { ?><span>₱<?= e(number_format($i * $step / 100)) ?></span><?php } ?>
        </div>
        <div class="chart-area">
            <div class="chart-grid" aria-hidden="true"><span></span><span></span><span></span><span></span><span></span></div>
            <div class="chart-bars">
                <?php foreach ($series as [$date, $amount]) { ?>
                    <div class="chart-column" title="<?= e($date->format('M j') . ' · ' . money($amount)) ?>" aria-label="<?= e($date->format('M j') . ' · ' . money($amount)) ?>">
                        <span class="chart-bar <?= $amount ? '' : 'is-zero' ?> <?= $date->format('Y-m-d') === date('Y-m-d') ? 'is-today' : '' ?>" style="--bar: <?= e((string)round($amount / $max * 100, 2)) ?>%"></span>
                        <small><?= e(count($series) <= 7 ? $date->format('D') : $date->format('j')) ?></small>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
<?php }
