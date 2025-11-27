<?php
/**
 * Members API
 * Handles CRUD operations for family members
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
            getMembers();
            break;
        case 'POST':
            createMember();
            break;
        case 'PUT':
            updateMember();
            break;
        case 'DELETE':
            deleteMember();
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function getMembers() {
    $pdo = db();
    $stmt = $pdo->query("SELECT * FROM members ORDER BY name");
    $members = $stmt->fetchAll();
    echo json_encode(['success' => true, 'data' => $members]);
}

function createMember() {
    $pdo = db();
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['name'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Name is required']);
        return;
    }
    
    $stmt = $pdo->prepare("INSERT INTO members (name, avatar) VALUES (?, ?)");
    $stmt->execute([
        $data['name'],
        $data['avatar'] ?? '👤'
    ]);
    
    $id = $pdo->lastInsertId();
    
    $stmt = $pdo->prepare("SELECT * FROM members WHERE id = ?");
    $stmt->execute([$id]);
    $member = $stmt->fetch();
    
    echo json_encode(['success' => true, 'data' => $member]);
}

function updateMember() {
    $pdo = db();
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing member ID']);
        return;
    }
    
    $stmt = $pdo->prepare("UPDATE members SET name = ?, avatar = ? WHERE id = ?");
    $stmt->execute([
        $data['name'],
        $data['avatar'] ?? '👤',
        $data['id']
    ]);
    
    $stmt = $pdo->prepare("SELECT * FROM members WHERE id = ?");
    $stmt->execute([$data['id']]);
    $member = $stmt->fetch();
    
    echo json_encode(['success' => true, 'data' => $member]);
}

function deleteMember() {
    $pdo = db();
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing member ID']);
        return;
    }
    
    $stmt = $pdo->prepare("DELETE FROM members WHERE id = ?");
    $stmt->execute([$data['id']]);
    
    echo json_encode(['success' => true, 'message' => 'Member deleted']);
}

