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

// Fetch user details
$user_query = mysqli_prepare($conn, "SELECT id, firstName, lastName, profilePicture, bio, email, dob, sex, security_question, security_answer, date_of_creation FROM users WHERE username = ?");
mysqli_stmt_bind_param($user_query, "s", $username);
mysqli_stmt_execute($user_query);
$result = mysqli_stmt_get_result($user_query);
$user = mysqli_fetch_assoc($result);

if (!$user) {
    die("User not found.");
}

$user_id = $user['id'];
$default_profile_picture = 'default_profile_picture.png';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate input
    $firstName = htmlspecialchars($_POST['firstName']);
    $lastName = htmlspecialchars($_POST['lastName']);
    $newUsername = htmlspecialchars($_POST['username']);
    $email = htmlspecialchars($_POST['email']);
    $dob = htmlspecialchars($_POST['dob']);
    $sex = htmlspecialchars($_POST['sex']);
    $bio = htmlspecialchars($_POST['bio']);
    $security_question = htmlspecialchars($_POST['security_question']);
    $security_answer = htmlspecialchars($_POST['security_answer']);

    // Check if the new username is already taken (if it's being changed)
    if ($newUsername !== $username) {
        $check_username_query = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ?");
        mysqli_stmt_bind_param($check_username_query, "s", $newUsername);
        mysqli_stmt_execute($check_username_query);
        mysqli_stmt_store_result($check_username_query);

        if (mysqli_stmt_num_rows($check_username_query) > 0) {
            $message = "Username already taken. Please choose a different username.";
        } else {
            mysqli_stmt_close($check_username_query);
        }
    }

    // Check if the new email is already taken (if it's being changed)
    if ($email !== $user['email']) {
        $check_email_query = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ?");
        mysqli_stmt_bind_param($check_email_query, "s", $email);
        mysqli_stmt_execute($check_email_query);
        mysqli_stmt_store_result($check_email_query);

        if (mysqli_stmt_num_rows($check_email_query) > 0) {
            $message = "Email already in use. Please use a different email.";
        } else {
            mysqli_stmt_close($check_email_query);
        }
    }

    // Handle profile picture update
    $filename = $user['profilePicture']; // Default to the current profile picture
    if (!empty($_POST['croppedImage'])) {
        $croppedImage = $_POST['croppedImage'];
        $croppedImage = str_replace('data:image/jpeg;base64,', '', $croppedImage);
        $croppedImage = base64_decode($croppedImage);

        // Validate the image
        if (getimagesizefromstring($croppedImage) === false) {
            $message = "Invalid image data. Please try again.";
        } else {
            // Generate a unique filename
            $filename = "profile_" . $user_id . "_" . time() . ".jpg";

            // Define the upload directory
            $upload_dir = "uploads/profile_pictures/";
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            // Save the cropped image
            if (file_put_contents($upload_dir . $filename, $croppedImage)) {
                // Delete the old profile picture if it's not the default
                if ($user['profilePicture'] !== $default_profile_picture && file_exists($upload_dir . $user['profilePicture'])) {
                    unlink($upload_dir . $user['profilePicture']);
                }
            } else {
                $message = "Failed to save the cropped image.";
            }
        }
    }

    // Update user details in the database
    if (!isset($message)) {
        $update_query = mysqli_prepare($conn, "UPDATE users SET firstName = ?, lastName = ?, username = ?, email = ?, dob = ?, sex = ?, bio = ?, security_question = ?, security_answer = ?, profilePicture = ? WHERE id = ?");
        mysqli_stmt_bind_param($update_query, "ssssssssssi", $firstName, $lastName, $newUsername, $email, $dob, $sex, $bio, $security_question, $security_answer, $filename, $user_id);
        mysqli_stmt_execute($update_query);

        if (mysqli_stmt_affected_rows($update_query) > 0) {
            $message = "Profile updated successfully!";
            // Update session username if it was changed
            if ($newUsername !== $username) {
                $_SESSION['username'] = $newUsername;
                $username = $newUsername; // Update local variable for display
            }
            // Refresh user data
            $user_query = mysqli_prepare($conn, "SELECT * FROM users WHERE username = ?");
            mysqli_stmt_bind_param($user_query, "s", $username);
            mysqli_stmt_execute($user_query);
            $result = mysqli_stmt_get_result($user_query);
            $user = mysqli_fetch_assoc($result);
        } else {
            $message = "Failed to update profile. Please try again.";
        }
    }

    // Redirect back to the profile page after updating
    header("Location: profile.php");
    exit();
}
// Format the date_of_creation to display only month and year
$date_of_creation = new DateTime($user['date_of_creation']);
$formatted_date = $date_of_creation->format('F Y'); // Example: "October 2023"

