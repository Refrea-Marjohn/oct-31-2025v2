<?php
// Get user profile image and email
$user_id = $_SESSION['user_id'];
$res = $conn->query("SELECT profile_image, email, phone_number FROM user_form WHERE id=$user_id");
$profile_image = '';
$user_email = '';
$user_phone = '';
if ($res && $row = $res->fetch_assoc()) {
    $profile_image = $row['profile_image'];
    $user_email = $row['email'];
    $user_phone = $row['phone_number'];
}
if (!$profile_image || !file_exists($profile_image)) {
    $profile_image = 'images/default-avatar.jpg';
}

// Get user display name
$user_name = '';
switch ($_SESSION['user_type']) {
    case 'admin':
        $user_name = $_SESSION['admin_name'] ?? 'Administrator';
        break;
    case 'attorney':
        $user_name = $_SESSION['attorney_name'] ?? 'Attorney';
        break;
    case 'employee':
        $user_name = $_SESSION['employee_name'] ?? 'Employee';
        break;
    case 'client':
        $user_name = $_SESSION['client_name'] ?? 'Client';
        break;
}

$user_title = ucfirst($_SESSION['user_type']);
?>

<style>
    /* Profile Arrow Animation */
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    /* Modal Slide In Animation */
    @keyframes modalSlideIn {
        from {
            transform: translateY(-50px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }
    
    /* Notification Slide Animations */
    @keyframes slideDown {
        from {
            transform: translate(-50%, -100%);
            opacity: 0;
        }
        to {
            transform: translate(-50%, 0);
            opacity: 1;
        }
    }
    
    @keyframes slideUp {
        from {
            transform: translate(-50%, 0);
            opacity: 1;
        }
        to {
            transform: translate(-50%, -100%);
            opacity: 0;
        }
    }
    
    /* OTP Expired Modal Button Hover Effects */
    #otpExpiredModal button:hover {
        transform: translateY(-2px);
    }
    
    #otpExpiredModal button:first-of-type:hover {
        background: #f8f9fa;
        border-color: #5a6268;
        color: #5a6268;
    }
    
    #otpExpiredModal button:last-of-type:hover {
        box-shadow: 0 6px 20px rgba(93, 14, 38, 0.4);
    }
    
    /* Invalid OTP Modal Button Hover Effects */
    #invalidOtpModal button:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(93, 14, 38, 0.4);
    }
    
    /* Send Code Button States */
    #sendCodeBtn:not(:disabled):hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(93, 14, 38, 0.5);
    }
    
    #sendCodeBtn:disabled {
        cursor: not-allowed !important;
        transform: none !important;
    }
    
    #sendCodeBtn:active:not(:disabled) {
        transform: translateY(0);
    }
    
    #profileArrow {
        transition: transform 0.3s ease, color 0.2s ease;
    }
    
    #profileArrow:hover {
        color: var(--accent-color) !important;
    }
    
    .profile-dropdown-content {
        display: none;
        position: absolute;
        top: 100%;
        right: 0;
        background: white;
        border: 2px solid var(--primary-color);
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 1000;
        min-width: 160px;
        animation: slideDown 0.2s ease-out;
    }
    
    .profile-dropdown-content.show {
        display: block;
    }
    
    .profile-dropdown-content a {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 15px;
        color: #333;
        text-decoration: none;
        transition: background-color 0.2s ease;
    }
    
    .profile-dropdown-content a:hover {
        background-color: #f8f9fa;
    }
    
    .profile-dropdown-content a.logout {
        color: #dc3545;
        border-top: 1px solid #e9ecef;
    }
    
    .profile-dropdown-content a.change-password {
        color: #8B1538;
        border-top: 1px solid #e9ecef;
    }
    
    .profile-dropdown-content a.change-password:hover {
        background-color: #f8f0f2;
    }
    
    .profile-dropdown-content a.logout:hover {
        background-color: #f8d7da;
    }
    
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
</style>

