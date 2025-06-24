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

if (!$user) {
    die("User not found.");
}

$user_id = $user['id'];

// Fetch pending friend requests for the current user
$requests_query = mysqli_prepare($conn, "
    SELECT fr.id AS request_id, u.id AS sender_id, u.username, u.profilePicture 
    FROM friend_requests fr 
    JOIN users u ON fr.sender_id = u.id 
    WHERE fr.receiver_id = ? AND fr.status = 'pending'
");
mysqli_stmt_bind_param($requests_query, "i", $user_id);
mysqli_stmt_execute($requests_query);
$requests_result = mysqli_stmt_get_result($requests_query);
$requests = mysqli_fetch_all($requests_result, MYSQLI_ASSOC);

// Handle accept/reject friend request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['accept_request'])) {
        $request_id = intval($_POST['request_id']);
        $sender_id = intval($_POST['sender_id']);

        // Update the request status to 'accepted'
        $update_request_query = mysqli_prepare($conn, "UPDATE friend_requests SET status = 'accepted' WHERE id = ?");
        mysqli_stmt_bind_param($update_request_query, "i", $request_id);
        mysqli_stmt_execute($update_request_query);

        // Add the friendship to the `friends` table
        $add_friend_query = mysqli_prepare($conn, "INSERT INTO friends (user_id, friend_id) VALUES (?, ?), (?, ?)");
        mysqli_stmt_bind_param($add_friend_query, "iiii", $user_id, $sender_id, $sender_id, $user_id);
        mysqli_stmt_execute($add_friend_query);

        // Redirect to refresh the page
        header("Location: friends.php");
        exit();
    } elseif (isset($_POST['reject_request'])) {
        $request_id = intval($_POST['request_id']);

        // Update the request status to 'rejected'
        $update_request_query = mysqli_prepare($conn, "UPDATE friend_requests SET status = 'rejected' WHERE id = ?");
        mysqli_stmt_bind_param($update_request_query, "i", $request_id);
        mysqli_stmt_execute($update_request_query);

        // Redirect to refresh the page
        header("Location: friends.php");
        exit();
    }
}

