
<?php
session_start(); // ✅ Start session

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "local_skill_swap";

// Connect to database
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}


// REGISTER
if (isset($_POST['register'])) {
  $full_name = $_POST['full_name'];
  $email = $_POST['email'];
  // Ensure you use the exact email you intend for the admin
  $admin_email = 'youradminemail@example.com'; // 🚨 **CHANGE THIS to your actual admin email**

  // Check if passwords match (Recommended addition for registration)
  if ($_POST['password'] !== $_POST['confirm']) {
    $message = "Passwords do not match.";
  } else {
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // Check if email already exists
    $check = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
      $message = "Email already exists!";
    } else {
      
      // LOGIC TO SET THE ROLE
      if ($email === $admin_email) {
          $role = 'admin'; // Set to 'admin' only for the specific email
      } else {
          $role = 'user'; // Default for all other users
      }

      // Use a prepared statement that includes the 'role' column
      $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
      $stmt->bind_param("ssss", $full_name, $email, $password, $role);
      $stmt->execute();

      // Successful registration and automatic login
      $_SESSION['user_id'] = $conn->insert_id;
      $_SESSION['full_name'] = $full_name;
      $_SESSION['role'] = $role;

      // Redirect based on the role
      if ($role === 'admin') {
        header("Location: admin_dashboard2.php"); 
        exit();
      } else {
        header("Location: welcome.php");
        exit();
      }
    }
  }
}
// ------------------------------------------------------------------
// ⬅️ END OF MODIFIED REGISTER BLOCK
// ------------------------------------------------------------------

// skillswap.php (Correction for LOGIN block)

