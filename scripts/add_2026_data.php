<?php
/**
 * Add 2026 Jan - Feb dummy transactions to the database
 * Run: php add_2026_data.php (from accountbook directory)
 */

require_once __DIR__ . '/../database.php';

$pdo = db();

// 2026 January transactions - Format: [category_id, amount, description, date, member, currency]
$jan2026 = [
    [1, 2500.00, 'Monthly salary', '2026-01-05', 'Dad', 'GBP'],
    [1, 1800.00, 'Monthly salary', '2026-01-05', 'Mom', 'GBP'],
    [6, 12.50, 'Breakfast at cafe', '2026-01-02', 'Dad', 'GBP'],
    [6, 45.00, 'Family dinner', '2026-01-06', 'Mom', 'GBP'],
    [6, 8.50, 'School lunch', '2026-01-07', 'Child', 'GBP'],
    [6, 85.00, 'Grocery shopping', '2026-01-10', 'Mom', 'GBP'],
    [6, 32.00, 'Weekend brunch', '2026-01-14', 'Dad', 'GBP'],
    [7, 120.00, 'Monthly travel card', '2026-01-01', 'Dad', 'GBP'],
    [7, 95.00, 'Monthly travel card', '2026-01-01', 'Mom', 'GBP'],
    [8, 89.99, 'New shoes', '2026-01-08', 'Child', 'GBP'],
    [8, 125.00, 'Winter coat', '2026-01-11', 'Mom', 'GBP'],
    [9, 65.00, 'Electricity bill', '2026-01-10', 'Dad', 'GBP'],
    [9, 35.00, 'Water bill', '2026-01-10', 'Dad', 'GBP'],
    [10, 1200.00, 'Monthly rent', '2026-01-01', 'Dad', 'GBP'],
    [11, 45.00, 'Doctor visit', '2026-01-09', 'Child', 'GBP'],
    [12, 450.00, 'Tuition fee', '2026-01-03', 'Child', 'GBP'],
    [13, 28.00, 'Movie tickets', '2026-01-13', 'Dad', 'GBP'],
    [2, 500.00, 'Project bonus', '2026-01-15', 'Dad', 'GBP'],
    [4, 350.00, 'Freelance work', '2026-01-18', 'Mom', 'GBP'],
    [13, 15.99, 'Netflix subscription', '2026-01-01', 'Dad', 'USD'],
    [8, 49.99, 'Amazon purchase', '2026-01-10', 'Mom', 'USD'],
    [6, 42.00, 'Restaurant dinner', '2026-01-20', 'Dad', 'EUR'],
];

// 2026 February transactions
$feb2026 = [
    [1, 2500.00, 'Monthly salary', '2026-02-05', 'Dad', 'GBP'],
    [1, 1800.00, 'Monthly salary', '2026-02-05', 'Mom', 'GBP'],
    [6, 15.00, 'Breakfast at cafe', '2026-02-02', 'Dad', 'GBP'],
    [6, 52.00, 'Family dinner', '2026-02-06', 'Mom', 'GBP'],
    [6, 9.00, 'School lunch', '2026-02-09', 'Child', 'GBP'],
    [6, 92.00, 'Grocery shopping', '2026-02-10', 'Mom', 'GBP'],
    [6, 28.00, 'Lunch out', '2026-02-14', 'Dad', 'GBP'],
    [7, 120.00, 'Monthly travel card', '2026-02-01', 'Dad', 'GBP'],
    [7, 95.00, 'Monthly travel card', '2026-02-01', 'Mom', 'GBP'],
    [8, 35.00, 'Books', '2026-02-08', 'Child', 'GBP'],
    [9, 58.00, 'Electricity bill', '2026-02-10', 'Dad', 'GBP'],
    [9, 32.00, 'Gas bill', '2026-02-10', 'Dad', 'GBP'],
    [10, 1200.00, 'Monthly rent', '2026-02-01', 'Dad', 'GBP'],
    [12, 450.00, 'Tuition fee', '2026-02-03', 'Child', 'GBP'],
    [13, 35.00, 'Theatre tickets', '2026-02-15', 'Mom', 'GBP'],
    [2, 800.00, 'Performance bonus', '2026-02-20', 'Dad', 'GBP'],
    [3, 150.00, 'Investment dividend', '2026-02-25', 'Dad', 'GBP'],
    [14, 320.00, 'Weekend trip', '2026-02-22', 'Mom', 'GBP'],
    [13, 15.99, 'Netflix subscription', '2026-02-01', 'Dad', 'USD'],
    [12, 29.99, 'Online course', '2026-02-15', 'Child', 'USD'],
    [6, 38.00, 'Valentine dinner', '2026-02-14', 'Dad', 'EUR'],
];

$stmt = $pdo->prepare("
    INSERT INTO transactions (category_id, amount, description, transaction_date, member, currency)
    VALUES (?, ?, ?, ?, ?, ?)
");

$count = 0;
foreach (array_merge($jan2026, $feb2026) as $tx) {
    $stmt->execute($tx);
    $count++;
}

echo "Added $count transactions for 2026 Jan - Feb.\n";
