/**
 * Family Account Book - Frontend Application
 */

const App = {
    // Application state
    state: {
        currentTab: 'dashboard',
        transactions: [],
        categories: [],
        members: [],
        summary: {},
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
            description: document.getElementById('transactionDescription').value,
            transaction_date: document.getElementById('transactionDate').value,
            member: document.getElementById('transactionMember').value || null
        };
        
        const id = document.getElementById('transactionId').value;
        if (id) {
            data.id = id;
            await this.api('transactions.php', 'PUT', data);
        } else {
            await this.api('transactions.php', 'POST', data);
        }
        
        this.closeModal();
        await this.refreshData();
    },

    async deleteTransaction(id) {
        if (!confirm('Are you sure you want to delete this transaction?')) return;
        
        await this.api('transactions.php', 'DELETE', { id });
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
        
        // Update summary cards
        const { income = 0, expense = 0, balance = 0 } = this.state.summary;
        
        document.getElementById('summaryIncome').textContent = this.formatCurrency(income);
        document.getElementById('summaryExpense').textContent = this.formatCurrency(expense);
        document.getElementById('summaryBalance').textContent = this.formatCurrency(balance);
        
        // Render recent transactions
        this.renderTransactionList(
            document.getElementById('recentTransactions'),
            this.state.transactions.slice(0, 10)
        );
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
                            ${this.formatCurrency(t.amount)}
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
        
        this.loadCategoryStats();
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
                                <span style="font-family: var(--font-mono);">${this.formatCurrency(item.total)} (${percent}%)</span>
                            </div>
                            <div style="background: var(--color-parchment); border-radius: 4px; height: 8px; overflow: hidden;">
                                <div style="background: ${item.color}; height: 100%; width: ${percent}%; transition: width 0.3s ease;"></div>
                            </div>
                        </div>
                    `;
                }).join('')}
            </div>
            <div style="margin-top: var(--space-lg); padding-top: var(--space-md); border-top: 1px dashed var(--color-border); text-align: right;">
                <strong>Total: ${this.formatCurrency(total)}</strong>
            </div>
        `;
    },

    renderSettings() {
        document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
        document.getElementById('settingsTab').classList.remove('hidden');
        
        this.renderCategoriesList();
        this.renderMembersList();
    },

    renderCategoriesList() {
        const incomeCategories = this.state.categories.filter(c => c.type === 'income');
        const expenseCategories = this.state.categories.filter(c => c.type === 'expense');
        
        const renderList = (categories, type) => categories.map(c => `
            <div style="display: flex; align-items: center; justify-content: space-between; padding: var(--space-sm); border-bottom: 1px solid var(--color-parchment);">
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
            <div style="display: flex; align-items: center; justify-content: space-between; padding: var(--space-sm); border-bottom: 1px solid var(--color-parchment);">
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
        
        const result = await this.api('categories.php', 'DELETE', { id });
        if (result.error) {
            alert(result.error);
            return;
        }
        
        await this.loadCategories();
        this.renderSettings();
    },

    async deleteMember(id) {
        if (!confirm('Are you sure you want to delete this member?')) return;
        
        await this.api('members.php', 'DELETE', { id });
        await this.loadMembers();
        this.renderSettings();
    },

    // Utility functions
    formatCurrency(amount) {
        return '¥' + parseFloat(amount).toLocaleString('zh-CN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    },

    formatDate(dateStr) {
        const date = new Date(dateStr);
        return date.toLocaleDateString('zh-CN', {
            month: 'short',
            day: 'numeric'
        });
    }
};

// Initialize app when DOM is ready
document.addEventListener('DOMContentLoaded', () => App.init());

