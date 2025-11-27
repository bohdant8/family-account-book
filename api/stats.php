<?php
/**
 * Statistics API
 * Provides summary and report data
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../database.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $action = $_GET['action'] ?? 'summary';
    
    switch ($action) {
        case 'summary':
            getSummary();
            break;
        case 'monthly':
            getMonthlyStats();
            break;
        case 'category':
            getCategoryStats();
            break;
        case 'member':
            getMemberStats();
            break;
        case 'trend':
            getTrend();
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function getSummary() {
    $pdo = db();
    
    $startDate = $_GET['start_date'] ?? date('Y-m-01');
    $endDate = $_GET['end_date'] ?? date('Y-m-t');
    
    // Total income
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(t.amount), 0) as total
        FROM transactions t
        JOIN categories c ON t.category_id = c.id
        WHERE c.type = 'income' AND t.transaction_date BETWEEN ? AND ?
    ");
    $stmt->execute([$startDate, $endDate]);
    $income = $stmt->fetch()['total'];
    
    // Total expense
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(t.amount), 0) as total
        FROM transactions t
        JOIN categories c ON t.category_id = c.id
        WHERE c.type = 'expense' AND t.transaction_date BETWEEN ? AND ?
    ");
    $stmt->execute([$startDate, $endDate]);
    $expense = $stmt->fetch()['total'];
    
    // Transaction count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM transactions
        WHERE transaction_date BETWEEN ? AND ?
    ");
    $stmt->execute([$startDate, $endDate]);
    $count = $stmt->fetch()['count'];
    
    // All time balance
    $stmt = $pdo->query("
        SELECT 
            COALESCE(SUM(CASE WHEN c.type = 'income' THEN t.amount ELSE 0 END), 0) as total_income,
            COALESCE(SUM(CASE WHEN c.type = 'expense' THEN t.amount ELSE 0 END), 0) as total_expense
        FROM transactions t
        JOIN categories c ON t.category_id = c.id
    ");
    $allTime = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'income' => (float)$income,
            'expense' => (float)$expense,
            'balance' => (float)$income - (float)$expense,
            'transaction_count' => (int)$count,
            'all_time_balance' => (float)$allTime['total_income'] - (float)$allTime['total_expense'],
            'period' => [
                'start' => $startDate,
                'end' => $endDate
            ]
        ]
    ]);
}

function getMonthlyStats() {
    $pdo = db();
    
    $year = $_GET['year'] ?? date('Y');
    
    $stmt = $pdo->prepare("
        SELECT 
            strftime('%m', t.transaction_date) as month,
            c.type,
            SUM(t.amount) as total
        FROM transactions t
        JOIN categories c ON t.category_id = c.id
        WHERE strftime('%Y', t.transaction_date) = ?
        GROUP BY month, c.type
        ORDER BY month
    ");
    $stmt->execute([$year]);
    $results = $stmt->fetchAll();
    
    // Initialize monthly data
    $monthly = [];
    for ($i = 1; $i <= 12; $i++) {
        $month = str_pad($i, 2, '0', STR_PAD_LEFT);
        $monthly[$month] = ['income' => 0, 'expense' => 0];
    }
    
    // Fill in actual data
    foreach ($results as $row) {
        $monthly[$row['month']][$row['type']] = (float)$row['total'];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $monthly,
        'year' => $year
    ]);
}

function getCategoryStats() {
    $pdo = db();
    
    $startDate = $_GET['start_date'] ?? date('Y-m-01');
    $endDate = $_GET['end_date'] ?? date('Y-m-t');
    $type = $_GET['type'] ?? 'expense';
    
    $stmt = $pdo->prepare("
        SELECT 
            c.id,
            c.name,
            c.icon,
            c.color,
            SUM(t.amount) as total,
            COUNT(t.id) as count
        FROM transactions t
        JOIN categories c ON t.category_id = c.id
        WHERE c.type = ? AND t.transaction_date BETWEEN ? AND ?
        GROUP BY c.id
        ORDER BY total DESC
    ");
    $stmt->execute([$type, $startDate, $endDate]);
    $results = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => $results,
        'type' => $type,
        'period' => ['start' => $startDate, 'end' => $endDate]
    ]);
}

function getMemberStats() {
    $pdo = db();
    
    $startDate = $_GET['start_date'] ?? date('Y-m-01');
    $endDate = $_GET['end_date'] ?? date('Y-m-t');
    
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(t.member, 'Unassigned') as member,
            c.type,
            SUM(t.amount) as total,
            COUNT(t.id) as count
        FROM transactions t
        JOIN categories c ON t.category_id = c.id
        WHERE t.transaction_date BETWEEN ? AND ?
        GROUP BY t.member, c.type
        ORDER BY total DESC
    ");
    $stmt->execute([$startDate, $endDate]);
    $results = $stmt->fetchAll();
    
    // Reorganize by member
    $members = [];
    foreach ($results as $row) {
        $name = $row['member'];
        if (!isset($members[$name])) {
            $members[$name] = ['income' => 0, 'expense' => 0, 'count' => 0];
        }
        $members[$name][$row['type']] = (float)$row['total'];
        $members[$name]['count'] += (int)$row['count'];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $members,
        'period' => ['start' => $startDate, 'end' => $endDate]
    ]);
}

function getTrend() {
    $pdo = db();
    
    $days = isset($_GET['days']) ? (int)$_GET['days'] : 30;
    $startDate = date('Y-m-d', strtotime("-$days days"));
    $endDate = date('Y-m-d');
    
    $stmt = $pdo->prepare("
        SELECT 
            t.transaction_date as date,
            c.type,
            SUM(t.amount) as total
        FROM transactions t
        JOIN categories c ON t.category_id = c.id
        WHERE t.transaction_date BETWEEN ? AND ?
        GROUP BY t.transaction_date, c.type
        ORDER BY t.transaction_date
    ");
    $stmt->execute([$startDate, $endDate]);
    $results = $stmt->fetchAll();
    
    // Initialize daily data
    $daily = [];
    $current = strtotime($startDate);
    $end = strtotime($endDate);
    
    while ($current <= $end) {
        $date = date('Y-m-d', $current);
        $daily[$date] = ['income' => 0, 'expense' => 0];
        $current = strtotime('+1 day', $current);
    }
    
    // Fill in actual data
    foreach ($results as $row) {
        $daily[$row['date']][$row['type']] = (float)$row['total'];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $daily,
        'period' => ['start' => $startDate, 'end' => $endDate]
    ]);
}

