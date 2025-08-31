<?php
// patients.php
require_once 'config.php';
check_session();

$conn = connectDB();

// Handle patient operations
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $first_name = sanitize_input($_POST['first_name']);
                $last_name = sanitize_input($_POST['last_name']);
                $dob = sanitize_input($_POST['date_of_birth']);
                $gender = sanitize_input($_POST['gender']);
                $address = sanitize_input($_POST['address']);
                $phone = sanitize_input($_POST['phone']);
                $email = sanitize_input($_POST['email']);
                $emergency_contact = sanitize_input($_POST['emergency_contact']);
                $blood_group = sanitize_input($_POST['blood_group']);
                
                $stmt = $conn->prepare("INSERT INTO patients (first_name, last_name, date_of_birth, gender, address, phone, email, emergency_contact, blood_group) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssssss", $first_name, $last_name, $dob, $gender, $address, $phone, $email, $emergency_contact, $blood_group);
                $stmt->execute();
                break;
                
            case 'admit':
                $patient_id = (int)$_POST['patient_id'];
                $room_id = (int)$_POST['room_id'];
                $doctor_id = (int)$_POST['doctor_id'];
                $diagnosis = sanitize_input($_POST['diagnosis']);
                
                // Start transaction
                $conn->begin_transaction();
                
                try {
                    // Create admission record
                    $stmt = $conn->prepare("INSERT INTO patient_admissions (patient_id, room_id, admission_date, doctor_id, diagnosis) VALUES (?, ?, NOW(), ?, ?)");
                    $stmt->bind_param("iiis", $patient_id, $room_id, $doctor_id, $diagnosis);
                    $stmt->execute();
                    
                    // Update room occupancy
                    $stmt = $conn->prepare("UPDATE rooms SET occupied = occupied + 1, status = 'Occupied' WHERE room_id = ? AND status = 'Available'");
                    $stmt->bind_param("i", $room_id);
                    $stmt->execute();
                    
                    $conn->commit();
                } catch (Exception $e) {
                    $conn->rollback();
                    log_error("Failed to admit patient: " . $e->getMessage());
                    throw $e;
                }
                break;
                
            case 'discharge':
                $admission_id = (int)$_POST['admission_id'];
                $discharge_notes = sanitize_input($_POST['discharge_notes']);
                $room_id = (int)$_POST['room_id'];
                
                // Start transaction
                $conn->begin_transaction();
                
                try {
                    // Update admission record with discharge date and notes
                    $stmt = $conn->prepare("UPDATE patient_admissions SET discharge_date = NOW(), discharge_notes = ? WHERE admission_id = ? AND discharge_date IS NULL");
                    $stmt->bind_param("si", $discharge_notes, $admission_id);
                    $stmt->execute();
                    
                    // Update room status
                    $stmt = $conn->prepare("UPDATE rooms SET occupied = occupied - 1, status = CASE WHEN occupied - 1 <= 0 THEN 'Available' ELSE status END WHERE room_id = ?");
                    $stmt->bind_param("i", $room_id);
                    $stmt->execute();
                    
                    $conn->commit();
                } catch (Exception $e) {
                    $conn->rollback();
                    log_error("Failed to discharge patient: " . $e->getMessage());
                    throw $e;
                }
                break;
                
            case 'remove':
                $patient_id = (int)$_POST['patient_id'];
                
                // Check if patient has active admissions
                $stmt = $conn->prepare("SELECT COUNT(*) FROM patient_admissions WHERE patient_id = ? AND discharge_date IS NULL");
                $stmt->bind_param("i", $patient_id);
                $stmt->execute();
                $stmt->bind_result($active_admissions);
                $stmt->fetch();
                $stmt->close();
                
                if ($active_admissions > 0) {
                    // Patient has active admissions, don't delete
                    $error_message = "Cannot remove patient with active admissions. Discharge the patient first.";
                } else {
                    // Start transaction
                    $conn->begin_transaction();
                    
                    try {
                        // Delete patient's admission records (if any)
                        $stmt = $conn->prepare("DELETE FROM patient_admissions WHERE patient_id = ?");
                        $stmt->bind_param("i", $patient_id);
                        $stmt->execute();
                        
                        // Delete patient
                        $stmt = $conn->prepare("DELETE FROM patients WHERE patient_id = ?");
                        $stmt->bind_param("i", $patient_id);
                        $stmt->execute();
                        
                        $conn->commit();
                    } catch (Exception $e) {
                        $conn->rollback();
                        log_error("Failed to remove patient: " . $e->getMessage());
                        throw $e;
                    }
                }
                break;
        }
    }
}