<!-- Enhanced Profile Header with Notifications -->
<div class="header" style="padding: 12px 20px; min-height: auto;">
    <div class="header-title">
        <h1 style="font-size: 1.4rem; margin: 0; line-height: 1.2;"><?= $page_title ?? 'Dashboard' ?></h1>
        <p style="font-size: 0.85rem; margin: 4px 0 0 0; opacity: 0.8;"><?= $page_subtitle ?? 'Overview of your activities' ?></p>
    </div>
    <div class="user-info" style="display: flex; align-items: center; gap: 12px;">
        <!-- Notifications Bell -->
        <div class="notifications-container" style="position: relative;">
            <button id="notificationsBtn" style="background: none; border: none; font-size: 16px; color: var(--primary-color); cursor: pointer; padding: 6px; transition: color 0.2s;" onmouseover="this.style.color='var(--accent-color)'" onmouseout="this.style.color='var(--primary-color)'">
                <i class="fas fa-bell"></i>
                <span id="notificationBadge" style="position: absolute; top: 0; right: 0; background: #dc3545; color: white; border-radius: 50%; width: 14px; height: 14px; font-size: 8px; display: flex; align-items: center; justify-content: center; font-weight: bold; display: none;">0</span>
            </button>
            
            <!-- Notifications Dropdown -->
            <div id="notificationsDropdown" style="position: absolute; top: 100%; right: 0; background: white; border: 2px solid #8B1538; border-radius: 8px; box-shadow: 0 6px 20px rgba(139, 21, 56, 0.2); width: 300px; max-height: 350px; overflow-y: auto; z-index: 3000; display: none;">
                <div style="padding: 12px; border-bottom: 1px solid #f3f4f6;">
                    <h3 style="margin: 0; font-size: 14px; color: #8B1538; font-weight: 600; display: flex; align-items: center; gap: 6px;">
                        <i class="fas fa-bell" style="color: #8B1538; font-size: 14px;"></i>
                        Notifications
                        <span id="notificationCount" style="background: #8B1538; color: white; border-radius: 8px; padding: 1px 6px; font-size: 10px; font-weight: bold;">0</span>
                    </h3>
                </div>
                <div id="notificationsList" style="padding: 8px;">
                    <!-- Notifications will be loaded here -->
                </div>
                <div style="padding: 12px; border-top: 2px solid #f3f4f6; text-align: center;">
                    <button onclick="markAllAsRead()" style="background: #8B1538; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 500; transition: all 0.2s;" onmouseover="this.style.background='#6B0F2A'" onmouseout="this.style.background='#8B1538'">Mark All as Read</button>
                </div>
            </div>
        </div>
        
        <!-- Profile Image with Dropdown -->
        <div class="profile-dropdown" style="display: flex; align-items: center; gap: 8px;">
            <div style="display: flex; align-items: center; gap: 4px;">
                <img src="<?= htmlspecialchars($profile_image) ?>" alt="<?= $user_title ?>" style="object-fit: cover; width: 32px; height: 32px; border-radius: 50%; border: 1.5px solid var(--primary-color); cursor: pointer;" onclick="toggleProfileDropdown()">
                <i id="profileArrow" class="fas fa-chevron-down" style="color: var(--primary-color); font-size: 10px; transition: transform 0.3s ease; cursor: pointer;" onclick="toggleProfileDropdown()"></i>
            </div>
            
            <div class="user-details">
                <h3 style="margin: 0; font-size: 13px; color: var(--primary-color); font-weight: 500;"><?= htmlspecialchars($user_name) ?></h3>
                <p style="margin: 0; font-size: 11px; color: var(--accent-color);"><?= $user_title ?></p>
            </div>
            
            <!-- Profile Dropdown Menu -->
            <div class="profile-dropdown-content" id="profileDropdown">
                <a href="#" onclick="editProfile()">
                    <i class="fas fa-user-edit"></i>
                    Edit Profile
                </a>
                <a href="#" onclick="changePassword()" class="change-password">
                    <i class="fas fa-key"></i>
                    Change Password
                </a>
                <a href="logout.php" class="logout">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </div>
        
        <!-- Edit Profile Modal -->
        <div id="editProfileModal" class="modal" style="display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); backdrop-filter: blur(5px); align-items: center; justify-content: center;">
            <div class="modal-content" style="background-color: white; padding: 0; border-radius: 12px; width: 90%; max-width: 450px; max-height: 85vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3); animation: modalSlideIn 0.3s ease-out;">
                <div class="modal-header" style="background: var(--primary-color); color: white; padding: 12px 16px; border-radius: 12px 12px 0 0; display: flex; justify-content: space-between; align-items: center;">
                    <h2 style="margin: 0; font-size: 1.2rem; font-weight: 600;">Edit Profile</h2>
                    <span class="close" onclick="closeEditProfileModal()" style="color: white; font-size: 24px; font-weight: bold; cursor: pointer; line-height: 1; transition: opacity 0.2s ease;">&times;</span>
                </div>
                <div class="modal-body" style="padding: 14px;">
                    <div class="profile-edit-container">
                        <form method="POST" enctype="multipart/form-data" class="profile-form" id="editProfileForm">
                            <div class="form-section" style="margin-bottom: 14px;">
                                <h3 style="color: var(--primary-color); margin-bottom: 10px; font-size: 1rem; border-bottom: 2px solid #f0f0f0; padding-bottom: 4px;">Profile Picture</h3>
                                <div class="profile-image-section" style="display: flex; align-items: center; gap: 12px;">
                                    <img src="<?= htmlspecialchars($profile_image) ?>" alt="Current Profile" id="currentProfileImage" class="current-profile-image" style="width: 70px; height: 70px; border-radius: 50%; object-fit: cover; border: 3px solid var(--primary-color); box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                                    <div class="image-upload" style="display: flex; flex-direction: column; gap: 6px;">
                                        <label for="profile_image" class="upload-btn" style="background: var(--primary-color); color: white; padding: 6px 12px; border-radius: 6px; cursor: pointer; text-align: center; transition: background 0.3s ease; display: inline-block; text-decoration: none; font-size: 0.8rem;">
                                            <i class="fas fa-camera"></i>
                                            Change Photo
                                        </label>
                                        <input type="file" id="profile_image" name="profile_image" accept="image/*" style="display: none;" onchange="previewImage(this)">
                                        <p class="upload-hint" style="color: #666; font-size: 0.8rem; margin: 0;">JPG, PNG, or GIF. Max 5MB.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section" style="margin-bottom: 14px;">
                                <h3 style="color: var(--primary-color); margin-bottom: 10px; font-size: 1rem; border-bottom: 2px solid #f0f0f0; padding-bottom: 4px;">Personal Information</h3>
                                <div class="form-group" style="margin-bottom: 10px;">
                                    <label for="name" style="display: block; margin-bottom: 4px; color: #333; font-weight: 500; font-size: 0.8rem;">Full Name</label>
                                    <input type="text" id="name" name="name" value="<?= htmlspecialchars($user_name) ?>" required style="width: 100%; padding: 6px 10px; border: 2px solid #e1e5e9; border-radius: 6px; font-size: 0.8rem; transition: border-color 0.3s ease;">
                                </div>
                                
                                <div class="form-group" style="margin-bottom: 10px;">
                                    <label for="email" style="display: block; margin-bottom: 4px; color: #333; font-weight: 500; font-size: 0.8rem;">Email Address</label>
                                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($user_email ?? '') ?>" readonly style="background-color: #f5f5f5; cursor: not-allowed; width: 100%; padding: 6px 10px; border: 2px solid #e1e5e9; border-radius: 6px; font-size: 0.8rem;">
                                    <small style="color: #666; font-size: 12px;">Email address cannot be changed for security reasons.</small>
                                </div>
                                
                                <div class="form-group" style="margin-bottom: 10px;">
                                    <label for="phone_number" style="display: block; margin-bottom: 4px; color: #333; font-weight: 500; font-size: 0.8rem;">Phone Number</label>
                                    <input type="tel" id="phone_number" name="phone_number" value="<?= htmlspecialchars($user_phone ?? '') ?>" placeholder="09123456789" maxlength="11" style="width: 100%; padding: 6px 10px; border: 2px solid #e1e5e9; border-radius: 6px; font-size: 0.8rem; transition: border-color 0.3s ease;" oninput="validatePhoneNumber(this)">
                                    <small style="color: #666; font-size: 0.7rem;">Must be exactly 11 digits starting with 09</small>
                                </div>
                            </div>

                            <div class="form-actions" style="display: flex; gap: 8px; justify-content: flex-end; margin-top: 14px; padding-top: 12px; border-top: 1px solid #e1e5e9;">
                                <button type="button" class="btn btn-secondary" onclick="closeEditProfileModal()" style="padding: 6px 12px; border-radius: 6px; text-decoration: none; font-weight: 500; cursor: pointer; border: none; display: inline-flex; align-items: center; gap: 4px; transition: all 0.3s ease; font-size: 0.8rem; background: #6c757d; color: white;">Cancel</button>
                                <button type="button" class="btn btn-primary" onclick="verifyPasswordBeforeSave()" style="padding: 6px 12px; border-radius: 6px; text-decoration: none; font-weight: 500; cursor: pointer; border: none; display: inline-flex; align-items: center; gap: 4px; transition: all 0.3s ease; font-size: 0.8rem; background: var(--primary-color); color: white;">
                                    <i class="fas fa-save"></i>
                                    Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                        </div>
    </div>
</div>

<!-- Password Verification Modal -->
<div id="passwordVerificationModal" class="modal" style="display: none; position: fixed; z-index: 2001; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); backdrop-filter: blur(5px); align-items: center; justify-content: center;">
    <div class="modal-content" style="background-color: white; padding: 0; border-radius: 12px; width: 90%; max-width: 400px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);">
        <div class="modal-header" style="background: var(--primary-color); color: white; padding: 12px 16px; border-radius: 12px 12px 0 0; display: flex; justify-content: space-between; align-items: center;">
            <h2 style="margin: 0; font-size: 1.2rem; font-weight: 600;">Security Verification</h2>
            <span class="close" onclick="closePasswordVerificationModal()" style="color: white; font-size: 24px; font-weight: bold; cursor: pointer; line-height: 1; transition: opacity 0.2s ease;">&times;</span>
        </div>
        <div class="modal-body" style="padding: 20px;">
            <div style="text-align: center; margin-bottom: 20px;">
                <i class="fas fa-shield-alt" style="font-size: 48px; color: var(--primary-color); margin-bottom: 10px;"></i>
                <h3 style="margin: 0; color: #333; font-size: 1.1rem;">Verify Your Identity</h3>
                <p style="margin: 10px 0 0 0; color: #666; font-size: 0.9rem;">Please enter your current password to save profile changes</p>
            </div>
            
            <form id="passwordVerificationForm">
                <div class="form-group" style="margin-bottom: 15px;">
                    <label for="current_password" style="display: block; margin-bottom: 5px; color: #333; font-weight: 500; font-size: 0.9rem;">Current Password</label>
                    <input type="password" id="current_password" name="current_password" required style="width: 100%; padding: 10px 12px; border: 2px solid #e1e5e9; border-radius: 6px; font-size: 0.9rem; transition: border-color 0.3s ease;">
                </div>
                
                <div class="form-actions" style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closePasswordVerificationModal()" style="padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 500; cursor: pointer; border: none; display: inline-flex; align-items: center; gap: 5px; transition: all 0.3s ease; font-size: 0.9rem; background: #6c757d; color: white;">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 500; cursor: pointer; border: none; display: inline-flex; align-items: center; gap: 5px; transition: all 0.3s ease; font-size: 0.9rem; background: var(--primary-color); color: white;">
                        <i class="fas fa-check"></i>
                        Verify & Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Change Password Modal -->
