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

// Fetch user details
$user_query = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ?");
mysqli_stmt_bind_param($user_query, "s", $username);
mysqli_stmt_execute($user_query);
$result = mysqli_stmt_get_result($user_query);
$user = mysqli_fetch_assoc($result);
$user_id = $user['id'];

// Fetch all workout plans for the user
$workout_plans_query = mysqli_prepare($conn, "SELECT id, name FROM workout_plans WHERE user_id = ?");
mysqli_stmt_bind_param($workout_plans_query, "i", $user_id);
mysqli_stmt_execute($workout_plans_query);
$workout_plans_result = mysqli_stmt_get_result($workout_plans_query);
$workout_plans = mysqli_fetch_all($workout_plans_result, MYSQLI_ASSOC);

// Default to the first workout plan if none is selected
$selected_workout_plan_id = isset($_GET['workout_plan_id']) ? intval($_GET['workout_plan_id']) : ($workout_plans[0]['id'] ?? null);

// Fetch workouts for the selected workout plan
$workouts = [];
if ($selected_workout_plan_id) {
    $workouts_query = mysqli_prepare($conn, "SELECT id, name FROM workouts WHERE workout_plan_id = ?");
    mysqli_stmt_bind_param($workouts_query, "i", $selected_workout_plan_id);
    mysqli_stmt_execute($workouts_query);
    $workouts_result = mysqli_stmt_get_result($workouts_query);
    $workouts = mysqli_fetch_all($workouts_result, MYSQLI_ASSOC);
}

// Default to the first workout if none is selected
$selected_workout_id = isset($_GET['workout_id']) ? intval($_GET['workout_id']) : ($workouts[0]['id'] ?? null);

// Fetch workout logs for the selected workout (only the best set per session)
// Default to 'all_time'
$time_frame = isset($_GET['time_frame']) ? $_GET['time_frame'] : 'all_time'; 
$workout_logs = [];
$labels = [];
$weights = [];
// Default to grey for no change
$weight_line_color = 'grey'; 
$weight_percentage_change = 0;
// Most recent session's best set
$current_weight = 0; 
// Difference between current weight and first weight
$progress_weight = 0; 
$time_frame_text = 'All Time'; 

// Fetch volume data for the selected workout
$volume_labels = [];
$volume_data = [];
$volume_line_color = 'grey'; 
$current_volume = 0;
$volume_percentage_change = 0;
$volume_progress = 0;

