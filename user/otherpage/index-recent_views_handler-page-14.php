<?php
/**
 * ENHANCED PRODUCT VIEW TRACKING SYSTEM
 * Uses existing recent_views table with added functionality
 * Updated: Includes size and color from product_colors
 */

// ====================================
// 1. TRACK VIEW (Enhanced with IP & User Agent)
// ====================================
function trackProductView($conn, $product_id) {
    $session_id = session_id();
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : NULL;
    $ip_address = getClientIP();
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Round current time to the nearest minute
    $rounded_time = date('Y-m-d H:i:00');
    
    // Check if this exact minute view already exists
    $check_sql = "SELECT id FROM recent_views 
                  WHERE session_id = ? 
                  AND product_id = ? 
                  AND viewed_at >= ?
                  AND viewed_at < DATE_ADD(?, INTERVAL 1 MINUTE)
                  LIMIT 1";
    
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("siss", $session_id, $product_id, $rounded_time, $rounded_time);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        return false; // Already tracked this minute
    }
    
    // Insert with rounded time
    $insert_sql = "INSERT INTO recent_views 
                   (user_id, session_id, product_id, ip_address, user_agent, viewed_at) 
                   VALUES (?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($insert_sql);
    $stmt->bind_param("isisss", $user_id, $session_id, $product_id, $ip_address, $user_agent, $rounded_time);
    
    if ($stmt->execute()) {
        updateProductViewCount($conn, $product_id);
        
        // Occasional cleanup
        if (rand(1, 100) == 1) {
            $conn->query("DELETE FROM recent_views WHERE viewed_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
        }
        
        return true;
    }
    
    return false;
}

// ====================================
// 2. UPDATE PRODUCT VIEW COUNT
// ====================================
function updateProductViewCount($conn, $product_id) {
    // Count total views (all time)
    $total_sql = "SELECT COUNT(*) as total FROM recent_views WHERE product_id = ?";
    $stmt = $conn->prepare($total_sql);
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $total_result = $stmt->get_result()->fetch_assoc();
    $total_views = $total_result['total'];
    
    // Count unique viewers (more accurate - counts distinct users/sessions)
    $unique_sql = "SELECT COUNT(DISTINCT COALESCE(user_id, session_id)) as unique_count 
                   FROM recent_views WHERE product_id = ?";
    $stmt = $conn->prepare($unique_sql);
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $unique_result = $stmt->get_result()->fetch_assoc();
    $unique_views = $unique_result['unique_count'];
    
    // Update products table with view counts
    $update_sql = "UPDATE products 
                   SET view_count = ?, 
                       unique_view_count = ? 
                   WHERE id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("iii", $total_views, $unique_views, $product_id);
    $stmt->execute();
}

// ====================================
// 3. GET PRODUCT VIEW COUNT
// ====================================
function getProductViewCount($conn, $product_id) {
    $sql = "SELECT view_count, unique_view_count FROM products WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    return [
        'total' => (int)($result['view_count'] ?? 0),
        'unique' => (int)($result['unique_view_count'] ?? 0)
    ];
}

// ====================================
// 4. FORMAT VIEW COUNT - TikTok Style
// ====================================
function formatViewCount($count) {
    if ($count >= 1000000) {
        return round($count / 1000000, 1) . 'M';
    } elseif ($count >= 1000) {
        return round($count / 1000, 1) . 'K';
    }
    return number_format($count);
}

// ====================================
// 5. GET CLIENT IP ADDRESS
// ====================================
function getClientIP() {
    $ip = '';
    
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    }
    
    // Validate and sanitize IP
    $ip = filter_var(trim($ip), FILTER_VALIDATE_IP);
    return $ip ? $ip : '0.0.0.0';
}

// ====================================
// 6. GET RECENT VIEWS (Enhanced with size, color & view counts)
// ====================================
function getRecentViews($conn, $limit = 10) {
    $session_id = session_id();
    
    $sql = "SELECT 
                p.id,
                p.product_name,
                p.main_image,
                p.description,
                p.category_id,
                p.view_count,
                p.unique_view_count,
                COALESCE(MIN(pv.price), 0) as price,
                COALESCE(MIN(pv.percent), 0) as percent,
                COALESCE(MAX(pv.discount), 0) as discount,
                COALESCE(MIN(pv.size), '') as size,
                GROUP_CONCAT(DISTINCT pc.color_name SEPARATOR ',') as color_name,
                MAX(rv.viewed_at) as viewed_at
            FROM recent_views rv
            INNER JOIN products p ON rv.product_id = p.id
            LEFT JOIN product_variants pv ON p.id = pv.product_id
            LEFT JOIN product_colors pc ON p.id = pc.product_id
            WHERE rv.session_id = ?
            GROUP BY p.id
            ORDER BY viewed_at DESC
            LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $session_id, $limit);
    $stmt->execute();
    
    return $stmt->get_result();
}

