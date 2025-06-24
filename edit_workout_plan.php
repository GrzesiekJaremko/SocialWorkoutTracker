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
$query = mysqli_query($conn, "SELECT * FROM workout_plans WHERE id='$plan_id'");
$plan = mysqli_fetch_assoc($query);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_name = mysqli_real_escape_string($conn, $_POST['plan_name']);

    if (!empty($new_name)) {
        $update_query = "UPDATE workout_plans SET name='$new_name' WHERE id='$plan_id'";
        if (mysqli_query($conn, $update_query)) {
            header("Location: homepage.php");
            exit();
        } else {
            echo "Error updating plan: " . mysqli_error($conn);
        }
    } else {
        echo "Please enter a valid workout plan name.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Edit Workout Plan</title>
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
            display: flex;
            flex-direction: column;
            gap: 16px; 
            align-items: center;
        }

        .editform input[type="text"] {
            width: 100%;
            max-width: 400px; 
            padding: 12px;
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
        <a href="homepage.php"><img class="arrow" src="assets/arrow.png" alt="back arrow" style="height: 40px; width: 40px;"></a>
        <h1 class="heading1">Edit Workout Plan</h1>
    </div>
    <!-- Spacer Div (Same Height as Top Section) -->
    <div class="spacer"></div>
    <div class="container">
        <form class="editform" method="POST">
            <input type="text" name="plan_name" value="<?php echo htmlspecialchars($plan['name']); ?>" required>
            <button type="submit">Update Plan</button>
        </form>
    </div>
</body>

</html>
