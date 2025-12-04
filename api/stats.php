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
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * Get historical exchange rate for a specific date
 * Returns the most recent rate on or before the given date
 * Falls back to current rate if no history exists
 */
function getHistoricalRate($pdo, $currency, $date, $currentRates) {
    // CNY is always 1
    if ($currency === 'CNY') {
        return 1.0;
    }
    
    // Try to get historical rate
    $stmt = $pdo->prepare("
        SELECT rate FROM exchange_rate_history 
        WHERE currency = ? AND effective_date <= ?
        ORDER BY effective_date DESC
        LIMIT 1
    ");
    $stmt->execute([$currency, $date]);
    $result = $stmt->fetch();
    
    if ($result) {
        return (float)$result['rate'];
    }
    
    // Fallback to current rate if no history
    return $currentRates[$currency]['rate'] ?? 1.0;
}

function getSummary() {
    $pdo = db();
    $baseCurrency = BASE_CURRENCY;
    
    // Get exchange rates from database
    $stmt = $pdo->query("SELECT currency, rate FROM exchange_rates");
    $ratesResult = $stmt->fetchAll();
    $currencies = [];
    foreach ($ratesResult as $row) {
        $currencies[$row['currency']] = [
            'symbol' => $row['currency'] === 'USD' ? '$' : '¥',
            'rate' => (float)$row['rate']
        ];
    }
    // Fallback to config if no rates in DB
    if (empty($currencies)) {
        $currencies = CURRENCIES;
    }
    
    $startDate = $_GET['start_date'] ?? date('Y-m-01');
    $endDate = $_GET['end_date'] ?? date('Y-m-t');
    
    // Get individual transactions for period (to use historical rates)
    $stmt = $pdo->prepare("
        SELECT 
            t.amount,
            COALESCE(t.currency, 'CNY') as currency,
            t.transaction_date,
            c.type
        FROM transactions t
        JOIN categories c ON t.category_id = c.id
        WHERE t.transaction_date BETWEEN ? AND ?
    ");
    $stmt->execute([$startDate, $endDate]);
    $periodTransactions = $stmt->fetchAll();
    
    // Calculate period totals using historical rates
    $periodByCurrency = [];
    $periodTotalIncome = 0;
    $periodTotalExpense = 0;
    
    foreach ($periodTransactions as $tx) {
        $cur = $tx['currency'];
        $amount = (float)$tx['amount'];
        $type = $tx['type'];
        $date = $tx['transaction_date'];
        
        // Get historical rate for this transaction's date
        $historicalRate = getHistoricalRate($pdo, $cur, $date, $currencies);
        
        // Track by currency (raw amounts)
        if (!isset($periodByCurrency[$cur])) {
            $periodByCurrency[$cur] = ['income' => 0, 'expense' => 0];
        }
        $periodByCurrency[$cur][$type] += $amount;
        
        // Convert to base currency using historical rate
        $convertedAmount = $amount * $historicalRate;
        if ($type === 'income') {
            $periodTotalIncome += $convertedAmount;
        } else {
            $periodTotalExpense += $convertedAmount;
        }
    }
    
    // Transaction count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM transactions
        WHERE transaction_date BETWEEN ? AND ?
    ");
    $stmt->execute([$startDate, $endDate]);
    $count = $stmt->fetch()['count'];
    
    // All time balance by currency (from transactions)
    $stmt = $pdo->query("
        SELECT 
            COALESCE(t.currency, 'CNY') as currency,
            COALESCE(SUM(CASE WHEN c.type = 'income' THEN t.amount ELSE 0 END), 0) as total_income,
            COALESCE(SUM(CASE WHEN c.type = 'expense' THEN t.amount ELSE 0 END), 0) as total_expense
        FROM transactions t
        JOIN categories c ON t.category_id = c.id
        GROUP BY t.currency
    ");
    $allTimeResults = $stmt->fetchAll();
    
    // Organize all-time data by currency
    $allTimeByCurrency = [];
    $allTimeTotalIncome = 0;
    $allTimeTotalExpense = 0;
    
    foreach ($allTimeResults as $row) {
        $cur = $row['currency'];
        $rate = $currencies[$cur]['rate'] ?? 1;
        
        $allTimeByCurrency[$cur] = [
            'income' => (float)$row['total_income'],
            'expense' => (float)$row['total_expense'],
            'balance' => (float)$row['total_income'] - (float)$row['total_expense']
        ];
        
        // Convert to base currency
        $allTimeTotalIncome += (float)$row['total_income'] * $rate;
        $allTimeTotalExpense += (float)$row['total_expense'] * $rate;
    }
    
    // Include currency exchanges in balance
    // Exchanges: subtract from_amount from from_currency, add to_amount to to_currency
    $stmt = $pdo->query("
        SELECT from_currency, to_currency, 
               SUM(from_amount) as total_from, 
               SUM(to_amount) as total_to
        FROM currency_exchanges
        GROUP BY from_currency, to_currency
    ");
    $exchanges = $stmt->fetchAll();
    
    foreach ($exchanges as $ex) {
        $fromCur = $ex['from_currency'];
        $toCur = $ex['to_currency'];
        $fromAmount = (float)$ex['total_from'];
        $toAmount = (float)$ex['total_to'];
        
        // Initialize currency entries if not exists
        if (!isset($allTimeByCurrency[$fromCur])) {
            $allTimeByCurrency[$fromCur] = ['income' => 0, 'expense' => 0, 'balance' => 0];
        }
        if (!isset($allTimeByCurrency[$toCur])) {
            $allTimeByCurrency[$toCur] = ['income' => 0, 'expense' => 0, 'balance' => 0];
        }
        
        // Subtract from source currency, add to target currency
        $allTimeByCurrency[$fromCur]['balance'] -= $fromAmount;
        $allTimeByCurrency[$toCur]['balance'] += $toAmount;
    }
    
    // Recalculate total balance including exchanges (convert each currency to base)
    $allTimeTotalBalance = 0;
    foreach ($allTimeByCurrency as $cur => $data) {
        $rate = $currencies[$cur]['rate'] ?? 1;
        $allTimeTotalBalance += $data['balance'] * $rate;
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'period_by_currency' => $periodByCurrency,
            'period_total' => [
                'income' => round($periodTotalIncome, 2),
                'expense' => round($periodTotalExpense, 2),
                'balance' => round($periodTotalIncome - $periodTotalExpense, 2),
                'currency' => $baseCurrency
            ],
            'transaction_count' => (int)$count,
            'all_time_by_currency' => $allTimeByCurrency,
            'all_time_total' => [
                'income' => round($allTimeTotalIncome, 2),
                'expense' => round($allTimeTotalExpense, 2),
                'balance' => round($allTimeTotalBalance, 2),
                'currency' => $baseCurrency
            ],
            'currencies' => $currencies,
            'base_currency' => $baseCurrency,
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


