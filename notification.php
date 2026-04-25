<?php
session_start();

include 'navbar2.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: skillswap.php");
    exit();
}

$current_user_id = (int) $_SESSION['user_id'];
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "local_skill_swap";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- A. Fetch ALL notifications for the user ---
// NOTE: Make sure the SQL syntax error (is\_read) mentioned previously is fixed in your actual file.
$sql_fetch = "SELECT id, message, type, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC";
$stmt_fetch = $conn->prepare($sql_fetch);
$stmt_fetch->bind_param("i", $current_user_id);
$stmt_fetch->execute();
$notifications = $stmt_fetch->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_fetch->close();

// --- B. Mark all as read ---
if (!empty($notifications)) {
    // Only update if there are unread notifications to avoid unnecessary DB calls
    $sql_mark_read = "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0";
    $stmt_mark_read = $conn->prepare($sql_mark_read);
    $stmt_mark_read->bind_param("i", $current_user_id);
    $stmt_mark_read->execute();
    $stmt_mark_read->close();
}

$conn->close();

// Helper function for visual flair
function getTypeIcon($type) {
    switch ($type) {
        case 'new_request': return '📬';
        case 'request_update': return '🔄';
        case 'message': return '💬';
        default: return 'ℹ️';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Notifications</title>
    <style>
             * { 
			 margin: 0; 
		padding: 0; 
		box-sizing: border-box; 
		font-family: "Segoe UI", sans-serif; }
        body {
            background: linear-gradient(to bottom, #ff5f3d, #8133ff 60%, #ffffff 100%);
            color: #333;
            min-height: 100vh;
           
        }
        .wrap {
            max-width: 800px;
            margin: 50px auto;
            padding: 30px;
            background: rgba(255, 255, 255, 0.98); /* Near-white background */
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        h1 { 
             color: #5d3aff; /* Purple accent */
            margin-bottom: 25px;
            text-align: center;
        }
        
        /* --- Notification List & Items --- */
        .notification-list { 
            list-style: none; 
            padding: 0; 
            display: flex;
            flex-direction: column;
            gap: 10px; /* Space between items */
        }
        
        .notification-item { 
            display: flex; 
            align-items: center;
            padding: 18px; 
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            cursor: pointer;
            border: 1px solid #f0f0f0;
        }
        .notification-item:hover {
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }
        
        /* Highlight for Unread */
        .notification-item.unread { 
            background-color: #ffe6e6; /* Very light red/pink for urgency */
            border-left: 5px solid #ff5f3d; /* Solid orange border */
            font-weight: 500;
        }
        .notification-item.unread .notification-message {
            font-weight: 700; /* Bolder message text */
            color: #333;
        }
        
        /* Read items style */
        .notification-item:not(.unread) {
            background-color: #f9f9f9;
            opacity: 0.85; /* Slightly faded */
        }

        /* Icon Container */
        .notification-icon-container { 
            font-size: 1.8rem; 
            margin-right: 20px; 
            padding: 5px;
            border-radius: 50%;
            background: #fff;
            line-height: 1;
        }
        .notification-item.unread .notification-icon-container {
            /* Small visual pulse for unread */
            box-shadow: 0 0 0 3px rgba(255, 95, 61, 0.3);
        }

        /* Content Area */
        .notification-content { 
            flex-grow: 1; 
            display: flex;
            flex-direction: column;
        }
        .notification-message { 
            margin: 0; 
            line-height: 1.4;
            color: #444;
        }
        .notification-time { 
            font-size: 0.8rem; 
            color: #999; 
            margin-top: 5px; 
            display: block; 
            align-self: flex-end; /* Pushes time to the right */
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            background: #f0f0ff; /* Light purple-blue background */
            border: 1px dashed #5d3aff;
            border-radius: 10px;
            margin-top: 30px;
            color: #5d3aff;
        }
        .empty-state p {
            font-size: 1.1rem;
            margin: 10px 0;
        }
        .empty-state span {
            font-size: 3rem;
            display: block;
            margin-bottom: 10px;
        }

    </style>
</head>
<body>

<div class="wrap">
    <h1><span style="color:#ff5f3d;">🔔Your Notifications</h1>
    
    <?php if (!empty($notifications)): ?>
        <ul class="notification-list">
            <?php foreach ($notifications as $notif): ?>
                <li class="notification-item <?php echo $notif['is_read'] == 0 ? 'unread' : ''; ?>">
                    <div class="notification-icon-container">
                        <?php echo getTypeIcon($notif['type']); ?>
                    </div>
                    <div class="notification-content">
                        <p class="notification-message">
                            <?php echo htmlspecialchars($notif['message']); ?>
                        </p>
                        <span class="notification-time">
                            <?php 
                                // Display time ago if recent, or full date if older
                                $time_ago = time() - strtotime($notif['created_at']);
                                if ($time_ago < 60) {
                                    echo "Just now";
                                } elseif ($time_ago < 3600) {
                                    echo round($time_ago / 60) . " mins ago";
                                } elseif ($time_ago < 86400) {
                                    echo round($time_ago / 3600) . " hours ago";
                                } else {
                                    echo date("M j, Y, g:i a", strtotime($notif['created_at']));
                                }
                            ?>
                        </span>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <div class="empty-state">
            <span>✨</span>
            <p>You have no notifications yet. Get swapping!</p>
            <p style="font-size: 0.9rem; color: #8133ff;">All is quiet on the skill front.</p>
        </div>
    <?php endif; ?>

</div>

</body>
</html>