<?php
session_start();
// Security Check: Redirect non-admins or unauthenticated users
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: skillswap.php");
    exit();
}

// ----------------------------------------------------
// 1. DATABASE CONNECTION & DATA FETCHING
// ----------------------------------------------------
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "local_skill_swap";

// Connect to database
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Function to fetch all data from a single table (REMAINS THE SAME)
function fetchTableData($conn, $tableName, $whereClause = "") {
    // Add the WHERE clause if provided
    $sql = "SELECT * FROM " . $tableName . ($whereClause ? " WHERE " . $whereClause : "");
    $result = $conn->query($sql);
    
    $data = [];
    if ($result && $result->num_rows > 0) {
        $fields = $result->fetch_fields();
        $headers = [];
        foreach ($fields as $field) {
            $headers[] = $field->name;
        }
        $data['headers'] = $headers;
        $data['rows'] = $result->fetch_all(MYSQLI_ASSOC);
    }
    return $data;
}

// ----------------------------------------------------
// MODIFICATION START HERE: Database Queries
// ----------------------------------------------------

// 1. Fetch USER data, EXCLUDING the admin
$nonAdminUsers = fetchTableData($conn, 'users', "role != 'admin'");

// 2. Define tables to fetch
$tables = [
    'users' => $nonAdminUsers, // Use the filtered list here
    'skills' => fetchTableData($conn, 'skills'),
    'requests' => fetchTableData($conn, 'requests'),
    'messages' => fetchTableData($conn, 'messages'),
    'notifications' => fetchTableData($conn, 'notifications')
];

// Optional: Fetch counts for the dashboard cards
$userCount = $conn->query("SELECT COUNT(*) AS count FROM users")->fetch_assoc()['count'] ?? 0;
$skillCount = $conn->query("SELECT COUNT(*) AS count FROM skills")->fetch_assoc()['count'] ?? 0;

// 👇 THE CORRECTED LOGIC: Fetch the count of PENDING disputes from the 'requests' table
$pendingDisputesCountResult = $conn->query("SELECT COUNT(*) AS count FROM requests WHERE status = 'pending'");
// Note: If you have a separate 'disputes' table and the user simply misspoke, 
// you should use that table. Assuming 'requests' is the correct table for now.
$pendingDisputesCount = $pendingDisputesCountResult->fetch_assoc()['count'] ?? 0;


// Close connection
$conn->close();

// ----------------------------------------------------
// 2. HELPER FUNCTION TO DISPLAY HTML TABLE
// ----------------------------------------------------
function displayTable($tableName, $data) {
    if (empty($data) || empty($data['rows'])) {
        echo "<p>No data found in the '<strong>" . htmlspecialchars($tableName) . "</strong>' table.</p>";
        return;
    }

    echo "<h3>Table: " . htmlspecialchars(ucwords(str_replace('_', ' ', $tableName))) . "</h3>";
    echo "<table class='data-table'>";
    
    // Table Header
    echo "<thead><tr>";
    foreach ($data['headers'] as $header) {
        // Hide sensitive columns like 'password'
        if ($header !== 'password') {
            echo "<th>" . htmlspecialchars(ucwords(str_replace('_', ' ', $header))) . "</th>";
        }
    }
    // ➡️ MODIFIED: Only add Actions header for the 'users' table
    if ($tableName === 'users') {
        echo "<th>Actions</th>";
    }
    echo "</tr></thead>";
    
    // Table Body
    echo "<tbody>";
    foreach ($data['rows'] as $row) {
        echo "<tr>";
        foreach ($data['headers'] as $header) {
            if ($header !== 'password') { // Hide sensitive data
                // 🚨 THE CRUCIAL FIX (Already applied correctly):
                $cellData = htmlspecialchars((string)($row[$header] ?? ''));
                
                if (strlen($cellData) > 50) {
                    $cellData = substr($cellData, 0, 50) . '...';
                }
                echo "<td>" . $cellData . "</td>";
            }
        }

        // ➡️ MODIFIED: Action Buttons ONLY for Users (Delete and Toggle Suspend)
        if ($tableName === 'users') {
            $id = $row['id'];
            $current_status = $row['role'] ?? 'user';
            
            echo "<td>";
            
if ($current_status !== 'suspended') {
    // Suspend link - Uses suspend-btn class
    echo "<a href='manage_data.php?action=toggle&type=user&id=$id&status=suspended' class='action-button suspend-btn'>Suspend</a>";
} else {
    // Unsuspend link - Uses unsuspend-btn class
    echo "<a href='manage_data.php?action=toggle&type=user&id=$id&status=user' class='action-button unsuspend-btn'>Unsuspend</a>";
}
echo "</td>";

        } 
        // ❌ REMOVED: The entire 'skills' action block is gone.
        
        echo "</tr>";
    }
    echo "</tbody></table>";
}

