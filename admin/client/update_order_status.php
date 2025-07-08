<?php
include '../../connection/connect.php'; 
function setOrderOngoing($orderId, $estimatedArrivalDate) {
    global $conn; // Assume $conn is your mysqli connection
    
    try {
        // Validate arrival date
        $arrivalTimestamp = strtotime($estimatedArrivalDate);
        if (!$arrivalTimestamp || $arrivalTimestamp <= time()) {
            throw new Exception('Invalid arrival date - must be in the future');
        }
        
        // Update order status and set arrival date
        $arrivalDate = date('Y-m-d H:i:s', $arrivalTimestamp);
        $stmt = $conn->prepare("UPDATE orders SET status = 'Ongoing', estimated_arrival_date = ? WHERE id = ?");
        $stmt->bind_param('si', $arrivalDate, $orderId);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            return [
                'success' => true,
                'message' => 'Order set to Ongoing with countdown',
                'order_id' => $orderId,
                'arrival_date' => $arrivalDate
            ];
        } else {
            throw new Exception('Order not found or no changes made');
        }
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Get orders with active countdowns
 */
function getOrdersWithCountdown() {
    global $conn;
    
    try {
        $query = "
            SELECT 
                id,
                customer_name,
                email,
                mobile,
                total,
                status,
                estimated_arrival_date,
                TIMESTAMPDIFF(SECOND, NOW(), estimated_arrival_date) as seconds_remaining
            FROM orders 
            WHERE status = 'Ongoing' 
            AND estimated_arrival_date IS NOT NULL 
            AND estimated_arrival_date > NOW()
            ORDER BY estimated_arrival_date ASC
        ";
        
        $result = $conn->query($query);
        
        if ($result) {
            return $result->fetch_all(MYSQLI_ASSOC);
        }
        
        return [];
        
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Check and update expired countdowns (call this periodically)
 */
function updateExpiredCountdowns() {
    global $conn;
    
    try {
        // Get orders that have expired countdowns
        $query = "
            SELECT id, customer_name 
            FROM orders 
            WHERE status = 'Ongoing' 
            AND estimated_arrival_date IS NOT NULL 
            AND estimated_arrival_date <= NOW()
        ";
        
        $result = $conn->query($query);
        $expiredOrders = [];
        
        if ($result) {
            $expiredOrders = $result->fetch_all(MYSQLI_ASSOC);
        }
        
        if (count($expiredOrders) > 0) {
            // Update status to 'Arrival'
            $updateQuery = "
                UPDATE orders 
                SET status = 'Arrival' 
                WHERE status = 'Ongoing' 
                AND estimated_arrival_date IS NOT NULL 
                AND estimated_arrival_date <= NOW()
            ";
            
            $conn->query($updateQuery);
            
            return [
                'success' => true,
                'updated_count' => count($expiredOrders),
                'orders' => $expiredOrders
            ];
        }
        
        return [
            'success' => true,
            'updated_count' => 0,
            'orders' => []
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

// Example usage:
/*
// Set order to ongoing with 7 days countdown
$result = setOrderOngoing(1, '+7 days');

// Get all orders with active countdowns
$countdownOrders = getOrdersWithCountdown();

// Update expired countdowns (call this via cron job every minute)
$expiredResult = updateExpiredCountdowns();
*/
?>