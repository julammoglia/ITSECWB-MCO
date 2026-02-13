<?php
session_start();
include "includes/EnvLoader.php";
include "includes/db.php";
include "includes/rate_limit.php";
include "includes/password_policy.php";

// Load Cloudflare Turnstile Configuration from environment
$turnstile_secret_key = EnvLoader::get('TURNSTILE_SECRET_KEY');
$turnstile_site_key = EnvLoader::get('TURNSTILE_SITE_KEY');

// Initialize variables
$login_error = "";
$forgot_password_error = "";
$forgot_password_success = "";
$register_error = "";
$register_success = "";

// Get client IP using the same function as rate_limit
$client_ip = get_client_ip();

// Get failed attempts from rate_limit table by IP
$login_attempts = 0;
$reset_attempts = 0;
$register_attempts = 0;

// Check login attempts from rate_limit table
$stmt = $conn->prepare("SELECT count FROM rate_limits WHERE rl_key = 'login' AND ip = ? AND window_start > UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 10 MINUTE))");
$stmt->bind_param("s", $client_ip);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $login_attempts = $row['count'];
}
$stmt->close();

// Check register attempts from rate_limit table
$stmt = $conn->prepare("SELECT count FROM rate_limits WHERE rl_key = 'register' AND ip = ? AND window_start > UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 10 MINUTE))");
$stmt->bind_param("s", $client_ip);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $register_attempts = $row['count'];
}
$stmt->close();

// Check reset attempts from rate_limit table
$stmt = $conn->prepare("SELECT count FROM rate_limits WHERE rl_key = 'forgot_password' AND ip = ? AND window_start > UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 10 MINUTE))");
$stmt->bind_param("s", $client_ip);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $reset_attempts = $row['count'];
}
$stmt->close();

// Determine if CAPTCHA should be shown
$show_login_captcha = $login_attempts >= 3;
$show_reset_captcha = $reset_attempts >= 2;
$show_register_captcha = $register_attempts >= 3;

// Function to verify Turnstile token
function verify_turnstile_token($token, $secret_key) {
    if (empty($token)) {
        return ['success' => false];
    }

    $url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    $data = [
        'secret' => $secret_key,
        'response' => $token
    ];
    
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/x-www-form-urlencoded',
            'content' => http_build_query($data),
            'timeout' => 10
        ]
    ];
    
    $context = stream_context_create($options);
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        return ['success' => false];
    }
    
    return json_decode($response, true);
}

// Handle login form submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    // Check CAPTCHA if required
    if ($show_login_captcha && $turnstile_secret_key) {
        if (empty($_POST['cf-turnstile-response'])) {
            $login_error = "Please complete the CAPTCHA verification.";
        } else {
            $captcha_result = verify_turnstile_token($_POST['cf-turnstile-response'], $turnstile_secret_key);
            if (!$captcha_result['success']) {
                $login_error = "CAPTCHA verification failed. Please try again.";
            }
        }
    }
    
    if (empty($login_error)) {
        // Rate limit: login (8 per 10 minutes)
        $rl_result = rate_limit($conn, 'login', 8, 600);
        if (!$rl_result['allowed']) {
            $login_error = send_rate_limit_error($rl_result['retry_after'], $email, 'login')['error'];
        } else {
            $stmt = $conn->prepare("SELECT user_id, password, user_role, first_name, last_name FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows === 1) {
                $stmt->bind_result($user_id, $hashed_password, $user_role, $first_name, $last_name);
                $stmt->fetch();

                if (password_verify($password, $hashed_password)) {
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['user_role'] = $user_role;
                    $_SESSION['first_name'] = $first_name;
                    $_SESSION['last_name'] = $last_name;
                    $_SESSION['email'] = $email;
                    $_SESSION['logged_in'] = true;
                    $_SESSION['login_attempts'] = 0;

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
                    $_SESSION['login_attempts'] = $login_attempts + 1;
                    $login_error = "Invalid email and/or password";
                }
            } else {
                $_SESSION['login_attempts'] = $login_attempts + 1;
                $login_error = "Invalid email and/or password";
            }
            $stmt->close();
        }
    }
}

