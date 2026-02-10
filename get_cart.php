<?php
session_start();
include('includes/db.php');
include_once('currency_handler.php');
$current_currency = getCurrencyData($conn);

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['error' => 'User not logged in']);
    exit;
}

$action = $_GET['action'] ?? 'get';

switch($action) {
    case 'get':
        getCartItems($conn, $user_id);
        break;
    case 'add':
        addToCart($conn, $user_id);
        break;
    case 'update':
        updateCart($conn, $user_id);
        break;
    case 'delete':
        deleteFromCart($conn, $user_id);
        break;
    default:
        echo json_encode(['error' => 'Invalid action']);
}

function getCartItems($conn, $user_id) {
    $sql = "SELECT c.cart_id, c.product_code, c.quantity, 
                   p.product_name, p.srp_php, p.stock_qty,
                   (c.quantity * p.srp_php) as total_price
            FROM cart c 
            JOIN products p ON c.product_code = p.product_code 
            WHERE c.user_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $current_currency = getCurrencyData($conn);
    
    $items = [];
    $total = 0;
    
    while($row = $result->fetch_assoc()) {
        $items[] = [
            'cart_id' => $row['cart_id'],
            'product_code' => $row['product_code'],
            'name' => $row['product_name'],
            'quantity' => $row['quantity'],
            'price' => formatPrice($row['srp_php'], $current_currency),
            'total_price' => number_format($row['total_price'], 2),
            'stock_qty' => $row['stock_qty']
        ];
        $total += $row['total_price'];
    }
    
    echo json_encode([
        'items' => $items,
        'total' => formatPrice($total, $current_currency),
        'count' => count($items)
    ]);
}

function addToCart($conn, $user_id) {
    $product_code = $_POST['product_code'] ?? 0;
    $quantity = $_POST['quantity'] ?? 1;
    
    if (!$product_code) {
        echo json_encode(['error' => 'Product code required']);
        return;
    }
    
    // Check if product exists and has stock
    $check_sql = "SELECT stock_qty FROM products WHERE product_code = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("i", $product_code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['error' => 'Product not found']);
        return;
    }
    
    $product = $result->fetch_assoc();
    if ($product['stock_qty'] < $quantity) {
        echo json_encode(['error' => 'Not enough stock']);
        return;
    }
    
    // Check if item already exists in cart
    $existing_sql = "SELECT quantity FROM cart WHERE user_id = ? AND product_code = ?";
    $stmt = $conn->prepare($existing_sql);
    $stmt->bind_param("ii", $user_id, $product_code);
    $stmt->execute();
    $existing = $stmt->get_result();
    
    if ($existing->num_rows > 0) {
        // Update existing item
        $current_qty = $existing->fetch_assoc()['quantity'];
        $new_qty = $current_qty + $quantity;
        
        if ($new_qty > $product['stock_qty']) {
            echo json_encode(['error' => 'Not enough stock for requested quantity']);
            return;
        }
        
        $update_sql = "UPDATE cart SET quantity = ? WHERE user_id = ? AND product_code = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("iii", $new_qty, $user_id, $product_code);
    } else {
        // Insert new item
        $insert_sql = "INSERT INTO cart (user_id, product_code, quantity) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param("iii", $user_id, $product_code, $quantity);
    }
    
    if ($stmt->execute()) {
        echo json_encode(['success' => 'Item added to cart']);
    } else {
        echo json_encode(['error' => 'Failed to add item']);
    }
}

function updateCart($conn, $user_id) {
    $cart_id = $_POST['cart_id'] ?? 0;
    $quantity = $_POST['quantity'] ?? 1;
    
    if (!$cart_id || $quantity < 1) {
        echo json_encode(['error' => 'Invalid parameters']);
        return;
    }
    
    // Check stock availability
    $check_sql = "SELECT p.stock_qty FROM cart c 
                  JOIN products p ON c.product_code = p.product_code 
                  WHERE c.cart_id = ? AND c.user_id = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("ii", $cart_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['error' => 'Cart item not found']);
        return;
    }
    
    $product = $result->fetch_assoc();
    if ($product['stock_qty'] < $quantity) {
        echo json_encode(['error' => 'Not enough stock']);
        return;
    }
    
    $update_sql = "UPDATE cart SET quantity = ? WHERE cart_id = ? AND user_id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("iii", $quantity, $cart_id, $user_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => 'Cart updated']);
    } else {
        echo json_encode(['error' => 'Failed to update cart']);
    }
}

function deleteFromCart($conn, $user_id) {
    $cart_id = $_POST['cart_id'] ?? 0;
    
    if (!$cart_id) {
        echo json_encode(['error' => 'Cart ID required']);
        return;
    }
    
    $delete_sql = "DELETE FROM cart WHERE cart_id = ? AND user_id = ?";
    $stmt = $conn->prepare($delete_sql);
    $stmt->bind_param("ii", $cart_id, $user_id);
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        echo json_encode(['success' => 'Item removed from cart']);
    } else {
        echo json_encode(['error' => 'Failed to remove item']);
    }
}
?>