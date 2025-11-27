<?php
/**
 * Categories API
 * Handles CRUD operations for categories
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../database.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    switch ($method) {
        case 'GET':
            getCategories();
            break;
        case 'POST':
            createCategory();
            break;
        case 'PUT':
            updateCategory();
            break;
        case 'DELETE':
            deleteCategory();
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function getCategories() {
    $pdo = db();
    
    $type = $_GET['type'] ?? null;
    
    if ($type) {
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE type = ? ORDER BY name");
        $stmt->execute([$type]);
    } else {
        $stmt = $pdo->query("SELECT * FROM categories ORDER BY type, name");
    }
    
    $categories = $stmt->fetchAll();
    echo json_encode(['success' => true, 'data' => $categories]);
}

function createCategory() {
    $pdo = db();
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['name']) || empty($data['type'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Name and type are required']);
        return;
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO categories (name, type, icon, color)
        VALUES (?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $data['name'],
        $data['type'],
        $data['icon'] ?? '📁',
        $data['color'] ?? '#6366f1'
    ]);
    
    $id = $pdo->lastInsertId();
    
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    $category = $stmt->fetch();
    
    echo json_encode(['success' => true, 'data' => $category]);
}

function updateCategory() {
    $pdo = db();
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing category ID']);
        return;
    }
    
    $stmt = $pdo->prepare("
        UPDATE categories 
        SET name = ?, type = ?, icon = ?, color = ?
        WHERE id = ?
    ");
    
    $stmt->execute([
        $data['name'],
        $data['type'],
        $data['icon'] ?? '📁',
        $data['color'] ?? '#6366f1',
        $data['id']
    ]);
    
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$data['id']]);
    $category = $stmt->fetch();
    
    echo json_encode(['success' => true, 'data' => $category]);
}

function deleteCategory() {
    $pdo = db();
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing category ID']);
        return;
    }
    
    // Check if category has transactions
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM transactions WHERE category_id = ?");
    $stmt->execute([$data['id']]);
    $result = $stmt->fetch();
    
    if ($result['count'] > 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Cannot delete category with existing transactions']);
        return;
    }
    
    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
    $stmt->execute([$data['id']]);
    
    echo json_encode(['success' => true, 'message' => 'Category deleted']);
}

