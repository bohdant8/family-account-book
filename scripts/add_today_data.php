<?php
/**
 * Add today's data to the database
 * Run: php scripts/add_today_data.php (from accountbook directory)
 */

require_once __DIR__ . '/../database.php';

$pdo = db();

// Today's date: 2026-02-12 (Thursday)
$today = '2026-02-12';

// Check if data already exists for today
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM transactions WHERE transaction_date = ?");
$stmt->execute([$today]);
$existingCount = $stmt->fetch()['count'];

echo "Checking transactions for $today...\n";
echo "Found $existingCount existing transactions.\n\n";

if ($existingCount > 0) {
    echo "Transactions already exist for today. Showing existing:\n";
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE transaction_date = ? ORDER BY id");
    $stmt->execute([$today]);
    $existing = $stmt->fetchAll();
    foreach ($existing as $tx) {
        echo sprintf("  - %s: %s %s (%s) - %s\n", 
            $tx['member'], 
            $tx['amount'], 
            $tx['currency'],
            $tx['description'],
            $tx['transaction_date']
        );
    }
    echo "\n";
}

// Today's transactions - Format: [category_id, amount, description, date, member, currency]
// Adding some typical daily transactions for a Thursday
$todayTransactions = [
    [6, 12.50, 'Morning coffee', $today, 'Dad', 'GBP'],
    [6, 8.50, 'School lunch', $today, 'Child', 'GBP'],
    [6, 15.00, 'Lunch break', $today, 'Mom', 'GBP'],
    [7, 5.50, 'Bus fare', $today, 'Dad', 'GBP'],
    [7, 3.20, 'Train ticket', $today, 'Mom', 'GBP'],
    [6, 28.00, 'Dinner out', $today, 'Dad', 'GBP'],
    [8, 24.99, 'Online purchase', $today, 'Mom', 'GBP'],
];

$stmt = $pdo->prepare("
    INSERT INTO transactions (category_id, amount, description, transaction_date, member, currency)
    VALUES (?, ?, ?, ?, ?, ?)
");

$added = 0;
foreach ($todayTransactions as $tx) {
    try {
        $stmt->execute($tx);
        $added++;
        echo "Added: {$tx[3]} - {$tx[2]} ({$tx[4]}) - {$tx[1]} {$tx[5]}\n";
    } catch (PDOException $e) {
        // Skip if duplicate or error
        echo "Skipped: {$tx[3]} - {$tx[2]} (Error: " . $e->getMessage() . ")\n";
    }
}

echo "\nAdded $added new transactions for $today.\n";
