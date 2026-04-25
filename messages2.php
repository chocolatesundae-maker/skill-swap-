<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: skillswap.php");
    exit();
}

// NOTE: Ensure 'navbar2.php' is included and sets $user_name
include 'navbar2.php';

$current_user_id = (int) $_SESSION['user_id'];
// Use null coalescing operator or a fallback if $user_name isn't guaranteed by navbar2.php
$current_user_name = htmlspecialchars($user_name ?? 'User'); 

// Database connection details
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "local_skill_swap";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// ------------------------------------------------------------------
// --- NEW: Check for direct target user ID from URL (offerer_id) ---
// ------------------------------------------------------------------
$target_user_id = 0;
$target_user_name = '';

if (isset($_GET['offerer_id']) && (int)$_GET['offerer_id'] > 0) {
    $potential_target_id = (int)$_GET['offerer_id'];

    // Prevent user from messaging themselves
    if ($potential_target_id !== $current_user_id) {
        $stmt_target = $conn->prepare("SELECT id, name FROM users WHERE id = ?");
        $stmt_target->bind_param("i", $potential_target_id);
        $stmt_target->execute();
        $res_target = $stmt_target->get_result();

        if ($res_target->num_rows > 0) {
            $target_data = $res_target->fetch_assoc();
            $target_user_id = (int) $target_data['id'];
            $target_user_name = htmlspecialchars($target_data['name']);
        }
        $stmt_target->close();
    }
}
// ------------------------------------------------------------------
// --- END NEW: Check for direct target user ID from URL ---
// ------------------------------------------------------------------


// --- Fetch all users the current user has conversed with ---
$conversations = [];
// NOTE: This SQL selects the other user's ID and name, and the count of unread messages *from* them.
$stmt = $conn->prepare("
    SELECT DISTINCT u.id, u.name, 
           (SELECT COUNT(m.id) FROM messages m WHERE m.sender_id = u.id AND m.receiver_id = ? AND m.is_read = 0) AS unread_count
    FROM messages m_main
    JOIN users u ON u.id = IF(m_main.sender_id = ?, m_main.receiver_id, m_main.sender_id)
    WHERE m_main.sender_id = ? OR m_main.receiver_id = ?
    ORDER BY unread_count DESC, 
    (SELECT MAX(sent_at) FROM messages m_sub WHERE (m_sub.sender_id = u.id AND m_sub.receiver_id = ?) OR (m_sub.receiver_id = u.id AND m_sub.sender_id = ?)) DESC
");
$stmt->bind_param("iiiiii", $current_user_id, $current_user_id, $current_user_id, $current_user_id, $current_user_id, $current_user_id);
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $conversations[] = $row;
}

// ------------------------------------------------------------------
// --- NEW: Add target user to conversations list if not present ---
// ------------------------------------------------------------------
if ($target_user_id > 0) {
    $found = false;
    foreach ($conversations as $conv) {
        if ((int)$conv['id'] === $target_user_id) {
            $found = true;
            break;
        }
    }
    // Only add if they are not already in the conversation list
    if (!$found) {
        // Prepend the new contact to the list so they appear first (or near the top)
        array_unshift($conversations, [
            'id' => $target_user_id, 
            'name' => $target_user_name, 
            'unread_count' => 0 
        ]);
    }
}
// ------------------------------------------------------------------
// --- END NEW: Add target user to conversations list if not present ---
// ------------------------------------------------------------------