// Handle registration form submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['register'])) {
    // Check CAPTCHA if required
    if ($show_register_captcha && $turnstile_secret_key) {
        if (empty($_POST['cf-turnstile-response'])) {
            $register_error = "Please complete the CAPTCHA verification.";
        } else {
            $captcha_result = verify_turnstile_token($_POST['cf-turnstile-response'], $turnstile_secret_key);
            if (!$captcha_result['success']) {
                $register_error = "CAPTCHA verification failed. Please try again.";
            }
        }
    }
    
    if (empty($register_error)) {
        // Rate limit: register (5 per 10 minutes)
        $rl_result = rate_limit($conn, 'register', 5, 600);
        if (!$rl_result['allowed']) {
            $register_error = send_rate_limit_error($rl_result['retry_after'], $_POST['regEmail'] ?? '', 'register')['error'];
        } else {
            $firstName = trim($_POST['firstName']);
            $lastName = trim($_POST['lastName']);
            $email = trim($_POST['regEmail']);
            $password = $_POST['regPassword'];
            $confirmPassword = $_POST['confirmPassword'];

            if (empty($firstName) || empty($lastName) || empty($email) || empty($password) || empty($confirmPassword)) {
                $register_error = "All fields are required.";
                $_SESSION['register_attempts'] = $register_attempts + 1;
            } elseif (($policy = validate_password_policy($password)) && !$policy['valid']) {
                $register_error = implode(" ", $policy['errors']);
                $_SESSION['register_attempts'] = $register_attempts + 1;
            } elseif ($password !== $confirmPassword) {
                $register_error = "Passwords do not match.";
                $_SESSION['register_attempts'] = $register_attempts + 1;
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $register_error = "Please enter a valid email address.";
                $_SESSION['register_attempts'] = $register_attempts + 1;
            } else {
                $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $stmt->store_result();

                if ($stmt->num_rows > 0) {
                    $register_error = "An account with this email already exists.";
                    $_SESSION['register_attempts'] = $register_attempts + 1;
                } else {
                    $passwordHash = password_hash($password, PASSWORD_BCRYPT);
                    $insert_stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, password, user_role) VALUES (?, ?, ?, ?, 'Customer')");
                    $insert_stmt->bind_param("ssss", $firstName, $lastName, $email, $passwordHash);
                    
                    if ($insert_stmt->execute()) {
                        $newUserId = $conn->insert_id;

                        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                            $file = $_FILES['profile_picture'];
                            $uploadDir = __DIR__ . '/uploads/profile_pictures/';
                            $validUpload = true;

                            if ($file['size'] > 2 * 1024 * 1024) {
                                $validUpload = false;
                            }

                            if ($validUpload) {
                                $finfo = new finfo(FILEINFO_MIME_TYPE);
                                $mimeType = $finfo->file($file['tmp_name']);
                                if (!in_array($mimeType, ['image/jpeg', 'image/png'])) {
                                    $validUpload = false;
                                }
                            }

                            if ($validUpload) {
                                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                                if (!in_array($ext, ['jpg', 'jpeg', 'png'])) {
                                    $validUpload = false;
                                }
                            }

                            if ($validUpload) {
                                $newFilename = 'user_' . $newUserId . '_' . time() . '.' . $ext;
                                $destination = $uploadDir . $newFilename;

                                if (move_uploaded_file($file['tmp_name'], $destination)) {
                                    $picStmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE user_id = ?");
                                    $picStmt->bind_param("si", $newFilename, $newUserId);
                                    $picStmt->execute();
                                    $picStmt->close();
                                }
                            }
                        }

                        $register_success = "Account created successfully! You can now sign in with your credentials.";
                        $_SESSION['register_attempts'] = 0;
                        $_POST = array();
                    } else {
                        $register_error = "Something went wrong. Please try again.";
                        $_SESSION['register_attempts'] = $register_attempts + 1;
                    }
                    $insert_stmt->close();
                }
                $stmt->close();
            }
        }
    }
}

