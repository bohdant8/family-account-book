<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Family Account Book</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📒</text></svg>">
</head>
<body>
    <div class="app-container">
        <!-- Header -->
        <header class="app-header">
            <h1 class="app-title">Account Book</h1>
            <p class="app-subtitle">Family Financial Records</p>
        </header>

        <!-- Navigation -->
        <nav class="nav-tabs">
            <button class="nav-tab active" data-tab="dashboard">📊 Dashboard</button>
            <button class="nav-tab" data-tab="transactions">📝 Transactions</button>
            <button class="nav-tab" data-tab="reports">📈 Reports</button>
            <button class="nav-tab" data-tab="settings">⚙️ Settings</button>
        </nav>

        <!-- Date Filter -->
        <div class="card">
            <div class="date-filter">
                <div class="date-filter-presets">
                    <button class="date-preset" data-preset="today">Today</button>
                    <button class="date-preset" data-preset="week">This Week</button>
                    <button class="date-preset active" data-preset="month">This Month</button>
                    <button class="date-preset" data-preset="year">This Year</button>
                </div>
                <div style="display: flex; gap: var(--space-sm); align-items: center;">
                    <input type="date" id="startDate" class="form-input" style="width: auto;">
                    <span>to</span>
                    <input type="date" id="endDate" class="form-input" style="width: auto;">
                </div>
                <button class="btn btn-primary" id="addTransactionBtn">+ Add Transaction</button>
            </div>
        </div>

        <!-- Dashboard Tab -->
        <div id="dashboardTab" class="tab-content">
            <!-- Summary Cards -->
            <div class="summary-grid">
                <div class="summary-card income">
                    <div class="summary-label">Income</div>
                    <div class="summary-value" id="summaryIncome">¥0.00</div>
                </div>
                <div class="summary-card expense">
                    <div class="summary-label">Expense</div>
                    <div class="summary-value" id="summaryExpense">¥0.00</div>
                </div>
                <div class="summary-card balance">
                    <div class="summary-label">Balance</div>
                    <div class="summary-value" id="summaryBalance">¥0.00</div>
                </div>
            </div>

            <!-- Recent Transactions -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Recent Transactions</h2>
                </div>
                <div id="recentTransactions">
                    <div class="loading">Loading transactions</div>
                </div>
            </div>
        </div>

        <!-- Transactions Tab -->
        <div id="transactionsTab" class="tab-content hidden">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">All Transactions</h2>
                </div>
                <div id="allTransactions">
                    <div class="loading">Loading transactions</div>
                </div>
            </div>
        </div>

        <!-- Reports Tab -->
        <div id="reportsTab" class="tab-content hidden">
            <div class="card">
                <div id="expenseChart" class="chart-container">
                    <div class="loading">Loading chart</div>
                </div>
            </div>
            <div class="card">
                <div id="incomeChart" class="chart-container">
                    <div class="loading">Loading chart</div>
                </div>
            </div>
        </div>

        <!-- Settings Tab -->
        <div id="settingsTab" class="tab-content hidden">
            <!-- Categories Management -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">💰 Income Categories</h2>
                </div>
                <div id="incomeCategoriesList"></div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">💸 Expense Categories</h2>
                </div>
                <div id="expenseCategoriesList"></div>
            </div>

            <!-- Family Members -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">👨‍👩‍👧‍👦 Family Members</h2>
                </div>
                <div id="membersList"></div>
            </div>
        </div>
    </div>

    <!-- Transaction Modal -->
    <div class="modal-backdrop">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Add Transaction</h3>
                <button class="modal-close">&times;</button>
            </div>
            
            <form id="transactionForm">
                <input type="hidden" id="transactionId">
                
                <!-- Type Toggle -->
                <div class="type-toggle">
                    <button type="button" class="type-toggle-btn expense active" data-type="expense">💸 Expense</button>
                    <button type="button" class="type-toggle-btn income" data-type="income">💰 Income</button>
                </div>
                
                <!-- Category Selection -->
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <div class="category-grid" id="categorySelect"></div>
                </div>
                
                <!-- Amount -->
                <div class="form-group">
                    <label class="form-label" for="transactionAmount">Amount</label>
                    <input type="number" id="transactionAmount" class="form-input" step="0.01" min="0" required placeholder="0.00">
                </div>
                
                <!-- Date -->
                <div class="form-group">
                    <label class="form-label" for="transactionDate">Date</label>
                    <input type="date" id="transactionDate" class="form-input" required>
                </div>
                
                <!-- Member -->
                <div class="form-group">
                    <label class="form-label" for="transactionMember">Family Member</label>
                    <select id="transactionMember" class="form-select">
                        <option value="">Select member (optional)</option>
                    </select>
                </div>
                
                <!-- Description -->
                <div class="form-group">
                    <label class="form-label" for="transactionDescription">Description</label>
                    <textarea id="transactionDescription" class="form-textarea" rows="2" placeholder="Add a note..."></textarea>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="App.closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Transaction</button>
                </div>
            </form>
        </div>
    </div>

    <script src="assets/js/app.js"></script>
</body>
</html>

