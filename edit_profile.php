<?php
require_once 'includes/security/auth.php';
security_ensure_session_started();
require_once 'includes/db.php';
require_once 'includes/security/input_validation.php';
require_once 'includes/security/password_policy.php';

$userId = security_require_login('Login.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    security_redirect('User.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    security_require_csrf('User.php', 'error', 'csrf');

    // Branch by action: profile update or password change
    $action = isset($_POST['action']) ? $_POST['action'] : 'update_profile';

    if ($action === 'change_password') {
        // Change password flow
        $old = isset($_POST['old_password']) ? $_POST['old_password'] : '';
        $new = isset($_POST['new_password']) ? $_POST['new_password'] : '';
        $confirm = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

        // Basic validation
        if ($new !== $confirm) {
            header("Location: User.php?pwd=invalid");
            exit();
        }
        // Enforce centralized password policy
        $policy = validate_password_policy($new);
        if (!$policy['valid']) {
            header("Location: User.php?pwd=invalid");
            exit();
        }

        // Fetch current hash
        $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows === 0) {
            header("Location: User.php?pwd=notfound");
            exit();
        }
        $stmt->bind_result($currentHash);
        $stmt->fetch();

        // Verify old password
        if (!password_verify($old, $currentHash)) {
            header("Location: User.php?pwd=old_incorrect");
            exit();
        }

        // Prevent using the same password as the current one
        if (password_verify($new, $currentHash)) {
            header("Location: User.php?pwd=same");
            exit();
        }

        // Hash and update new password
        $newHash = password_hash($new, PASSWORD_BCRYPT);
        $up = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $up->bind_param("si", $newHash, $userId);
        if ($up->execute()) {
            security_initialize_authenticated_session();
            header("Location: User.php?pwd=success");
            exit();
        } else {
            header("Location: User.php?pwd=fail");
            exit();
        }
    }

    // Default: profile update flow
    $firstName = isset($_POST['first_name']) ? trim($_POST['first_name']) : null;
    $lastName = isset($_POST['last_name']) ? trim($_POST['last_name']) : null;

    $firstNameSubmitted = ($firstName !== '' && $firstName !== null);
    $lastNameSubmitted  = ($lastName  !== '' && $lastName  !== null);

    // Get current values from database
    $stmt = $conn->prepare("SELECT first_name, last_name, profile_picture FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result || $result->num_rows === 0) {
        echo "User not found.";
        exit();
    }

    $current = $result->fetch_assoc();

    // Keep current values if inputs are empty
    if (!$firstNameSubmitted) {
        $firstName = $current['first_name'];
    }

    if (!$lastNameSubmitted) {
        $lastName = $current['last_name'];
    }

    // Validate name format if new values were submitted
    if ($firstNameSubmitted && !security_is_valid_name($firstName)) {
        header("Location: User.php?error=invalid_name");
        exit();
    }
    if ($lastNameSubmitted && !security_is_valid_name($lastName)) {
        header("Location: User.php?error=invalid_name");
        exit();
    }

    // Update name
    $updateStmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ? WHERE user_id = ?");
    $updateStmt->bind_param("ssi", $firstName, $lastName, $userId);
    $updateStmt->execute();

    // Handle profile picture deletion
    if (isset($_POST['delete_picture']) && $_POST['delete_picture'] === '1') {
        if (!empty($current['profile_picture'])) {
            $uploadDir = __DIR__ . '/uploads/profile_pictures/';
            $oldFile = $uploadDir . $current['profile_picture'];
            if (file_exists($oldFile)) {
                unlink($oldFile);
            }
            $delStmt = $conn->prepare("UPDATE users SET profile_picture = NULL WHERE user_id = ?");
            $delStmt->bind_param("i", $userId);
            $delStmt->execute();
        }
        header("Location: User.php");
        exit();
    }

    // Handle profile picture upload
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['profile_picture'];
        $uploadDir = __DIR__ . '/uploads/profile_pictures/';

        // Validate file size (max 2MB)
        if ($file['size'] > 2 * 1024 * 1024) {
            header("Location: User.php?error=filesize");
            exit();
        }

        // Validate MIME type using finfo (server-side check)
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        $allowedMimes = ['image/jpeg', 'image/png'];

        if (!in_array($mimeType, $allowedMimes)) {
            header("Location: User.php?error=filetype");
            exit();
        }

        // Validate file extension
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExts = ['jpg', 'jpeg', 'png'];

        if (!in_array($ext, $allowedExts)) {
            header("Location: User.php?error=filetype");
            exit();
        }

        // Generate unique filename
        $newFilename = 'user_' . $userId . '_' . time() . '.' . $ext;
        $destination = $uploadDir . $newFilename;

        // Delete old profile picture if exists
        if (!empty($current['profile_picture'])) {
            $oldFile = $uploadDir . $current['profile_picture'];
            if (file_exists($oldFile)) {
                unlink($oldFile);
            }
        }

        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            // Update database with new filename
            $picStmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE user_id = ?");
            $picStmt->bind_param("si", $newFilename, $userId);
            $picStmt->execute();
        }
    }

    header("Location: User.php");
    exit();
} else {
    echo "Unauthorized access.";
}