if ($selected_workout_id) {
    // Determine the date range based on the selected time frame
    $date_range = '';
    switch ($time_frame) {
        case '1_month':
            $date_range = "AND date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
            $time_frame_text = 'Last Month';
            break;
        case '3_months':
            $date_range = "AND date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
            $time_frame_text = 'Last 3 Months';
            break;
        case '6_months':
            $date_range = "AND date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)";
            $time_frame_text = 'Last 6 Months';
            break;
        case '12_months':
            $date_range = "AND date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)";
            $time_frame_text = 'Last 12 Months';
            break;
        case 'all_time':
        default:
            $date_range = "";
            $time_frame_text = 'All Time';
            break;
    }

    // Fetch the best set (maximum weight) for each session of the selected workout within the time frame
    $logs_query = mysqli_prepare($conn, "
        SELECT DATE(date) AS session_date, MAX(weight) AS max_weight
        FROM workout_logs
        WHERE workout_id = ? AND user_id = ? $date_range
        GROUP BY session_date
        ORDER BY session_date ASC
    ");
    mysqli_stmt_bind_param($logs_query, "ii", $selected_workout_id, $user_id);
    mysqli_stmt_execute($logs_query);
    $logs_result = mysqli_stmt_get_result($logs_query);
    $workout_logs = mysqli_fetch_all($logs_result, MYSQLI_ASSOC);

    // Prepare data for the weight graph
    if (!empty($workout_logs)) {
        $first_weight = floatval($workout_logs[0]['max_weight']);
        // Most recent session's best set
        $current_weight = floatval($workout_logs[count($workout_logs) - 1]['max_weight']); 
        // Calculate progress weight
        $progress_weight = $current_weight - $first_weight; 

        // Calculate percentage change for weight
        $weight_percentage_change = (($current_weight - $first_weight) / $first_weight) * 100;

        // Determine line color based on progression
        if ($weight_percentage_change > 0) {
            $weight_line_color = 'green'; // Progress
        } elseif ($weight_percentage_change < 0) {
            $weight_line_color = 'red'; // Regression
        } else {
            $weight_line_color = 'grey'; // No change
        }

        // Extract labels (dates) and weights for the graph
        foreach ($workout_logs as $log) {
            $labels[] = $log['session_date'];
            $weights[] = floatval($log['max_weight']);
        }
    }

    // Fetch volume data for the selected workout
    $volume_query = mysqli_prepare($conn, "
        SELECT DATE(date) AS session_date, SUM(reps * weight) AS total_volume
        FROM workout_logs
        WHERE workout_id = ? AND user_id = ? $date_range
        GROUP BY session_date
        ORDER BY session_date ASC
    ");
    mysqli_stmt_bind_param($volume_query, "ii", $selected_workout_id, $user_id);
    mysqli_stmt_execute($volume_query);
    $volume_result = mysqli_stmt_get_result($volume_query);
    $volume_logs = mysqli_fetch_all($volume_result, MYSQLI_ASSOC);

    // Prepare data for the volume graph
    if (!empty($volume_logs)) {
        $first_volume = floatval($volume_logs[0]['total_volume']);
        // Most recent session's total volume
        $current_volume = floatval($volume_logs[count($volume_logs) - 1]['total_volume']); 
        // Calculate volume progress
        $volume_progress = $current_volume - $first_volume; 

        // Calculate percentage change for volume
        $volume_percentage_change = (($current_volume - $first_volume) / $first_volume) * 100;

        // Determine line color based on progression
        if ($volume_percentage_change > 0) {
            $volume_line_color = 'green'; // Progress
        } elseif ($volume_percentage_change < 0) {
            $volume_line_color = 'red'; // Regression
        } else {
            $volume_line_color = 'grey'; // No change
        }

        // Extract labels (dates) and volumes for the graph
        foreach ($volume_logs as $log) {
            $volume_labels[] = $log['session_date'];
            $volume_data[] = floatval($log['total_volume']);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Progress Tracker</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            background-color: #121212;
            color: #ffffff;
            line-height: 1.6;
            padding: 16px;
            display: -webkit-flex;
            display: flex;
            -webkit-flex-direction: column;
            flex-direction: column;
            -webkit-align-items: center;
            align-items: center;
            min-height: 100vh;
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

        .top-section h1 {
            margin: 0;
            font-size: 1.5rem;
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

        .container {
            padding: 0px;
            max-width: 100%;
            margin: 0 auto;
        }

        .workout-plan-select,
        .workout-select {
            margin-bottom: 20px;
        }

        .workout-plan-select select,
        .workout-select select {
            padding: 10px;
            font-size: 16px;
            background-color: #1e1e1e;
            color: #ffffff;
            border: 1px solid #333;
            border-radius: 5px;
            width: 100%;
        }

        .graph-container {
            background-color: #1e1e1e;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }

        .time-frame-links {
            display: flex;
            justify-content: flex-start;
            gap: 15px;
            margin-top: 10px;
            font-size: 14px;
        }

        .time-frame-links a {
            color: #1e90ff;
            text-decoration: none;
            cursor: pointer;
        }

        .time-frame-links a:hover {
            text-decoration: underline;
        }

        .current-weight,
        .current-volume {
            text-align: center;
            margin-top: 10px;
            font-size: 18px;
            font-weight: bold;
        }

        .progress,
        .volume-progress {
            text-align: center;
            margin-top: 5px;
            font-size: 16px;
            color:
                <?php echo $line_color; ?>
            ;
        }

        .graph-heading {
            text-align: left;
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 8px;
            margin-top: 24px;
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
        
    </style>
</head>

<body>
    <!-- Fixed Top Section -->
    <div class="top-section">
        <a href="homepage.php" style="height:40px;"><img class="arrow" src="assets/arrow.png" alt="back arrow"
                style="height: 40px; width: auto;"></a>
        <h1 style="height:50px;">Your Progress</h1>
    </div>
    <!-- Spacer Div (Same Height as Top Section) -->
    <div class="spacer"></div>

    <div class="container">

        <!-- Workout Plan Selection Dropdown -->
        <div class="workout-plan-select">
            <label for="workout_plan">Select Workout Plan:</label>
            <select id="workout_plan"
                onchange="window.location.href = 'progress.php?workout_plan_id=' + this.value + '&workout_id=' + this.options[this.selectedIndex].getAttribute('data-first-workout-id') + '&time_frame=<?php echo $time_frame; ?>';">
                <?php foreach ($workout_plans as $plan): ?>
                    <?php
                    // Fetch the first workout in this plan
                    $first_workout_query = mysqli_prepare($conn, "SELECT id FROM workouts WHERE workout_plan_id = ? LIMIT 1");
                    mysqli_stmt_bind_param($first_workout_query, "i", $plan['id']);
                    mysqli_stmt_execute($first_workout_query);
                    $first_workout_result = mysqli_stmt_get_result($first_workout_query);
                    $first_workout = mysqli_fetch_assoc($first_workout_result);
                    $first_workout_id = $first_workout['id'] ?? null;
                    ?>
                    <option value="<?php echo $plan['id']; ?>" data-first-workout-id="<?php echo $first_workout_id; ?>"
                        <?php echo ($selected_workout_plan_id == $plan['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($plan['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Workout Selection Dropdown -->
        <div class="workout-select">
            <label for="workout">Select Workout:</label>
            <select id="workout"
                onchange="window.location.href = 'progress.php?workout_plan_id=<?php echo $selected_workout_plan_id; ?>&workout_id=' + this.value + '&time_frame=<?php echo $time_frame; ?>';">
                <?php foreach ($workouts as $workout): ?>
                    <option value="<?php echo $workout['id']; ?>" <?php echo ($selected_workout_id == $workout['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($workout['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Time Frame Links -->
        <div class="time-frame-links">
            <a onclick="updateTimeFrame('1_month')">Last Month</a>
            <a onclick="updateTimeFrame('3_months')">Last 3 Months</a>
            <a onclick="updateTimeFrame('6_months')">Last 6 Months</a>
            <a onclick="updateTimeFrame('12_months')">Last 12 Months</a>
            <a onclick="updateTimeFrame('all_time')">All Time</a>
        </div>

        <!-- Weight Section -->
        <div class="graph-heading">Weight:</div>
        <div class="graph-container">
            <canvas id="progressChart"></canvas>
        </div>

        <!-- Current Weight and Progress Display -->
        <?php if ($selected_workout_id && !empty($workout_logs)): ?>
            <div class="current-weight">
                Current Weight: <?php echo number_format($current_weight, 2); ?>kg
            </div>
            <div class="progress" style="color: <?php echo $weight_line_color; ?>;">
                Progress:
                <?php echo ($progress_weight > 0) ? '+' : ''; ?>     <?php echo number_format($progress_weight, 2); ?>kg
                (<?php echo number_format($weight_percentage_change, 2); ?>%)
                <span class="arrow">
                    <?php
                    if ($weight_percentage_change > 0) {
                        echo '↑'; // Green arrow up for progress
                    } elseif ($weight_percentage_change < 0) {
                        echo '↓'; // Red arrow down for regression
                    } else {
                        echo '—'; // Straight line for no change
                    }
                    ?>
                </span>
                in the <?php echo $time_frame_text; ?>!
            </div>
        <?php endif; ?>

        <!-- Volume Section -->
        <div class="graph-heading">Total Volume:</div>
        <div class="graph-container">
            <canvas id="volumeChart"></canvas>
        </div>

        <!-- Current Volume and Volume Progress Display -->
        <?php if ($selected_workout_id && !empty($volume_logs)): ?>
            <div class="current-volume">
                Current Volume: <?php echo number_format($current_volume, 2); ?>kg
            </div>
            <div class="volume-progress" style="color: <?php echo $volume_line_color; ?>;">
                Volume Progress:
                <?php echo ($volume_progress > 0) ? '+' : ''; ?>     <?php echo number_format($volume_progress, 2); ?>kg
                (<?php echo number_format($volume_percentage_change, 2); ?>%)
                <span class="arrow">
                    <?php
                    if ($volume_percentage_change > 0) {
                        echo '↑'; // Green arrow up for progress
                    } elseif ($volume_percentage_change < 0) {
                        echo '↓'; // Red arrow down for regression
                    } else {
                        echo '—'; // Straight line for no change
                    }
                    ?>
                </span>
                in the <?php echo $time_frame_text; ?>!
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Chart.js Configuration for Progress Chart
        const ctx = document.getElementById('progressChart').getContext('2d');
        const progressChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($labels); ?>,
                datasets: [{
                    // Empty label to remove the legend
                    label: '', 
                    data: <?php echo json_encode($weights); ?>,
                    borderColor: '<?php echo $weight_line_color; ?>',
                    borderWidth: 2,
                    fill: false
                }]
            },
            options: {
                plugins: {
                    legend: {
                        display: false 
                    }
                },
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: 'Date'
                        }
                    },
                    y: {
                        title: {
                            display: true,
                            text: 'Weight (kg)'
                        }
                    }
                },
                responsive: true,
                maintainAspectRatio: false
            }
        });

        // Chart.js Configuration for Volume Chart
        const volumeCtx = document.getElementById('volumeChart').getContext('2d');
        const volumeChart = new Chart(volumeCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($volume_labels); ?>,
                datasets: [{
                    // Empty label to remove the legend
                    label: '', 
                    data: <?php echo json_encode($volume_data); ?>,
                    borderColor: '<?php echo $volume_line_color; ?>',
                    borderWidth: 2,
                    fill: false
                }]
            },
            options: {
                plugins: {
                    legend: {
                        display: false 
                    }
                },
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: 'Date'
                        }
                    },
                    y: {
                        title: {
                            display: true,
                            text: 'Volume (kg)'
                        }
                    }
                },
                responsive: true,
                maintainAspectRatio: false
            }
        });

        // Function to update the time frame
        function updateTimeFrame(timeFrame) {
            const workoutId = <?php echo $selected_workout_id ?? 'null'; ?>;
            if (workoutId) {
                window.location.href = `progress.php?workout_plan_id=<?php echo $selected_workout_plan_id; ?>&workout_id=${workoutId}&time_frame=${timeFrame}`;
            }
        }
    </script>
</body>

</html>
