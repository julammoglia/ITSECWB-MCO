<?php
session_start();
require_once 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'error' => 'Invalid request method']));
}

$user_id = $_SESSION['user_id'] ?? 1; 
$payment_method = $_POST['payment_method'] ?? '';
$currency_code = 3; // PHP currency

try {
    $conn->begin_transaction();
    
    // Get cart items and calculate total
    $cart_query = "SELECT c.product_code, c.quantity, p.srp_php, p.stock_qty, p.product_name 
                   FROM cart c 
                   JOIN products p ON c.product_code = p.product_code 
                   WHERE c.user_id = ?";
    
    $stmt = $conn->prepare($cart_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $cart_items = $stmt->get_result();
    
    if ($cart_items->num_rows === 0) {
        throw new Exception('Cart is empty');
    }
    
    $total_amount = 0;
    $order_items = [];
    
    // Check stock and calculate total
    while ($item = $cart_items->fetch_assoc()) {
        if ($item['quantity'] > $item['stock_qty']) {
            throw new Exception("Insufficient stock for {$item['product_name']}");
        }
        
        $item_total = $item['srp_php'] * $item['quantity'];
        $total_amount += $item_total;
        $order_items[] = $item;
    }
    
    // Add shipping for card/ewallet
    if ($payment_method === 'card' || $payment_method === 'ewallet') {
        $total_amount += 0; // Assuming no shipping cost for now
    }
    
    // Create order
    $order_query = "INSERT INTO orders (user_id, order_date, totalamt_php, order_status, currency_code) 
                    VALUES (?, CURDATE(), ?, 'Processing', ?)";
    
    $stmt = $conn->prepare($order_query);
    $stmt->bind_param("idi", $user_id, $total_amount, $currency_code);
    $stmt->execute();
    $order_id = $conn->insert_id;
    
    // Insert order items and update stock
    foreach ($order_items as $item) {
        // Insert order item
        $item_query = "INSERT INTO order_items (order_id, product_code, quantity, srp_php, totalprice_php) 
                       VALUES (?, ?, ?, ?, ?)";
        
        $item_total = $item['srp_php'] * $item['quantity'];
        $stmt = $conn->prepare($item_query);
        $stmt->bind_param("iiddd", $order_id, $item['product_code'], $item['quantity'], $item['srp_php'], $item_total);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to insert order item: " . $stmt->error);
        }
        
        // Update stock
        $stock_query = "UPDATE products SET stock_qty = stock_qty - ? WHERE product_code = ?";
        $stmt = $conn->prepare($stock_query);
        $stmt->bind_param("ii", $item['quantity'], $item['product_code']);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update stock: " . $stmt->error);
        }
        
        // Verify stock update
        if ($stmt->affected_rows === 0) {
            throw new Exception("No stock was updated for product: " . $item['product_name']);
        }
    }
    
    // Create payment record
    $payment_status = ($payment_method === 'cash') ? 'unpaid' : 'paid';
    $payment_date = ($payment_method === 'cash') ? NULL : date('Y-m-d H:i:s');
    
    $payment_query = "INSERT INTO payments (currency_code, order_id, totalamt_php, payment_status, payment_method, payment_date) 
                      VALUES (?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($payment_query);
    $stmt->bind_param("iidsss", $currency_code, $order_id, $total_amount, $payment_status, $payment_method, $payment_date);
    $stmt->execute();
    
    // Clear cart
    $clear_cart = "DELETE FROM cart WHERE user_id = ?";
    $stmt = $conn->prepare($clear_cart);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'order_id' => $order_id,
        'total' => $total_amount,
        'message' => 'Payment processed successfully!'
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    
    // Error log
    error_log("Payment processing error: " . $e->getMessage());
    error_log("Error trace: " . $e->getTraceAsString());
    
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage(),
        'debug_info' => [
            'user_id' => $user_id,
            'payment_method' => $payment_method,
            'total_amount' => $total_amount ?? 0
        ]
    ]);
}
?>