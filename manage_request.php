<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: skillswap.php");
    exit();
}

// DB connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "local_skill_swap";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    $message = "Database connection failed: " . $conn->connect_error;
    header("Location: request.php?status_message=" . urlencode($message));
    exit();
}

$current_user_id = (int) $_SESSION['user_id'];

// Accept input via GET or POST (more robust)
$request_id_raw = isset($_REQUEST['request_id']) ? $_REQUEST['request_id'] : null;
$action_raw     = isset($_REQUEST['action']) ? $_REQUEST['action'] : null;

// Validate request_id is an integer > 0
$request_id = filter_var($request_id_raw, FILTER_VALIDATE_INT);
if ($request_id === false || $request_id === null || $request_id <= 0) {
    $message = "Invalid request ID specified. (received: " . htmlspecialchars($request_id_raw) . ")";
    header("Location: request.php?status_message=" . urlencode($message));
    exit();
}

// Normalize action
$action = is_string($action_raw) ? strtolower(trim($action_raw)) : '';
if ($action === 'accept' || $action === 'accepted') {
    $new_status = 'accepted';
} elseif ($action === 'reject' || $action === 'decline' || $action === 'rejected') {
    $new_status = 'rejected';
} else {
    $message = "Invalid action specified. Use 'accept' or 'reject'. (received: " . htmlspecialchars($action_raw) . ")";
    header("Location: request.php?status_message=" . urlencode($message));
    exit();
}

// Now process safely inside a transaction
$conn->begin_transaction();

try {
    // Verify that current user is the receiver and the request is still pending
    $stmt_verify = $conn->prepare("
        SELECT r.sender_id, s1.skill_name AS offered_skill, s2.skill_name AS requested_skill
        FROM requests r
        JOIN skills s1 ON r.offered_skill_id = s1.id
        JOIN skills s2 ON r.requested_skill_id = s2.id
        WHERE r.id = ? AND r.receiver_id = ? AND r.status = 'pending'
        FOR UPDATE
    ");
    if (!$stmt_verify) throw new Exception("Prepare failed (verify): " . $conn->error);

    $stmt_verify->bind_param("ii", $request_id, $current_user_id);
    $stmt_verify->execute();
    $result = $stmt_verify->get_result();
    $request_details = $result->fetch_assoc();
    $stmt_verify->close();

    if (!$request_details) {
        throw new Exception("Request not found, already processed, or you are not authorized to manage it.");
    }

    $sender_id = (int)$request_details['sender_id'];
    $offered_skill = $request_details['offered_skill'];
    $requested_skill = $request_details['requested_skill'];

    // Update request
    $stmt_update = $conn->prepare("
        UPDATE requests
        SET status = ?
        WHERE id = ? AND receiver_id = ? AND status = 'pending'
    ");
    if (!$stmt_update) throw new Exception("Prepare failed (update): " . $conn->error);

    $stmt_update->bind_param("sii", $new_status, $request_id, $current_user_id);
    if (!$stmt_update->execute() || $stmt_update->affected_rows === 0) {
        throw new Exception("Failed to update request status (maybe already processed).");
    }
    $stmt_update->close();

    // Create notification for sender
    if ($new_status === 'accepted') {
        $notification_message = "Success! Your request has been ACCEPTED. The user agreed to swap their '{$requested_skill}' for your '{$offered_skill}'.";
        $notification_type = 'request_accepted';
    } else {
        $notification_message = "Update: Your request has been REJECTED. The user declined swapping '{$offered_skill}' for their '{$requested_skill}'.";
        $notification_type = 'request_rejected';
    }

    $stmt_notify = $conn->prepare("
        INSERT INTO notifications (user_id, type, message, is_read, created_at)
        VALUES (?, ?, ?, 0, NOW())
    ");
    if (!$stmt_notify) throw new Exception("Prepare failed (notify): " . $conn->error);
    $stmt_notify->bind_param("iss", $sender_id, $notification_type, $notification_message);
    $stmt_notify->execute();
    $stmt_notify->close();

    $conn->commit();

    $status_verb = ($new_status === 'accepted') ? 'accepted' : 'rejected';
    $message = "Exchange request successfully {$status_verb}. Sender has been notified.";

} catch (Exception $e) {
    $conn->rollback();
    // Give a useful message but avoid leaking sensitive DB internals in production
    $message = "Error processing request: " . $e->getMessage();
}

$conn->close();
header("Location: request.php?status_message=" . urlencode($message));
exit();
