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

if (!isset($_GET['workout_id'])) {
    header("Location: homepage.php");
    exit();
}

$workout_id = intval($_GET['workout_id']);
$username = $_SESSION['username'];

// Fetch user ID
$user_query = mysqli_query($conn, "SELECT id FROM users WHERE username='$username'");
$user = mysqli_fetch_assoc($user_query);
$user_id = $user['id'];

// Fetch workout details
$workout_query = mysqli_query($conn, "SELECT * FROM workouts WHERE id='$workout_id' AND workout_plan_id IN (SELECT id FROM workout_plans WHERE user_id='$user_id')");
$workout = mysqli_fetch_assoc($workout_query);

if (!$workout) {
    echo "Workout not found.";
    exit();
}

// Check if there's an active session for this workout
$active_session_query = mysqli_query($conn, "SELECT id FROM workout_sessions WHERE user_id='$user_id' AND workout_id='$workout_id' AND end_time IS NULL");
$active_session = mysqli_fetch_assoc($active_session_query);

// Start a new session if there's no active session
if (!$active_session) {
    $start_time = date("Y-m-d H:i:s");
    mysqli_query($conn, "INSERT INTO workout_sessions (user_id, workout_id, start_time) VALUES ('$user_id', '$workout_id', '$start_time')");
    $active_session_id = mysqli_insert_id($conn);
} else {
    $active_session_id = $active_session['id'];
}

// Initialize session variables to retain form values
if (!isset($_SESSION['form_values'])) {
    $_SESSION['form_values'] = [
        'reps' => '',
        'weight' => ''
    ];
}

// Add workout log functionality
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['log_set'])) {
    // Fetch the highest set number in the current session
    $max_set_query = mysqli_query($conn, "SELECT MAX(set_number) as max_set FROM workout_logs WHERE session_id='$active_session_id'");
    $max_set_result = mysqli_fetch_assoc($max_set_query);
    $set_number = $max_set_result['max_set'] ? $max_set_result['max_set'] + 1 : 1; // Start at 1 if no sets exist

    // Store form values in session
    $_SESSION['form_values'] = [
        'reps' => intval($_POST['reps']),
        'weight' => floatval($_POST['weight'])
    ];

    $reps = intval($_POST['reps']);
    $weight = floatval($_POST['weight']);
    $date = date("Y-m-d H:i:s");

    $insert_log_query = "INSERT INTO workout_logs (workout_id, user_id, set_number, reps, weight, date, session_id) 
                         VALUES ('$workout_id', '$user_id', '$set_number', '$reps', '$weight', '$date', '$active_session_id')";
    if (mysqli_query($conn, $insert_log_query)) {
        // Redirect to the same page to prevent form resubmission on page refresh
        header("Location: add_workout_log.php?workout_id=$workout_id");
        exit();
    } else {
        echo "Error logging workout: " . mysqli_error($conn);
    }
}

// Handle ending the session
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['end_session'])) {
    $end_time = date("Y-m-d H:i:s");
    mysqli_query($conn, "UPDATE workout_sessions SET end_time='$end_time' WHERE id='$active_session_id'");

    // Reset the form values
    $_SESSION['form_values'] = [
        'reps' => '',
        'weight' => ''
    ];

    header("Location: add_workout_log.php?workout_id=$workout_id");
    exit();
}

// Handle deleting a set from the current session
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_set'])) {
    $log_id = intval($_POST['log_id']);

    // Ensure the log belongs to the current session
    $check_log_query = mysqli_query($conn, "SELECT * FROM workout_logs WHERE id='$log_id' AND session_id='$active_session_id'");
    if (mysqli_num_rows($check_log_query) > 0) {
        // Delete the set
        mysqli_query($conn, "DELETE FROM workout_logs WHERE id='$log_id'");

        // Reorder the set numbers for the remaining sets
        $remaining_sets_query = mysqli_query($conn, "
            SELECT id FROM workout_logs 
            WHERE session_id='$active_session_id' 
            ORDER BY set_number ASC
        ");
        $set_number = 1;
        while ($set = mysqli_fetch_assoc($remaining_sets_query)) {
            mysqli_query($conn, "UPDATE workout_logs SET set_number='$set_number' WHERE id='{$set['id']}'");
            $set_number++;
        }

        header("Location: add_workout_log.php?workout_id=$workout_id");
        exit();
    } else {
        echo "Error: You can only delete sets from the current session.";
    }
}

