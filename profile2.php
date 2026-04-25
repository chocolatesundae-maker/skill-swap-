<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: skillswap.php");
    exit();
}

// Include navbar
include 'navbar2.php';

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "local_skill_swap";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Defaults
$user = [
    'name' => 'User',
    'email' => '-',
    'skills_offered' => '-',
    'skills_wanted' => '-',
    'gender' => '-',
    'credits' => 0,
    'profile_picture' => ''
];

$user_id = (int) $_SESSION['user_id'];

// Fetch user info
$stmt = $conn->prepare("SELECT name, email, skills_offered, skills_wanted, gender, credits, profile_picture FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res && $res->num_rows > 0) {
    $db_user = $res->fetch_assoc();
    foreach ($user as $k => $v) {
        if (isset($db_user[$k]) && $db_user[$k] !== null) $user[$k] = $db_user[$k];
    }
}
$stmt->close();

// Handle Add Skill
$add_msg = '';
$add_err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_skill'])) {
    $skill_name = trim($_POST['skill_name'] ?? '');
    $category   = trim($_POST['category'] ?? '');
    $description= trim($_POST['description'] ?? '');

    if ($skill_name === '' || $category === '') {
        $add_err = "Please provide both Skill Name and Category.";
    } else {
        $ins = $conn->prepare("INSERT INTO skills (user_id, category, skill_name, description) VALUES (?, ?, ?, ?)");
        $ins->bind_param("isss", $user_id, $category, $skill_name, $description);
        if ($ins->execute()) {
            $add_msg = "Skill added successfully!";
            header("Location: profile2.php");
            exit();
        } else {
            $add_err = "Error adding skill: " . htmlspecialchars($ins->error);
        }
        $ins->close();
    }
}

