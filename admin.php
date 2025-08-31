<?php


// admin.php
require_once 'config.php';
check_session();

// Check if user has admin privileges
if ($_SESSION['role'] !== 'Admin') {
    header("Location: dashboard.php");
    exit();
}

$conn = connectDB();

// Handle administrative operations
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_inventory':
                $name = sanitize_input($_POST['item_name']);
                $category = sanitize_input($_POST['category']);
                $quantity = (int)$_POST['quantity'];
                $unit = sanitize_input($_POST['unit']);
                $reorder_level = (int)$_POST['reorder_level'];
                
                $stmt = $conn->prepare("INSERT INTO inventory (name, category, quantity, unit, reorder_level) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("ssisi", $name, $category, $quantity, $unit, $reorder_level);
                $stmt->execute();
                break;
                
            case 'update_inventory':
                $item_id = (int)$_POST['item_id'];
                $quantity = (int)$_POST['quantity'];
                $reorder_level = (int)$_POST['reorder_level'];
                
                $stmt = $conn->prepare("UPDATE inventory SET quantity = ?, reorder_level = ? WHERE item_id = ?");
                $stmt->bind_param("iii", $quantity, $reorder_level, $item_id);
                $stmt->execute();
                break;
                
            case 'remove_inventory':
                $item_id = (int)$_POST['item_id'];
                
                $stmt = $conn->prepare("DELETE FROM inventory WHERE item_id = ?");
                $stmt->bind_param("i", $item_id);
                $stmt->execute();
                break;
                
            case 'add_expense':
                $category = sanitize_input($_POST['expense_category']);
                $amount = (float)$_POST['amount'];
                $description = sanitize_input($_POST['description']);
                $date = sanitize_input($_POST['date']);
                $staff_id = (int)$_POST['recorded_by'];
                
                $stmt = $conn->prepare("INSERT INTO expenses (category, amount, description, date, recorded_by) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sdssi", $category, $amount, $description, $date, $staff_id);
                $stmt->execute();
                break;
                
            case 'remove_expense':
                $expense_id = (int)$_POST['expense_id'];
                
                $stmt = $conn->prepare("DELETE FROM expenses WHERE expense_id = ?");
                $stmt->bind_param("i", $expense_id);
                $stmt->execute();
                break;
        }
    }
}

// Get inventory items
$result = $conn->query("SELECT * FROM inventory ORDER BY category, name");
$inventory = $result->fetch_all(MYSQLI_ASSOC);

