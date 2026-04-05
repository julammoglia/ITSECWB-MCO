<?php
require_once 'includes/security/auth.php';
security_ensure_session_started();
security_handle_logout('index.php');

require_once 'includes/db.php'; 
require_once 'includes/security/input_validation.php';
include_once('currency_handler.php');
$current_currency = getCurrencyData($conn);
$userId = security_require_login('Login.php');

// Fetch user profile
$stmt = $conn->prepare("SELECT user_id, user_role, first_name, last_name, email, phone, password, profile_picture FROM users WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$userResult = $stmt->get_result()->fetch_assoc();

if (!$userResult) {
    security_logout('Login.php');
}

$profileAlert = null;

if (isset($_GET['error'])) {
    if ($_GET['error'] === 'unauthorized') {
        $profileAlert = ['type' => 'error', 'text' => 'Only customers can delete their account.'];
    } elseif ($_GET['error'] === 'notfound') {
        $profileAlert = ['type' => 'error', 'text' => 'User not found.'];
    } elseif ($_GET['error'] === 'csrf') {
        $profileAlert = ['type' => 'error', 'text' => 'The request could not be verified. Please try again.'];
    } elseif ($_GET['error'] === 'invalid_name') {
        $profileAlert = ['type' => 'error', 'text' => 'Invalid name. Only letters, spaces, hyphens, apostrophes, and dots are allowed.'];
    } elseif ($_GET['error'] === 'invalid_email') {
        $profileAlert = ['type' => 'error', 'text' => 'Enter a valid email address.'];
    } elseif ($_GET['error'] === 'invalid_phone') {
        $profileAlert = ['type' => 'error', 'text' => 'Enter a valid PH phone number in 09XXXXXXXXX format.'];
    } elseif ($_GET['error'] === 'email_exists') {
        $profileAlert = ['type' => 'error', 'text' => 'That email address is already being used by another account.'];
    } elseif ($_GET['error'] === 'filesize') {
        $profileAlert = ['type' => 'error', 'text' => 'Profile picture must be under 2MB.'];
    } elseif ($_GET['error'] === 'filetype') {
        $profileAlert = ['type' => 'error', 'text' => 'Only JPG, JPEG, and PNG images are allowed.'];
    }
} elseif (($_GET['profile'] ?? '') === 'updated') {
    $profileAlert = ['type' => 'success', 'text' => 'Profile updated successfully.'];
}

// Create full name from first_name and last_name
$fullName = trim($userResult['first_name'] . ' ' . $userResult['last_name']);
$displayPhone = security_phone_to_local_format($userResult['phone'] ?? '');

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

    /* Alert styles */
    .alert {
      padding: 12px 16px;
      border-radius: 8px;
      margin: 12px 0 0;
      display: flex;
      align-items: center;
      gap: 8px;
      position: relative;
      font-size: 0.95rem;
      opacity: 1;
      transform: translateY(0);
      transition: opacity 0.35s ease, transform 0.35s ease;
    }
    .alert.is-hiding {
      opacity: 0;
      transform: translateY(-8px);
    }
    .alert-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
    .alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
    .alert-warning { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
    .alert .alert-close {
      position: absolute;
      right: 10px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      font-size: 18px;
      cursor: pointer;
      color: inherit;
    }

    /* Arrange actions: Edit + Change on the left, Logout on the right */
    .form-actions {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
      flex-wrap: wrap;
    }
    .form-actions .action-group {
      display: flex;
      gap: 12px;
      align-items: center;
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
      <form action="edit_profile.php" method="post" enctype="multipart/form-data">
        <?php echo security_csrf_input(); ?>
        <div class="form-group">
          <label for="edit_first_name">First Name</label>
          <div class="input-wrapper">
            <div class="input-icon"><i class="fas fa-user"></i></div>
            <input type="text" id="edit_first_name" name="first_name" value="<?= htmlspecialchars($userResult['first_name']) ?>" placeholder="Enter your first name" maxlength="50" pattern="^[A-Za-zÀ-ÿ\s\-'.]{1,50}$" title="Only letters, spaces, hyphens, apostrophes, and dots allowed.">
          </div>
        </div>

        <div class="form-group">
          <label for="edit_last_name">Last Name</label>
          <div class="input-wrapper">
            <div class="input-icon"><i class="fas fa-user"></i></div>
            <input type="text" id="edit_last_name" name="last_name" value="<?= htmlspecialchars($userResult['last_name']) ?>" placeholder="Enter your last name" maxlength="50" pattern="^[A-Za-zÀ-ÿ\s\-'.]{1,50}$" title="Only letters, spaces, hyphens, apostrophes, and dots allowed.">
          </div>
        </div>

        <div class="form-group">
          <label for="edit_email">Email</label>
          <div class="input-wrapper">
            <div class="input-icon"><i class="fas fa-envelope"></i></div>
            <input type="email" id="edit_email" name="email" value="<?= htmlspecialchars($userResult['email']) ?>" placeholder="Enter your email" maxlength="45" inputmode="email" autocomplete="email" pattern="^[A-Za-z0-9][A-Za-z0-9._%+\-]*@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$" title="Enter a valid email address. The email cannot start with a dot." required>
          </div>
        </div>

        <div class="form-group">
          <label for="edit_phone">Phone Number</label>
          <div class="input-wrapper">
            <div class="input-icon"><i class="fas fa-phone"></i></div>
            <input type="tel" id="edit_phone" name="phone" value="<?= htmlspecialchars($displayPhone) ?>" placeholder="09XXXXXXXXX" maxlength="11" inputmode="numeric" autocomplete="tel-national" pattern="^09\d{9}$" title="Enter exactly 11 digits starting with 09." required>
          </div>
        </div>

        <div class="form-group">
          <label for="profile_picture">Profile Picture <small style="font-weight: 400; color: #9ca3af;">(max 2MB, JPG/JPEG/PNG)</small></label>
          <div class="input-wrapper">
            <div class="input-icon"><i class="fas fa-camera"></i></div>
            <input type="file" id="profile_picture" name="profile_picture" accept="image/jpeg,image/png">
          </div>
          <?php if (!empty($userResult['profile_picture'])): ?>
            <div style="margin-top: 0.5rem;">
              <label style="display: inline-flex; align-items: center; gap: 0.5rem; cursor: pointer; color: #ef4444; font-size: 0.85rem; font-weight: 400;">
                <input type="checkbox" name="delete_picture" value="1" style="width: auto; accent-color: #ef4444;">
                <i class="fas fa-trash-alt"></i> Remove current photo
              </label>
            </div>
          <?php endif; ?>
        </div>

        <div class="modal-actions">
          <button type="submit" class="save-btn">
            <i class="fas fa-save"></i> Save Changes
          </button>
          <button type="submit" class="delete-btn" formaction="delete_account.php" formmethod="post"
                  onclick="return confirm('Are you sure you want to delete your account?')">
            <i class="fas fa-trash-alt"></i> Delete Account
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Change Password Modal -->
<div id="changePasswordModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h2>Change Password</h2>
      <button class="close-btn" onclick="closeChangePasswordModal()"><i class="fas fa-times"></i></button>
    </div>

    <div class="modal-body">
      <?php if (isset($_GET['pwd'])): 
        $pwd = $_GET['pwd'];
        $messages = [
          'success' => ['type' => 'success', 'text' => 'Password updated successfully.'],
          'old_incorrect' => ['type' => 'error', 'text' => 'Current password is incorrect.'],
          'invalid' => ['type' => 'error', 'text' => 'New password is invalid or does not match confirmation.'],
          'same' => ['type' => 'warning', 'text' => 'New password must be different from the current password.'],
          'fail' => ['type' => 'error', 'text' => 'Failed to update password. Please try again.']
        ];
        $msg = $messages[$pwd] ?? null;
        if ($msg): ?>
          <div class="alert alert-<?= htmlspecialchars($msg['type']) ?>" data-auto-dismiss="5000">
            <i class="fas <?= $msg['type']==='success' ? 'fa-check-circle' : ($msg['type']==='warning' ? 'fa-exclamation-triangle' : 'fa-times-circle') ?>"></i>
            <?= htmlspecialchars($msg['text']) ?>
            <button class="alert-close" onclick="dismissAlert(this.parentElement)">&times;</button>
          </div>
      <?php endif; endif; ?>
      <form id="changePasswordForm" action="edit_profile.php" method="post">
        <?php echo security_csrf_input(); ?>
        <input type="hidden" name="action" value="change_password">

        <div class="form-group">
          <label for="old_password">Current Password</label>
          <div class="input-wrapper">
            <div class="input-icon"><i class="fas fa-lock"></i></div>
            <input type="password" id="old_password" name="old_password" placeholder="Enter your current password" required>
            <button type="button" class="toggle-password" onclick="togglePassword('old_password', this)" aria-label="Show password" style="position:absolute; right:10px; top:50%; transform: translateY(-50%); background:none; border:none; cursor:pointer; color:#6b7280;"><i class="far fa-eye"></i></button>
          </div>
        </div>

        <div class="form-group">
          <div class="input-wrapper">
            <div class="input-icon"><i class="fas fa-key"></i></div>
            <input type="password" id="new_password" name="new_password" placeholder="Enter your new password" minlength="12" required>
            <button type="button" class="toggle-password" onclick="togglePassword('new_password', this)" aria-label="Show password" style="position:absolute; right:10px; top:50%; transform: translateY(-50%); background:none; border:none; cursor:pointer; color:#6b7280;"><i class="far fa-eye"></i></button>
          </div>
        </div>

        <div class="form-group">
          <label for="confirm_password">Retype New Password</label>
          <div class="input-wrapper">
            <div class="input-icon"><i class="fas fa-key"></i></div>
            <input type="password" id="confirm_password" name="confirm_password" placeholder="Retype your new password" minlength="12" required>
            <button type="button" class="toggle-password" onclick="togglePassword('confirm_password', this)" aria-label="Show password" style="position:absolute; right:10px; top:50%; transform: translateY(-50%); background:none; border:none; cursor:pointer; color:#6b7280;"><i class="far fa-eye"></i></button>
          </div>
          <small id="passwordMismatch" style="display:none; color:#ef4444; margin-top:0.25rem;">New password and retype do not match.</small>
        </div>

        <div class="modal-actions">
          <button type="submit" class="save-btn">
            <i class="fas fa-save"></i> Confirm Change
          </button>
          <button type="button" class="delete-btn" onclick="closeChangePasswordModal()">
            <i class="fas fa-times"></i> Cancel
          </button>
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
      <?php if (!empty($userResult['profile_picture'])): ?>
        <img src="uploads/profile_pictures/<?= htmlspecialchars($userResult['profile_picture']) ?>" alt="Profile" class="avatar-large">
      <?php else: ?>
        <div class="avatar-large"><?= $userInitials ?></div>
      <?php endif; ?>
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
          <?php if ($profileAlert): ?>
            <div class="alert alert-<?= htmlspecialchars($profileAlert['type']) ?>" style="margin-bottom: 16px;" data-auto-dismiss="5000">
              <i class="fas <?= $profileAlert['type'] === 'success' ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
              <?= htmlspecialchars($profileAlert['text']) ?>
              <button class="alert-close" onclick="dismissAlert(this.parentElement)">&times;</button>
            </div>
          <?php endif; ?>
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

          <div class="form-row">
            <div class="form-group">
              <label for="phone">Phone Number</label>
              <input id="phone" type="tel" value="<?= htmlspecialchars($displayPhone) ?>" readonly>
            </div>
          </div>
                    
          <div class="form-actions">
            <div class="action-group">
              <button class="edit-profile-btn" onclick="openModal()">
                <i class="fas fa-edit btn-icon"></i>
                Edit Profile
              </button>

              <button class="logout-btn" onclick="openChangePasswordModal()">
                <i class="fas fa-key"></i> Change Password
              </button>
            </div>

            <div class="action-group">
              <form method="POST" style="display: inline;">
                <?php echo security_csrf_input(); ?>
                <button type="submit" name="logout" value="1" class="logout-btn" style="background: #ffffff; color: #ef4444; border: 1px solid #fecaca;"
                        onclick="return confirm('Are you sure you want to logout?');">
                  <i class="fas fa-sign-out-alt"></i> Logout
                </button>
              </form>
            </div>
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

    function openChangePasswordModal() {
      document.getElementById("changePasswordModal").style.display = "block";
    }

    function closeChangePasswordModal() {
      document.getElementById("changePasswordModal").style.display = "none";
    }

    // Auto-open Change Password modal if redirected with a pwd status
    (function() {
      const params = new URLSearchParams(window.location.search);
      if (params.has('pwd')) {
        openChangePasswordModal();
      }
    })();

    function togglePassword(inputId, btn) {
      const input = document.getElementById(inputId);
      const icon = btn.querySelector('i');
      if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
      } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
      }
    }

    function dismissAlert(element) {
      if (!element) return;
      element.classList.add('is-hiding');
      window.setTimeout(() => element.remove(), 350);
    }

    document.querySelectorAll('.alert[data-auto-dismiss]').forEach((alert) => {
      const delay = parseInt(alert.dataset.autoDismiss || '5000', 10);

      window.setTimeout(() => dismissAlert(alert), delay);
    });

    // Client-side validation for matching passwords
    const newPwd = document.getElementById('new_password');
    const confirmPwd = document.getElementById('confirm_password');
    const mismatchMsg = document.getElementById('passwordMismatch');
    const form = document.getElementById('changePasswordForm');

    function checkMatch() {
      if (!newPwd || !confirmPwd) return true;
      const bothFilled = newPwd.value.length > 0 && confirmPwd.value.length > 0;
      const match = newPwd.value === confirmPwd.value;
      if (mismatchMsg) {
        // Only show mismatch message if both fields have been filled
        mismatchMsg.style.display = (bothFilled && !match) ? 'block' : 'none';
      }
      // Only set validity when both are filled; otherwise keep neutral so form UX isn't aggressive
      confirmPwd.setCustomValidity(bothFilled && !match ? 'Passwords do not match' : '');
      return !bothFilled || match;
    }

    if (newPwd && confirmPwd) {
      newPwd.addEventListener('input', checkMatch);
      confirmPwd.addEventListener('input', checkMatch);
    }

    function sanitizeProfileEmailInput(input) {
      input.value = input.value.replace(/\s+/g, '').replace(/^\.+/, '');
    }

    function sanitizeProfileNameInput(input) {
      input.value = input.value
        .replace(/[^A-Za-zÀ-ÿ\s\-'.]/g, '')
        .replace(/\s{2,}/g, ' ')
        .replace(/^\s+/, '');
    }

    function sanitizeProfilePhoneInput(input) {
      input.value = input.value.replace(/\D/g, '').slice(0, 11);
    }

    function bindProfileSanitizer(selector, sanitizer) {
      document.querySelectorAll(selector).forEach((input) => {
        input.addEventListener('input', () => sanitizer(input));
        input.addEventListener('paste', () => {
          requestAnimationFrame(() => sanitizer(input));
        });
      });
    }

    bindProfileSanitizer('#edit_first_name, #edit_last_name', sanitizeProfileNameInput);
    bindProfileSanitizer('#edit_email', sanitizeProfileEmailInput);
    bindProfileSanitizer('#edit_phone', sanitizeProfilePhoneInput);

    if (form) {
      form.addEventListener('submit', function(e) {
        // Validate new/confirm match first
        if (!checkMatch()) {
          e.preventDefault();
          return;
        }
        // Prompt if new equals old password
        const oldPwd = document.getElementById('old_password');
        if (oldPwd && newPwd && oldPwd.value.length > 0 && newPwd.value.length > 0 && oldPwd.value === newPwd.value) {
          const proceed = confirm('New password must be different from the current password. Do you want to continue?');
          if (!proceed) {
            e.preventDefault();
            return;
          }
        }
      });
    }

    window.onclick = function (event) {
      const editModal = document.getElementById("editProfileModal");
      const changeModal = document.getElementById("changePasswordModal");
      if (event.target == editModal) {
        editModal.style.display = "none";
      }
      if (event.target == changeModal) {
        changeModal.style.display = "none";
      }
    }
  </script>
</body>
</html>