<div id="changePasswordModal" class="modal" style="display: none; position: fixed; z-index: 2002; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); backdrop-filter: blur(5px); align-items: center; justify-content: center;">
    <div class="modal-content" style="background-color: white; padding: 0; border-radius: 12px; width: 90%; max-width: 450px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);">
        <div class="modal-header" style="background: var(--primary-color); color: white; padding: 12px 16px; border-radius: 12px 12px 0 0; display: flex; justify-content: space-between; align-items: center;">
            <h2 style="margin: 0; font-size: 1.2rem; font-weight: 600;">Change Password</h2>
        </div>
        <div class="modal-body" style="padding: 20px;">
            <div style="text-align: center; margin-bottom: 20px;">
                <i class="fas fa-key" style="font-size: 48px; color: var(--primary-color); margin-bottom: 10px;"></i>
                <h3 style="margin: 0; color: #333; font-size: 1.1rem;">Secure Password Change</h3>
                <p style="margin: 10px 0 0 0; color: #666; font-size: 0.9rem;">We'll send a verification code to your email address</p>
            </div>
            
            <form id="changePasswordForm">
                <div class="form-group" style="margin-bottom: 15px;">
                    <label for="change_password_email" style="display: block; margin-bottom: 5px; color: #333; font-weight: 500; font-size: 0.9rem;">Email Address</label>
                    <input type="email" id="change_password_email" name="email" value="<?= htmlspecialchars($user_email ?? '') ?>" readonly style="background-color: #f5f5f5; cursor: not-allowed; width: 100%; padding: 10px 12px; border: 2px solid #e1e5e9; border-radius: 6px; font-size: 0.9rem;">
                    <small style="color: #666; font-size: 12px;">Verification code will be sent to this email</small>
                </div>
                
                <div class="form-actions" style="display: flex; gap: 10px; justify-content: center; margin-top: 20px;">
                    <button type="submit" id="sendCodeBtn" class="btn btn-primary" style="padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; cursor: pointer; border: none; display: inline-flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.3s ease; font-size: 1rem; background: linear-gradient(135deg, #5D0E26, #8B1538); color: white; width: 100%; box-shadow: 0 4px 12px rgba(93, 14, 38, 0.3);">
                        <i class="fas fa-paper-plane" id="sendCodeIcon"></i>
                        <span id="sendCodeText">Send Verification Code</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Password Change Verification Modal -->
