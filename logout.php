<?php
include 'connect.php';
session_start();
session_unset();
session_destroy();

// Clear the "Remember Me" cookie
if (isset($_COOKIE['remember_me'])) {
    // Delete the cookie
    setcookie('remember_me', '', time() - 3600, "/");

    // Clear the token from the database
    $token = $_COOKIE['remember_me'];
    $clear_token_query = $conn->prepare("UPDATE users SET remember_token = NULL WHERE remember_token = ?");
    $clear_token_query->bind_param("s", $token);
    $clear_token_query->execute();
}

// Redirect to the login page
header("Location: index.php");
exit();
?>
