<?php
session_start(); // Start the session

// Handle logout (before any output, including HTML)
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php"); // Redirect to home page
    exit();
}

// Make sure user is logged in (before any output)
if (!isset($_SESSION['user_id'])) {
    header("Location: Login.php"); 
    exit();
}

require_once 'includes/db.php'; 
include_once('currency_handler.php');
$current_currency = getCurrencyData($conn);
$userId = $_SESSION['user_id'];

// Fetch user profile
$stmt = $conn->prepare("SELECT user_id, user_role, first_name, last_name, email, password FROM users WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$userResult = $stmt->get_result()->fetch_assoc();

if (!$userResult) {
    // if user not found, redirect to login
    session_destroy();
    header("Location: Login.php");
    exit();
}


if (isset($_GET['error'])) {
    if ($_GET['error'] === 'unauthorized') {
        echo "<p class='error-msg'>Only customers can delete their account.</p>";
    } elseif ($_GET['error'] === 'notfound') {
        echo "<p class='error-msg'>User not found.</p>";
    }
}

// Create full name from first_name and last_name
$fullName = trim($userResult['first_name'] . ' ' . $userResult['last_name']);

// Fetch user orders with currency information
$orderQuery = $conn->prepare("
    SELECT o.order_id, o.order_date, o.totalamt_php, o.order_status, c.currency_name, c.price_php 
    FROM orders o 
    LEFT JOIN currencies c ON o.currency_code = c.currency_code 
    WHERE o.user_id = ? 
    ORDER BY o.order_date DESC
");
$orderQuery->bind_param("i", $userId);
$orderQuery->execute();
$orderResults = $orderQuery->get_result();

// Function to get user initials for avatar
function getUserInitials($firstName, $lastName) {
    $firstInitial = !empty($firstName) ? strtoupper($firstName[0]) : '';
    $lastInitial = !empty($lastName) ? strtoupper($lastName[0]) : '';
    return $firstInitial . $lastInitial;
}
$userInitials = getUserInitials($userResult['first_name'], $userResult['last_name']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>User Dashboard</title>
  <link rel="stylesheet" href="styles/users.css">
  <!-- Google Fonts - Outfit -->
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <!-- Font Awesome CDN -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <style>
    /* Global Font */
    * {
      font-family: 'Outfit', sans-serif;
    }

    /* Modal Styles */
    .modal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.4);
      animation: fadeIn 0.3s ease;
    }

    .modal-content {
      background: white;
      margin: 5% auto;
      padding: 0;
      border-radius: 16px;
      width: 90%;
      max-width: 500px;
      position: relative;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
      animation: slideIn 0.3s ease;
    }

    .modal-header {
      padding: 2rem 2rem 1rem;
      border-bottom: 1px solid #f3f4f6;
      position: relative;
    }

    .modal-header h2 {
      font-size: 1.5rem;
      font-weight: 600;
      margin: 0;
      color: #374151;
    }

    .close-btn {
      position: absolute;
      top: 1rem;
      right: 1rem;
      background: none;
      border: none;
      width: 32px;
      height: 32px;
      color: #9ca3af;
      font-size: 1.5rem;
      cursor: pointer;
      transition: color 0.2s ease;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 6px;
    }

    .close-btn:hover {
      color: #374151;
      background: #f3f4f6;
    }

    .modal-body {
      padding: 2rem;
    }

    .modal .form-group {
      margin-bottom: 1.5rem;
      position: relative;
    }

    .modal .form-group label {
      display: block;
      font-weight: 500;
      margin-bottom: 0.5rem;
      color: #374151;
      font-size: 0.875rem;
    }

    .input-wrapper {
      position: relative;
    }

    .input-icon {
      position: absolute;
      left: 0.75rem;
      top: 50%;
      transform: translateY(-50%);
      color: #9ca3af;
      font-size: 1rem;
      z-index: 1;
    }

    .modal .form-group input {
      width: 100%;
      padding: 0.75rem 1rem 0.75rem 2.5rem;
      border: 1px solid #d1d5db;
      border-radius: 8px;
      font-size: 1rem;
      background: white;
      color: #374151;
      transition: all 0.2s ease;
      box-sizing: border-box;
    }

    .modal .form-group input:focus {
      outline: none;
      border-color: #ecc94b;
      box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
    }

    .modal-actions {
      display: flex;
      gap: 5rem;
      padding: 0px;
      max-height: 50px;
    }

    .save-btn {
      background: #ecc94b;
      color: white;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      font-weight: 500;
      transition: all 0.2s ease;
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;    
    }

    .save-btn:hover {
      background: #c0a133ff;
    }

    .delete-btn {
      background: white;
      color: #ef4444;
      text-decoration: none;
      font-weight: 500;
      padding: 5px;
      border: 1px solid #fecaca;
      border-radius: 8px;
      transition: all 0.2s ease;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      font-size: 1rem;
      flex: 1;
    }

    .delete-btn:hover {
      background: #fef2f2;
      border-color: #f87171;
    }

    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }

    @keyframes slideIn {
      from {
        opacity: 0;
        transform: translateY(-20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
  </style>
</head>

<!-- Edit Profile Modal -->
<div id="editProfileModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h2>Edit Profile</h2>
      <button class="close-btn" onclick="closeModal()"><i class="fas fa-times"></i></button>
    </div>

    <div class="modal-body">
      <form action="edit_profile.php" method="post">
        <div class="form-group">
          <label for="edit_first_name">First Name</label>
          <div class="input-wrapper">
            <div class="input-icon"><i class="fas fa-user"></i></div>
            <input type="text" id="edit_first_name" name="first_name" value="<?= htmlspecialchars($userResult['first_name']) ?>" placeholder="Enter your first name">
          </div>
        </div>

        <div class="form-group">
          <label for="edit_last_name">Last Name</label>
          <div class="input-wrapper">
            <div class="input-icon"><i class="fas fa-user"></i></div>
            <input type="text" id="edit_last_name" name="last_name" value="<?= htmlspecialchars($userResult['last_name']) ?>" placeholder="Enter your last name">
          </div>
        </div>

        <div class="modal-actions">
          <button type="submit" class="save-btn">
            <i class="fas fa-save"></i> Save Changes
          </button>
          <a href="delete_account.php" onclick="return confirm('Are you sure you want to delete your account?')" class="delete-btn">
            <i class="fas fa-trash-alt"></i> Delete Account
          </a>
        </div>
      </form>
    </div>
  </div>
</div>

<body>
  <div class="container">
    <!-- Back navigation -->
    <div class="back-nav">
      <a href="index.php" class="back-btn">
        <span class="back-arrow"><i class="fas fa-arrow-left"></i></span>
        Back to Home
      </a>
    </div>
    
    <!-- User header with avatar and member info -->
    <div class="user-profile-header">
      <div class="avatar-large"><?= $userInitials ?></div>
      <div class="user-details">
        <h1><?= htmlspecialchars($fullName) ?></h1>
        <p class="member-since">Member since <?= date('n/j/Y') ?></p>
      </div>
    </div>
    
    <!-- Tab Navigation -->
    <div class="tab-navigation">
      <button class="tab-btn active" onclick="showTab('profile')">Profile</button>
      <button class="tab-btn" onclick="showTab('orders')">Order History</button>
    </div>
    
    <!-- Profile Tab Content -->
    <div id="profile" class="tab-content active">
      <div class="profile-section">
        <div class="section-header">
          <h2>
            <i class="fas fa-user-circle"></i>
            Profile Information
          </h2>
        </div>
        
        <div class="profile-form">
          <div class="form-row">
            <div class="form-group">
              <label for="full_name">Full Name</label>
              <div class="input-container">
                <input id="full_name" type="text" value="<?= htmlspecialchars($fullName) ?>" readonly>
                <button class="field-menu-btn"><i class="fas fa-ellipsis-v"></i></button>
              </div>
            </div>
            
            <div class="form-group">
              <label for="email">Email</label>
              <input id="email" type="email" value="<?= htmlspecialchars($userResult['email']) ?>" readonly>
            </div>
          </div>
                    
          <div class="form-actions">
            <button class="edit-profile-btn" onclick="openModal()">
              <i class="fas fa-edit btn-icon"></i>
              Edit Profile
            </button>
            
            <button class="logout-btn" onclick="return confirmLogout()">
              <a href="?logout=1" style="text-decoration: none; color: inherit;">
                <i class="fas fa-sign-out-alt"></i> Logout
              </a>
            </button>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Order History Tab Content -->
    <div id="orders" class="tab-content">
      <div class="orders-section">
        <div class="section-header">
          <h2><i class="fas fa-history"></i> Order History</h2>
        </div>
        
        <?php if ($orderResults->num_rows > 0): ?>
          <div class="orders-list">
            <?php while ($order = $orderResults->fetch_assoc()): ?>
              <div class="order-card">
                <div class="order-header">
                  <div class="order-info">
                    <h3>Order #<?= htmlspecialchars($order['order_id']) ?></h3>
                    <p class="order-date"><?= date('M j, Y', strtotime($order['order_date'])) ?></p>
                  </div>
                  <span class="status-badge <?= strtolower(str_replace(' ', '-', $order['order_status'])) ?>">
                    <?php 
                    $status = strtolower($order['order_status']);
                    $statusIcon = '';
                    switch($status) {
                      case 'pending':
                        $statusIcon = '<i class="fas fa-clock"></i>';
                        break;
                      case 'processing':
                        $statusIcon = '<i class="fas fa-cog fa-spin"></i>';
                        break;
                      case 'shipped':
                        $statusIcon = '<i class="fas fa-truck"></i>';
                        break;
                      case 'delivered':
                      case 'completed':
                        $statusIcon = '<i class="fas fa-check-circle"></i>';
                        break;
                      default:
                        $statusIcon = '<i class="fas fa-info-circle"></i>';
                    }
                    echo $statusIcon . ' ' . htmlspecialchars(strtoupper($order['order_status']));
                    ?>
                  </span>
                </div>
                
                <div class="order-amount">
                  <span class="amount"><?= formatPrice($order['totalamt_php'], $current_currency) ?></span>
                  <?php if ($order['currency_name'] && $order['currency_name'] !== 'PHP'): ?>
                    <span class="currency-note">Original: <?= htmlspecialchars($order['currency_name']) ?></span>
                  <?php endif; ?>
                </div>
                
                <div class="order-progress">
                  <!-- Ordered Step -->
                  <div class="progress-step <?= (strtolower($order['order_status']) === 'delivered' || !empty($order['order_date'])) ? 'completed' : '' ?>">
                    <div class="step-dot">
                    </div>
                    <div class="step-label">
                      <span>Ordered</span>
                      <small><?= date('M j', strtotime($order['order_date'])) ?></small>
                    </div>
                  </div>

                  <!-- Processing Step -->
                  <div class="progress-step <?= (strtolower($order['order_status']) === 'delivered' || in_array(strtolower($order['order_status']), ['processing', 'shipped', 'completed'])) ? 'completed' : '' ?>">
                    <div class="step-dot">
                    </div>
                    <div class="step-label">
                      <span>Processing</span>
                    </div>
                  </div>

                  <!-- Shipped Step -->
                  <div class="progress-step <?= (strtolower($order['order_status']) === 'delivered' || in_array(strtolower($order['order_status']), ['shipped', 'completed'])) ? 'completed' : '' ?>">
                    <div class="step-dot">
                    </div>
                    <div class="step-label">
                      <span>Shipped</span>
                    </div>
                  </div>

                  <!-- Delivered Step -->
                  <div class="progress-step <?= strtolower($order['order_status']) === 'delivered' || strtolower($order['order_status']) === 'completed' ? 'completed' : '' ?>">
                    <div class="step-dot">
                    </div>
                    <div class="step-label">
                      <span>Delivered</span>
                    </div>
                  </div>
                </div>
              </div>
            <?php endwhile; ?>
          </div>
        <?php else: ?>
          <div class="empty-state">
            <div class="empty-icon"><i class="fas fa-box-open"></i></div>
            <h3>No orders found</h3>
            <p>You haven't placed any orders yet.</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  
  <script>
    function showTab(tab) {
      // Remove active class from all tab contents and buttons
      document.querySelectorAll(".tab-content").forEach(div => div.classList.remove("active"));
      document.querySelectorAll(".tab-btn").forEach(btn => btn.classList.remove("active"));
      
      // Add active class to selected tab content and button
      document.getElementById(tab).classList.add("active");
      event.target.classList.add("active");
    }
    
    function viewOrderDetails(orderId) {
      alert('View details for Order #' + orderId);
    }
    
    function confirmLogout() {
      return confirm('Are you sure you want to logout?');
    }

    function openModal() {
      document.getElementById("editProfileModal").style.display = "block";
    }

    function closeModal() {
      document.getElementById("editProfileModal").style.display = "none";
    }

    window.onclick = function (event) {
      const modal = document.getElementById("editProfileModal");
      if (event.target == modal) {
        modal.style.display = "none";
      }
    }
  </script>
</body>
</html>