<div id="passwordChangeVerificationModal" class="modal" style="display: none; position: fixed; z-index: 2003; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); backdrop-filter: blur(5px); align-items: center; justify-content: center;">
    <div class="modal-content" style="background-color: white; padding: 0; border-radius: 12px; width: 90%; max-width: 450px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);">
        <div class="modal-header" style="background: var(--primary-color); color: white; padding: 12px 16px; border-radius: 12px 12px 0 0; display: flex; justify-content: space-between; align-items: center;">
            <h2 style="margin: 0; font-size: 1.2rem; font-weight: 600; width: 100%; text-align: center;" id="modalTitle">Verify Code</h2>
        </div>
        <div class="modal-body" style="padding: 20px;">
            <!-- OTP Verification Stage -->
            <div id="otpVerificationStage">
                <div style="text-align: center; margin-bottom: 20px;">
                    <i class="fas fa-shield-alt" style="font-size: 48px; color: var(--primary-color); margin-bottom: 10px;"></i>
                    <h3 style="margin: 0; color: #333; font-size: 1.1rem;">Enter Verification Code</h3>
                    <p style="margin: 10px 0 0 0; color: #666; font-size: 0.9rem;">Check your email for the verification code</p>
                    <p id="countdownTimer" style="margin: 5px 0 0 0; color: #8B1538; font-size: 0.9rem; font-weight: 600;">Code expires in: <span id="timeLeft">60</span> seconds</p>
                </div>
                
                <form id="otpVerificationForm" onsubmit="verifyOTP(event)">
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label for="verification_code" style="display: block; margin-bottom: 5px; color: #333; font-weight: 500; font-size: 0.9rem; width: 100%; text-align: center;">Verification Code</label>
                        <input type="text" id="verification_code" name="verification_code" required maxlength="6" style="width: 100%; padding: 10px 12px; border: 2px solid #e1e5e9; border-radius: 6px; font-size: 0.9rem; transition: border-color 0.3s ease; text-align: center; letter-spacing: 2px;" placeholder="000000" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                    </div>
                    
                    <div class="form-actions" style="display: flex; flex-direction: column; gap: 12px; margin-top: 20px;">
                        <button type="submit" class="btn btn-primary" style="padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 600; cursor: pointer; border: none; display: inline-flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.3s ease; font-size: 1rem; background: linear-gradient(135deg, #5D0E26, #8B1538); color: white; width: 100%; box-shadow: 0 4px 12px rgba(93, 14, 38, 0.3);">
                            <i class="fas fa-check"></i>
                            Verify Code
                        </button>
                        
                        <button type="button" id="resendOtpBtn" onclick="resendOtpCode()" style="padding: 10px 24px; border: 2px solid #5D0E26; background: white; color: #5D0E26; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; font-size: 0.95rem; display: inline-flex; align-items: center; justify-content: center; gap: 8px; width: 100%;" onmouseover="this.style.background='#5D0E26'; this.style.color='white'" onmouseout="this.style.background='white'; this.style.color='#5D0E26'">
                            <i class="fas fa-redo" id="resendIcon"></i>
                            <span id="resendText">Resend OTP</span>
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Password Change Stage (initially hidden) -->
            <div id="passwordChangeStage" style="display: none;">
                <div style="text-align: center; margin-bottom: 20px;">
                    <i class="fas fa-key" style="font-size: 48px; color: #10b981; margin-bottom: 10px;"></i>
                    <h3 style="margin: 0; color: #333; font-size: 1.1rem;">Set New Password</h3>
                    <p style="margin: 10px 0 0 0; color: #666; font-size: 0.9rem;">Create a strong password for your account</p>
                </div>
                
                <form id="passwordChangeVerificationForm">
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label for="new_password" style="display: block; margin-bottom: 5px; color: #333; font-weight: 500; font-size: 0.9rem;">New Password</label>
                        <div style="position: relative;">
                            <input type="password" id="new_password" name="new_password" required style="width: 100%; padding: 10px 40px 10px 12px; border: 2px solid #e1e5e9; border-radius: 6px; font-size: 0.9rem; transition: border-color 0.3s ease;" oninput="checkPasswordStrength(); checkPasswordMatch();" onkeyup="this.value = this.value.replace(/\s/g, '')">
                            <i class="fas fa-eye" id="toggleNewPassword" onclick="togglePasswordVisibility('new_password', 'toggleNewPassword')" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #666; font-size: 14px;"></i>
                        </div>
                        
                        <!-- Password Strength Indicator -->
                        <div id="passwordStrengthIndicator" style="display: none; margin-top: 10px; padding: 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;">
                            <div style="margin-bottom: 12px;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                    <span style="font-size: 11px; font-weight: 600; color: #64748b;">Password Strength:</span>
                                    <span id="strengthText" style="font-size: 11px; font-weight: 600; color: #64748b;">Weak</span>
                                </div>
                                <div style="width: 100%; height: 4px; background: #e2e8f0; border-radius: 2px; overflow: hidden;">
                                    <div id="strengthBar" style="width: 0%; height: 100%; background: #ef4444; transition: all 0.3s ease; border-radius: 2px;"></div>
                                </div>
                            </div>
                            <div style="font-size: 11px;">
                                <div style="margin-bottom: 4px;">
                                    <span id="lengthCheck" style="color: #ef4444;">✗</span>
                                    <span style="margin-left: 6px; color: #64748b;">At least 8 characters</span>
                                </div>
                                <div style="margin-bottom: 4px;">
                                    <span id="uppercaseCheck" style="color: #ef4444;">✗</span>
                                    <span style="margin-left: 6px; color: #64748b;">Uppercase letter</span>
                                </div>
                                <div style="margin-bottom: 4px;">
                                    <span id="lowercaseCheck" style="color: #ef4444;">✗</span>
                                    <span style="margin-left: 6px; color: #64748b;">Lowercase letter</span>
                                </div>
                                <div style="margin-bottom: 4px;">
                                    <span id="numberCheck" style="color: #ef4444;">✗</span>
                                    <span style="margin-left: 6px; color: #64748b;">Number</span>
                                </div>
                                <div>
                                    <span id="specialCheck" style="color: #ef4444;">✗</span>
                                    <span style="margin-left: 6px; color: #64748b;">Special char (!@#$%^&*()-_+={}[]:";'<>.,?/\|~)</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label for="confirm_new_password" style="display: block; margin-bottom: 5px; color: #333; font-weight: 500; font-size: 0.9rem;">Confirm New Password</label>
                        <div style="position: relative;">
                            <input type="password" id="confirm_new_password" name="confirm_new_password" required style="width: 100%; padding: 10px 40px 10px 12px; border: 2px solid #e1e5e9; border-radius: 6px; font-size: 0.9rem; transition: border-color 0.3s ease;" onkeyup="this.value = this.value.replace(/\s/g, ''); checkPasswordMatch();">
                            <i class="fas fa-eye" id="toggleConfirmPassword" onclick="togglePasswordVisibility('confirm_new_password', 'toggleConfirmPassword')" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #666; font-size: 14px;"></i>
                        </div>
                        <div id="passwordMatchIndicator" style="margin-top: 5px; font-size: 12px; display: none;">
                            <span id="passwordMatchText" style="color: #ef4444;">Passwords do not match</span>
                        </div>
                    </div>
                    
                    <div class="form-actions" style="display: flex; gap: 10px; justify-content: center; margin-top: 20px;">
                        <button type="submit" class="btn btn-primary" style="padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 500; cursor: pointer; border: none; display: inline-flex; align-items: center; gap: 5px; transition: all 0.3s ease; font-size: 0.9rem; background: var(--primary-color); color: white; width: 50%;">
                            <i class="fas fa-check"></i>
                            Change Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Verification Code Sent Success Modal -->
<div id="verificationCodeSentModal" class="modal" style="display: none; position: fixed; z-index: 2004; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); backdrop-filter: blur(5px); align-items: center; justify-content: center;">
    <div class="modal-content" style="background-color: white; padding: 0; border-radius: 12px; width: 90%; max-width: 400px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);">
        <div class="modal-header" style="background: linear-gradient(135deg, #5D0E26, #8B1538); color: white; padding: 20px; border-radius: 12px 12px 0 0; text-align: left;">
            <div style="display: flex; align-items: center; gap: 15px;">
                <div style="width: 50px; height: 50px; background: rgba(255, 255, 255, 0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-check" style="font-size: 20px; color: white;"></i>
                </div>
                <div>
                    <h2 style="margin: 0; font-size: 1.3rem; font-weight: 600;">Verification Code Sent!</h2>
                    <p style="margin: 5px 0 0 0; font-size: 0.9rem; opacity: 0.9;">Check your email for the code</p>
                </div>
            </div>
        </div>
        <div class="modal-body" style="padding: 25px; text-align: center;">
            <p style="color: #374151; font-size: 1rem; margin: 0 0 20px 0; line-height: 1.5;">
                We've sent a 6-digit verification code to your email address. Please check your inbox and enter the code to continue.
            </p>
            <div style="background: #f0f9ff; border: 1px solid #0ea5e9; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
                <p style="color: #0369a1; font-size: 0.9rem; margin: 0; font-weight: 500;">
                    <i class="fas fa-info-circle" style="margin-right: 8px;"></i>
                    The code will expire in 60 seconds
                </p>
            </div>
            <button onclick="closeVerificationCodeSentModal()" style="background: linear-gradient(135deg, #5D0E26, #8B1538); color: white; border: none; padding: 12px 30px; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(93, 14, 38, 0.3);" onmouseover="this.style.background='linear-gradient(135deg, #8B1538, #5D0E26)'" onmouseout="this.style.background='linear-gradient(135deg, #5D0E26, #8B1538)'">
                <i class="fas fa-check" style="margin-right: 8px;"></i>
                Got It
            </button>
        </div>
    </div>
</div>

<!-- Password Change Success Modal -->
<div id="passwordChangeSuccessModal" class="modal" style="display: none; position: fixed; z-index: 2005; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); backdrop-filter: blur(5px); align-items: center; justify-content: center;">
    <div class="modal-content" style="background-color: white; padding: 0; border-radius: 12px; width: 90%; max-width: 450px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);">
        <div class="modal-header" style="background: linear-gradient(135deg, #5D0E26, #8B1538); color: white; padding: 20px; border-radius: 12px 12px 0 0; text-align: left;">
            <div style="display: flex; align-items: center; gap: 15px;">
                <div style="width: 50px; height: 50px; background: rgba(255, 255, 255, 0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-check-circle" style="font-size: 20px; color: white;"></i>
                </div>
                <div>
                    <h2 style="margin: 0; font-size: 1.3rem; font-weight: 600;">Password Changed!</h2>
                    <p style="margin: 5px 0 0 0; font-size: 0.9rem; opacity: 0.9;">Your password has been updated successfully</p>
                </div>
            </div>
        </div>
        <div class="modal-body" style="padding: 25px; text-align: center;">
            <div style="background: #f0f9ff; border: 1px solid #0ea5e9; border-radius: 8px; padding: 20px; margin-bottom: 25px;">
                <i class="fas fa-info-circle" style="font-size: 24px; color: #0ea5e9; margin-bottom: 10px;"></i>
                <p style="color: #0369a1; font-size: 1rem; margin: 0; font-weight: 500;">
                    For security reasons, you need to login again with your new password.
                </p>
            </div>
            <p style="color: #374151; font-size: 0.9rem; margin: 0 0 25px 0; line-height: 1.5;">
                You will be redirected to the login page automatically.
            </p>
            <button onclick="redirectToLogin()" style="background: linear-gradient(135deg, #5D0E26, #8B1538); color: white; border: none; padding: 12px 30px; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(93, 14, 38, 0.3);" onmouseover="this.style.background='linear-gradient(135deg, #8B1538, #5D0E26)'" onmouseout="this.style.background='linear-gradient(135deg, #5D0E26, #8B1538)'">
                <i class="fas fa-sign-in-alt" style="margin-right: 8px;"></i>
                Go to Login
            </button>
        </div>
    </div>
</div>

<!-- OTP Expired Modal -->
<div id="otpExpiredModal" style="display: none; position: fixed; z-index: 999999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.85); backdrop-filter: blur(8px); align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 16px; width: 90%; max-width: 420px; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3); overflow: hidden; animation: modalSlideIn 0.3s ease-out;">
        <!-- Header -->
        <div style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; padding: 20px 24px; display: flex; align-items: center; gap: 12px;">
            <i class="fas fa-clock" style="font-size: 24px;"></i>
            <h3 style="margin: 0; font-size: 1.3rem; font-weight: 600;">Verification Code Expired</h3>
        </div>
        
        <!-- Body -->
        <div style="padding: 24px;">
            <p style="margin: 0 0 16px 0; font-size: 1.05rem; color: #333; line-height: 1.6; font-weight: 500;">
                The OTP has expired.
            </p>
            <p style="margin: 0; font-size: 0.95rem; color: #666; line-height: 1.6; background: #fff3cd; padding: 12px; border-radius: 8px; border-left: 4px solid #f59e0b;">
                <i class="fas fa-lightbulb" style="color: #f59e0b; margin-right: 8px;"></i>
                Click <strong>"Resend OTP"</strong> below to receive a new verification code.
            </p>
        </div>
        
        <!-- Footer -->
        <div style="padding: 16px 24px; background: #f8f9fa; display: flex; gap: 12px; justify-content: flex-end; border-top: 1px solid #e9ecef;">
            <button onclick="closeOtpExpiredModal()" style="padding: 10px 24px; border: 1px solid #6c757d; background: white; color: #6c757d; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s ease; font-size: 0.95rem;">
                <i class="fas fa-times" style="margin-right: 6px;"></i>Close
            </button>
            <button onclick="resendOtpFromExpiredModal()" style="padding: 10px 24px; border: none; background: linear-gradient(135deg, #5D0E26 0%, #8B1538 100%); color: white; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s ease; box-shadow: 0 4px 12px rgba(93, 14, 38, 0.3); font-size: 0.95rem;">
                <i class="fas fa-paper-plane" style="margin-right: 6px;"></i>Resend OTP
            </button>
        </div>
    </div>
</div>

<!-- Invalid OTP Modal -->
<div id="invalidOtpModal" style="display: none; position: fixed; z-index: 999999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.85); backdrop-filter: blur(8px); align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 16px; width: 90%; max-width: 400px; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3); overflow: hidden; animation: modalSlideIn 0.3s ease-out;">
        <!-- Header -->
        <div style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; padding: 20px 24px; display: flex; align-items: center; gap: 12px;">
            <i class="fas fa-times-circle" style="font-size: 24px;"></i>
            <h3 style="margin: 0; font-size: 1.3rem; font-weight: 600;">Invalid Verification Code</h3>
        </div>
        
        <!-- Body -->
        <div style="padding: 24px;">
            <p style="margin: 0 0 12px 0; font-size: 1rem; color: #333; line-height: 1.6;" id="invalidOtpMessage">
                The verification code you entered is incorrect.
            </p>
            <p style="margin: 0; font-size: 0.9rem; color: #666; line-height: 1.6;">
                <i class="fas fa-info-circle" style="color: #5D0E26; margin-right: 6px;"></i>
                Please check and try again, or request a new code.
            </p>
        </div>
        
        <!-- Footer -->
        <div style="padding: 16px 24px; background: #f8f9fa; display: flex; gap: 12px; justify-content: flex-end; border-top: 1px solid #e9ecef;">
            <button onclick="closeInvalidOtpModal()" style="padding: 10px 24px; border: none; background: linear-gradient(135deg, #5D0E26 0%, #8B1538 100%); color: white; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s ease; box-shadow: 0 4px 12px rgba(93, 14, 38, 0.3); font-size: 0.95rem;">
                <i class="fas fa-check" style="margin-right: 6px;"></i>Try Again
            </button>
        </div>
    </div>
</div>

<!-- Move modal and notifications outside header -->
<script>
// Move modal and notifications to body level for proper layering
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('editProfileModal');
    const passwordVerificationModal = document.getElementById('passwordVerificationModal');
    const changePasswordModal = document.getElementById('changePasswordModal');
    const passwordChangeVerificationModal = document.getElementById('passwordChangeVerificationModal');
    const verificationCodeSentModal = document.getElementById('verificationCodeSentModal');
    const passwordChangeSuccessModal = document.getElementById('passwordChangeSuccessModal');
    const otpExpiredModal = document.getElementById('otpExpiredModal');
    const invalidOtpModal = document.getElementById('invalidOtpModal');
    const notificationsDropdown = document.getElementById('notificationsDropdown');
    
    if (modal && modal.parentElement !== document.body) {
        document.body.appendChild(modal);
    }
    
    if (passwordVerificationModal && passwordVerificationModal.parentElement !== document.body) {
        document.body.appendChild(passwordVerificationModal);
    }
    
    if (changePasswordModal && changePasswordModal.parentElement !== document.body) {
        document.body.appendChild(changePasswordModal);
    }
    
    if (passwordChangeVerificationModal && passwordChangeVerificationModal.parentElement !== document.body) {
        document.body.appendChild(passwordChangeVerificationModal);
    }
    
    if (verificationCodeSentModal && verificationCodeSentModal.parentElement !== document.body) {
        document.body.appendChild(verificationCodeSentModal);
    }
    
    if (passwordChangeSuccessModal && passwordChangeSuccessModal.parentElement !== document.body) {
        document.body.appendChild(passwordChangeSuccessModal);
    }
    
    // IMPORTANT: Append alert modals LAST so they have highest DOM order
    if (otpExpiredModal && otpExpiredModal.parentElement !== document.body) {
        document.body.appendChild(otpExpiredModal);
    }
    
    if (invalidOtpModal && invalidOtpModal.parentElement !== document.body) {
        document.body.appendChild(invalidOtpModal);
    }
    
    if (notificationsDropdown && notificationsDropdown.parentElement !== document.body) {
        document.body.appendChild(notificationsDropdown);
        
        // Update notifications dropdown positioning to work with body
        const notificationsBtn = document.getElementById('notificationsBtn');
        if (notificationsBtn) {
            notificationsBtn.addEventListener('click', function() {
                const dropdown = document.getElementById('notificationsDropdown');
                const btnRect = notificationsBtn.getBoundingClientRect();
                
                // Position dropdown relative to button
                dropdown.style.position = 'fixed';
                dropdown.style.top = (btnRect.bottom + 5) + 'px';
                dropdown.style.right = (window.innerWidth - btnRect.right) + 'px';
                dropdown.style.zIndex = '9999';
                
                // Toggle visibility
                const isVisible = dropdown.style.display === 'block';
                dropdown.style.display = isVisible ? 'none' : 'block';
                
                if (!isVisible) {
                    loadNotifications();
                }
            });
        }
    }
});
</script>