// Retrieve form values from session
$reps = $_SESSION['form_values']['reps'];
$weight = $_SESSION['form_values']['weight'];

// Fetch logs for the current session
$current_session_logs = [];
if ($active_session_id) {
    $current_session_logs_query = mysqli_query($conn, "
        SELECT * FROM workout_logs 
        WHERE session_id='$active_session_id' 
        ORDER BY set_number DESC
    ");
    $current_session_logs = mysqli_fetch_all($current_session_logs_query, MYSQLI_ASSOC);
}

// Fetch logs for the last completed session
$last_session_logs = [];
$last_session_query = mysqli_query($conn, "
    SELECT l.*, s.end_time 
    FROM workout_logs l
    JOIN workout_sessions s ON l.session_id = s.id
    WHERE s.user_id='$user_id' AND s.workout_id='$workout_id' AND s.end_time IS NOT NULL
    ORDER BY s.start_time DESC, l.date DESC
    LIMIT 1
");
$last_session = mysqli_fetch_assoc($last_session_query);
if ($last_session) {
    $last_session_logs_query = mysqli_query($conn, "
        SELECT * FROM workout_logs 
        WHERE session_id='{$last_session['session_id']}' 
        ORDER BY set_number ASC
    ");
    $last_session_logs = mysqli_fetch_all($last_session_logs_query, MYSQLI_ASSOC);

    // Calculate the number of days since the last session
    $last_session_end_time = new DateTime($last_session['end_time']);
    $current_time = new DateTime();
    $days_since_last_session = $current_time->diff($last_session_end_time)->days;

    // Display "today" if the last session was completed today
    $last_trained_message = ($days_since_last_session == 0) ? "today" : "$days_since_last_session days ago";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Track Sets and Reps</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* General container styling */
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
    min-height: 72px; 
    flex-wrap: wrap; 
}
/*Spacer to match nav (including padding) */
.spacer {
    min-height: 72px;
}

/* For mobile */
@media (max-width: 600px) {
    .top-section {
        min-height: 64px;
    }
    .spacer {
        min-height: 64px;
    }
}

.top-section h1 {
    margin: 0;
    font-size: 1.5rem;
    line-height: 1.2;
    word-break: break-word; 
    max-width: calc(100% - 60px);
}

        .top-section h2 {
            margin: 0;
            font-size: 1.5rem;
            color: #ffffff;
        }
        /* Faint line */
        .divider {
            border-bottom: 1px solid #333333; 
            margin: 8px 0; 
        }

        /* Input box styling */
        input[type="number"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #333333;
            border-radius: 8px;
            background-color: #1e1e1e;
            color: #ffffff;
            font-size: 1rem;
            margin-bottom: 16px;
            -webkit-appearance: none;
            appearance: none;
        }

        /* Hide up/down arrows in number inputs */
        input[type="number"]::-webkit-inner-spin-button,
        input[type="number"]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        /* Button styling */
        .log-set-button,
        .end-session-button {
            width: 100%;
            padding: 16px;
            background-color: #1e90ff;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            cursor: pointer;
            margin-bottom: 16px;
            transition: background-color 0.3s ease;
        }

        .log-set-button:hover {
            background-color: #0077cc;
        }

        .end-session-button {
            background-color: #228B22;
        }

        .end-session-button:hover {
            background-color: #1A6A1A;
        }

        .delete-button {
            background-color: #ff4444;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 5px;
            cursor: pointer;
            margin-left: 10px;
        }

        .delete-button:hover {
            background-color: #cc0000;
        }

        /* Personal Records Section */
        .container2 {
            background-color: #1e1e1e;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 16px;
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
    <script>
        // Function to confirm deletion of a set
        function confirmDelete() {
            return confirm("Are you sure you want to delete this set?");
        }

        // Function to confirm ending the session
        function confirmEndSession() {
            return confirm("Are you sure you want to end the session? This action cannot be undone.");
        }
    </script>
</head>

<body>
    <div class="container">
        <!-- Top Section with Back Arrow and Plan Name -->
        <div class="top-section">
            <a href="view_workout_plan.php?id=<?php echo $workout['workout_plan_id']; ?>" style="height: 40px;">
                <img class="arrow" src="assets/arrow.png" alt="back arrow" style="height: 40px; width: auto;">
            </a>
            <h1><?php echo htmlspecialchars($workout['name']); ?></h1>
        </div>
    <!-- Spacer Div (Same Height as Top Section) -->
    <div class="spacer"></div>

        <h2>Log a New Set</h2>
        <form method="POST">
            <!-- Label for Weight -->
            <label for="weight">Weight (kg):</label>
            <input type="number" id="weight" name="weight" step="0.1" placeholder="Weight (kg)"
                value="<?php echo htmlspecialchars($weight); ?>" required>

            <!-- Label for Reps -->
            <label for="reps">Reps:</label>
            <input type="number" id="reps" name="reps" placeholder="Reps" value="<?php echo htmlspecialchars($reps); ?>"
                required>
            <button class="log-set-button" type="submit" name="log_set">Log Set</button>
            <button class="end-session-button" type="submit" name="end_session" onclick="return confirmEndSession()">End Session</button>
        </form>

<!-- Current Session Logs -->
<div class="container2">
    <h3>Current Session Logs</h3>
    <?php if (!empty($current_session_logs)): ?>
        <ul>
            <?php
            // Initialize total volume variable for current session
            $total_volume_current = 0; 
            foreach ($current_session_logs as $log):
                // Calculate volume for this set
                $set_volume = $log['weight'] * $log['reps']; 
                // Add to total volume
                $total_volume_current += $set_volume; 
            ?>
                <li>
                    Set <?php echo $log['set_number']; ?> - Weight:
                    <?php echo $log['weight']; ?> kg, Reps: <?php echo $log['reps']; ?>, Time:
                    <?php echo date("H:i:s", strtotime($log['date'])); ?>
                    <form method="POST" style="display: inline;" onsubmit="return confirmDelete()">
                        <input type="hidden" name="log_id" value="<?php echo $log['id']; ?>">
                        <button type="submit" name="delete_set" class="delete-button">Delete</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
        <!-- Display Total Volume for Current Session -->
        <p><strong>Total Volume: <?php echo $total_volume_current; ?> kg</strong></p>
    <?php else: ?>
        <p>No logs for the current session yet.</p>
    <?php endif; ?>
</div>

<!-- Last Session Logs -->
<div class="container2">
    <h3>Last Session Logs</h3>
    <?php if (!empty($last_session_logs)): ?>
        <p>Last trained <?php echo $last_trained_message; ?>.</p>
        <ul>
            <?php
            // Initialize total volume variable
            $total_volume = 0; 
            foreach ($last_session_logs as $log):
                // Calculate volume for this set
                $set_volume = $log['weight'] * $log['reps'];
                // Add to total volume 
                $total_volume += $set_volume; 
            ?>
                <li>Set <?php echo $log['set_number']; ?> - Weight:
                    <?php echo $log['weight']; ?> kg, Reps: <?php echo $log['reps']; ?>, Time:
                    <?php echo date("H:i:s", strtotime($log['date'])); ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <!-- Display Total Volume -->
        <p><strong>Total Volume: <?php echo $total_volume; ?> kg</strong></p>
    <?php else: ?>
        <p>No logs found for the last session.</p>
    <?php endif; ?>
</div>
    </div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sync spacer height with header height
    const header = document.querySelector('.top-section');
    const spacer = document.querySelector('.spacer');
    
    function updateSpacerHeight() {
        spacer.style.height = header.offsetHeight + 'px';
    }
    // Update on load and if window resizes
    updateSpacerHeight();
    window.addEventListener('resize', updateSpacerHeight);
});
</script>
    
</body>

</html>
