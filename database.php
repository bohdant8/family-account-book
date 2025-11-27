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
                description TEXT,
                transaction_date DATE NOT NULL,
                member VARCHAR(100),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (category_id) REFERENCES categories(id)
            )
        ");

        // Create family members table
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS members (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(100) NOT NULL,
                avatar VARCHAR(10) DEFAULT '👤',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

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
        $dummyTransactions = [
            // This month - various expenses and income
            // Salary (category_id: 1)
            [1, 15000.00, 'Monthly salary', $currentYear . '-' . $currentMonth . '-05', 'Dad'],
            [1, 8000.00, 'Monthly salary', $currentYear . '-' . $currentMonth . '-05', 'Mom'],
            
            // Food & Dining (category_id: 6)
            [6, 45.50, 'Breakfast at cafe', $currentYear . '-' . $currentMonth . '-02', 'Dad'],
            [6, 128.00, 'Family dinner', $currentYear . '-' . $currentMonth . '-06', 'Mom'],
            [6, 35.00, 'Lunch at school', $currentYear . '-' . $currentMonth . '-07', 'Child'],
            [6, 256.80, 'Grocery shopping', $currentYear . '-' . $currentMonth . '-10', 'Mom'],
            [6, 89.00, 'Weekend brunch', $currentYear . '-' . $currentMonth . '-14', 'Dad'],
            [6, 178.50, 'Grocery shopping', $currentYear . '-' . $currentMonth . '-18', 'Mom'],
            [6, 65.00, 'Snacks and drinks', $currentYear . '-' . $currentMonth . '-20', 'Child'],
            [6, 320.00, 'Restaurant dinner', $currentYear . '-' . $currentMonth . '-22', 'Dad'],
            
            // Transportation (category_id: 7)
            [7, 500.00, 'Monthly metro card', $currentYear . '-' . $currentMonth . '-01', 'Dad'],
            [7, 300.00, 'Monthly metro card', $currentYear . '-' . $currentMonth . '-01', 'Mom'],
            [7, 150.00, 'Gas refuel', $currentYear . '-' . $currentMonth . '-12', 'Dad'],
            [7, 35.00, 'Taxi to airport', $currentYear . '-' . $currentMonth . '-15', 'Mom'],
            
            // Shopping (category_id: 8)
            [8, 299.00, 'New shoes', $currentYear . '-' . $currentMonth . '-08', 'Child'],
            [8, 450.00, 'Winter jacket', $currentYear . '-' . $currentMonth . '-11', 'Mom'],
            [8, 89.00, 'Books', $currentYear . '-' . $currentMonth . '-16', 'Child'],
            
            // Utilities (category_id: 9)
            [9, 280.00, 'Electricity bill', $currentYear . '-' . $currentMonth . '-10', 'Dad'],
            [9, 85.00, 'Water bill', $currentYear . '-' . $currentMonth . '-10', 'Dad'],
            [9, 150.00, 'Internet bill', $currentYear . '-' . $currentMonth . '-12', 'Dad'],
            [9, 180.00, 'Phone bills', $currentYear . '-' . $currentMonth . '-12', 'Mom'],
            
            // Housing (category_id: 10)
            [10, 5500.00, 'Monthly rent', $currentYear . '-' . $currentMonth . '-01', 'Dad'],
            
            // Healthcare (category_id: 11)
            [11, 120.00, 'Doctor visit', $currentYear . '-' . $currentMonth . '-09', 'Child'],
            [11, 85.00, 'Pharmacy', $currentYear . '-' . $currentMonth . '-09', 'Mom'],
            
            // Education (category_id: 12)
            [12, 2500.00, 'Tuition fee', $currentYear . '-' . $currentMonth . '-03', 'Child'],
            [12, 350.00, 'Piano lessons', $currentYear . '-' . $currentMonth . '-15', 'Child'],
            
            // Entertainment (category_id: 13)
            [13, 180.00, 'Movie tickets', $currentYear . '-' . $currentMonth . '-13', 'Dad'],
            [13, 99.00, 'Streaming subscription', $currentYear . '-' . $currentMonth . '-01', 'Dad'],
            [13, 250.00, 'Concert tickets', $currentYear . '-' . $currentMonth . '-20', 'Mom'],
            
            // Bonus income (category_id: 2)
            [2, 3000.00, 'Project bonus', $currentYear . '-' . $currentMonth . '-15', 'Dad'],
            
            // Side Income (category_id: 4)
            [4, 800.00, 'Freelance work', $currentYear . '-' . $currentMonth . '-18', 'Mom'],
        ];
        
        // Add transactions from last month
        $lastMonth = $currentMonth == '01' ? '12' : str_pad($currentMonth - 1, 2, '0', STR_PAD_LEFT);
        $lastMonthYear = $currentMonth == '01' ? $currentYear - 1 : $currentYear;
        
        $lastMonthTransactions = [
            [1, 15000.00, 'Monthly salary', $lastMonthYear . '-' . $lastMonth . '-05', 'Dad'],
            [1, 8000.00, 'Monthly salary', $lastMonthYear . '-' . $lastMonth . '-05', 'Mom'],
            [10, 5500.00, 'Monthly rent', $lastMonthYear . '-' . $lastMonth . '-01', 'Dad'],
            [6, 890.00, 'Grocery shopping', $lastMonthYear . '-' . $lastMonth . '-08', 'Mom'],
            [6, 450.00, 'Restaurant meals', $lastMonthYear . '-' . $lastMonth . '-15', 'Dad'],
            [7, 500.00, 'Monthly metro card', $lastMonthYear . '-' . $lastMonth . '-01', 'Dad'],
            [7, 300.00, 'Monthly metro card', $lastMonthYear . '-' . $lastMonth . '-01', 'Mom'],
            [9, 650.00, 'Utility bills', $lastMonthYear . '-' . $lastMonth . '-10', 'Dad'],
            [8, 1200.00, 'New laptop bag', $lastMonthYear . '-' . $lastMonth . '-12', 'Dad'],
            [12, 2500.00, 'Tuition fee', $lastMonthYear . '-' . $lastMonth . '-03', 'Child'],
            [13, 99.00, 'Streaming subscription', $lastMonthYear . '-' . $lastMonth . '-01', 'Dad'],
            [11, 350.00, 'Annual checkup', $lastMonthYear . '-' . $lastMonth . '-20', 'Mom'],
            [3, 1500.00, 'Stock dividend', $lastMonthYear . '-' . $lastMonth . '-25', 'Dad'],
            [14, 2800.00, 'Weekend trip', $lastMonthYear . '-' . $lastMonth . '-22', 'Mom'],
        ];
        
        // Add transactions from 2 months ago
        $twoMonthsAgo = $currentMonth <= '02' ? str_pad(12 + $currentMonth - 2, 2, '0', STR_PAD_LEFT) : str_pad($currentMonth - 2, 2, '0', STR_PAD_LEFT);
        $twoMonthsAgoYear = $currentMonth <= '02' ? $currentYear - 1 : $currentYear;
        
        $twoMonthsAgoTransactions = [
            [1, 15000.00, 'Monthly salary', $twoMonthsAgoYear . '-' . $twoMonthsAgo . '-05', 'Dad'],
            [1, 8000.00, 'Monthly salary', $twoMonthsAgoYear . '-' . $twoMonthsAgo . '-05', 'Mom'],
            [10, 5500.00, 'Monthly rent', $twoMonthsAgoYear . '-' . $twoMonthsAgo . '-01', 'Dad'],
            [6, 720.00, 'Grocery shopping', $twoMonthsAgoYear . '-' . $twoMonthsAgo . '-10', 'Mom'],
            [7, 800.00, 'Transportation', $twoMonthsAgoYear . '-' . $twoMonthsAgo . '-01', 'Dad'],
            [9, 580.00, 'Utility bills', $twoMonthsAgoYear . '-' . $twoMonthsAgo . '-10', 'Dad'],
            [12, 2500.00, 'Tuition fee', $twoMonthsAgoYear . '-' . $twoMonthsAgo . '-03', 'Child'],
            [13, 99.00, 'Streaming subscription', $twoMonthsAgoYear . '-' . $twoMonthsAgo . '-01', 'Dad'],
            [2, 5000.00, 'Year-end bonus', $twoMonthsAgoYear . '-' . $twoMonthsAgo . '-28', 'Mom'],
        ];
        
        // Merge all transactions
        $allTransactions = array_merge($dummyTransactions, $lastMonthTransactions, $twoMonthsAgoTransactions);
        
        // Insert all transactions
        $stmt = $this->pdo->prepare("
            INSERT INTO transactions (category_id, amount, description, transaction_date, member)
            VALUES (?, ?, ?, ?, ?)
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

