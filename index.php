<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Register & Login</title>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <link rel="stylesheet" href="style.css">
  <style>
    .container {
      background-color: #1e1e1e;
      padding: 20px;
      border-radius: 10px;
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
      width: 100%;
      max-width: 400px;
      margin: 20px;
    }

    h1.form-title {
      text-align: center;
      color: #ffffff;
      margin-bottom: 20px;
    }

    .input-group {
      margin-bottom: 15px;
      position: relative;
    }

    .input-group input,
    .input-group select {
      width: 100%;
      padding: 10px;
      border: 1px solid #444;
      border-radius: 5px;
      font-size: 16px;
      background-color: #1e1e1e;
      color: #ffffff;
    }

    .input-group input::placeholder {
      color: #bbb;
    }

    .input-group label {
      display: block;
      margin-bottom: 5px;
      color: #bbb;
      font-size: 14px;
    }

    .input-group input[type="radio"] {
      width: auto;
      margin-right: 10px;
    }

    .input-group input[type="radio"]+label {
      display: inline-block;
      margin-right: 15px;
      font-size: 14px;
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

    .or {
      text-align: center;
      color: #777;
      margin: 20px 0;
    }

    .links {
      text-align: center;
      margin-top: 15px;
    }

    .links p {
      margin: 0;
      color: #bbb;
    }

    #errorMessage,
    #passwordCriteria,
    #passwordMatch {
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

      h1.form-title {
        font-size: 24px;
      }

      .input-group input,
      .input-group select {
        font-size: 14px;
      }

      .btn {
        font-size: 14px;
      }

      .links button {
        font-size: 14px;
      }
    }

    .greybtn {
      background-color: #6c757d;
      width: 100%;
      padding: 10px;
      color: white;
      border: none;
      border-radius: 5px;
      font-size: 16px;
      cursor: pointer;
      margin-top: 10px;
    }

    .greybtn:hover {
      background-color: rgb(76, 83, 88);
    }

    .logo.img {
      width: 100%;
      height: auto;
    }
  </style>
</head>
<div class="logo">
  <img src="assets/logoworkouttracker.png" alt="Logo" style="width: 100%;">
</div>

