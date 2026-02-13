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
    <style>
        /* ============================
           ADDED: Inline field error styles
           ============================ */
        .field-error {
            margin-top: 6px;
            font-size: 0.85rem;
            color: #d93025;
            display: none;
        }
        .input-invalid {
            border-color: #d93025 !important;
            outline-color: #d93025 !important;
        }
    </style>
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

                            <!-- CHANGED: required + pattern 4 groups of 4 digits -->
                            <input type="text"
                                   class="form-input"
                                   placeholder="1234 5678 9012 3456"
                                   id="card-number"
                                   required
                                   inputmode="numeric"
                                   autocomplete="cc-number"
                                   maxlength="19"
                                   pattern="^\d{4}\s\d{4}\s\d{4}\s\d{4}$">
                            <div class="field-error" id="err-card-number"></div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Cardholder Name</label>

                            <!-- CHANGED: required + letters-only (with common name punctuation) -->
                            <input type="text"
                                   class="form-input"
                                   placeholder="John Doe"
                                   id="cardholder-name"
                                   required
                                   autocomplete="cc-name"
                                   maxlength="60"
                                   pattern="^[A-Za-zÀ-ÿ\s\-\'.]+$"
                                   title="Only letters, spaces, hyphen (-), apostrophe ('), and dot (.) are allowed.">
                            <div class="field-error" id="err-cardholder-name"></div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Expiry Date</label>

                                <!-- CHANGED: required + MM/YY -->
                                <input type="text"
                                       class="form-input"
                                       placeholder="MM/YY"
                                       id="expiry-date"
                                       required
                                       inputmode="numeric"
                                       autocomplete="cc-exp"
                                       maxlength="5"
                                       pattern="^(0[1-9]|1[0-2])\/\d{2}$"
                                       title="Use MM/YY format (e.g., 08/29).">
                                <div class="field-error" id="err-expiry-date"></div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">CVV</label>

                                <!-- CHANGED: required + exactly 3 digits -->
                                <input type="text"
                                       class="form-input"
                                       placeholder="123"
                                       id="cvv"
                                       required
                                       inputmode="numeric"
                                       autocomplete="cc-csc"
                                       maxlength="3"
                                       pattern="^\d{3}$">
                                <div class="field-error" id="err-cvv"></div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Billing Address</label>

                            <!-- CHANGED: required + letters-only per your request -->
                            <input type="text"
                                   class="form-input"
                                   placeholder="Main Street"
                                   id="billing-address"
                                   required
                                   maxlength="120"
                                   pattern="^[A-Za-zÀ-ÿ\s\-\'.]+$"
                                   title="Only letters, spaces, hyphen (-), apostrophe ('), and dot (.) are allowed.">
                            <div class="field-error" id="err-billing-address"></div>
                        </div>
                    </div>

                    <!-- E-Wallet Payment Content -->
                    <div class="payment-content" id="ewallet-content">
                        <div class="form-group">
                            <label class="form-label">E-Wallet Provider</label>

                            <!-- CHANGED: required -->
                            <select class="form-select" id="ewallet-provider" required>
                                <option value="">Select your e-wallet</option>
                                <option value="gcash">GCash</option>
                                <option value="paymaya">PayMaya</option>
                                <option value="grabpay">GrabPay</option>
                            </select>
                            <div class="field-error" id="err-ewallet-provider"></div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Phone Number / Account</label>

                            <!-- CHANGED: required + PH mobile format (09XXXXXXXXX) -->
                            <input type="tel"
                                   class="form-input"
                                   placeholder="09XXXXXXXXX"
                                   id="ewallet-account"
                                   required
                                   inputmode="tel"
                                   maxlength="11"
                                   pattern="^09\d{9}$"
                                   title="Use 11 digits starting with 09 (e.g., 09123456789).">
                            <div class="field-error" id="err-ewallet-account"></div>
                            <small style="display:block; margin-top:6px; color:#6b7280; font-size:0.85rem;">
                                Use 11 digits starting with 09 (e.g., 09123456789).
                            </small>
                        </div>
                        
                        <div class="info-text">
                            You will be redirected to your e-wallet app to complete the payment.
                        </div>
                    </div>

                    <!-- Cash Payment Content -->
                    <div class="payment-content" id="cash-content">
                        <div class="form-group">
                            <label class="form-label">Pickup Location</label>

                            <!-- CHANGED: required -->
                            <select class="form-select" id="pickup-location" required>
                                <option value="">Select pickup location</option>
                                <option value="main-branch">Main Branch - BGC</option>
                                <option value="makati-branch">Makati Branch</option>
                                <option value="ortigas-branch">Ortigas Branch</option>
                            </select>
                            <div class="field-error" id="err-pickup-location"></div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Preferred Pickup Date</label>

                            <!-- CHANGED: required -->
                            <input type="date" class="form-input" id="pickup-date" required>
                            <div class="field-error" id="err-pickup-date"></div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Preferred Pickup Time</label>

                            <!-- CHANGED: required -->
                            <select class="form-select" id="pickup-time" required>
                                <option value="">Select time slot</option>
                                <option value="9-11">9:00 AM - 11:00 AM</option>
                                <option value="11-1">11:00 AM - 1:00 PM</option>
                                <option value="1-3">1:00 PM - 3:00 PM</option>
                                <option value="3-5">3:00 PM - 5:00 PM</option>
                            </select>
                            <div class="field-error" id="err-pickup-time"></div>
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

                            <!-- CHANGED: required + letters-only -->
                            <input type="text"
                                   class="form-input"
                                   placeholder="John"
                                   id="first-name"
                                   required
                                   maxlength="50"
                                   pattern="^[A-Za-zÀ-ÿ\s\-\'.]+$"
                                   title="Only letters, spaces, hyphen (-), apostrophe ('), and dot (.) are allowed.">
                            <div class="field-error" id="err-first-name"></div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Last Name</label>

                            <!-- CHANGED: required + letters-only -->
                            <input type="text"
                                   class="form-input"
                                   placeholder="Doe"
                                   id="last-name"
                                   required
                                   maxlength="50"
                                   pattern="^[A-Za-zÀ-ÿ\s\-\'.]+$"
                                   title="Only letters, spaces, hyphen (-), apostrophe ('), and dot (.) are allowed.">
                            <div class="field-error" id="err-last-name"></div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Address</label>

                        <!-- CHANGED: required + letters-only per your request -->
                        <input type="text"
                               class="form-input"
                               placeholder="Street address"
                               id="address"
                               required
                               maxlength="120"
                               pattern="^[A-Za-zÀ-ÿ\s\-\'.]+$"
                               title="Only letters, spaces, hyphen (-), apostrophe ('), and dot (.) are allowed.">
                        <div class="field-error" id="err-address"></div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">City</label>

                            <!-- CHANGED: required + letters-only -->
                            <input type="text"
                                   class="form-input"
                                   placeholder="Las Piñas"
                                   id="city"
                                   required
                                   maxlength="60"
                                   pattern="^[A-Za-zÀ-ÿ\s\-\'.]+$"
                                   title="Only letters, spaces, hyphen (-), apostrophe ('), and dot (.) are allowed.">
                            <div class="field-error" id="err-city"></div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Postal Code</label>

                            <!-- CHANGED: required + 4 digits -->
                            <input type="text"
                                   class="form-input"
                                   placeholder="1740"
                                   id="postal-code"
                                   required
                                   inputmode="numeric"
                                   maxlength="4"
                                   pattern="^\d{4}$"
                                   title="Postal code must be 4 digits.">
                            <div class="field-error" id="err-postal-code"></div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Phone Number</label>

                        <!-- CHANGED: required + PH mobile 11 digits -->
                        <input type="tel"
                               class="form-input"
                               placeholder="09XXXXXXXXX"
                               id="phone"
                               required
                               inputmode="tel"
                               maxlength="11"
                               pattern="^09\d{9}$"
                               title="Use 11 digits starting with 09 (e.g., 09123456789).">
                        <div class="field-error" id="err-phone"></div>
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

        const paymentMethods = document.querySelectorAll('.payment-method');
        const paymentContents = document.querySelectorAll('.payment-content');
        const shippingRow = document.getElementById('shipping-row');
        const totalAmount = document.getElementById('total-amount');
        const subtotalElement = document.querySelector('.summary-item .summary-value');

        let currentSubtotal = 0;

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
                    updateOrderSummary('card');
                }
            })
            .catch(error => console.error('Error loading cart total:', error));
        }

        paymentMethods.forEach(method => {
            method.addEventListener('click', () => {
                paymentMethods.forEach(m => m.classList.remove('active'));
                paymentContents.forEach(c => c.classList.remove('active'));

                method.classList.add('active');
                const methodType = method.getAttribute('data-method');
                document.getElementById(`${methodType}-content`).classList.add('active');

                updateOrderSummary(methodType);
                clearAllErrors(); // ADDED
            });
        });

        function updateOrderSummary(paymentMethod) {
            const shippingCard = document.getElementById('shipping-card');

            if (paymentMethod === 'cash') {
                shippingCard.style.display = 'none';
                shippingRow.style.display = 'none';
            } else {
                shippingCard.style.display = 'block';
                shippingRow.style.display = 'flex';
            }

            const currencySymbol = currentSubtotal.match(/[^0-9.,\s]+/g)?.[0] || '';
            const subtotalNumber = parseFloat(String(currentSubtotal).replace(/[^0-9.]/g, ''));
            const total = subtotalNumber + 0;

            totalAmount.textContent = `${currencySymbol}${total.toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            })}`;
        }

        /* ============================
           ADDED: Error helpers
           ============================ */
        function showError(inputEl, errorEl, message) {
            if (!inputEl || !errorEl) return;
            inputEl.classList.add('input-invalid');
            errorEl.textContent = message;
            errorEl.style.display = 'block';
        }

        function clearAllErrors() {
            document.querySelectorAll('.field-error').forEach(e => { e.textContent=''; e.style.display='none'; });
            document.querySelectorAll('.input-invalid').forEach(i => i.classList.remove('input-invalid'));
        }

        /* ============================
           ADDED: Validators
           ============================ */
        const nameRegex = /^[A-Za-zÀ-ÿ\s\-'.]+$/;
        const cardRegex = /^\d{4}\s\d{4}\s\d{4}\s\d{4}$/;
        const cvvRegex  = /^\d{3}$/;
        const expRegex  = /^(0[1-9]|1[0-2])\/\d{2}$/;
        const postalRegex = /^\d{4}$/;
        const phPhoneRegex = /^09\d{9}$/; // ADDED: PH standard mobile number

        function isFutureOrCurrentExpiry(mmYY) {
            const [mm, yy] = mmYY.split('/');
            const month = parseInt(mm, 10);
            const year = 2000 + parseInt(yy, 10);
            if (!month || !year) return false;

            const now = new Date();
            const currentMonth = now.getMonth() + 1;
            const currentYear = now.getFullYear();

            if (year > currentYear) return true;
            if (year === currentYear && month >= currentMonth) return true;
            return false;
        }

        // Form validation and completion
        document.getElementById('complete-payment').addEventListener('click', () => {
            const activeMethod = document.querySelector('.payment-method.active').getAttribute('data-method');

            if (!validateForm(activeMethod)) return;

            const button = document.getElementById('complete-payment');
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin" style="margin-right: 8px;"></i><span>Processing...</span>';
            button.disabled = true;

            const formData = new FormData();
            formData.append('payment_method', activeMethod);

            fetch('process_payment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const formattedTotal = currencyData.symbol + parseFloat(data.total / currencyData.price_php).toLocaleString('en-US', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                    alert(`Order confirmed! Order ID: ${data.order_id}\nTotal: ${formattedTotal}\n\nYou will receive a confirmation email shortly.`);
                    window.location.href = 'Index.php';
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(() => alert('An error occurred while processing your order. Please try again.'))
            .finally(() => {
                button.innerHTML = originalText;
                button.disabled = false;
            });
        });

        function validateForm(activeMethod) {
            clearAllErrors();

            // Shipping required for CARD + EWALLET
            if (activeMethod === 'card' || activeMethod === 'ewallet') {
                const firstName = document.getElementById('first-name');
                const lastName  = document.getElementById('last-name');
                const address   = document.getElementById('address');
                const city      = document.getElementById('city');
                const postal    = document.getElementById('postal-code');
                const phone     = document.getElementById('phone');

                let ok = true;

                if (!firstName.value.trim()) { showError(firstName, document.getElementById('err-first-name'), 'First name is required.'); ok = false; }
                else if (!nameRegex.test(firstName.value.trim())) { showError(firstName, document.getElementById('err-first-name'), 'First name must contain letters only.'); ok = false; }

                if (!lastName.value.trim()) { showError(lastName, document.getElementById('err-last-name'), 'Last name is required.'); ok = false; }
                else if (!nameRegex.test(lastName.value.trim())) { showError(lastName, document.getElementById('err-last-name'), 'Last name must contain letters only.'); ok = false; }

                if (!address.value.trim()) { showError(address, document.getElementById('err-address'), 'Address is required.'); ok = false; }
                else if (!nameRegex.test(address.value.trim())) { showError(address, document.getElementById('err-address'), 'Address must contain letters only.'); ok = false; }

                if (!city.value.trim()) { showError(city, document.getElementById('err-city'), 'City is required.'); ok = false; }
                else if (!nameRegex.test(city.value.trim())) { showError(city, document.getElementById('err-city'), 'City must contain letters only.'); ok = false; }

                if (!postal.value.trim()) { showError(postal, document.getElementById('err-postal-code'), 'Postal code is required.'); ok = false; }
                else if (!postalRegex.test(postal.value.trim())) { showError(postal, document.getElementById('err-postal-code'), 'Postal code must be 4 digits.'); ok = false; }

                if (!phone.value.trim()) { showError(phone, document.getElementById('err-phone'), 'Phone number is required.'); ok = false; }
                else if (!phPhoneRegex.test(phone.value.trim())) { showError(phone, document.getElementById('err-phone'), 'Phone must be 11 digits starting with 09.'); ok = false; }

                if (!ok) return false;
            }

            // Card fields required (ALL required)
            if (activeMethod === 'card') {
                const cardNumber     = document.getElementById('card-number');
                const cardholderName = document.getElementById('cardholder-name');
                const expiryDate     = document.getElementById('expiry-date');
                const cvv            = document.getElementById('cvv');
                const billing        = document.getElementById('billing-address');

                let ok = true;

                if (!cardNumber.value.trim()) { showError(cardNumber, document.getElementById('err-card-number'), 'Card number is required.'); ok = false; }
                else if (!cardRegex.test(cardNumber.value.trim())) { showError(cardNumber, document.getElementById('err-card-number'), 'Card number must be 1234 5678 9012 3456.'); ok = false; }

                if (!cardholderName.value.trim()) { showError(cardholderName, document.getElementById('err-cardholder-name'), 'Cardholder name is required.'); ok = false; }
                else if (!nameRegex.test(cardholderName.value.trim())) { showError(cardholderName, document.getElementById('err-cardholder-name'), 'Cardholder name must contain letters only.'); ok = false; }

                if (!expiryDate.value.trim()) { showError(expiryDate, document.getElementById('err-expiry-date'), 'Expiry date is required.'); ok = false; }
                else if (!expRegex.test(expiryDate.value.trim())) { showError(expiryDate, document.getElementById('err-expiry-date'), 'Expiry must be MM/YY (01-12).'); ok = false; }
                else if (!isFutureOrCurrentExpiry(expiryDate.value.trim())) { showError(expiryDate, document.getElementById('err-expiry-date'), 'Card is expired.'); ok = false; }

                if (!cvv.value.trim()) { showError(cvv, document.getElementById('err-cvv'), 'CVV is required.'); ok = false; }
                else if (!cvvRegex.test(cvv.value.trim())) { showError(cvv, document.getElementById('err-cvv'), 'CVV must be exactly 3 digits.'); ok = false; }

                if (!billing.value.trim()) { showError(billing, document.getElementById('err-billing-address'), 'Billing address is required.'); ok = false; }
                else if (!nameRegex.test(billing.value.trim())) { showError(billing, document.getElementById('err-billing-address'), 'Billing address must contain letters only.'); ok = false; }

                return ok;
            }

            // Ewallet required fields (ALL required) + PH phone validation
            if (activeMethod === 'ewallet') {
                const provider = document.getElementById('ewallet-provider');
                const account  = document.getElementById('ewallet-account');

                let ok = true;

                if (!provider.value) {
                    showError(provider, document.getElementById('err-ewallet-provider'), 'Please select an e-wallet provider.');
                    ok = false;
                }

                if (!account.value.trim()) {
                    showError(account, document.getElementById('err-ewallet-account'), 'E-wallet phone number is required.');
                    ok = false;
                } else if (!phPhoneRegex.test(account.value.trim())) {
                    // ADDED: Validate e-wallet phone as PH mobile number (09XXXXXXXXX)
                    showError(account, document.getElementById('err-ewallet-account'), 'E-wallet phone must be 11 digits starting with 09.');
                    ok = false;
                }

                return ok;
            }

            // Cash required fields (ALL required)
            if (activeMethod === 'cash') {
                const location = document.getElementById('pickup-location');
                const date     = document.getElementById('pickup-date');
                const time     = document.getElementById('pickup-time');

                let ok = true;
                if (!location.value) { showError(location, document.getElementById('err-pickup-location'), 'Pickup location is required.'); ok = false; }
                if (!date.value) { showError(date, document.getElementById('err-pickup-date'), 'Pickup date is required.'); ok = false; }
                if (!time.value) { showError(time, document.getElementById('err-pickup-time'), 'Pickup time is required.'); ok = false; }
                return ok;
            }

            return true;
        }

        /* ============================
           CHANGED: Input restrictions (live)
           ============================ */

        // Card number: digits only, 16 max, format 4x4
        document.getElementById('card-number').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\s/g, '').replace(/[^0-9]/g, '').substring(0, 16);
            e.target.value = (value.match(/.{1,4}/g) || []).join(' ');
        });

        // Names + city + address + billing: letters only (with - ' . and spaces)
        function enforceLettersOnly(el) {
            if (!el) return;
            el.addEventListener('input', function() {
                el.value = el.value.replace(/[^A-Za-zÀ-ÿ\s\-'.]/g, '').replace(/\s+/g, ' ');
            });
        }
        enforceLettersOnly(document.getElementById('cardholder-name'));
        enforceLettersOnly(document.getElementById('first-name'));
        enforceLettersOnly(document.getElementById('last-name'));
        enforceLettersOnly(document.getElementById('city'));
        enforceLettersOnly(document.getElementById('address'));
        enforceLettersOnly(document.getElementById('billing-address'));

        // Expiry: digits only -> MM/YY
        document.getElementById('expiry-date').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '').substring(0, 4);
            if (value.length >= 3) value = value.substring(0, 2) + '/' + value.substring(2);
            e.target.value = value;
        });

        // CVV: digits only, 3 max
        document.getElementById('cvv').addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '').substring(0, 3);
        });

        // Postal: digits only, 4 max
        document.getElementById('postal-code').addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '').substring(0, 4);
        });

        // Shipping phone: digits only, 11 max
        document.getElementById('phone').addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '').substring(0, 11);
        });

        // ADDED: E-wallet phone: digits only, 11 max (PH format)
        document.getElementById('ewallet-account').addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '').substring(0, 11);
        });

        // Set minimum pickup date to tomorrow
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        document.getElementById('pickup-date').min = tomorrow.toISOString().split('T')[0];
    </script>
</body>
</html>