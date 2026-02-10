<?php
session_start();
require_once 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];

    // Clean input
    $firstName = isset($_POST['first_name']) ? trim($_POST['first_name']) : null;
    $lastName = isset($_POST['last_name']) ? trim($_POST['last_name']) : null;

    // Get current values from database
    $stmt = $conn->prepare("SELECT first_name, last_name FROM users WHERE user_id = ?");
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

    // Update database
    $updateStmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ? WHERE user_id = ?");
    $updateStmt->bind_param("ssi", $firstName, $lastName, $userId);
    if ($updateStmt->execute()) {
        header("Location: User.php");
        exit();
    } else {
        echo "Failed to update: " . $conn->error;
    }
} else {
    echo "Unauthorized access.";
}
