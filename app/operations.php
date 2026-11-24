<?php
declare(strict_types=1);

function authenticate(PDO $db): void
{
    $address = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
    query($db, "DELETE FROM login_attempts WHERE expires_at <= NOW() LIMIT 100");
    query($db, "INSERT INTO login_attempts (address_hash, attempts, expires_at)
        VALUES (?, 1, DATE_ADD(NOW(), INTERVAL 15 MINUTE))
        ON DUPLICATE KEY UPDATE attempts = LEAST(attempts + 1, 1000)", [$address]);
    if ((int)query($db, "SELECT attempts FROM login_attempts WHERE address_hash = ?", [$address])->fetchColumn() > 20) {
        throw new StoreError('Too many sign-in attempts. Try again in 15 minutes.');
    }

    $username = strtolower(field('username', 40));
    $password = $_POST['password'] ?? '';
    $user = query($db, "SELECT * FROM users WHERE username = ? AND active = 1", [$username])->fetch();
    $hash = $user['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
    if (!is_string($password) || strlen($password) > 72 || !password_verify($password, $hash) || !$user) {
        throw new StoreError('The username or password is incorrect.');
    }

    session_regenerate_id(true);
    $_SESSION = ['user_id' => (int)$user['id'], 'seen' => time(), 'start' => time(), 'csrf' => bin2hex(random_bytes(32))];
    query($db, "DELETE FROM login_attempts WHERE address_hash = ?", [$address]);
}

function saveUser(PDO $db, string $role): void
{
    $name = field('name', 100);
    $username = strtolower(field('username', 40));
    if (!preg_match('/^[a-z0-9._-]{3,40}$/D', $username)) {
        throw new StoreError('Use 3–40 letters, numbers, periods, underscores, or hyphens for the username.');
    }
    query($db, "INSERT INTO users (name, username, password_hash, role) VALUES (?, ?, ?, ?)",
        [$name, $username, newPassword(), $role]);
}

function newPassword(): string
{
    $password = $_POST['password'] ?? '';
    if (!is_string($password) || strlen($password) < 12 || strlen($password) > 72 || str_contains($password, "\0")) {
        throw new StoreError('Use a password between 12 and 72 bytes long.');
    }
    return password_hash($password, PASSWORD_DEFAULT);
}

function firstOwner(PDO $db): void
{
    if (!(int)$db->query("SELECT GET_LOCK(CONCAT('sari_setup_', LEFT(SHA2(DATABASE(), 256), 32)), 5)")->fetchColumn()) {
        throw new StoreError('Setup is busy. Try again.');
    }
    try {
        if ($db->query("SELECT id FROM users LIMIT 1")->fetch()) {
            throw new StoreError('The store already has an account.');
        }
        saveUser($db, 'owner');
    } finally {
        $db->query("SELECT RELEASE_LOCK(CONCAT('sari_setup_', LEFT(SHA2(DATABASE(), 256), 32)))");
    }
}

function saveProduct(PDO $db, int $user): void
{
    $id = integer($_POST['id'] ?? '0', 0);
    $category = integer($_POST['category_id'] ?? null);
    $values = [$category, strtoupper(field('sku', 40)), field('name', 120), field('unit', 30),
        cents('price'), cents('cost', true), integer($_POST['low_stock'] ?? null, 0, 1000000),
        isset($_POST['active']) ? 1 : 0];
    if (!query($db, "SELECT id FROM categories WHERE id = ?", [$category])->fetch()) {
        throw new StoreError('Choose an existing category.');
    }
    if ($id) {
        query($db, "UPDATE products SET category_id=?, sku=?, name=?, unit=?, price_cents=?, cost_cents=?, low_stock=?, active=? WHERE id=?",
            [...$values, $id]);
        return;
    }

    $stock = integer($_POST['opening_stock'] ?? '0', 0, 1000000);
    atomic($db, function () use ($db, $values, $stock, $user): void {
        query($db, "INSERT INTO products (category_id, sku, name, unit, price_cents, cost_cents, low_stock, active, stock)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)", [...$values, $stock]);
        if ($stock) {
            moveStock($db, (int)$db->lastInsertId(), $user, $stock, 'opening');
        }
    });
}

function moveStock(PDO $db, int $product, int $user, int $delta, string $reason, string $note = '', ?int $sale = null, ?string $key = null): void
{
    query($db, "INSERT INTO stock_movements (product_id, user_id, sale_id, quantity_change, reason, note, request_key)
        VALUES (?, ?, ?, ?, ?, ?, ?)", [$product, $user, $sale, $delta, $reason, $note, $key]);
}

function restock(PDO $db, int $user): void
{
    $product = integer($_POST['product_id'] ?? null);
    $quantity = integer($_POST['quantity'] ?? null, 1, 1000000);
    $key = requestKey();
    $note = field('note', 255, false);
    $reason = choice($_POST['reason'] ?? null, ['replenishment', 'adjustment'], 'replenishment');
    $subtract = ($_POST['direction'] ?? '') === 'remove';
    if ($subtract && ($reason !== 'adjustment' || $note === '')) {
        throw new StoreError('Select an adjustment and add a note when removing stock.');
    }
    $delta = $subtract ? -$quantity : $quantity;
    atomic($db, function () use ($db, $user, $product, $reason, $delta, $note, $key): void {
        $current = query($db, "SELECT stock FROM products WHERE id=? FOR UPDATE", [$product])->fetch();
        if (query($db, "SELECT id FROM stock_movements WHERE request_key=? AND user_id=? AND product_id=?", [$key, $user, $product])->fetch()) {
            return;
        }
        if (!$current || $current['stock'] + $delta < 0 || $current['stock'] + $delta > 1000000) {
            throw new StoreError('The resulting stock must be between 0 and 1,000,000 units.');
        }
        query($db, "UPDATE products SET stock=? WHERE id=?", [$current['stock'] + $delta, $product]);
        moveStock($db, $product, $user, $delta, $reason, $note, null, $key);
    });
}

function checkout(PDO $db, int $user): int
{
    $key = requestKey();
    $existing = fn() => query($db, "SELECT id FROM sales WHERE checkout_key=? AND user_id=?", [$key, $user])->fetchColumn();
    if ($sale = $existing()) {
        return (int)$sale;
    }
    $method = paymentMethod(['cash', 'gcash', 'utang']);
    $customer = $method === 'utang' ? integer($_POST['customer_id'] ?? null) : null;
    $tendered = $method === 'cash' ? cents('tendered', false, 12) : null;
    $reference = $method === 'gcash' ? field('reference', 80) : '';
    $input = json_decode(field('cart', 20000), true);
    if (!is_array($input) || !array_is_list($input) || count($input) === 0 || count($input) > 100) {
        throw new StoreError('Add between 1 and 100 different products to the sale.');
    }
    $cart = [];
    foreach ($input as $item) {
        $id = integer($item['id'] ?? null);
        if (isset($cart[$id])) {
            throw new StoreError('A product was entered more than once.');
        }
        $cart[$id] = [
            'quantity' => integer($item['quantity'] ?? null, 1, 10000),
            'price' => integer($item['price'] ?? null, 1, 999999999),
        ];
    }
    ksort($cart);
    try {
        return atomic($db, function () use ($db, $user, $cart, $key, $customer, $method, $tendered, $reference): int {
            if ($customer !== null && !query($db, "SELECT id FROM customers WHERE id=?", [$customer])->fetch()) {
                throw new StoreError('Choose an existing customer for this utang sale.');
            }
            $total = 0;
            $products = [];
            foreach ($cart as $id => $item) {
                $product = query($db, "SELECT * FROM products WHERE id=? FOR UPDATE", [$id])->fetch();
                if (!$product || !$product['active']) {
                    throw new StoreError('A product is unavailable. Refresh the POS and update your cart.');
                }
                if ($item['quantity'] > $product['stock']) {
                    throw new StoreError('Insufficient stock for ' . $product['name'] . '.');
                }
                if ($item['price'] !== (int)$product['price_cents']) {
                    throw new StoreError('The price of ' . $product['name'] . ' changed. Refresh the POS before charging the customer.');
                }
                $total += $item['quantity'] * (int)$product['price_cents'];
                $products[$id] = $product;
            }
            if ($tendered !== null && $tendered < $total) {
                throw new StoreError('Cash received must cover the sale total.');
            }
            query($db, "INSERT INTO sales (user_id, customer_id, checkout_key, total_cents) VALUES (?, ?, ?, ?)",
                [$user, $customer, $key, $total]);
            $sale = (int)$db->lastInsertId();
            foreach ($products as $id => $product) {
                $quantity = $cart[$id]['quantity'];
                query($db, "INSERT INTO sales_details (sale_id, product_id, product_name, unit, quantity, price_cents, cost_cents)
                    VALUES (?, ?, ?, ?, ?, ?, ?)",
                    [$sale, $id, $product['name'], $product['unit'], $quantity, $product['price_cents'], $product['cost_cents']]);
                query($db, "UPDATE products SET stock=stock-? WHERE id=?", [$quantity, $id]);
                moveStock($db, $id, $user, -$quantity, 'sale', '', $sale);
            }
            query($db, "INSERT INTO payments (sale_id, method, amount_cents, tendered_cents, change_cents, reference)
                VALUES (?, ?, ?, ?, ?, ?)", [$sale, $method, $total, $tendered, $tendered === null ? 0 : $tendered - $total, $reference]);
            if ($method === 'utang') {
                query($db, "INSERT INTO debts (sale_id, original_cents, balance_cents) VALUES (?, ?, ?)", [$sale, $total, $total]);
            }
            return $sale;
        });
    } catch (PDOException | StoreError $error) {
        if ($sale = $existing()) {
            return (int)$sale;
        }
        throw $error;
    }
}

function collectDebt(PDO $db, int $user): void
{
    $id = integer($_POST['debt_id'] ?? null);
    $amount = cents('amount', false, 12);
    $method = paymentMethod(['cash', 'gcash']);
    $reference = $method === 'gcash' ? field('reference', 80) : '';
    $key = requestKey();
    atomic($db, function () use ($db, $id, $amount, $method, $reference, $user, $key): void {
        $debt = query($db, "SELECT balance_cents FROM debts WHERE id=? FOR UPDATE", [$id])->fetch();
        if (query($db, "SELECT id FROM debt_payments WHERE request_key=? AND user_id=? AND debt_id=?", [$key, $user, $id])->fetch()) {
            return;
        }
        if (!$debt || $amount > (int)$debt['balance_cents']) {
            throw new StoreError('The payment exceeds the outstanding balance.');
        }
        query($db, "INSERT INTO debt_payments (debt_id, user_id, amount_cents, method, reference, request_key) VALUES (?, ?, ?, ?, ?, ?)",
            [$id, $user, $amount, $method, $reference, $key]);
        query($db, "UPDATE debts SET balance_cents=balance_cents-? WHERE id=?", [$amount, $id]);
    });
}

function saveCustomer(PDO $db): int
{
    $id = integer($_POST['id'] ?? '0', 0);
    $values = [field('name', 100), field('phone', 35, false), field('notes', 255, false)];
    if ($id) {
        query($db, "UPDATE customers SET name=?, phone=?, notes=? WHERE id=?", [...$values, $id]);
        return $id;
    }
    query($db, "INSERT INTO customers (name, phone, notes) VALUES (?, ?, ?)", $values);
    return (int)$db->lastInsertId();
}