// ====================================
// 7. GET RECOMMENDED PRODUCTS (Popularity-based with size & color)
// ====================================
function getRecommendedProducts($conn, $limit = 10) {
    $sql = "SELECT 
                p.id,
                p.product_name,
                p.main_image,
                p.description,
                p.category_id,
                p.view_count,
                p.unique_view_count,
                COALESCE(MIN(pv.price), 0) as price,
                COALESCE(MIN(pv.percent), 0) as percent,
                COALESCE(MAX(pv.discount), 0) as discount,
                COALESCE(MIN(pv.size), '') as size,
                GROUP_CONCAT(DISTINCT pc.color_name SEPARATOR ',') as color_name,
                COUNT(DISTINCT rv.id) as recent_views
            FROM products p
            LEFT JOIN product_variants pv ON p.id = pv.product_id
            LEFT JOIN product_colors pc ON p.id = pc.product_id
            INNER JOIN recent_views rv ON p.id = rv.product_id
            WHERE rv.viewed_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY p.id
            ORDER BY recent_views DESC, p.view_count DESC
            LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    
    return $stmt->get_result();
}

// ====================================
// 8. GET TRENDING PRODUCTS
// ====================================
function getTrendingProducts($conn, $limit = 10) {
    $sql = "SELECT 
                p.id,
                p.product_name,
                p.main_image,
                p.category_id,
                p.view_count,
                p.unique_view_count,
                COALESCE(MIN(pv.price), 0) as price,
                COALESCE(MIN(pv.percent), 0) as percent,
                COALESCE(MAX(pv.discount), 0) as discount,
                COALESCE(MIN(pv.size), '') as size,
                GROUP_CONCAT(DISTINCT pc.color_name SEPARATOR ',') as color_name,
                
                COUNT(CASE 
                    WHEN rv.viewed_at > DATE_SUB(NOW(), INTERVAL 7 DAY) 
                    THEN 1 
                END) as views_last_7d,
                
                COUNT(CASE 
                    WHEN rv.viewed_at BETWEEN DATE_SUB(NOW(), INTERVAL 14 DAY) 
                                          AND DATE_SUB(NOW(), INTERVAL 7 DAY)
                    THEN 1 
                END) as views_prev_7d,
                
                (
                    COUNT(CASE 
                        WHEN rv.viewed_at > DATE_SUB(NOW(), INTERVAL 7 DAY) 
                        THEN 1 
                    END) * 10
                    +
                    CASE 
                        WHEN COUNT(CASE 
                                WHEN rv.viewed_at > DATE_SUB(NOW(), INTERVAL 7 DAY) 
                                THEN 1 
                            END) > 
                            COUNT(CASE 
                                WHEN rv.viewed_at BETWEEN DATE_SUB(NOW(), INTERVAL 14 DAY) 
                                                      AND DATE_SUB(NOW(), INTERVAL 7 DAY)
                                THEN 1 
                            END) * 2
                        THEN 100
                        ELSE 0
                    END
                ) as trending_score
                
            FROM products p
            LEFT JOIN product_variants pv ON p.id = pv.product_id
            LEFT JOIN product_colors pc ON p.id = pc.product_id
            INNER JOIN recent_views rv ON p.id = rv.product_id
            WHERE rv.viewed_at > DATE_SUB(NOW(), INTERVAL 14 DAY)
            GROUP BY p.id
            HAVING views_last_7d >= 3
            ORDER BY trending_score DESC
            LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    
    return $stmt->get_result();
}

// ====================================
// 9. "USERS WHO VIEWED THIS ALSO VIEWED"
// ====================================
function getSimilarViewedProducts($conn, $product_id, $limit = 10) {
    $session_id = session_id();
    
    $sql = "SELECT 
                p.id,
                p.product_name,
                p.main_image,
                p.view_count,
                p.unique_view_count,
                COALESCE(MIN(pv.price), 0) as price,
                COALESCE(MIN(pv.percent), 0) as percent,
                COALESCE(MAX(pv.discount), 0) as discount,
                COALESCE(MIN(pv.size), '') as size,
                GROUP_CONCAT(DISTINCT pc.color_name SEPARATOR ',') as color_name,
                COUNT(DISTINCT rv.session_id) as co_view_count
            FROM products p
            LEFT JOIN product_variants pv ON p.id = pv.product_id
            LEFT JOIN product_colors pc ON p.id = pc.product_id
            INNER JOIN recent_views rv ON p.id = rv.product_id
            WHERE rv.session_id IN (
                SELECT DISTINCT session_id 
                FROM recent_views 
                WHERE product_id = ?
                AND session_id != ?
            )
            AND p.id != ?
            GROUP BY p.id
            ORDER BY co_view_count DESC, p.view_count DESC
            LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isii", $product_id, $session_id, $product_id, $limit);
    $stmt->execute();
    
    return $stmt->get_result();
}

