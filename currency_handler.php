<?php
// currency_handler.php
include_once('includes/db.php');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set default currency to PHP if not set
if (!isset($_SESSION['selected_currency'])) {
    $_SESSION['selected_currency'] = 3; // PHP is currency_code 3
}

// Handle AJAX currency change requests
if (isset($_POST['action']) && $_POST['action'] === 'change_currency') {
    $currency_id = intval($_POST['currency_id']);
    if (in_array($currency_id, [1, 2, 3])) { // Only allow valid currency codes
        $_SESSION['selected_currency'] = $currency_id;
        echo json_encode(['success' => true, 'currency_id' => $currency_id]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid currency']);
    }
    exit;
}

// Function to get currency data - Use function_exists to prevent redeclaration
if (!function_exists('getCurrencyData')) {
    function getCurrencyData($conn, $currency_id = null) {
        if ($currency_id === null) {
            $currency_id = $_SESSION['selected_currency'] ?? 3;
        }
        
        $stmt = $conn->prepare("SELECT currency_code, currency_name, symbol, price_php FROM currencies WHERE currency_code = ?");
        $stmt->bind_param("i", $currency_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            return $row;
        }
        
        // Fallback to PHP if currency not found
        return [
            'currency_code' => 3,
            'currency_name' => 'PHP',
            'symbol' => '₱',
            'price_php' => 1.00
        ];
    }
}

// Function to convert price - Use function_exists to prevent redeclaration
if (!function_exists('convertPrice')) {
    function convertPrice($php_price, $conversion_rate) {
        return $php_price / $conversion_rate;
    }
}

// Function to format price with currency - Use function_exists to prevent redeclaration
if (!function_exists('formatPrice')) {
    function formatPrice($php_price, $currency_data) {
        // price_php in the database should be the conversion rate FROM PHP
        // For PHP: rate = 1.0, for USD: rate = 0.018 (1 PHP = 0.018 USD), for KRW: rate = 24.5 (1 PHP = 24.5 KRW)
        $converted_price = convertPrice($php_price, $currency_data['price_php']);
        return $currency_data['symbol'] . number_format($converted_price, 2);
    }
}
?>