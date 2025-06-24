<?php
include 'connect.php';

// Handle Sign-Up
if (isset($_POST['signUp'])) {
    $email = $_POST['email'];
    $firstName = $_POST['fName'];
    $lastName = $_POST['lName'];
    $username = $_POST['username'];
    $dob = $_POST['dob'];
    $sex = $_POST['sex'];
    // Hash the password
    $password = md5($_POST['password']); 
    $securityQuestion = $_POST['security_question'];
    $securityAnswer = $_POST['security_answer'];

    // Check if email already exists
    $checkEmail = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $checkEmail->bind_param("s", $email);
    $checkEmail->execute();
    $checkEmail->store_result();

    if ($checkEmail->num_rows > 0) {
        echo "Email Address Already Exists!";
    } else {
        // Check if username is already taken
        $checkUsername = $conn->prepare("SELECT * FROM users WHERE username = ?");
        $checkUsername->bind_param("s", $username);
        $checkUsername->execute();
        $checkUsername->store_result();

        if ($checkUsername->num_rows > 0) {
            echo "Username is taken!";
        } else {
            // Insert new user into the database using prepared statements
            $insertQuery = $conn->prepare("INSERT INTO users (email, firstName, lastName, username, dob, sex, password, security_question, security_answer) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $insertQuery->bind_param("sssssssss", $email, $firstName, $lastName, $username, $dob, $sex, $password, $securityQuestion, $securityAnswer);

            if ($insertQuery->execute()) {
                header("Location: index.php");
                exit();
            } else {
                echo "Error: " . $conn->error;
            }
        }
    }
}

// Handle Sign-In
if (isset($_POST['signIn'])) {
    $username = $_POST['username'];
    $password = md5($_POST['password']); 
    // Check if "Remember Me" is selected
    $remember_me = isset($_POST['remember_me']); 

    // Check if user exists
    $sql = $conn->prepare("SELECT * FROM users WHERE username = ? AND password = ?");
    $sql->bind_param("ss", $username, $password);
    $sql->execute();
    $result = $sql->get_result();

    if ($result->num_rows > 0) {
        session_start();
        $row = $result->fetch_assoc();
        $_SESSION['username'] = $row['username'];

        // Handle "Remember Me" functionality
        if ($remember_me) {
            // Generate a secure token
            $token = bin2hex(random_bytes(32)); 

            // Store the token in the database
            $update_token_query = $conn->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
            $update_token_query->bind_param("si", $token, $row['id']);
            $update_token_query->execute();

            // Set a cookie with the token (expires in 30 days)
            setcookie('remember_me', $token, time() + (86400 * 30), "/", "", true, true);
        }

        echo "success";
    } else {
        echo "Incorrect Username or Password"; 
    }
    exit();
}

// Handle "Remember Me" Token Validation on Page Load
if (!isset($_SESSION['username']) && isset($_COOKIE['remember_me'])) {
    $token = $_COOKIE['remember_me'];

    // Fetch user by token
    $user_query = $conn->prepare("SELECT id, username FROM users WHERE remember_token = ?");
    $user_query->bind_param("s", $token);
    $user_query->execute();
    $result = $user_query->get_result();
    $user = $result->fetch_assoc();

    if ($user) {
        // Log the user in
        session_start();
        $_SESSION['username'] = $user['username'];
        header("Location: homepage.php"); // Redirect to homepage
        exit();
    } else {
        // Invalid token, delete the cookie
        setcookie('remember_me', '', time() - 3600, "/");
    }
}
?>
