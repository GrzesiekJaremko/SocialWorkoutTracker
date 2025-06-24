<?php
include 'connect.php';

if (isset($_POST['resetPassword'])) {
    $username = $_POST['username'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];

    // Validate password match
    if ($newPassword !== $confirmPassword) {
        echo "Passwords do not match.";
        exit();
    }

    // Validate password criteria
    $passwordRegex = '/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d).{8,}$/';
    if (!preg_match($passwordRegex, $newPassword)) {
        echo "Password must be at least 8 characters, contain one uppercase letter, and include both letters and numbers.";
        exit();
    }

    // Hash the new password
    $hashedPassword = md5($newPassword);

    // Update the user's password
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE username = ?");
    $stmt->bind_param("ss", $hashedPassword, $username);

    if ($stmt->execute()) {
        echo "success"; 
        exit();
    } else {
        echo "Error: " . $conn->error;
        exit();
    }
}

$username = $_GET['username'] ?? null;
if (!$username) {
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="style.css">
    <style>

        body {
            font-family: Arial, sans-serif;
            background-color: #1e1e1e; 
            color: #ffffff; 
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .container {
            background-color: #2d2d2d; 
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 400px;
            margin: 20px;
        }

        h1 {
            text-align: center;
            color: #ffffff; 
            margin-bottom: 20px;
        }

        .input-group {
            margin-bottom: 15px;
            position: relative;
        }

        .input-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #444; 
            border-radius: 5px;
            font-size: 16px;
            background-color: #3d3d3d; 
            color: #ffffff; 
        }

        .input-group input::placeholder {
            color: #bbb; 
        }

        .btn {
            width: 100%;
            padding: 10px;
            background-color: #007bff; 
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            margin-top: 10px;
        }

        .btn:hover {
            background-color: #0056b3; 
        }

        #errorMessage {
            text-align: center;
            margin: 10px 0;
            font-size: 14px;
            color: #ff4444; 
        }

        /* Responsive Design */
        @media (max-width: 480px) {
            .container {
                padding: 15px;
            }

            h1 {
                font-size: 24px;
            }

            .input-group input {
                font-size: 14px;
            }

            .btn {
                font-size: 14px;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>Reset Password</h1>
        <div id="errorMessage" style="display: none;"></div>
        <form id="resetPasswordForm">
            <input type="hidden" name="username" value="<?php echo htmlspecialchars($username); ?>">
            <div class="input-group">
                <input type="password" name="new_password" id="new_password" placeholder="New Password" required>
            </div>
            <div class="input-group">
                <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm Password" required>
            </div>
            <input type="submit" class="btn" value="Reset Password" name="resetPassword">
        </form>
    </div>

    <script>
        $(document).ready(function () {
            // AJAX form submission for Reset Password
            $("#resetPasswordForm").on("submit", function (event) {
                event.preventDefault(); 

                const username = $("input[name='username']").val();
                const newPassword = $("#new_password").val();
                const confirmPassword = $("#confirm_password").val();

                $.ajax({
                    url: "reset_password.php",
                    type: "POST",
                    data: {
                        resetPassword: true,
                        username: username,
                        new_password: newPassword,
                        confirm_password: confirmPassword
                    },
                    success: function (response) {
                        if (response === "success") {
                            // Redirect to login page on success
                            window.location.href = "index.php"; 
                        } else {
                            // Show error message
                            $("#errorMessage").text(response).show(); 
                        }
                    },
                    error: function (xhr, status, error) {
                        $("#errorMessage").text("An error occurred. Please try again.").show();
                    }
                });
            });
        });
    </script>
</body>

</html>
