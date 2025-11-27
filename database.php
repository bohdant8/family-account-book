<?php
/**
 * Database Connection and Setup
 */

require_once __DIR__ . '/config.php';

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        // Ensure data directory exists
        $dataDir = dirname(DB_PATH);
        if (!file_exists($dataDir)) {
            mkdir($dataDir, 0755, true);
        }

        try {
            $this->pdo = new PDO('sqlite:' . DB_PATH);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->initializeSchema();
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->pdo;
    }

    private function initializeSchema() {
        // Create categories table
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(100) NOT NULL,
                type VARCHAR(20) NOT NULL CHECK(type IN ('income', 'expense')),
                icon VARCHAR(50) DEFAULT '📁',
                color VARCHAR(20) DEFAULT '#6366f1',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Create transactions table
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS transactions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                category_id INTEGER NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                currency VARCHAR(3) DEFAULT 'CNY',
                description TEXT,
                transaction_date DATE NOT NULL,
                member VARCHAR(100),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (category_id) REFERENCES categories(id)
            )
        ");
        
        // Add currency column if it doesn't exist (migration for existing databases)
        try {
            $this->pdo->exec("ALTER TABLE transactions ADD COLUMN currency VARCHAR(3) DEFAULT 'CNY'");
        } catch (PDOException $e) {
            // Column already exists, ignore
        }

        // Create family members table
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS members (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(100) NOT NULL,
                avatar VARCHAR(10) DEFAULT '👤',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Create exchange rates table (for dynamic rates)
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS exchange_rates (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                currency VARCHAR(3) NOT NULL UNIQUE,
                rate DECIMAL(10,6) NOT NULL,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Create currency exchanges table (for exchange transactions)
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS currency_exchanges (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                from_currency VARCHAR(3) NOT NULL,
                to_currency VARCHAR(3) NOT NULL,
                from_amount DECIMAL(10,2) NOT NULL,
                to_amount DECIMAL(10,2) NOT NULL,
                exchange_rate DECIMAL(10,6) NOT NULL,
                exchange_date DATE NOT NULL,
                member VARCHAR(100),
                description TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Initialize default exchange rates if empty
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM exchange_rates");
        $result = $stmt->fetch();
        
        if ($result['count'] == 0) {
            $defaultRates = [
                ['CNY', 1.0],
                ['JPY', 0.052],
                ['USD', 7.25],
            ];
            $stmt = $this->pdo->prepare("INSERT INTO exchange_rates (currency, rate) VALUES (?, ?)");
            foreach ($defaultRates as $rate) {
                $stmt->execute($rate);
            }
        }

        // Insert default categories if empty
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM categories");
        $result = $stmt->fetch();
        
        if ($result['count'] == 0) {
            $defaultCategories = [
                // Income categories
                ['Salary', 'income', '💰', '#10b981'],
                ['Bonus', 'income', '🎁', '#06b6d4'],
                ['Investment', 'income', '📈', '#8b5cf6'],
                ['Side Income', 'income', '💼', '#f59e0b'],
                ['Other Income', 'income', '✨', '#ec4899'],
                
                // Expense categories
                ['Food & Dining', 'expense', '🍜', '#ef4444'],
                ['Transportation', 'expense', '🚗', '#f97316'],
                ['Shopping', 'expense', '🛒', '#eab308'],
                ['Utilities', 'expense', '💡', '#84cc16'],
                ['Housing', 'expense', '🏠', '#22c55e'],
                ['Healthcare', 'expense', '🏥', '#14b8a6'],
                ['Education', 'expense', '📚', '#0ea5e9'],
                ['Entertainment', 'expense', '🎬', '#6366f1'],
                ['Travel', 'expense', '✈️', '#a855f7'],
                ['Other Expense', 'expense', '📦', '#64748b'],
            ];

            $stmt = $this->pdo->prepare("INSERT INTO categories (name, type, icon, color) VALUES (?, ?, ?, ?)");
            foreach ($defaultCategories as $cat) {
                $stmt->execute($cat);
            }
        }

        // Insert default family members if empty
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM members");
        $result = $stmt->fetch();
        
        if ($result['count'] == 0) {
            $defaultMembers = [
                ['Dad', '👨'],
                ['Mom', '👩'],
                ['Child', '👦'],
            ];

            $stmt = $this->pdo->prepare("INSERT INTO members (name, avatar) VALUES (?, ?)");
            foreach ($defaultMembers as $member) {
                $stmt->execute($member);
            }
        }

        // Insert dummy transactions if empty
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM transactions");
        $result = $stmt->fetch();
        
        if ($result['count'] == 0) {
            $this->insertDummyData();
        }
    }

    private function insertDummyData() {
        // Get current date info
        $currentYear = date('Y');
        $currentMonth = date('m');
        
        // Dummy transactions for the past 3 months
        // Format: [category_id, amount, description, date, member, currency]
        $dummyTransactions = [
            // This month - CNY transactions (main currency)
            [1, 15000.00, 'Monthly salary', $currentYear . '-' . $currentMonth . '-05', 'Dad', 'CNY'],
            [1, 8000.00, 'Monthly salary', $currentYear . '-' . $currentMonth . '-05', 'Mom', 'CNY'],
            [6, 45.50, 'Breakfast at cafe', $currentYear . '-' . $currentMonth . '-02', 'Dad', 'CNY'],
            [6, 128.00, 'Family dinner', $currentYear . '-' . $currentMonth . '-06', 'Mom', 'CNY'],
            [6, 35.00, 'Lunch at school', $currentYear . '-' . $currentMonth . '-07', 'Child', 'CNY'],
            [6, 256.80, 'Grocery shopping', $currentYear . '-' . $currentMonth . '-10', 'Mom', 'CNY'],
            [6, 89.00, 'Weekend brunch', $currentYear . '-' . $currentMonth . '-14', 'Dad', 'CNY'],
            [7, 500.00, 'Monthly metro card', $currentYear . '-' . $currentMonth . '-01', 'Dad', 'CNY'],
            [7, 300.00, 'Monthly metro card', $currentYear . '-' . $currentMonth . '-01', 'Mom', 'CNY'],
            [8, 299.00, 'New shoes', $currentYear . '-' . $currentMonth . '-08', 'Child', 'CNY'],
            [8, 450.00, 'Winter jacket', $currentYear . '-' . $currentMonth . '-11', 'Mom', 'CNY'],
            [9, 280.00, 'Electricity bill', $currentYear . '-' . $currentMonth . '-10', 'Dad', 'CNY'],
            [9, 85.00, 'Water bill', $currentYear . '-' . $currentMonth . '-10', 'Dad', 'CNY'],
            [10, 5500.00, 'Monthly rent', $currentYear . '-' . $currentMonth . '-01', 'Dad', 'CNY'],
            [11, 120.00, 'Doctor visit', $currentYear . '-' . $currentMonth . '-09', 'Child', 'CNY'],
            [12, 2500.00, 'Tuition fee', $currentYear . '-' . $currentMonth . '-03', 'Child', 'CNY'],
            [13, 180.00, 'Movie tickets', $currentYear . '-' . $currentMonth . '-13', 'Dad', 'CNY'],
            [2, 3000.00, 'Project bonus', $currentYear . '-' . $currentMonth . '-15', 'Dad', 'CNY'],
            [4, 800.00, 'Freelance work', $currentYear . '-' . $currentMonth . '-18', 'Mom', 'CNY'],
            
            // JPY transactions (Japanese purchases)
            [8, 5980.00, 'Japanese skincare', $currentYear . '-' . $currentMonth . '-12', 'Mom', 'JPY'],
            [6, 1200.00, 'Ramen lunch', $currentYear . '-' . $currentMonth . '-12', 'Dad', 'JPY'],
            [8, 3500.00, 'Anime merchandise', $currentYear . '-' . $currentMonth . '-14', 'Child', 'JPY'],
            [13, 8800.00, 'Concert ticket Japan', $currentYear . '-' . $currentMonth . '-18', 'Mom', 'JPY'],
            
            // USD transactions (international)
            [13, 15.99, 'Netflix subscription', $currentYear . '-' . $currentMonth . '-01', 'Dad', 'USD'],
            [8, 49.99, 'Amazon purchase', $currentYear . '-' . $currentMonth . '-10', 'Mom', 'USD'],
            [12, 29.99, 'Online course', $currentYear . '-' . $currentMonth . '-15', 'Child', 'USD'],
            [4, 150.00, 'Freelance payment', $currentYear . '-' . $currentMonth . '-20', 'Dad', 'USD'],
        ];
        
        // Add transactions from last month
        $lastMonth = $currentMonth == '01' ? '12' : str_pad($currentMonth - 1, 2, '0', STR_PAD_LEFT);
        $lastMonthYear = $currentMonth == '01' ? $currentYear - 1 : $currentYear;
        
        $lastMonthTransactions = [
            [1, 15000.00, 'Monthly salary', $lastMonthYear . '-' . $lastMonth . '-05', 'Dad', 'CNY'],
            [1, 8000.00, 'Monthly salary', $lastMonthYear . '-' . $lastMonth . '-05', 'Mom', 'CNY'],
            [10, 5500.00, 'Monthly rent', $lastMonthYear . '-' . $lastMonth . '-01', 'Dad', 'CNY'],
            [6, 890.00, 'Grocery shopping', $lastMonthYear . '-' . $lastMonth . '-08', 'Mom', 'CNY'],
            [7, 500.00, 'Monthly metro card', $lastMonthYear . '-' . $lastMonth . '-01', 'Dad', 'CNY'],
            [9, 650.00, 'Utility bills', $lastMonthYear . '-' . $lastMonth . '-10', 'Dad', 'CNY'],
            [12, 2500.00, 'Tuition fee', $lastMonthYear . '-' . $lastMonth . '-03', 'Child', 'CNY'],
            [3, 1500.00, 'Stock dividend', $lastMonthYear . '-' . $lastMonth . '-25', 'Dad', 'CNY'],
            [14, 2800.00, 'Weekend trip', $lastMonthYear . '-' . $lastMonth . '-22', 'Mom', 'CNY'],
            // USD last month
            [13, 15.99, 'Netflix subscription', $lastMonthYear . '-' . $lastMonth . '-01', 'Dad', 'USD'],
            [4, 200.00, 'Consulting fee', $lastMonthYear . '-' . $lastMonth . '-15', 'Dad', 'USD'],
        ];
        
        // Add transactions from 2 months ago
        $twoMonthsAgo = $currentMonth <= '02' ? str_pad(12 + $currentMonth - 2, 2, '0', STR_PAD_LEFT) : str_pad($currentMonth - 2, 2, '0', STR_PAD_LEFT);
        $twoMonthsAgoYear = $currentMonth <= '02' ? $currentYear - 1 : $currentYear;
        
        $twoMonthsAgoTransactions = [
            [1, 15000.00, 'Monthly salary', $twoMonthsAgoYear . '-' . $twoMonthsAgo . '-05', 'Dad', 'CNY'],
            [1, 8000.00, 'Monthly salary', $twoMonthsAgoYear . '-' . $twoMonthsAgo . '-05', 'Mom', 'CNY'],
            [10, 5500.00, 'Monthly rent', $twoMonthsAgoYear . '-' . $twoMonthsAgo . '-01', 'Dad', 'CNY'],
            [6, 720.00, 'Grocery shopping', $twoMonthsAgoYear . '-' . $twoMonthsAgo . '-10', 'Mom', 'CNY'],
            [7, 800.00, 'Transportation', $twoMonthsAgoYear . '-' . $twoMonthsAgo . '-01', 'Dad', 'CNY'],
            [12, 2500.00, 'Tuition fee', $twoMonthsAgoYear . '-' . $twoMonthsAgo . '-03', 'Child', 'CNY'],
            [2, 5000.00, 'Year-end bonus', $twoMonthsAgoYear . '-' . $twoMonthsAgo . '-28', 'Mom', 'CNY'],
            // JPY 2 months ago
            [14, 85000.00, 'Japan trip expenses', $twoMonthsAgoYear . '-' . $twoMonthsAgo . '-20', 'Dad', 'JPY'],
        ];
        
        // Merge all transactions
        $allTransactions = array_merge($dummyTransactions, $lastMonthTransactions, $twoMonthsAgoTransactions);
        
        // Insert all transactions
        $stmt = $this->pdo->prepare("
            INSERT INTO transactions (category_id, amount, description, transaction_date, member, currency)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($allTransactions as $transaction) {
            $stmt->execute($transaction);
        }
    }
}

// Helper function to get database connection
function db() {
    return Database::getInstance()->getConnection();
}

