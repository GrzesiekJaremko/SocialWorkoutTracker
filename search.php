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

// Determine the back link based on the referrer
// Default back link
$back_link = "homepage.php";
$search_term = ''; 

if (isset($_SERVER['HTTP_REFERER'])) {
    $referrer = $_SERVER['HTTP_REFERER'];
    // Check if the referrer is the search_result.php page
    if (strpos($referrer, 'search_result.php') !== false) {
        // Get the search term from the query parameters
        $search_term = isset($_GET['search_term']) ? $_GET['search_term'] : '';
        $back_link = "search_result.php?search_username=" . urlencode($search_term);
    }
    // Check if the referrer is the friends page
    elseif (strpos($referrer, 'friends.php') !== false) {
        $back_link = "friends.php";
    }
}

// Handle search form submission
$searched_user = null;
// Check if the searched user is already a friend
$is_friend = false; 
// Check if a friend request has been sent
$friend_request_sent = false; 
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['search_username'])) {
    $search_username = htmlspecialchars($_GET['search_username']);

    // Redirect to profile.php if the user searches for themselves
    if ($search_username === $username) {
        header("Location: profile.php");
        exit();
    }

    // Fetch the searched user's details
    $searched_user_query = mysqli_prepare($conn, "SELECT *, profilePicture, date_of_creation, bio FROM users WHERE username = ?");
    mysqli_stmt_bind_param($searched_user_query, "s", $search_username);
    mysqli_stmt_execute($searched_user_query);
    $searched_user_result = mysqli_stmt_get_result($searched_user_query);
    $searched_user = mysqli_fetch_assoc($searched_user_result);

    if ($searched_user) {
        $searched_user_id = $searched_user['id'];

        // Initialize stats to 0
        $searched_user['total_sets'] = 0;
        $searched_user['total_sessions'] = 0;

        // Fetch the searched user's stats
        // Total Sets Recorded
        $total_sets_query = mysqli_prepare($conn, "SELECT SUM(set_number) AS total_sets FROM workout_logs WHERE user_id = ?");
        mysqli_stmt_bind_param($total_sets_query, "i", $searched_user_id);
        mysqli_stmt_execute($total_sets_query);
        $total_sets_result = mysqli_stmt_get_result($total_sets_query);
        $total_sets_row = mysqli_fetch_assoc($total_sets_result);
        $searched_user['total_sets'] = $total_sets_row['total_sets'] ?? 0;

        // Total Sessions Completed
        $total_sessions_query = mysqli_prepare($conn, "SELECT COUNT(DISTINCT DATE(date)) AS total_sessions FROM workout_logs WHERE user_id = ?");
        mysqli_stmt_bind_param($total_sessions_query, "i", $searched_user_id);
        mysqli_stmt_execute($total_sessions_query);
        $total_sessions_result = mysqli_stmt_get_result($total_sessions_query);
        $total_sessions_row = mysqli_fetch_assoc($total_sessions_result);
        $searched_user['total_sessions'] = $total_sessions_row['total_sessions'] ?? 0;

        // Last Time in the Gym
        $last_session_query = mysqli_prepare($conn, "SELECT MAX(end_time) AS last_session FROM workout_sessions WHERE user_id = ?");
        mysqli_stmt_bind_param($last_session_query, "i", $searched_user_id);
        mysqli_stmt_execute($last_session_query);
        $last_session_result = mysqli_stmt_get_result($last_session_query);
        $last_session = mysqli_fetch_assoc($last_session_result)['last_session'];

        if ($last_session) {
            $last_session_time = new DateTime($last_session);
            $current_time = new DateTime();
            $days_since_last_session = $current_time->diff($last_session_time)->days;

            // Customize the message based on the number of days
            if ($days_since_last_session === 0) {
                $last_session_message = "today";
            } elseif ($days_since_last_session === 1) {
                $last_session_message = "yesterday";
            } else {
                $last_session_message = "$days_since_last_session days ago";
            }
        } else {
            $last_session_message = "No sessions recorded yet.";
        }

        // Fetch all workouts and their highest weight with corresponding reps
        $workouts_query = mysqli_prepare($conn, "
            SELECT w.name, l.weight AS max_weight, l.reps 
            FROM workouts w 
            LEFT JOIN workout_logs l ON w.id = l.workout_id 
            WHERE w.workout_plan_id IN (SELECT id FROM workout_plans WHERE user_id = ?) 
            AND l.weight = (SELECT MAX(weight) FROM workout_logs WHERE workout_id = w.id AND user_id = ?) 
            GROUP BY w.name 
            ORDER BY w.name ASC
        ");
        mysqli_stmt_bind_param($workouts_query, "ii", $searched_user_id, $searched_user_id);
        mysqli_stmt_execute($workouts_query);
        $workouts_result = mysqli_stmt_get_result($workouts_query);
        $workouts = mysqli_fetch_all($workouts_result, MYSQLI_ASSOC);

        // Check if the searched user is already a friend
        $check_friendship_query = mysqli_prepare($conn, "SELECT * FROM friends WHERE user_id = ? AND friend_id = ?");
        mysqli_stmt_bind_param($check_friendship_query, "ii", $user_id, $searched_user_id);
        mysqli_stmt_execute($check_friendship_query);
        mysqli_stmt_store_result($check_friendship_query);
        $is_friend = mysqli_stmt_num_rows($check_friendship_query) > 0;

        // Check if a friend request has already been sent
        $check_request_query = mysqli_prepare($conn, "SELECT * FROM friend_requests WHERE sender_id = ? AND receiver_id = ? AND status = 'pending'");
        mysqli_stmt_bind_param($check_request_query, "ii", $user_id, $searched_user_id);
        mysqli_stmt_execute($check_request_query);
        mysqli_stmt_store_result($check_request_query);
        $friend_request_sent = mysqli_stmt_num_rows($check_request_query) > 0;
    }
}

// Handle send friend request form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_request'])) {
    $receiver_id = intval($_POST['receiver_id']);
    $search_username = htmlspecialchars($_POST['search_username']);

    // Check if a friend request already exists
    $check_request_query = mysqli_prepare($conn, "SELECT * FROM friend_requests WHERE sender_id = ? AND receiver_id = ? AND status = 'pending'");
    mysqli_stmt_bind_param($check_request_query, "ii", $user_id, $receiver_id);
    mysqli_stmt_execute($check_request_query);
    mysqli_stmt_store_result($check_request_query);

    if (mysqli_stmt_num_rows($check_request_query) === 0) {
        // Send the friend request
        $send_request_query = mysqli_prepare($conn, "INSERT INTO friend_requests (sender_id, receiver_id, status) VALUES (?, ?, 'pending')");
        mysqli_stmt_bind_param($send_request_query, "ii", $user_id, $receiver_id);
        mysqli_stmt_execute($send_request_query);

        if (mysqli_stmt_affected_rows($send_request_query)) {
            // Redirect to preserve the search_username parameter
            header("Location: search.php?search_username=" . urlencode($search_username));
            exit();
        } else {
            $error = "Failed to send friend request. Please try again.";
        }
    } else {
        $error = "Friend request already sent.";
    }
}

