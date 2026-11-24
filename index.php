<?php
declare(strict_types=1);

require __DIR__ . '/app/core.php';
require __DIR__ . '/app/operations.php';
require __DIR__ . '/app/read.php';

$config = settings();
$secure = in_array(strtolower($_SERVER['HTTPS'] ?? ''), ['on', '1'], true);
$local = $config['local'] && in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self'; img-src 'self' data:; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
header('Cache-Control: no-store');
if (!$secure && !$local) {
    http_response_code(403);
    exit('Open this store through its HTTPS address.');
}
if ($secure && !$local) {
    header('Strict-Transport-Security: max-age=31536000');
}

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
$cookiePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/') . '/';
session_name('SARI_STORE');
session_set_cookie_params(['path' => $cookiePath, 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax']);
session_start();
if (isset($_SESSION['user_id']) && (time() - ($_SESSION['seen'] ?? 0) > 1800 || time() - ($_SESSION['start'] ?? 0) > 43200)) {
    $_SESSION = [];
    session_regenerate_id(true);
}
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));

try {
    $db = connection($config);
    $setup = $local && !$db->query("SELECT id FROM users LIMIT 1")->fetch();
} catch (PDOException $error) {
    error_log($error->getMessage());
    http_response_code(503);
    $unavailable = true;
    require __DIR__ . '/views/auth.php';
    exit;
}
$user = isset($_SESSION['user_id']) ? query($db, "SELECT id, name, username, role FROM users WHERE id=? AND active=1", [$_SESSION['user_id']])->fetch() : false;
if ($user) {
    $_SESSION['seen'] = time();
} elseif (isset($_SESSION['user_id'])) {
    $_SESSION = ['csrf' => bin2hex(random_bytes(32))];
    session_regenerate_id(true);
}
$owner = $user && $user['role'] === 'owner';
$sections = ['dashboard', 'pos', 'inventory', 'customers', 'transactions', 'receipt', 'account'];
$adminSections = ['products', 'expenses', 'reports', 'staff'];
$page = choice($_GET['page'] ?? null, [...$sections, ...$adminSections], 'dashboard');
if ($user && !$owner && in_array($page, $adminSections, true)) {
    http_response_code(403);
    $page = 'forbidden';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    $back = 'index.php?page=' . $page;
    try {
        if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
            throw new StoreError('Your session changed. Refresh the page and try again.');
        }
        if (!$user) {
            if ($action === 'setup' && $setup) {
                firstOwner($db);
                flash('Owner account created. Sign in to open your store.');
                redirect('index.php');
            }
            if ($action !== 'login') {
                throw new StoreError('Sign in to continue.');
            }
            authenticate($db);
            redirect('index.php');
        }
        if (in_array($action, ['product', 'category', 'delete_category', 'stock', 'expense', 'staff', 'staff_status'], true) && !$owner) {
            http_response_code(403);
            exit('Owner access is required.');
        }

        switch ($action) {
            case 'logout':
                $_SESSION = [];
                setcookie(session_name(), '', ['expires' => 1, 'path' => $cookiePath, 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax']);
                session_destroy();
                redirect('index.php');
            case 'product':
                saveProduct($db, (int)$user['id']);
                flash('Product saved.');
                redirect('index.php?page=products');
            case 'category':
                $id = integer($_POST['id'] ?? '0', 0);
                $name = field('name', 80);
                $id ? query($db, "UPDATE categories SET name=? WHERE id=?", [$name, $id])
                    : query($db, "INSERT INTO categories (name) VALUES (?)", [$name]);
                flash('Category saved.');
                break;
            case 'delete_category':
                $id = integer($_POST['id'] ?? null);
                if (query($db, "SELECT id FROM products WHERE category_id=? LIMIT 1", [$id])->fetch()) {
                    throw new StoreError('Move the products in this category before deleting it.');
                }
                query($db, "DELETE FROM categories WHERE id=?", [$id]);
                flash('Category deleted.');
                break;
            case 'stock':
                restock($db, (int)$user['id']);
                flash('Stock updated.');
                break;
            case 'checkout':
                $id = checkout($db, (int)$user['id']);
                flash('Sale completed.');
                redirect('index.php?page=receipt&id=' . $id);
            case 'customer':
                $id = saveCustomer($db);
                flash('Customer saved.');
                redirect('index.php?page=customers&id=' . $id);
            case 'debt_payment':
                collectDebt($db, (int)$user['id']);
                flash('Utang payment recorded.');
                break;
            case 'expense':
                $key = requestKey();
                query($db, "INSERT INTO expenses (user_id, description, category, amount_cents, expense_date, request_key)
                    VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE request_key=request_key",
                    [$user['id'], field('description', 140), field('category', 60), cents('amount'), dateValue('expense_date'), $key]);
                flash('Expense recorded.');
                break;
            case 'staff':
                saveUser($db, 'cashier');
                flash('Cashier account created.');
                break;
            case 'staff_status':
                query($db, "UPDATE users SET active=1-active WHERE id=? AND role='cashier'", [integer($_POST['id'] ?? null)]);
                flash('Cashier access updated.');
                break;
            case 'password':
                $hash = query($db, "SELECT password_hash FROM users WHERE id=?", [$user['id']])->fetchColumn();
                $old = $_POST['current_password'] ?? '';
                if (!is_string($old) || strlen($old) > 72 || !password_verify($old, $hash)) {
                    throw new StoreError('Your current password is incorrect.');
                }
                query($db, "UPDATE users SET password_hash=? WHERE id=?", [newPassword(), $user['id']]);
                session_regenerate_id(true);
                $_SESSION['csrf'] = bin2hex(random_bytes(32));
                flash('Password updated.');
                break;
            default:
                throw new StoreError('This action is unavailable.');
        }
    } catch (StoreError $error) {
        flash($error->getMessage(), 'danger');
    } catch (PDOException $error) {
        error_log($error->getMessage());
        $code = (int)($error->errorInfo[1] ?? 0);
        flash(match ($code) {
            1062 => 'A record with that name, product code, or username already exists.',
            1451, 1452 => 'The linked record changed. Refresh the page and try again.',
            default => 'The store could not save that change. Please try again.',
        }, 'danger');
    }
    foreach (['id', 'edit', 'category'] as $param) {
        if (isset($_GET[$param]) && is_string($_GET[$param]) && ctype_digit($_GET[$param])) {
            $back .= '&' . $param . '=' . (int)$_GET[$param];
        }
    }
    redirect($user ? $back : 'index.php');
}

$message = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
if (!$user) {
    require __DIR__ . '/views/auth.php';
    exit;
}
if ($page === 'receipt') {
    try {
        $receipt = receipt($db, $user, integer($_GET['id'] ?? null));
    } catch (StoreError) {
        http_response_code(404);
        $page = 'missing';
    }
}
require __DIR__ . '/views/layout.php';
