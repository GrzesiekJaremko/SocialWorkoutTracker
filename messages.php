<?php
session_start();
include("connect.php");

// Check if the user is already logged in via session
if (isset($_SESSION['username'])) {
    // User is logged in, proceed as normal
    $username = $_SESSION['username'];
}
// If not logged in, check for "Remember Me" cookie
elseif (isset($_COOKIE['remember_me'])) {
    $token = $_COOKIE['remember_me'];

    // Fetch user by token
    $user_query = $conn->prepare("SELECT id, username FROM users WHERE remember_token = ?");
    $user_query->bind_param("s", $token);
    $user_query->execute();
    $result = $user_query->get_result();
    $user = $result->fetch_assoc();

    if ($user) {
        // Log the user in automatically
        $_SESSION['username'] = $user['username'];
        $username = $user['username'];
    } else {
        // Invalid token, delete the cookie and redirect to login
        setcookie('remember_me', '', time() - 3600, "/");
        header("Location: index.php");
        exit();
    }
}
// If neither session nor cookie is valid, redirect to login
else {
    header("Location: index.php");
    exit();
}

$username = $_SESSION['username'];

// Fetch current user's details
$user_query = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ?");
mysqli_stmt_bind_param($user_query, "s", $username);
mysqli_stmt_execute($user_query);
$result = mysqli_stmt_get_result($user_query);
$user = mysqli_fetch_assoc($result);
$user_id = $user['id'];

// Handle sending a message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $receiver_id = intval($_POST['receiver_id']);
    $message = htmlspecialchars($_POST['message']);

    // Insert the message into the database
    $insert_query = mysqli_prepare($conn, "INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
    mysqli_stmt_bind_param($insert_query, "iis", $user_id, $receiver_id, $message);
    mysqli_stmt_execute($insert_query);
}

