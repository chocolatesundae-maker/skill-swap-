<?php
// Start session if not started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Database connection details needed by the navbar to fetch user name AND request count
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "local_skill_swap";
// Attempt to connect, but don't stop execution if it fails (as this is an included file)
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    // In a production environment, you would log this error.
    // echo ""; 
}

$user_name = "Guest";
$role = "";
$request_count = 0; // Initialize counter

// Only fetch user info if logged in AND connection is successful
if (isset($_SESSION['user_id']) && !$conn->connect_error) {
    $user_id = $_SESSION['user_id'];

    // Query both name/role and new_requests_count in one go for efficiency
    $stmt = $conn->prepare("SELECT name, role, new_requests_count FROM users WHERE id=?");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user) {
            $user_name = $user['name'];
            $role = $user['role'];
            // Fetch the count
            $request_count = (int) $user['new_requests_count'];
        }
    }
}
// Close connection if it was successfully opened
if (isset($conn) && $conn->ping()) {
    $conn->close();
}

?>

<nav>
    <h1><a href="welcome.php" style="color:white; text-decoration:none;">The Quid</a></h1>
    <ul>
        <?php if (isset($_SESSION['user_id'])): ?>
            
            <li><a href="browse_skill2.php">Browse Skills</a></li>
            <li><a href="messages2.php">💬 Messages</a></li>
            <li>
                <a href="request.php" class="nav-link-requests">
                    Requests
                    <?php if ($request_count > 0): ?>
                        <span class="notification-badge">
                            <?php echo $request_count > 99 ? '99+' : $request_count; ?>
                        </span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
    <a href="notification.php" class="nav-link notification-icon">
        🔔 
        <span id="notification-count" class="badge">0</span>
    </a>
</li>
            <li><a href="profile2.php">Profile (<?php echo htmlspecialchars($user_name); ?>)</a></li>
            
            <?php if ($role == 'admin'): ?>
                <li><a href="admin_dashboard.php">Admin Dashboard</a></li>
            <?php endif; ?>
            
            <li><a href="skillswap.php">Log Out</a></li> 
            
        <?php endif; ?>
    </ul>
</nav>

<style>
nav {
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 40px;
    position: sticky;
    top: 0;
    z-index: 1000;
}
nav h1 { color: #fff; font-size: 1.6em; }
nav ul { list-style: none; display: flex; gap: 25px; margin: 0; padding: 0; }
nav ul li a { text-decoration: none; color: #fff; font-weight: 500; transition: color 0.3s; }
nav ul li a:hover { color: #f0e68c; }

/* NEW: Style for the Requests link container to allow badge positioning */
.nav-link-requests {
    position: relative; 
    display: inline-block;
}

/* NEW: Style for the Notification Badge */
.notification-badge {
    position: absolute;
    top: -10px; /* Moves it slightly up */
    right: -15px; /* Moves it slightly to the right */
    background: #ff5f3d; /* Highlight color */
    color: white;
    border: 2px solid rgba(255, 255, 255, 0.5); /* Small white border for contrast */
    border-radius: 50%;
    padding: 3px 6px;
    font-size: 10px;
    line-height: 1;
    font-weight: bold;
    min-width: 18px; 
    text-align: center;
    pointer-events: none;
	
}
.notification-icon {
    position: relative;
    padding: 10px 15px; /* Adjust padding as needed */
    font-size: 1.2rem;
}
.notification-icon .badge {
    position: absolute;
    top: 5px; /* Adjust vertical position */
    right: 5px; /* Adjust horizontal position */
    background-color: red;
    color: white;
    font-size: 0.7rem;
    border-radius: 50%;
    padding: 3px 6px;
    min-width: 20px;
    text-align: center;
    line-height: 1;
    font-weight: bold;
    display: none; /* Hidden by default */
}
</style>

<script>

// Function to update the notification count
function updateNotificationCount() {
    $.ajax({
        url: 'fetch_notification_count.php',
        method: 'GET',
        dataType: 'json',
        success: function(data) {
            const countElement = $('#notification-count');
            const count = data.count;
            
            countElement.text(count);
            if (count > 0) {
                countElement.show();
            } else {
                countElement.hide();
            }
        },
        error: function(xhr, status, error) {
            console.error("Failed to fetch notification count:", error);
        }
    });
}

// Initial load
$(document).ready(function() {
    updateNotificationCount();
    // Refresh count every 60 seconds (optional)
    setInterval(updateNotificationCount, 60000); 
});
</script>