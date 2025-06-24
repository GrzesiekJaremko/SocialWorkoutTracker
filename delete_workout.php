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

// Fetch workout details to get the workout_plan_id
$workout_query = mysqli_query($conn, "SELECT workout_plan_id FROM workouts WHERE id='$workout_id'");
$workout = mysqli_fetch_assoc($workout_query);

if (!$workout) {
    echo "Workout not found.";
    exit();
}

$plan_id = $workout['workout_plan_id'];

// Verify the workout belongs to the user
$plan_query = mysqli_query($conn, "SELECT id FROM workout_plans WHERE id='$plan_id' AND user_id='$user_id'");
if (mysqli_num_rows($plan_query) == 0) {
    echo "You do not have permission to delete this workout.";
    exit();
}

// Fetch all session IDs related to the workout
$session_ids = [];
$sessions_query = mysqli_query($conn, "SELECT id FROM workout_sessions WHERE workout_id='$workout_id'");
while ($session = mysqli_fetch_assoc($sessions_query)) {
    $session_ids[] = $session['id'];
}

// Delete related records in workout_logs
if (!empty($session_ids)) {
    $session_ids_str = implode(",", $session_ids);
    $delete_logs_query = "DELETE FROM workout_logs WHERE session_id IN ($session_ids_str)";
    if (!mysqli_query($conn, $delete_logs_query)) {
        echo "Error deleting related logs: " . mysqli_error($conn);
        exit();
    }
}

// Delete related records in workout_sessions
$delete_sessions_query = "DELETE FROM workout_sessions WHERE workout_id='$workout_id'";
if (!mysqli_query($conn, $delete_sessions_query)) {
    echo "Error deleting related sessions: " . mysqli_error($conn);
    exit();
}

// Delete the workout
$delete_workout_query = "DELETE FROM workouts WHERE id='$workout_id'";
if (mysqli_query($conn, $delete_workout_query)) {
    // Redirect back to the current workout plan page
    header("Location: view_workout_plan.php?id=$plan_id");
    exit();
} else {
    echo "Error deleting workout: " . mysqli_error($conn);
}
?>