// Fetch friends for the current user
$friends_query = mysqli_prepare($conn, "
    SELECT u.id, u.username, u.profilePicture 
    FROM friends f 
    JOIN users u ON f.friend_id = u.id 
    WHERE f.user_id = ?
");
mysqli_stmt_bind_param($friends_query, "i", $user_id);
mysqli_stmt_execute($friends_query);
$friends_result = mysqli_stmt_get_result($friends_query);
$friends = mysqli_fetch_all($friends_result, MYSQLI_ASSOC);

// Fetch the latest visible message and unread message count for each friend
$latest_messages = [];
$unread_counts = [];
foreach ($friends as $friend) {
    $friend_id = $friend['id'];
    $latest_message_query = mysqli_prepare($conn, "
        SELECT m.*, u.username AS sender_username 
        FROM messages m 
        JOIN users u ON m.sender_id = u.id 
        WHERE ((m.sender_id = ? AND m.receiver_id = ? AND m.deleted_by_sender = 0) 
           OR (m.sender_id = ? AND m.receiver_id = ? AND m.deleted_by_receiver = 0)) 
        ORDER BY m.created_at DESC 
        LIMIT 1
    ");
    mysqli_stmt_bind_param($latest_message_query, "iiii", $user_id, $friend_id, $friend_id, $user_id);
    mysqli_stmt_execute($latest_message_query);
    $latest_message_result = mysqli_stmt_get_result($latest_message_query);
    $latest_message = mysqli_fetch_assoc($latest_message_result);
    $latest_messages[$friend_id] = $latest_message;

    // Fetch the count of unread messages from this friend
    $unread_count_query = mysqli_prepare($conn, "
        SELECT COUNT(*) AS unread_count 
        FROM messages 
        WHERE sender_id = ? AND receiver_id = ? AND is_read = 0
    ");
    mysqli_stmt_bind_param($unread_count_query, "ii", $friend_id, $user_id);
    mysqli_stmt_execute($unread_count_query);
    $unread_count_result = mysqli_stmt_get_result($unread_count_query);
    $unread_count = mysqli_fetch_assoc($unread_count_result)['unread_count'];
    $unread_counts[$friend_id] = $unread_count;
}

// Sort friends by the latest visible message timestamp
usort($friends, function ($a, $b) use ($latest_messages) {
    $time_a = $latest_messages[$a['id']]['created_at'] ?? null;
    $time_b = $latest_messages[$b['id']]['created_at'] ?? null;

    // Friends with messages come first
    if ($time_a === null && $time_b === null)
        return 0;
    if ($time_a === null)
        return 1;
    if ($time_b === null)
        return -1;

    // Sort by latest message timestamp in descending order
    return strtotime($time_b) - strtotime($time_a);
});

// Fetch full chat log if a friend is selected
$selected_friend_id = $_GET['friend_id'] ?? null;
$chat_log = [];
if ($selected_friend_id) {
    // Mark all messages from this friend as read
    $mark_as_read_query = mysqli_prepare($conn, "
        UPDATE messages 
        SET is_read = 1 
        WHERE receiver_id = ? AND sender_id = ? AND is_read = 0
    ");
    mysqli_stmt_bind_param($mark_as_read_query, "ii", $user_id, $selected_friend_id);
    mysqli_stmt_execute($mark_as_read_query);

    // Fetch the full chat log (only messages visible to the active user)
    $chat_log_query = mysqli_prepare($conn, "
        SELECT m.*, u.username AS sender_username 
        FROM messages m 
        JOIN users u ON m.sender_id = u.id 
        WHERE ((m.sender_id = ? AND m.receiver_id = ? AND m.deleted_by_sender = 0) 
           OR (m.sender_id = ? AND m.receiver_id = ? AND m.deleted_by_receiver = 0)) 
        ORDER BY m.created_at ASC
    ");
    mysqli_stmt_bind_param($chat_log_query, "iiii", $user_id, $selected_friend_id, $selected_friend_id, $user_id);
    mysqli_stmt_execute($chat_log_query);
    $chat_log_result = mysqli_stmt_get_result($chat_log_query);
    $chat_log = mysqli_fetch_all($chat_log_result, MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Messages</title>
    <link rel="stylesheet" href="style.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        .top-section {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background-color: #121212;
            padding: 16px;
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 10px;
            height: 72px;
        }

        /* Spacer Div (Same Height as Top Section including padding) */
        .spacer {
            height: 72px;
        }

        .container {
            margin: 0;
            padding: 0;
            width: 100%;
            max-width: 100%;
            overflow-x: hidden;
        }

        .top-section.hidden {
            display: none;
        }

        .friend-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .friend-item {
            display: flex;
            align-items: center;
            padding: 12px;
            background-color: #1e1e1e;
            border-radius: 8px;
            cursor: pointer;
        }

        .friend-item img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            margin-right: 10px;
        }

        .friend-item .friend-info {
            flex-grow: 1;
        }

        .friend-item .friend-username {
            font-size: 1rem;
            color: #ffffff;
        }

        .friend-item .latest-message {
            font-size: 0.9em;
            color: #cccccc;
        }

        .chat-container {
            display: flex;
            flex-direction: column;
            height: calc(100vh - 150px);
            background-color: #1e1e1e;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 16px;
            overflow-y: auto;
            position: relative;
        }

        .chat-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background-color: #121212;
            padding: 16px;
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 10px;
            height: 72px;
        }

        .chat-log {
            height: auto;
            padding-top: 24px;
            padding-bottom: 80px;
            margin-bottom: 80px;
            padding-left: 8px;
            padding-right: 8px;
            overflow-y: auto;
            position: relative;
            box-sizing: border-box;
        }

        .chat-header .back-arrow {
            font-size: 24px;
            color: #ffffff;
            cursor: pointer;
        }

        .chat-header img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
        }

        .chat-header .friend-username {
            font-size: 1.25rem;
            font-weight: bold;
            color: #ffffff;
        }

        .message-bubble {
            max-width: 70%;
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 12px;
            position: relative;
        }

        .message-bubble.received {
            background-color: #333333;
            align-self: flex-start;
            margin-right: auto;
        }

        .message-bubble.sent {
            background-color: #1e90ff;
            align-self: flex-end;
            margin-left: auto;
        }

        .message-bubble .message-content {
            margin-top: 5px;
            color: #ffffff;
        }

        .message-bubble .message-time {
            font-size: 0.8em;
            color: #cccccc;
            margin-top: 5px;
            text-align: right;
        }

        .message-input-area {
            margin-left: auto;
            margin-right: auto;
            display: flex;
            gap: 10px;
            padding: 12px;
            background-color: #1e1e1e;
            border-radius: 8px;
            width: 100%;
            box-sizing: border-box;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 10;
        }

        .message-input-area textarea {
            height: 100%;
            width: 85%;
            flex-grow: 1;
            padding: 12px;
            border: 1px solid #333333;
            border-radius: 8px;
            background-color: #1e1e1e;
            color: #ffffff;
            font-size: 1rem;
            resize: none;
            min-height: 50px;
        }

        .message-input-area button {
            height: 100%;
            padding: 12px 20px;
            background-color: #1e90ff;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            white-space: nowrap;
            width: auto;
        }

        .message-input-area button:hover {
            background-color: #0077cc;
        }

        /* Responsive Design for Mobile */
        @media (max-width: 600px) {
            .message-input-area {
                padding: 8px;
            }

            .message-input-area textarea {
                min-height: 40px;
                font-size: 0.9rem;
            }

            .message-input-area button {
                padding: 8px 16px;
                font-size: 0.9rem;
            }
        }

        .submitbtn {
            flex-grow: 1;
            padding: 12px;
            background-color: #1e90ff;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            white-space: nowrap;
            margin-left: 10px;
            height: auto;
            min-height: 50px;
        }

        .submitbtn:hover {
            background-color: #0077cc;
        }

        .chat-log::-webkit-scrollbar {
            width: 4px;
        }

        .chat-log::-webkit-scrollbar-track {
            background: #1e1e1e;
            border-radius: 3px;
        }

        .chat-log::-webkit-scrollbar-thumb {
            background: #555;
            border-radius: 3px;
        }

        .chat-log::-webkit-scrollbar-thumb:hover {
            background: #888;
        }

        /* Responsive adjustments for smaller screens */
        @media (max-width: 600px) {

            .top-section {
                height: 64px;
            }

            .spacer {
                height: 64px;
            }
        }

        .notification-badge {
            background-color: red;
            color: white;
            font-size: 12px;
            font-weight: bold;
            padding: 2px 6px;
            border-radius: 50%;
            margin-left: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 18px;
            height: 18px;
            line-height: 1;
            box-sizing: border-box;
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Top Section with Back Arrow and Header -->
        <div class="top-section <?php echo $selected_friend_id ? 'hidden' : ''; ?>">
            <a href="homepage.php"><img class="arrow" src="assets/arrow.png" alt="back arrow"
                    style="height: 40px; width: 40px;"></a>
            <h1 style="height: 50px;">Messages</h1>
        </div>
        <!-- Spacer Div (Same Height as Top Section) -->
        <div class="spacer"></div>
        <!-- Friend List with Latest Messages -->
        <?php if (!$selected_friend_id): ?>
            <div class="friend-list">
                <?php foreach ($friends as $friend): ?>
                    <div class="friend-item"
                        onclick="window.location.href='messages.php?friend_id=<?php echo $friend['id']; ?>'">
                        <img src="uploads/profile_pictures/<?php echo htmlspecialchars($friend['profilePicture']); ?>"
                            alt="Profile Picture">
                        <div class="friend-info">
                            <div class="friend-username">
                                <?php echo htmlspecialchars($friend['username']); ?>
                                <!-- Notification Badge for Unread Messages -->
                                <?php if ($unread_counts[$friend['id']] > 0): ?>
                                    <span class="notification-badge"><?php echo $unread_counts[$friend['id']]; ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="latest-message">
                                <?php if (isset($latest_messages[$friend['id']])): ?>
                                    <?php echo htmlspecialchars($latest_messages[$friend['id']]['message']); ?>
                                <?php else: ?>
                                    Start chatting!
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Chat Log for Selected Friend -->
        <?php if ($selected_friend_id): ?>
            <?php
            $selected_friend = $friends[array_search($selected_friend_id, array_column($friends, 'id'))];
            ?>
            <!-- Chat Header -->
            <div class="chat-header">
                <img src="assets/arrow.png" alt="back arrow" class="arrow" onclick="goBack()"
                    style="height: 40px; width: 40px;">
                <img src="uploads/profile_pictures/<?php echo htmlspecialchars($selected_friend['profilePicture']); ?>"
                    alt="Profile Picture">
                <h1>
                    <?php echo htmlspecialchars($selected_friend['username']); ?>
                </h1>
            </div>

            <!-- Chat Log -->
            <div class="chat-log">
                <?php if (!empty($chat_log)): ?>
                    <?php foreach ($chat_log as $message): ?>
                        <div class="message-bubble <?php echo $message['sender_id'] == $user_id ? 'sent' : 'received'; ?>">
                            <div class="message-content">
                                <?php echo htmlspecialchars($message['message']); ?>
                            </div>
                            <div class="message-time">
                                <?php echo htmlspecialchars($message['created_at']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No messages yet. Start the conversation!</p>
                <?php endif; ?>
            </div>

            <!-- Message Input Area -->
            <div class="message-input-area">
                <form method="POST" action="messages.php?friend_id=<?php echo $selected_friend_id; ?>"
                    style="width: 100%; display: flex; align-items: center; gap: 10px;">
                    <!-- Hidden input for receiver_id -->
                    <input type="hidden" name="receiver_id" value="<?php echo $selected_friend_id; ?>">
                    <textarea name="message" rows="1" placeholder="Type a message..." required></textarea>
                    <button class="submitbtn" type="submit" name="send_message">Send</button>
                </form>
            </div>
            <a id="chat-bottom" style="visibility: hidden;"></a>
        <?php endif; ?>
    </div>
    <script>
        // Function to scroll to the bottom anchor tag instantly
        function scrollToBottom() {
            const anchor = document.getElementById('chat-bottom');
            if (anchor) {
                // Debugging
                console.log('Scrolling to bottom anchor instantly...'); 
                 // Scroll down instantly
                anchor.scrollIntoView({ behavior: 'auto', block: 'end' });
            }
        }

        // Function to handle the back arrow click
        function goBack() {
            // Show the top-section container instantly
            const topSection = document.querySelector('.top-section');
            const placeholder = document.querySelector('.spacer');
            const divider = document.querySelector('.divider');
            if (topSection && placeholder && divider) {
                topSection.classList.remove('hidden');
                placeholder.classList.add('hidden'); 
                divider.classList.remove('hidden'); 
            }

            // Navigate back instantly
            if (document.referrer && !document.referrer.includes(window.location.href)) {
                window.location.href = document.referrer;
            } else {
                // Default to messages.php if the referrer is invalid or the same page
                window.location.href = 'messages.php';
            }
        }

        // Use MutationObserver to detect when the chat log is updated
        const chatLogContainer = document.querySelector('.chat-log');
        if (chatLogContainer) {
            const observer = new MutationObserver((mutationsList) => {
                for (const mutation of mutationsList) {
                    if (mutation.type === 'childList') {
                        //Debugging
                        console.log('Chat log updated. Scrolling to bottom...');
                        //Scroll after update
                        scrollToBottom();
                    }
                }
            });

            // Observe changes to the chat log's child nodes
            observer.observe(chatLogContainer, { childList: true, subtree: true });
        }

        // Hide the top-section container when a chat log is opened
        document.addEventListener('DOMContentLoaded', () => {
            const selectedFriendId = "<?php echo $selected_friend_id; ?>"; // Get the selected friend ID from PHP
            const topSection = document.querySelector('.top-section');
            const placeholder = document.querySelector('.spacer');
            const divider = document.querySelector('.divider');

            if (selectedFriendId && topSection && placeholder && divider) {
                topSection.classList.add('hidden'); 
                placeholder.classList.remove('hidden');
                divider.classList.add('hidden');
            }
            // Debugging
            console.log('DOM fully loaded. Attempting to scroll...'); 
            scrollToBottom();
        });

        // Scroll to the bottom when the window is fully loaded (fallback)
        window.addEventListener('load', () => {
            // Debugging
            console.log('Window fully loaded. Attempting to scroll...'); 
            scrollToBottom();
        });


    </script>
</body>

</html>