// Get all patients
$result = $conn->query("SELECT * FROM patients ORDER BY last_name, first_name");
$patients = $result->fetch_all(MYSQLI_ASSOC);

// Get patient admission status and details
$patient_status = [];
$patient_admissions = [];
$result = $conn->query("SELECT pa.admission_id, pa.patient_id, pa.room_id, pa.admission_date, pa.doctor_id, pa.diagnosis,
                        r.room_number, r.room_type,
                        s.first_name as doctor_first_name, s.last_name as doctor_last_name
                        FROM patient_admissions pa
                        JOIN rooms r ON pa.room_id = r.room_id
                        JOIN staff s ON pa.doctor_id = s.staff_id
                        WHERE pa.discharge_date IS NULL");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $patient_id = $row['patient_id'];
        if (!isset($patient_status[$patient_id])) {
            $patient_status[$patient_id] = 0;
        }
        $patient_status[$patient_id]++;
        
        if (!isset($patient_admissions[$patient_id])) {
            $patient_admissions[$patient_id] = [];
        }
        $patient_admissions[$patient_id][] = $row;
    }
}

// Get available rooms
$result = $conn->query("SELECT * FROM rooms WHERE status = 'Available'");
$available_rooms = $result->fetch_all(MYSQLI_ASSOC);

