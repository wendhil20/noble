<?php
/**
 * Audit Trail Helper Functions
 * Logs all important actions in the system
 */

function logAuditTrail($conn, $actionType, $tableName, $recordId, $orderId = null, $orderItemId = null, $oldValue = null, $newValue = null, $description = null) {
    // Get user information from session
    $userId = $_SESSION['noble_id'] ?? null;
    $userName = $_SESSION['noble_name'] ?? 'Unknown User';
    $userLevel = $_SESSION['noble_lvl'] ?? 'unknown';
    
    // Get IP address
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    }
    
    // Get user agent
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    // Convert arrays/objects to JSON
    if (is_array($oldValue) || is_object($oldValue)) {
        $oldValue = json_encode($oldValue);
    }
    if (is_array($newValue) || is_object($newValue)) {
        $newValue = json_encode($newValue);
    }
    
    try {
        $stmt = $conn->prepare("
            INSERT INTO audit_trail 
            (user_id, user_name, user_level, action_type, table_name, record_id, 
             order_id, order_item_id, old_value, new_value, description, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param(
            "issssiiisssss",
            $userId,
            $userName,
            $userLevel,
            $actionType,
            $tableName,
            $recordId,
            $orderId,
            $orderItemId,
            $oldValue,
            $newValue,
            $description,
            $ipAddress,
            $userAgent
        );
        
        $result = $stmt->execute();
        $stmt->close();
        
        // Log to error log for debugging
        if (!$result) {
            error_log("Audit Trail Error: Failed to log action '$actionType' for user '$userName'");
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Audit Trail Exception: " . $e->getMessage());
        return false;
    }
}

/**
 * Get audit trail for a specific record
 */
function getAuditTrail($conn, $tableName = null, $recordId = null, $orderId = null, $orderItemId = null, $limit = 100) {
    $conditions = [];
    $params = [];
    $types = "";
    
    if ($tableName) {
        $conditions[] = "table_name = ?";
        $params[] = $tableName;
        $types .= "s";
    }
    
    if ($recordId) {
        $conditions[] = "record_id = ?";
        $params[] = $recordId;
        $types .= "i";
    }
    
    if ($orderId) {
        $conditions[] = "order_id = ?";
        $params[] = $orderId;
        $types .= "i";
    }
    
    if ($orderItemId) {
        $conditions[] = "order_item_id = ?";
        $params[] = $orderItemId;
        $types .= "i";
    }
    
    $sql = "SELECT * FROM audit_trail";
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }
    $sql .= " ORDER BY created_at DESC LIMIT ?";
    
    $params[] = $limit;
    $types .= "i";
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $trails = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    return $trails;
}
?>