// ====================================
// 10. GET FLASH SALE PRODUCTS
// ====================================
function getFlashSaleProducts($conn, $limit = 10) {
    $sql = "SELECT 
                p.id,
                p.product_name,
                p.main_image,
                p.view_count,
                p.unique_view_count,
                COALESCE(MIN(pv.price), 0) as price,
                COALESCE(MIN(pv.percent), 0) as percent,
                COALESCE(MAX(pv.discount), 0) as discount,
                COALESCE(MIN(pv.size), '') as size,
                GROUP_CONCAT(DISTINCT pc.color_name SEPARATOR ',') as color_name
            FROM products p
            INNER JOIN product_variants pv ON p.id = pv.product_id
            LEFT JOIN product_colors pc ON p.id = pc.product_id
            WHERE pv.discount > 0
            GROUP BY p.id
            HAVING discount >= 10
            ORDER BY discount DESC, p.view_count DESC
            LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    
    return $stmt->get_result();
}

// ====================================
// 11. BATCH UPDATE ALL VIEW COUNTS (Maintenance)
// ====================================
function batchUpdateAllViewCounts($conn) {
    // Update all products at once
    $sql = "UPDATE products p
            SET 
                view_count = (
                    SELECT COUNT(*) 
                    FROM recent_views rv 
                    WHERE rv.product_id = p.id
                ),
                unique_view_count = (
                    SELECT COUNT(DISTINCT COALESCE(user_id, session_id))
                    FROM recent_views rv
                    WHERE rv.product_id = p.id
                )";
    
    $result = $conn->query($sql);
    return $result;
}

// ====================================
// 12. GET VIEW STATISTICS (Admin Dashboard)
// ====================================
function getViewStatistics($conn) {
    $stats = [];
    
    // Total views
    $total_sql = "SELECT COUNT(*) as total FROM recent_views";
    $stats['total_views'] = $conn->query($total_sql)->fetch_assoc()['total'];
    
    // Unique viewers
    $unique_sql = "SELECT COUNT(DISTINCT COALESCE(user_id, session_id)) as total FROM recent_views";
    $stats['unique_viewers'] = $conn->query($unique_sql)->fetch_assoc()['total'];
    
    // Today's views
    $today_sql = "SELECT COUNT(*) as total FROM recent_views WHERE DATE(viewed_at) = CURDATE()";
    $stats['today_views'] = $conn->query($today_sql)->fetch_assoc()['total'];
    
    // This week's views
    $week_sql = "SELECT COUNT(*) as total FROM recent_views WHERE viewed_at > DATE_SUB(NOW(), INTERVAL 7 DAY)";
    $stats['week_views'] = $conn->query($week_sql)->fetch_assoc()['total'];
    
    // Most viewed product
    $most_viewed_sql = "SELECT p.id, p.product_name, p.view_count 
                        FROM products p 
                        WHERE p.view_count > 0
                        ORDER BY p.view_count DESC 
                        LIMIT 1";
    $result = $conn->query($most_viewed_sql);
    $stats['most_viewed'] = $result->num_rows > 0 ? $result->fetch_assoc() : null;
    
    return $stats;
}

// ====================================
// 13. GET MOST VIEWED PRODUCTS
// ====================================
function getMostViewedProducts($conn, $limit = 10) {
    $sql = "SELECT 
                p.id,
                p.product_name,
                p.main_image,
                p.description,
                p.view_count,
                p.unique_view_count,
                COALESCE(MIN(pv.price), 0) as price,
                COALESCE(MIN(pv.percent), 0) as percent,
                COALESCE(MAX(pv.discount), 0) as discount,
                COALESCE(MIN(pv.size), '') as size,
                GROUP_CONCAT(DISTINCT pc.color_name SEPARATOR ',') as color_name
            FROM products p
            LEFT JOIN product_variants pv ON p.id = pv.product_id
            LEFT JOIN product_colors pc ON p.id = pc.product_id
            WHERE p.view_count > 0
            GROUP BY p.id
            ORDER BY p.view_count DESC
            LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    
    return $stmt->get_result();
}