// Fetch workout stats
$user_id = $user['id'];

// Total Sets Recorded
$total_sets_query = mysqli_prepare($conn, "SELECT SUM(set_number) AS total_sets FROM workout_logs WHERE user_id = ?");
mysqli_stmt_bind_param($total_sets_query, "i", $user_id);
mysqli_stmt_execute($total_sets_query);
$total_sets_result = mysqli_stmt_get_result($total_sets_query);
$total_sets = mysqli_fetch_assoc($total_sets_result)['total_sets'];

// Total Sessions Completed (grouped by date)
$total_sessions_query = mysqli_prepare($conn, "SELECT COUNT(DISTINCT DATE(date)) AS total_sessions FROM workout_logs WHERE user_id = ?");
mysqli_stmt_bind_param($total_sessions_query, "i", $user_id);
mysqli_stmt_execute($total_sessions_query);
$total_sessions_result = mysqli_stmt_get_result($total_sessions_query);
$total_sessions = mysqli_fetch_assoc($total_sessions_result)['total_sessions'];

// Last Time in the Gym
$last_session_query = mysqli_prepare($conn, "SELECT MAX(end_time) AS last_session FROM workout_sessions WHERE user_id = ?");
mysqli_stmt_bind_param($last_session_query, "i", $user_id);
mysqli_stmt_execute($last_session_query);
$last_session_result = mysqli_stmt_get_result($last_session_query);
$last_session = mysqli_fetch_assoc($last_session_result)['last_session'];

if ($last_session) {
    $last_session_time = new DateTime($last_session);
    $current_time = new DateTime();
    $days_since_last_session = $current_time->diff($last_session_time)->days;

    // Customize the message based on the number of days
    if ($days_since_last_session === 0) {
        $last_session_message = "today";
    } elseif ($days_since_last_session === 1) {
        $last_session_message = "yesterday";
    } else {
        $last_session_message = "$days_since_last_session days ago";
    }
} else {
    $last_session_message = "No sessions recorded yet.";
}

