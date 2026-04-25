<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: skillswap.php");
    exit();
}

// Define the directory where images will be stored
$target_dir = "profile_pictures/"; // **NOTE: Ensure this folder exists and is writable!**

// Include navbar
include 'navbar2.php';

// Database connection details
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "local_skill_swap";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$user_id = (int) $_SESSION['user_id'];
$message = '';
$error = '';

// --- 1. Fetch Current User Data ---
$user = [];
$stmt = $conn->prepare("SELECT name, email, skills_offered, skills_wanted, gender, profile_picture FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res && $res->num_rows > 0) {
    $user = $res->fetch_assoc();
} else {
    $error = "User data could not be fetched.";
}
$stmt->close();

// --- 2. Handle Form Submission (Update User Data) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // Sanitize and validate basic input
    $name           = trim($_POST['name'] ?? '');
    $email          = trim($_POST['email'] ?? '');
    $skills_offered = trim($_POST['skills_offered'] ?? '');
    $skills_wanted  = trim($_POST['skills_wanted'] ?? '');
    $gender         = $_POST['gender'] ?? '';
    
    // Default image path is the existing one
    $profile_picture_path = $user['profile_picture']; 
    $update_image = false;

    // --- Image Upload Logic ---
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $file_tmp_name = $_FILES['profile_picture']['tmp_name'];
        $file_name = basename($_FILES['profile_picture']['name']);
        
        // Generate a unique filename to prevent overwrites (e.g., user_ID_timestamp.ext)
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $unique_name = "user_" . $user_id . "_" . time() . "." . $file_ext;
        $target_file = $target_dir . $unique_name;
        
        // Basic image checks
        $image_type = mime_content_type($file_tmp_name);
        if (!in_array($image_type, ['image/jpeg', 'image/png', 'image/gif'])) {
            $error = "Only JPG, JPEG, PNG, & GIF files are allowed for the profile picture.";
        } elseif ($_FILES['profile_picture']['size'] > 5000000) { // 5MB limit
            $error = "Sorry, your file is too large (max 5MB).";
        } else {
            // Attempt to move the uploaded file
            if (move_uploaded_file($file_tmp_name, $target_file)) {
                $profile_picture_path = $target_file;
                $update_image = true;
                
                // OPTIONAL: Delete the old picture if one existed and the upload was successful
                if (!empty($user['profile_picture']) && file_exists($user['profile_picture'])) {
                    unlink($user['profile_picture']);
                }
            } else {
                $error = "Sorry, there was an error uploading your file.";
            }
        }
    }
    // --- End Image Upload Logic ---

    if ($error === '') {
        if (empty($name) || empty($email) || empty($gender)) {
            $error = "Name, Email, and Gender are required fields.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format.";
        } else {
            // Check if the email is already in use by another user
            $check_email_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $check_email_stmt->bind_param("si", $email, $user_id);
            $check_email_stmt->execute();
            $email_res = $check_email_stmt->get_result();
            $check_email_stmt->close();

            if ($email_res->num_rows > 0) {
                $error = "This email is already linked to another account.";
            } else {
                // Determine the correct SQL statement based on whether a new picture was uploaded
                if ($update_image) {
                     $sql = "UPDATE users SET name = ?, email = ?, skills_offered = ?, skills_wanted = ?, gender = ?, profile_picture = ? WHERE id = ?";
                     $update_stmt = $conn->prepare($sql);
                     $update_stmt->bind_param("ssssssi", $name, $email, $skills_offered, $skills_wanted, $gender, $profile_picture_path, $user_id);
                } else {
                    $sql = "UPDATE users SET name = ?, email = ?, skills_offered = ?, skills_wanted = ?, gender = ? WHERE id = ?";
                    $update_stmt = $conn->prepare($sql);
                    $update_stmt->bind_param("sssssi", $name, $email, $skills_offered, $skills_wanted, $gender, $user_id);
                }

                if ($update_stmt->execute()) {
                    $message = "Profile updated successfully! Redirecting...";
                    // Update the current $user array with new data for immediate display
                    $user['name'] = $name;
                    $user['email'] = $email;
                    $user['skills_offered'] = $skills_offered;
                    $user['skills_wanted'] = $skills_wanted;
                    $user['gender'] = $gender;
                    $user['profile_picture'] = $profile_picture_path;

                    // Redirect back to the profile page after a successful update
                    header("Location: profile2.php?update_success=1");
                    exit();
                } else {
                    $error = "Error updating profile: " . htmlspecialchars($update_stmt->error);
                }
                $update_stmt->close();
            }
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Edit Profile - <?php echo htmlspecialchars($user['name'] ?? 'User'); ?></title>
    <style>
        /* --- General Page Setup --- */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Segoe UI", sans-serif;
        }

        body {
            /* Matched gradient colors */
            background: linear-gradient(to bottom, #ff5f3d, #8133ff 60%, #ffffff 100%);
            color: white;
            min-height: 100vh;
        }

        .wrap {
            max-width: 800px; /* Adjusted width for edit page */
            margin: 36px auto;
            padding: 20px;
        }
        
        /* --- Card Styles (Frosted Glass Effect) --- */
        .card {
            background: rgba(255, 255, 255, 0.08); /* Lighter background */
            padding: 30px;
            border-radius: 20px;
            backdrop-filter: blur(5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }

        /* --- Form Styles --- */
        h2 {
            margin-top: 0;
            margin-bottom: 20px;
            color: #ffdf6b;
            text-align: center;
        }

        form label {
            margin-top: 10px;
            margin-bottom: 5px;
            font-weight: 500;
            color: #f8f8f8;
            display: block;
        }

        form input, form textarea, form select {
            width: 100%;
            padding: 12px;
            border-radius: 10px;
            border: none;
            outline: none;
            margin-bottom: 15px;
            font-size: 14px;
            background: rgba(255, 255, 255, 0.95);
            color: #333;
        }
        
        form textarea {
            resize: vertical;
        }

        .gender-options {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
        }

        .gender-options input[type="radio"] {
            width: auto;
            margin-right: 5px;
        }
        
        .gender-options label {
            display: inline-flex;
            align-items: center;
            margin: 0;
            font-weight: normal;
        }


        input[type=submit] {
            background: #fff;
            color: #5d3aff;
            border-radius: 10px;
            padding: 12px 20px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
            margin-top: 10px;
        }
        
        input[type=submit]:hover {
            background: #ffdf6b;
            color: #8133ff;
            transform: translateY(-2px);
        }

        /* Messages */
        .msg {
            background-color: #4CAF50;
            color: white;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 8px;
            text-align: center;
        }
        .err {
            background-color: #f44336;
            color: white;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 8px;
            text-align: center;
        }

        /* Back Button */
        .back-btn {
            display: block;
            width: 100%;
            padding: 10px;
            text-align: center;
            margin-top: 20px;
            background: rgba(255, 255, 255, 0.15);
            color: #ffdf6b;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            transition: 0.3s;
        }
        .back-btn:hover {
            background: rgba(255, 255, 255, 0.25);
            color: #fff;
        }

    </style>
</head>
<body>

<div class="wrap">
    <div class="card">
        <h2>Edit Your Profile Information</h2>

        <?php if ($message): ?><div class="msg"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="err"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <form method="post">
            <label for="name">Full Name *</label>
            <input id="name" name="name" type="text" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required>

            <label for="email">Email *</label>
            <input id="email" name="email" type="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
			
			 <label for="skills_offered">Skill Offered *</label>
            <input id="skills_offered" name="skills_offered" type="text" value="<?php echo htmlspecialchars($user['skills_offered'] ?? ''); ?>" required>
			
			 <label for="skills_wanted">Skill Wanted *</label>
            <input id="skills_wanted" name="skills_wanted" type="text" value="<?php echo htmlspecialchars($user['skills_wanted'] ?? ''); ?>" required>



         

            <label>Gender *</label>
            <div class="gender-options">
                <label>
                    <input type="radio" name="gender" value="Male" <?php echo (isset($user['gender']) && $user['gender'] == 'Male') ? 'checked' : ''; ?> required>
                    Male
                </label>
                <label>
                    <input type="radio" name="gender" value="Female" <?php echo (isset($user['gender']) && $user['gender'] == 'Female') ? 'checked' : ''; ?> required>
                    Female
                </label>
                <label>
                    <input type="radio" name="gender" value="Other" <?php echo (isset($user['gender']) && $user['gender'] == 'Other') ? 'checked' : ''; ?> required>
                    Other
                </label>
            </div>
            
            <input type="submit" name="update_profile" value="Save Changes">
			
			
        </form>
        
        <a href="profile2.php" class="back-btn">Cancel and Go Back</a>
    </div>
</div>

</body>
</html>