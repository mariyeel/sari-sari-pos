<?php
$products = $db->query("SELECT p.id, p.sku, p.name, p.unit, p.price_cents, p.stock, p.low_stock, c.id AS category_id, c.name AS category
    FROM products p JOIN categories c ON c.id=p.category_id WHERE p.active=1 ORDER BY c.name, p.name")->fetchAll();
$categories = array_column($products, 'category', 'category_id');
$customers = $db->query("SELECT id, name, phone FROM customers ORDER BY name, id")->fetchAll();
?>
<section class="page-heading"><div><p class="eyebrow">CHECKOUT</p><h1>Point of sale</h1></div><div class="heading-actions"><a class="btn btn-light" href="?page=transactions"><?= icon('clock-history') ?> Transactions</a><a class="btn btn-primary mobile-cart-link" href="#current-sale"><?= icon('cart3') ?> View cart <span data-cart-jump-count>0</span></a></div></section>
<div class="pos-grid" data-pos data-user="<?= e($user['id']) ?>">
    <section class="panel catalog">
        <div class="panel-head"><h2 id="pos-products-heading">Products</h2></div>
        <div class="panel-body catalog-body">
            <div class="product-search-tools">
                <label class="search-field"><?= icon('search') ?><input type="search" class="form-control" placeholder="Search product or SKU" aria-label="Search product or SKU" data-catalog-search></label>
                <?php if ($products) { ?><button type="button" class="btn btn-light" aria-haspopup="dialog" aria-controls="barcode-scanner" data-open-barcode-scanner="cart" hidden>Scan barcode</button><?php } ?>
            </div>
            <?php if ($products) { ?>
                <p class="text-secondary small" role="status" data-cart-scan-status></p>
                <div class="catalog-filters" aria-label="Product categories"><button class="filter-pill active" type="button" data-category="" aria-pressed="true">All products</button>
                <?php foreach ($categories as $id => $category) { ?><button class="filter-pill" type="button" data-category="<?= e($id) ?>" aria-pressed="false"><?= e($category) ?></button><?php } ?>
                </div>
                <div class="catalog-product-scroll" role="region" aria-labelledby="pos-products-heading" tabindex="0">
                    <div class="product-grid">
                    <?php foreach ($products as $product) { ?>
                        <button class="product-tile" type="button" data-add="<?= e($product['id']) ?>" data-group="<?= e($product['category_id']) ?>"
                            data-name="<?= e($product['name']) ?>" data-sku="<?= e($product['sku']) ?>" data-unit="<?= e($product['unit']) ?>"
                            data-price="<?= e($product['price_cents']) ?>" data-stock="<?= e($product['stock']) ?>" <?= $product['stock'] ? '' : 'disabled' ?>>
                            <span class="product-top"><span class="product-icon"><?= icon('box-seam') ?></span><span class="stock-pill <?= $product['stock'] ? ($product['stock'] <= $product['low_stock'] ? '' : 'in-stock') : 'out' ?>"><?= $product['stock'] ? e($product['stock']) . ' left' : 'Out of stock' ?></span></span>
                            <span class="product-name"><?= e($product['name']) ?></span><small><?= e($product['unit']) ?> · <?= e($product['sku']) ?></small><strong><?= e(money($product['price_cents'])) ?></strong>
                        </button>
                    <?php } ?>
                    </div>
                    <div data-catalog-empty hidden><?php emptyState('No matching products', 'Try a different search or category.', 'search'); ?></div>
                </div>
            <?php } else {
                emptyState('No products available', $owner ? 'Add a category and product to start selling.' : 'Ask the store owner to add products.', 'box-seam');
                if ($owner) { ?><div class="text-center pb-3"><a class="btn btn-light" href="?page=products">Manage products</a></div><?php }
            } ?>
        </div>
    </section>
    <section class="panel cart-panel" id="current-sale" aria-label="Current sale" tabindex="-1">
        <div class="panel-head"><div><h2>Current sale</h2><p data-cart-count aria-live="polite">0 items</p></div><button class="btn btn-light btn-sm" type="button" data-clear-cart disabled>Clear</button></div>
        <div class="panel-body cart-body">
            <div data-empty-cart><?php emptyState('Your cart is empty', 'Select a product to add it to this sale.', 'cart3'); ?></div>
            <ul class="cart-items" data-cart-items></ul>
            <div class="cart-total"><span>Total</span><strong data-total aria-live="polite">₱0.00</strong></div>
            <form method="post" class="form-stack" data-checkout>
                <?= formAction('checkout', true) ?>
                <input type="hidden" name="cart" data-cart-json>
                <fieldset class="payment-options"><legend>Payment method</legend>
                    <div>
                        <?php foreach (['cash' => 'cash', 'gcash' => 'phone', 'utang' => 'journal-text'] as $method => $symbol) { ?>
                            <label class="payment-choice"><input type="radio" name="method" value="<?= e($method) ?>" <?= $method === 'cash' ? 'checked' : '' ?>><span><?= icon($symbol) ?><?= e(methodName($method)) ?></span></label>
                        <?php } ?>
                    </div>
                </fieldset>
                <div data-payment="cash">
                    <label class="form-label">Cash received (₱)<input class="form-control" name="tendered" inputmode="decimal" maxlength="15" placeholder="0.00" required></label>
                    <div class="cash-tools"><button class="btn btn-light btn-sm" type="button" data-exact>Exact amount</button><span>Change <strong data-change>₱0.00</strong></span></div>
                </div>
                <div data-payment="gcash" hidden><label class="form-label">GCash reference<input class="form-control" name="reference" maxlength="80" placeholder="Reference from the payment confirmation" disabled></label></div>
                <div data-payment="utang" hidden>
                    <label class="form-label">Customer<select class="form-select" name="customer_id" disabled><option value="">Choose a customer</option><?php foreach ($customers as $customer) { ?><option value="<?= e($customer['id']) ?>"><?= e($customer['name'] . ($customer['phone'] ? ' · ' . $customer['phone'] : '')) ?></option><?php } ?></select></label>
                    <a class="table-link" href="?page=customers">Manage customers</a>
                </div>
                <p class="cart-status" data-cart-status aria-live="polite" hidden></p>
                <button class="btn btn-primary complete-sale" data-complete disabled><?= icon('check2-circle') ?> Complete sale</button>
            </form>
            <noscript><p class="alert alert-danger mt-3">Enable JavaScript to use the checkout.</p></noscript>
        </div>
    </section>
</div>
<?php if ($products) { barcodeScanner(); } ?>