// Fetch all workouts and their highest weight with corresponding reps
$workouts_query = mysqli_prepare($conn, "
    SELECT w.name, l.weight AS max_weight, l.reps 
    FROM workouts w 
    LEFT JOIN workout_logs l ON w.id = l.workout_id 
    WHERE w.workout_plan_id IN (SELECT id FROM workout_plans WHERE user_id = ?) 
    AND l.weight = (SELECT MAX(weight) FROM workout_logs WHERE workout_id = w.id AND user_id = ?) 
    GROUP BY w.name 
    ORDER BY w.name ASC
");
mysqli_stmt_bind_param($workouts_query, "ii", $user_id, $user_id);
mysqli_stmt_execute($workouts_query);
$workouts_result = mysqli_stmt_get_result($workouts_query);
$workouts = mysqli_fetch_all($workouts_result, MYSQLI_ASSOC);

// Handle form submission (for editing profile)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate input
    $firstName = htmlspecialchars($_POST['firstName']);
    $lastName = htmlspecialchars($_POST['lastName']);
    $newUsername = htmlspecialchars($_POST['username']); // Add username field
    $email = htmlspecialchars($_POST['email']);
    $dob = htmlspecialchars($_POST['dob']);
    $sex = htmlspecialchars($_POST['sex']);
    $bio = htmlspecialchars($_POST['bio']);
    $security_question = htmlspecialchars($_POST['security_question']);
    $security_answer = htmlspecialchars($_POST['security_answer']);

    // Check if the new username is already taken (if it's being changed)
    if ($newUsername !== $username) {
        $check_username_query = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ?");
        mysqli_stmt_bind_param($check_username_query, "s", $newUsername);
        mysqli_stmt_execute($check_username_query);
        mysqli_stmt_store_result($check_username_query);

        if (mysqli_stmt_num_rows($check_username_query) > 0) {
            $message = "Username already taken. Please choose a different username.";
        } else {
            mysqli_stmt_close($check_username_query);
        }
    }

    // Check if the new email is already taken (if it's being changed)
    if ($email !== $user['email']) {
        $check_email_query = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ?");
        mysqli_stmt_bind_param($check_email_query, "s", $email);
        mysqli_stmt_execute($check_email_query);
        mysqli_stmt_store_result($check_email_query);

        if (mysqli_stmt_num_rows($check_email_query) > 0) {
            $message = "Email already in use. Please use a different email.";
        } else {
            mysqli_stmt_close($check_email_query);
        }
    }

    if (isset($_FILES['profilePicture']) && $_FILES['profilePicture']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['profilePicture'];
    
        // Validate the file 
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 2 * 1024 * 1024; 
    
        if (!in_array($file['type'], $allowed_types)) {
            $message = "Invalid file type. Only JPEG, PNG, and GIF are allowed.";
        } else {
            // Resize and crop the image to reduce file size
            $resized_image = resizeAndCropImage($file['tmp_name'], $max_size, 300, 300);
            if (!$resized_image) {
                $message = "Failed to resize the image. Please try again with a smaller file.";
            } else {
                // Generate a unique filename
                // Save as JPEG for consistency
                $filename = "profile_" . $user_id . "_" . time() . ".jpg"; 
    
                // Define the upload directory
                $upload_dir = "uploads/profile_pictures/";
                if (!is_dir($upload_dir)) {
                    // Create the directory if it doesn't exist
                    mkdir($upload_dir, 0755, true); 
                }
    
                // Move the resized image to the upload directory
                if (rename($resized_image, $upload_dir . $filename)) {
                    // Delete the old profile picture if it's not the default
                    if ($user['profilePicture'] !== $default_profile_picture && file_exists($upload_dir . $user['profilePicture'])) {
                        unlink($upload_dir . $user['profilePicture']);
                    }
    
                    // Update the profile picture in the database
                    $update_picture_query = mysqli_prepare($conn, "UPDATE users SET profilePicture = ? WHERE id = ?");
                    mysqli_stmt_bind_param($update_picture_query, "si", $filename, $user_id);
                    mysqli_stmt_execute($update_picture_query);
    
                    if (mysqli_stmt_affected_rows($update_picture_query) > 0) {
                        $message = "Profile picture updated successfully!";
                        // Refresh user data
                        $user['profilePicture'] = $filename;
                    } else {
                        // Database update failed, delete the uploaded file
                        unlink($upload_dir . $filename);
                        $message = "Failed to update profile picture in the database.";
                    }
                } else {
                    $message = "Failed to upload the file.";
                }
            }
        }
    }

    // Update user details in the database
    if (!isset($message)) {
        $update_query = mysqli_prepare($conn, "UPDATE users SET firstName = ?, lastName = ?, username = ?, email = ?, dob = ?, sex = ?, bio = ?, security_question = ?, security_answer = ? WHERE id = ?");
        mysqli_stmt_bind_param($update_query, "sssssssssi", $firstName, $lastName, $newUsername, $email, $dob, $sex, $bio, $security_question, $security_answer, $user_id);
        mysqli_stmt_execute($update_query);

        if (mysqli_stmt_affected_rows($update_query) > 0) {
            $message = "Profile updated successfully!";
            // Update session username if it was changed
            if ($newUsername !== $username) {
                $_SESSION['username'] = $newUsername;
                // Update local variable for display
                $username = $newUsername; 
            }
            // Refresh user data
            $user_query = mysqli_prepare($conn, "SELECT * FROM users WHERE username = ?");
            mysqli_stmt_bind_param($user_query, "s", $username);
            mysqli_stmt_execute($user_query);
            $result = mysqli_stmt_get_result($user_query);
            $user = mysqli_fetch_assoc($result);
        } else {
            $message = "Failed to update profile. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Profile</title>
    <link rel="stylesheet" href="style.css">
    <!-- jQuery (required for Cropper.js) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <!-- Cropper.js CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.css" />
    <!-- Cropper.js JavaScript -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.js"></script>
    <style>

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

/* Spacer Div (Same Height as Top Section and padding) */
.spacer {
height: 72px;
}

/* Responsive adjustments for smaller screens */
@media (max-width: 600px) {

.top-section{
height: 64px;
}

.spacer {
height: 64px;
}
}

.profile-header {
display: flex;
align-items: center;
gap: 10px;
margin-bottom: 20px;
margin-left: 16px;
}

.profile-header h1 {
margin: 0;
font-size: 1.5rem;
}

.profile-header .arrow {
width: 1.5rem;
height: 1.5rem;
cursor: pointer;
}

.profile-header-container {
background-color: #1e1e1e; 
padding: 16px;
margin-bottom: 24px;
display: flex;
align-items: flex-start;
position: relative; 
}

.profile-picture {
width: 100px;
height: 100px;
border-radius: 50%;
margin-right: 16px;
}

.profile-info {
flex: 1;
text-align: left;
}

.profile-info h2 {
margin: 0;
font-size: 1.25rem;
color: #ffffff;
}

.profile-info p {
margin: 8px;
color: #cccccc;
}

.edit-profile-button {
position: absolute;
font-weight: bold;
top: 16px;
right: 16px;
background: none; 
border: none; 
color: #1e90ff; 
font-size: 0.875rem;
cursor: pointer;
padding: 0px; 
margin-bottom: 8px;
transition: color 0.3s ease; 
}

.edit-profile-button:hover {
color: #0077cc; 
text-decoration: none; 
}

/* Stats Section */
.stats-container {
background-color: #1e1e1e; 
border-radius: 8px;
padding: 16px;
margin-bottom: 24px;
text-align: left;
}

.stats-container h2 {
margin-top: 0;
font-size: 1.25rem;
color: #ffffff;
}

.stats-container p {
margin: 8px 0;
color: #cccccc;
}

/* Personal Records Section */
.personal-records-container {
background-color: #1e1e1e; 
border-radius: 8px;
padding: 16px;
margin-bottom: 24px;
text-align: left;
}

.personal-records-container h3 {
margin-top: 0;
font-size: 1.25rem;
color: #ffffff;
}

.personal-records-container h4 {
margin: 16px 0 8px;
font-size: 1.1rem;
color: #ffffff;
}

.personal-records-container ul {
list-style: none;
padding: 0;
margin: 0;
}

.personal-records-container li {
margin: 8px 0;
color: #cccccc;
}

/* Log Out Button */
.logout-container {
text-align: center;
margin-top: 24px;
}

.logout-btn {
padding: 12px 24px;
background-color: #ff4444;
color: #ffffff;
border: none;
border-radius: 8px;
font-size: 1rem;
cursor: pointer;
text-decoration: none;
}

.logout-btn:hover {
background-color: #cc0000; 
}

/* Edit Form */
#edit-form {
background-color: #1e1e1e;
border-radius: 8px; 
padding: 16px; 
margin-bottom: 24px; 
text-align: left; 
}

#edit-form h2 {
margin-top: 0;
font-size: 1.25rem;
color: #ffffff; 
}

#edit-form label {
display: block;
margin: 16px 0 8px;
font-size: 0.875rem;
color: #cccccc; 
}

