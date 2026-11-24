<?php
declare(strict_types=1);

function metrics(PDO $db, DateTimeImmutable $start, DateTimeImmutable $end): array
{
    $dates = [$start->format('Y-m-d'), $end->format('Y-m-d')];
    $sales = query($db, "SELECT COUNT(*) AS transactions, COALESCE(SUM(s.total_cents),0) AS sales,
        COALESCE(SUM(CASE WHEN p.method='cash' THEN s.total_cents ELSE 0 END),0) AS cash,
        COALESCE(SUM(CASE WHEN p.method='gcash' THEN s.total_cents ELSE 0 END),0) AS gcash,
        COALESCE(SUM(CASE WHEN p.method='utang' THEN s.total_cents ELSE 0 END),0) AS utang
        FROM sales s JOIN payments p ON p.sale_id=s.id WHERE s.created_at>=? AND s.created_at<?", $dates)->fetch();
    $sales['cost'] = query($db, "SELECT COALESCE(SUM(sd.cost_cents*sd.quantity),0)
        FROM sales s JOIN sales_details sd ON sd.sale_id=s.id WHERE s.created_at>=? AND s.created_at<?", $dates)->fetchColumn();
    $sales['expenses'] = query($db, "SELECT COALESCE(SUM(amount_cents),0) FROM expenses
        WHERE expense_date>=? AND expense_date<?", $dates)->fetchColumn();
    $sales['collections'] = query($db, "SELECT COALESCE(SUM(amount_cents),0) FROM debt_payments
        WHERE created_at>=? AND created_at<?", $dates)->fetchColumn();
    $sales['gross'] = (int)$sales['sales'] - (int)$sales['cost'];
    $sales['net'] = $sales['gross'] - (int)$sales['expenses'];
    return $sales;
}

function dailySeries(PDO $db, DateTimeImmutable $start, DateTimeImmutable $end, ?int $user = null): array
{
    $params = [$start->format('Y-m-d'), $end->format('Y-m-d')];
    if ($user !== null) { $params[] = $user; }
    $rows = query($db, "SELECT DATE(created_at) AS day, SUM(total_cents) AS total FROM sales
        WHERE created_at>=? AND created_at<?" . ($user === null ? '' : ' AND user_id=?') . ' GROUP BY DATE(created_at)', $params)
        ->fetchAll(PDO::FETCH_KEY_PAIR);
    $series = [];
    for ($day = $start; $day < $end; $day = $day->modify('+1 day')) {
        $series[] = [$day, (int)($rows[$day->format('Y-m-d')] ?? 0)];
    }
    return $series;
}

function saleRows(PDO $db, array $user, bool $all = false): array
{
    $clauses = [];
    $values = [];
    if ($user['role'] !== 'owner') {
        $clauses[] = 's.user_id=?';
        $values[] = $user['id'];
    }
    if ($all) {
        $method = choice($_GET['method'] ?? null, ['cash','gcash','utang'], '');
        $search = is_string($_GET['find'] ?? null) ? trim($_GET['find']) : '';
        if ($method) {
            $clauses[] = 'p.method=?';
            $values[] = $method;
        }
        if (preg_match('/^(?:TX-)?0*([1-9][0-9]{0,9})$/iD', $search, $parts)) {
            $clauses[] = 's.id=?';
            $values[] = $parts[1];
        } elseif ($search !== '') {
            $clauses[] = 'c.name LIKE ?';
            $values[] = '%' . substr($search, 0, 100) . '%';
        }
    }
    $page = $all ? max(1, min(50000, (int)($_GET['n'] ?? 1))) : 1;
    $limit = $all ? 26 : 5;
    $offset = ($page - 1) * 25;
    return query($db, "SELECT s.*, p.method, u.name AS cashier, c.name AS customer
        FROM sales s JOIN payments p ON p.sale_id=s.id JOIN users u ON u.id=s.user_id
        LEFT JOIN customers c ON c.id=s.customer_id" .
        ($clauses ? ' WHERE ' . implode(' AND ', $clauses) : '') .
        " ORDER BY s.id DESC LIMIT $limit OFFSET $offset", $values)->fetchAll();
}

function receipt(PDO $db, array $user, int $id): array
{
    $row = query($db, "SELECT s.*, p.method, p.tendered_cents, p.change_cents, p.reference,
        u.name AS cashier, c.name AS customer FROM sales s
        JOIN payments p ON p.sale_id=s.id JOIN users u ON u.id=s.user_id
        LEFT JOIN customers c ON c.id=s.customer_id WHERE s.id=?", [$id])->fetch();
    if (!$row || ($user['role'] !== 'owner' && (int)$row['user_id'] !== (int)$user['id'])) {
        throw new StoreError('Receipt unavailable.');
    }
    $row['items'] = query($db, "SELECT * FROM sales_details WHERE sale_id=? ORDER BY id", [$id])->fetchAll();
    return $row;
}
