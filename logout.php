<?php
// logout.php
require_once 'config.php';
session_start();

if (isset($_SESSION['user_id'])) {
    // Log the logout action
    $conn = connectDB();
    $stmt = $conn->prepare("INSERT INTO access_logs (user_id, action, ip_address) VALUES (?, 'logout', ?)");
    $stmt->bind_param("is", $_SESSION['user_id'], $_SERVER['REMOTE_ADDR']);
    $stmt->execute();
    
    // Clear all session variables
    $_SESSION = array();
    
    // Destroy the session
    session_destroy();
}

// Redirect to login page
header("Location: login.php");
exit();
?>