#edit-form input[type="text"],
#edit-form input[type="email"],
#edit-form input[type="date"],
#edit-form select,
#edit-form textarea {
width: 100%;
padding: 8px;
border: 1px solid #333; 
border-radius: 8px; 
background-color: #1e1e1e;
color: #ffffff; 
font-size: 0.875rem;
box-sizing: border-box; 
}

#edit-form textarea {
height: 100px; 
resize: vertical; 
}

.update-profile-button {
width: 100%;
padding: 16px;
background-color: #1e90ff;
color: #ffffff;
border: none;
border-radius: 8px;
font-size: 1rem;
cursor: pointer;
margin-top: 24px;
margin-bottom: 16px;
transition: background-color 0.3s ease;
}
.update-profile-button:hover {
background-color: #0077cc;
}

/* Profile Picture Container in Edit Form */
#edit-form label[for="profilePicture"] {
display: flex;
justify-content: center; 
align-items: center; 
position: relative; 
margin-bottom: 16px; 
}

/* Profile Picture Container in Edit Form */
#edit-form label[for="profilePicture"] {
display: flex;
justify-content: center; 
align-items: center; 
position: relative;
margin-bottom: 16px; 
}

/* Grey Out Effect and Edit Text Overlay */
#edit-form label[for="profilePicture"]::after {
content: "Edit";
position: absolute;
top: 50%;
left: 50%;
transform: translate(-50%, -50%);
color: white; 
font-size: 1rem;
font-weight: bold;
background-color: rgba(0, 0, 0, 0.5); 
padding: 8px 16px;
border-radius: 8px;
opacity: 1; 
}

