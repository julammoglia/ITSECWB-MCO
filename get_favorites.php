<?php
session_start();
include('includes/db.php');

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'User not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];


try {
    $sql = "SELECT product_code FROM isfavorite WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $favorites = [];
    while ($row = $result->fetch_assoc()) {
        $favorites[] = $row['product_code'];
    }
    
    echo json_encode(['success' => true, 'favorites' => $favorites]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>