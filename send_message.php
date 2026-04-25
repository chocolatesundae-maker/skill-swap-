<?php
session_start();
header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

// 1. Check Authentication
$current_user_id = (int) ($_SESSION['user_id'] ?? 0);
if ($current_user_id === 0) {
    $response['message'] = 'Authentication required.';
    echo json_encode($response);
    exit();
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "local_skill_swap";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    $response['message'] = 'Database connection failed.';
    echo json_encode($response);
    exit();
}

$action = $_POST['action'] ?? '';

// --- ACTION: SEND MESSAGE ---
if ($action === 'send') {
    $receiver_id = (int) ($_POST['receiver_id'] ?? 0);
    $message_text = trim($_POST['message_text'] ?? '');

    if ($receiver_id === 0 || empty($message_text)) {
        $response['message'] = 'Invalid recipient or empty message.';
    } else {
        $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message_text) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $current_user_id, $receiver_id, $message_text);

        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Message sent.';
            
            // OPTIONAL: Insert a notification entry into the notifications table
            // This is how the receiver will see a notification badge pop up
            /*
            $notification_msg = "You received a new message.";
            $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'message')");
            $notif_stmt->bind_param("is", $receiver_id, $notification_msg);
            $notif_stmt->execute();
            $notif_stmt->close();
            */

        } else {
            $response['message'] = 'Failed to send message.';
        }
        $stmt->close();
    }
}

// --- ACTION: FETCH MESSAGES ---
else if ($action === 'fetch') {
    $receiver_id = (int) ($_POST['receiver_id'] ?? 0);

    if ($receiver_id === 0) {
        $response['message'] = 'Invalid user to fetch messages from.';
    } else {
        // Select all messages between the current user and the receiver
        $stmt = $conn->prepare("
            SELECT id, sender_id, receiver_id, message_text, sent_at, is_read 
            FROM messages 
            WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
            ORDER BY sent_at ASC
        ");
        $stmt->bind_param("iiii", $current_user_id, $receiver_id, $receiver_id, $current_user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        
        $messages = [];
        while ($row = $res->fetch_assoc()) {
            $messages[] = [
                'id' => (int) $row['id'],
                'sender_id' => (int) $row['sender_id'],
                'receiver_id' => (int) $row['receiver_id'],
                'message_text' => htmlspecialchars($row['message_text']),
                'sent_at' => $row['sent_at'],
                'is_read' => (bool) $row['is_read']
            ];
        }
        $stmt->close();

        // Mark incoming messages as read
        $mark_read_stmt = $conn->prepare("
            UPDATE messages 
            SET is_read = 1 
            WHERE sender_id = ? AND receiver_id = ? AND is_read = 0
        ");
        $mark_read_stmt->bind_param("ii", $receiver_id, $current_user_id);
        $mark_read_stmt->execute();
        $mark_read_stmt->close();

        $response['success'] = true;
        $response['messages'] = $messages;
    }
}

// --- ACTION: CHECK UNREAD COUNTS (for polling/notifications) ---
else if ($action === 'check_unread_counts') {
    $stmt = $conn->prepare("
        SELECT sender_id, COUNT(id) AS unread_count 
        FROM messages 
        WHERE receiver_id = ? AND is_read = 0 
        GROUP BY sender_id
    ");
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $counts = [];
    while ($row = $res->fetch_assoc()) {
        $counts[] = [
            'sender_id' => (int) $row['sender_id'],
            'unread_count' => (int) $row['unread_count']
        ];
    }
    $stmt->close();

    $response['success'] = true;
    $response['counts'] = $counts;
}

$conn->close();
echo json_encode($response);