$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Messages — SkillSwap</title>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <style>
        /* General styles to fit your theme */
        * { margin: 0; 
		padding: 0; 
		box-sizing: border-box; 
		font-family: "Segoe UI", sans-serif; }
        body { background: linear-gradient(to bottom, #ff5f3d, #8133ff 60%, #ffffff 100%); color: white; min-height: 100vh; }
        .wrap { max-width: 1200px; margin: 36px auto; padding: 20px; }
        h1 { margin: 0 0 20px 0; font-size: 32px; font-weight: 700; color: #ffdf6b; text-align: center; }
        .card { background: rgba(255, 255, 255, 0.08); padding: 30px; border-radius: 20px; backdrop-filter: blur(5px); box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2); }

        /* Messaging Layout */
        .messaging-container { display: flex; height: 70vh; min-height: 500px; }
        
        /* Contact List (Inbox) */
        .inbox-list { width: 30%; background: rgba(0, 0, 0, 0.1); border-right: 1px solid rgba(255, 255, 255, 0.2); overflow-y: auto; border-radius: 15px 0 0 15px; }
        .inbox-list h2 { padding: 15px; font-size: 1.2rem; color: #ffdf6b; border-bottom: 1px solid rgba(255, 255, 255, 0.2); }
        .contact-item { padding: 15px; border-bottom: 1px solid rgba(255, 255, 255, 0.1); cursor: pointer; transition: background 0.3s; display: flex; justify-content: space-between; align-items: center; }
        .contact-item:hover, .contact-item.active { background: rgba(255, 255, 255, 0.2); }
        .contact-name { font-weight: 600; color: white; }
        .unread-badge { background: #ff5f3d; color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px; font-weight: 700; }

        /* Chat Window */
        .chat-window { width: 70%; background: rgba(255, 255, 255, 0.1); display: flex; flex-direction: column; border-radius: 0 15px 15px 0; }
        .chat-header { padding: 15px; font-size: 1.4rem; color: #ffdf6b; border-bottom: 1px solid rgba(255, 255, 255, 0.2); }
        #chatMessages { flex-grow: 1; padding: 20px; overflow-y: auto; display: flex; flex-direction: column; }
        .message-form-container { padding: 15px; border-top: 1px solid rgba(255, 255, 255, 0.2); display: flex; }
        .message-form-container textarea { flex-grow: 1; padding: 10px; border-radius: 8px; border: none; margin-right: 10px; resize: none; background: rgba(255, 255, 255, 0.9); color: #333; }
        .message-form-container button { background: #ffdf6b; color: #8133ff; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: background 0.3s; }
        .message-form-container button:hover { background: #ffffff; }

        /* Individual Messages */
        .message { padding: 8px 12px; margin-bottom: 10px; max-width: 80%; border-radius: 15px; font-size: 14px; position: relative; }
        .message-sender { background: #8133ff; color: white; align-self: flex-end; margin-left: auto; }
        .message-receiver { background: #ff5f3d; color: white; align-self: flex-start; margin-right: auto; }
        .message-time { display: block; font-size: 10px; opacity: 0.7; margin-top: 3px; }
        
        /* Initial State */
        .chat-initial { text-align: center; padding-top: 150px; color: #ccc; }
        
    </style>
</head>
<body>

<div class="wrap">
    <h1>💬 Your Messages</h1>
    
    <div class="card">
        <div class="messaging-container">
            
            <div class="inbox-list">
                <h2>Conversations</h2>
                <?php if (!empty($conversations)): ?>
                    <?php foreach ($conversations as $c): ?>
                        <div 
                            class="contact-item" 
                            data-chat-id="<?php echo (int) $c['id']; ?>"
                            data-chat-name="<?php echo htmlspecialchars($c['name']); ?>"
                        >
                            <span class="contact-name"><?php echo htmlspecialchars($c['name']); ?></span>
                            <?php if ($c['unread_count'] > 0): ?>
                                <span class="unread-badge" id="badge-<?php echo (int) $c['id']; ?>">
                                    <?php echo (int) $c['unread_count']; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="padding: 15px; color: #ccc;">No current conversations.</p>
                <?php endif; ?>
            </div>

            <div class="chat-window">
                <div class="chat-header" id="chatHeader">Select a user to chat...</div>
                <div id="chatMessages">
                    <div class="chat-initial">Click a contact on the left to start a conversation.</div>
                </div>
                
                <div class="message-form-container" id="messageFormContainer" style="display: none;">
                    <textarea id="messageText" placeholder="Type your message..." rows="2"></textarea>
                    <button id="sendMessageBtn">Send</button>
                    <input type="hidden" id="receiverId" value="0">
                </div>
            </div>

        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    let currentReceiverId = 0;
    const $chatMessages = $('#chatMessages');
    const $messageText = $('#messageText');
    const $sendMessageBtn = $('#sendMessageBtn');

    // Function to scroll chat window to the bottom
    function scrollToBottom() {
        $chatMessages.scrollTop($chatMessages[0].scrollHeight);
    }

    // Function to fetch and display messages
    function fetchMessages(receiverId, shouldScroll = true) {
        if (receiverId === 0) return;

        $.ajax({
            // NOTE: This file must handle the 'fetch' action on the server side
            url: 'send_message.php', 
            type: 'POST',
            dataType: 'json',
            data: { action: 'fetch', receiver_id: receiverId },
            success: function(response) {
                if (response.success) {
                    // Only empty and redraw if the receiver hasn't changed, or if it's the initial load
                    if (receiverId === currentReceiverId) {
                        $chatMessages.empty();
                        
                        if (response.messages.length === 0) {
                            // Display message for a new conversation
                            $chatMessages.html('<div class="chat-initial">Start a new conversation! Your message will be the first.</div>');
                        } else {
                            response.messages.forEach(msg => {
                                // Determine if the message is from the current user or the contact
                                const senderClass = msg.sender_id === <?php echo $current_user_id; ?> ? 'message-sender' : 'message-receiver';
                                const time = new Date(msg.sent_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    
                                $chatMessages.append(`
                                    <div class="message ${senderClass}">
                                        ${msg.message_text}
                                        <span class="message-time">${time}</span>
                                    </div>
                                `);
                            });
                        }

                        if (shouldScroll) {
                            scrollToBottom();
                        }
                        
                        // Clear the unread badge after fetching and marking as read
                        $(`#badge-${receiverId}`).hide().text('0');
                    }

                } else if (receiverId === currentReceiverId) {
                    $chatMessages.html('<div class="chat-initial" style="color: #ff5f3d;">Error loading messages.</div>');
                }
            }
        });
    }

    // Function to check only the unread counts for the inbox list
    function checkUnreadCounts() {
        $.ajax({
            // NOTE: This file must handle the 'check_unread_counts' action on the server side
            url: 'send_message.php', 
            type: 'POST',
            dataType: 'json',
            data: { action: 'check_unread_counts' },
            success: function(response) {
                if (response.success) {
                    response.counts.forEach(item => {
                        // Find the badge associated with the sender
                        const $badge = $(`#badge-${item.sender_id}`);
                        if (item.unread_count > 0) {
                            $badge.text(item.unread_count).show();
                        } else {
                            $badge.hide();
                        }
                    });
                }
            }
        });
    }
    
    // --- Event Handlers ---

    // 1. Contact Item Click Handler
    $('.contact-item').on('click', function() {
        // Highlight active contact
        $('.contact-item').removeClass('active');
        $(this).addClass('active');

        currentReceiverId = $(this).data('chat-id');
        const receiverName = $(this).data('chat-name');
        
        // Update header and form visibility
        $('#chatHeader').text(`Chatting with ${receiverName}`);
        $('#receiverId').val(currentReceiverId);
        $('#messageFormContainer').show();
        $messageText.focus();

        // Load chat history and scroll to bottom
        fetchMessages(currentReceiverId, true);
    });

    // 2. Send Message Handler
    $sendMessageBtn.on('click', function() {
        const text = $messageText.val().trim();
        if (text === '' || currentReceiverId === 0) return;

        // Disable button to prevent double send
        $sendMessageBtn.prop('disabled', true);

        $.ajax({
            // NOTE: This file must handle the 'send' action on the server side
            url: 'send_message.php',
            type: 'POST',
            dataType: 'json',
            data: { 
                action: 'send', 
                receiver_id: currentReceiverId, 
                message_text: text 
            },
            success: function(response) {
                if (response.success) {
                    // Fast update: append the sent message instantly
                    const now = new Date();
                    const time = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                    $chatMessages.append(`
                        <div class="message message-sender">
                            ${text}
                            <span class="message-time">${time}</span>
                        </div>
                    `);
                    $messageText.val('');
                    scrollToBottom();
                } else {
                    alert('Error sending message: ' + response.message);
                }
            },
            complete: function() {
                $sendMessageBtn.prop('disabled', false);
            }
        });
    });

    // 3. Polling for New Messages (Keep this for real-time chat)
    setInterval(function() {
        if (currentReceiverId !== 0) {
            // If a chat is open, periodically refresh the thread to see new replies (shouldScroll=false to maintain user position if possible)
            fetchMessages(currentReceiverId, false); 
        } 
        // Always check unread counts for the inbox list even if a chat is open
        checkUnreadCounts(); 
    }, 5000); // Check every 5 seconds

    // ------------------------------------------------------------------
    // --- NEW: Auto-select and load chat if target user ID is present ---
    // ------------------------------------------------------------------
    const autoLoadId = <?php echo (int) $target_user_id; ?>;

    if (autoLoadId > 0) {
        // Find the corresponding contact-item element
        const $targetContact = $(`.contact-item[data-chat-id="${autoLoadId}"]`);
        
        if ($targetContact.length) {
            // Simulate a click on the contact item to trigger the chat load
            $targetContact.trigger('click');
        } 
    }
    // ------------------------------------------------------------------
    // --- END NEW: Auto-select and load chat if target user ID is present ---
    // ------------------------------------------------------------------
});
</script>
</body>
</html>