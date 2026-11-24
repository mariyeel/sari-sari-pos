<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Manila');

final class StoreError extends RuntimeException {}

function settings(): array
{
    $local = PHP_SAPI === 'cli' || in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
    $config = [
        'local' => $local,
        'db_host' => '127.0.0.1',
        'db_port' => 3306,
        'db_name' => 'pos_db',
        'db_user' => $local ? 'root' : '',
        'db_pass' => '',
    ];
    $file = dirname(__DIR__) . '/config.local.php';
    return is_file($file) ? array_replace($config, require $file) : $config;
}

function connection(array $config): PDO
{
    $pdo = new PDO(
        "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4",
        $config['db_user'],
        $config['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
    );
    $pdo->exec("SET time_zone = '+08:00'");
    return $pdo;
}

function query(PDO $db, string $sql, array $params = []): PDOStatement
{
    $statement = $db->prepare($sql);
    $statement->execute($params);
    return $statement;
}

function atomic(PDO $db, callable $work): mixed
{
    $db->beginTransaction();
    try {
        $result = $work();
        $db->commit();
        return $result;
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $error;
    }
}

function e(string|int|null $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function money(int|string|null $cents): string
{
    $amount = decimal($cents);
    $negative = str_starts_with($amount, '-');
    [$whole, $fraction] = explode('.', ltrim($amount, '-'));
    $grouped = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $whole);
    return ($negative ? '−' : '') . '₱' . $grouped . '.' . $fraction;
}

function decimal(int|string|null $cents): string
{
    $amount = (string)($cents ?? 0);
    $digits = str_pad(ltrim($amount, '-'), 3, '0', STR_PAD_LEFT);
    return (str_starts_with($amount, '-') ? '-' : '') . substr($digits, 0, -2) . '.' . substr($digits, -2);
}

function field(string $key, int $max, bool $required = true): string
{
    $raw = $_POST[$key] ?? '';
    $value = is_string($raw) ? trim($raw) : '';
    if (($required && $value === '') || mb_strlen($value, 'UTF-8') > $max) {
        throw new StoreError('Check the ' . str_replace('_', ' ', $key) . ' field.');
    }
    return $value;
}

function integer(mixed $value, int $minimum = 1, int $maximum = 2147483647): int
{
    $result = is_scalar($value) ? filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $minimum, 'max_range' => $maximum]]) : false;
    if ($result === false) {
        throw new StoreError('Enter a valid whole number.');
    }
    return $result;
}

function cents(string $key, bool $allowZero = false, int $wholeDigits = 7): int
{
    $value = field($key, $wholeDigits + 3);
    if (!preg_match('/^(\d{1,' . $wholeDigits . '})(?:\.(\d{1,2}))?$/D', $value, $parts)) {
        throw new StoreError('Enter a valid peso amount with up to two decimal places.');
    }
    $amount = (int)$parts[1] * 100 + (int)str_pad($parts[2] ?? '', 2, '0');
    if (!$allowZero && $amount === 0) {
        throw new StoreError('Enter an amount greater than zero.');
    }
    return $amount;
}

function requestKey(): string
{
    $key = field('request_key', 32);
    if (!preg_match('/^[a-f0-9]{32}$/D', $key)) {
        throw new StoreError('Refresh the page and try again.');
    }
    return $key;
}

function dateValue(string $key): string
{
    $value = field($key, 10);
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value || (int)$date->format('Y') < 1000) {
        throw new StoreError('Enter a valid date.');
    }
    return $value;
}

function paymentMethod(array $allowed): string
{
    $value = field('method', 8);
    if (!in_array($value, $allowed, true)) {
        throw new StoreError('Choose a valid payment method.');
    }
    return $value;
}

function methodName(string $method): string
{
    return ['cash' => 'Cash', 'gcash' => 'GCash', 'utang' => 'Utang'][$method] ?? $method;
}

function receiptNumber(int|string $id): string
{
    return 'TX-' . str_pad((string)$id, 5, '0', STR_PAD_LEFT);
}

function webDate(string $date, bool $withTime = true): string
{
    return (new DateTimeImmutable($date))->format($withTime ? 'M j, Y · g:i A' : 'M j, Y');
}

function redirect(string $target): never
{
    header('Location: ' . $target, true, 303);
    exit;
}

function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = [$type, $message];
}

function csrf(): string
{
    return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">';
}

function formAction(string $action, bool $key = false): string
{
    return csrf() . '<input type="hidden" name="action" value="' . e($action) . '">' .
        ($key ? '<input type="hidden" name="request_key" value="' . bin2hex(random_bytes(16)) . '">' : '');
}

function choice(mixed $value, array $allowed, string $default): string
{
    return is_string($value) && in_array($value, $allowed, true) ? $value : $default;
}