/* Grey Out Effect */
#edit-form label[for="profilePicture"] img {
filter: brightness(0.7); 
}
</style>
</head>
<body>
    <!-- Profile Header -->
    <div class="top-section" style="width: 100%;">
        <a href="#"><img class="arrow" src="assets/arrow.png" alt="back arrow" style="height: 40px; width: 40px;"></a>
        <h1>@<?php echo htmlspecialchars($username); ?></h1>
    </div>
    <div class="spacer"></div>


    <!-- Profile Information -->
    <div id="profile-info" class="profile-content" style="width: 100%;">
        <!-- Profile Header Container -->
        <div class="profile-header-container">
            <!-- Profile Picture -->
            <img src="uploads/profile_pictures/<?php echo $user['profilePicture']; ?>" alt="Profile Picture" class="profile-picture">
            <!-- Profile Info -->
            <div class="profile-info">
                <?php if (!empty($user['bio'])): ?>
                    <p><?php echo htmlentities($user['bio'], ENT_QUOTES, 'UTF-8', false); ?></p>
                <?php endif; ?>
            </div>
            <!-- Edit Button -->
            <button class="edit-profile-button" onclick="toggleEditForm()">Edit</button>
        </div>

        <!-- Stats Section -->
        <div class="stats-container">
            <h2>Stats</h2>
            <p><strong>Last Time in the Gym:</strong> <?php echo $last_session_message; ?></p>
            <p><strong>Total Sets Recorded:</strong> <?php echo $total_sets ?? 0; ?></p>
            <p><strong>Total Sessions Completed:</strong> <?php echo $total_sessions ?? 0; ?></p>
        </div>
        <!-- Personal Records Section -->
        <div class="personal-records-container">
            <h3>Personal Records</h3>
            <?php
            // Fetch all workout plans and their associated workouts
            $workout_plans_query = mysqli_prepare($conn, "
                SELECT DISTINCT wp.id AS plan_id, wp.name AS plan_name, w.id AS workout_id, w.name AS workout_name, l.weight AS max_weight, l.reps 
                FROM workout_plans wp
                LEFT JOIN workouts w ON wp.id = w.workout_plan_id
                LEFT JOIN workout_logs l ON w.id = l.workout_id 
                WHERE wp.user_id = ? 
                AND l.weight = (SELECT MAX(weight) FROM workout_logs WHERE workout_id = w.id AND user_id = ?)
                AND l.reps = (SELECT MAX(reps) FROM workout_logs WHERE workout_id = w.id AND user_id = ? AND weight = l.weight)
                ORDER BY wp.name ASC, w.name ASC
            ");
            mysqli_stmt_bind_param($workout_plans_query, "iii", $user_id, $user_id, $user_id);
            mysqli_stmt_execute($workout_plans_query);
            $workout_plans_result = mysqli_stmt_get_result($workout_plans_query);
            $workout_plans = mysqli_fetch_all($workout_plans_result, MYSQLI_ASSOC);

            // Group workouts by their plans
            $grouped_workouts = [];
            foreach ($workout_plans as $workout) {
                $plan_name = $workout['plan_name'];
                if (!isset($grouped_workouts[$plan_name])) {
                    $grouped_workouts[$plan_name] = [];
                }
                $grouped_workouts[$plan_name][] = $workout;
            }

            // Display grouped workouts
            if (!empty($grouped_workouts)):
                foreach ($grouped_workouts as $plan_name => $workouts):
                    ?>
                    <h4><?php echo htmlspecialchars($plan_name); ?></h4>
                    <ul>
                        <?php foreach ($workouts as $workout): ?>
                            <li>
                                <strong><?php echo htmlspecialchars($workout['workout_name']); ?>:</strong>
                                Weight: <?php echo $workout['max_weight'] ?? 'N/A'; ?> kg,
                                Reps: <?php echo $workout['reps'] ?? 'N/A'; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endforeach;
            else: ?>
                <p>No workout data available.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Edit Form (Hidden by Default) -->
    <div id="edit-form" style="display: none; width: 100%;" class="profile-content">
        <form method="POST" action="profile.php" enctype="multipart/form-data">
            <!-- Profile Picture Upload -->
            <label for="profilePicture" style="cursor: pointer; position: relative;">
                <img id="image-preview" src="uploads/profile_pictures/<?php echo $user['profilePicture']; ?>" alt="Profile Picture" style="width: 100px; height: 100px; border-radius: 50%;">
                <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: white; font-weight: bold; background: rgba(0, 0, 0, 0.5); padding: 5px; border-radius: 5px;">Edit</div>
            </label>
            <input type="file" id="profilePicture" name="profilePicture" accept="image/*" style="display: none;">

            <!-- Cropper.js Modal -->
            <div id="cropper-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.8); z-index: 1000;">
                <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #fff; padding: 20px; border-radius: 8px;">
                    <div style="width: 300px; height: 300px;">
                        <img id="cropper-image" src="" alt="Cropper Image" style="max-width: 100%; max-height: 100%;">
                    </div>
                    <button id="crop-button" style="margin-top: 10px; padding: 8px 16px; background: #1e90ff; color: #fff; border: none; border-radius: 4px; cursor: pointer;">Crop</button>
                    <button id="cancel-button" style="margin-top: 10px; padding: 8px 16px; background: #ff4444; color: #fff; border: none; border-radius: 4px; cursor: pointer;">Cancel</button>
                </div>
            </div>

        <!-- Bio Field -->
        <label for="bio">Bio:</label>
        <textarea id="bio" name="bio"
            placeholder="Tell us about yourself..."><?php echo $user['bio'] ?? ''; ?></textarea>

        <!-- Personal Information -->
        <label for="firstName">First Name:</label>
        <input type="text" id="firstName" name="firstName"
            value="<?php echo htmlspecialchars($user['firstName']); ?>" required>

        <label for="lastName">Last Name:</label>
        <input type="text" id="lastName" name="lastName" value="<?php echo htmlspecialchars($user['lastName']); ?>"
            required>

        <label for="username">Username:</label>
        <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>"
            required>

        <label for="email">Email:</label>
        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>"
            required>

        <label for="dob">Date of Birth:</label>
        <input type="date" id="dob" name="dob" value="<?php echo htmlspecialchars($user['dob']); ?>" required>

        <label for="sex">Gender:</label>
        <select id="sex" name="sex" required>
            <option value="Male" <?php echo ($user['sex'] === 'Male') ? 'selected' : ''; ?>>Male</option>
            <option value="Female" <?php echo ($user['sex'] === 'Female') ? 'selected' : ''; ?>>Female</option>
        </select>


        <!-- Security Question and Answer -->
        <label for="security_question">Security Question:</label>
        <select id="security_question" name="security_question" required>
            <option value="What is your mother's maiden name?" <?php echo ($user['security_question'] === "What is your mother's maiden name?") ? 'selected' : ''; ?>>What is your mother's maiden name?</option>
            <option value="What was the name of your first pet?" <?php echo ($user['security_question'] === "What was the name of your first pet?") ? 'selected' : ''; ?>>What was the name of your first pet?</option>
            <option value="What city were you born in?" <?php echo ($user['security_question'] === "What city were you born in?") ? 'selected' : ''; ?>>What city were you born in?</option>
            <option value="What is the name of your favorite teacher?" <?php echo ($user['security_question'] === "What is the name of your favorite teacher?") ? 'selected' : ''; ?>>
                What is the name of your favorite teacher?</option>
        </select>

        <label for="security_answer">Security Answer:</label>
        <input type="text" id="security_answer" name="security_answer"
            value="<?php echo htmlspecialchars($user['security_answer']); ?>" required>

            <!-- Update Profile Button -->
            <button class="update-profile-button" type="submit">Update Profile</button>
        </form>
    </div>

    <!-- Log Out Button -->
    <div class="logout-container" id="logout-container">
        <a class="logout-btn" href="logout.php">Log Out</a>
    </div>

    <script>
// Function to handle back arrow click
function handleBackArrowClick() {
    const editForm = document.getElementById('edit-form');

    if (editForm.style.display === 'block') {
        // If edit form is visible, go back to profile.php
        window.location.href = "profile.php";
    } else {
        // If edit form is not visible, go back to homepage.php
        window.location.href = "homepage.php";
    }
}

// Add event listener to the back arrow
document.querySelector('.top-section .arrow').addEventListener('click', function (event) {
    // Prevent default link behavior
    event.preventDefault(); 
    handleBackArrowClick();
});

        // JavaScript for Cropper.js and form handling
        let cropper;

// Toggle edit form
function toggleEditForm() {
    const profileInfo = document.getElementById('profile-info');
    const editForm = document.getElementById('edit-form');
    const logoutContainer = document.getElementById('logout-container');
    const profileHeader = document.querySelector('.top-section h1');

    if (profileInfo.style.display === 'none') {
        profileInfo.style.display = 'block';
        editForm.style.display = 'none';
        logoutContainer.style.display = 'block';
        profileHeader.textContent = "Profile";
    } else {
        profileInfo.style.display = 'none';
        editForm.style.display = 'block';
        logoutContainer.style.display = 'none';
        profileHeader.textContent = "Edit Profile";
    }
}

// Handle file input change
document.getElementById('profilePicture').addEventListener('change', function (event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function (e) {
            // Show the cropper modal
            document.getElementById('cropper-modal').style.display = 'block';

            // Initialize Cropper.js
            const image = document.getElementById('cropper-image');
            image.src = e.target.result;

            if (cropper) {
                cropper.destroy();
            }

            cropper = new Cropper(image, {
                // Square crop
                aspectRatio: 1, 
                viewMode: 1,
                autoCropArea: 1,
                responsive: true,
            });
        };
        reader.readAsDataURL(file);
    }
});

