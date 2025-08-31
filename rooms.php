<?php
// rooms.php
require_once 'config.php';
check_session();

$conn = connectDB();

// Handle room operations
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $room_number = sanitize_input($_POST['room_number']);
                $room_type = sanitize_input($_POST['room_type']);
                $capacity = (int)$_POST['capacity'];
                $floor_number = (int)$_POST['floor_number'];
                
                $stmt = $conn->prepare("INSERT INTO rooms (room_number, room_type, capacity, floor_number) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssii", $room_number, $room_type, $capacity, $floor_number);
                $stmt->execute();
                break;
                
            case 'update':
                $room_id = (int)$_POST['room_id'];
                $status = sanitize_input($_POST['status']);
                
                $stmt = $conn->prepare("UPDATE rooms SET status = ? WHERE room_id = ?");
                $stmt->bind_param("si", $status, $room_id);
                $stmt->execute();
                break;
        }
    }
}

// Get all rooms
$result = $conn->query("SELECT * FROM rooms ORDER BY floor_number, room_number");
$rooms = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Room Management - Hospital Management System</title>
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
        
        .room-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .room-card {
            background-color: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .room-card.Available {
            border-left: 4px solid #4CAF50;
        }
        
        .room-card.Occupied {
            border-left: 4px solid #f44336;
        }
        
        .room-card.Under-Maintenance {
            border-left: 4px solid #ff9800;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
        }
        
        input, select {
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
        
        .status-Available {
            background-color: #e8f5e9;
            color: #4CAF50;
        }
        
        .status-Occupied {
            background-color: #ffebee;
            color: #f44336;
        }
        
        .status-Under-Maintenance {
            background-color: #fff3e0;
            color: #ff9800;
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
    <div class="container">
        <h1>Room Management</h1>
         <div style="margin: 10px;">
<a href="dashboard.php" class="back-to-dashboard">Back to Dashboard</a>
</div>
        <!-- Add Room Form -->
        <div class="room-card">
            <h2>Add New Room</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add">
                
                <div class="form-group">
                    <label for="room_number">Room Number:</label>
                    <input type="text" id="room_number" name="room_number" required>
                </div>
                
                <div class="form-group">
                    <label for="room_type">Room Type:</label>
                    <select id="room_type" name="room_type" required>
                        <option value="General">General</option>
                        <option value="Private">Private</option>
                        <option value="ICU">ICU</option>
                        <option value="Operation Theatre">Operation Theatre</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="capacity">Capacity:</label>
                    <input type="number" id="capacity" name="capacity" required min="1">
                </div>
                
                <div class="form-group">
                    <label for="floor_number">Floor Number:</label>
                    <input type="number" id="floor_number" name="floor_number" required min="1">
                </div>
                
                <button type="submit">Add Room</button>
            </form>
        </div>
        
        <!-- Room Grid -->
        <div class="room-grid">
            <?php foreach ($rooms as $room): ?>
                <div class="room-card <?php echo $room['status']; ?>">
                    <h3>Room <?php echo htmlspecialchars($room['room_number']); ?></h3>
                    <p>Type: <?php echo htmlspecialchars($room['room_type']); ?></p>
                    <p>Floor: <?php echo htmlspecialchars($room['floor_number']); ?></p>
                    <p>Capacity: <?php echo htmlspecialchars($room['capacity']); ?></p>
                    <p>Currently Occupied: <?php echo htmlspecialchars($room['occupied']); ?></p>
                    <div class="status-badge status-<?php echo $room['status']; ?>">
                        <?php echo htmlspecialchars($room['status']); ?>
                    </div>
                    
                    <form method="POST" action="" style="margin-top: 10px;">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="room_id" value="<?php echo $room['room_id']; ?>">
                        <select name="status" onchange="this.form.submit()">
                            <option value="Available" <?php echo $room['status'] == 'Available' ? 'selected' : ''; ?>>Available</option>
                            <option value="Occupied" <?php echo $room['status'] == 'Occupied' ? 'selected' : ''; ?>>Occupied</option>
                            <option value="Under Maintenance" <?php echo $room['status'] == 'Under Maintenance' ? 'selected' : ''; ?>>Under Maintenance</option>
                        </select>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>