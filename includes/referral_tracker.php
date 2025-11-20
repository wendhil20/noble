<?php
// ============================================
// FILE: referral_tracker.php (FIXED VERSION)
// Place this in: /noble/includes/referral_tracker.php
// ============================================

/**
 * Track referral visits with date analytics
 * Call this function at the top of your landing page
 */
function trackReferralVisit($conn) {
    // Check if referral code exists in URL
    if (!isset($_GET['ref']) || empty($_GET['ref'])) {
        return false;
    }
    
    // ✅ FIX 1: Convert to uppercase to match database format
    $ref_code = trim(strtoupper($_GET['ref']));
    
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
    
    // Get visitor info
    $visitor_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $visit_date = date('Y-m-d');
    $visit_time = date('Y-m-d H:i:s');
    
    // ✅ FIX 2: Check if this IP already visited TODAY (not just in session)
    // This allows counting the same person on different days
    $check_stmt = $conn->prepare("
        SELECT id FROM referral_visits 
        WHERE referral_id = ? 
        AND visitor_ip = ? 
        AND visit_date = ? 
        LIMIT 1
    ");
    $check_stmt->bind_param("iss", $referral_id, $visitor_ip, $visit_date);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $check_stmt->close();
        return true; // Already counted this IP today
    }
    $check_stmt->close();
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // 1. Update total_scans in referral_codes table
        $stmt = $conn->prepare("UPDATE referral_codes SET total_scans = total_scans + 1 WHERE id = ?");
        $stmt->bind_param("i", $referral_id);
        $stmt->execute();
        $stmt->close();
        
        // 2. Insert detailed visit log
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
        
        // ✅ FIX 3: Store in session to prevent multiple DB hits during same visit
        $_SESSION['ref_tracked_' . $referral_id . '_' . $visit_date] = true;
        
        return true;
        
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        error_log("Referral tracking error: " . $e->getMessage());
        return false;
    }
}
?>