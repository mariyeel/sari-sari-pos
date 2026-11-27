<?php
$categories = $db->query("SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id=c.id) AS product_count FROM categories c ORDER BY name")->fetchAll();
$products = $db->query("SELECT p.*, c.name AS category FROM products p JOIN categories c ON c.id=p.category_id ORDER BY p.active DESC, p.name")->fetchAll();
$product = !empty($_GET['edit']) ? query($db, "SELECT * FROM products WHERE id=?", [(int)$_GET['edit']])->fetch() : false;
$category = !empty($_GET['category']) ? query($db, "SELECT * FROM categories WHERE id=?", [(int)$_GET['category']])->fetch() : false;
?>
<section class="page-heading"><div><p class="eyebrow">CATALOG</p><h1>Products &amp; categories</h1><p>Set your tingi selling units, prices, and stock alerts.</p></div><a href="?page=inventory" class="btn btn-light"><?= icon('layers') ?> Inventory</a></section>
<div class="management-grid">
    <section class="panel table-panel" data-product-list>
        <div class="panel-head"><div><h2 id="products-heading">Products</h2><p><?= e(count($products)) ?> in the catalog</p></div><?php if ($product) { ?><a class="btn btn-light btn-sm" href="?page=products#product-form">Add product</a><?php } ?></div>
        <?php if ($products) { ?>
        <div class="panel-body product-search-tools">
            <label class="search-field"><?= icon('search') ?><input type="search" class="form-control" placeholder="Search product or SKU" aria-label="Search product or SKU" data-product-search></label>
            <button type="button" class="btn btn-light" aria-haspopup="dialog" aria-controls="barcode-scanner" data-open-barcode-scanner="search" hidden>Scan barcode</button>
        </div>
        <div class="table-responsive catalog-table-scroll" role="region" aria-labelledby="products-heading" tabindex="0"><table class="table align-middle mb-0"><thead><tr><th>Product</th><th>Category</th><th>Selling price</th><th>Unit cost</th><th>Status</th><th><span class="visually-hidden">Edit</span></th></tr></thead><tbody>
        <?php foreach ($products as $item) { ?><tr data-product-name="<?= e($item['name']) ?>" data-product-sku="<?= e($item['sku']) ?>"><td class="fw-semibold"><?= e($item['name']) ?><small class="d-block text-secondary"><?= e($item['sku']) ?> · <?= e($item['unit']) ?></small></td><td><?= e($item['category']) ?></td><td><?= e(money($item['price_cents'])) ?></td><td><?= e(money($item['cost_cents'])) ?></td><td><span class="method <?= $item['active'] ? 'method-cash' : 'method-utang' ?>"><?= $item['active'] ? 'Active' : 'Archived' ?></span></td><td class="text-end"><a class="btn btn-light btn-sm" href="?page=products&amp;edit=<?= e($item['id']) ?>#product-form">Edit</a></td></tr><?php } ?>
        <tr hidden data-product-search-empty><td colspan="6" class="text-center text-secondary">No products match your search.</td></tr>
        </tbody></table></div>
        <?php } else { emptyState('No products yet', 'Add a category, then create your first product.', 'box-seam'); } ?>
    </section>
    <section class="panel" id="product-form">
        <div class="panel-head"><div><h2><?= $product ? 'Edit product' : 'Add product' ?></h2><p>Enter the price and cost for one selling unit.</p></div></div>
        <?php if (!$categories) { ?><div class="panel-body"><a class="table-link" href="#category-form">Add a category first</a></div><?php } else { ?>
        <form method="post" class="panel-body form-stack">
            <?= formAction('product') ?><input type="hidden" name="id" value="<?= e($product['id'] ?? 0) ?>">
            <label class="form-label">Product name<input name="name" class="form-control" maxlength="120" value="<?= e($product['name'] ?? '') ?>" required></label>
            <div class="field-pair">
                <div>
                    <label class="form-label">SKU<input name="sku" class="form-control" maxlength="40" value="<?= e($product['sku'] ?? '') ?>" required></label>
                    <button type="button" class="btn btn-light btn-sm mt-2" aria-haspopup="dialog" aria-controls="barcode-scanner" data-open-barcode-scanner="sku" hidden>Scan SKU</button>
                    <p class="text-secondary small" role="status" data-sku-scan-status></p>
                </div>
                <label class="form-label">Selling unit<input name="unit" class="form-control" maxlength="30" value="<?= e($product['unit'] ?? 'piece') ?>" placeholder="piece, sachet, pack" required></label>
            </div>
            <label class="form-label">Category<select class="form-select" name="category_id" required><option value="">Choose a category</option><?php foreach ($categories as $item) { ?><option value="<?= e($item['id']) ?>" <?= ($product['category_id'] ?? null) === $item['id'] ? 'selected' : '' ?>><?= e($item['name']) ?></option><?php } ?></select></label>
            <div class="field-pair">
                <label class="form-label">Selling price (₱)<input name="price" class="form-control" inputmode="decimal" maxlength="10" value="<?= $product ? e(decimal($product['price_cents'])) : '' ?>" required></label>
                <label class="form-label">Unit cost (₱)<input name="cost" class="form-control" inputmode="decimal" maxlength="10" value="<?= $product ? e(decimal($product['cost_cents'])) : '' ?>" required></label>
            </div>
            <div class="field-pair">
                <label class="form-label">Low-stock level<input type="number" class="form-control" name="low_stock" min="0" max="1000000" value="<?= e($product['low_stock'] ?? 5) ?>" required></label>
                <?php if (!$product) { ?><label class="form-label">Opening stock<input type="number" class="form-control" name="opening_stock" min="0" max="1000000" value="0" required></label><?php } ?>
            </div>
            <label class="form-check"><input class="form-check-input" type="checkbox" name="active" <?= ($product['active'] ?? 1) ? 'checked' : '' ?>> <span class="form-check-label">Available for sale</span></label>
            <button class="btn btn-primary"><?= icon('check2') ?> Save product</button>
        </form>
        <?php } ?>
    </section>
    <section class="panel table-panel">
        <div class="panel-head"><div><h2 id="categories-heading">Categories</h2><p>Group products so they’re easy to find at checkout.</p></div></div>
        <?php if ($categories) { ?>
        <div class="table-responsive catalog-table-scroll category-table-scroll" role="region" aria-labelledby="categories-heading" tabindex="0"><table class="table align-middle mb-0"><thead><tr><th>Name</th><th>Products</th><th class="text-end">Actions</th></tr></thead><tbody>
        <?php foreach ($categories as $item) { ?><tr><td class="fw-semibold"><?= e($item['name']) ?></td><td><?= e($item['product_count']) ?></td><td><div class="table-actions">
            <a class="btn btn-light btn-sm" href="?page=products&amp;category=<?= e($item['id']) ?>#category-form">Edit</a>
            <?php if (!$item['product_count']) { ?><form method="post" action="?page=products" data-confirm="Delete this empty category?"><?= formAction('delete_category') ?><input type="hidden" name="id" value="<?= e($item['id']) ?>"><button class="btn btn-light btn-sm">Delete</button></form><?php } ?>
        </div></td></tr><?php } ?>
        </tbody></table></div>
        <?php } else { emptyState('No categories yet', '', 'tags'); } ?>
    </section>
    <section class="panel" id="category-form">
        <div class="panel-head"><div><h2><?= $category ? 'Edit category' : 'Add category' ?></h2></div><?php if ($category) { ?><a class="btn btn-light btn-sm" href="?page=products#category-form">Add category</a><?php } ?></div>
        <form method="post" action="?page=products" class="panel-body form-stack">
            <?= formAction('category') ?><input type="hidden" name="id" value="<?= e($category['id'] ?? 0) ?>">
            <label class="form-label">Category name<input name="name" class="form-control" maxlength="80" value="<?= e($category['name'] ?? '') ?>" required></label>
            <button class="btn btn-primary"><?= icon('check2') ?> Save category</button>
        </form>
    </section>
</div>
<?php if ($products || $categories) { barcodeScanner(); } ?>
