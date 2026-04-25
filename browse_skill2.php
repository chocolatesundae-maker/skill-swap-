<?php
session_start();

// Include navbar
include 'navbar2.php';

// Database connection details (matching profile2.php)
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "local_skill_swap";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch ALL skills from all users
// IMPORTANT: We fetch s.id (Skill ID) and u.id (Offerer ID) and other details
$sql = "SELECT 
            s.id AS skill_id, 
            s.skill_name, 
            s.category, 
            s.description, 
            u.id AS offerer_id,
            u.name AS user_name
        FROM 
            skills s
        JOIN 
            users u ON s.user_id = u.id
        ORDER BY 
            s.id DESC"; // Latest skills first

$all_skills = [];
$res = $conn->query($sql);

if ($res && $res->num_rows > 0) {
    while ($r = $res->fetch_assoc()) {
        $all_skills[] = $r;
    }
}

// Get the current logged-in user ID to hide the button on their own skills
$current_user_id = (int) ($_SESSION['user_id'] ?? 0); 

// Fetch current user's skills for the dropdown in the modal
$user_offered_skills = [];
if ($current_user_id > 0) {
    $stmt2 = $conn->prepare("SELECT id, skill_name FROM skills WHERE user_id = ? ORDER BY skill_name ASC");
    $stmt2->bind_param("i", $current_user_id);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    if ($res2 && $res2->num_rows > 0) {
        while ($r = $res2->fetch_assoc()) $user_offered_skills[] = $r;
    }
    $stmt2->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Browse Skills — SkillSwap</title>
   
    <style>
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
            margin-bottom: 20px;
        }

        h1 {
            margin: 0 0 20px 0;
            font-size: 32px;
            font-weight: 700;
            color: #ffdf6b; /* Gold accent */
            text-align: center;
        }
        
        /* --- Skill Grid Layout --- */
        .skill-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); /* Responsive grid */
            gap: 20px;
        }

        /* --- Skill Card --- */
        .skill-item-card {
            background: rgba(255, 255, 255, 0.15);
            padding: 20px;
            border-radius: 12px;
            border-left: 5px solid #ff5f3d; /* Orange accent */
            transition: transform 0.2s, box-shadow 0.2s;
            display: flex; 
            flex-direction: column; 
            justify-content: space-between;
        }
        
        .skill-item-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.3);
        }

        .skill-item-card strong {
            font-size: 1.4rem;
            display: block;
            margin-bottom: 5px;
            color: white;
        }
        
        .skill-category {
            font-size: 14px;
            color: #ffdf6b; /* Gold accent */
            font-weight: 600;
            margin-bottom: 10px;
            display: block;
        }
        
        .skill-offered-by {
            font-size: 13px;
            color: #ccc;
            margin-top: 15px;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
            padding-top: 10px;
        }
        
        .skill-description {
            font-size: 15px; 
            color: #f0f0f0;
            flex-grow: 1; /* Allows the description to take up space */
        }
        
        .no-skills-msg {
            color: #f8f8f8;
            font-size: 18px;
            text-align: center;
            padding: 50px 0;
        }

        /* --- NEW: Button Container for two buttons --- */
        .skill-actions {
            display: flex;
            gap: 10px; /* Space between buttons */
            margin-top: 15px;
        }

        /* --- Request Button Style (Adjusted for flex container) --- */
        .btn-request {
            display: inline-block;
            padding: 8px 15px;
            border-radius: 10px; 
            background: #fff; /* White background */
            color: #5d3aff; /* Purple text */
            text-decoration: none;
            font-weight: 700;
            transition: 0.3s;
            text-align: center;
            font-size: 14px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            border: none;
            cursor: pointer;
            flex-grow: 1; /* Make it take up available space */
        }

        .btn-request:hover {
            background: #ffdf6b;
            color: #8133ff;
            transform: translateY(-2px);
        }

        /* --- NEW: Message Button Style --- */
        .btn-message {
            display: inline-block;
            padding: 8px 15px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 700;
            transition: 0.3s;
            text-align: center;
            font-size: 14px;
            border: none;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            flex-grow: 1; /* Make it take up available space */
            
            /* Specific Message styling */
            background: #ff5f3d; /* Orange/Red background */
            color: #fff; /* White text */
        }

        .btn-message:hover {
            background: #ff7e60;
            color: #fff;
            transform: translateY(-2px);
        }

        /* --- MODAL STYLES (No change needed) --- */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7); /* Dark semi-transparent background */
            display: none; /* Hidden by default */
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        
        .modal-content {
            /* Mimics the card style */
            background: rgba(255, 255, 255, 0.15); 
            padding: 40px;
            border-radius: 20px;
            backdrop-filter: blur(10px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            width: 90%;
            max-width: 500px;
            color: white;
            position: relative;
        }
        
        .modal-close {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 24px;
            font-weight: 700;
            color: #ffdf6b;
            cursor: pointer;
            transition: 0.2s;
        }
        
        .modal-close:hover {
            color: #ff5f3d;
        }

        .modal-header h2 {
            margin-top: 0;
            color: #ffdf6b;
            font-size: 24px;
            margin-bottom: 20px;
        }

        .modal-info-block {
            padding: 15px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 3px solid #ff5f3d;
        }
        
        .modal-info-block p {
            margin: 3px 0;
        }

        /* Form elements within modal */
        #exchangeForm label {
            margin-top: 10px;
            margin-bottom: 5px;
            font-weight: 500;
            color: #f8f8f8;
            display: block;
        }

        #exchangeForm select, #exchangeForm input[type="text"] {
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
        
        #exchangeForm input[readonly] {
             background: rgba(255, 255, 255, 0.7); /* Slightly darker for read-only */
        }
        
        #exchangeForm .submit-btn {
            background: #fff;
            color: #5d3aff;
            border-radius: 10px;
            padding: 12px 20px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
            margin-top: 10px;
            width: auto;
        }
        
        #exchangeForm .submit-btn:hover {
            background: #ffdf6b;
            color: #8133ff;
            transform: translateY(-2px);
        }

        /* Ajax Message */
        #ajaxMessage {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 8px;
            text-align: center;
            display: none;
        }
        .ajax-success { background-color: #4CAF50; }
        .ajax-error { background-color: #f44336; }
    </style>
</head>
<body>

<div class="wrap">
    <h1>Explore Skills Offered by the Community 🚀</h1>
    
    <div class="card">
        <?php if (!empty($all_skills)): ?>
            <div class="skill-grid">
                <?php foreach ($all_skills as $skill): ?>
                    <div 
                        class="skill-item-card"
                        data-skill-id="<?php echo htmlspecialchars($skill['skill_id']); ?>"
                        data-offerer-id="<?php echo htmlspecialchars($skill['offerer_id']); ?>"
                        data-skill-name="<?php echo htmlspecialchars($skill['skill_name']); ?>"
                        data-skill-category="<?php echo htmlspecialchars($skill['category']); ?>"
                        data-offerer-name="<?php echo htmlspecialchars($skill['user_name']); ?>"
                    >
                        <div>
                            <strong><?php echo htmlspecialchars($skill['skill_name']); ?></strong>
                            <span class="skill-category">Category: <?php echo htmlspecialchars($skill['category']); ?></span>
                            
                            <?php if (!empty($skill['description'])): ?>
                                <div class="skill-description"><?php echo nl2br(htmlspecialchars($skill['description'])); ?></div>
                            <?php else: ?>
                                <div class="skill-description">No description provided.</div>
                            <?php endif; ?>
                            
                            <div class="skill-offered-by">
                                Offered by: **<?php echo htmlspecialchars($skill['user_name']); ?>**
                            </div>
                        </div>

                        <?php 
                        // Show action buttons only if logged in AND not their own skill
                        if ($current_user_id > 0 && $current_user_id !== (int) $skill['offerer_id']): 
                        ?>
                            <!-- NEW: Button Container for side-by-side buttons -->
                            <div class="skill-actions">
                                <!-- NEW: Message Button -->
                                <a 
                                    href="messages2.php?offerer_id=<?php echo htmlspecialchars($skill['offerer_id']); ?>"
                                    class="btn-message"
                                >
                                    Message
                                </a>
                                <!-- Request Exchange Button (retains modal functionality) -->
<button 
    type="button"
    class="btn-request open-modal-btn"
>
    Request Exchange
</button>


                            </div>

                        <?php elseif ($current_user_id === (int) $skill['offerer_id']): ?>
                            <div style="color: #ffdf6b; font-size: 12px; margin-top: 15px; font-weight: 600;">
                                (Your Skill)
                            </div>
                        <?php else: ?>
                            <div style="color: #ccc; font-size: 12px; margin-top: 15px;">
                                Log in to take action
                            </div>
                        <?php endif; ?>
                        </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-skills-msg">
                It looks like no skills have been added yet. Be the first to add one!
            </div>
        <?php endif; ?>
    </div>
</div>

<div id="exchangeModal" class="modal-overlay">
    <div class="modal-content">
        <span class="modal-close">&times;</span>
        <div class="modal-header">
            <h2>Request Skill Exchange</h2>
			

        </div>
		

        
        <div class="modal-info-block">
            <p style="font-weight: 700;" id="requestedSkillName"></p>
            <p style="font-size: 13px; color: #ccc;">Offered by: <span id="offererName"></span></p>
        </div>
        <div id="ajaxMessage"></div>

 <form id="exchangeForm">
            <input type="hidden" name="requested_skill_id" id="modalRequestedSkillId">
            <input type="hidden" name="offerer_id" id="modalOffererId">
            <label for="offered_skill_id">Your Offered Skill (what you will teach):</label>
            <select id="offered_skill_id" name="offered_skill_id" required>
                <option value="">-- Select your skill --</option>
               <?php foreach ($user_offered_skills as $skill): ?>
    <option value="<?php echo (int) $skill['id']; ?>">
        <?php echo htmlspecialchars($skill['skill_name']); ?>
    </option>
<?php endforeach; ?>

            </select>
            <label for="requested_skill_display">Skill You Want to Learn (requested from other user):</label>
            <input
                id="requested_skill_display" 
                name="requested_skill_display" 
                type="text" 
                required
            >
            <button type="submit" class="submit-btn">Request Exchange</button>
        </form>
    </div>
</div>
 <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script>


document.querySelectorAll('.open-modal-btn').forEach(button => {
    button.addEventListener('click', function() {
        const card = this.closest('.skill-item-card');
        const requestedSkillId = card.dataset.skillId;
        const offererId = card.dataset.offererId;
        const skillName = card.dataset.skillName;
        const offererName = card.dataset.offererName;

        // Put values inside hidden inputs
        document.getElementById('modalRequestedSkillId').value = requestedSkillId;
        document.getElementById('modalOffererId').value = offererId;
        document.getElementById('requested_skill_display').value = skillName;

        // Update modal display
        document.getElementById('requestedSkillName').innerText = skillName;
        document.getElementById('offererName').innerText = offererName;

        // Clear previous messages
        $('#ajaxMessage').hide().removeClass('ajax-success ajax-error').text('');

        // Open modal
        document.getElementById('exchangeModal').style.display = 'flex';
        console.log("Modal opened with → requested_skill_id:", requestedSkillId, "offerer_id:", offererId);
    });
});


// 💥 NEW LOGIC: Modal Closing (Ang solusyon sa X button) 💥
const modal = document.getElementById('exchangeModal');
const closeButton = document.querySelector('.modal-close');

// Function to close the modal
function closeModal() {
    modal.style.display = 'none';
}

// 1. Close when the 'X' button is clicked
closeButton.addEventListener('click', closeModal);

// 2. Close when user clicks anywhere outside the modal (optional, but good UX)
window.addEventListener('click', function(event) {
    if (event.target === modal) {
        closeModal();
    }
});
// -----------------------------------------------------------------


// ✅ Handle form submission via AJAX (Existing JQuery code)
$(document).ready(function() {
    $('#exchangeForm').on('submit', function(e) {
        e.preventDefault();

        const offeredSkill = $('#offered_skill_id').val();
        const requestedSkill = $('#modalRequestedSkillId').val();
        const offerer = $('#modalOffererId').val();

        console.log("🟢 Sending:", { offeredSkill, requestedSkill, offerer });
        console.log($(this).serialize());

        $.ajax({
            url: 'submit_exchange.php',
            method: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(res) {
                // Use the built-in fadeOut from jQuery for a smooth closing
                $('#ajaxMessage').removeClass('ajax-error').addClass('ajax-success').text(res.message || 'Exchange request sent successfully!').fadeIn(200);
                $('#exchangeModal').fadeOut(500, function() {
                    $('#ajaxMessage').hide().text(''); // Clear message after modal is fully closed
                });
            },
            error: function(xhr, status, error) {
                 const errMsg = xhr.responseJSON ? xhr.responseJSON.message : 'An unknown server error occurred.';
                 $('#ajaxMessage').removeClass('ajax-success').addClass('ajax-error').text(errMsg).fadeIn(200);
                 console.error("AJAX Error:", error, errMsg);
                 // Alert only for critical failure, rely on the message div for user feedback
            }
        });
    });
});
</script>




</body>
</html>
