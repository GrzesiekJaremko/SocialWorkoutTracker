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

if (!isset($_GET['id'])) {
    header("Location: homepage.php");
    exit();
}

$plan_id = intval($_GET['id']);
$username = $_SESSION['username'];

// Fetch user ID
$user_query = mysqli_query($conn, "SELECT id FROM users WHERE username='$username'");
$user = mysqli_fetch_assoc($user_query);
$user_id = $user['id'];

// Fetch workout plan details
$plan_query = mysqli_query($conn, "SELECT * FROM workout_plans WHERE id='$plan_id' AND user_id='$user_id'");
$plan = mysqli_fetch_assoc($plan_query);

if (!$plan) {
    echo "Workout plan not found.";
    exit();
}

// Add workout functionality
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['workout_name'])) {
    $workout_name = mysqli_real_escape_string($conn, $_POST['workout_name']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);

    $insert_workout_query = "INSERT INTO workouts (workout_plan_id, name, description) VALUES ('$plan_id', '$workout_name', '$description')";
    if (mysqli_query($conn, $insert_workout_query)) {
        header("Location: view_workout_plan.php?id=$plan_id");
        exit();
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}

// Fetch workouts for this plan
$workouts_query = mysqli_query($conn, "SELECT * FROM workouts WHERE workout_plan_id='$plan_id'");
$workouts = mysqli_fetch_all($workouts_query, MYSQLI_ASSOC);
$has_workouts = !empty($workouts);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>View Workout Plan</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            padding: 16px;
            margin: 0;
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

        .action-buttons {
            display: none;
            gap: 10px;
            margin-top: 10px;
        }

        .action-btn {
            padding: 5px 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            color: #fff;
        }

        .edit-btn {
            background-color: #4CAF50;
        }

        .del-btn {
            background-color: #f44336;
        }

        p {
            padding-top: 20px;
        }

        .container {
            padding: 0px;
            max-width: 100%;
            margin: 0 auto;
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

        #add-workout-form {
            width: 100%;
            background-color: #1e1e1e;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            display: none;
        }

        #add-workout-form input[type="text"],
        #add-workout-form textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #333333;
            border-radius: 8px;
            background-color: #1e1e1e;
            color: #ffffff;
            font-size: 1rem;
            margin-bottom: 16px;
        }

        #add-workout-form button {
            padding: 8px 16px;
            background-color: #1e90ff;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
        }

        .add-plan-button {
            width: 100%;
            padding: 16px;
            background-color: #1e90ff;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            cursor: pointer;
            margin-bottom: 16px;
        }

        .add-plan-button:hover {
            background-color: #0077cc;
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

        .greybtn {
            background-color: #6c757d;
            width: 100%;
            padding: 10px;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            margin-top: 10px;
        }

        .greybtn:hover {
            background-color: rgb(76, 83, 88);
        }
    </style>
    <script>
        // Function to toggle the visibility of the add workout form and action buttons
        function toggleEditWorkoutForm() {
            const form = document.getElementById('add-workout-form');
            const button = document.getElementById('toggle-button');
            const actionButtons = document.querySelectorAll('.action-buttons');

            // Toggle visibility of the add workout form
            if (form.style.display === 'none' || form.style.display === '') {
                form.style.display = 'block';
                button.textContent = 'Cancel';
            } else {
                form.style.display = 'none';
                button.textContent = 'Edit';
            }

            // Toggle visibility of action buttons (Edit and Delete)
            actionButtons.forEach(buttons => {
                buttons.style.display = buttons.style.display === 'flex' ? 'none' : 'flex';
            });
        }

        // Function to confirm deletion of a workout
        function confirmDelete(workoutId) {
            if (confirm("Are you sure you want to delete this workout? This action cannot be undone.")) {
                window.location.href = "delete_workout.php?id=" + workoutId;
            }
        }
    </script>
</head>

<body>
    <!-- Fixed Top Section -->
    <div class="top-section">
        <a href="homepage.php" style="height:40px;"><img class="arrow" src="assets/arrow.png" alt="back arrow"
                style="height: 40px; width: auto;"></a>
        <h1 style="height:50px;"><?php echo htmlspecialchars($plan['name']); ?></h1>
    </div>
    <!-- Spacer Div (Same Height as Top Section) -->
    <div class="spacer"></div>

    <!-- Main Content -->
    <div class="container">
        <!-- Workouts in this Plan -->
        <div class="workout-list">
            <h2>Workouts in this Plan</h2>
            <?php
            if (!$has_workouts) {
                echo "<p>No workouts added yet. Add one below.</p>";
            } else {
                foreach ($workouts as $workout) {
                    echo "<div>
                            <a class='plan-btn' onclick=\"window.location.href='add_workout_log.php?workout_id=" . $workout['id'] . "'\">" . htmlspecialchars($workout['name']) . "</a>
                            <div class='action-buttons'>
                           <a class='action-btn edit-btn' href='edit_workout.php?id=" . $workout['id'] . "'>Edit</a>
                                <a class='action-btn del-btn' href='#' onclick='confirmDelete(" . $workout['id'] . ")'>Delete</a>
                            </div>
                          </div>";
                }
            }
            ?>
        </div>

        <!-- Add a New Workout / Cancel Button -->
        <?php if ($has_workouts): ?>
            <button id="toggle-button" class="add-plan-button" onclick="toggleEditWorkoutForm()">Edit</button>
        <?php endif; ?>

        <!-- Add a New Workout Form -->
        <div id="add-workout-form" style="display: <?php echo $has_workouts ? 'none' : 'block'; ?>;">
            <h3>Add a New Workout</h3>
            <form method="POST">
                <input type="text" name="workout_name" placeholder="Workout Name" required>
                <textarea name="description" placeholder="Workout Description"></textarea>
                <button type="submit" name="add_workout">Add Workout</button>
                <button type="button" style="background-color: #6c757d;"
                    onclick="toggleEditWorkoutForm()">Cancel</button>
            </form>
        </div>
    </div>
</body>

</html>
