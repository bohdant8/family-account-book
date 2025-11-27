/**
 * Family Account Book - Frontend Application
 */

const App = {
    // Currency configuration (rates are updated from server)
    currencies: {
        CNY: { symbol: '¥', name: 'Chinese Yuan', rate: 1 },
        JPY: { symbol: '¥', name: 'Japanese Yen', rate: 0.05 },
        USD: { symbol: '$', name: 'US Dollar', rate: 7.25 }
    },
    baseCurrency: 'CNY',

    // Application state
    state: {
        currentTab: 'dashboard',
        transactions: [],
        categories: [],
        members: [],
        summary: {},
        monthlyData: {},
        exchangeHistory: [],
        selectedYear: new Date().getFullYear(),
        filters: {
            startDate: null,
            endDate: null,
            type: null,
            member: null
        },
        editingTransaction: null
    },

    // Initialize application
    async init() {
        // Set default date filter to current month
        const now = new Date();
        this.state.filters.startDate = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split('T')[0];
        this.state.filters.endDate = new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().split('T')[0];

        // Load initial data
        await Promise.all([
            this.loadCategories(),
            this.loadMembers(),
            this.loadTransactions(),
            this.loadSummary()
        ]);

        // Setup event listeners
        this.setupEventListeners();
        
        // Render initial view
        this.render();
    },

    // API calls
    async api(endpoint, method = 'GET', data = null) {
        const options = {
            method,
            headers: { 'Content-Type': 'application/json' }
        };
        
        if (data && method !== 'GET') {
            options.body = JSON.stringify(data);
        }
        
        let url = `api/${endpoint}`;
        if (method === 'GET' && data) {
            const params = new URLSearchParams(data);
            url += `?${params}`;
        }
        
        const response = await fetch(url);
        return response.json();
    },

    async loadCategories() {
        const result = await this.api('categories.php');
        if (result.success) {
            this.state.categories = result.data;
        }
    },

    async loadMembers() {
        const result = await this.api('members.php');
        if (result.success) {
            this.state.members = result.data;
        }
    },

    async loadTransactions() {
        const params = {};
        if (this.state.filters.startDate) params.start_date = this.state.filters.startDate;
        if (this.state.filters.endDate) params.end_date = this.state.filters.endDate;
        if (this.state.filters.type) params.type = this.state.filters.type;
        if (this.state.filters.member) params.member = this.state.filters.member;
        
        const result = await this.api('transactions.php', 'GET', params);
        if (result.success) {
            this.state.transactions = result.data;
        }
    },

    async loadSummary() {
        const params = {
            start_date: this.state.filters.startDate,
            end_date: this.state.filters.endDate
        };
        
        const result = await this.api('stats.php', 'GET', params);
        if (result.success) {
            this.state.summary = result.data;
            // Update currencies from server
            if (result.data.currencies) {
                this.currencies = result.data.currencies;
            }
            if (result.data.base_currency) {
                this.baseCurrency = result.data.base_currency;
            }
        }
    },

    // Event handlers
    setupEventListeners() {
        // Navigation tabs
        document.querySelectorAll('.nav-tab').forEach(tab => {
            tab.addEventListener('click', (e) => {
                this.state.currentTab = e.target.dataset.tab;
                this.render();
            });
        });

        // Add transaction button
        document.getElementById('addTransactionBtn')?.addEventListener('click', () => {
            this.openTransactionModal();
        });

        // Modal close
        document.querySelector('.modal-backdrop')?.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal-backdrop')) {
                this.closeModal();
            }
        });

        document.querySelector('.modal-close')?.addEventListener('click', () => {
            this.closeModal();
        });

        // Type toggle in modal
        document.querySelectorAll('.type-toggle-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                document.querySelectorAll('.type-toggle-btn').forEach(b => b.classList.remove('active'));
                e.target.classList.add('active');
                this.renderCategorySelect(e.target.dataset.type);
            });
        });

        // Transaction form submit
        document.getElementById('transactionForm')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            await this.saveTransaction();
        });

        // Date presets
        document.querySelectorAll('.date-preset').forEach(btn => {
            btn.addEventListener('click', (e) => {
                this.setDatePreset(e.target.dataset.preset);
            });
        });

        // Date inputs
        document.getElementById('startDate')?.addEventListener('change', (e) => {
            this.state.filters.startDate = e.target.value;
            this.refreshData();
        });

        document.getElementById('endDate')?.addEventListener('change', (e) => {
            this.state.filters.endDate = e.target.value;
            this.refreshData();
        });

        // Year navigation for monthly chart
        document.getElementById('prevYearBtn')?.addEventListener('click', () => {
            this.state.selectedYear--;
            this.loadMonthlyData();
        });

        document.getElementById('nextYearBtn')?.addEventListener('click', () => {
            this.state.selectedYear++;
            this.loadMonthlyData();
        });

        // Currency exchange
        document.getElementById('openExchangeBtn')?.addEventListener('click', () => {
            this.openExchangeModal();
        });

        document.getElementById('exchangeForm')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            await this.saveExchange();
        });

        // Live exchange calculation
        document.getElementById('exchangeFromAmount')?.addEventListener('input', () => {
            this.calculateExchange();
        });
        document.getElementById('exchangeFromCurrency')?.addEventListener('change', () => {
            this.calculateExchange();
        });
        document.getElementById('exchangeToCurrency')?.addEventListener('change', () => {
            this.calculateExchange();
        });

        // Close exchange modal on backdrop click
        document.getElementById('exchangeModal')?.addEventListener('click', (e) => {
            if (e.target.id === 'exchangeModal') {
                this.closeExchangeModal();
            }
        });
    },

    // Date presets
    setDatePreset(preset) {
        const now = new Date();
        let start, end;

        switch (preset) {
            case 'today':
                start = end = now.toISOString().split('T')[0];
                break;
            case 'week':
                const weekStart = new Date(now);
                weekStart.setDate(now.getDate() - now.getDay());
                start = weekStart.toISOString().split('T')[0];
                end = now.toISOString().split('T')[0];
                break;
            case 'month':
                start = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split('T')[0];
                end = new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().split('T')[0];
                break;
            case 'year':
                start = new Date(now.getFullYear(), 0, 1).toISOString().split('T')[0];
                end = new Date(now.getFullYear(), 11, 31).toISOString().split('T')[0];
                break;
        }

        this.state.filters.startDate = start;
        this.state.filters.endDate = end;
        
        document.getElementById('startDate').value = start;
        document.getElementById('endDate').value = end;
        
        document.querySelectorAll('.date-preset').forEach(b => b.classList.remove('active'));
        document.querySelector(`.date-preset[data-preset="${preset}"]`)?.classList.add('active');
        
        this.refreshData();
    },

    async refreshData() {
        await Promise.all([
            this.loadTransactions(),
            this.loadSummary()
        ]);
        this.render();
    },

    // Modal operations
    openTransactionModal(transaction = null) {
        this.state.editingTransaction = transaction;
        
        const modal = document.querySelector('.modal-backdrop');
        const form = document.getElementById('transactionForm');
        const title = document.querySelector('.modal-title');
        
        form.reset();
        
        if (transaction) {
            title.textContent = 'Edit Transaction';
            document.getElementById('transactionId').value = transaction.id;
            document.getElementById('transactionAmount').value = transaction.amount;
            document.getElementById('transactionCurrency').value = transaction.currency || 'CNY';
            document.getElementById('transactionDescription').value = transaction.description || '';
            document.getElementById('transactionDate').value = transaction.transaction_date;
            document.getElementById('transactionMember').value = transaction.member || '';
            
            // Set type toggle
            const type = transaction.type;
            document.querySelectorAll('.type-toggle-btn').forEach(b => {
                b.classList.toggle('active', b.dataset.type === type);
            });
            this.renderCategorySelect(type, transaction.category_id);
        } else {
            title.textContent = 'Add Transaction';
            document.getElementById('transactionId').value = '';
            document.getElementById('transactionDate').value = new Date().toISOString().split('T')[0];
            document.getElementById('transactionCurrency').value = 'CNY';
            
            // Default to expense
            document.querySelectorAll('.type-toggle-btn').forEach(b => {
                b.classList.toggle('active', b.dataset.type === 'expense');
            });
            this.renderCategorySelect('expense');
        }
        
        modal.classList.add('active');
    },

    closeModal() {
        document.querySelector('.modal-backdrop').classList.remove('active');
        this.state.editingTransaction = null;
    },

    renderCategorySelect(type, selectedId = null) {
        const container = document.getElementById('categorySelect');
        const categories = this.state.categories.filter(c => c.type === type);
        
        container.innerHTML = categories.map(cat => `
            <div class="category-item ${selectedId == cat.id ? 'selected' : ''}" data-id="${cat.id}">
                <span class="category-item-icon">${cat.icon}</span>
                <span class="category-item-name">${cat.name}</span>
            </div>
        `).join('');
        
        container.querySelectorAll('.category-item').forEach(item => {
            item.addEventListener('click', () => {
                container.querySelectorAll('.category-item').forEach(i => i.classList.remove('selected'));
                item.classList.add('selected');
            });
        });
    },

    async saveTransaction() {
        const type = document.querySelector('.type-toggle-btn.active').dataset.type;
        const categoryId = document.querySelector('.category-item.selected')?.dataset.id;
        
        if (!categoryId) {
            alert('Please select a category');
            return;
        }
        
        const data = {
            category_id: categoryId,
            amount: parseFloat(document.getElementById('transactionAmount').value),
            currency: document.getElementById('transactionCurrency').value,
            description: document.getElementById('transactionDescription').value,
            transaction_date: document.getElementById('transactionDate').value,
            member: document.getElementById('transactionMember').value || null
        };
        
        const id = document.getElementById('transactionId').value;
        if (id) {
            data.id = id;
            await fetch('api/transactions.php', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
        } else {
            await fetch('api/transactions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
        }
        
        this.closeModal();
        await this.refreshData();
    },

    async deleteTransaction(id) {
        if (!confirm('Are you sure you want to delete this transaction?')) return;
        
        await fetch('api/transactions.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        await this.refreshData();
    },

    // Rendering
    render() {
        // Update nav tabs
        document.querySelectorAll('.nav-tab').forEach(tab => {
            tab.classList.toggle('active', tab.dataset.tab === this.state.currentTab);
        });
        
        // Update date inputs
        document.getElementById('startDate').value = this.state.filters.startDate;
        document.getElementById('endDate').value = this.state.filters.endDate;
        
        // Update member select
        this.renderMemberSelect();
        
        // Render current tab
        switch (this.state.currentTab) {
            case 'dashboard':
                this.renderDashboard();
                break;
            case 'transactions':
                this.renderTransactions();
                break;
            case 'reports':
                this.renderReports();
                break;
            case 'settings':
                this.renderSettings();
                break;
        }
    },

    renderMemberSelect() {
        const select = document.getElementById('transactionMember');
        if (!select) return;
        
        select.innerHTML = '<option value="">Select member (optional)</option>' +
            this.state.members.map(m => `<option value="${m.name}">${m.avatar} ${m.name}</option>`).join('');
    },

    renderDashboard() {
        // Show dashboard, hide others
        document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
        document.getElementById('dashboardTab').classList.remove('hidden');
        
        // Render multi-currency summary
        this.renderSummaryCards();
        
        // Render recent transactions
        this.renderTransactionList(
            document.getElementById('recentTransactions'),
            this.state.transactions.slice(0, 10)
        );
    },

    renderSummaryCards() {
        const { 
            period_by_currency = {}, 
            period_total = {},
            all_time_by_currency = {},
            all_time_total = {}
        } = this.state.summary;
        
        const baseCurrency = this.baseCurrency || 'CNY';
        
        // Render period income by currency
        const incomeContainer = document.getElementById('summaryIncome');
        const expenseContainer = document.getElementById('summaryExpense');
        const balanceContainer = document.getElementById('summaryBalance');
        
        // Format period income - show converted total
        if (period_total.income !== undefined) {
            let incomeHtml = `<div class="currency-total">${this.formatCurrency(period_total.income, baseCurrency)}</div>`;
            // Show breakdown if multiple currencies
            const currencies = Object.keys(period_by_currency).filter(c => period_by_currency[c].income > 0);
            if (currencies.length > 1) {
                incomeHtml += `<div class="currency-breakdown">`;
                for (const currency of currencies) {
                    incomeHtml += `<span class="breakdown-item">${currency}: ${this.formatCurrency(period_by_currency[currency].income, currency)}</span>`;
                }
                incomeHtml += `</div>`;
            }
            incomeContainer.innerHTML = incomeHtml;
        } else {
            incomeContainer.innerHTML = this.formatCurrency(0, baseCurrency);
        }
        
        // Format period expense - show converted total
        if (period_total.expense !== undefined) {
            let expenseHtml = `<div class="currency-total">${this.formatCurrency(period_total.expense, baseCurrency)}</div>`;
            // Show breakdown if multiple currencies
            const currencies = Object.keys(period_by_currency).filter(c => period_by_currency[c].expense > 0);
            if (currencies.length > 1) {
                expenseHtml += `<div class="currency-breakdown">`;
                for (const currency of currencies) {
                    expenseHtml += `<span class="breakdown-item">${currency}: ${this.formatCurrency(period_by_currency[currency].expense, currency)}</span>`;
                }
                expenseHtml += `</div>`;
            }
            expenseContainer.innerHTML = expenseHtml;
        } else {
            expenseContainer.innerHTML = this.formatCurrency(0, baseCurrency);
        }
        
        // Format all-time balance - show converted total
        if (all_time_total.balance !== undefined) {
            const balanceClass = all_time_total.balance >= 0 ? 'positive' : 'negative';
            let balanceHtml = `<div class="currency-total ${balanceClass}">${this.formatCurrency(all_time_total.balance, baseCurrency)}</div>`;
            // Show breakdown if multiple currencies
            const currencies = Object.keys(all_time_by_currency);
            if (currencies.length > 1) {
                balanceHtml += `<div class="currency-breakdown">`;
                for (const currency of currencies) {
                    const bal = all_time_by_currency[currency].balance;
                    const cls = bal >= 0 ? 'positive' : 'negative';
                    balanceHtml += `<span class="breakdown-item ${cls}">${currency}: ${this.formatCurrency(bal, currency)}</span>`;
                }
                balanceHtml += `</div>`;
            }
            balanceContainer.innerHTML = balanceHtml;
        } else {
            balanceContainer.innerHTML = this.formatCurrency(0, baseCurrency);
        }
    },

    renderTransactions() {
        document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
        document.getElementById('transactionsTab').classList.remove('hidden');
        
        this.renderTransactionList(
            document.getElementById('allTransactions'),
            this.state.transactions
        );
    },

    renderTransactionList(container, transactions) {
        if (transactions.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon">📒</div>
                    <div class="empty-state-text">No transactions found</div>
                </div>
            `;
            return;
        }
        
        container.innerHTML = `
            <ul class="transaction-list">
                ${transactions.map(t => `
                    <li class="transaction-item">
                        <div class="transaction-icon" style="background-color: ${t.color}20">
                            ${t.icon}
                        </div>
                        <div class="transaction-details">
                            <div class="transaction-category">${t.category_name}</div>
                            <div class="transaction-description">${t.description || '—'}</div>
                        </div>
                        <div class="transaction-meta">
                            <div class="transaction-date">${this.formatDate(t.transaction_date)}</div>
                            <div class="transaction-member">${t.member || ''}</div>
                        </div>
                        <div class="transaction-amount ${t.type}">
                            ${this.formatCurrency(t.amount, t.currency)}
                        </div>
                        <div class="transaction-actions">
                            <button class="btn btn-sm btn-secondary" onclick="App.openTransactionModal(${JSON.stringify(t).replace(/"/g, '&quot;')})">Edit</button>
                            <button class="btn btn-sm btn-danger" onclick="App.deleteTransaction(${t.id})">Delete</button>
                        </div>
                    </li>
                `).join('')}
            </ul>
        `;
    },

    renderReports() {
        document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
        document.getElementById('reportsTab').classList.remove('hidden');
        
        this.loadMonthlyData();
        this.loadCategoryStats();
    },

    async loadMonthlyData() {
        const result = await this.api('stats.php', 'GET', {
            action: 'monthly',
            year: this.state.selectedYear
        });
        
        if (result.success) {
            this.state.monthlyData = result.data;
            this.renderMonthlyChart();
        }
    },

    renderMonthlyChart() {
        const container = document.getElementById('monthlyChart');
        const yearDisplay = document.getElementById('selectedYear');
        
        if (!container) return;
        
        // Update year display
        if (yearDisplay) {
            yearDisplay.textContent = this.state.selectedYear;
        }
        
        const data = this.state.monthlyData;
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        
        // Find max value for scaling
        let maxValue = 0;
        for (const [month, values] of Object.entries(data)) {
            maxValue = Math.max(maxValue, values.income || 0, values.expense || 0);
        }
        
        // If no data, show empty state
        if (maxValue === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon">📊</div>
                    <div class="empty-state-text">No data for ${this.state.selectedYear}</div>
                </div>
            `;
            return;
        }
        
        // Render chart
        container.innerHTML = `
            <div class="monthly-chart">
                ${months.map((month, index) => {
                    const monthKey = String(index + 1).padStart(2, '0');
                    const monthData = data[monthKey] || { income: 0, expense: 0 };
                    const incomeHeight = maxValue > 0 ? (monthData.income / maxValue) * 160 : 0;
                    const expenseHeight = maxValue > 0 ? (monthData.expense / maxValue) * 160 : 0;
                    
                    return `
                        <div class="month-bar-group">
                            <div class="month-bars">
                                <div class="month-bar income" 
                                     style="height: ${Math.max(incomeHeight, 4)}px"
                                     data-amount="${this.formatCurrency(monthData.income, 'CNY')}">
                                </div>
                                <div class="month-bar expense" 
                                     style="height: ${Math.max(expenseHeight, 4)}px"
                                     data-amount="${this.formatCurrency(monthData.expense, 'CNY')}">
                                </div>
                            </div>
                            <div class="month-label">${month}</div>
                        </div>
                    `;
                }).join('')}
            </div>
        `;
    },

    async loadCategoryStats() {
        const expenseResult = await this.api('stats.php', 'GET', {
            action: 'category',
            type: 'expense',
            start_date: this.state.filters.startDate,
            end_date: this.state.filters.endDate
        });
        
        const incomeResult = await this.api('stats.php', 'GET', {
            action: 'category',
            type: 'income',
            start_date: this.state.filters.startDate,
            end_date: this.state.filters.endDate
        });
        
        this.renderCategoryChart('expenseChart', expenseResult.data || [], 'Expenses by Category');
        this.renderCategoryChart('incomeChart', incomeResult.data || [], 'Income by Category');
    },

    renderCategoryChart(containerId, data, title) {
        const container = document.getElementById(containerId);
        if (!container) return;
        
        if (data.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon">📊</div>
                    <div class="empty-state-text">No data for this period</div>
                </div>
            `;
            return;
        }
        
        const total = data.reduce((sum, item) => sum + parseFloat(item.total), 0);
        
        container.innerHTML = `
            <h3 class="card-title" style="margin-bottom: var(--space-lg);">${title}</h3>
            <div style="display: flex; flex-direction: column; gap: var(--space-md);">
                ${data.map(item => {
                    const percent = (item.total / total * 100).toFixed(1);
                    return `
                        <div>
                            <div style="display: flex; justify-content: space-between; margin-bottom: var(--space-xs);">
                                <span>${item.icon} ${item.name}</span>
                                <span style="font-weight: 600;">${this.formatCurrency(item.total, 'CNY')} (${percent}%)</span>
                            </div>
                            <div style="background: var(--color-bg); border-radius: 4px; height: 8px; overflow: hidden;">
                                <div style="background: ${item.color}; height: 100%; width: ${percent}%; transition: width 0.3s ease;"></div>
                            </div>
                        </div>
                    `;
                }).join('')}
            </div>
            <div style="margin-top: var(--space-lg); padding-top: var(--space-md); border-top: 2px dashed var(--color-accent); text-align: right;">
                <strong>Total: ${this.formatCurrency(total, 'CNY')}</strong>
            </div>
        `;
    },

    async renderSettings() {
        document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
        document.getElementById('settingsTab').classList.remove('hidden');
        
        this.renderCategoriesList();
        this.renderMembersList();
        this.renderExchangeRates();
        
        // Load and render exchange history
        await this.loadExchangeHistory();
        this.renderExchangeHistory();
        
        // Load and render rate chart
        this.setupRateChartControls();
        await this.loadRateChart(90);
    },

    setupRateChartControls() {
        document.querySelectorAll('.period-btn').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                document.querySelectorAll('.period-btn').forEach(b => b.classList.remove('active'));
                e.target.classList.add('active');
                const days = parseInt(e.target.dataset.days);
                await this.loadRateChart(days);
            });
        });
    },

    async loadRateChart(days = 90) {
        const container = document.getElementById('rateChartContainer');
        if (!container) return;
        
        container.innerHTML = '<div class="loading">Loading chart...</div>';
        
        const result = await this.api(`exchange.php?action=rate_chart&days=${days}`);
        if (result.success) {
            this.renderRateChart(result.data, result.currencies);
        } else {
            container.innerHTML = '<div class="empty-state"><div class="empty-state-icon">📊</div><div class="empty-state-text">No rate data available</div></div>';
        }
    },

    renderRateChart(data, currencies) {
        const container = document.getElementById('rateChartContainer');
        const legendContainer = document.getElementById('rateChartLegend');
        if (!container || !data || data.length === 0) return;
        
        // Find min/max for scaling
        let minRate = Infinity, maxRate = 0;
        currencies.forEach(cur => {
            data.forEach(d => {
                if (d[cur] !== undefined) {
                    minRate = Math.min(minRate, d[cur]);
                    maxRate = Math.max(maxRate, d[cur]);
                }
            });
        });
        
        // Add padding to min/max
        const range = maxRate - minRate;
        minRate = Math.max(0, minRate - range * 0.1);
        maxRate = maxRate + range * 0.1;
        
        const chartHeight = 200;
        const chartWidth = container.offsetWidth - 60; // Leave space for Y axis
        const pointSpacing = Math.max(1, chartWidth / data.length);
        
        // Colors for different currencies
        const colors = {
            'USD': '#10b981',
            'JPY': '#f59e0b',
            'EUR': '#8b5cf6',
            'GBP': '#ec4899'
        };
        
        // Generate SVG paths for each currency
        let pathsHtml = '';
        let dotsHtml = '';
        
        currencies.forEach(cur => {
            const color = colors[cur] || '#6366f1';
            let pathD = '';
            let lastY = null;
            
            data.forEach((d, i) => {
                const x = 50 + i * pointSpacing;
                const rate = d[cur];
                if (rate !== undefined) {
                    const y = chartHeight - ((rate - minRate) / (maxRate - minRate)) * (chartHeight - 20);
                    if (pathD === '') {
                        pathD = `M ${x} ${y}`;
                    } else {
                        pathD += ` L ${x} ${y}`;
                    }
                    lastY = y;
                    
                    // Add dots for data points (sparse for large datasets)
                    if (data.length < 60 || i % Math.ceil(data.length / 30) === 0) {
                        dotsHtml += `
                            <circle cx="${x}" cy="${y}" r="3" fill="${color}" 
                                class="rate-dot" data-date="${d.date}" data-rate="${rate}" data-currency="${cur}">
                                <title>${d.date}: ${rate.toFixed(4)} ${cur}/CNY</title>
                            </circle>
                        `;
                    }
                }
            });
            
            if (pathD) {
                pathsHtml += `<path d="${pathD}" stroke="${color}" stroke-width="2" fill="none" class="rate-line" />`;
            }
        });
        
        // Generate Y axis labels
        const yLabels = [];
        for (let i = 0; i <= 4; i++) {
            const rate = minRate + (maxRate - minRate) * (i / 4);
            const y = chartHeight - (i / 4) * (chartHeight - 20);
            yLabels.push(`<text x="45" y="${y + 4}" class="axis-label">${rate.toFixed(2)}</text>`);
            yLabels.push(`<line x1="50" y1="${y}" x2="${50 + chartWidth}" y2="${y}" class="grid-line" />`);
        }
        
        // Generate X axis labels (show ~6 dates)
        const xLabels = [];
        const labelInterval = Math.floor(data.length / 6);
        data.forEach((d, i) => {
            if (i % labelInterval === 0 || i === data.length - 1) {
                const x = 50 + i * pointSpacing;
                const dateLabel = new Date(d.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                xLabels.push(`<text x="${x}" y="${chartHeight + 15}" class="axis-label x-label">${dateLabel}</text>`);
            }
        });
        
        container.innerHTML = `
            <svg width="100%" height="${chartHeight + 30}" viewBox="0 0 ${chartWidth + 60} ${chartHeight + 30}" class="rate-chart-svg">
                <!-- Grid lines -->
                ${yLabels.join('')}
                
                <!-- Lines -->
                ${pathsHtml}
                
                <!-- Data points -->
                ${dotsHtml}
                
                <!-- X axis labels -->
                ${xLabels.join('')}
            </svg>
        `;
        
        // Render legend
        legendContainer.innerHTML = currencies.map(cur => {
            const color = colors[cur] || '#6366f1';
            const latestRate = data[data.length - 1]?.[cur];
            return `
                <span class="rate-legend-item">
                    <span class="legend-color" style="background: ${color}"></span>
                    ${cur}: ${latestRate?.toFixed(4) || '—'}
                </span>
            `;
        }).join('');
    },

    renderExchangeRates() {
        const container = document.getElementById('exchangeRatesList');
        if (!container) return;
        
        const baseCurrency = this.baseCurrency || 'CNY';
        const today = new Date().toISOString().split('T')[0];
        
        container.innerHTML = `
            <p class="exchange-note">Rates are used historically - set the <strong>effective date</strong> for accurate period calculations:</p>
            <div class="exchange-rates-grid">
                ${Object.entries(this.currencies).map(([code, info]) => `
                    <div class="exchange-rate-item ${code === baseCurrency ? 'base-currency' : ''}" data-currency="${code}">
                        <span class="rate-currency">${code}</span>
                        <span class="rate-name">${info.name || code}</span>
                        ${code === baseCurrency ? 
                            `<span class="rate-value base">Base Currency</span>` :
                            `<div class="rate-edit">
                                <span class="rate-label">1 ${code} =</span>
                                <input type="number" class="rate-input" value="${info.rate || 1}" step="0.0001" min="0.0001" data-currency="${code}">
                                <span class="rate-label">${baseCurrency}</span>
                                <input type="date" class="rate-date" value="${today}" data-currency="${code}" title="Effective date">
                                <button class="btn btn-sm btn-success rate-save" onclick="App.updateExchangeRate('${code}')">✓</button>
                            </div>`
                        }
                    </div>
                `).join('')}
            </div>
            <p class="exchange-disclaimer">💡 Historical rates are used when viewing past periods. Add rates for past dates to improve accuracy.</p>
        `;
    },

    async updateExchangeRate(currency) {
        const input = document.querySelector(`.rate-input[data-currency="${currency}"]`);
        const dateInput = document.querySelector(`.rate-date[data-currency="${currency}"]`);
        if (!input) return;
        
        const rate = parseFloat(input.value);
        const effectiveDate = dateInput?.value || new Date().toISOString().split('T')[0];
        
        if (isNaN(rate) || rate <= 0) {
            alert('Please enter a valid positive rate');
            return;
        }
        
        const response = await fetch('api/exchange.php?action=rates', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ currency, rate, effective_date: effectiveDate })
        });
        const result = await response.json();
        
        if (result.success) {
            this.currencies[currency].rate = rate;
            // Refresh summary to recalculate with new rate
            await this.loadSummary();
            this.render();
        } else {
            alert(result.error || 'Failed to update rate');
        }
    },

    async loadExchangeHistory() {
        const result = await this.api('exchange.php?action=history');
        if (result.success) {
            this.state.exchangeHistory = result.data;
        }
    },

    renderExchangeHistory() {
        const container = document.getElementById('exchangeHistory');
        if (!container) return;
        
        const history = this.state.exchangeHistory;
        
        if (!history || history.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon">💱</div>
                    <div class="empty-state-text">No currency exchanges yet</div>
                </div>
            `;
            return;
        }
        
        container.innerHTML = `
            <div class="exchange-history-list">
                ${history.map(ex => `
                    <div class="exchange-history-item">
                        <div class="exchange-info">
                            <span class="exchange-amounts">
                                ${this.formatCurrency(ex.from_amount, ex.from_currency)} 
                                <span class="exchange-arrow">→</span> 
                                ${this.formatCurrency(ex.to_amount, ex.to_currency)}
                            </span>
                            <span class="exchange-meta">
                                ${this.formatDate(ex.exchange_date)} 
                                ${ex.member ? `• ${ex.member}` : ''}
                                ${ex.description ? `• ${ex.description}` : ''}
                            </span>
                        </div>
                        <span class="exchange-rate-badge">Rate: ${parseFloat(ex.exchange_rate).toFixed(4)}</span>
                    </div>
                `).join('')}
            </div>
        `;
    },

    openExchangeModal() {
        const modal = document.getElementById('exchangeModal');
        document.getElementById('exchangeForm').reset();
        document.getElementById('exchangeDate').value = new Date().toISOString().split('T')[0];
        
        // Populate member select
        const memberSelect = document.getElementById('exchangeMember');
        memberSelect.innerHTML = '<option value="">Select member (optional)</option>' +
            this.state.members.map(m => `<option value="${m.name}">${m.avatar} ${m.name}</option>`).join('');
        
        this.calculateExchange();
        modal.classList.add('active');
    },

    closeExchangeModal() {
        document.getElementById('exchangeModal').classList.remove('active');
    },

    calculateExchange() {
        const fromCurrency = document.getElementById('exchangeFromCurrency').value;
        const toCurrency = document.getElementById('exchangeToCurrency').value;
        const fromAmount = parseFloat(document.getElementById('exchangeFromAmount').value) || 0;
        
        const fromRate = this.currencies[fromCurrency]?.rate || 1;
        const toRate = this.currencies[toCurrency]?.rate || 1;
        
        // Convert: from -> CNY -> to
        const cnyAmount = fromAmount * fromRate;
        const toAmount = cnyAmount / toRate;
        
        document.getElementById('exchangeToAmount').value = toAmount.toFixed(2);
        document.getElementById('exchangePreviewFrom').textContent = `${fromAmount.toFixed(2)} ${fromCurrency}`;
        document.getElementById('exchangePreviewTo').textContent = `${toAmount.toFixed(2)} ${toCurrency}`;
        
        const exchangeRate = fromRate / toRate;
        document.getElementById('currentRateInfo').textContent = 
            `Current rate: 1 ${fromCurrency} = ${exchangeRate.toFixed(4)} ${toCurrency}`;
    },

    async saveExchange() {
        const data = {
            from_currency: document.getElementById('exchangeFromCurrency').value,
            to_currency: document.getElementById('exchangeToCurrency').value,
            from_amount: parseFloat(document.getElementById('exchangeFromAmount').value),
            to_amount: parseFloat(document.getElementById('exchangeToAmount').value),
            exchange_date: document.getElementById('exchangeDate').value,
            member: document.getElementById('exchangeMember').value || null,
            description: document.getElementById('exchangeDescription').value || null
        };
        
        if (data.from_currency === data.to_currency) {
            alert('Please select different currencies');
            return;
        }
        
        if (!data.from_amount || data.from_amount <= 0) {
            alert('Please enter a valid amount');
            return;
        }
        
        const response = await fetch('api/exchange.php?action=exchange', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        const result = await response.json();
        
        if (result.success) {
            this.closeExchangeModal();
            // Refresh both exchange history and summary (for updated balance)
            await Promise.all([
                this.loadExchangeHistory(),
                this.loadSummary()
            ]);
            this.renderExchangeHistory();
            // Update dashboard if visible
            if (this.state.currentTab === 'dashboard') {
                this.renderSummaryCards();
            }
        } else {
            alert(result.error || 'Failed to process exchange');
        }
    },

    renderCategoriesList() {
        const incomeCategories = this.state.categories.filter(c => c.type === 'income');
        const expenseCategories = this.state.categories.filter(c => c.type === 'expense');
        
        const renderList = (categories, type) => categories.map(c => `
            <div style="display: flex; align-items: center; justify-content: space-between; padding: var(--space-sm); border-bottom: 1px solid var(--color-bg);">
                <div style="display: flex; align-items: center; gap: var(--space-sm);">
                    <span style="font-size: 1.25rem;">${c.icon}</span>
                    <span>${c.name}</span>
                    <span style="width: 12px; height: 12px; background: ${c.color}; border-radius: 50%;"></span>
                </div>
                <button class="btn btn-sm btn-danger" onclick="App.deleteCategory(${c.id})">×</button>
            </div>
        `).join('');
        
        document.getElementById('incomeCategoriesList').innerHTML = renderList(incomeCategories, 'income') || '<div class="empty-state">No income categories</div>';
        document.getElementById('expenseCategoriesList').innerHTML = renderList(expenseCategories, 'expense') || '<div class="empty-state">No expense categories</div>';
    },

    renderMembersList() {
        const container = document.getElementById('membersList');
        container.innerHTML = this.state.members.map(m => `
            <div style="display: flex; align-items: center; justify-content: space-between; padding: var(--space-sm); border-bottom: 1px solid var(--color-bg);">
                <div style="display: flex; align-items: center; gap: var(--space-sm);">
                    <span style="font-size: 1.25rem;">${m.avatar}</span>
                    <span>${m.name}</span>
                </div>
                <button class="btn btn-sm btn-danger" onclick="App.deleteMember(${m.id})">×</button>
            </div>
        `).join('') || '<div class="empty-state">No family members</div>';
    },

    async deleteCategory(id) {
        if (!confirm('Are you sure? Categories with transactions cannot be deleted.')) return;
        
        const response = await fetch('api/categories.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const result = await response.json();
        
        if (result.error) {
            alert(result.error);
            return;
        }
        
        await this.loadCategories();
        this.renderSettings();
    },

    async deleteMember(id) {
        if (!confirm('Are you sure you want to delete this member?')) return;
        
        await fetch('api/members.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        await this.loadMembers();
        this.renderSettings();
    },

    // Utility functions
    formatCurrency(amount, currency = 'CNY') {
        const config = this.currencies[currency] || this.currencies.CNY;
        const value = parseFloat(amount);
        
        // JPY doesn't use decimal places
        const decimals = currency === 'JPY' ? 0 : 2;
        
        return config.symbol + value.toLocaleString('en-US', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        });
    },

    formatDate(dateStr) {
        const date = new Date(dateStr);
        return date.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric'
        });
    }
};

// Initialize app when DOM is ready
document.addEventListener('DOMContentLoaded', () => App.init());
