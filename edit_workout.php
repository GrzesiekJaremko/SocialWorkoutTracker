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

$workout_id = intval($_GET['id']);
$username = $_SESSION['username'];

// Fetch user ID
$user_query = mysqli_query($conn, "SELECT id FROM users WHERE username='$username'");
$user = mysqli_fetch_assoc($user_query);
$user_id = $user['id'];

// Fetch workout details
$workout_query = mysqli_query($conn, "SELECT * FROM workouts WHERE id='$workout_id'");
$workout = mysqli_fetch_assoc($workout_query);

if (!$workout) {
    echo "Workout not found.";
    exit();
}

// Ensure the user owns the workout
$plan_query = mysqli_query($conn, "SELECT user_id FROM workout_plans WHERE id='{$workout['workout_plan_id']}'");
$plan = mysqli_fetch_assoc($plan_query);

if ($plan['user_id'] != $user_id) {
    echo "Unauthorized access.";
    exit();
}

// Handle update
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_name = mysqli_real_escape_string($conn, $_POST['workout_name']);
    $new_description = mysqli_real_escape_string($conn, $_POST['description']);

    if (!empty($new_name)) {
        $update_query = "UPDATE workouts SET name='$new_name', description='$new_description' WHERE id='$workout_id'";
        if (mysqli_query($conn, $update_query)) {
            header("Location: view_workout_plan.php?id={$workout['workout_plan_id']}");
            exit();
        } else {
            echo "Error updating workout: " . mysqli_error($conn);
        }
    } else {
        echo "Workout name cannot be empty.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Edit Workout</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            padding: 16px;
            margin: 0; 
            background-color: #121212; 
            color: #ffffff;
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

        .container {
            padding: 0px; 
            max-width: 100%; 
            margin: 0 auto;
        }

        .editform {
            gap: 16px; 
            align-items: center;
        }

        .editform input[type="text"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #333333; 
            border-radius: 8px; 
            background-color: #1e1e1e; 
            color: #ffffff; 
            font-size: 1rem;
            outline: none; 
        }

        .editform input[type="text"]:focus {
            border-color: #1e90ff; 
        }
        
        .editform textarea{
            width: 100%;
            margin-top: 16px;
            margin-bottom: 16px;
            padding: 8px;
            color: #ffffff;
            background-color: #121212;
        }

        .editform button {
            width: 100%;
            padding: 12px 24px;
            background-color: #1e90ff; 
            color: #ffffff; 
            border: none;
            border-radius: 8px; 
            font-size: 1rem;
            cursor: pointer;
            transition: background-color 0.3s ease; 
        }

        .editform button:hover {
            background-color: #0077cc; 
        }
    </style>
</head>
<body>
<div class="top-section">
        <a href="view_workout_plan.php?id=<?php echo $workout['workout_plan_id']; ?>"><img class="arrow" src="assets/arrow.png" alt="back arrow" style="height: 40px; width: 40px;"></a>
        <h1 class="heading1">Edit Workout Plan</h1>
    </div>
    <!-- Spacer Div (Same Height as Top Section) -->
    <div class="spacer"></div>
    <div class="container">
        <form class="editform" method="POST">
            <input type="text" name="workout_name" value="<?php echo htmlspecialchars($workout['name']); ?>" required>
            <textarea placeholder="Workout description" name="description"><?php echo htmlspecialchars($workout['description']); ?></textarea>
            <button type="submit">Update Workout</button>
        </form>
    </div>
    </div>
</body>
</html>
