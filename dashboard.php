<?php
// dashboard.php
require_once 'config.php';
check_session();

$conn = connectDB();

// Get user information
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("
    SELECT u.*, s.first_name, s.last_name, s.role as staff_role
    FROM users u
    LEFT JOIN staff s ON u.staff_id = s.staff_id
    WHERE u.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get system statistics based on user role
$stats = [];

if (in_array($user['role'], ['Admin', 'Doctor'])) {
    // Get total patients
    $result = $conn->query("SELECT COUNT(*) as total FROM patients");
    $stats['total_patients'] = $result->fetch_assoc()['total'];
    
    // Get occupied rooms
    $result = $conn->query("SELECT COUNT(*) as total FROM rooms WHERE status = 'Occupied'");
    $stats['occupied_rooms'] = $result->fetch_assoc()['total'];
    
    // Get total staff
    $result = $conn->query("SELECT COUNT(*) as total FROM staff WHERE status = 'Active'");
    $stats['active_staff'] = $result->fetch_assoc()['total'];
    
    // Get low stock items
    $result = $conn->query("SELECT COUNT(*) as total FROM inventory WHERE quantity <= reorder_level");
    $stats['low_stock_items'] = $result->fetch_assoc()['total'];
}

// Get recent activities
$stmt = $conn->prepare("
    SELECT al.*, u.username
    FROM access_logs al
    JOIN users u ON al.user_id = u.user_id
    ORDER BY al.timestamp DESC
    LIMIT 10
");
$stmt->execute();
$recent_activities = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Handle password change
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'change_password') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Verify current password
    $stmt = $conn->prepare("SELECT password_hash FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if (password_verify($current_password, $result['password_hash'])) {
        if ($new_password === $confirm_password) {
            // Update password
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
            $stmt->bind_param("si", $new_hash, $user_id);
            $stmt->execute();
            
            $success_message = "Password updated successfully!";
        } else {
            $error_message = "New passwords do not match!";
        }
    } else {
        $error_message = "Current password is incorrect!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Hospital Management System</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        
        .navbar {
            background-color: #333;
            padding: 1rem;
            color: white;
        }
        
        .navbar-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 20px;
        }
        
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .card {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .stat-card {
            text-align: center;
        }
        
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #4CAF50;
            margin: 10px 0;
        }
        
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .menu-item {
            background-color: #4CAF50;
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            text-decoration: none;
            transition: background-color 0.3s;
        }
        
        .menu-item:hover {
            background-color: #45a049;
        }
        
        .activity-item {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
        }
        
        input[type="password"] {
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
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
<?php include 'header.php'; ?>
    <nav class="navbar">
        <div class="navbar-content">
            <h1>Hospital Management System</h1>
            <div class="user-info">
                <span>Welcome, <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></span>
                <a href="logout.php" style="color: white;">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($stats)): ?>
            <div class="grid">
                <div class="card stat-card">
                    <h3>Total Patients</h3>
                    <div class="stat-number"><?php echo $stats['total_patients']; ?></div>
                </div>
                
                <div class="card stat-card">
                    <h3>Occupied Rooms</h3>
                    <div class="stat-number"><?php echo $stats['occupied_rooms']; ?></div>
                </div>
                
                <div class="card stat-card">
                    <h3>Active Staff</h3>
                    <div class="stat-number"><?php echo $stats['active_staff']; ?></div>
                </div>
                
                <div class="card stat-card">
                    <h3>Low Stock Items</h3>
                    <div class="stat-number"><?php echo $stats['low_stock_items']; ?></div>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <h2>Quick Access</h2>
            <div class="menu-grid">
                <?php if (in_array($user['role'], ['Admin', 'Doctor', 'Nurse'])): ?>
                    <a href="patients.php" class="menu-item">Patient Management</a>
                <?php endif; ?>
                
                <?php if (in_array($user['role'], ['Admin', 'Doctor'])): ?>
                    <a href="rooms.php" class="menu-item">Room Management</a>
                <?php endif; ?>
                
                <?php if ($user['role'] == 'Admin'): ?>
                    <a href="jobs.php" class="menu-item">Job Management</a>
                    <a href="admin.php" class="menu-item">Hospital Administration</a>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="grid">
            <div class="card">
                <h2>Recent Activities</h2>
                <?php foreach ($recent_activities as $activity): ?>
                    <div class="activity-item">
                        <strong><?php echo htmlspecialchars($activity['username']); ?></strong>
                        <?php echo htmlspecialchars($activity['action']); ?>
                        <br>
                        <small><?php echo htmlspecialchars($activity['timestamp']); ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="card">
                <h2>Change Password</h2>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="form-group">
                        <label for="current_password">Current Password:</label>
                        <input type="password" id="current_password" name="current_password" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password">New Password:</label>
                        <input type="password" id="new_password" name="new_password" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password:</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                    
                    <button type="submit">Change Password</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>