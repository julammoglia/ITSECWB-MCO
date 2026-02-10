<?php
session_start();
include "includes/db.php";

// Initialize variables
$login_error = "";
$forgot_password_error = "";
$forgot_password_success = "";
$register_error = "";
$register_success = "";

// Handle login form submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // to get user data
    $stmt = $conn->prepare("SELECT user_id, password, user_role, first_name, last_name FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 1) {
        $stmt->bind_result($user_id, $hashed_password, $user_role, $first_name, $last_name);
        $stmt->fetch();

        if ($password === $hashed_password) {
            $_SESSION['user_id'] = $user_id;
            $_SESSION['user_role'] = $user_role;
            $_SESSION['first_name'] = $first_name;
            $_SESSION['last_name'] = $last_name;
            $_SESSION['email'] = $email;
            $_SESSION['logged_in'] = true;

            // Role-based redirection
            $redirect_page = "";
            $role_message = "";
            
            switch (strtolower($user_role)) {
                case 'admin':
                    $redirect_page = 'Admin.php';
                    $role_message = 'Welcome Admin! Redirecting to admin dashboard...';
                    break;
                case 'staff':
                    $redirect_page = 'staff_main.php';
                    $role_message = 'Welcome Staff! Redirecting to staff panel...';
                    break;
                case 'customer':
                default:
                    $redirect_page = 'Index.php';
                    $role_message = 'Welcome Customer! Redirecting to homepage...';
                    break;
            }
            echo "<script>
                alert('$role_message');
                window.location.href = '$redirect_page';
            </script>";
            exit;
        } else {
            $login_error = "Incorrect password.";
        }
    } else {
        $login_error = "No account found with that email.";
    }
    $stmt->close();
}

// Handle registration form submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['register'])) {
    $firstName = trim($_POST['firstName']);
    $lastName = trim($_POST['lastName']);
    $email = trim($_POST['regEmail']);
    $password = $_POST['regPassword'];
    $confirmPassword = $_POST['confirmPassword'];

    // Validation
    if (empty($firstName) || empty($lastName) || empty($email) || empty($password) || empty($confirmPassword)) {
        $register_error = "All fields are required.";
    } elseif (strlen($password) < 6) {
        $register_error = "Password must be at least 6 characters long.";
    } elseif ($password !== $confirmPassword) {
        $register_error = "Passwords do not match.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $register_error = "Please enter a valid email address.";
    } else {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $register_error = "An account with this email already exists.";
        } else {
            // Insert new user (default customer role)
            $insert_stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, password, user_role) VALUES (?, ?, ?, ?, 'Customer')");
            $insert_stmt->bind_param("ssss", $firstName, $lastName, $email, $password);
            
            if ($insert_stmt->execute()) {
                $register_success = "Account created successfully! You can now sign in with your credentials.";
                // Clear form data after successful registration
                $_POST = array();
            } else {
                $register_error = "Something went wrong. Please try again.";
            }
            $insert_stmt->close();
        }
        $stmt->close();
    }
}

