<?php
session_start();

// Include your common files like navbar or connection setup
include 'navbar2.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: skillswap.php");
    exit();
}

// Database connection details
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "local_skill_swap";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$current_user_id = (int) $_SESSION['user_id'];
$requests_received = [];
$requests_sent = [];

// --- A. Fetch Requests RECEIVED by the current user ---
$sql_received = "
    SELECT 
        r.id AS request_id, 
        r.status, 
        s1.skill_name AS offered_skill,
        s2.skill_name AS requested_skill,
        u.name AS sender_name,
        r.created_at
    FROM requests r
    JOIN users u ON r.sender_id = u.id
    JOIN skills s1 ON r.offered_skill_id = s1.id
    JOIN skills s2 ON r.requested_skill_id = s2.id
    WHERE r.receiver_id = ?
    ORDER BY r.created_at DESC";

$stmt_received = $conn->prepare($sql_received);
$stmt_received->bind_param("i", $current_user_id);
$stmt_received->execute();
$result_received = $stmt_received->get_result();
while ($row = $result_received->fetch_assoc()) {
    $requests_received[] = $row;
}
$stmt_received->close();

// --- B. Fetch Requests SENT by the current user ---
$sql_sent = "
    SELECT 
        r.id AS request_id, 
        r.status, 
        s1.skill_name AS offered_skill,
        s2.skill_name AS requested_skill,
        u.name AS receiver_name,
        r.created_at
    FROM requests r
    JOIN users u ON r.receiver_id = u.id
    JOIN skills s1 ON r.offered_skill_id = s1.id
    JOIN skills s2 ON r.requested_skill_id = s2.id
    WHERE r.sender_id = ?
    ORDER BY r.created_at DESC";

$stmt_sent = $conn->prepare($sql_sent);
$stmt_sent->bind_param("i", $current_user_id);
$stmt_sent->execute();
$result_sent = $stmt_sent->get_result();
while ($row = $result_sent->fetch_assoc()) {
    $requests_sent[] = $row;
}
$stmt_sent->close();

$conn->close();