// Handle crop button click
document.getElementById('crop-button').addEventListener('click', function () {
    if (cropper) {
        const croppedCanvas = cropper.getCroppedCanvas({ width: 300, height: 300 });
        // Adjust quality
        const croppedImage = croppedCanvas.toDataURL('image/jpeg', 0.85); 

        // Update the preview image
        document.getElementById('image-preview').src = croppedImage;

        // Hide the cropper modal
        document.getElementById('cropper-modal').style.display = 'none';

        // Store the cropped image data URL in a hidden input for form submission
        const croppedImageInput = document.createElement('input');
        croppedImageInput.type = 'hidden';
        croppedImageInput.name = 'croppedImage';
        croppedImageInput.value = croppedImage;
        document.querySelector('form').appendChild(croppedImageInput);
    }
});

// Handle cancel button click
document.getElementById('cancel-button').addEventListener('click', function () {
    document.getElementById('cropper-modal').style.display = 'none';
    document.getElementById('profilePicture').value = '';
});

// Handle form submission
document.querySelector('form').addEventListener('submit', function (event) {
    event.preventDefault(); 

    // Submit the form via AJAX
    const formData = new FormData(this);
    fetch('profile.php', {
        method: 'POST',
        body: formData,
    })
    .then(response => {
        if (response.redirected) {
            // Redirect to the profile page
            window.location.href = response.url; 
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
});
    </script>
</body>
</html>
