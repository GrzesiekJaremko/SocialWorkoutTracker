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

// Fetch current user's details
$user_query = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ?");
mysqli_stmt_bind_param($user_query, "s", $username);
mysqli_stmt_execute($user_query);
$result = mysqli_stmt_get_result($user_query);
$user = mysqli_fetch_assoc($result);

if (!$user) {
    die("User not found.");
}

$user_id = $user['id'];

// Get the search term from the query string
$search_term = isset($_GET['search_username']) ? trim($_GET['search_username']) : '';

// Check if the form has been submitted
$form_submitted = isset($_GET['search_username']);

// If the form is submitted and the search term is empty, show an error message
if ($form_submitted && empty($search_term)) {
    $error_message = "Please enter a search term.";
} elseif ($form_submitted && !empty($search_term)) {
    // Split the search term into individual words
    $search_words = explode(' ', $search_term);

    // Query for fuzzy search, excluding the current user
    $query = "SELECT id, username, firstName, lastName, profilePicture FROM users WHERE id != ? AND (";
    $conditions = [];
    // Add the current user's ID to exclude them
    $params = [$user_id]; 

    foreach ($search_words as $word) {
        $conditions[] = "(username LIKE ? OR firstName LIKE ? OR lastName LIKE ?)";
        $params[] = "%$word%";
        $params[] = "%$word%";
        $params[] = "%$word%";
    }

    $query .= implode(' AND ', $conditions);
    $query .= ") ORDER BY 
        CASE 
            WHEN username LIKE ? THEN 1 
            WHEN firstName LIKE ? OR lastName LIKE ? THEN 2 
            ELSE 3 
        END, username ASC";

    // Add the best match conditions to the parameters
    array_push($params, "%$search_term%", "%$search_term%", "%$search_term%");

    // Execute the query using prepared statements
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, str_repeat('s', count($params)), ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    // Fetch all matching users
    $users = mysqli_fetch_all($result, MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Results</title>
    <link rel="stylesheet" href="style.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
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

        /* Spacer Div (Same Height as Top Section including padding) */
        .spacer {
            height: 72px;
        }

        body {
            font-family: Arial, sans-serif;
            background-color: #121212;
            color: white;
        }

        .search-form {
            flex-grow: 1; 
            display: flex;
            gap: 8px; 
            max-width: 100%;
            align-items: center; 
        }

        .search-form input[type="text"] {
            flex-grow: 1;
            padding: 8px; 
            border: 1px solid #333; 
            border-radius: 8px; 
            background-color: #1e1e1e; 
            color: white;
            font-size: 14px; 
            height: 40px; 
            box-sizing: border-box; 
        }

        .search-form button {
            padding: 8px 16px; 
            background-color: #1e90ff; 
            color: white; 
            border: none; 
            border-radius: 8px; 
            cursor: pointer; 
            font-size: 14px; 
            height: 40px; 
            box-sizing: border-box; 
        }

        .search-results {
            max-width: 600px;
            margin: 0 auto;
        }

        .user-result {
            display: flex;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #333;
        }

        .user-result:hover {
            background-color: #1e1e1e;
        }

        .user-result img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            margin-right: 15px;
        }

        .user-info {
            flex-grow: 1;
        }

        .user-info h3 {
            margin: 0;
            font-size: 18px;
        }

        .user-info p {
            margin: 0;
            color: #888;
        }

        .no-results {
            text-align: center;
            color: #888;
            margin-top: 20px;
        }

        .error-message {
            text-align: center;
            color: #ff4444;
            margin-top: 20px;
        }

        /* Responsive adjustments for smaller screens */
        @media (max-width: 600px) {
            .top-section {
                height: 64px;
            }

            .spacer {
                height: 64px;
            }

            .search-bar button {
                display: none;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Top Section with Back Arrow and Header -->
        <div class="top-section">
            <a href="homepage.php"><img class="arrow" src="assets/arrow.png" alt="back arrow"
                    style="height: 40px; width: 40px;"></a>
            <h1 style="height: 50px;">Search Results</h1>
        </div>
        <!-- Spacer Div (Same Height as Top Section) -->
        <div class="spacer"></div>

        <!-- Search Bar -->
        <div style="width:100%; padding:8px; align-items: center;" class="search-bar">
            <form method="GET" action="search_result.php" class="search-form">
                <input type="text" name="search_username" placeholder="Search for users..."
                    value="<?php echo htmlspecialchars($search_term); ?>">
                <button type="submit">Search</button>
            </form>
        </div>

        <!-- Display Search Results -->
        <?php if (!empty($search_term)): ?>
            <div class="search-results">
                <h2>Search Results for "<?php echo htmlspecialchars($search_term); ?>"</h2>
                <?php if (empty($users)): ?>
                    <p class="no-results">No users found.</p>
                <?php else: ?>
                    <?php foreach ($users as $user): ?>
                        <!-- Make the entire result clickable -->
                        <a href="search.php?search_username=<?php echo urlencode($user['username']); ?>&search_term=<?php echo urlencode($search_term); ?>"
                            style="text-decoration: none; color: inherit;">
                            <div class="user-result">
                                <img src="uploads/profile_pictures/<?php echo htmlspecialchars($user['profilePicture']); ?>"
                                    alt="Profile Picture">
                                <div class="user-info">
                                    <h3><?php echo htmlspecialchars($user['username']); ?></h3>
                                    <p><?php echo htmlspecialchars($user['firstName'] . ' ' . $user['lastName']); ?></p>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>