// Helper function for status display
function getStatusBadge($status) {
    switch ($status) {
        case 'pending': return '<span style="color: white; background-color: #ff9800; padding: 5px 10px; border-radius: 5px;">Pending</span>';
        case 'accepted': return '<span style="color: white; background-color: #4CAF50; padding: 5px 10px; border-radius: 5px;">Accepted ✅</span>';
        case 'rejected': return '<span style="color: white; background-color: #f44336; padding: 5px 10px; border-radius: 5px;">Rejected ❌</span>';
        default: return $status;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Exchange Requests</title>
    <style>
         * { margin: 0; 
		padding: 0; 
		box-sizing: border-box; 
		font-family: "Segoe UI", sans-serif; }
        body {
            background: linear-gradient(to bottom, #ff5f3d, #8133ff 60%, #ffffff 100%);
            color: #333;
            min-height: 100vh;
            
        }
        .wrap {
            max-width: 1000px;
            margin: 50px auto;
            padding: 30px;
            background: rgba(255, 255, 255, 0.95); /* Semi-transparent white background */
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        h1 {
            color: #5d3aff; /* Purple accent */
            margin-bottom: 25px;
            text-align: center;
        }
        h2 {
            color: #ff5f3d; /* Orange accent */
            margin-top: 40px;
            margin-bottom: 20px;
            padding-bottom: 5px;
            border-bottom: 2px solid #f0f0f0;
            font-size: 1.5rem;
        }

        /* --- Status Message --- */
        .status-message {
            padding: 15px;
            background-color: #d4edda;
            color: #155724;
            border-left: 5px solid #4CAF50;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        /* --- Request List Container (New) --- */
        .request-list-container {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        /* --- Individual Request Card (New) --- */
        .request-card {
            display: flex;
            background: white;
            border: 1px solid #ddd;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s;
        }
        .request-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
        }

        /* Status colors for border/icon */
        .received-pending { border-left: 6px solid #ff9800; }
        .received-accepted { border-left: 6px solid #4CAF50; }
        .received-rejected { border-left: 6px solid #f44336; }
        .sent-pending { border-left: 6px solid #5d3aff; } /* Use purple for sent pending */


        /* --- Card Sections --- */
        .request-details {
            flex-grow: 1;
            display: grid;
            grid-template-columns: repeat(3, 1fr); /* 3 columns for details */
            gap: 10px;
        }

        .request-action {
            width: 200px; /* Fixed width for actions */
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: flex-end;
            border-left: 1px solid #eee;
            padding-left: 20px;
        }
        .request-action span { text-align: right; }

        /* Detail Items */
        .detail-item strong {
            display: block;
            font-size: 0.9rem;
            color: #777;
            margin-bottom: 3px;
        }
        .detail-item span {
            font-size: 1.1rem;
            color: #333;
            font-weight: 500;
        }
        
        /* --- Status Badge Styling --- */
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 700;
            text-transform: uppercase;
            color: white;
            display: inline-block;
        }
        .status-badge-pending { background-color: #ff9800; }
        .status-badge-accepted { background-color: #4CAF50; }
        .status-badge-rejected { background-color: #f44336; }

        /* --- Action Buttons --- */
        .action-link {
            text-decoration: none;
            padding: 10px 15px;
            border-radius: 8px;
            font-weight: 700;
            margin-top: 5px;
            width: 100%;
            text-align: center;
            transition: background-color 0.2s, transform 0.2s;
        }
        .btn-accept { background-color: #4CAF50; color: white; border: none; }
        .btn-accept:hover { background-color: #388E3C; transform: scale(1.02); }
        .btn-reject { background-color: #f44336; color: white; border: none; }
        .btn-reject:hover { background-color: #d32f2f; transform: scale(1.02); }
    </style>

</head>
<body>

<div class="wrap">
    <h1>Manage Skill Exchange Requests 🤝</h1>

    <?php if (isset($_GET['status_message'])): ?>
        <p class="status-message">
            <?php echo htmlspecialchars($_GET['status_message']); ?>
        </p>
    <?php endif; ?>

    <h2><span style="color:#5d3aff;">📥</span> Requests Received (Action Needed)</h2>
    <?php if (!empty($requests_received)): ?>
        <div class="request-list-container">
            <?php foreach ($requests_received as $req): ?>
                <div class="request-card received-<?php echo htmlspecialchars($req['status']); ?>">
                    <div class="request-details">
                        <div class="detail-item">
                            <strong>From:</strong>
                            <span><?php echo htmlspecialchars($req['sender_name']); ?></span>
                        </div>
                        <div class="detail-item">
                            <strong>Wants Your Skill:</strong>
                            <span style="font-weight:700;"><?php echo htmlspecialchars($req['requested_skill']); ?></span>
                        </div>
                        <div class="detail-item">
                            <strong>Offers Their Skill:</strong>
                            <span><?php echo htmlspecialchars($req['offered_skill']); ?></span>
                        </div>
                        <div class="detail-item">
                            <strong>Status:</strong>
                            <?php echo getStatusBadge($req['status']); ?>
                        </div>
                        <div class="detail-item">
                            <strong>Date Received:</strong>
                            <span style="font-size:1rem;"><?php echo date("M j, Y", strtotime($req['created_at'])); ?></span>
                        </div>
                    </div>

                    <div class="request-action">
                        <?php if ($req['status'] === 'pending'): ?>
                            <a href="manage_request.php?action=accept&request_id=<?php echo $req['request_id']; ?>" class="action-link btn-accept" onclick="return confirm('Are you sure you want to ACCEPT this exchange?')">Accept</a>
                            <a href="manage_request.php?action=reject&request_id=<?php echo $req['request_id']; ?>" class="action-link btn-reject" onclick="return confirm('Are you sure you want to REJECT this exchange?')">Reject</a>
                        <?php else: ?>
                            <span style="color:#777; font-style: italic;"><?php echo $req['status'] === 'accepted' ? 'Accepted' : 'Rejected'; ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p style="padding: 20px; background:#f9f9f9; border-radius: 8px;">🎉 No new exchange requests received. Keep offering amazing skills!</p>
    <?php endif; ?>

    ---

    <h2><span style="color:#5d3aff;">📤</span> Requests Sent (Awaiting Response)</h2>
    <?php if (!empty($requests_sent)): ?>
        <div class="request-list-container">
            <?php foreach ($requests_sent as $req): ?>
                <div class="request-card sent-pending">
                    <div class="request-details">
                        <div class="detail-item">
                            <strong>To:</strong>
                            <span><?php echo htmlspecialchars($req['receiver_name']); ?></span>
                        </div>
                        <div class="detail-item">
                            <strong>Requested Skill:</strong>
                            <span style="font-weight:700;"><?php echo htmlspecialchars($req['requested_skill']); ?></span>
                        </div>
                        <div class="detail-item">
                            <strong>Your Offered Skill:</strong>
                            <span><?php echo htmlspecialchars($req['offered_skill']); ?></span>
                        </div>
                        <div class="detail-item">
                            <strong>Status:</strong>
                            <?php echo getStatusBadge($req['status']); ?>
                        </div>
                        <div class="detail-item">
                            <strong>Date Sent:</strong>
                            <span style="font-size:1rem;"><?php echo date("M j, Y", strtotime($req['created_at'])); ?></span>
                        </div>
                    </div>
                    <div class="request-action">
                        <span style="color:#777;">Tracking...</span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p style="padding: 20px; background:#f9f9f9; border-radius: 8px;">🔍 You haven't sent any exchange requests yet. Time to explore!</p>
    <?php endif; ?>

</div>



</body>
</html>