// Handle forgot password form submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['forgot_password'])) {
    // Check CAPTCHA if required
    if ($show_reset_captcha && $turnstile_secret_key) {
        if (empty($_POST['cf-turnstile-response'])) {
            $forgot_password_error = "Please complete the CAPTCHA verification.";
        } else {
            $captcha_result = verify_turnstile_token($_POST['cf-turnstile-response'], $turnstile_secret_key);
            if (!$captcha_result['success']) {
                $forgot_password_error = "CAPTCHA verification failed. Please try again.";
            }
        }
    }
    
    if (empty($forgot_password_error)) {
        // Rate limit: forgot password (8 per 10 minutes)
        $rl_result = rate_limit($conn, 'forgot_password', 8, 600);
        if (!$rl_result['allowed']) {
            $forgot_password_error = send_rate_limit_error($rl_result['retry_after'], $_POST['email'] ?? '', 'forgot_password')['error'];
            $_SESSION['reset_attempts'] = $reset_attempts + 1;
        } else {
            $email = trim($_POST['email']);
            $old_password = $_POST['oldPassword'];
            $new_password = $_POST['newPassword'];

            if (empty($email) || empty($old_password) || empty($new_password)) {
                $forgot_password_error = "All fields are required.";
                $_SESSION['reset_attempts'] = $reset_attempts + 1;
            } elseif (($policy = validate_password_policy($new_password)) && !$policy['valid']) {
                $forgot_password_error = implode(" ", $policy['errors']);
                $_SESSION['reset_attempts'] = $reset_attempts + 1;
            } else {
                $stmt = $conn->prepare("SELECT user_id, password FROM users WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $stmt->store_result();

                if ($stmt->num_rows === 1) {
                    $stmt->bind_result($user_id, $hashed_password);
                    $stmt->fetch();

                    if (password_verify($old_password, $hashed_password)) {
                        $newPasswordHash = password_hash($new_password, PASSWORD_BCRYPT);
                        $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
                        $update_stmt->bind_param("ss", $newPasswordHash, $email);
                        
                        if ($update_stmt->execute()) {
                            $forgot_password_success = "Password updated successfully. You can now login with your new password.";
                            $_SESSION['reset_attempts'] = 0;
                        } else {
                            $forgot_password_error = "Something went wrong. Please try again.";
                            $_SESSION['reset_attempts'] = $reset_attempts + 1;
                        }
                        $update_stmt->close();
                    } else {
                        $forgot_password_error = "Old password is incorrect.";
                        $_SESSION['reset_attempts'] = $reset_attempts + 1;
                    }
                } else {
                    $forgot_password_error = "No account found with that email.";
                    $_SESSION['reset_attempts'] = $reset_attempts + 1;
                }
                $stmt->close();
            }
        }
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
    <?php if ($turnstile_site_key && ($show_login_captcha || $show_register_captcha || $show_reset_captcha)): ?>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <?php endif; ?>
    <style>
        .cf-turnstile {
            display: flex;
            justify-content: center;
            margin: 15px 0;
        }
    </style>
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
                <a href="#" id="forgotPasswordLink" onclick="toggleForm('forgotPassword')">Reset Password?</a>
            </div>
            
            <?php if ($show_login_captcha && $turnstile_site_key): ?>
            <div class="cf-turnstile" data-sitekey="<?php echo htmlspecialchars($turnstile_site_key); ?>" data-theme="light"></div>
            <?php endif; ?>
            
            <?php if (!empty($login_error)) { echo "<p style='color: red; margin: 10px 0; font-size: 14px;'>$login_error</p>"; } ?>
            
            <button type="submit" name="login"><i class="fa fa-sign-in"></i>Sign In</button>
        </form>

        <!-- Register Form -->
        <form id="registerForm" method="post" action="" enctype="multipart/form-data" style="display:none;">
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

            <label for="regPassword">Password <small>(min 12 chars, 3 of: upper/lower/digit/special, no spaces)</small></label>
            <div class="password-container">
                <input type="password" id="regPassword" name="regPassword" placeholder="Create a password" minlength="12" required>
                <i onclick="togglePassword('regPassword')" class="fa fa-eye"></i>
            </div>

            <label for="confirmPassword">Confirm Password</label>
            <div class="password-container">
                <input type="password" id="confirmPassword" name="confirmPassword" placeholder="Confirm your password" required>
                <i onclick="togglePassword('confirmPassword')" class="fa fa-eye"></i>
            </div>

            <label for="regProfilePicture">Profile Picture <small>(optional — max 2MB, JPG/JPEG/PNG)</small></label>
            <input type="file" id="regProfilePicture" name="profile_picture" accept="image/jpeg,image/png">

            <div style="background-color: #f8f9fa; padding: 10px; border-radius: 5px; margin: 10px 0; font-size: 14px; color: #666;">
                <i class="fa fa-info-circle"></i> Your account will be created as a Customer account
            </div>

            <div class="terms">
                <label><input type="checkbox" name="agree" required> I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a></label>
            </div>

            <?php if ($show_register_captcha && $turnstile_site_key): ?>
            <div class="cf-turnstile" data-sitekey="<?php echo htmlspecialchars($turnstile_site_key); ?>" data-theme="light"></div>
            <?php endif; ?>

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

            <label for="newPassword">New Password <small>(min 12 chars, 3 of: upper/lower/digit/special, no spaces)</small></label>
            <input type="password" id="newPassword" name="newPassword" placeholder="Enter new password" minlength="12" required>

            <?php if ($show_reset_captcha && $turnstile_site_key): ?>
            <div class="cf-turnstile" data-sitekey="<?php echo htmlspecialchars($turnstile_site_key); ?>" data-theme="light"></div>
            <?php endif; ?>

            <?php if (!empty($forgot_password_error)) { echo "<p style='color: red; margin: 10px 0;'>$forgot_password_error</p>"; } ?>
            <?php if (!empty($forgot_password_success)) { echo "<p style='color: green; margin: 10px 0;'>$forgot_password_success</p>"; } ?>

            <button type="submit" name="forgot_password">Reset Password</button>
        </form>
    </div>
</div>

<script>
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

    <?php if (!empty($register_success)) { ?>
        toggleForm('register');
    <?php } elseif (!empty($register_error)) { ?>
        toggleForm('register');
    <?php } elseif (!empty($forgot_password_error) || !empty($forgot_password_success)) { ?>
        toggleForm('forgotPassword');
    <?php } ?>
</script>

</body>
</html>
