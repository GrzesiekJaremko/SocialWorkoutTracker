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

// Get the plan_id and username from the URL
if (!isset($_GET['plan_id']) || !isset($_GET['username'])) {
    die("Invalid request.");
}

$plan_id = intval($_GET['plan_id']);
$searched_username = htmlspecialchars($_GET['username']);

// Fetch the plan details
$plan_query = mysqli_prepare($conn, "SELECT * FROM workout_plans WHERE id = ?");
mysqli_stmt_bind_param($plan_query, "i", $plan_id);
mysqli_stmt_execute($plan_query);
$plan_result = mysqli_stmt_get_result($plan_query);
$plan = mysqli_fetch_assoc($plan_result);

if (!$plan) {
    die("Plan not found.");
}

// Fetch the workouts for the selected plan
$workouts_query = mysqli_prepare($conn, "SELECT * FROM workouts WHERE workout_plan_id = ?");
mysqli_stmt_bind_param($workouts_query, "i", $plan_id);
mysqli_stmt_execute($workouts_query);
$workouts_result = mysqli_stmt_get_result($workouts_query);
$workouts = mysqli_fetch_all($workouts_result, MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Workouts for <?php echo htmlspecialchars($plan['name']); ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        .back-button {
            text-decoration: none;
            font-size: 24px;
            margin-right: 10px;
            color: #000;
        }

        .back-button:hover {
            color: #555;
        }

        .page-header {
            display: flex;
            align-items: center;
        }

        /* Personal Records Section */
        .container2 {
            width: 100%;
            background-color: #1e1e1e;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 8px;
            text-align: left;
            width: 100%;
        }

        .container2 h3 {
            margin-top: 0;
            font-size: 1.25rem;
            color: #ffffff;
        }

        .container2 h4 {
            margin: 16px 0 8px;
            font-size: 1.1rem;
            color: #ffffff;
        }

        .container2 ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .container2 li {
            margin: 8px 0;
            color: #cccccc;
        }
    </style>
</head>

<body>
    <!-- Fixed Top Section -->
    <div class="top-section">
        <!-- Back Arrow -->
        <a href="<?php echo htmlspecialchars($_SERVER['HTTP_REFERER'] ?? 'homepage.php'); ?>" style="height:40px;">
            <img class="arrow" src="assets/arrow.png" alt="back arrow" style="height: 40px; width: auto;">
        </a>
        <h1 style="height:50px;"><?php echo htmlspecialchars($searched_username); ?>'s <?php echo htmlspecialchars($plan['name']); ?> plan</h1>
    </div>
    <!-- Spacer Div (Same Height as Top Section) -->
    <div class="spacer"></div>

    <!-- Workouts List -->
    <?php if (!empty($workouts)): ?>
        <?php foreach ($workouts as $workout): ?>
            <div style="width:100%;">
                <h2><?php echo htmlspecialchars($workout['name']); ?></h2>
                
                <!-- Fetch and display the latest session for this workout -->
                <?php
                // Fetch the latest session for this workout
                $latest_session_query = mysqli_prepare($conn, "
                SELECT * FROM workout_sessions 
                WHERE workout_id = ? 
                ORDER BY end_time DESC 
                LIMIT 1
                ");
                mysqli_stmt_bind_param($latest_session_query, "i", $workout['id']);
                mysqli_stmt_execute($latest_session_query);
                $latest_session_result = mysqli_stmt_get_result($latest_session_query);
                $latest_session = mysqli_fetch_assoc($latest_session_result);
                
                if ($latest_session): ?>
                    <div class="container2">
                    <h3>Latest Session: <?php echo htmlspecialchars($latest_session['end_time']); ?></h3>

                    <!-- Fetch and display the logs for this session -->
                    <?php
                    $session_logs_query = mysqli_prepare($conn, "
                        SELECT * FROM workout_logs 
                        WHERE session_id = ? 
                        ORDER BY set_number ASC
                    ");
                    mysqli_stmt_bind_param($session_logs_query, "i", $latest_session['id']);
                    mysqli_stmt_execute($session_logs_query);
                    $session_logs_result = mysqli_stmt_get_result($session_logs_query);
                    $session_logs = mysqli_fetch_all($session_logs_result, MYSQLI_ASSOC);
                    ?>

                    <?php if (!empty($session_logs)): ?>
                        <ul>
                                <?php foreach ($session_logs as $log): ?>
                                    <li>
                                        <!-- Display set number, reps, and weight on the same line -->
                                        <p>Set <?php echo htmlspecialchars($log['set_number']); ?>, Reps:
                                        <?php echo htmlspecialchars($log['reps']); ?>, Weight:
                                        <?php echo htmlspecialchars($log['weight']); ?> kg</p>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                    <?php else: ?>
                        <div class="container2">
                        <p>No logs found for this session.</p>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="container2">
                    <p>No sessions found for this workout.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="container2">
        <p>No workouts found for this plan.</p>
        </div>
    <?php endif; ?>
</body>

</html>
