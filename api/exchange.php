<?php
/**
 * Exchange Rates & Currency Exchange API
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../database.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $action = $_GET['action'] ?? 'rates';
    
    switch ($action) {
        case 'rates':
            handleRates($method);
            break;
        case 'exchange':
            handleExchange($method);
            break;
        case 'history':
            getExchangeHistory();
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function handleRates($method) {
    switch ($method) {
        case 'GET':
            getRates();
            break;
        case 'PUT':
            updateRate();
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

function getRates() {
    $pdo = db();
    
    $stmt = $pdo->query("SELECT currency, rate, updated_at FROM exchange_rates ORDER BY currency");
    $rates = $stmt->fetchAll();
    
    // Convert to object format
    $ratesObj = [];
    foreach ($rates as $rate) {
        $ratesObj[$rate['currency']] = [
            'rate' => (float)$rate['rate'],
            'updated_at' => $rate['updated_at']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $ratesObj
    ]);
}

function updateRate() {
    $pdo = db();
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['currency']) || !isset($data['rate'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Currency and rate are required']);
        return;
    }
    
    $currency = strtoupper($data['currency']);
    $rate = (float)$data['rate'];
    
    if ($rate <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Rate must be positive']);
        return;
    }
    
    // Update or insert
    $stmt = $pdo->prepare("
        INSERT INTO exchange_rates (currency, rate, updated_at) 
        VALUES (?, ?, CURRENT_TIMESTAMP)
        ON CONFLICT(currency) DO UPDATE SET rate = ?, updated_at = CURRENT_TIMESTAMP
    ");
    $stmt->execute([$currency, $rate, $rate]);
    
    echo json_encode([
        'success' => true,
        'message' => "Rate updated: 1 $currency = $rate CNY"
    ]);
}

function handleExchange($method) {
    switch ($method) {
        case 'POST':
            createExchange();
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

function createExchange() {
    $pdo = db();
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required = ['from_currency', 'to_currency', 'from_amount', 'exchange_date'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Missing required field: $field"]);
            return;
        }
    }
    
    $fromCurrency = strtoupper($data['from_currency']);
    $toCurrency = strtoupper($data['to_currency']);
    $fromAmount = (float)$data['from_amount'];
    
    if ($fromCurrency === $toCurrency) {
        http_response_code(400);
        echo json_encode(['error' => 'Cannot exchange same currency']);
        return;
    }
    
    // Get current rates
    $stmt = $pdo->prepare("SELECT rate FROM exchange_rates WHERE currency = ?");
    
    $stmt->execute([$fromCurrency]);
    $fromRate = $stmt->fetch();
    if (!$fromRate) {
        http_response_code(400);
        echo json_encode(['error' => "Unknown currency: $fromCurrency"]);
        return;
    }
    
    $stmt->execute([$toCurrency]);
    $toRate = $stmt->fetch();
    if (!$toRate) {
        http_response_code(400);
        echo json_encode(['error' => "Unknown currency: $toCurrency"]);
        return;
    }
    
    // Calculate exchange
    // First convert to CNY, then to target currency
    $cnyAmount = $fromAmount * (float)$fromRate['rate'];
    $toAmount = $cnyAmount / (float)$toRate['rate'];
    $exchangeRate = (float)$fromRate['rate'] / (float)$toRate['rate'];
    
    // Allow manual override of to_amount
    if (!empty($data['to_amount'])) {
        $toAmount = (float)$data['to_amount'];
        $exchangeRate = $toAmount / $fromAmount;
    }
    
    // Insert exchange record
    $stmt = $pdo->prepare("
        INSERT INTO currency_exchanges 
        (from_currency, to_currency, from_amount, to_amount, exchange_rate, exchange_date, member, description)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $fromCurrency,
        $toCurrency,
        $fromAmount,
        round($toAmount, 2),
        $exchangeRate,
        $data['exchange_date'],
        $data['member'] ?? null,
        $data['description'] ?? "Exchange $fromCurrency to $toCurrency"
    ]);
    
    $id = $pdo->lastInsertId();
    
    // Return the created exchange
    $stmt = $pdo->prepare("SELECT * FROM currency_exchanges WHERE id = ?");
    $stmt->execute([$id]);
    $exchange = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'data' => $exchange,
        'message' => sprintf(
            "Exchanged %.2f %s → %.2f %s (Rate: %.4f)",
            $fromAmount, $fromCurrency,
            $toAmount, $toCurrency,
            $exchangeRate
        )
    ]);
}

function getExchangeHistory() {
    $pdo = db();
    
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    
    $stmt = $pdo->prepare("
        SELECT * FROM currency_exchanges 
        ORDER BY exchange_date DESC, created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    $exchanges = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => $exchanges
    ]);
}

