<?php
session_start();
include('includes/db.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'User not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$product_code = $_POST['product_code'] ?? null;

if (!$product_code) {
    echo json_encode(['success' => false, 'error' => 'Product code required']);
    exit;
}

try {
    // Check if product is already in favorites
    $check_sql = "SELECT COUNT(*) as count FROM isfavorite WHERE user_id = ? AND product_code = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $user_id, $product_code);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row['count'] > 0) {
        // Remove from favorites
        $delete_sql = "DELETE FROM isfavorite WHERE user_id = ? AND product_code = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("ii", $user_id, $product_code);
        $delete_stmt->execute();
        
        echo json_encode(['success' => true, 'action' => 'removed', 'is_favorite' => false]);
    } else {
        // Add to favorites
        $insert_sql = "INSERT INTO isfavorite (user_id, product_code) VALUES (?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("ii", $user_id, $product_code);
        $insert_stmt->execute();
        
        echo json_encode(['success' => true, 'action' => 'added', 'is_favorite' => true]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>