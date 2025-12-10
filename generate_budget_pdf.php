<?php
/**
 * Generate Budget PDF Handler
 * Generates a PDF report of the event budget
 */

session_start();
require_once 'database.php';
require_once 'role_check.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get event ID from URL
$event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;

if ($event_id <= 0) {
    die('Invalid event ID');
}

// Check permission
if (!canDo($event_id, $_SESSION['user_id'], 'can_edit_budget')) {
    die('Unauthorized');
}

try {
    $pdo = getDatabaseConnection();
    
    // Get event details
    $stmt = $pdo->prepare('SELECT id, titre, date FROM events WHERE id = ?');
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();
    
    if (!$event) {
        die('Event not found');
    }
    
    // Get budget settings
    $stmt = $pdo->prepare('SELECT budget_limit, currency FROM event_budget_settings WHERE event_id = ?');
    $stmt->execute([$event_id]);
    $budget_settings = $stmt->fetch();
    
    if (!$budget_settings) {
        $budget_settings = ['budget_limit' => 0, 'currency' => 'MAD'];
    }
    
    // Get expenses
    $stmt = $pdo->prepare('
        SELECT id, category, title, amount, date, notes 
        FROM event_expenses 
        WHERE event_id = ? 
        ORDER BY date DESC
    ');
    $stmt->execute([$event_id]);
    $expenses = $stmt->fetchAll();
    
    // Get incomes
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
    
    // Generate HTML for PDF
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body {
                font-family: Arial, sans-serif;
                color: #333;
                margin: 20px;
            }
            h1 {
                color: #2c3e50;
                border-bottom: 3px solid #3498db;
                padding-bottom: 10px;
            }
            h2 {
                color: #34495e;
                margin-top: 30px;
                border-bottom: 2px solid #ecf0f1;
                padding-bottom: 5px;
            }
            .summary {
                display: flex;
                justify-content: space-around;
                margin: 20px 0;
                padding: 20px;
                background-color: #ecf0f1;
                border-radius: 5px;
            }
            .summary-item {
                text-align: center;
            }
            .summary-item .label {
                font-weight: bold;
                color: #7f8c8d;
                font-size: 12px;
            }
            .summary-item .value {
                font-size: 24px;
                font-weight: bold;
                color: #2c3e50;
            }
            .positive {
                color: #27ae60;
            }
            .negative {
                color: #e74c3c;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin: 20px 0;
            }
            th {
                background-color: #3498db;
                color: white;
                padding: 12px;
                text-align: left;
                font-weight: bold;
            }
            td {
                padding: 10px 12px;
                border-bottom: 1px solid #ecf0f1;
            }
            tr:nth-child(even) {
                background-color: #f8f9fa;
            }
            .alert {
                padding: 15px;
                margin: 20px 0;
                border-radius: 5px;
                border-left: 5px solid;
            }
            .alert-danger {
                background-color: #fadbd8;
                border-color: #e74c3c;
                color: #c0392b;
            }
            .alert-warning {
                background-color: #fdebd0;
                border-color: #f39c12;
                color: #d68910;
            }
            .footer {
                margin-top: 40px;
                padding-top: 20px;
                border-top: 1px solid #ecf0f1;
                font-size: 12px;
                color: #7f8c8d;
                text-align: center;
            }
        </style>
    </head>
    <body>
        <h1>Budget Report: ' . htmlspecialchars($event['titre']) . '</h1>
        <p><strong>Event Date:</strong> ' . htmlspecialchars($event['date']) . '</p>
        <p><strong>Generated:</strong> ' . date('Y-m-d H:i:s') . '</p>
        
        <div class="summary">
            <div class="summary-item">
                <div class="label">Total Income</div>
                <div class="value positive">' . number_format($total_incomes, 2) . ' ' . $currency . '</div>
            </div>
            <div class="summary-item">
                <div class="label">Total Expenses</div>
                <div class="value negative">' . number_format($total_expenses, 2) . ' ' . $currency . '</div>
            </div>
            <div class="summary-item">
                <div class="label">Balance</div>
                <div class="value ' . ($balance >= 0 ? 'positive' : 'negative') . '">' . number_format($balance, 2) . ' ' . $currency . '</div>
            </div>
            ' . ($budget_limit > 0 ? '
            <div class="summary-item">
                <div class="label">Budget Limit</div>
                <div class="value">' . number_format($budget_limit, 2) . ' ' . $currency . '</div>
            </div>
            ' : '') . '
        </div>
    ';
    
    // Add alerts
    if ($budget_limit > 0) {
        if ($total_expenses > $budget_limit) {
            $html .= '<div class="alert alert-danger">
                <strong>Alert:</strong> Your event exceeds the planned budget! 
                Overspent by ' . number_format($total_expenses - $budget_limit, 2) . ' ' . $currency . '
            </div>';
        } elseif ($total_expenses >= ($budget_limit * 0.8)) {
            $percentage = round(($total_expenses / $budget_limit) * 100);
            $html .= '<div class="alert alert-warning">
                <strong>Warning:</strong> You have reached ' . $percentage . '% of your budget.
            </div>';
        }
    }
    
    // Expenses table
    if (!empty($expenses)) {
        $html .= '<h2>Expenses</h2>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Category</th>
                    <th>Title</th>
                    <th>Amount</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($expenses as $expense) {
            $html .= '<tr>
                <td>' . htmlspecialchars($expense['date']) . '</td>
                <td>' . htmlspecialchars($expense['category']) . '</td>
                <td>' . htmlspecialchars($expense['title']) . '</td>
                <td>' . number_format($expense['amount'], 2) . ' ' . $currency . '</td>
                <td>' . htmlspecialchars($expense['notes'] ?? '') . '</td>
            </tr>';
        }
        
        $html .= '</tbody>
        </table>';
    }
    
    // Incomes table
    if (!empty($incomes)) {
        $html .= '<h2>Incomes</h2>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Source</th>
                    <th>Title</th>
                    <th>Amount</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($incomes as $income) {
            $html .= '<tr>
                <td>' . htmlspecialchars($income['date']) . '</td>
                <td>' . htmlspecialchars($income['source']) . '</td>
                <td>' . htmlspecialchars($income['title']) . '</td>
                <td>' . number_format($income['amount'], 2) . ' ' . $currency . '</td>
                <td>' . htmlspecialchars($income['notes'] ?? '') . '</td>
            </tr>';
        }
        
        $html .= '</tbody>
        </table>';
    }
    
    // Footer
    $html .= '<div class="footer">
        <p>This budget report was generated by EventUp on ' . date('Y-m-d H:i:s') . '</p>
    </div>
    </body>
    </html>';
    
    // Check if DOMPDF is available
    $dompdf_path = __DIR__ . '/vendor/autoload.php';
    
    if (file_exists($dompdf_path)) {
        require_once $dompdf_path;
        
        // Use fully qualified class name instead of use statement
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        // Output PDF
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="budget_' . $event_id . '_' . date('Y-m-d') . '.pdf"');
        header('Cache-Control: private, must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Expires: 0');
        echo $dompdf->output();
    } else {
        // If DOMPDF not available, output HTML for browser to print
        header('Content-Type: text/html; charset=utf-8');
        echo $html . '
        <div style="position: fixed; top: 10px; right: 10px; background: #f8f9fa; padding: 10px; border: 1px solid #dee2e6; border-radius: 5px; font-family: Arial, sans-serif; font-size: 14px;">
            <p style="margin: 0; color: #6c757d;"><strong>PDF Export:</strong> DOMPDF not installed. Use browser print (Ctrl+P) to save as PDF.</p>
        </div>';
    }
    
} catch (PDOException $e) {
    error_log('Error generating budget PDF: ' . $e->getMessage());
    die('Error generating PDF');
}
?>