// LOGIN
// LOGIN
if (isset($_POST['login'])) {
  $email = $_POST['email'];
  $password = $_POST['password'];

  // 1. ✅ FIX THE QUERY: Select the 'role' column
  $stmt = $conn->prepare("SELECT id, name, password, role FROM users WHERE email = ?"); 
  $stmt->bind_param("s", $email);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    if (password_verify($password, $user['password'])) {
      
      // 🚨 NEW CHECK: BLOCK SUSPENDED USERS 🚨
      if ($user['role'] === 'suspended') {
        $message = "Your account has been suspended by the administrator.";
        // Stop execution here, do not create a session
      } else {
        // ✅ Authentication success (and not suspended)
        
        // 2. ✅ SET THE ROLE IN SESSION: Store the role for later checks
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['full_name'] = $user['name'];
        $_SESSION['role'] = $user['role']; 

        // 3. ✅ REDIRECT BASED ON ROLE: Check the fetched role
        if ($user['role'] === 'admin') {
          header("Location: admin_dashboard2.php"); // Go to admin page
          exit();
        } else {
          header("Location: profile2.php"); // Go to user page (profile2.php)
          exit();
        }
      } // End of suspension check
      
    } else {
      $message = "Invalid password.";
    }
  } else {
    $message = "No account found with that email.";
  }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Trade Skills, Build Community</title>
  <style>
    body {
      margin: 0;
      font-family: "Segoe UI", sans-serif;
      color: #333;
      background-color: #fff;
    }

    .hero {
      background: linear-gradient(to bottom, #ff5f3d, #8133ff 60%, #ffffff 100%);
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 80px 10%;
    }

    .container {
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      max-width: 1100px;
      width: 100%;
    }

    .hero-text { flex: 1; min-width: 300px; padding-right: 40px; }
    .hero h1 { font-size: 3rem; font-weight: 700; line-height: 1.2; }
    .hero p { font-size: 1.1rem; line-height: 1.5; margin: 20px 0; }
    .buttons { display: flex; gap: 15px; }

    .btn {
      padding: 12px 24px;
      border: none;
      border-radius: 8px;
      font-weight: 600;
      cursor: pointer;
      transition: 0.3s;
      text-decoration: none;
    }

    .btn-primary { background-color: #6d28d9; color: white; }
    .btn-primary:hover { background-color: #5b21b6; }
    .btn-secondary { background-color: white; color: #6d28d9; }
    .btn-secondary:hover { background-color: #f3e8ff; }

    .hero-image img {
      width: 100%;
      max-width: 480px;
      border-radius: 20px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
    }

   /* ===== MODAL (redesigned) ===== */
.modal {
  display: none;
  position: fixed;
  z-index: 10;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0,0,0,0.6);
  justify-content: center;
  align-items: center;
}

.modal-content {
  background: #fff;
  border-radius: 16px;
  width: 420px;
  box-shadow: 0 8px 25px rgba(0,0,0,0.2);
  overflow: hidden;
  animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-10px); }
  to { opacity: 1; transform: translateY(0); }
}

.modal-header {
  display: flex;
  justify-content: space-around;
  background: linear-gradient(90deg, #ff5f3d, #8133ff);
  color: white;
  font-weight: 600;
  font-size: 1.1rem;
}

.modal-header button {
  flex: 1;
  padding: 15px 0;
  background: transparent;
  border: none;
  color: white;
  cursor: pointer;
  transition: background 0.3s;
}

.modal-header button.active {
  background: rgba(255,255,255,0.2);
}

.modal-body {
  padding: 30px;
}

.modal-body form {
  display: none;
}

.modal-body form.active {
  display: block;
}

.modal-body input {
  width: 100%;
  padding: 12px;
  margin: 8px 0 16px;
  border: 1px solid #ccc;
  border-radius: 8px;
  font-size: 15px;
}

.modal-body .btn {
  width: 100%;
  padding: 12px;
  border-radius: 8px;
  background: linear-gradient(90deg, #ff5f3d, #8133ff);
  border: none;
  color: #fff;
  font-weight: 600;
  cursor: pointer;
  transition: 0.3s;
}

.modal-body .btn:hover {
  opacity: 0.9;
}

.close {
  position: absolute;
  top: 20px;
  right: 25px;
  font-size: 28px;
  cursor: pointer;
  color: #fff;
}

    /* ===== WHY SECTION ===== */
    .why-section {
      text-align: center;
      padding: 80px 10%;
      background-color: #fff;
    }

    .why-section h2 {
      font-size: 2.5rem;
      font-weight: 700;
      margin-bottom: 10px;
    }

    .why-section p {
      color: #555;
      font-size: 1.1rem;
      margin-bottom: 60px;
    }

    .cards {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 20px;
      max-width: 1100px;
      margin: 0 auto;
    }

    .card {
      border: 1px solid #ddd;
      border-radius: 12px;
      padding: 30px 20px;
      text-align: left;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
      transition: 0.3s;
    }

    .card:hover {
      transform: translateY(-5px);
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }

    .card-icon {
      font-size: 28px;
      margin-bottom: 15px;
    }

    .orange { color: #ff6600; }
    .purple { color: #6d28d9; }
    .teal { color: #0d9488; }

    /* ===== HOW IT WORKS ===== */
    .how-section {
      text-align: center;
      background-color: #f9f9f9;
      padding: 80px 10%;
    }

    .how-section h2 {
      font-size: 2.5rem;
      font-weight: 700;
      margin-bottom: 10px;
    }

    .how-section p {
      color: #666;
      font-size: 1.1rem;
      margin-bottom: 60px;
    }

    .steps {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 40px;
      max-width: 900px;
      margin: 0 auto;
    }

    .step-number {
      font-size: 3rem;
      font-weight: 700;
      color: #f8cfc2;
    }

    /* ===== CTA SECTION ===== */
    .cta-section {
      background: linear-gradient(90deg, #ff6600, #9333ea, #5b21b6);
      color: white;
      text-align: center;
      padding: 80px 10%;
      border-radius: 20px;
      max-width: 1100px;
      margin: 80px auto;
    }

    .cta-section h2 {
      font-size: 2rem;
      font-weight: 700;
      margin-bottom: 15px;
    }

    .cta-section p {
      font-size: 1.1rem;
      margin-bottom: 30px;
    }

    .cta-btn {
      background-color: #4c1d95;
      color: white;
      padding: 12px 28px;
      border: none;
      border-radius: 8px;
      font-weight: 600;
      cursor: pointer;
      transition: 0.3s;
    }

    .cta-btn:hover {
      background-color: #6d28d9;
    }

    /* ===== FOOTER ===== */
    .footer {
      text-align: center;
      padding: 20px 0;
      font-size: 15px;
      color: #555;
    }

    .footer-line {
      width: 100%;
      height: 1px;
      background-color: #eaeaea;
      margin-bottom: 20px;
    }
  </style>
</head>
<body>

  <!-- HERO -->
  <section class="hero">
    <div class="container">
      <div class="hero-text">
        <h1>Trade Skills,<br>Build Community</h1>
        <p>Learn guitar in exchange for language tutoring. Share your carpentry skills for graphic design help. Connect with users and grow together.</p>
        <div class="buttons">
          <button class="btn btn-primary" onclick="document.getElementById('authModal').style.display='flex'">Get Started Free →</button>
        </div>
      </div>
      <div class="hero-image">
        <img src="skillswap2.png" alt="Community Image" />
      </div>
    </div>
  </section>

 <!-- WHY SKILL SWAP -->
  <section class="why-section">
    <h2>Why The Quid?</h2>
    <p>Breaking down economic barriers to learning while building meaningful local connections</p>

    <div class="cards">
      <div class="card"><div class="card-icon orange">👥</div><h3>Connect Locally</h3><p>Find skilled neighbors ready to share their knowledge in your area.</p></div>
      <div class="card"><div class="card-icon purple">📘</div><h3>Learn Anything</h3><p>From music to coding, cooking to carpentry — trade skills without spending money.</p></div>
      <div class="card"><div class="card-icon teal">📍</div><h3>Community First</h3><p>Build lasting relationships with people in your neighborhood.</p></div>
      <div class="card"><div class="card-icon orange">⭐</div><h3>Trust & Safety</h3><p>Verified profiles and community ratings ensure quality exchanges.</p></div>
    </div>
  </section>

  <!-- HOW IT WORKS -->
  <section class="how-section">
    <h2>How It Works</h2>
    <p>Simple, Free, and Community-Driven</p>
    <div class="steps">
      <div><div class="step-number">01</div><h3>Create Your Profile</h3><p>List the skills you can offer and what you want to learn.</p></div>
      <div><div class="step-number">02</div><h3>Find Matches</h3><p>Discover people nearby with complementary skills.</p></div>
      <div><div class="step-number">03</div><h3>Start Swapping</h3><p>Connect, schedule sessions, and grow together.</p></div>
    </div>
  </section>

  <!-- CTA -->
  <section class="cta-section">
    <h2>Ready to Join Your Local Community?</h2>
    <p>Start connecting with neighbors, learning new skills, and building meaningful relationships today.</p>
	<button class="cta-btn" onclick="document.getElementById('authModal').style.display='flex'">Get Started – It’s Free →</button>
   
  </section>

  <!-- FOOTER -->
  <footer class="footer">
    <div class="footer-line"></div>
    <p>© 2025 The Quid. Building Community, One Skill at a Time.</p>
  </footer>

  <!-- MODAL -->
<div class="modal" id="authModal">
  <div class="modal-content">
    <span class="close" onclick="document.getElementById('authModal').style.display='none'">&times;</span>
    
    <div class="modal-header">
      <button id="loginTab" class="active" onclick="showForm('login')">Log In</button>
      <button id="registerTab" onclick="showForm('register')">Register</button>
    </div>

    <div class="modal-body">
      <!-- Login Form -->
      <form id="loginForm" method="POST" class="active">
        <input type="email" name="email" placeholder="Email" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit" name="login" class="btn">Log In</button>
      </form>

      <!-- Register Form -->
      <form id="registerForm" method="POST">
        <input type="text" name="full_name" placeholder="Full Name" required>
        <input type="email" name="email" placeholder="Email" required>
        <input type="password" name="password" placeholder="Password" required>
        <input type="password" name="confirm" placeholder="Confirm Password" required>
        <button type="submit" name="register" class="btn">Register</button>
      </form>
    </div>
  </div>
</div>

  <?php if (isset($message)) echo "<script>alert('$message');</script>"; ?>
  
  <script>
function showForm(tab) {
  document.getElementById('loginForm').classList.remove('active');
  document.getElementById('registerForm').classList.remove('active');
  document.getElementById('loginTab').classList.remove('active');
  document.getElementById('registerTab').classList.remove('active');

  if (tab === 'login') {
    document.getElementById('loginForm').classList.add('active');
    document.getElementById('loginTab').classList.add('active');
  } else {
    document.getElementById('registerForm').classList.add('active');
    document.getElementById('registerTab').classList.add('active');
  }
}
</script>


</body>
</html>
