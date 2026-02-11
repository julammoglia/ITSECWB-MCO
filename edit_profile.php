<?php
session_start();
require_once 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];

    // Clean input
    $firstName = isset($_POST['first_name']) ? trim($_POST['first_name']) : null;
    $lastName = isset($_POST['last_name']) ? trim($_POST['last_name']) : null;

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
    if ($firstName === '' || $firstName === null) {
        $firstName = $current['first_name'];
    }

    if ($lastName === '' || $lastName === null) {
        $lastName = $current['last_name'];
    }

    // Update name
    $updateStmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ? WHERE user_id = ?");
    $updateStmt->bind_param("ssi", $firstName, $lastName, $userId);
    $updateStmt->execute();

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