// Handle forgot password form submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['forgot_password'])) {
    $email = trim($_POST['email']);
    $old_password = $_POST['oldPassword'];
    $new_password = $_POST['newPassword'];

    // Validation
    if (empty($email) || empty($old_password) || empty($new_password)) {
        $forgot_password_error = "All fields are required.";
    } elseif (strlen($new_password) < 6) {
        $forgot_password_error = "New password must be at least 6 characters long.";
    } else {
        // Check if email exists
        $stmt = $conn->prepare("SELECT user_id, password FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 1) {
            $stmt->bind_result($user_id, $hashed_password);
            $stmt->fetch();

            if ($old_password === $hashed_password) {
                // Update with new password
                $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
                $update_stmt->bind_param("ss", $new_password, $email);
                
                if ($update_stmt->execute()) {
                    $forgot_password_success = "Password updated successfully. You can now login with your new password.";
                } else {
                    $forgot_password_error = "Something went wrong. Please try again.";
                }
                $update_stmt->close();
            } else {
                $forgot_password_error = "Old password is incorrect.";
            }
        } else {
            $forgot_password_error = "No account found with that email.";
        }
        $stmt->close();
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="styles/login.css">
</head>
<body>

<div class="container">
    <h1>Welcome to PluggedIn</h1>
    <p>Sign in to your account or create a new one</p>

    <!-- Toggle between forms -->
    <div class="btn-group">
        <button id="loginBtn" class="active" onclick="toggleForm('login')"><i class="fa fa-sign-in"></i>Sign In</button>
        <button id="registerBtn" onclick="toggleForm('register')"><i class="fa fa-user-plus"></i>Register</button>
    </div>

    <div class="form-wrapper">
        <!-- Login Form -->
        <form id="loginForm" method="post" action="" class="active">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" placeholder="Enter your email" required>

            <label for="password">Password</label>
            <div class="password-container">
                <input type="password" id="password" name="password" placeholder="Enter your password" required>
                <i onclick="togglePassword('password')" class="fa fa-eye"></i>
            </div>

            <div class="terms">
                <a href="#" id="forgotPasswordLink" onclick="toggleForm('forgotPassword')">Forgot password?</a>
            </div>
            
            <?php if (!empty($login_error)) { echo "<p style='color: red; margin: 10px 0; font-size: 14px;'>$login_error</p>"; } ?>
            
            <button type="submit" name="login"><i class="fa fa-sign-in"></i>Sign In</button>
        </form>

        <!-- Register Form -->
        <form id="registerForm" method="post" action="" style="display:none;">
            <div class="name-row">
                <div>
                    <label for="firstName">First Name</label>
                    <input type="text" id="firstName" name="firstName" placeholder="John" 
                           value="<?php echo isset($_POST['firstName']) && empty($register_success) ? htmlspecialchars($_POST['firstName']) : ''; ?>" required>
                </div>
                <div>
                    <label for="lastName">Last Name</label>
                    <input type="text" id="lastName" name="lastName" placeholder="Doe" 
                           value="<?php echo isset($_POST['lastName']) && empty($register_success) ? htmlspecialchars($_POST['lastName']) : ''; ?>" required>
                </div>
            </div>

            <label for="regEmail">Email</label>
            <input type="email" id="regEmail" name="regEmail" placeholder="john.doe@example.com" 
                   value="<?php echo isset($_POST['regEmail']) && empty($register_success) ? htmlspecialchars($_POST['regEmail']) : ''; ?>" required>

            <label for="regPassword">Password <small>(minimum 6 characters)</small></label>
            <div class="password-container">
                <input type="password" id="regPassword" name="regPassword" placeholder="Create a password" minlength="6" required>
                <i onclick="togglePassword('regPassword')" class="fa fa-eye"></i>
            </div>

            <label for="confirmPassword">Confirm Password</label>
            <div class="password-container">
                <input type="password" id="confirmPassword" name="confirmPassword" placeholder="Confirm your password" required>
                <i onclick="togglePassword('confirmPassword')" class="fa fa-eye"></i>
            </div>

            <div style="background-color: #f8f9fa; padding: 10px; border-radius: 5px; margin: 10px 0; font-size: 14px; color: #666;">
                <i class="fa fa-info-circle"></i> Your account will be created as a Customer account
            </div>

            <div class="terms">
                <label><input type="checkbox" name="agree" required> I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a></label>
            </div>

            <?php if (!empty($register_error)) { echo "<p style='color: red; margin: 10px 0; font-size: 14px;'>$register_error</p>"; } ?>
            <?php if (!empty($register_success)) { echo "<p style='color: green; margin: 10px 0; font-size: 14px;'>$register_success</p>"; } ?>

            <button type="submit" name="register"><i class="fa fa-user-plus"></i>Create Account</button>
        </form>

        <!-- Forgot Password Form -->
        <form id="forgotPasswordForm" method="post" action="" style="display:none;">
            <label for="forgotEmail">Email</label>
            <input type="email" id="forgotEmail" name="email" placeholder="Enter your email" required>

            <label for="oldPassword">Old Password</label>
            <input type="password" id="oldPassword" name="oldPassword" placeholder="Enter old password" required>

            <label for="newPassword">New Password <small>(minimum 6 characters)</small></label>
            <input type="password" id="newPassword" name="newPassword" placeholder="Enter new password" minlength="6" required>

            <?php if (!empty($forgot_password_error)) { echo "<p style='color: red; margin: 10px 0;'>$forgot_password_error</p>"; } ?>
            <?php if (!empty($forgot_password_success)) { echo "<p style='color: green; margin: 10px 0;'>$forgot_password_success</p>"; } ?>

            <button type="submit" name="forgot_password">Reset Password</button>
        </form>
    </div>
</div>

<script>
    // Toggle between login, register, and forgot password forms
    function toggleForm(form) {
        const loginForm = document.getElementById('loginForm');
        const registerForm = document.getElementById('registerForm');
        const forgotPasswordForm = document.getElementById('forgotPasswordForm');
        
        if (form === 'login') {
            loginForm.style.display = 'block';
            registerForm.style.display = 'none';
            forgotPasswordForm.style.display = 'none';
            setActiveButton('login');
        } else if (form === 'register') {
            loginForm.style.display = 'none';
            registerForm.style.display = 'block';
            forgotPasswordForm.style.display = 'none';
            setActiveButton('register');
        } else if (form === 'forgotPassword') {
            loginForm.style.display = 'none';
            registerForm.style.display = 'none';
            forgotPasswordForm.style.display = 'block';
            setActiveButton('forgotPassword');
        }
    }

    // Set active button style
    function setActiveButton(form) {
        const buttons = document.querySelectorAll('.btn-group button');
        buttons.forEach(button => {
            button.classList.remove('active');
        });
        
        if (form !== 'forgotPassword') {
            const activeButton = document.getElementById(form + 'Btn');
            if (activeButton) {
                activeButton.classList.add('active');
            }
        }
    }

    // Password visibility toggle
    function togglePassword(id) {
        const input = document.getElementById(id);
        const icon = input.nextElementSibling;
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }

    // Show registration form if there's a registration success message
    <?php if (!empty($register_success)) { ?>
        // Keep registration form visible to show success message
        toggleForm('register');
    <?php } elseif (!empty($register_error)) { ?>
        // Show registration form if there was an error
        toggleForm('register');
    <?php } elseif (!empty($forgot_password_error) || !empty($forgot_password_success)) { ?>
        // Show forgot password form if there was an error or success
        toggleForm('forgotPassword');
    <?php } ?>
</script>

</body>
</html>