<?php
require_once 'includes/security/auth.php';
security_ensure_session_started();
require_once 'includes/db.php';
include_once('currency_handler.php');
$current_currency = getCurrencyData($conn);

header('Content-Type: application/json');

$user_id = security_require_login_api('User not logged in');

try {
    $query = "SELECT SUM(c.quantity * p.srp_php) as subtotal 
              FROM cart c 
              JOIN products p ON c.product_code = p.product_code 
              WHERE c.user_id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    $subtotal = formatPrice($row['subtotal'], $current_currency) ?? 0;
    
    echo json_encode([
        'success' => true,
        'subtotal' => $subtotal
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