// Handle remove friend form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_friend'])) {
    $friend_id = intval($_POST['friend_id']);
    $search_username = htmlspecialchars($_POST['search_username']);

    // Remove the friendship from both sides
    $remove_friend_query = mysqli_prepare($conn, "DELETE FROM friends WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)");
    mysqli_stmt_bind_param($remove_friend_query, "iiii", $user_id, $friend_id, $friend_id, $user_id);
    mysqli_stmt_execute($remove_friend_query);

    if (mysqli_stmt_affected_rows($remove_friend_query)) {
        // Redirect to preserve the search_username parameter
        header("Location: search.php?search_username=" . urlencode($search_username));
        exit();
    } else {
        $error = "Failed to remove friend. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Search User</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
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

        .top-section h2 {
            margin: 0;
            font-size: 1.5rem;
            color: #ffffff;
        }

        /* Spacer Div (Same Height as Top Section including padding) */
        .spacer {
            height: 72px;
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

        .container {
            width: 100%;
            margin: 0;
            padding: 8px;
        }

        .profile-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }

        .profile-header h1 {
            margin: 0;
            font-size: 1.5rem;
        }

        .profile-header .arrow {
            width: 1.5rem;
            height: 1.5rem;
            cursor: pointer;
        }

        .profile-header-container {
            width: 100%;
            background-color: #1e1e1e;
            border-radius: 8px;
            padding: 8px;
            margin-bottom: 16px;
            gap: 16px;
        }

        .profile-picture {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
        }

        .profile-info {
            flex: 1;
        }

        .profile-info h2 {
            margin: 0;
            font-size: 1.25rem;
            color: #ffffff;
        }

        .profile-info p {
            margin: 0;
            color: #cccccc;
        }

        .edit-profile-button {
            padding: 8px 16px;
            background-color: #1e90ff;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-size: 0.875rem;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .edit-profile-button:hover {
            background-color: #0077cc;
        }

        /* Stats Section */
        .stats-container {
            background-color: #1e1e1e;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 16px;
            text-align: left;
        }

        .stats-container h2 {
            margin-top: 0;
            font-size: 1.25rem;
            color: #ffffff;
        }

        .stats-container p {
            margin: 8px 0;
            color: #cccccc;
        }

        /* Personal Records Section */
        .personal-records-container {
            background-color: #1e1e1e;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 16px;
            text-align: left;
        }

        .personal-records-container h3 {
            margin-top: 0;
            font-size: 1.25rem;
            color: #ffffff;
        }

        .personal-records-container h4 {
            margin: 16px 0 8px;
            font-size: 1.1rem;
            color: #ffffff;
        }

        .personal-records-container ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .personal-records-container li {
            margin: 8px 0;
            color: #cccccc;
        }

/* Buttons Container */
.buttons-container {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    margin: 8px;
    flex-wrap: wrap; 
}

.friends-label {
    color: green;
    font-weight: bold;
    padding: 0 8px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Buttons */
.remove-friend-button,
.message-button,
.send-request-button {
    height: 30px;
    min-width: 120px; 
    padding: 0 16px;
    border: none;
    border-radius: 8px;
    font-size: 0.875rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0; 
}
.request-sent-message {
    color: green;
    padding: 0 8px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0;
}
/* Form elements */
.buttons-container form {
    margin: 0;
    display: flex; 
}

        .remove-friend-button {
            background-color: #ff4444;
            color: #ffffff;
        }

        .remove-friend-button:hover {
            background-color: #cc0000;
        }

        .message-button {
            background-color: #1e90ff;
            color: #ffffff;
        }

        .message-button:hover {
            background-color: #0077cc;
        }

        .send-request-button {
            background-color: #1e90ff;
            color: #ffffff;
            margin: 8px;
            padding: 8px;
            align-items: center;
        }

        .send-request-button:hover {
            background-color: #0077cc;
        }

        .search-form {
            margin: 8px;
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .search-form input[type="text"] {
            flex-grow: 1;
            border: 1px solid #444;
            border-radius: 5px;
            font-size: 16px;
            background-color: #1e1e1e;
            color: #ffffff;
        }

        .search-form input[type="text"]::placeholder {
            color: #bbb;
        }

        .search-form button {
            padding: 10px 20px;
            background-color: #1e90ff;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
        }

        .picandbio {
            display: flex;
        }

        .button-container {
            align-items: center;
            display: flex;
        }

        /* Hide search button on mobile */
        @media (max-width: 600px) {
            .search-form button {
                display: none;
            }
        }

        /* Responsive Design */
        @media (max-width: 480px) {
            .container {
                padding: 8px;
            }

            .profile-header h1 {
                font-size: 1.25rem;
            }

            .profile-picture {
                width: 80px;
                height: 80px;
            }

            .profile-info h2 {
                font-size: 1.1rem;
            }

            .stats-container h2,
            .personal-records-container h3 {
                font-size: 1.1rem;
            }

        }

        /* Workout Plans Section */
        .workout-plans-container {
            background-color: #1e1e1e;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 8px;
            text-align: left;
        }

        .workout-plans-container h3 {
            margin-top: 0;
            font-size: 1.25rem;
            color: #ffffff;
        }

        .workout-plans-container ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .workout-plans-container li {
            margin: 8px 0;
            color: #cccccc;
        }

        .workout-plans-container a {
            color: #1e90ff;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .workout-plans-container a:hover {
            color: #0077cc;
        }

        /* Hidden sections */
        .friends-only {
            display: none;
        }

        .friends-visible {
            display: block;
        }
    </style>
    <script>
        function confirmRemoveFriend() {
            return confirm("Are you sure you want to remove this friend? This cannot be undone.");
        }
    </script>
</head>

<body>
    <div class="container">
        <!-- Profile Header -->
        <!-- Top Section -->
        <div class="top-section">
            <a href="<?php echo $back_link; ?>"><img class="arrow" src="assets/arrow.png" alt="back arrow"
                    style="height: 40px; width: 40px;"></a>
            <h1>@<?php echo htmlspecialchars($searched_user['username']) ?></h1>
        </div>
        <!-- Spacer Div (Same Height as Top Section) -->
        <div class="spacer"></div>

        <!-- Display Success or Error Messages -->
        <?php if (isset($_GET['success'])): ?>
            <p style="color: green;"><?php echo htmlspecialchars($_GET['success']); ?></p>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <p style="color: red;"><?php echo $error; ?></p>
        <?php endif; ?>

        <!-- Display Searched User's Profile -->
        <?php if ($searched_user): ?>
            <!-- Profile Header Container -->
            <div class="profile-header-container">
                <!-- Profile Picture -->
                <div class="picandbio">

                    <?php
                    $profile_picture_path = "uploads/profile_pictures/" . $searched_user['profilePicture'];
                    ?>
                    <img src="<?php echo $profile_picture_path; ?>" alt="Profile Picture" class="profile-picture">

                    <!-- Profile Info (Username and Bio) -->
                    <div class="profile-info">
                        <?php if (!empty($searched_user['bio'])): ?>
                            <p style="padding: 8px;"><?php echo htmlspecialchars($searched_user['bio'], ENT_QUOTES, 'UTF-8'); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
<div class="buttons-container">
    <!-- Friend Request or Remove Friend Button -->
    <?php if ($is_friend): ?>
        <p class="friends-label">Friends</p>
        <form method="POST" action="search.php" style="display: flex; margin: 0;"
            onsubmit="return confirmRemoveFriend()">
            <input type="hidden" name="friend_id" value="<?php echo $searched_user['id']; ?>">
            <input type="hidden" name="search_username"
                value="<?php echo htmlspecialchars($search_username); ?>">
            <button type="submit" name="remove_friend" class="remove-friend-button">Remove Friend</button>
        </form>
        <!-- Message Button -->
        <a href="messages.php?friend_id=<?php echo $searched_user['id']; ?>" class="message-button">Message</a>
    <?php elseif ($friend_request_sent): ?>
        <p style="color: green; margin: 8px; display: flex; align-items: center;">Friend request sent.</p>
    <?php else: ?>
        <form method="POST" action="search.php" style="display: flex; margin: 0;">
            <input type="hidden" name="receiver_id" value="<?php echo $searched_user['id']; ?>">
            <input type="hidden" name="search_username"
                value="<?php echo htmlspecialchars($search_username); ?>">
            <button type="submit" name="send_request" class="send-request-button">Send Friend Request</button>
        </form>
    <?php endif; ?>
</div>
            </div>

            <!-- Stats Section (Only visible to friends) -->
            <div class="stats-container <?php echo $is_friend ? 'friends-visible' : 'friends-only'; ?>">
                <h2>Stats</h2>
                <p><strong>Last Time in the Gym:</strong> <?php echo $last_session_message; ?></p>
                <p><strong>Total Sets Recorded:</strong> <?php echo $searched_user['total_sets']; ?></p>
                <p><strong>Total Sessions Completed:</strong> <?php echo $searched_user['total_sessions']; ?></p>
            </div>

            <!-- Personal Records Section (Only visible to friends) -->
            <div class="personal-records-container <?php echo $is_friend ? 'friends-visible' : 'friends-only'; ?>">
                <h3>Personal Records</h3>
                <?php
                // Fetch all workout plans and their associated workouts
                $workout_plans_query = mysqli_prepare($conn, "
                    SELECT DISTINCT wp.id AS plan_id, wp.name AS plan_name, w.id AS workout_id, w.name AS workout_name, l.weight AS max_weight, l.reps 
                    FROM workout_plans wp
                    LEFT JOIN workouts w ON wp.id = w.workout_plan_id
                    LEFT JOIN workout_logs l ON w.id = l.workout_id 
                    WHERE wp.user_id = ? 
                    AND l.weight = (SELECT MAX(weight) FROM workout_logs WHERE workout_id = w.id AND user_id = ?)
                    AND l.reps = (SELECT MAX(reps) FROM workout_logs WHERE workout_id = w.id AND user_id = ? AND weight = l.weight)
                    ORDER BY wp.name ASC, w.name ASC
                ");
                mysqli_stmt_bind_param($workout_plans_query, "iii", $searched_user_id, $searched_user_id, $searched_user_id);
                mysqli_stmt_execute($workout_plans_query);
                $workout_plans_result = mysqli_stmt_get_result($workout_plans_query);
                $workout_plans = mysqli_fetch_all($workout_plans_result, MYSQLI_ASSOC);

                // Group workouts by their plans
                $grouped_workouts = [];
                foreach ($workout_plans as $workout) {
                    $plan_name = $workout['plan_name'];
                    if (!isset($grouped_workouts[$plan_name])) {
                        $grouped_workouts[$plan_name] = [];
                    }
                    $grouped_workouts[$plan_name][] = $workout;
                }

                // Display grouped workouts
                if (!empty($grouped_workouts)):
                    foreach ($grouped_workouts as $plan_name => $workouts):
                        ?>
                        <h4><?php echo htmlspecialchars($plan_name); ?></h4>
                        <ul>
                            <?php foreach ($workouts as $workout): ?>
                                <li>
                                    <strong><?php echo htmlspecialchars($workout['workout_name']); ?>:</strong>
                                    Weight: <?php echo $workout['max_weight'] ?? 'N/A'; ?> kg,
                                    Reps: <?php echo $workout['reps'] ?? 'N/A'; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endforeach;
                else: ?>
                    <p>No workout data available.</p>
                <?php endif; ?>
            </div>

            <!-- Workout Plans Section (Only visible to friends) -->
            <div class="workout-plans-container <?php echo $is_friend ? 'friends-visible' : 'friends-only'; ?>">
                <h3>Workout Plans</h3>
                <?php
                // Fetch workout plans for the searched user
                $workout_plans_query = mysqli_prepare($conn, "
                    SELECT id, name 
                    FROM workout_plans 
                    WHERE user_id = ?
                    ORDER BY created_at ASC
                ");
                mysqli_stmt_bind_param($workout_plans_query, "i", $searched_user_id);
                mysqli_stmt_execute($workout_plans_query);
                $workout_plans_result = mysqli_stmt_get_result($workout_plans_query);
                $workout_plans = mysqli_fetch_all($workout_plans_result, MYSQLI_ASSOC);

                if (!empty($workout_plans)):
                    ?>
                    <ul>
                        <?php foreach ($workout_plans as $plan): ?>
                            <li>
                                <a class="plan-btn"
                                    href="usersplan.php?plan_id=<?php echo $plan['id']; ?>&user_id=<?php echo $searched_user_id; ?>&username=<?php echo urlencode($searched_user['username']); ?>"
                                    style="color: #ffffff;">
                                    <?php echo htmlspecialchars($plan['name']); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>No workout plans found.</p>
                <?php endif; ?>
            </div>

            <?php if (!$is_friend): ?>
                <div class="stats-container">
                    <p>Become friends to view <?php echo htmlspecialchars($searched_user['username']); ?>'s stats and workout
                        data.</p>
                </div>
            <?php endif; ?>
        <?php elseif (isset($_GET['search_username'])): ?>
            <p>User not found.</p>
        <?php endif; ?>
    </div>
</body>

</html>
