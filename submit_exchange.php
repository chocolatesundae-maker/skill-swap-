<?php
session_start();
header('Content-Type: application/json');

// --- 1. Security Check ---
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to send an exchange request.']);
    http_response_code(401);
    exit;
}

// --- 2. Database Connection ---
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "local_skill_swap";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    http_response_code(500);
    exit;
}

// --- 3. Gather and Validate Input ---
$sender_id = (int) $_SESSION['user_id'];
$offered_skill_id = (int) ($_POST['offered_skill_id'] ?? 0);
$requested_skill_id = (int) ($_POST['requested_skill_id'] ?? 0);
$receiver_id = (int) ($_POST['offerer_id'] ?? 0);

// Basic Validation
if ($offered_skill_id <= 0 || $requested_skill_id <= 0 || $receiver_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid or missing skill/user IDs.']);
    exit;
}

if ($sender_id === $receiver_id) {
    echo json_encode(['success' => false, 'message' => 'You cannot send an exchange request to yourself.']);
    exit;
}

// --- 4. Process Request: Insert into 'requests' table ---
$conn->begin_transaction();
$request_success = false;
$notification_success = false;

try {
    // 4A. Get the names of the skills for the notification message
    $stmt_names = $conn->prepare(
        "SELECT 
            (SELECT skill_name FROM skills WHERE id = ?) as offered_name,
            (SELECT skill_name FROM skills WHERE id = ?) as requested_name,
            (SELECT name FROM users WHERE id = ?) as sender_name"
    );
    $stmt_names->bind_param("iii", $offered_skill_id, $requested_skill_id, $sender_id);
    $stmt_names->execute();
    $result_names = $stmt_names->get_result()->fetch_assoc();
    $stmt_names->close();
    
    $offered_name = $result_names['offered_name'] ?? 'Your skill';
    $requested_name = $result_names['requested_name'] ?? 'Their skill';
    $sender_name = $result_names['sender_name'] ?? 'A user';


    // 4B. Insert the Exchange Request
    $stmt_request = $conn->prepare(
        "INSERT INTO requests (sender_id, receiver_id, offered_skill_id, requested_skill_id, status, created_at) 
         VALUES (?, ?, ?, ?, 'pending', NOW())"
    );
    $status = 'pending';
    $stmt_request->bind_param("iiii", $sender_id, $receiver_id, $offered_skill_id, $requested_skill_id);
    $request_success = $stmt_request->execute();
    $stmt_request->close();
    
    // 4C. Insert the Notification
    if ($request_success) {
        $notification_message = 
            $sender_name . " wants to exchange! They are offering '" . $offered_name . 
            "' for your skill '" . $requested_name . "'.";
        
        $stmt_notify = $conn->prepare(
            "INSERT INTO notifications (user_id, type, message, is_read, created_at) 
             VALUES (?, 'new_request', ?, 0, NOW())"
        );
        $stmt_notify->bind_param("is", $receiver_id, $notification_message);
        $notification_success = $stmt_notify->execute();
        $stmt_notify->close();
    }
    
    // --- 5. Commit or Rollback ---
    if ($request_success && $notification_success) {
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Exchange request successfully sent to the user.']);
    } else {
        $conn->rollback();
        // Log the failure details if possible
        echo json_encode(['success' => false, 'message' => 'Failed to record request and/or notification. Please try again.']);
        http_response_code(500);
    }

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
    http_response_code(500);
}

$conn->close();
?>