// Get expenses
$result = $conn->query("
    SELECT e.*, s.first_name, s.last_name
    FROM expenses e
    JOIN staff s ON e.recorded_by = s.staff_id
    ORDER BY e.date DESC
");
$expenses = $result->fetch_all(MYSQLI_ASSOC);

// Calculate low stock items
$low_stock = array_filter($inventory, function($item) {
    return $item['quantity'] <= $item['reorder_level'];
});
    
    // Calculate total expenses by category
    $expense_categories = [];
    foreach ($expenses as $expense) {
        $category = $expense['category'];
        if (!isset($expense_categories[$category])) {
            $expense_categories[$category] = 0;
        }
        $expense_categories[$category] += $expense['amount'];
    }
    ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hospital Administration - Hospital Management System</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .card {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            color: #333;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        
        button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 5px;
        }
        
        button:hover {
            background-color: #45a049;
        }
         .btn-danger {
            background-color: #dc3545;
        }
        
        .btn-danger:hover {
            background-color: #c82333;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .alert-warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
        
        .alert-info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        th {
            background-color: #f8f9fa;
        }
        
        tr:hover {
            background-color: #f5f5f5;
        }
        
        .status-low {
            color: #dc3545;
            font-weight: bold;
        }
        
        .summary-box {
            background-color: #e9ecef;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        
        .chart-container {
            height: 300px;
            margin: 20px 0;
        }
         .back-to-dashboard {
        position: fixed;
        bottom: 20px;
        left: 20px;
        background-color: #007bff;
        color: white;
        padding: 10px 15px;
        border-radius: 5px;
        text-decoration: none;
         }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 50%;
            border-radius: 8px;
        }
        
        .confirm-delete {
            color: #dc3545;
            font-weight: bold;
        }
    </style>
</head>
<body>
<?php include 'header.php'; ?>
    <div class="container">
        <h1>Hospital Administration</h1>
        <div style="margin: 10px;">
<a href="dashboard.php" class="back-to-dashboard">Back to Dashboard</a>
</div>
        
        <!-- Inventory Alerts -->
        <?php if (!empty($low_stock)): ?>
            <div class="alert alert-warning">
                <h3>Low Stock Alert</h3>
                <ul>
                    <?php foreach ($low_stock as $item): ?>
                        <li>
                            <?php echo htmlspecialchars($item['name']); ?> - 
                            Current quantity: <?php echo $item['quantity']; ?> <?php echo $item['unit']; ?> 
                            (Reorder level: <?php echo $item['reorder_level']; ?>)
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <div class="grid">
            <!-- Inventory Management -->
            <div class="card">
                <h2>Add Inventory Item</h2>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="add_inventory">
                    
                    <div class="form-group">
                        <label for="item_name">Item Name:</label>
                        <input type="text" id="item_name" name="item_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="category">Category:</label>
                        <select id="category" name="category" required>
                            <option value="Medicine">Medicine</option>
                            <option value="Equipment">Equipment</option>
                            <option value="Supplies">Supplies</option>
                            <option value="Laboratory">Laboratory</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="quantity">Quantity:</label>
                        <input type="number" id="quantity" name="quantity" required min="0">
                    </div>
                    
                    <div class="form-group">
                        <label for="unit">Unit:</label>
                        <input type="text" id="unit" name="unit" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="reorder_level">Reorder Level:</label>
                        <input type="number" id="reorder_level" name="reorder_level" required min="0">
                    </div>
                    
                    <button type="submit">Add Item</button>
                </form>
            </div>
            
            <!-- Expense Management -->
            <div class="card">
                <h2>Add Expense</h2>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="add_expense">
                    
                    <div class="form-group">
                        <label for="expense_category">Category:</label>
                        <select id="expense_category" name="expense_category" required>
                            <option value="Utilities">Utilities</option>
                            <option value="Supplies">Supplies</option>
                            <option value="Maintenance">Maintenance</option>
                            <option value="Salaries">Salaries</option>
                            <option value="Equipment">Equipment</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="amount">Amount:</label>
                        <input type="number" id="amount" name="amount" required min="0" step="0.01">
                    </div>
                    
                    <div class="form-group">
                        <label for="date">Date:</label>
                        <input type="date" id="date" name="date" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description:</label>
                        <textarea id="description" name="description" rows="3" required></textarea>
                    </div>
                    
                     <div class="form-group">
            <label for="recorded_by">Recorded By:</label>
            <select id="recorded_by" name="recorded_by" required>
                <?php
                // Fetch staff members for dropdown
                $staff_result = $conn->query("SELECT staff_id, first_name, last_name FROM staff ORDER BY last_name, first_name");
                while ($staff = $staff_result->fetch_assoc()) {
                    echo '<option value="' . $staff['staff_id'] . '">' . 
                         htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']) . '</option>';
                }
                ?>
            </select>
        </div>
        
                    <button type="submit">Add Expense</button>
                </form>
            </div>
        </div>
        
        <!-- Inventory List -->
        <div class="card">
            <h2>Inventory</h2>
            <table>
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Category</th>
                        <th>Quantity</th>
                        <th>Unit</th>
                        <th>Reorder Level</th>
                        <th>Last Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($inventory as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['name']); ?></td>
                            <td><?php echo htmlspecialchars($item['category']); ?></td>
                            <td class="<?php echo $item['quantity'] <= $item['reorder_level'] ? 'status-low' : ''; ?>">
                                <?php echo htmlspecialchars($item['quantity']); ?>
                            </td>
                            <td><?php echo htmlspecialchars($item['unit']); ?></td>
                            <td><?php echo htmlspecialchars($item['reorder_level']); ?></td>
                            <td><?php echo htmlspecialchars($item['last_updated']); ?></td>
                            <td>
                                <button onclick="showUpdateForm(<?php echo htmlspecialchars(json_encode($item)); ?>)">
                                    Update
                                </button>
                                <button class="btn-danger" onclick="confirmRemoveInventory(<?php echo $item['item_id']; ?>, '<?php echo htmlspecialchars($item['name'], ENT_QUOTES); ?>')">
                                    Remove
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Expense Summary -->
        <div class="card">
            <h2>Expense Summary</h2>
            <div class="grid">
                <?php foreach ($expense_categories as $category => $total): ?>
                    <div class="summary-box">
                        <h3><?php echo htmlspecialchars($category); ?></h3>
                        <p>Total: $<?php echo number_format($total, 2); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <h3>Recent Expenses</h3>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Category</th>
                        <th>Amount</th>
                        <th>Description</th>
                        <th>Recorded By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($expenses as $expense): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($expense['date']); ?></td>
                            <td><?php echo htmlspecialchars($expense['category']); ?></td>
                            <td>$<?php echo number_format($expense['amount'], 2); ?></td>
                            <td><?php echo htmlspecialchars($expense['description']); ?></td>
                            <td>
                                <?php echo htmlspecialchars($expense['first_name'] . ' ' . $expense['last_name']); ?>
                            </td>
                            <td>
                                <button class="btn-danger" onclick="confirmRemoveExpense(<?php echo $expense['expense_id']; ?>, '<?php echo htmlspecialchars($expense['date'], ENT_QUOTES); ?> - $<?php echo number_format($expense['amount'], 2); ?>')">
                                    Remove
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Update Inventory Modal -->
    <div id="updateModal" class="modal">
        <div class="modal-content">
            <h3>Update Inventory</h3>
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_inventory">
                <input type="hidden" name="item_id" id="update_item_id">
                
                <div class="form-group">
                    <label for="update_quantity">Quantity:</label>
                    <input type="number" id="update_quantity" name="quantity" required min="0">
                </div>
                
                <div class="form-group">
                    <label for="update_reorder_level">Reorder Level:</label>
                    <input type="number" id="update_reorder_level" name="reorder_level" required min="0">
                </div>
                
                <button type="submit">Update</button>
                <button type="button" onclick="hideUpdateForm()">Cancel</button>
            </form>
        </div>
    </div>
     <!-- Remove Inventory Confirmation Modal -->
    <div id="removeInventoryModal" class="modal">
        <div class="modal-content">
            <h3>Confirm Removal</h3>
            <p>Are you sure you want to remove the inventory item: <span id="removeInventoryName" class="confirm-delete"></span>?</p>
            <p>This action cannot be undone.</p>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="remove_inventory">
                <input type="hidden" name="item_id" id="remove_item_id">
                
                <button type="submit" class="btn-danger">Confirm Remove</button>
                <button type="button" onclick="hideRemoveInventoryForm()">Cancel</button>
            </form>
        </div>
    </div>
    
    <!-- Remove Expense Confirmation Modal -->
    <div id="removeExpenseModal" class="modal">
        <div class="modal-content">
            <h3>Confirm Removal</h3>
            <p>Are you sure you want to remove the expense: <span id="removeExpenseName" class="confirm-delete"></span>?</p>
            <p>This action cannot be undone.</p>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="remove_expense">
                <input type="hidden" name="expense_id" id="remove_expense_id">
                
                <button type="submit" class="btn-danger">Confirm Remove</button>
                <button type="button" onclick="hideRemoveExpenseForm()">Cancel</button>
            </form>
        </div>
    </div>
    
    <script>
        function showUpdateForm(item) {
            document.getElementById('update_item_id').value = item.item_id;
            document.getElementById('update_quantity').value = item.quantity;
            document.getElementById('update_reorder_level').value = item.reorder_level;
            document.getElementById('updateModal').style.display = 'block';
        }
        
        function hideUpdateForm() {
            document.getElementById('updateModal').style.display = 'none';
        }
        
        function confirmRemoveInventory(itemId, itemName) {
            document.getElementById('remove_item_id').value = itemId;
            document.getElementById('removeInventoryName').textContent = itemName;
            document.getElementById('removeInventoryModal').style.display = 'block';
        }
        
        function hideRemoveInventoryForm() {
            document.getElementById('removeInventoryModal').style.display = 'none';
        }
        
        function confirmRemoveExpense(expenseId, expenseDesc) {
            document.getElementById('remove_expense_id').value = expenseId;
            document.getElementById('removeExpenseName').textContent = expenseDesc;
            document.getElementById('removeExpenseModal').style.display = 'block';
        }
        
        function hideRemoveExpenseForm() {
            document.getElementById('removeExpenseModal').style.display = 'none';
        }
    </script>
</body>
</html>