// Fetch user's skills
$user_skills = [];
$stmt2 = $conn->prepare("SELECT id, category, skill_name, description FROM skills WHERE user_id = ? ORDER BY id DESC");
$stmt2->bind_param("i", $user_id);
$stmt2->execute();
$res2 = $stmt2->get_result();
if ($res2 && $res2->num_rows > 0) {
    while ($r = $res2->fetch_assoc()) $user_skills[] = $r;
}
$stmt2->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title><?php echo htmlspecialchars($user['name']); ?> — Profile</title>
    <!-- New modern, responsive styling -->
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
            max-width: 1200px; /* Wider wrap */
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

        /* --- Profile Head --- */
        .profile-head {
            display: flex;
            gap: 30px;
            align-items: center;
        }

        .avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #ffdf6b; /* Gold accent border */
            box-shadow: 0 0 15px rgba(255, 223, 107, 0.5);
            flex-shrink: 0;
        }
        
        .avatar-placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.05);
            color: #ddd;
            font-size: 16px;
            font-weight: 500;
        }

        h1 {
            margin: 0;
            font-size: 32px;
            font-weight: 700;
        }

        p {
            margin: 6px 0;
            color: #f8f8f8;
            font-size: 15px;
        }

        .label {
            color: #ffdf6b; /* Gold accent for labels */
            font-weight: 700;
        }

        .actions {
            margin-top: 20px;
        }

        .btn {
            display: inline-block;
            padding: 10px 25px;
            border-radius: 10px; /* Square/rounded button */
            background: #fff;
            color: #6c3cff; /* Purple accent */
            text-decoration: none;
            font-weight: 700;
            transition: 0.3s;
        }

        .btn:hover {
            background: #ffdf6b;
            color: #8133ff;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
        }

        /* --- Content Layout --- */
        .two-col {
            display: grid;
            grid-template-columns: minmax(300px, 1fr) minmax(300px, 400px); /* 1fr and fixed width for form */
            gap: 40px;
            margin-top: 40px;
        }

        /* --- Skill List --- */
        .skill-card {
            background: rgba(255, 255, 255, 0.15);
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 15px;
            border-left: 5px solid #ff5f3d; /* Orange accent */
        }
        
        .skill-card strong {
            font-size: 1.1rem;
            display: block;
            margin-bottom: 5px;
        }

        /* --- Form Styles --- */
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

        /* --- Responsive Adjustments --- */
        @media (max-width: 900px) {
            .profile-head {
                flex-direction: column;
                text-align: center;
            }
            .two-col {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<div class="wrap">
    <!-- Profile Card -->
    <div class="card profile-head">
        <div style="flex:0 0 150px;text-align:center">
            <?php if (!empty($user['profile_picture'])): ?>
                <img class="avatar" src="<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="Avatar">
            <?php else: ?>
                <div class="avatar avatar-placeholder">No Image</div>
            <?php endif; ?>
        </div>

        <div style="flex:1">
            <h1><?php echo htmlspecialchars($user['name']); ?></h1>
            <p><span class="label">Email:</span> <?php echo htmlspecialchars($user['email']); ?></p>
            <p><span class="label">Skill Offered:</span> <?php echo htmlspecialchars($user['skills_offered']); ?></p>
            <p><span class="label">Skill Wanted:</span> <?php echo htmlspecialchars($user['skills_wanted']); ?></p>
            <p><span class="label">Gender:</span> <?php echo htmlspecialchars($user['gender']); ?></p>
           

            <div class="actions">
                <a class="btn" href="edit_profile2.php">Edit Profile</a>
            </div>
        </div>
    </div>

    <div class="two-col">
        <!-- Skills list -->
        <div class="card">
            <h2 style="margin-top:0; color:#ffdf6b;">My Skills</h2>
            <?php if (!empty($user_skills)): ?>
                <?php foreach ($user_skills as $s): ?>
                    <div class="skill-card">
                        <strong><?php echo htmlspecialchars($s['skill_name']); ?></strong>
                        <div style="font-size:13px;color:#e9eefb">Category: <?php echo htmlspecialchars($s['category']); ?></div>
                        <?php if (!empty($s['description'])): ?>
                            <div style="margin-top:8px; font-size: 14px; color: #f0f0f0;"><?php echo nl2br(htmlspecialchars($s['description'])); ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No skills added yet. Use the form on the right to add one.</p>
            <?php endif; ?>
        </div>

        <!-- Add Skill Form -->
        <div class="card">
            <h3 style="margin-top:0; color:#ffdf6b;">Add a New Skill</h3>
            <?php if ($add_msg): ?><div class="msg"><?php echo htmlspecialchars($add_msg); ?></div><?php endif; ?>
            <?php if ($add_err): ?><div class="err"><?php echo htmlspecialchars($add_err); ?></div><?php endif; ?>

            <form method="post">
                <label for="skill_name">Skill Name <span style="color:#ffd1a9">*</span></label>
                <input id="skill_name" name="skill_name" type="text" required>

                <label for="category">Category <span style="color:#ffd1a9">*</span></label>
                <select id="category" name="category" required>
                    <option value="">-- Select a Category --</option>
                    <option>Arts & Crafts</option>
                    <option>Music & Instruments</option>
                    <option>Cooking & Baking</option>
                    <option>Home Improvement</option>
                    <option>Technology & Programming</option>
                    <option>Design & Multimedia</option>
                    <option>Education & Tutoring</option>
                    <option>Health & Fitness</option>
                    <option>Beauty & Personal Care</option>
                    <option>Business & Finance</option>
                    <option>Agriculture & Gardening</option>
                    <option>Automotive</option>
                    <option>Photography & Videography</option>
                    <option>Writing & Translation</option>
                    <option>Pet Care</option>
                    <option>Event Planning</option>
                    <option>Social Media & Marketing</option>
                    <option>Travel & Lifestyle</option>
                    <option>Handyman Services</option>
                    <option>Academic Assistance</option>
                    <option>Language & Communication</option>
                    <option>Fashion & Styling</option>
                    <option>Interior Design</option>
                    <option>Film & Animation</option>
                    <option>Public Speaking & Debating</option>
                    <option>Environmental Sustainability</option>
                    <option>Spirituality & Well-being</option>
                    <option>Culinary Arts</option>
                    <option>Childcare & Education</option>
                    <option>Engineering & Mechanics</option>
                    <option>Data Science & Analytics</option>
                    <option>Architecture & Construction</option>
                    <option>Hospitality & Tourism</option>
                    <option>Gaming & Esports</option>
                    <option>Science & Research</option>
                </select>

                <label for="description">Description (optional)</label>
                <textarea id="description" name="description" rows="4"></textarea>

                <input type="submit" name="add_skill" value="Add Skill">
            </form>
        </div>
    </div>
</div>

</body>
</html>