<script>
        function toggleProfileDropdown() {
            const dropdown = document.getElementById('profileDropdown');
            const arrow = document.getElementById('profileArrow');
            dropdown.classList.toggle('show');
            
            // Animate arrow rotation
            if (dropdown.classList.contains('show')) {
                arrow.style.transform = 'rotate(180deg)';
            } else {
                arrow.style.transform = 'rotate(0deg)';
            }
        }
        
        function editProfile() {
            const arrow = document.getElementById('profileArrow');
            const dropdown = document.getElementById('profileDropdown');
            
            // Start arrow animation
            arrow.style.animation = 'spin 1s linear infinite';
            
            // Close dropdown when opening modal
            dropdown.classList.remove('show');
            arrow.style.transform = 'rotate(0deg)';
            
            // Show modal
            document.getElementById('editProfileModal').style.display = 'flex';
            
            // Stop arrow animation after modal loads
            setTimeout(() => {
                arrow.style.animation = 'none';
            }, 1000);
        }

        function closeEditProfileModal() {
            const arrow = document.getElementById('profileArrow');
            document.getElementById('editProfileModal').style.display = 'none';
            
            // Reset arrow animation
            arrow.style.animation = 'none';
            arrow.style.transform = 'rotate(0deg)';
        }
        
        // Close dropdown when clicking outside
        window.onclick = function(event) {
            if (!event.target.matches('img') && !event.target.closest('.profile-dropdown')) {
                const dropdowns = document.getElementsByClassName('profile-dropdown-content');
                const arrow = document.getElementById('profileArrow');
                
                for (let dropdown of dropdowns) {
                    if (dropdown.classList.contains('show')) {
                        dropdown.classList.remove('show');
                        // Reset arrow rotation when closing dropdown
                        arrow.style.transform = 'rotate(0deg)';
                    }
                }
            }
            
            // Modal close on outside click removed - users must use buttons to close
        }

        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('currentProfileImage').src = e.target.result;
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Handle form submission - REMOVED since we now use password verification
        // The form submission is now handled by verifyPasswordAndSave() function

        // Password verification functions
        function verifyPasswordBeforeSave() {
            // Validate phone number first
            const phoneInput = document.getElementById('phone_number');
            const phoneNumber = phoneInput.value.trim();
            
            if (phoneNumber && !/^09\d{9}$/.test(phoneNumber)) {
                showInvalidOtpModal('Phone number must be exactly 11 digits starting with 09 (e.g., 09123456789)');
                phoneInput.focus();
                return;
            }
            
            // Show confirmation first
            if (confirm('Are you sure you want to save these changes to your profile?')) {
                // Hide the edit profile modal
                document.getElementById('editProfileModal').style.display = 'none';
                // Show the password verification modal
                document.getElementById('passwordVerificationModal').style.display = 'flex';
            }
        }

        function closePasswordVerificationModal() {
            document.getElementById('passwordVerificationModal').style.display = 'none';
            document.getElementById('current_password').value = '';
            // Show the edit profile modal again
            document.getElementById('editProfileModal').style.display = 'flex';
        }

        // Phone number validation function
        function validatePhoneNumber(input) {
            let value = input.value.replace(/\D/g, ''); // Remove non-digits
            if (value.length > 11) {
                value = value.substring(0, 11);
            }
            input.value = value;
            
            // Visual feedback
            if (value.length === 11 && /^09\d{9}$/.test(value)) {
                input.style.borderColor = '#28a745'; // Green for valid
            } else if (value.length > 0) {
                input.style.borderColor = '#dc3545'; // Red for invalid
            } else {
                input.style.borderColor = '#e1e5e9'; // Default
            }
        }

        // Handle password verification form submission
        document.getElementById('passwordVerificationForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const password = document.getElementById('current_password').value;
            if (!password) {
                showInvalidOtpModal('Please enter your current password');
                return;
            }

            // Verify password and save profile
            verifyPasswordAndSave(password);
        });

        function verifyPasswordAndSave(password) {
            const formData = new FormData(document.getElementById('editProfileForm'));
            formData.append('current_password', password);
            formData.append('security_token', generateSecurityToken());

            fetch('update_profile.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccessModal('Profile updated successfully!');
                    closePasswordVerificationModal();
                    closeEditProfileModal();
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showInvalidOtpModal(data.message);
                    if (data.message.includes('password')) {
                        document.getElementById('current_password').value = '';
                        document.getElementById('current_password').focus();
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showInvalidOtpModal('An error occurred while updating the profile.');
            });
        }

        function generateSecurityToken() {
            return Date.now().toString(36) + Math.random().toString(36).substr(2);
        }

        // Change Password Functions
        function changePassword() {
            const arrow = document.getElementById('profileArrow');
            const dropdown = document.getElementById('profileDropdown');
            
            // Start arrow animation
            arrow.style.animation = 'spin 1s linear infinite';
            
            // Close dropdown when opening modal
            dropdown.classList.remove('show');
            arrow.style.transform = 'rotate(0deg)';
            
            // Show modal
            document.getElementById('changePasswordModal').style.display = 'flex';
            
            // Stop arrow animation after modal loads
            setTimeout(() => {
                arrow.style.animation = 'none';
            }, 1000);
        }

        function closeChangePasswordModal() {
            const arrow = document.getElementById('profileArrow');
            document.getElementById('changePasswordModal').style.display = 'none';
            
            // Reset arrow animation
            arrow.style.animation = 'none';
            arrow.style.transform = 'rotate(0deg)';
        }

        function closeVerificationCodeSentModal() {
            document.getElementById('verificationCodeSentModal').style.display = 'none';
        }

        function redirectToLogin() {
            document.getElementById('passwordChangeSuccessModal').style.display = 'none';
            window.location.href = 'logout.php';
        }

        function closePasswordChangeVerificationModal() {
            document.getElementById('passwordChangeVerificationModal').style.display = 'none';
            document.getElementById('verification_code').value = '';
            document.getElementById('new_password').value = '';
            document.getElementById('confirm_new_password').value = '';
            
            // Reset to OTP stage
            document.getElementById('otpVerificationStage').style.display = 'block';
            document.getElementById('passwordChangeStage').style.display = 'none';
            document.getElementById('modalTitle').textContent = 'Verify Code';
            
            // Hide password strength indicator
            const indicator = document.getElementById('passwordStrengthIndicator');
            if (indicator) {
                indicator.style.display = 'none';
            }
            
            // Hide password match indicator
            const matchIndicator = document.getElementById('passwordMatchIndicator');
            if (matchIndicator) {
                matchIndicator.style.display = 'none';
            }
            
            // Clear countdown timer
            if (window.passwordChangeTimer) {
                clearInterval(window.passwordChangeTimer);
                window.passwordChangeTimer = null;
            }
            
            // Reset countdown display
            const countdownElement = document.getElementById('countdownTimer');
            if (countdownElement) {
                countdownElement.innerHTML = 'Code expires in: <span id="timeLeft">60</span> seconds';
                countdownElement.style.color = '#8B1538';
            }
        }

        // Password Strength Checker Function
        function checkPasswordStrength() {
            const password = document.getElementById('new_password').value;
            const indicator = document.getElementById('passwordStrengthIndicator');
            const strengthBar = document.getElementById('strengthBar');
            const strengthText = document.getElementById('strengthText');
            
            // Show/hide indicator based on password input
            if (password.length > 0) {
                indicator.style.display = 'block';
            } else {
                indicator.style.display = 'none';
                return;
            }
            
            // Check individual requirements
            const hasLength = password.length >= 8;
            const hasUppercase = /[A-Z]/.test(password);
            const hasLowercase = /[a-z]/.test(password);
            const hasNumber = /\d/.test(password);
            const hasSpecial = /[!@#$%^&*()\-_+={}[\]:";'<>.,?/\\|~]/.test(password);
            
            // Update checkmarks
            updateCheckmark('lengthCheck', hasLength);
            updateCheckmark('uppercaseCheck', hasUppercase);
            updateCheckmark('lowercaseCheck', hasLowercase);
            updateCheckmark('numberCheck', hasNumber);
            updateCheckmark('specialCheck', hasSpecial);
            
            // Calculate strength score
            let score = 0;
            if (hasLength) score += 1;
            if (hasUppercase) score += 1;
            if (hasLowercase) score += 1;
            if (hasNumber) score += 1;
            if (hasSpecial) score += 1;
            
            // Update strength bar and text
            let strength = 'Weak';
            let color = '#ef4444';
            let width = '20%';
            
            if (score >= 5) {
                strength = 'Strong';
                color = '#10b981';
                width = '100%';
            } else if (score >= 4) {
                strength = 'Good';
                color = '#3b82f6';
                width = '80%';
            } else if (score >= 3) {
                strength = 'Fair';
                color = '#f59e0b';
                width = '60%';
            } else if (score >= 2) {
                strength = 'Weak';
                color = '#ef4444';
                width = '40%';
            } else {
                strength = 'Very Weak';
                color = '#dc2626';
                width = '20%';
            }
            
            strengthBar.style.width = width;
            strengthBar.style.background = color;
            strengthText.textContent = strength;
            strengthText.style.color = color;
        }
        
        function updateCheckmark(elementId, isValid) {
            const element = document.getElementById(elementId);
            if (isValid) {
                element.textContent = '✓';
                element.style.color = '#10b981';
            } else {
                element.textContent = '✗';
                element.style.color = '#ef4444';
            }
        }

        // Function to toggle password visibility
        function togglePasswordVisibility(inputId, toggleIconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(toggleIconId);
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

        // Function to check password match
        function checkPasswordMatch() {
            const password = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_new_password').value;
            const indicator = document.getElementById('passwordMatchIndicator');
            const matchText = document.getElementById('passwordMatchText');
            
            if (confirmPassword.length > 0) {
                indicator.style.display = 'block';
                if (password === confirmPassword) {
                    matchText.textContent = 'Passwords match ✓';
                    matchText.style.color = '#10b981';
                } else {
                    matchText.textContent = 'Passwords do not match ✗';
                    matchText.style.color = '#ef4444';
                }
            } else {
                indicator.style.display = 'none';
            }
        }

        // Function to verify OTP and show password change form
        function verifyOTP(event) {
            event.preventDefault();
            
            const verificationCode = document.getElementById('verification_code').value;
            
            if (!verificationCode || verificationCode.length !== 6) {
                showInvalidOtpModal('Please enter a valid 6-digit verification code');
                return;
            }
            
            // Verify code with server (without password yet)
            fetch('change_password.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'verify_otp',
                    verification_code: verificationCode
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Hide OTP stage, show password change stage
                    document.getElementById('otpVerificationStage').style.display = 'none';
                    document.getElementById('passwordChangeStage').style.display = 'block';
                    document.getElementById('modalTitle').textContent = 'Set New Password';
                } else {
                    showInvalidOtpModal(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showInvalidOtpModal('An error occurred during verification');
            });
        }

        // Handle change password form submission
        document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const email = document.getElementById('change_password_email').value;
            if (!email) {
                showInvalidOtpModal('Email address is required');
                return;
            }

            // Send verification code
            sendPasswordChangeCode(email);
        });

        // Handle password change verification form submission
        document.getElementById('passwordChangeVerificationForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_new_password').value;
            
            if (!newPassword || !confirmPassword) {
                showInvalidOtpModal('All fields are required');
                return;
            }
            
            if (newPassword !== confirmPassword) {
                showInvalidOtpModal('Passwords do not match');
                return;
            }
            
            // Validate password strength
            if (!/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%\^&*()_\-+={}\[\]:;"\'<>,.?\/~`|\\\\])[A-Za-z\d!@#$%\^&*()_\-+={}\[\]:;"\'<>,.?\/~`|\\\\]{8,}$/.test(newPassword)) {
                showInvalidOtpModal('Password must be at least 8 characters, include uppercase and lowercase letters, at least one number, and at least one allowed special character (! @ # $ % ^ & * ( ) _ + - = { } [ ] : ; " \' < > , . ? / ~ ` | \\).');
                return;
            }

            // Get verification code (already stored in session from OTP stage)
            const verificationCode = document.getElementById('verification_code').value;

            // Change password (OTP already verified in previous stage)
            changePasswordAfterVerification(verificationCode, newPassword);
        });

        function sendPasswordChangeCode(email) {
            // Get button elements
            const sendBtn = document.getElementById('sendCodeBtn');
            const sendIcon = document.getElementById('sendCodeIcon');
            const sendText = document.getElementById('sendCodeText');
            
            // Disable button and show loading state
            sendBtn.disabled = true;
            sendBtn.style.cursor = 'not-allowed';
            sendBtn.style.opacity = '0.7';
            sendIcon.className = 'fas fa-spinner fa-spin';
            sendText.textContent = 'Sending...';
            
            fetch('change_password.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=send_code&email=' + encodeURIComponent(email)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success state briefly
                    sendIcon.className = 'fas fa-check';
                    sendText.textContent = 'Code Sent!';
                    sendBtn.style.background = 'linear-gradient(135deg, #28a745, #20c997)';
                    
                    setTimeout(() => {
                        // Close first modal and show verification modal
                        document.getElementById('changePasswordModal').style.display = 'none';
                        document.getElementById('passwordChangeVerificationModal').style.display = 'flex';
                        
                        // Reset button state
                        sendBtn.disabled = false;
                        sendBtn.style.cursor = 'pointer';
                        sendBtn.style.opacity = '1';
                        sendBtn.style.background = 'linear-gradient(135deg, #5D0E26, #8B1538)';
                        sendIcon.className = 'fas fa-paper-plane';
                        sendText.textContent = 'Send Verification Code';
                        
                        // Start countdown timer
                        startCountdownTimer();
                        
                        // Show success modal
                        document.getElementById('verificationCodeSentModal').style.display = 'flex';
                    }, 800);
                } else {
                    // Reset button on error
                    sendBtn.disabled = false;
                    sendBtn.style.cursor = 'pointer';
                    sendBtn.style.opacity = '1';
                    sendIcon.className = 'fas fa-paper-plane';
                    sendText.textContent = 'Send Verification Code';
                    
                    showInvalidOtpModal(data.message);
                }
            })
            .catch(error => {
                // Reset button on error
                sendBtn.disabled = false;
                sendBtn.style.cursor = 'pointer';
                sendBtn.style.opacity = '1';
                sendIcon.className = 'fas fa-paper-plane';
                sendText.textContent = 'Send Verification Code';
                
                console.error('Error:', error);
                showInvalidOtpModal('An error occurred while sending the verification code.');
            });
        }

        // Countdown timer function
        function startCountdownTimer() {
            let timeLeft = 60; // 1 minute
            const timerElement = document.getElementById('timeLeft');
            const countdownElement = document.getElementById('countdownTimer');
            
            const timer = setInterval(() => {
                timeLeft--;
                timerElement.textContent = timeLeft;
                
                // Change color as time runs out
                if (timeLeft <= 10) {
                    countdownElement.style.color = '#dc3545'; // Red
                } else if (timeLeft <= 30) {
                    countdownElement.style.color = '#f59e0b'; // Orange
                }
                
                if (timeLeft <= 0) {
                    clearInterval(timer);
                    countdownElement.innerHTML = '<span style="color: #dc3545;">Code has expired! Click "Resend OTP" below to get a new code.</span>';
                }
            }, 1000);
            
            // Store timer ID for cleanup
            window.passwordChangeTimer = timer;
        }

        function verifyCodeAndChangePassword(code, newPassword) {
            fetch('change_password.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=verify_and_change&verification_code=' + encodeURIComponent(code) + '&new_password=' + encodeURIComponent(newPassword)
            })
            .then(response => {
                // Check if response is valid JSON
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Invalid JSON response:', text);
                        throw new Error('Server returned invalid response. Please check the console for details.');
                    }
                });
            })
            .then(data => {
                if (data.success) {
                    // Close verification modal and show success modal
                    closePasswordChangeVerificationModal();
                    document.getElementById('passwordChangeSuccessModal').style.display = 'flex';
                    
                    // Auto redirect after 3 seconds
                    setTimeout(() => {
                        redirectToLogin();
                    }, 3000);
                } else {
                    // Show error in custom modal (including expired messages)
                    showInvalidOtpModal(data.message);
                    
                    if (data.message.includes('code')) {
                        document.getElementById('verification_code').value = '';
                        document.getElementById('verification_code').focus();
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showInvalidOtpModal('An error occurred while changing the password: ' + error.message);
            });
        }
        
        function changePasswordAfterVerification(code, newPassword) {
            // This is called after OTP verification is successful
            // Just change the password using the already verified OTP
            verifyCodeAndChangePassword(code, newPassword);
        }

        function showOtpExpiredModal() {
            document.getElementById('otpExpiredModal').style.display = 'flex';
        }

        function closeOtpExpiredModal() {
            document.getElementById('otpExpiredModal').style.display = 'none';
        }

        function showInvalidOtpModal(message) {
            document.getElementById('invalidOtpMessage').textContent = message;
            document.getElementById('invalidOtpModal').style.display = 'flex';
        }

        function closeInvalidOtpModal() {
            document.getElementById('invalidOtpModal').style.display = 'none';
            // Focus back to verification code input
            const verificationCodeField = document.getElementById('verification_code');
            if (verificationCodeField) {
                verificationCodeField.focus();
                verificationCodeField.select();
            }
        }

        function showSuccessModal(message) {
            // Create temporary success modal
            const modal = document.createElement('div');
            modal.style.cssText = 'display: flex; position: fixed; z-index: 999999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.85); backdrop-filter: blur(8px); align-items: center; justify-content: center;';
            modal.innerHTML = `
                <div style="background: white; border-radius: 16px; width: 90%; max-width: 400px; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3); overflow: hidden; animation: modalSlideIn 0.3s ease-out;">
                    <div style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; padding: 20px 24px; display: flex; align-items: center; gap: 12px;">
                        <i class="fas fa-check-circle" style="font-size: 24px;"></i>
                        <h3 style="margin: 0; font-size: 1.3rem; font-weight: 600;">Success</h3>
                    </div>
                    <div style="padding: 24px;">
                        <p style="margin: 0; font-size: 1rem; color: #333; line-height: 1.6;">${message}</p>
                    </div>
                    <div style="padding: 16px 24px; background: #f8f9fa; display: flex; justify-content: flex-end; border-top: 1px solid #e9ecef;">
                        <button onclick="this.closest('div').parentElement.parentElement.remove()" style="padding: 10px 24px; border: none; background: linear-gradient(135deg, #28a745 0%, #20c997 100%); color: white; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s ease; box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3); font-size: 0.95rem;">
                            <i class="fas fa-check" style="margin-right: 6px;"></i>OK
                        </button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }

        function resendOtpFromExpiredModal() {
            // Close expired modal
            closeOtpExpiredModal();
            
            // Close verification modal if open
            closePasswordChangeVerificationModal();
            
            // Clear the verification code field
            const verificationCodeField = document.getElementById('verification_code');
            if (verificationCodeField) {
                verificationCodeField.value = '';
            }
            
            // Clear new password fields too
            const newPasswordField = document.getElementById('new_password');
            const confirmPasswordField = document.getElementById('confirm_new_password');
            if (newPasswordField) newPasswordField.value = '';
            if (confirmPasswordField) confirmPasswordField.value = '';
            
            // Re-open the change password modal
            document.getElementById('changePasswordModal').style.display = 'flex';
            
            // Show friendly message
            setTimeout(() => {
                const messageDiv = document.createElement('div');
                messageDiv.style.cssText = 'position: fixed; top: 20px; left: 50%; transform: translateX(-50%); background: linear-gradient(135deg, #5D0E26, #8B1538); color: white; padding: 15px 25px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.3); z-index: 99999; animation: slideDown 0.3s ease;';
                messageDiv.innerHTML = '<i class="fas fa-info-circle" style="margin-right: 8px;"></i>Please click "Send Verification Code" to receive a new OTP';
                document.body.appendChild(messageDiv);
                
                setTimeout(() => {
                    messageDiv.style.animation = 'slideUp 0.3s ease';
                    setTimeout(() => messageDiv.remove(), 300);
                }, 3000);
            }, 300);
        }

        function resendOtpCode() {
            const resendBtn = document.getElementById('resendOtpBtn');
            const resendIcon = document.getElementById('resendIcon');
            const resendText = document.getElementById('resendText');
            
            // Disable button and show loading
            resendBtn.disabled = true;
            resendBtn.style.cursor = 'not-allowed';
            resendBtn.style.opacity = '0.6';
            resendIcon.className = 'fas fa-spinner fa-spin';
            resendText.textContent = 'Sending...';
            
            // Get email from the change password form
            const email = document.getElementById('change_password_email').value;
            
            // Send new OTP
            fetch('change_password.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=send_code&email=' + encodeURIComponent(email)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success state
                    resendIcon.className = 'fas fa-check';
                    resendText.textContent = 'Code Sent!';
                    resendBtn.style.background = 'linear-gradient(135deg, #28a745, #20c997)';
                    resendBtn.style.borderColor = '#28a745';
                    resendBtn.style.color = 'white';
                    
                    // Clear verification code field
                    document.getElementById('verification_code').value = '';
                    
                    // Restart countdown timer
                    if (window.passwordChangeTimer) {
                        clearInterval(window.passwordChangeTimer);
                    }
                    startCountdownTimer();
                    
                    // Show success notification
                    showSuccessModal('New verification code sent to your email!');
                    
                    // Reset button after 3 seconds
                    setTimeout(() => {
                        resendBtn.disabled = false;
                        resendBtn.style.cursor = 'pointer';
                        resendBtn.style.opacity = '1';
                        resendBtn.style.background = 'white';
                        resendBtn.style.borderColor = '#5D0E26';
                        resendBtn.style.color = '#5D0E26';
                        resendIcon.className = 'fas fa-redo';
                        resendText.textContent = 'Resend OTP';
                    }, 3000);
                } else {
                    // Reset button on error
                    resendBtn.disabled = false;
                    resendBtn.style.cursor = 'pointer';
                    resendBtn.style.opacity = '1';
                    resendIcon.className = 'fas fa-redo';
                    resendText.textContent = 'Resend OTP';
                    
                    showInvalidOtpModal(data.message);
                }
            })
            .catch(error => {
                // Reset button on error
                resendBtn.disabled = false;
                resendBtn.style.cursor = 'pointer';
                resendBtn.style.opacity = '1';
                resendIcon.className = 'fas fa-redo';
                resendText.textContent = 'Resend OTP';
                
                console.error('Error:', error);
                showInvalidOtpModal('An error occurred while sending the verification code.');
            });
        }
        </script>
    </div>
</div>

<script>
// Notifications functionality
let notificationsVisible = false;

// Close notifications when clicking outside
document.addEventListener('click', function(event) {
    const notificationsBtn = document.getElementById('notificationsBtn');
    const dropdown = document.getElementById('notificationsDropdown');
    
    if (notificationsBtn && dropdown && !notificationsBtn.contains(event.target) && !dropdown.contains(event.target)) {
        dropdown.style.display = 'none';
        notificationsVisible = false;
    }
});

function loadNotifications() {
    fetch('get_notifications.php')
        .then(response => response.json())
        .then(data => {
            updateNotificationBadge(data.unread_count);
            displayNotifications(data.notifications);
        })
        .catch(error => console.error('Error loading notifications:', error));
}

function updateNotificationBadge(count) {
    const badge = document.getElementById('notificationBadge');
    const countElement = document.getElementById('notificationCount');
    
    if (count > 0) {
        badge.textContent = count > 99 ? '99+' : count;
        badge.style.display = 'flex';
        countElement.textContent = count > 99 ? '99+' : count;
    } else {
        badge.style.display = 'none';
        countElement.textContent = '0';
    }
}

function displayNotifications(notifications) {
    const container = document.getElementById('notificationsList');
    
    if (notifications.length === 0) {
        container.innerHTML = '<div style="padding: 20px; text-align: center; color: #6b7280; font-style: italic;">No notifications</div>';
        return;
    }
    
    container.innerHTML = notifications.map(notification => `
        <div onclick="handleNotificationClick(${notification.id}, '${notification.url || ''}')" style="padding: 12px; border-bottom: 1px solid #f3f4f6; ${!notification.is_read ? 'background: #f8f9fa;' : ''} border-radius: 8px; margin: 4px 0; cursor: pointer; transition: all 0.2s ease;" onmouseover="this.style.background='#e5f3ff'" onmouseout="this.style.background='${!notification.is_read ? '#f8f9fa' : 'transparent'}'">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 10px;">
                <div style="flex: 1;">
                    <div style="font-weight: 600; font-size: 14px; color: #374151; margin-bottom: 4px; display: flex; align-items: center; gap: 6px;">
                        ${notification.title}
                        ${notification.url ? '<i class="fas fa-external-link-alt" style="font-size: 10px; color: #6b7280;"></i>' : ''}
                    </div>
                    <div style="font-size: 12px; color: #6b7280; margin-bottom: 4px;">${notification.message}</div>
                    <div style="font-size: 11px; color: #9ca3af;">${formatTime(notification.created_at)}</div>
                </div>
                <div style="width: 8px; height: 8px; border-radius: 50%; background: ${getNotificationColor(notification.type)}; ${notification.is_read ? 'display: none;' : ''}"></div>
            </div>
        </div>
    `).join('');
}

function getNotificationColor(type) {
    switch (type) {
        case 'success': return '#10b981';
        case 'warning': return '#f59e0b';
        case 'error': return '#ef4444';
        default: return '#3b82f6';
    }
}

function formatTime(timestamp) {
    const date = new Date(timestamp);
    const now = new Date();
    const diff = now - date;
    
    if (diff < 60000) return 'Just now';
    if (diff < 3600000) return Math.floor(diff / 60000) + 'm ago';
    if (diff < 86400000) return Math.floor(diff / 3600000) + 'h ago';
    return date.toLocaleDateString();
}

function markAllAsRead() {
    fetch('get_notifications.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'mark_read=true'
    })
    .then(() => {
        loadNotifications();
    })
    .catch(error => console.error('Error marking notifications as read:', error));
}

function handleNotificationClick(notificationId, url) {
    // Mark notification as read first
    markOneAsRead(notificationId);
    
    // Navigate to the appropriate page if URL is provided
    if (url && url.trim() !== '') {
        // Close the notification dropdown
        const dropdown = document.getElementById('notificationsDropdown');
        if (dropdown) {
            dropdown.style.display = 'none';
        }
        
        // Navigate to the URL
        setTimeout(() => {
            if (url.includes('#')) {
                // Handle anchor links (e.g., dashboard.php#cases)
                const [page, anchor] = url.split('#');
                if (page === window.location.pathname.split('/').pop()) {
                    // If we're already on the same page, just scroll to the anchor
                    const element = document.getElementById(anchor);
                    if (element) {
                        element.scrollIntoView({ behavior: 'smooth' });
                    }
                } else {
                    // Navigate to different page
                    window.location.href = url;
                }
            } else {
                // Navigate to different page
                window.location.href = url;
            }
        }, 100);
    }
}

function markOneAsRead(id) {
    if (!id) return;
    fetch('get_notifications.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'mark_one=true&id=' + encodeURIComponent(id)
    })
    .then(() => {
        loadNotifications();
    })
    .catch(error => console.error('Error marking notification as read:', error));
}

// Load notifications on page load
document.addEventListener('DOMContentLoaded', function() {
    loadNotifications();
    // Refresh notifications every 30 seconds
    setInterval(loadNotifications, 30000);
});
</script> 