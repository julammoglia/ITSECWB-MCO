<?php
session_start();
require_once 'includes/db.php';
include_once('currency_handler.php');
$current_currency = getCurrencyData($conn);

$user_id = $_SESSION['user_id'] ?? null; // Replace with actual session handling

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