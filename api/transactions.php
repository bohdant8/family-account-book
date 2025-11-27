<?php
/**
 * Transactions API
 * Handles CRUD operations for transactions
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../database.php';

$method = $_SERVER['REQUEST_METHOD'];

// Handle preflight requests
if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                getTransaction($_GET['id']);
            } else {
                getTransactions();
            }
            break;
        case 'POST':
            createTransaction();
            break;
        case 'PUT':
            updateTransaction();
            break;
        case 'DELETE':
            deleteTransaction();
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function getTransactions() {
    $pdo = db();
    
    // Build query with filters
    $where = [];
    $params = [];
    
    if (!empty($_GET['start_date'])) {
        $where[] = "t.transaction_date >= ?";
        $params[] = $_GET['start_date'];
    }
    
    if (!empty($_GET['end_date'])) {
        $where[] = "t.transaction_date <= ?";
        $params[] = $_GET['end_date'];
    }
    
    if (!empty($_GET['type'])) {
        $where[] = "c.type = ?";
        $params[] = $_GET['type'];
    }
    
    if (!empty($_GET['category_id'])) {
        $where[] = "t.category_id = ?";
        $params[] = $_GET['category_id'];
    }
    
    if (!empty($_GET['member'])) {
        $where[] = "t.member = ?";
        $params[] = $_GET['member'];
    }
    
    $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $sql = "
        SELECT t.*, c.name as category_name, c.type, c.icon, c.color
        FROM transactions t
        JOIN categories c ON t.category_id = c.id
        $whereClause
        ORDER BY t.transaction_date DESC, t.created_at DESC
    ";
    
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    
    $sql .= " LIMIT $limit OFFSET $offset";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'data' => $transactions]);
}

function getTransaction($id) {
    $pdo = db();
    
    $stmt = $pdo->prepare("
        SELECT t.*, c.name as category_name, c.type, c.icon, c.color
        FROM transactions t
        JOIN categories c ON t.category_id = c.id
        WHERE t.id = ?
    ");
    $stmt->execute([$id]);
    $transaction = $stmt->fetch();
    
    if ($transaction) {
        echo json_encode(['success' => true, 'data' => $transaction]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Transaction not found']);
    }
}

function createTransaction() {
    $pdo = db();
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required = ['category_id', 'amount', 'transaction_date'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Missing required field: $field"]);
            return;
        }
    }
    
    // Default currency to CNY if not provided
    $currency = $data['currency'] ?? 'CNY';
    
    $stmt = $pdo->prepare("
        INSERT INTO transactions (category_id, amount, currency, description, transaction_date, member)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $data['category_id'],
        $data['amount'],
        $currency,
        $data['description'] ?? '',
        $data['transaction_date'],
        $data['member'] ?? null
    ]);
    
    $id = $pdo->lastInsertId();
    
    // Return the created transaction
    $stmt = $pdo->prepare("
        SELECT t.*, c.name as category_name, c.type, c.icon, c.color
        FROM transactions t
        JOIN categories c ON t.category_id = c.id
        WHERE t.id = ?
    ");
    $stmt->execute([$id]);
    $transaction = $stmt->fetch();
    
    echo json_encode(['success' => true, 'data' => $transaction]);
}

function updateTransaction() {
    $pdo = db();
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing transaction ID']);
        return;
    }
    
    // Default currency to CNY if not provided
    $currency = $data['currency'] ?? 'CNY';
    
    $stmt = $pdo->prepare("
        UPDATE transactions 
        SET category_id = ?, amount = ?, currency = ?, description = ?, transaction_date = ?, member = ?, updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    
    $stmt->execute([
        $data['category_id'],
        $data['amount'],
        $currency,
        $data['description'] ?? '',
        $data['transaction_date'],
        $data['member'] ?? null,
        $data['id']
    ]);
    
    // Return the updated transaction
    $stmt = $pdo->prepare("
        SELECT t.*, c.name as category_name, c.type, c.icon, c.color
        FROM transactions t
        JOIN categories c ON t.category_id = c.id
        WHERE t.id = ?
    ");
    $stmt->execute([$data['id']]);
    $transaction = $stmt->fetch();
    
    echo json_encode(['success' => true, 'data' => $transaction]);
}

function deleteTransaction() {
    $pdo = db();
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing transaction ID']);
        return;
    }
    
    $stmt = $pdo->prepare("DELETE FROM transactions WHERE id = ?");
    $stmt->execute([$data['id']]);
    
    echo json_encode(['success' => true, 'message' => 'Transaction deleted']);
}

