<?php
// manage_data.php
session_start();
error_reporting(E_ALL); // Add these lines temporarily for full error visibility
ini_set('display_errors', 1);

// Security Check: Redirect non-admins or unauthenticated users
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: skillswap.php");
    exit();
}

// ----------------------------------------------------
// DATABASE CONNECTION
// ----------------------------------------------------
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "local_skill_swap";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ----------------------------------------------------
// GET PARAMETERS
// ----------------------------------------------------
$action = $_GET['action'] ?? '';
$type = $_GET['type'] ?? '';
// Ensure ID is an integer
$id = (int)($_GET['id'] ?? 0); 

$message = "";
$redirect_anchor = '';

if ($action && $type === 'user' && $id > 0) {
    $table = 'users';
    $redirect_anchor = '#users'; // Anchor to go back to the user table
    
    // ✅ Use the correct session variable for the current admin's ID
    $current_admin_id = $_SESSION['user_id'] ?? 0; 
    
    if ($action === 'delete') {
        // DELETE USER - Security check to prevent admin self-deletion
        if ($id == $current_admin_id) {
             $message = "Admin cannot delete their own account.";
        } else {
            $stmt = $conn->prepare("DELETE FROM $table WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $message = "User ID $id deleted successfully.";
            } else {
                $message = "Error deleting user: " . $conn->error;
            }
            $stmt->close();
        }
    
    } elseif ($action === 'toggle' && isset($_GET['status'])) {
        // SUSPEND/UNSUSPEND USER (uses the 'role' column)
        $new_status = ($_GET['status'] === 'suspended') ? 'suspended' : 'user';
        
        // Ensure the admin cannot suspend themselves!
        if ($id == $current_admin_id && $new_status == 'suspended') {
             $message = "Admin cannot suspend their own account.";
        } else {
            // ✅ THE CRITICAL SQL UPDATE
            $stmt = $conn->prepare("UPDATE $table SET role = ? WHERE id = ?");
            $stmt->bind_param("si", $new_status, $id);
            
            if ($stmt->execute()) {
                $status_word = ($new_status === 'suspended') ? 'Suspended' : 'Unsuspended (Active)';
                $message = "User ID $id status changed to $status_word.";
            } else {
                // This message is crucial for debugging database errors
                $message = "Error updating user status: " . $conn->error; 
            }
            $stmt->close();
        }
    }
} else {
     $message = "Invalid action or missing parameters.";
}

$conn->close();

// ----------------------------------------------------
// REDIRECT AFTER ACTION
// ----------------------------------------------------
// Redirect back to the dashboard with the status message and anchor
header("Location: admin_dashboard2.php?status=" . urlencode($message) . $redirect_anchor);
exit();
?>