// Fetch the current user's friends
$friends_query = mysqli_prepare($conn, "
    SELECT u.id, u.username, u.firstName, u.lastName, u.profilePicture 
    FROM friends f 
    JOIN users u ON f.friend_id = u.id 
    WHERE f.user_id = ?
");
mysqli_stmt_bind_param($friends_query, "i", $user_id);
mysqli_stmt_execute($friends_query);
$friends_result = mysqli_stmt_get_result($friends_query);
$friends = mysqli_fetch_all($friends_result, MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Friends</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body{
            padding: 8px;
        }
        .container {
            padding: 0 8px; 
            max-width: 100%; 
            margin: 0 auto;
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

        .profile-header {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 16px;
        }

        .profile-header h1 {
            margin: 0;
            font-size: 1.5rem;
            color: #ffffff;
        }

        .profile-header .arrow {
            width: 1.5rem;
            height: 1.5rem;
            cursor: pointer;
        }

        /* Friend Requests Section */
        .requests-container {
            background-color: #1e1e1e; 
            padding: 16px;
            border-radius: 8px; 
        }

        .requests-container h2 {
            margin-top: 0;
            font-size: 1.25rem;
            color: #ffffff;
            margin-bottom: 16px;
        }

        .requests-container ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .requests-container li {
            display: flex;
            align-items: center;
            padding: 12px;
            border-bottom: 1px solid #333333;
        }

        .requests-container li:last-child {
            border-bottom: none;
        }

        .requests-container img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            margin-right: 15px;
        }

        .requests-container span {
            flex-grow: 1;
            font-size: 1rem;
            color: #ffffff;
        }

        .requests-container button {
            padding: 12px 20px; 
            background-color: #1e90ff;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem; 
            margin-left: 10px;
        }

        .requests-container button:hover {
            background-color: #0077cc;
        }

        .requests-container .reject-btn {
            background-color: #ff4444;
        }

        .requests-container .reject-btn:hover {
            background-color: #cc0000;
        }

        .no-requests {
            color: #cccccc;
            font-style: italic;
            padding: 16px;
        }

        /* Friends List Section */
        .friends-container {
            background-color: #1e1e1e; 
            padding: 16px;
            margin-top: 16px; 
            border-radius: 8px;
        }

        .friends-container h2 {
            margin-top: 0;
            font-size: 1.25rem;
            color: #ffffff;
            margin-bottom: 16px;
        }

        .friends-container ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .friends-container li {
            display: flex;
            align-items: center;
            padding: 12px;
            border-bottom: 1px solid #333333;
            cursor: pointer; 
        }

        .friends-container li:last-child {
            border-bottom: none;
        }

        .friends-container img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            margin-right: 15px;
        }

        .friends-container span {
            flex-grow: 1;
            font-size: 1rem;
            color: #ffffff;
        }

        .no-friends {
            color: #cccccc;
            font-style: italic;
            padding: 16px;
        }
        .container {
            padding: 8px; 
            max-width: 100%; 
            margin: 0 auto; 
        }
        .top-section h2 {
            margin: 0;
            font-size: 1.5rem;
            color: #ffffff;
        }
                /* Responsive adjustments for smaller screens */
@media (max-width: 600px) {

.top-section{
    height: 64px;
}

.spacer {
    height: 64px; 
}
}

    </style>
</head>
<body>
    <div class="container">
        <!-- Profile Header -->
        <div class="top-section">
        <a href="homepage.php"><img class="arrow" src="assets/arrow.png" alt="back arrow" style="height: 40px; width: 40px;">
        </a>
        <h1 style="height: 50px;">Friends</h1>
    </div>
    <!-- Spacer Div (Same Height as Top Section including padding) -->
    <div class="spacer"></div>

    <!-- Friend Requests Section -->
    <div class="requests-container">
        <h2>Friend Requests</h2>
        <?php if (!empty($requests)): ?>
            <ul>
                <?php foreach ($requests as $request): ?>
                    <li>
                        <!-- Display Sender's Profile Picture -->
                        <?php
                        $profile_picture_path = "uploads/profile_pictures/" . $request['profilePicture'];
                        ?>
                        <img src="<?php echo $profile_picture_path; ?>" alt="Profile Picture">
                        
                        <!-- Sender's Username -->
                        <span><?php echo htmlspecialchars($request['username']); ?></span>

                        <!-- Accept Button -->
                        <form method="POST" action="friends.php" style="display: inline;">
                            <input type="hidden" name="request_id" value="<?php echo $request['request_id']; ?>">
                            <input type="hidden" name="sender_id" value="<?php echo $request['sender_id']; ?>">
                            <button type="submit" name="accept_request">Accept</button>
                        </form>

                        <!-- Reject Button -->
                        <form method="POST" action="friends.php" style="display: inline;">
                            <input type="hidden" name="request_id" value="<?php echo $request['request_id']; ?>">
                            <button type="submit" name="reject_request" class="reject-btn">Reject</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="no-requests">No pending friend requests.</p>
        <?php endif; ?>
    </div>

    <!-- Friends List Section -->
    <div class="friends-container">
        <h2>Your Friends</h2>
        <?php if (!empty($friends)): ?>
            <ul>
                <?php foreach ($friends as $friend): ?>
                    <a href="search.php?search_username=<?php echo urlencode($friend['username']); ?>" style="text-decoration: none; color: inherit;">
                        <li>
                            <!-- Display Profile Picture -->
                            <?php
                            $profile_picture_path = "uploads/profile_pictures/" . $friend['profilePicture'];
                            ?>
                            <img src="<?php echo $profile_picture_path; ?>" alt="Profile Picture">
                            
                            <!-- Friend's Name -->
                            <span><?php echo htmlspecialchars($friend['firstName'] . ' ' . $friend['lastName']); ?></span>
                        </li>
                    </a>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="no-friends">You have no friends yet. Search for users to add them!</p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
