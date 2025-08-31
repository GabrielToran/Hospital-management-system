<?php
// jobs.php
require_once 'config.php';
check_session();

$conn = connectDB();

// Handle department and staff operations
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_department':
                $name = sanitize_input($_POST['department_name']);
                $description = sanitize_input($_POST['description']);
                
                $stmt = $conn->prepare("INSERT INTO departments (name, description) VALUES (?, ?)");
                $stmt->bind_param("ss", $name, $description);
                $stmt->execute();
                break;
                
            case 'add_staff':
                $first_name = sanitize_input($_POST['first_name']);
                $last_name = sanitize_input($_POST['last_name']);
                $department_id = (int)$_POST['department_id'];
                $role = sanitize_input($_POST['role']);
                $email = sanitize_input($_POST['email']);
                $phone = sanitize_input($_POST['phone']);
                $hire_date = sanitize_input($_POST['hire_date']);
                
                // Start transaction
                $conn->begin_transaction();
                
                try {
                    // Insert staff record
                    $stmt = $conn->prepare("INSERT INTO staff (first_name, last_name, department_id, role, email, phone, hire_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssissss", $first_name, $last_name, $department_id, $role, $email, $phone, $hire_date);
                    $stmt->execute();
                    
                    // Generate username and password for new staff
                    $staff_id = $conn->insert_id;
                    $username = strtolower($first_name[0] . $last_name . $staff_id);
                    $temp_password = bin2hex(random_bytes(8));
                    $password_hash = password_hash($temp_password, PASSWORD_DEFAULT);
                    
                    // Create user account
                    $stmt = $conn->prepare("INSERT INTO users (username, password_hash, staff_id, role) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("ssis", $username, $password_hash, $staff_id, $role);
                    $stmt->execute();
                    
                    $conn->commit();
                    
                    // Store temporary password to show to admin (in real system, would email this)
                    $_SESSION['temp_credentials'] = [
                        'username' => $username,
                        'password' => $temp_password
                    ];
                } catch (Exception $e) {
                    $conn->rollback();
                    log_error("Failed to add staff member: " . $e->getMessage());
                    throw $e;
                }
                break;
                
            case 'update_staff_status':
                $staff_id = (int)$_POST['staff_id'];
                $status = sanitize_input($_POST['status']);
                
                $stmt = $conn->prepare("UPDATE staff SET status = ? WHERE staff_id = ?");
                $stmt->bind_param("si", $status, $staff_id);
                $stmt->execute();
                
                // Also update user account if staff is made inactive
                if ($status == 'Inactive') {
                    $stmt = $conn->prepare("UPDATE users SET is_active = 0 WHERE staff_id = ?");
                    $stmt->bind_param("i", $staff_id);
                    $stmt->execute();
                }
                break;
        }
    }
}

// Get departments
$result = $conn->query("SELECT * FROM departments ORDER BY name");
$departments = $result->fetch_all(MYSQLI_ASSOC);

// Get staff members with department info
$result = $conn->query("
    SELECT s.*, d.name as department_name
    FROM staff s
    JOIN departments d ON s.department_id = d.department_id
    ORDER BY s.last_name, s.first_name
");
$staff = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Management - Hospital Management System</title>
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
        }
        
        button:hover {
            background-color: #45a049;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.9em;
        }
        
        .status-Active {
            background-color: #e8f5e9;
            color: #4CAF50;
        }
        
        .status-Inactive {
            background-color: #ffebee;
            color: #f44336;
        }
        
        .status-On-Leave {
            background-color: #fff3e0;
            color: #ff9800;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
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
         .back-to-dashboard {
        position: fixed;
        bottom: 20px;
        left: 20px;
        background-color: #007bff;
        color: white;
        padding: 10px 15px;
        border-radius: 5px;
        text-decoration: none;
    </style>
</head>
<body>
<?php include 'header.php'; ?>
    <div class="container">
        <h1>Job Management</h1>
         <div style="margin: 10px;">
<a href="dashboard.php" class="back-to-dashboard">Back to Dashboard</a>
</div>
        <?php if (isset($_SESSION['temp_credentials'])): ?>
            <div class="alert">
                New staff account created:<br>
                Username: <?php echo htmlspecialchars($_SESSION['temp_credentials']['username']); ?><br>
                Temporary Password: <?php echo htmlspecialchars($_SESSION['temp_credentials']['password']); ?><br>
                Please provide these credentials to the staff member securely.
            </div>
            <?php unset($_SESSION['temp_credentials']); ?>
        <?php endif; ?>
        
        <div class="grid">
            <!-- Department Management -->
            <div class="card">
                <h2>Add Department</h2>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="add_department">
                    
                    <div class="form-group">
                        <label for="department_name">Department Name:</label>
                        <input type="text" id="department_name" name="department_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description:</label>
                        <textarea id="description" name="description" rows="3"></textarea>
                    </div>
                    
                    <button type="submit">Add Department</button>
                </form>
            </div>
            
            <!-- Staff Management -->
            <div class="card">
                <h2>Add Staff Member</h2>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="add_staff">
                    
                    <div class="form-group">
                        <label for="first_name">First Name:</label>
                        <input type="text" id="first_name" name="first_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="last_name">Last Name:</label>
                        <input type="text" id="last_name" name="last_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="department_id">Department:</label>
                        <select id="department_id" name="department_id" required>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['department_id']; ?>">
                                    <?php echo htmlspecialchars($dept['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="role">Role:</label>
                        <select id="role" name="role" required>
                            <option value="Doctor">Doctor</option>
                            <option value="Nurse">Nurse</option>
                            <option value="Admin">Admin</option>
                            <option value="Support Staff">Support Staff</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email:</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone:</label>
                        <input type="tel" id="phone" name="phone" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="hire_date">Hire Date:</label>
                        <input type="date" id="hire_date" name="hire_date" required>
                    </div>
                    
                    <button type="submit">Add Staff Member</button>
                </form>
            </div>
        </div>
        
        <!-- Staff List -->
        <div class="card">
            <h2>Staff List</h2>
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Role</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($staff as $member): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($member['department_name']); ?></td>
                            <td><?php echo htmlspecialchars($member['role']); ?></td>
                            <td><?php echo htmlspecialchars($member['email']); ?></td>
                            <td><?php echo htmlspecialchars($member['phone']); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $member['status']; ?>">
                                    <?php echo htmlspecialchars($member['status']); ?>
                                </span>
                            </td>
                            <td>
                                <form method="POST" action="" style="display: inline;">
                                    <input type="hidden" name="action" value="update_staff_status">
                                    <input type="hidden" name="staff_id" value="<?php echo $member['staff_id']; ?>">
                                    <select name="status" onchange="this.form.submit()">
                                        <option value="Active" <?php echo $member['status'] == 'Active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="Inactive" <?php echo $member['status'] == 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                                        <option value="On Leave" <?php echo $member['status'] == 'On Leave' ? 'selected' : ''; ?>>On Leave</option>
                                    </select>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>