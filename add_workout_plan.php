<?php
session_start();
include("connect.php");

if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

$username = $_SESSION['username'];

// Fetch user ID
$user_query = mysqli_query($conn, "SELECT id FROM users WHERE username='$username'");
$user = mysqli_fetch_assoc($user_query);
$user_id = $user['id'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $plan_name = mysqli_real_escape_string($conn, $_POST['plan_name']);

    if (!empty($plan_name)) {
        $insert_query = "INSERT INTO workout_plans (user_id, name) VALUES ('$user_id', '$plan_name')";
        if (mysqli_query($conn, $insert_query)) {
            header("Location: homepage.php");
            exit();
        } else {
            echo "Error: " . mysqli_error($conn);
        }
    } else {
        echo "Please enter a workout plan name.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Add Workout Plan</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <div>
        <div class="top">
            <a href="homepage.php"><img class="arrow" src="assets/arrow.png" alt="back arrow"></a>
            <h2>Add a New Workout Plan</h2>
             <!-- Faint line added under greeting -->
            <div class="divider"></div>
        </div>
        <form method="POST">
            <input type="text" name="plan_name" placeholder="Enter workout plan name" required>
            <br><br>
            <button type="submit">Add Plan</button>
        </form>
    </div>
</body>

</html>
