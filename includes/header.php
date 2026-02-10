<?php
// Start session to check login status
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (file_exists('includes/db.php')) {
    include_once('includes/db.php');
    include_once('currency_handler.php');
    $header_currency = getCurrencyData($conn);
} else {
    $header_currency = ['currency_name' => 'PHP', 'symbol' => '₱'];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PluggedIn</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles/header.css">
</head>
<body>
<!-- Header Section -->
<header>
    <div class="logo">
        <a href="Index.php">
        <img src="assets/logo.png" alt="TechPeripherals Logo"> </a>
    </div>
    <div class="search-bar">
        <input type="text" placeholder="Search products...">
        <i class="fas fa-search"></i>
    </div>
    <div class="icons">
        <!-- Currency Dropdown -->
        <div class="icon dropdown">
            <button class="currency" id="currencyDropdownButton">
                <?php echo $header_currency['symbol'] . ' ' . $header_currency['currency_name']; ?> <i class="fas fa-caret-down"></i>
            </button>
            <div class="dropdown-content" id="currencyDropdownContent">
                <div class="dropdown-item active">₱ PHP</div>
                <div class="dropdown-item">$ USD</div>
                <div class="dropdown-item">₩ KRW</div>
            </div>
        </div>
        <!-- Favorite -->
        <div class="icon">
            <a href="Favorites.php"><i class="fas fa-heart"></i></a>
        </div>
        <!-- Cart -->
        <div class="icon">
            <a href="#" id="cart-toggle" class="cart-icon">
                <i class="fas fa-shopping-cart"></i>
            </a>
        </div>
        <!-- Profile -->
        <div class="icon">
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="User.php"><i class="fas fa-user"></i></a>
            <?php else: ?>
                <a href="Login.php"><i class="fas fa-user"></i></a>
            <?php endif; ?>
        </div>
    </div>
</header>
<!-- Side Cart -->

<div id="side-cart" class="side-cart">
    <div class="side-cart-header">
        <h3>Shopping Cart</h3>
        <button id="close-cart">&times;</button>
    </div>
    <div id="cart-items" class="side-cart-content">
        <p class="empty-cart-msg">Your cart is empty.</p>
    </div>
    <div class="side-cart-footer">
        <a href="payment.php">
            <button class="checkout-btn">Proceed to Checkout</button>
        </a>
    </div>
</div>

    <!-- Cart Overlay for mobile -->
    <div id="cart-overlay" class="cart-overlay"></div>

    <!-- Cart fetcher -->
    <!-- Cart fetcher -->
<script>
    // Enhanced Cart JavaScript
    document.getElementById('cart-toggle').addEventListener('click', function () {
        document.getElementById('side-cart').classList.add('open');
        loadCart();
    });

    document.getElementById('close-cart').addEventListener('click', function () {
        document.getElementById('side-cart').classList.remove('open');
    });

function loadCart() {
    fetch('get_cart.php?action=get')
    .then(response => response.json())
    .then(data => {
        const container = document.getElementById('cart-items');
        container.innerHTML = '';
        
        if (data.items.length === 0) {
            container.innerHTML = '<p class="empty-cart-msg">Your cart is empty.</p>';
        } else {
            // Add cart items
            data.items.forEach(item => {
                container.innerHTML += `
                <div class="cart-item" data-cart-id="${item.cart_id}">
                    <div class="item-image">
                        <i class="fas fa-headphones"></i>
                    </div>
                    <div class="item-info">
                        <strong>${item.name}</strong>
                        <div class="item-brand">TechPeripherals</div>
                        <p class="item-price">${item.price}</p>
                        <div class="quantity-controls">
                            <button class="qty-btn" onclick="updateQuantity(${item.cart_id}, ${item.quantity - 1})">−</button>
                            <span class="quantity">${item.quantity}</span>
                            <button class="qty-btn" onclick="updateQuantity(${item.cart_id}, ${item.quantity + 1})">+</button>
                        </div>
                    </div>
                    <button class="remove-btn" onclick="removeItem(${item.cart_id})">
                    <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
                `;
            });
            
            // Calculate subtotal without tax
            const subtotal = data.total.replace(/,/g, '');

            // Add cart summary (without tax)
            container.innerHTML += `
            <div class="cart-summary">
                <div class="summary-row">
                    <span class="summary-label">Subtotal</span>
                    <span class="summary-value"> ${subtotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                </div>
                <div class="summary-row total-row">
                    <span class="summary-label">Total</span>
                    <span class="summary-value">${subtotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                </div>
            </div>
            `;
        }
    })
    .catch(error => {
        console.error('Error loading cart:', error);
    });
}

    function updateQuantity(cartId, newQuantity) {
        if (newQuantity < 1) {
            removeItem(cartId);
            return;
        }
    
        const formData = new FormData();
        formData.append('cart_id', cartId);
        formData.append('quantity', newQuantity);
        
        fetch('get_cart.php?action=update', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadCart(); // Reload cart
            } else {
                alert(data.error || 'Failed to update quantity');
            }
        })
        .catch(error => {
            console.error('Error updating quantity:', error);
        });
    }

    function removeItem(cartId) {
        if (!confirm('Remove this item from cart?')) return;
        
        const formData = new FormData();
        formData.append('cart_id', cartId);
        
        fetch('get_cart.php?action=delete', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadCart(); // Reload cart
            } else {
                alert(data.error || 'Failed to remove item');
            }
        })
        .catch(error => {
            console.error('Error removing item:', error);
        });
    }

       </script>
       
   <!-- Improved Currency Dropdown JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const currencyButton = document.getElementById('currencyDropdownButton');
    const currencyDropdown = document.getElementById('currencyDropdownContent');
    const dropdownItems = document.querySelectorAll('.dropdown-item');
    
    // Toggle dropdown
    currencyButton.addEventListener('click', function(event) {
        event.stopPropagation();
        currencyDropdown.style.display = currencyDropdown.style.display === 'block' ? 'none' : 'block';
    });
    
    // Function to change currency with state preservation
    function changeCurrency(currencyId) {
        // Save current state before currency change
        const currentState = {
            activeCategory: document.querySelector('.category-btn.active')?.getAttribute('data-category') || 'all',
            searchTerm: document.querySelector('.search-bar input')?.value || '',
            scrollPosition: window.scrollY
        };
        
        // Store state in sessionStorage
        sessionStorage.setItem('pageState', JSON.stringify(currentState));
        
        const formData = new FormData();
        formData.append('action', 'change_currency');
        formData.append('currency_id', currencyId);
        
        fetch('currency_handler.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                console.error('Error changing currency:', data.error);
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
    }
    
    // Handle dropdown item clicks
    dropdownItems.forEach(item => {
        item.addEventListener('click', function(event) {
            event.stopPropagation();
            
            // Remove active class from all items
            dropdownItems.forEach(i => i.classList.remove('active'));
            
            // Add active class to clicked item
            this.classList.add('active');
            
            // Update button text
            currencyButton.innerHTML = this.innerHTML + ' <i class="fas fa-caret-down"></i>';
            
            // Hide dropdown
            currencyDropdown.style.display = 'none';
            
            // Get currency ID based on selected text
            const selectedText = this.textContent.trim();
            let currencyId;
            
            if (selectedText.includes('KRW')) {
                currencyId = 1;
            } else if (selectedText.includes('USD')) {
                currencyId = 2;
            } else if (selectedText.includes('PHP')) {
                currencyId = 3;
            }
            
            // Change currency
            if (currencyId) {
                changeCurrency(currencyId);
            }
        });
    });
    
    // Close dropdown when clicking outside
    window.addEventListener('click', function(event) {
        if (!currencyButton.contains(event.target) && !currencyDropdown.contains(event.target)) {
            currencyDropdown.style.display = 'none';
        }
    });
});
</script>


</body>
</html>