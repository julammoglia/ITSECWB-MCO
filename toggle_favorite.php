<?php
require_once 'includes/security/auth.php';
security_ensure_session_started();
include('includes/db.php');
require_once 'includes/security.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$user_id = security_require_login_api('User not logged in');
security_require_csrf_api();
$product_code = security_validate_positive_number($_POST['product_code'] ?? null, false);

if (!$product_code) {
    echo json_encode(['success' => false, 'error' => 'Product code required']);
    exit;
}

try {
    $product_stmt = $conn->prepare("SELECT 1 FROM products WHERE product_code = ?");
    $product_stmt->bind_param("i", $product_code);
    $product_stmt->execute();
    $product_stmt->store_result();

    if ($product_stmt->num_rows === 0) {
        $product_stmt->close();
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Product not found']);
        exit;
    }
    $product_stmt->close();

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
    log_event('CUSTOMER_FAVORITE_FAILURE', [
        'user_id' => $user_id,
        'product_code' => $product_code,
        'exception' => $e->getMessage(),
    ]);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to update favorites right now. Please try again.']);
}

$conn->close();
?>