// Get doctors
$result = $conn->query("SELECT staff_id, first_name, last_name FROM staff WHERE role = 'Doctor' AND status = 'Active'");
$doctors = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Management - Hospital Management System</title>
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
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
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
        
        .btn-warning {
            background-color: #ffc107;
            color: #212529;
        }
        
        .btn-warning:hover {
            background-color: #e0a800;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
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
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .patient-status {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            margin-left: 5px;
        }
        
        .status-admitted {
            background-color: #cfe2ff;
            color: #084298;
        }
        
        .admission-details {
            background-color: #f8f9fa;
            padding: 15px;
            margin: 10px 0;
            border-radius: 6px;
            border-left: 3px solid #007bff;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Patient Management</h1>
        <div style="margin: 10px;">
            <a href="dashboard.php" class="back-to-dashboard">Back to Dashboard</a>
        </div>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <!-- Add Patient Form -->
        <div class="card">
            <h2>Add New Patient</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="first_name">First Name:</label>
                        <input type="text" id="first_name" name="first_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="last_name">Last Name:</label>
                        <input type="text" id="last_name" name="last_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="date_of_birth">Date of Birth:</label>
                        <input type="date" id="date_of_birth" name="date_of_birth" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="gender">Gender:</label>
                        <select id="gender" name="gender" required>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="blood_group">Blood Group:</label>
                        <select id="blood_group" name="blood_group" required>
                            <option value="A+">A+</option>
                            <option value="A-">A-</option>
                            <option value="B+">B+</option>
                            <option value="B-">B-</option>
                            <option value="AB+">AB+</option>
                            <option value="AB-">AB-</option>
                            <option value="O+">O+</option>
                            <option value="O-">O-</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone:</label>
                        <input type="tel" id="phone" name="phone" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email:</label>
                        <input type="email" id="email" name="email">
                    </div>
                    
                    <div class="form-group">
                        <label for="emergency_contact">Emergency Contact:</label>
                        <input type="tel" id="emergency_contact" name="emergency_contact" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="address">Address:</label>
                    <textarea id="address" name="address" required rows="3"></textarea>
                </div>
                
                <button type="submit">Add Patient</button>
            </form>
        </div>
        
        <!-- Patient List -->
        <div class="card">
            <h2>Patient List</h2>
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Gender</th>
                        <th>Date of Birth</th>
                        <th>Phone</th>
                        <th>Blood Group</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($patients as $patient): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($patient['gender']); ?></td>
                            <td><?php echo htmlspecialchars($patient['date_of_birth']); ?></td>
                            <td><?php echo htmlspecialchars($patient['phone']); ?></td>
                            <td><?php echo htmlspecialchars($patient['blood_group']); ?></td>
                            <td>
                                <?php if (isset($patient_status[$patient['patient_id']]) && $patient_status[$patient['patient_id']] > 0): ?>
                                    <span class="patient-status status-admitted">Currently Admitted</span>
                                <?php else: ?>
                                    <span>Outpatient</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button onclick="showAdmitForm(<?php echo $patient['patient_id']; ?>)">Admit</button>
                                <?php if (isset($patient_status[$patient['patient_id']]) && $patient_status[$patient['patient_id']] > 0): ?>
                                    <button class="btn-warning" onclick="showPatientAdmissions(<?php echo $patient['patient_id']; ?>)">Discharge</button>
                                <?php endif; ?>
                                <button class="btn-danger" onclick="confirmRemovePatient(<?php echo $patient['patient_id']; ?>, '<?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name'], ENT_QUOTES); ?>', <?php echo isset($patient_status[$patient['patient_id']]) ? $patient_status[$patient['patient_id']] : 0; ?>)">
                                    Remove
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Admit Patient Modal -->
        <div id="admitModal" class="modal">
            <div class="modal-content">
                <h3>Admit Patient</h3>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="admit">
                    <input type="hidden" name="patient_id" id="admit_patient_id">
                    
                    <div class="form-group">
                        <label for="room_id">Select Room:</label>
                        <select id="room_id" name="room_id" required>
                            <?php foreach ($available_rooms as $room): ?>
                                <option value="<?php echo $room['room_id']; ?>">
                                    Room <?php echo htmlspecialchars($room['room_number']); ?> (<?php echo htmlspecialchars($room['room_type']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="doctor_id">Assign Doctor:</label>
                        <select id="doctor_id" name="doctor_id" required>
                            <?php foreach ($doctors as $doctor): ?>
                                <option value="<?php echo $doctor['staff_id']; ?>">
                                    Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="diagnosis">Initial Diagnosis:</label>
                        <textarea id="diagnosis" name="diagnosis" required rows="3"></textarea>
                    </div>
                    
                    <button type="submit">Admit Patient</button>
                    <button type="button" onclick="hideAdmitForm()">Cancel</button>
                </form>
            </div>
        </div>
        
        <!-- Patient Admissions Modal -->
        <div id="admissionsModal" class="modal">
            <div class="modal-content">
                <h3>Current Admissions</h3>
                <div id="admissionsContainer">
                    <!-- Admission details will be populated here -->
                </div>
            </div>
        </div>
        
        <!-- Discharge Patient Modal -->
        <div id="dischargeModal" class="modal">
            <div class="modal-content">
                <h3>Discharge Patient</h3>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="discharge">
                    <input type="hidden" name="admission_id" id="discharge_admission_id">
                    <input type="hidden" name="room_id" id="discharge_room_id">
                    
                    <div class="form-group">
                        <label for="discharge_notes">Discharge Notes:</label>
                        <textarea id="discharge_notes" name="discharge_notes" required rows="4" placeholder="Enter treatment summary, follow-up instructions, medications, etc."></textarea>
                    </div>
                    
                    <button type="submit">Confirm Discharge</button>
                    <button type="button" onclick="hideDischargeForm()">Cancel</button>
                </form>
            </div>
        </div>
        
        <!-- Remove Patient Confirmation Modal -->
        <div id="removePatientModal" class="modal">
            <div class="modal-content">
                <h3>Confirm Patient Removal</h3>
                <p>Are you sure you want to remove <span id="removePatientName" class="confirm-delete"></span> from the system?</p>
                <p id="admissionWarning" style="display: none;" class="alert alert-danger">
                    <strong>Warning:</strong> This patient is currently admitted. Discharge the patient before removal.
                </p>
                <p id="removalConfirmation">This action cannot be undone and will remove all patient records.</p>
                
                <form method="POST" action="" id="removePatientForm">
                    <input type="hidden" name="action" value="remove">
                    <input type="hidden" name="patient_id" id="remove_patient_id">
                    
                    <button type="submit" class="btn-danger" id="confirmRemoveBtn">Confirm Remove</button>
                    <button type="button" onclick="hideRemovePatientForm()">Cancel</button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        function showAdmitForm(patientId) {
            document.getElementById('admit_patient_id').value = patientId;
            document.getElementById('admitModal').style.display = 'block';
        }
        
        function hideAdmitForm() {
            document.getElementById('admitModal').style.display = 'none';
        }
        
        function showPatientAdmissions(patientId) {
            // Get patient admissions
            const patientAdmissions = <?php echo json_encode($patient_admissions); ?>;
            const admissions = patientAdmissions[patientId] || [];
            
            // Clear previous content
            const container = document.getElementById('admissionsContainer');
            container.innerHTML = '';
            
            if (admissions.length === 0) {
                container.innerHTML = '<p>No active admissions found for this patient.</p>';
            } else {
                // Display each admission
                for (const admission of admissions) {
                    const admissionDate = new Date(admission.admission_date).toLocaleDateString();
                    
                    const admissionDiv = document.createElement('div');
                    admissionDiv.className = 'admission-details';
                    admissionDiv.innerHTML = `
                        <p><strong>Admission Date:</strong> ${admissionDate}</p>
                        <p><strong>Room:</strong> ${admission.room_number} (${admission.room_type})</p>
                        <p><strong>Doctor:</strong> Dr. ${admission.doctor_first_name} ${admission.doctor_last_name}</p>
                        <p><strong>Diagnosis:</strong> ${admission.diagnosis}</p>
                        <button class="btn-warning" onclick="showDischargeForm(${admission.admission_id}, ${admission.room_id})">
                            Discharge Patient
                        </button>
                    `;
                    
                    container.appendChild(admissionDiv);
                }
            }
            
            document.getElementById('admissionsModal').style.display = 'block';
        }
        
        function hideAdmissionsModal() {
            document.getElementById('admissionsModal').style.display = 'none';
        }
        
        function showDischargeForm(admissionId, roomId) {
            // Hide admissions modal and show discharge form
            document.getElementById('admissionsModal').style.display = 'none';
            
            document.getElementById('discharge_admission_id').value = admissionId;
            document.getElementById('discharge_room_id').value = roomId;
            document.getElementById('dischargeModal').style.display = 'block';
        }
        
        function hideDischargeForm() {
            document.getElementById('dischargeModal').style.display = 'none';
        }
        
        function confirmRemovePatient(patientId, patientName, admissionCount) {
            document.getElementById('remove_patient_id').value = patientId;
            document.getElementById('removePatientName').textContent = patientName;
            
            // Check if patient is currently admitted
            if (admissionCount > 0) {
                document.getElementById('admissionWarning').style.display = 'block';
                document.getElementById('removalConfirmation').style.display = 'none';
                document.getElementById('confirmRemoveBtn').style.display = 'none';
            } else {
                document.getElementById('admissionWarning').style.display = 'none';
                document.getElementById('removalConfirmation').style.display = 'block';
                document.getElementById('confirmRemoveBtn').style.display = 'inline-block';
            }
            
            document.getElementById('removePatientModal').style.display = 'block';
        }
        
        function hideRemovePatientForm() {
            document.getElementById('removePatientModal').style.display = 'none';
        }
        
        // Close modal when clicking outside of it
        window.onclick = function(event) {
            const modals = document.getElementsByClassName('modal');
            for (let i = 0; i < modals.length; i++) {
                if (event.target == modals[i]) {
                    modals[i].style.display = 'none';
                }
            }
        }
    </script>
</body>
</html>