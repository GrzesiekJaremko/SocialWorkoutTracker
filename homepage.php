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

// Fetch user details using prepared statements
$user_query = mysqli_prepare($conn, "SELECT id, firstName, lastName, profilePicture FROM users WHERE username = ?");
mysqli_stmt_bind_param($user_query, "s", $username);
mysqli_stmt_execute($user_query);
$result = mysqli_stmt_get_result($user_query);
$user = mysqli_fetch_assoc($result);
$user_id = $user['id'];

// Fetch the number of pending friend requests
$friend_requests_query = mysqli_prepare($conn, "SELECT COUNT(*) AS request_count FROM friend_requests WHERE receiver_id = ? AND status = 'pending'");
mysqli_stmt_bind_param($friend_requests_query, "i", $user_id);
mysqli_stmt_execute($friend_requests_query);
$friend_requests_result = mysqli_stmt_get_result($friend_requests_query);
$friend_requests = mysqli_fetch_assoc($friend_requests_result);
$request_count = $friend_requests['request_count'];

// Fetch the number of unread messages
$unread_messages_query = mysqli_prepare($conn, "SELECT COUNT(*) AS unread_count FROM messages WHERE receiver_id = ? AND is_read = 0");
mysqli_stmt_bind_param($unread_messages_query, "i", $user_id);
mysqli_stmt_execute($unread_messages_query);
$unread_messages_result = mysqli_stmt_get_result($unread_messages_query);
$unread_messages = mysqli_fetch_assoc($unread_messages_result);
$unread_count = $unread_messages['unread_count'];

// Handle adding a new workout plan
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_plan'])) {
    $plan_name = mysqli_real_escape_string($conn, $_POST['plan_name']);

    if (!empty($plan_name)) {
        $insert_query = "INSERT INTO workout_plans (user_id, name) VALUES ('$user_id', '$plan_name')";
        if (mysqli_query($conn, $insert_query)) {
            // Redirect to the same page to prevent form resubmission on refresh
            header("Location: homepage.php");
            exit();
        } else {
            echo "Error: " . mysqli_error($conn);
        }
    } else {
        echo "Please enter a workout plan name.";
    }
}

