<?php
require_once 'includes/security/auth.php';
security_ensure_session_started();
require_once 'includes/db.php';
require_once 'includes/db_operations.php';
require_once 'includes/security.php';
require_once 'includes/security/input_validation.php';

header('Content-Type: application/json');

function checkout_fail(string $message, int $status = 400): void
{
    http_response_code($status);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    checkout_fail('Invalid request method.', 405);
}

$user_id = security_require_login_api('Unauthorized.');
security_require_csrf_api();
$payment_method = $_POST['payment_method'] ?? '';

// Validate payment method
$allowed_methods = ['card', 'ewallet', 'cash'];
if (!in_array($payment_method, $allowed_methods, true)) {
    checkout_fail('Invalid payment method.');
}

// Validate shipping fields for card and ewallet payments
if ($payment_method === 'card' || $payment_method === 'ewallet') {
    $first_name  = $_POST['first_name']  ?? '';
    $last_name   = $_POST['last_name']   ?? '';
    $address     = $_POST['address']     ?? '';
    $city        = $_POST['city']        ?? '';
    $postal_code = $_POST['postal_code'] ?? '';
    $phone       = $_POST['phone']       ?? '';

    $name_regex    = '/^[A-Za-zÀ-ÿ\s\-\'.]{1,50}$/u';
    $address_regex = '/^[A-Za-zÀ-ÿ0-9\s\-\'.#,]{1,120}$/u';
    $city_regex    = '/^[A-Za-zÀ-ÿ\s\-\'.]{1,60}$/u';
    $postal_regex  = '/^\d{4}$/';
    $phone_regex   = '/^09\d{9}$/';

    if (!security_is_valid_name($first_name) || mb_strlen($first_name) > 50) {
        checkout_fail('Invalid first name.');
    }
    if (!security_is_valid_name($last_name) || mb_strlen($last_name) > 50) {
        checkout_fail('Invalid last name.');
    }
    if (!preg_match($address_regex, $address)) {
        checkout_fail('Invalid address.');
    }
    if (!preg_match($city_regex, $city)) {
        checkout_fail('Invalid city.');
    }
    if (!preg_match($postal_regex, $postal_code)) {
        checkout_fail('Invalid postal code. Must be 4 digits.');
    }
    if (!security_is_valid_phone($phone)) {
        checkout_fail('Invalid phone number. Must be 11 digits starting with 09.');
    }
}

// Validate ewallet account number
if ($payment_method === 'card') {
    $card_number = trim((string) ($_POST['card_number'] ?? ''));
    $cardholder_name = trim((string) ($_POST['cardholder_name'] ?? ''));
    $expiry_date = trim((string) ($_POST['expiry_date'] ?? ''));
    $cvv = trim((string) ($_POST['cvv'] ?? ''));
    $billing_address = trim((string) ($_POST['billing_address'] ?? ''));

    if (preg_match('/^\d{4}\s\d{4}\s\d{4}\s\d{4}$/', $card_number) !== 1) {
        checkout_fail('Invalid card number.');
    }
    if (!security_is_valid_name($cardholder_name) || mb_strlen($cardholder_name) > 60) {
        checkout_fail('Invalid cardholder name.');
    }
    if (preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $expiry_date) !== 1) {
        checkout_fail('Invalid expiry date.');
    }

    [$expiryMonth, $expiryYearSuffix] = explode('/', $expiry_date, 2);
    $expiryMonth = (int) $expiryMonth;
    $expiryYear = 2000 + (int) $expiryYearSuffix;
    $currentMonth = (int) date('n');
    $currentYear = (int) date('Y');
    if ($expiryYear < $currentYear || ($expiryYear === $currentYear && $expiryMonth < $currentMonth)) {
        checkout_fail('Card is expired.');
    }

    if (preg_match('/^\d{3}$/', $cvv) !== 1) {
        checkout_fail('Invalid CVV.');
    }
    if (!preg_match($address_regex, $billing_address)) {
        checkout_fail('Invalid billing address.');
    }
}

if ($payment_method === 'ewallet') {
    $ewallet_account = $_POST['ewallet_account'] ?? '';
    if (!security_is_valid_phone($ewallet_account)) {
        checkout_fail('Invalid e-wallet phone number. Must be 11 digits starting with 09.');
    }
}

if ($payment_method === 'cash') {
    $pickup_location = trim((string) ($_POST['pickup_location'] ?? ''));
    $pickup_date = trim((string) ($_POST['pickup_date'] ?? ''));
    $pickup_time = trim((string) ($_POST['pickup_time'] ?? ''));

    $allowed_locations = ['main-branch', 'makati-branch', 'ortigas-branch'];
    $allowed_times = ['9-11', '11-1', '1-3', '3-5'];

    if (!in_array($pickup_location, $allowed_locations, true)) {
        checkout_fail('Invalid pickup location.');
    }
    if (!in_array($pickup_time, $allowed_times, true)) {
        checkout_fail('Invalid pickup time.');
    }

    $pickupDateObject = DateTime::createFromFormat('Y-m-d', $pickup_date);
    if (!$pickupDateObject || $pickupDateObject->format('Y-m-d') !== $pickup_date) {
        checkout_fail('Invalid pickup date.');
    }

    $minimumPickupDate = (new DateTime('today'))->modify('+1 day');
    if ($pickupDateObject < $minimumPickupDate) {
        checkout_fail('Pickup date must be at least one day in advance.');
    }
}

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
        
        if (!db_decrement_product_stock($conn, (int) $item['product_code'], (int) $item['quantity'], false)) {
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

    security_log_audit('TRANSACTION', 'SUCCESS', 'checkout', [
        'user_id' => $user_id,
        'order_id' => $order_id,
        'payment_method' => $payment_method,
        'payment_status' => $payment_status,
        'total_amount' => number_format($total_amount, 2, '.', ''),
    ], $user_id);
    
    echo json_encode([
        'success' => true, 
        'order_id' => $order_id,
        'total' => $total_amount,
        'message' => 'Payment processed successfully!'
    ]);
    
} catch (Exception $e) {
    $conn->rollback();

    security_log_audit('TRANSACTION', 'FAILED', 'checkout', [
        'user_id' => $user_id,
        'payment_method' => $payment_method,
        'reason' => $e->getMessage(),
    ], $user_id);

    echo json_encode([
        'success' => false, 
        'error' => 'Unable to complete checkout right now. Please review your order and try again.'
    ]);
}
?>