// ... (Rest of the PHP and HTML code remains the same) ...

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SkillSwap Admin Dashboard</title>
    <style>
	
.action-button {
    text-decoration: none !important; /* Remove underline from links */
    padding: 6px 12px;
    border-radius: 5px;
    font-weight: 600;
    transition: background-color 0.3s;
    display: inline-block; /* Allows padding and margin */
    min-width: 90px; /* Ensures buttons are similar width */
    text-align: center;
    border: 1px solid transparent;
}

.suspend-btn {
    background-color: #ff9800; /* Orange background */
    color: white !important; /* White text */
    border-color: #f57c00;
}
.suspend-btn:hover {
    background-color: #f57c00;
}

.unsuspend-btn {
    background-color: #4CAF50; /* Green background */
    color: white !important; /* White text */
    border-color: #388E3C;
}
.unsuspend-btn:hover {
    background-color: #388E3C;
}
        /* ... (Your existing CSS styles go here, I've truncated them for brevity) ... */
        :root {
            --primary-color: #6d28d9; /* Deep Purple */
            --secondary-color: #ff5f3d; /* Coral/Orange */
            --background-light: #f4f7f9;
            --card-background: #ffffff;
            --text-dark: #333;
            --shadow-light: 0 4px 12px rgba(0, 0, 0, 0.08);
            --shadow-hover: 0 6px 16px rgba(0, 0, 0, 0.15);
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: var(--background-light); color: var(--text-dark); line-height: 1.6; }
        .container { 
    display: flex; 
} 
.sidebar { 
    width: 250px; 
    background: linear-gradient(to bottom, #ff5f3d, #8133ff 60%, #ffffff 100%); 
    color: white; 
    padding: 20px; 
    box-shadow: 2px 0 10px rgba(0, 0, 0, 0.15); 
    display: flex; 
    flex-direction: column; 
    
    /* ➡️ ADDED: Makes the sidebar stick to the full height of the viewport */
    height: 100vh;
    /* ➡️ ADDED: Prevents the sidebar itself from scrolling (if content fits) */
    overflow: hidden; 
    position: sticky;
    top: 0;
}
        .nav-link { color: white; 
		text-decoration: none; 
		padding: 12px 15px; 
		margin-bottom: 10px; 
		border-radius: 8px; 
		transition: background-color 0.3s, transform 0.2s; display: block; font-weight: 500; }
        .nav-link:hover { background-color: rgba(255, 255, 255, 0.15); transform: translateX(5px); }
        .nav-link.active { background-color: rgba(255, 255, 255, 0.15); font-weight: 600; }
        .logo { 
    font-size: 1.5rem; 
    font-weight: 700; 
    margin-bottom: 30px; 
    text-align: center; 
    padding: 10px 0; 
    border-bottom: 1px solid rgba(255, 255, 255, 0.2); 
}
/* ... (Your existing CSS for nav-link) ... */
.logout-btn { 
    
    text-align: center; 
    padding: 20px 0; 
    margin-top: 15px; /* Added slight top margin for separation */
    border-top: 1px solid rgba(255, 255, 255, 0.2);
}
.logout-btn a { 
    color: white; 
    text-decoration: none; 
    background-color: #f04e31; 
    padding: 8px 15px; 
    border-radius: 5px; 
    transition: background-color 0.3s; 
}
        .main-content { flex-grow: 1; padding: 40px; }
        h1 { font-size: 2.5rem; color: var(--primary-color); margin-bottom: 10px; }
        h2 { font-size: 1.8rem; margin-bottom: 15px; }
        h3 { font-size: 1.5rem; margin-top: 30px; margin-bottom: 15px; color: var(--secondary-color); border-bottom: 2px solid #ddd; padding-bottom: 5px; }
        .greeting { font-size: 1.1rem; margin-bottom: 30px; color: #666; }
        .dashboard-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 25px; margin-top: 20px; }
        .card { background-color: var(--card-background); padding: 25px; border-radius: 12px; box-shadow: var(--shadow-light); border-left: 5px solid var(--secondary-color); transition: box-shadow 0.3s, transform 0.3s; font-size: 1.1rem; font-weight: 600; color: var(--text-dark); }
        .card:hover { box-shadow: var(--shadow-hover); transform: translateY(-3px); }
        .card span { display: block; font-size: 2rem; color: var(--primary-color); margin-top: 5px; font-weight: 700; }

        /* 6. Table Styling for Data Display */
        .data-table-container {
            margin-top: 40px;
            padding: 20px;
            background: var(--card-background);
            border-radius: 12px;
            box-shadow: var(--shadow-light);
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
            font-size: 0.9rem;
        }
        .data-table th, .data-table td {
            border: 1px solid #ddd;
            padding: 10px 15px;
            text-align: left;
            word-wrap: break-word; /* Prevents long text from breaking layout */
        }
        .data-table th {
            background-color: #e9e9e9;
            font-weight: 600;
            color: var(--primary-color);
            position: sticky;
            top: 0;
        }
        .data-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .data-table tr:hover {
            background-color: #ffe8e8; /* Light highlight on hover */
        }
    </style>
</head>
<body>

    <div class="container">
        
        <div class="sidebar">
            <div class="logo">The Quid Admin</div>
            
            <a href="#dashboard" class="nav-link active">Dashboard</a>
            <a href="#users" class="nav-link">Users Data</a>
            <a href="#skills" class="nav-link">Skills Data</a>
            
            <a href="#messages" class="nav-link">Messages Data</a>
            <a href="#notifications" class="nav-link">Notifications Data</a>
            <a href="#requests" class="nav-link">Requests Data</a>
            
            <div class="logout-btn">
                <a href="skillswap.php">Log out</a>
            </div>
        </div>
        
        <div class="main-content">
            <h1 id="dashboard">Welcome, Admin!</h1>
			<?php if (isset($_GET['status'])): ?>
    <div style="background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 10px; border-radius: 5px; margin-bottom: 20px;">
        <?php echo htmlspecialchars($_GET['status']); ?>
    </div>
<?php endif; ?>
            <p class="greeting">Here you can manage users, skills, and activity data for the skill swap platform. You are logged in as: **<?php echo $_SESSION['full_name'] ?? 'Administrator'; ?>**</p>

            <div class="dashboard-cards">
                <div class="card">Total Users: <span><?php echo $userCount; ?></span></div>
                <div class="card">Active Skills: <span><?php echo $skillCount; ?></span></div>
                
                <div class="card">Pendings: <span><?php echo $pendingDisputesCount; ?></span></div>
            </div>

            <h2 style="margin-top: 40px; margin-bottom: 20px; color: var(--text-dark);">Full Database Contents</h2>
            <div class="data-table-container">
                
                <?php foreach ($tables as $tableName => $data): ?>
                    <div id="<?php echo strtolower($tableName); ?>">
                        <?php displayTable($tableName, $data); ?>
                    </div>
                <?php endforeach; ?>

            </div>
            
        </div>
    </div>

</body>
</html>