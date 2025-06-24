<?php
include 'connect.php';

if (isset($_POST['verifyDetails'])) {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $dob = $_POST['dob'];
    $securityQuestion = $_POST['security_question'];
    $securityAnswer = $_POST['security_answer'];

    // Verify user details
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND email = ? AND dob = ? AND security_question = ? AND security_answer = ?");
    $stmt->bind_param("sssss", $username, $email, $dob, $securityQuestion, $securityAnswer);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Redirect to password reset page
        header("Location: reset_password.php?username=" . urlencode($username));
        exit();
    } else {
        echo "Invalid details. Please try again.";
    }
}
?>
