<?php
session_start();
include ('includes/header.php');
include_once('currency_handler.php');
$current_currency = getCurrencyData($conn);

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// DB connection
include('includes/db.php');
$user_id = $_SESSION['user_id'];

// Fetch favorited products with more details
$sql = "SELECT p.product_code, p.product_name, p.description, p.srp_php, p.stock_qty, c.category_name 
        FROM isfavorite f
        JOIN products p ON f.product_code = p.product_code
        LEFT JOIN categories c ON p.category_code = c.category_code
        WHERE f.user_id = ?
        ORDER BY p.product_name";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("i", $user_id);
if (!$stmt->execute()) {
    die("Execute failed: " . $stmt->error);
}

$result = $stmt->get_result();
$favorites = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $favorites[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Favorites | TechPeripherals</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles/product_category.css">
    <link rel="stylesheet" href="styles/product_modal.css">
    <link rel="stylesheet" href="styles/favorites.css">
</head>
<body>

<!-- Page Title Section -->
<div class="page-title">
    <div class="container">
        <h1><i class="far fa-heart"></i> My Favorites</h1>
    </div>
</div>

<?php if (!empty($favorites)): ?>
    <!-- Product Cards Container -->
    <section style="padding: 0 40px 60px;">
        <div id="favoritesContainer" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 30px;">
            <?php foreach ($favorites as $product): ?>
                <div class="product-card" data-category="<?php echo htmlspecialchars($product['category_code'] ?? ''); ?>">
                    <div class="product-image" onclick="openProductModal('<?php echo htmlspecialchars($product['product_code']); ?>')">
                        <i class="fas fa-image" style="font-size: 24px;"></i>
                        <span style="margin-left: 8px;">Product Image</span>
                        <div class="product-image-overlay">
                            <button class="quick-view-btn" onclick="event.stopPropagation(); openProductModal('<?php echo htmlspecialchars($product['product_code']); ?>')">
                                <i class="far fa-eye"></i>
                                Quick View
                            </button>
                            <button class="product-favorite-btn active" 
                                    id="favorite-card-<?php echo htmlspecialchars($product['product_code']); ?>"
                                    onclick="event.stopPropagation(); toggleFavorite('<?php echo htmlspecialchars($product['product_code']); ?>')">
                                <i class="fas fa-heart" style="color: #ff4757;"></i>
                            </button>
                        </div>
                    </div>
                    
                    <?php if (!empty($product['category_name'])): ?>
                        <div class="product-category">
                            <?php echo htmlspecialchars($product['category_name']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <h3 class="product-title">
                        <?php echo htmlspecialchars($product['product_name']); ?>
                    </h3>
                    
                    <?php if (!empty($product['description'])): ?>
                        <p class="product-description">
                            <?php echo htmlspecialchars($product['description']); ?>
                        </p>
                    <?php endif; ?>
                    
                    <div class="product-price">
                        <?php echo formatPrice($product['srp_php'], $current_currency); ?>
                    </div>
                    
                    <div class="product-stock <?php echo ($product['stock_qty'] <= 0) ? 'out-of-stock' : ''; ?>">
                        <?php if ($product['stock_qty'] > 0): ?>
                            <i class="fas fa-check-circle"></i> <?php echo $product['stock_qty']; ?> in stock
                        <?php else: ?>
                            <i class="fas fa-times-circle"></i> Out of stock
                        <?php endif; ?>
                    </div>
                    
                    <button class="add-to-cart-btn"
                            style="font-family: 'Outfit', sans-serif; color: white;"
                            <?php echo ($product['stock_qty'] <= 0) ? 'disabled' : ''; ?>
                            data-product-code="<?php echo htmlspecialchars($product['product_code']); ?>">
                        <i class="fas fa-shopping-cart"></i> 
                        <?php echo ($product['stock_qty'] > 0) ? 'Add to Cart' : 'Out of Stock'; ?>
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php else: ?>
    <div class="favorites-container">
        <div class="favorites-icon"><i class="fas fa-heart"></i></div>
        <div class="favorites-text">No favorites yet</div>
        <div class="favorites-subtext">Start browsing our products and click the heart icon to add items to your favorites list.</div>
        <a href="index.php" class="browse-btn"><i class="fas fa-shopping-bag"></i> Browse Products</a>
    </div>
<?php endif; ?>

<!-- Product Modal -->
<div id="productModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 style="margin: 0; color: #333;">Product Details</h2>
            <button class="modal-close" onclick="closeProductModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="modal-image">
                <i class="fas fa-image" style="font-size: 48px; color: #ccc;"></i>
            </div>
            <div class="modal-details">
                <h1 id="modalProductName">Product Name</h1>
                <p class="modal-description" id="modalDescription">Product description goes here.</p>
                                
                <div class="modal-price" id="modalPrice">₱12,500</div>
                                
                <div class="modal-stock" id="modalStock">
                    Stock: <span class="stock-available">18 available</span>
                </div>
                
                <div class="quantity-section">
                    <label>Quantity:</label>
                    <div class="quantity-controls">
                        <button class="quantity-btn" onclick="decreaseQuantity()">−</button>
                        <input type="number" class="quantity-input" id="modalQuantity" value="1" min="1">
                        <button class="quantity-btn" onclick="increaseQuantity()">+</button>
                    </div>
                </div>
                
                <div class="modal-actions">
                    <button class="add-to-cart-btn" id="modalAddToCart" data-product-code="">
                        <i class="fas fa-shopping-cart"></i>
                        Add to Cart
                    </button>
                    <button class="modal-favorite-btn active" id="modalFavoriteBtn" onclick="toggleFavorite()">
                        <i class="fas fa-heart" style="color: #ff4757;"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$stmt->close();
$conn->close();
?>

<script>
let currentProduct = null;

// Store products data for modal
const favoritesData = <?php echo json_encode($favorites); ?>;

// Store favorite states - all items on this page are favorites
let favoriteStates = {};

document.addEventListener("DOMContentLoaded", () => {
    // Initialize favorite states for all products on this page
    favoritesData.forEach(product => {
        favoriteStates[product.product_code] = true;
    });

    // Add to cart functionality for product cards only (exclude modal button)
    const addToCartButtons = document.querySelectorAll(".add-to-cart-btn:not([disabled]):not(#modalAddToCart)");
    addToCartButtons.forEach(button => {
        button.addEventListener("click", () => {
            const productCode = button.getAttribute("data-product-code");
            if (productCode) {
                addToCart(productCode, 1, button);
            }
        });
    });

    // Modal add to cart functionality
    document.getElementById('modalAddToCart').addEventListener('click', () => {
        if (currentProduct) {
            const quantity = document.getElementById('modalQuantity').value;
            const modalAddToCartBtn = document.getElementById('modalAddToCart');
            addToCart(currentProduct.product_code, quantity, modalAddToCartBtn);
        }
    });

    // Close modal when clicking outside
    window.addEventListener('click', (event) => {
        const modal = document.getElementById('productModal');
        if (event.target === modal) {
            closeProductModal();
        }
    });

    // Prevent modal from closing when clicking inside modal content
    document.querySelector('.modal-content').addEventListener('click', (event) => {
        event.stopPropagation();
    });
});

function openProductModal(productCode) {
    const product = favoritesData.find(p => p.product_code === productCode);
    if (!product) return;

    currentProduct = product;
    
    // Populate modal with product data
    document.getElementById('modalProductName').textContent = product.product_name;
    document.getElementById('modalDescription').textContent = product.description || 'Professional tech peripheral for enhanced productivity and performance.';
    document.getElementById('modalPrice').textContent = '₱' + Number(product.srp_php).toLocaleString();
    
    // Update stock information
    const stockElement = document.getElementById('modalStock');
    const addToCartBtn = document.getElementById('modalAddToCart');
    const quantityInput = document.getElementById('modalQuantity');
    
    // Set the product code for the modal add to cart button
    addToCartBtn.setAttribute('data-product-code', product.product_code);
    
    if (product.stock_qty > 0) {
        stockElement.innerHTML = `Stock: <span class="stock-available">${product.stock_qty} available</span>`;
        addToCartBtn.disabled = false;
        addToCartBtn.innerHTML = '<i class="fas fa-shopping-cart"></i> Add to Cart';
        quantityInput.max = product.stock_qty;
    } else {
        stockElement.innerHTML = `Stock: <span class="stock-unavailable">Out of stock</span>`;
        addToCartBtn.disabled = true;
        addToCartBtn.innerHTML = 'Out of Stock';
        quantityInput.max = 0;
    }
    
    // Reset quantity
    document.getElementById('modalQuantity').value = 1;
    
    // Show modal
    document.getElementById('productModal').style.display = 'block';
    document.body.style.overflow = 'hidden'; // Prevent background scrolling
}

function closeProductModal() {
    document.getElementById('productModal').style.display = 'none';
    document.body.style.overflow = 'auto'; // Restore scrolling
    currentProduct = null;
}

function increaseQuantity() {
    const quantityInput = document.getElementById('modalQuantity');
    const currentValue = parseInt(quantityInput.value);
    const maxValue = parseInt(quantityInput.max);
    
    if (currentValue < maxValue) {
        quantityInput.value = currentValue + 1;
    }
}

function decreaseQuantity() {
    const quantityInput = document.getElementById('modalQuantity');
    const currentValue = parseInt(quantityInput.value);
    
    if (currentValue > 1) {
        quantityInput.value = currentValue - 1;
    }
}

function toggleFavorite(productCode) {
    // If no productCode is passed, use current product (modal context)
    if (!productCode && currentProduct) {
        productCode = currentProduct.product_code;
    }
    
    if (!productCode) return;
    
    // Send request to server
    const formData = new FormData();
    formData.append('product_code', productCode);
    
    fetch('toggle_favorite.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update local state
            favoriteStates[productCode] = data.is_favorite;
            
            // If item was removed from favorites, remove from page
            if (data.action === 'removed') {
                // Close modal if it's open for this product
                if (currentProduct && currentProduct.product_code === productCode) {
                    closeProductModal();
                }
                
                // Reload the page to reflect changes
                window.location.reload();
            } else {
                // Update UI for added favorites
                updateFavoriteButtons(productCode, data.is_favorite);
            }
            
            // Show feedback
            const action = data.action === 'added' ? 'Added to' : 'Removed from';
            console.log(`${action} favorites: Product ${productCode}`);
        } else {
            console.error('Error toggling favorite:', data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

function updateFavoriteButtons(productCode, isFavorite) {
    // Update card favorite button
    const cardFavoriteBtn = document.getElementById(`favorite-card-${productCode}`);
    if (cardFavoriteBtn) {
        updateFavoriteButton(cardFavoriteBtn, isFavorite);
    }
    
    // Update modal favorite button (only if current product matches)
    if (currentProduct && currentProduct.product_code === productCode) {
        const modalFavoriteBtn = document.getElementById('modalFavoriteBtn');
        updateFavoriteButton(modalFavoriteBtn, isFavorite);
    }
}

function updateFavoriteButton(buttonElement, isFavorite) {
    const icon = buttonElement.querySelector('i');
    
    if (isFavorite) {
        buttonElement.classList.add('active');
        icon.className = 'fas fa-heart';
        icon.style.color = '#ff4757';
    } else {
        buttonElement.classList.remove('active');
        icon.className = 'far fa-heart';
        icon.style.color = '';
    }
}

function addToCart(productCode, quantity, buttonElement) {
    const formData = new FormData();
    formData.append('product_code', productCode);
    formData.append('quantity', quantity);
    
    fetch('get_cart.php?action=add', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const originalText = buttonElement.innerHTML;
            const originalColor = buttonElement.style.backgroundColor;
            
            buttonElement.innerHTML = '<i class="fas fa-check"></i> Added!';
            buttonElement.style.backgroundColor = "#27ae60";
            
            setTimeout(() => {
                buttonElement.innerHTML = originalText;
                buttonElement.style.backgroundColor = originalColor || "#7f4af1";
            }, 1500);
        } else {
            alert(data.error || 'Failed to add to cart');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to add to cart');
    });
}
</script>

</body>
</html>

<?php include('includes/footer.php'); ?>