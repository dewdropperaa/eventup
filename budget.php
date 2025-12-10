<?php
session_start();
require_once 'database.php';
require_once 'role_check.php';

// Check if user is logged in
requireLogin();

// Get event ID from URL
$event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;

if ($event_id <= 0) {
    header('Location: organizer_dashboard.php');
    exit();
}

// Check permission: user must be event owner or have can_edit_budget permission
if (!canDo($event_id, $_SESSION['user_id'], 'can_edit_budget')) {
    header('Location: unauthorized.php');
    exit();
}

try {
    $pdo = getDatabaseConnection();
    
    // Get event details
    $stmt = $pdo->prepare('SELECT id, titre, date, created_by FROM events WHERE id = ?');
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();
    
    if (!$event) {
        header('Location: organizer_dashboard.php');
        exit();
    }
    
    // Determine user roles for navigation
    $isEventOwner = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $event['created_by'];
    $isEventAdmin = isEventAdmin($_SESSION['user_id'], $event_id);
    $isEventOrganizer = isEventOrganizer($_SESSION['user_id'], $event_id);
    
    // Get budget settings
    $stmt = $pdo->prepare('SELECT budget_limit, currency FROM event_budget_settings WHERE event_id = ?');
    $stmt->execute([$event_id]);
    $budget_settings = $stmt->fetch();
    
    if (!$budget_settings) {
        $budget_settings = ['budget_limit' => 0, 'currency' => 'MAD'];
    }
    
    // Get all expenses
    $stmt = $pdo->prepare('
        SELECT id, category, title, amount, date, notes 
        FROM event_expenses 
        WHERE event_id = ? 
        ORDER BY date DESC
    ');
    $stmt->execute([$event_id]);
    $expenses = $stmt->fetchAll();
    
    // Get all incomes
    $stmt = $pdo->prepare('
        SELECT id, source, title, amount, date, notes 
        FROM event_incomes 
        WHERE event_id = ? 
        ORDER BY date DESC
    ');
    $stmt->execute([$event_id]);
    $incomes = $stmt->fetchAll();
    
    // Calculate totals
    $total_expenses = array_sum(array_column($expenses, 'amount'));
    $total_incomes = array_sum(array_column($incomes, 'amount'));
    $balance = $total_incomes - $total_expenses;
    $budget_limit = $budget_settings['budget_limit'];
    $currency = $budget_settings['currency'];
    
    // Calculate category breakdown for pie chart
    $category_breakdown = [];
    foreach ($expenses as $expense) {
        if (!isset($category_breakdown[$expense['category']])) {
            $category_breakdown[$expense['category']] = 0;
        }
        $category_breakdown[$expense['category']] += $expense['amount'];
    }
    
} catch (PDOException $e) {
    error_log('Error loading budget: ' . $e->getMessage());
    die('Error loading budget data');
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EventUp - Gestion du Budget</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            padding-top: 76px;
            background-color: #f5f7fa;
        }
    </style>
</head>
<body>
    <?php include 'event_header.php'; ?>

<div class="container-fluid mt-4">
    <div class="row">
        <?php 
        // Set eventId variable for event_nav.php
        $eventId = $event_id;
        include 'event_nav.php'; 
        ?>
        
        <div class="col-lg-9">
            <div class="row mb-4">
                <div class="col-md-8">
                    <h1><i class="bi bi-wallet2"></i> Budget Management</h1>
                    <p class="text-muted">Event: <strong><?php echo htmlspecialchars($event['titre']); ?></strong></p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="generate_budget_pdf.php?event_id=<?php echo $event_id; ?>" class="btn btn-danger" target="_blank">
                        <i class="bi bi-file-pdf"></i> Export PDF
                    </a>
                </div>
            </div>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h6 class="card-title text-muted">Total Income</h6>
                <h3 class="text-success"><?php echo number_format($total_incomes, 2); ?> <?php echo $currency; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h6 class="card-title text-muted">Total Expenses</h6>
                <h3 class="text-danger"><?php echo number_format($total_expenses, 2); ?> <?php echo $currency; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h6 class="card-title text-muted">Balance</h6>
                <h3 class="<?php echo $balance >= 0 ? 'text-success' : 'text-danger'; ?>">
                    <?php echo number_format($balance, 2); ?> <?php echo $currency; ?>
                </h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h6 class="card-title text-muted">Budget Limit</h6>
                <h3><?php echo number_format($budget_limit, 2); ?> <?php echo $currency; ?></h3>
                <small class="text-muted">
                    <?php 
                    if ($budget_limit > 0) {
                        $percentage = round(($total_expenses / $budget_limit) * 100);
                        echo $percentage . '% used';
                    } else {
                        echo 'Not set';
                    }
                    ?>
                </small>
            </div>
        </div>
    </div>
</div>

<!-- Alerts -->
<?php if ($budget_limit > 0): ?>
    <?php if ($total_expenses > $budget_limit): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle"></i>
            <strong>Attention :</strong> Votre événement dépasse le budget prévu !
            Dépassement de <?php echo number_format($total_expenses - $budget_limit, 2); ?> <?php echo $currency; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php elseif ($total_expenses >= ($budget_limit * 0.8)): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-circle"></i>
            <strong>Attention :</strong> Vous avez atteint <?php echo round(($total_expenses / $budget_limit) * 100); ?>% du budget.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
<?php endif; ?>

<!-- Budget Limit Settings -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">Budget Limit Settings</h5>
    </div>
    <div class="card-body">
        <form id="budgetLimitForm" class="row g-3">
            <div class="col-md-6">
                <label for="budgetLimit" class="form-label">Budget Limit</label>
                <input type="number" class="form-control" id="budgetLimit" name="budget_limit" 
                       value="<?php echo $budget_limit; ?>" step="0.01" min="0">
            </div>
            <div class="col-md-6">
                <label for="currency" class="form-label">Currency</label>
                <select class="form-select" id="currency" name="currency">
                    <option value="MAD" <?php echo $currency === 'MAD' ? 'selected' : ''; ?>>MAD (Moroccan Dirham)</option>
                    <option value="USD" <?php echo $currency === 'USD' ? 'selected' : ''; ?>>USD (US Dollar)</option>
                    <option value="EUR" <?php echo $currency === 'EUR' ? 'selected' : ''; ?>>EUR (Euro)</option>
                    <option value="GBP" <?php echo $currency === 'GBP' ? 'selected' : ''; ?>>GBP (British Pound)</option>
                </select>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle"></i> Save Budget Limit
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Tabs for Expenses and Incomes -->
<ul class="nav nav-tabs mb-4" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="expenses-tab" data-bs-toggle="tab" data-bs-target="#expenses" 
                type="button" role="tab" aria-controls="expenses" aria-selected="true">
            <i class="bi bi-cash-coin"></i> Expenses
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="incomes-tab" data-bs-toggle="tab" data-bs-target="#incomes" 
                type="button" role="tab" aria-controls="incomes" aria-selected="false">
            <i class="bi bi-money-bill"></i> Incomes
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="charts-tab" data-bs-toggle="tab" data-bs-target="#charts" 
                type="button" role="tab" aria-controls="charts" aria-selected="false">
            <i class="bi bi-bar-chart"></i> Charts
        </button>
    </li>
</ul>

<div class="tab-content">
    <!-- Expenses Tab -->
    <div class="tab-pane fade show active" id="expenses" role="tabpanel" aria-labelledby="expenses-tab">
        <div class="mb-3">
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
                <i class="bi bi-plus-circle"></i> Add Expense
            </button>
        </div>
        
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Category</th>
                        <th>Title</th>
                        <th>Amount</th>
                        <th>Notes</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="expensesTableBody">
                    <?php foreach ($expenses as $expense): ?>
                        <tr data-expense-id="<?php echo $expense['id']; ?>">
                            <td><?php echo htmlspecialchars($expense['date']); ?></td>
                            <td>
                                <span class="badge bg-secondary"><?php echo htmlspecialchars($expense['category']); ?></span>
                            </td>
                            <td><?php echo htmlspecialchars($expense['title']); ?></td>
                            <td class="fw-bold text-danger"><?php echo number_format($expense['amount'], 2); ?> <?php echo $currency; ?></td>
                            <td><?php echo htmlspecialchars($expense['notes'] ?? ''); ?></td>
                            <td>
                                <button class="btn btn-sm btn-danger delete-expense" data-expense-id="<?php echo $expense['id']; ?>">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (empty($expenses)): ?>
                <div class="alert alert-info">No expenses yet. Add one to get started!</div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Incomes Tab -->
    <div class="tab-pane fade" id="incomes" role="tabpanel" aria-labelledby="incomes-tab">
        <div class="mb-3">
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addIncomeModal">
                <i class="bi bi-plus-circle"></i> Add Income
            </button>
        </div>
        
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Source</th>
                        <th>Title</th>
                        <th>Amount</th>
                        <th>Notes</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="incomesTableBody">
                    <?php foreach ($incomes as $income): ?>
                        <tr data-income-id="<?php echo $income['id']; ?>">
                            <td><?php echo htmlspecialchars($income['date']); ?></td>
                            <td>
                                <span class="badge bg-info"><?php echo htmlspecialchars($income['source']); ?></span>
                            </td>
                            <td><?php echo htmlspecialchars($income['title']); ?></td>
                            <td class="fw-bold text-success"><?php echo number_format($income['amount'], 2); ?> <?php echo $currency; ?></td>
                            <td><?php echo htmlspecialchars($income['notes'] ?? ''); ?></td>
                            <td>
                                <button class="btn btn-sm btn-danger delete-income" data-income-id="<?php echo $income['id']; ?>">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (empty($incomes)): ?>
                <div class="alert alert-info">No incomes yet. Add one to get started!</div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Charts Tab -->
    <div class="tab-pane fade" id="charts" role="tabpanel" aria-labelledby="charts-tab">
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Répartition par catégorie</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Budget vs Dépenses réelles</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="budgetChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Expense Modal -->
<div class="modal fade" id="addExpenseModal" tabindex="-1" aria-labelledby="addExpenseModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addExpenseModalLabel">Add New Expense</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addExpenseForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="expenseCategory" class="form-label">Category</label>
                        <select class="form-select" id="expenseCategory" name="category" required>
                            <option value="">Select a category</option>
                            <option value="Logistique">Logistique</option>
                            <option value="Marketing">Marketing</option>
                            <option value="Restauration">Restauration</option>
                            <option value="Matériel">Matériel</option>
                            <option value="Transport">Transport</option>
                            <option value="Autres">Autres</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="expenseTitle" class="form-label">Title</label>
                        <input type="text" class="form-control" id="expenseTitle" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label for="expenseAmount" class="form-label">Amount (<?php echo $currency; ?>)</label>
                        <input type="number" class="form-control" id="expenseAmount" name="amount" step="0.01" min="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label for="expenseDate" class="form-label">Date</label>
                        <input type="date" class="form-control" id="expenseDate" name="date" required>
                    </div>
                    <div class="mb-3">
                        <label for="expenseNotes" class="form-label">Notes</label>
                        <textarea class="form-control" id="expenseNotes" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Expense</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Income Modal -->
<div class="modal fade" id="addIncomeModal" tabindex="-1" aria-labelledby="addIncomeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addIncomeModalLabel">Add New Income</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addIncomeForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="incomeSource" class="form-label">Source</label>
                        <input type="text" class="form-control" id="incomeSource" name="source" placeholder="e.g., Ticket Sales, Sponsorship" required>
                    </div>
                    <div class="mb-3">
                        <label for="incomeTitle" class="form-label">Title</label>
                        <input type="text" class="form-control" id="incomeTitle" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label for="incomeAmount" class="form-label">Amount (<?php echo $currency; ?>)</label>
                        <input type="number" class="form-control" id="incomeAmount" name="amount" step="0.01" min="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label for="incomeDate" class="form-label">Date</label>
                        <input type="date" class="form-control" id="incomeDate" name="date" required>
                    </div>
                    <div class="mb-3">
                        <label for="incomeNotes" class="form-label">Notes</label>
                        <textarea class="form-control" id="incomeNotes" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Income</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Chart.js Library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

<script>
const eventId = <?php echo $event_id; ?>;
const currency = '<?php echo $currency; ?>';
const budgetLimit = <?php echo $budget_limit; ?>;
const totalExpenses = <?php echo $total_expenses; ?>;
const totalIncomes = <?php echo $total_incomes; ?>;

// Category breakdown data for pie chart
const categoryBreakdown = <?php echo json_encode($category_breakdown); ?>;

// Initialize charts when charts tab is shown
document.getElementById('charts-tab').addEventListener('click', function() {
    setTimeout(initializeCharts, 100);
});

function initializeCharts() {
    // Pie Chart - Category Breakdown
    const categoryCtx = document.getElementById('categoryChart');
    if (categoryCtx && !categoryCtx.chart) {
        const labels = Object.keys(categoryBreakdown);
        const data = Object.values(categoryBreakdown);
        const colors = ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40'];
        
        categoryCtx.chart = new Chart(categoryCtx, {
            type: 'pie',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: colors.slice(0, labels.length),
                    borderColor: '#fff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ' + context.parsed + ' ' + currency;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Bar Chart - Budget vs Actual
    const budgetCtx = document.getElementById('budgetChart');
    if (budgetCtx && !budgetCtx.chart) {
        budgetCtx.chart = new Chart(budgetCtx, {
            type: 'bar',
            data: {
                labels: ['Budget', 'Actual Expenses'],
                datasets: [{
                    label: 'Amount (' + currency + ')',
                    data: [budgetLimit, totalExpenses],
                    backgroundColor: [
                        '#36A2EB',
                        totalExpenses > budgetLimit ? '#FF6384' : '#4BC0C0'
                    ],
                    borderColor: [
                        '#36A2EB',
                        totalExpenses > budgetLimit ? '#FF6384' : '#4BC0C0'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                indexAxis: 'y',
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.parsed.x + ' ' + currency;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
}

// Add Expense Form Handler
document.getElementById('addExpenseForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('event_id', eventId);
    
    fetch('add_expense.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Success', 'Expense added successfully');
            document.getElementById('addExpenseForm').reset();
            const modal = bootstrap.Modal.getInstance(document.getElementById('addExpenseModal'));
            modal.hide();
            location.reload();
        } else {
            showToast('Error', data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error', 'Failed to add expense', 'danger');
    });
});

// Add Income Form Handler
document.getElementById('addIncomeForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('event_id', eventId);
    
    fetch('add_income.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Success', 'Income added successfully');
            document.getElementById('addIncomeForm').reset();
            const modal = bootstrap.Modal.getInstance(document.getElementById('addIncomeModal'));
            modal.hide();
            location.reload();
        } else {
            showToast('Error', data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error', 'Failed to add income', 'danger');
    });
});

// Delete Expense Handler
document.querySelectorAll('.delete-expense').forEach(button => {
    button.addEventListener('click', function() {
        if (confirm('Are you sure you want to delete this expense?')) {
            const expenseId = this.dataset.expenseId;
            
            fetch('delete_expense.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'expense_id=' + expenseId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Success', 'Expense deleted successfully');
                    document.querySelector(`[data-expense-id="${expenseId}"]`).remove();
                    location.reload();
                } else {
                    showToast('Error', data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error', 'Failed to delete expense', 'danger');
            });
        }
    });
});

// Delete Income Handler
document.querySelectorAll('.delete-income').forEach(button => {
    button.addEventListener('click', function() {
        if (confirm('Are you sure you want to delete this income?')) {
            const incomeId = this.dataset.incomeId;
            
            fetch('delete_income.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'income_id=' + incomeId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Success', 'Income deleted successfully');
                    document.querySelector(`[data-income-id="${incomeId}"]`).remove();
                    location.reload();
                } else {
                    showToast('Error', data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error', 'Failed to delete income', 'danger');
            });
        }
    });
});

// Budget Limit Form Handler
document.getElementById('budgetLimitForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('event_id', eventId);
    
    fetch('update_budget_limit.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Success', 'Budget limit updated successfully');
            location.reload();
        } else {
            showToast('Error', data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error', 'Failed to update budget limit', 'danger');
    });
});

// Toast notification helper
function showToast(title, message, type = 'success') {
    const toastHtml = `
        <div class="toast align-items-center text-white bg-${type === 'danger' ? 'danger' : 'success'} border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <strong>${title}:</strong> ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    `;
    
    const toastContainer = document.createElement('div');
    toastContainer.className = 'position-fixed bottom-0 end-0 p-3';
    toastContainer.innerHTML = toastHtml;
    document.body.appendChild(toastContainer);
    
    const toast = new bootstrap.Toast(toastContainer.querySelector('.toast'));
    toast.show();
    
    setTimeout(() => toastContainer.remove(), 5000);
}
</script>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
