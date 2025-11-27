# Family Account Book 📒

A simple, elegant family account book web application built with PHP and SQLite.

## Features

- ✅ **Dashboard** - Overview of income, expenses, and balance
- ✅ **Transaction Management** - Add, edit, and delete transactions
- ✅ **Categories** - Pre-defined income and expense categories with icons
- ✅ **Family Members** - Track spending by family member
- ✅ **Reports** - Visual breakdown of expenses and income by category
- ✅ **Date Filtering** - Filter transactions by date range
- ✅ **Responsive Design** - Works on desktop and mobile devices

## Requirements

- PHP 7.4 or higher
- SQLite3 extension enabled
- Web server (Apache, Nginx, or PHP built-in server)

## Quick Start

### Option 1: PHP Built-in Server (Easiest)

```bash
cd accountbook
php -S localhost:8000
```

Then open http://localhost:8000 in your browser.

### Option 2: Apache/Nginx

Point your web server's document root to the `accountbook` folder.

## Project Structure

```
accountbook/
├── index.php           # Main application page
├── config.php          # Configuration settings
├── database.php        # Database connection and schema
├── api/
│   ├── transactions.php # Transaction CRUD API
│   ├── categories.php   # Categories CRUD API
│   ├── members.php      # Family members API
│   └── stats.php        # Statistics and reports API
├── assets/
│   ├── css/
│   │   └── style.css   # Application styles
│   └── js/
│       └── app.js      # Frontend JavaScript
├── data/
│   └── accountbook.db  # SQLite database (auto-created)
└── README.md
```

## Usage

### Adding Transactions

1. Click the "+ Add Transaction" button
2. Select Income or Expense
3. Choose a category
4. Enter the amount and date
5. Optionally select a family member and add a description
6. Click "Save Transaction"

### Viewing Reports

1. Click the "Reports" tab
2. Use the date filter to select a time period
3. View expense and income breakdown by category

### Managing Categories and Members

1. Click the "Settings" tab
2. View and manage income/expense categories
3. View and manage family members

## Customization

### Currency

Edit `config.php` to change the currency symbol:

```php
define('CURRENCY_SYMBOL', '$');
define('CURRENCY_CODE', 'USD');
```

Also update `assets/js/app.js` in the `formatCurrency` function.

### Timezone

Edit `config.php`:

```php
date_default_timezone_set('America/New_York');
```

### Adding Categories

Categories can be added directly in the database or by modifying the default categories in `database.php`.

## API Endpoints

### Transactions
- `GET /api/transactions.php` - List transactions
- `POST /api/transactions.php` - Create transaction
- `PUT /api/transactions.php` - Update transaction
- `DELETE /api/transactions.php` - Delete transaction

### Categories
- `GET /api/categories.php` - List categories
- `POST /api/categories.php` - Create category
- `PUT /api/categories.php` - Update category
- `DELETE /api/categories.php` - Delete category

### Statistics
- `GET /api/stats.php?action=summary` - Get summary
- `GET /api/stats.php?action=monthly` - Get monthly stats
- `GET /api/stats.php?action=category` - Get category breakdown

## License

MIT License - Feel free to use and modify for your family!