// Fetch a random workout with logs in the last 3 months
$random_workout_query = mysqli_prepare($conn, "
    SELECT w.id, w.name 
    FROM workouts w
    JOIN workout_plans wp ON w.workout_plan_id = wp.id
    JOIN workout_logs wl ON w.id = wl.workout_id
    WHERE wp.user_id = ? AND wl.date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
    GROUP BY w.id
    ORDER BY RAND()
    LIMIT 1
");
mysqli_stmt_bind_param($random_workout_query, "i", $user_id);
mysqli_stmt_execute($random_workout_query);
$random_workout_result = mysqli_stmt_get_result($random_workout_query);
$random_workout = mysqli_fetch_assoc($random_workout_result);

// Fetch progress data for the random workout (last 3 months)
$random_workout_id = $random_workout['id'] ?? null;
$random_workout_name = $random_workout['name'] ?? null;
$progress_labels = [];
$progress_data = [];
$percentage_change = 0;

if ($random_workout_id) {
    $progress_query = mysqli_prepare($conn, "
        SELECT DATE(date) AS session_date, MAX(weight) AS max_weight
        FROM workout_logs
        WHERE workout_id = ? AND user_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
        GROUP BY session_date
        ORDER BY session_date ASC
    ");
    mysqli_stmt_bind_param($progress_query, "ii", $random_workout_id, $user_id);
    mysqli_stmt_execute($progress_query);
    $progress_result = mysqli_stmt_get_result($progress_query);
    $progress_logs = mysqli_fetch_all($progress_result, MYSQLI_ASSOC);

    if (!empty($progress_logs)) {
        $first_weight = floatval($progress_logs[0]['max_weight']);
        $current_weight = floatval($progress_logs[count($progress_logs) - 1]['max_weight']);
        $percentage_change = (($current_weight - $first_weight) / $first_weight) * 100;

        foreach ($progress_logs as $log) {
            $progress_labels[] = $log['session_date'];
            $progress_data[] = floatval($log['max_weight']);
        }
    }
}

// Check if the user has any workout logs at all
$has_logs_query = mysqli_prepare($conn, "
    SELECT COUNT(*) AS log_count 
    FROM workout_logs 
    WHERE user_id = ?
");
mysqli_stmt_bind_param($has_logs_query, "i", $user_id);
mysqli_stmt_execute($has_logs_query);
$has_logs_result = mysqli_stmt_get_result($has_logs_query);
$has_logs = mysqli_fetch_assoc($has_logs_result);
$has_logs = $has_logs['log_count'] > 0;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Homepage</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Function to toggle edit mode
        function toggleEditMode() {
            const editButtons = document.querySelectorAll('.action-buttons');
            const addPlanFormButton = document.getElementById('add-plan-form-button');
            const addPlanForm = document.getElementById('add-plan-form');
            const editButton = document.getElementById('add-plan-button');

            // Toggle visibility of edit and delete buttons
            editButtons.forEach(button => {
                button.style.display = button.style.display === 'flex' ? 'none' : 'flex';
            });

            // Toggle button text and visibility
            if (editButton.textContent === 'Edit') {
                editButton.textContent = 'Cancel';
                addPlanFormButton.style.display = 'inline-block';
            } else {
                editButton.textContent = 'Edit';
                addPlanFormButton.style.display = 'none';
                addPlanForm.style.display = 'none'; // Hide the form if visible
            }
        }

        // Function to show the add workout plan form
        function showAddPlanForm() {
            document.getElementById('add-plan-form').style.display = 'block';
            document.getElementById('add-plan-form-button').style.display = 'none';
        }

        // Function to confirm deletion of a workout plan
        function confirmDelete(planId) {
            if (confirm("Are you sure you want to delete this plan? This action cannot be undone.")) {
                window.location.href = "delete_workout_plan.php?id=" + planId;
            }
        }
    </script>
    <link rel="stylesheet" href="style.css">
    <style>
        .greeting-container {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px;
            background-color: #121212;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 10;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .profile-pic img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: block;
            margin: 0;
        }

        .search-form {
            flex-grow: 1;
            display: flex;
            gap: 8px;
            max-width: 100%;
            align-items: center;
        }

        .search-form input[type="text"] {
            flex-grow: 1;
            padding: 8px;
            border: 1px solid #333;
            border-radius: 8px;
            background-color: #1e1e1e;
            color: white;
            font-size: 14px;
            height: 40px;
            box-sizing: border-box;
        }

        .search-form button {
            padding: 8px 16px;
            background-color: #1e90ff;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            height: 40px;
            box-sizing: border-box;
        }

        /* Style for the navigation links container */
        .nav-links {
            display: flex;
            gap: 16px;
            align-items: center;
            padding-right: 10px;
        }

        .nav-link {
            position: relative;
        }

        .nav-icon {
            width: auto;
            height: 24px;
            transition: opacity 0.3s ease;
        }

        .nav-icon:hover {
            opacity: 0.8;
        }

        .notification-badge {
            background-color: red;
            color: white;
            font-size: 12px;
            font-weight: bold;
            padding: 2px 6px;
            border-radius: 50%;
            position: absolute;
            top: -5px;
            right: -5px;
            min-width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            transform: translate(50%, -50%);
        }

        /* Responsive adjustments for smaller screens */
        @media (max-width: 600px) {
            .greeting-container {
                gap: 12px;
                padding: 8px;
            }

            .profile-pic img {
                width: 48px;
                height: 48px;
            }

            .nav-icon {
                height: 28px;
            }

            .nav-link {
                height: 28px;
            }

            .search-form {
                max-width: 100%;
            }

            .search-form input[type="text"] {
                padding: 6px;
                font-size: 12px;
                height: 48px;
            }

            .search-form button {
                padding: 6px 12px;
                font-size: 12px;
                height: 48px;
            }

            .notification-badge {
                font-size: 10px;
                min-width: 16px;
                height: 16px;
                right: 0;
            }
        }

        /* Spacer div to push content down */
        /* Matches the height of the greeting-container (including padding) */
        .greeting-container-spacer {
            height: 72px;

        }

        /* Responsive adjustments for smaller screens */
        @media (max-width: 600px) {
            .greeting-container-spacer {
                height: 64px;
            }
        }

        /* Progress Button Container */
        .progress-container {
            background-color: #1e1e1e;
            width: 100%;
            border-radius: 10px;
            padding: 16px;
            margin-top: 20px;
            text-align: center;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .progress-container:hover {
            transform: scale(1.02);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .progress-container h2 {
            text-align: left;
            margin-bottom: 16px;
            font-size: 1.5rem;
        }

        .progress-container h3 {
            margin-bottom: 8px;
            font-size: 1.25rem;
        }

        .progress-container .percentage {
            font-size: 1.25rem;
            font-weight: bold;
            color:
                <?php echo ($percentage_change > 0) ? '#4CAF50' : (($percentage_change < 0) ? '#FF4444' : '#888'); ?>
            ;
        }

        .progress-container canvas {
            max-height: 200px;
            margin-top: 16px;
        }
    </style>
</head>

<body>
    <div class="greeting-container">
        <!-- Profile Picture as a Link to Profile -->
        <a href="profile.php" class="profile-pic" style="height: 48px; width: 48px; margin: 0;">
            <img src="uploads/profile_pictures/<?php echo htmlspecialchars($user['profilePicture']); ?>"
                alt="Profile Picture">
        </a>
        <!-- Search Button and Form -->
        <form method="GET" action="search_result.php" class="search-form">
            <input type="text" name="search_username" placeholder="Search by username" required>
            <button class="search-button" type="submit">Search</button>
        </form>
        <!-- Friends and Messages Links -->
        <div class="nav-links" style="margin: 0;">
            <div class="nav-link">
                <a href="friends.php">
                    <img src="assets/friends.png" alt="Friends" class="nav-icon">
                </a>
                <?php if ($request_count > 0): ?>
                    <span class="notification-badge"><?php echo $request_count; ?></span>
                <?php endif; ?>
            </div>
            <div class="nav-link">
                <a href="messages.php">
                    <img src="assets/messages.png" alt="Messages" class="nav-icon">
                </a>
                <?php if ($unread_count > 0): ?>
                    <span class="notification-badge"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Spacer div to push content down -->
    <div class="greeting-container-spacer"></div>

    <!-- Workout Plans Section -->
    <div class="workout-list">
        <h2>Your Workout Plans</h2>
        <?php
        // Fetch workout plans using prepared statements
        $plans_query = mysqli_prepare($conn, "SELECT * FROM workout_plans WHERE user_id = ?");
        mysqli_stmt_bind_param($plans_query, "i", $user_id);
        mysqli_stmt_execute($plans_query);
        $plans_result = mysqli_stmt_get_result($plans_query);

        if (mysqli_num_rows($plans_result) == 0) {
            echo "<p>You currently have no workout plans. Add one below.</p>";
        } else {
            while ($plan = mysqli_fetch_assoc($plans_result)) {
                echo "<div>
                        <a class='plan-btn' href='view_workout_plan.php?id=" . $plan['id'] . "'>" . htmlspecialchars($plan['name']) . "</a>
                        <div class='action-buttons' style='display: none;'>
                            <a class='action-btn edit-btn' href='edit_workout_plan.php?id=" . $plan['id'] . "'>Edit</a>
                            <a class='action-btn del-btn' href='#' onclick='confirmDelete(" . $plan['id'] . ")'>Delete</a>
                        </div>
                      </div>";
            }
        }
        ?>
    </div>

    <!-- Edit/Cancel Button -->
    <button id="add-plan-button" onclick="toggleEditMode()">Edit</button>

    <!-- Add Another Workout Plan Button (Hidden by Default) -->
    <button id="add-plan-form-button" class="add-plan-button" style="display: none;" onclick="showAddPlanForm()">Add
        Another Workout Plan</button>

    <!-- Add Workout Plan Form (Hidden by Default) -->
    <div id="add-plan-form" style="display: none;">
        <h3>Add Workout Plan</h3>
        <form method="POST" action="homepage.php">
            <input type="text" name="plan_name" placeholder="Enter workout plan name" required>
            <br><br>
            <button type="submit" name="add_plan">Add Plan</button>
            <button type="button" onclick="toggleEditMode()">Cancel</button>
        </form>
    </div>

    <!-- Progress Button -->
    <div class="progress-container" onclick="window.location.href='progress.php'">
        <h2>View Your Progress</h2>
        <?php if ($random_workout_id): ?>
            <h3><?php echo htmlspecialchars($random_workout_name); ?></h3>
            <div class="percentage">
                <?php echo ($percentage_change > 0) ? '↑' : (($percentage_change < 0) ? '↓' : '—'); ?>
                <?php echo number_format($percentage_change, 2); ?>%
            </div>
            <canvas id="progressButtonChart"></canvas>
        <?php elseif ($has_logs): ?>
            <p class="encouragement-message">Log more workouts to see progress here!</p>
        <?php else: ?>
            <p class="encouragement-message">Log workouts to unlock the Progress Page!</p>
        <?php endif; ?>
    </div>

    <?php if ($random_workout_id): ?>
        <script>
            // Chart.js Configuration for Progress Button
            const progressButtonCtx = document.getElementById('progressButtonChart').getContext('2d');
            const progressButtonChart = new Chart(progressButtonCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($progress_labels); ?>,
                    datasets: [{
                        label: 'Weight (kg)',
                        data: <?php echo json_encode($progress_data); ?>,
                        borderColor: '#1e90ff',
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
        </script>
    <?php endif; ?>
</body>

</html>
