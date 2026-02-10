<?php
session_start();
require_once 'includes/db.php';
include_once('currency_handler.php');
$current_currency = getCurrencyData($conn);

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Checkout</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles/payment.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="checkout-container">
        <!-- Header -->
        <div class="back-nav">
        <a href="index.php" class="back-btn">
            <span class="back-arrow">←</span>
            Back to Home
        </a>
            <h1 class="checkout-title">Checkout</h1>
        </div>

        <div class="checkout-content">
            <div class="main-content">
                <!-- Payment Method Card -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-shopping-cart card-icon"></i>
                        <h2 class="card-title">Payment Method</h2>
                    </div>

                    <div class="payment-methods">
                        <div class="payment-method active" data-method="card">
                            <i class="fas fa-credit-card"></i>
                            Card
                        </div>
                        <div class="payment-method" data-method="ewallet">
                            <i class="fas fa-wallet"></i>
                            E-Wallet
                        </div>
                        <div class="payment-method" data-method="cash">
                            <i class="fas fa-money-bill-wave"></i>
                            Cash
                        </div>
                    </div>

                    <!-- Card Payment Content -->
                    <div class="payment-content active" id="card-content">
                        <div class="form-group">
                            <label class="form-label">Card Number</label>
                            <input type="text" class="form-input" placeholder="1234 5678 9012 3456" id="card-number">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Cardholder Name</label>
                            <input type="text" class="form-input" placeholder="John Doe" id="cardholder-name">
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Expiry Date</label>
                                <input type="text" class="form-input" placeholder="MM/YY" id="expiry-date">
                            </div>
                            <div class="form-group">
                                <label class="form-label">CVV</label>
                                <input type="text" class="form-input" placeholder="123" id="cvv">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Billing Address</label>
                            <input type="text" class="form-input" placeholder="123 Main St, City, State 12345" id="billing-address">
                        </div>
                    </div>

                    <!-- E-Wallet Payment Content -->
                    <div class="payment-content" id="ewallet-content">
                        <div class="form-group">
                            <label class="form-label">E-Wallet Provider</label>
                            <select class="form-select" id="ewallet-provider">
                                <option value="">Select your e-wallet</option>
                                <option value="gcash">GCash</option>
                                <option value="paymaya">PayMaya</option>
                                <option value="grabpay">GrabPay</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Phone Number / Account</label>
                            <input type="text" class="form-input" placeholder="+63 912 345 6789" id="ewallet-account">
                        </div>
                        
                        <div class="info-text">
                            You will be redirected to your e-wallet app to complete the payment.
                        </div>
                    </div>

                    <!-- Cash Payment Content -->
                    <div class="payment-content" id="cash-content">
                        <div class="form-group">
                            <label class="form-label">Pickup Location</label>
                            <select class="form-select" id="pickup-location">
                                <option value="">Select pickup location</option>
                                <option value="main-branch">Main Branch - BGC</option>
                                <option value="makati-branch">Makati Branch</option>
                                <option value="ortigas-branch">Ortigas Branch</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Preferred Pickup Date</label>
                            <input type="date" class="form-input" id="pickup-date">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Preferred Pickup Time</label>
                            <select class="form-select" id="pickup-time">
                                <option value="">Select time slot</option>
                                <option value="9-11">9:00 AM - 11:00 AM</option>
                                <option value="11-1">11:00 AM - 1:00 PM</option>
                                <option value="1-3">1:00 PM - 3:00 PM</option>
                                <option value="3-5">3:00 PM - 5:00 PM</option>
                            </select>
                        </div>
                        
                        <div class="info-text">
                            Please bring exact change when picking up your order. A confirmation will be sent to your email.
                        </div>
                    </div>
                </div>

                <!-- Shipping Information Card -->
                <div class="card" id="shipping-card">
                    <div class="card-header">
                        <i class="fas fa-shipping-fast card-icon"></i>
                        <h2 class="card-title">Shipping Information</h2>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">First Name</label>
                            <input type="text" class="form-input" placeholder="John" id="first-name">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Last Name</label>
                            <input type="text" class="form-input" placeholder="Doe" id="last-name">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Address</label>
                        <input type="text" class="form-input" placeholder="Street address" id="address">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">City</label>
                            <input type="text" class="form-input" placeholder="Las Piñas" id="city">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Postal Code</label>
                            <input type="text" class="form-input" placeholder="1740" id="postal-code">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" class="form-input" placeholder="+63 912 345 6789" id="phone">
                    </div>
                </div>
            </div>

            <!-- Order Summary Sidebar -->
            <div class="order-summary">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-receipt card-icon"></i>
                        <h2 class="card-title">Order Summary</h2>
                    </div>

                    <div class="summary-item">
                        <span class="summary-label">Subtotal</span>
                        <span class="summary-value">₱0</span>
                    </div>
                    
                    <div class="summary-item" id="shipping-row" style="display: none;">
                        <span class="summary-label">Shipping</span>
                        <span class="summary-value"><?php echo formatPrice(0,$current_currency)?></span>
                    </div>
                

                    <div class="summary-item total">
                        <span>Total</span>
                        <span id="total-amount">₱0</span>
                    </div>

                    <div class="summary-buttons">
                        <button class="confirm-button" id="complete-payment">
                            <i class="fas fa-credit-card" style="margin-right: 8px;"></i>
                            Complete Payment
                        </button>
                        <div class="secure-checkout">
                            <i class="fas fa-lock"></i>
                            Secure Checkout
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>

        // Add currency data for JavaScript
        const currencyData = {
        currency_code: <?php echo $current_currency['currency_code']; ?>,
        currency_name: '<?php echo $current_currency['currency_name']; ?>',
        symbol: '<?php echo $current_currency['symbol']; ?>',
        price_php: <?php echo $current_currency['price_php']; ?>
        };

        // Payment method switching
        const paymentMethods = document.querySelectorAll('.payment-method');
        const paymentContents = document.querySelectorAll('.payment-content');
        const buttonText = document.getElementById('button-text');
        const shippingRow = document.getElementById('shipping-row');
        const totalAmount = document.getElementById('total-amount');
        const subtotalElement = document.querySelector('.summary-item .summary-value');

        let currentSubtotal = 0;

        // Load cart total on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadCartTotal();
        });

        function loadCartTotal() {
            fetch('get_cart_total.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    currentSubtotal = data.subtotal;
                    subtotalElement.textContent = `${currentSubtotal}`; 
                    updateOrderSummary('card'); // Initialize with card payment
                }
            })
            .catch(error => console.error('Error loading cart total:', error));
        }

        paymentMethods.forEach(method => {
            method.addEventListener('click', () => {
                // Remove active class from all methods
                paymentMethods.forEach(m => m.classList.remove('active'));
                paymentContents.forEach(c => c.classList.remove('active'));

                // Add active class to clicked method
                method.classList.add('active');
                const methodType = method.getAttribute('data-method');
                document.getElementById(`${methodType}-content`).classList.add('active');

                // Update button text and shipping
                updateOrderSummary(methodType);
            });
        });

        function updateOrderSummary(paymentMethod) {
            let shipping = 0;
            let buttonTextValue = 'Confirm Order';
            const shippingCard = document.getElementById('shipping-card');

            if (paymentMethod === 'cash') {
                buttonTextValue = 'Confirm Order';
                shippingCard.style.display = 'none';
                shippingRow.style.display = 'none';
            } else if (paymentMethod === 'ewallet') {
                shipping = 0;
                buttonTextValue = 'Complete Payment';
                shippingCard.style.display = 'block';
                shippingRow.style.display = 'flex';
            } else if (paymentMethod === 'card') {
                shipping = 0;
                buttonTextValue = 'Complete Payment';
                shippingCard.style.display = 'block';
                shippingRow.style.display = 'flex';
            }   

            //Extract the symbol (e.g., $, ₱, etc.)
            const currencySymbol = currentSubtotal.match(/[^0-9.,\s]+/g)?.[0] || '';

            //Convert the number part to a float
            const subtotalNumber = parseFloat(currentSubtotal.replace(/[^0-9.]/g, ''));

            //Add shipping
            const total = subtotalNumber + 0; // Assuming no shipping cost for now

            //Format total with symbol and 2 decimal places
            totalAmount.textContent = `${currencySymbol}${total.toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
            })}`;

            buttonText.textContent = buttonTextValue;
        }

        // Form validation and completion
        document.getElementById('complete-payment').addEventListener('click', () => {
            const activeMethod = document.querySelector('.payment-method.active').getAttribute('data-method');
            
            // Validate based on payment method
            if (!validateForm(activeMethod)) {
                return;
            }

            // Show loading state
            const button = document.getElementById('complete-payment');
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin" style="margin-right: 8px;"></i><span>Processing...</span>';
            button.disabled = true;

            // Prepare form data
            const formData = new FormData();
            formData.append('payment_method', activeMethod);

            // Process payment
            fetch('process_payment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Format the total with current currency
                    const formattedTotal = currencyData.symbol + parseFloat(data.total / currencyData.price_php).toLocaleString('en-US', {
                    minimumFractionDigits: 2, 
                    maximumFractionDigits: 2
                 });
                    alert(`Order confirmed! Order ID: ${data.order_id}\nTotal: ${formattedTotal}\n\nYou will receive a confirmation email shortly.`);
                    // Redirect to success page or reload
                    window.location.href = 'Index.php'; // Redirect to home page
                } else {
                    console.error('Payment error:', data);
                    let errorMsg = 'Error: ' + data.error;
                    if (data.debug_info) {
                        console.log('Debug info:', data.debug_info);
                    }
                    alert(errorMsg);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while processing your order. Please try again.');
            })
            .finally(() => {
                button.innerHTML = originalText;
                button.disabled = false;
            });
        });

        function validateForm(activeMethod) {
            if (activeMethod === 'card' || activeMethod === 'ewallet') {
                // Validate shipping info
                const firstName = document.getElementById('first-name').value.trim();
                const lastName = document.getElementById('last-name').value.trim();
                const address = document.getElementById('address').value.trim();
                
                if (!firstName || !lastName || !address) {
                    alert('Please fill in all shipping information fields.');
                    return false;
                }
            }

            if (activeMethod === 'card') {
                const cardNumber = document.getElementById('card-number').value.replace(/\s/g, '');
                const cardholderName = document.getElementById('cardholder-name').value.trim();
                const expiryDate = document.getElementById('expiry-date').value.trim();
                const cvv = document.getElementById('cvv').value.trim();

                if (!cardNumber || !cardholderName || !expiryDate || !cvv) {
                    alert('Please fill in all card information.');
                    return false;
                }
                if (cardNumber.length !== 16) {
                    alert('Please enter a valid 16-digit card number.');
                    return false;
                }
            } else if (activeMethod === 'ewallet') {
                const provider = document.getElementById('ewallet-provider').value;
                const account = document.getElementById('ewallet-account').value.trim();

                if (!provider || !account) {
                    alert('Please select an e-wallet provider and enter your account.');
                    return false;
                }
            } else if (activeMethod === 'cash') {
                const location = document.getElementById('pickup-location').value;
                const date = document.getElementById('pickup-date').value;
                const time = document.getElementById('pickup-time').value;

                if (!location || !date || !time) {
                    alert('Please fill in all pickup information.');
                    return false;
                }
            }
            
            return true;
        }

        // Auto-format card number
        document.getElementById('card-number').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\s/g, '').replace(/[^0-9]/gi, '');
            let formattedValue = value.match(/.{1,4}/g)?.join(' ') || '';
            if (value.length > 16) {
                formattedValue = formattedValue.substring(0, 19);
            }
            e.target.value = formattedValue;
        });

        // Auto-format expiry date
        document.getElementById('expiry-date').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 2) {
                value = value.substring(0, 2) + '/' + value.substring(2, 4);
            }
            e.target.value = value;
        });

        // Limit CVV to 3 digits
        document.getElementById('cvv').addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '').substring(0, 3);
        });

        // Set minimum pickup date to tomorrow
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        document.getElementById('pickup-date').min = tomorrow.toISOString().split('T')[0];
    </script>
</body>
</html>