<body>
  <!--sign up section-->
  <div class="container" id="signup" style="display: none;">
    <h1 class="form-title">Register</h1>
    <form method="post" action="register.php" id="signupForm">
      <div class="input-group">
        <input type="email" name="email" id="email" placeholder="Email" required>
        <label for="email">Email</label>
      </div>
      <div class="input-group">
        <input type="text" name="fName" id="fName" placeholder="First Name" required>
        <label for="fName">First Name</label>
      </div>
      <div class="input-group">
        <input type="text" name="lName" id="lName" placeholder="Last Name" required>
        <label for="lName">Last Name</label>
      </div>
      <div class="input-group">
        <input type="text" name="username" id="username" placeholder="Username" required>
        <label for="username">Username</label>
      </div>
      <div class="input-group">
        <input type="date" name="dob" id="dob" placeholder="Date of Birth" required>
        <label for="dob">Date of Birth</label>
      </div>
      <div class="input-group">
        <input type="radio" name="sex" id="male" value="Male" required>
        <label for="male">Male</label>
        <input type="radio" name="sex" id="female" value="Female" required>
        <label for="female">Female</label>
      </div>
      <div class="input-group">
        <input type="password" name="password" id="password" placeholder="Password" required>
        <label for="password">Password</label>
      </div>
      <div class="input-group">
        <input type="password" name="confirmPassword" id="confirmPassword" placeholder="Confirm Password" required>
        <label for="confirmPassword">Confirm Password</label>
      </div>
      <div class="input-group">
        <label for="security_question">Security Question</label>
        <select name="security_question" id="security_question" required>
          <option value="What is your mother's maiden name?">What is your mother's maiden name?</option>
          <option value="What was the name of your first pet?">What was the name of your first pet?</option>
          <option value="What city were you born in?">What city were you born in?</option>
          <option value="What is the name of your favorite teacher?">What is the name of your favorite teacher?</option>
        </select>
      </div>
      <div class="input-group">
        <input type="text" name="security_answer" id="security_answer" placeholder="Security Answer" required>
        <label for="security_answer">Security Answer</label>
      </div>
      <p id="passwordCriteria" style="color: #ff4444; display: none;">
        Password must be at least 8 characters, contain one uppercase letter, and include both letters and numbers.
      </p>
      <p id="passwordMatch" style="color: #ff4444; display: none;">
        Passwords do not match.
      </p>
      <input type="submit" class="btn" value="Sign Up" name="signUp">
    </form>
    <p class="or"> ----------or-------- </p>
    <div class="links">
      <p>Already Have Account ?</p>
      <button class="btn" id="signInButton">Sign In</button>
    </div>
  </div>
  <!--sign in section -->
  <div class="container" id="signIn">
    <h1 class="form-title">Sign In</h1>
    <div id="errorMessage" style="color: #ff4444; display: none;"></div>
    <form id="signInForm">
      <div class="input-group">
        <input type="text" name="username" id="usernameLogin" placeholder="Username" required>
      </div>
      <div class="input-group">
        <input type="password" name="password" id="passwordLogin" placeholder="Password" required>
      </div>
      <!-- Inside the Sign-In Form -->
      <div>
        <input type="checkbox" name="remember_me" id="remember_me">
        <label for="remember_me">Remember Me</label>
      </div>
      <input type="submit" class="btn" value="Sign In" name="signIn">
    </form>
    <button id="forgotPasswordButton" class="greybtn">Forgot Password?</button>
    <p class="or"> ----------or-------- </p>
    <div class="links">
      <p>Don't have account yet?</p>
      <button id="signUpButton" class="btn">Sign Up</button>
    </div>
  </div>

  <!-- Forgot Password Form -->
  <div class="container" id="forgotPassword" style="display: none;">
    <h1 class="form-title">Forgot Password</h1>
    <form method="post" action="forgot_password.php" id="forgotPasswordForm">
      <div class="input-group">
        <input type="text" name="username" id="forgotUsername" placeholder="Username" required>
      </div>
      <div class="input-group">
        <input type="email" name="email" id="forgotEmail" placeholder="Email" required>
      </div>
      <div class="input-group">
        <input type="date" name="dob" id="forgotDob" placeholder="Date of Birth" required>
      </div>
      <div class="input-group">
        <label for="forgotSecurityQuestion">Security Question</label>
        <select name="security_question" id="forgotSecurityQuestion" required>
          <option value="What is your mother's maiden name?">What is your mother's maiden name?</option>
          <option value="What was the name of your first pet?">What was the name of your first pet?</option>
          <option value="What city were you born in?">What city were you born in?</option>
          <option value="What is the name of your favorite teacher?">What is the name of your favorite teacher?</option>
        </select>
      </div>
      <div class="input-group">
        <input type="text" name="security_answer" id="forgotSecurityAnswer" placeholder="Security Answer" required>
      </div>
      <input type="submit" class="btn" value="Verify Details" name="verifyDetails">
    </form>
    <button id="backToLogin" class="greybtn">Back to Login</button>
  </div>

  <script>
    document.addEventListener("DOMContentLoaded", function () {
      console.log("JavaScript is running!");

      // Password validation for sign-up form
      document.getElementById("signupForm").addEventListener("submit", function (event) {
        const password = document.getElementById("password").value;
        const confirmPassword = document.getElementById("confirmPassword").value;
        const criteriaMessage = document.getElementById("passwordCriteria");
        const matchMessage = document.getElementById("passwordMatch");
        // At least 8 chars, 1 uppercase, 1 number, 1 letter
        const passwordRegex = /^(?=.*[A-Z])(?=.*[a-z])(?=.*\d).{8,}$/; 

        let isValid = true;

        // Check if password meets criteria
        if (!passwordRegex.test(password)) {
          criteriaMessage.style.display = "block";
          isValid = false;
        } else {
          criteriaMessage.style.display = "none";
        }

        // Check if passwords match
        if (password !== confirmPassword) {
          matchMessage.style.display = "block";
          isValid = false;
        } else {
          matchMessage.style.display = "none";
        }

        if (!isValid) {
          console.log("Form submission blocked due to password errors.");
          // STOP FORM FROM SUBMITTING
          event.preventDefault(); 
        } else {
          console.log("Form is valid, proceeding with submission.");
        }
      });

      // Form Toggle Functionality
      const signUpButton = document.getElementById("signUpButton");
      const signInButton = document.getElementById("signInButton");
      const forgotPasswordButton = document.getElementById("forgotPasswordButton");
      const backToLoginButton = document.getElementById("backToLogin");
      const signInForm = document.getElementById("signIn");
      const signUpForm = document.getElementById("signup");
      const forgotPasswordForm = document.getElementById("forgotPassword");

      signUpButton.addEventListener("click", function () {
        signInForm.style.display = "none";
        signUpForm.style.display = "block";
        forgotPasswordForm.style.display = "none";
      });

      signInButton.addEventListener("click", function () {
        signInForm.style.display = "block";
        signUpForm.style.display = "none";
        forgotPasswordForm.style.display = "none";
      });

      forgotPasswordButton.addEventListener("click", function () {
        signInForm.style.display = "none";
        signUpForm.style.display = "none";
        forgotPasswordForm.style.display = "block";
      });

      backToLoginButton.addEventListener("click", function () {
        forgotPasswordForm.style.display = "none";
        signInForm.style.display = "block";
      });

      // AJAX form submission for Sign In
      $("#signInForm").on("submit", function (event) {
        // Prevent the form from submitting normally
        event.preventDefault(); 

        const username = $("#usernameLogin").val();
        const password = $("#passwordLogin").val();
        // Check if "Remember Me" is selected
        const rememberMe = $("#remember_me").is(":checked"); 

        $.ajax({
          url: "register.php",
          type: "POST",
          data: {
            signIn: true,
            username: username,
            password: password,
            remember_me: rememberMe 
          },
          success: function (response) {
            if (response === "success") {
                // Redirect on success
              window.location.href = "homepage.php"; 
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
