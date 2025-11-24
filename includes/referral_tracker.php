<?php
//referral_tracker.php
// ============================================
// FILE: referral_tracker.php (CORRECTED VERSION)
// Place this in: /noble/includes/referral_tracker.php
// ============================================

/**
 * Track referral visits - counts each new browser session
 * Call this function at the top of your landing page
 */
function trackReferralVisit($conn) {
    // Check if referral code exists in URL
    if (!isset($_GET['ref']) || empty($_GET['ref'])) {
        return false;
    }
    
    // Convert to uppercase to match database format
    $ref_code = trim(strtoupper($_GET['ref']));
    
    // ✅ SAVE referral code to session for later use during login
    $_SESSION['pending_referral_code'] = $ref_code;
    
    // Validate referral code format (NH-XXXXXX)
    if (!preg_match('/^NH-[A-Z0-9]{6}$/', $ref_code)) {
        return false;
    }
    
    // Check if referral code is valid and active
    $stmt = $conn->prepare("SELECT id, user_id FROM referral_codes WHERE referral_code = ? AND is_active = 1 LIMIT 1");
    $stmt->bind_param("s", $ref_code);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        return false; // Invalid or inactive code
    }

    $row = $result->fetch_assoc();
    $referral_id = $row['id'];
    $user_id = $row['user_id'];
    $stmt->close();
    
    // ✅ CHECK SESSION FIRST - Only count once per browser session
    $session_key = 'ref_tracked_' . $referral_id;
    if (isset($_SESSION[$session_key]) && $_SESSION[$session_key] === true) {
        return true; // Already counted in this session
    }
    
    // Get visitor info
    $visitor_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $visit_date = date('Y-m-d');
    $visit_time = date('Y-m-d H:i:s');
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // 1. Update total_scans in referral_codes table
        $stmt = $conn->prepare("UPDATE referral_codes SET total_scans = total_scans + 1 WHERE id = ?");
        $stmt->bind_param("i", $referral_id);
        $stmt->execute();
        $stmt->close();
        
        // 2. Insert detailed visit log (every visit gets logged)
        $stmt = $conn->prepare("
            INSERT INTO referral_visits 
            (referral_id, user_id, referral_code, visit_date, visit_time, visitor_ip, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("iisssss", $referral_id, $user_id, $ref_code, $visit_date, $visit_time, $visitor_ip, $user_agent);
        $stmt->execute();
        $stmt->close();
        
        // Commit transaction
        $conn->commit();
        
        // ✅ Mark as tracked in THIS browser session
        $_SESSION[$session_key] = true;
        
        return true;
        
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        error_log("Referral tracking error: " . $e->getMessage());
        return false;
    }
}

/**
 * Associate referral code with user account after login OR if already logged in
 * Call this after successful login OR on page load if user is logged in
 */
function assignReferralToUser($conn, $user_id) {
    // Check if there's a pending referral code from the session
    if (!isset($_SESSION['pending_referral_code']) || empty($_SESSION['pending_referral_code'])) {
        return false;
    }
    
    $ref_code = $_SESSION['pending_referral_code'];
    
    // Validate user_id
    if (empty($user_id) || !is_numeric($user_id)) {
        return false;
    }
    
    // Validate referral code exists and is active
    $stmt = $conn->prepare("SELECT id, user_id FROM referral_codes WHERE referral_code = ? AND is_active = 1 LIMIT 1");
    $stmt->bind_param("s", $ref_code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        unset($_SESSION['pending_referral_code']); // Clear invalid code
        return false;
    }
    
    $row = $result->fetch_assoc();
    $referrer_user_id = $row['user_id'];
    $referral_id = $row['id'];
    $stmt->close();
    
    // ✅ Don't allow self-referral
    if ($referrer_user_id == $user_id) {
        unset($_SESSION['pending_referral_code']);
        return false;
    }
    
    // ✅ Check if user already has a referral code assigned
    $check_stmt = $conn->prepare("SELECT referred_by_code FROM users WHERE id = ? LIMIT 1");
    $check_stmt->bind_param("i", $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        $check_stmt->close();
        unset($_SESSION['pending_referral_code']);
        return false; // User doesn't exist
    }
    
    $user_data = $check_result->fetch_assoc();
    $check_stmt->close();
    
    // ✅ Only assign if user doesn't already have a referral code (NULL check)
    if (!empty($user_data['referred_by_code'])) {
        unset($_SESSION['pending_referral_code']);
        return false; // User already has a referral
    }
    
    // ✅ Start transaction to ensure data consistency
    $conn->begin_transaction();
    
    try {
        // Update user with referral code
        $update_stmt = $conn->prepare("UPDATE users SET referred_by_code = ? WHERE id = ? AND referred_by_code IS NULL");
        $update_stmt->bind_param("si", $ref_code, $user_id);
        $update_stmt->execute();
        $affected_rows = $update_stmt->affected_rows;
        $update_stmt->close();
        
        if ($affected_rows > 0) {
            // Commit transaction
            $conn->commit();
            
            // Clear the pending referral from session
            unset($_SESSION['pending_referral_code']);
            
            return true;
        } else {
            $conn->rollback();
            unset($_SESSION['pending_referral_code']);
            return false;
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Referral assignment error: " . $e->getMessage());
        return false;
    }
}
?>