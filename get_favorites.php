<?php
require_once 'includes/security/auth.php';
security_ensure_session_started();
include('includes/db.php');
require_once 'includes/security.php';

header('Content-Type: application/json');

$user_id = security_require_login_api('User not logged in');


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
    log_event('CUSTOMER_FAVORITES_FETCH_FAILURE', [
        'user_id' => $user_id,
        'exception' => $e->getMessage(),
    ]);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to load favorites right now. Please try again.']);
}

$conn->close();
?>
