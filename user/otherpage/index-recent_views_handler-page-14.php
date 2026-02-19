<?php
//  index-recent_views_handler-page-14.php
/**
 * 🔥 COMPLETE SMART PRICE SYSTEM - LAZADA STYLE
 * Shows: Overall Lowest to Highest Price
 * Updated: 2025-11-06
 */

// ====================================
// 1. TRACK VIEW (Enhanced with IP & User Agent)
// ====================================
function trackProductView($conn, $product_id)
{
    $session_id = session_id();
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : NULL;
    $ip_address = getClientIP();
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $rounded_time = date('Y-m-d H:i:00');

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
        return false;
    }

    $insert_sql = "INSERT INTO recent_views 
                   (user_id, session_id, product_id, ip_address, user_agent, viewed_at) 
                   VALUES (?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($insert_sql);
    $stmt->bind_param("isisss", $user_id, $session_id, $product_id, $ip_address, $user_agent, $rounded_time);

    if ($stmt->execute()) {
        updateProductViewCount($conn, $product_id);

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
function updateProductViewCount($conn, $product_id)
{
    $total_sql = "SELECT COUNT(*) as total FROM recent_views WHERE product_id = ?";
    $stmt = $conn->prepare($total_sql);
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $total_result = $stmt->get_result()->fetch_assoc();
    $total_views = $total_result['total'];

    $unique_sql = "SELECT COUNT(DISTINCT COALESCE(user_id, session_id)) as unique_count 
                   FROM recent_views WHERE product_id = ?";
    $stmt = $conn->prepare($unique_sql);
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $unique_result = $stmt->get_result()->fetch_assoc();
    $unique_views = $unique_result['unique_count'];

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
function getProductViewCount($conn, $product_id)
{
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
function formatViewCount($count)
{
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
function getClientIP()
{
    $ip = '';

    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    }

    $ip = filter_var(trim($ip), FILTER_VALIDATE_IP);
    return $ip ? $ip : '0.0.0.0';
}

// ====================================
// 🔥 6. GET RECENT VIEWS (SMART PRICE RANGE)
// ====================================
function getRecentViews($conn, $limit = 10)
{
    $session_id = session_id();

    $sql = "SELECT 
                p.id,
                p.product_name,
                p.main_image,
                p.description,
                p.category_id,
                p.view_count,
                p.unique_view_count,
                p.price as base_price,
                
                -- 🔥 SEPARATE SIZE & COLOR PRICES
                COALESCE(MIN(pv.price), 0) as min_size_price,
                COALESCE(MAX(pv.price), 0) as max_size_price,
                COALESCE(MIN(pc.price), 0) as min_color_price,
                COALESCE(MAX(pc.price), 0) as max_color_price,
                
                -- Base price (fallback)
                COALESCE(
                    NULLIF(MIN(pv.price), 0),
                    NULLIF(MIN(pc.price), 0),
                    p.price
                ) as price,
                
                -- Markup & Discount
                COALESCE(MIN(pv.percent), 0) as percent,
                COALESCE(MAX(pv.discount), 0) as discount,
                
                -- Variant/Color details
                GROUP_CONCAT(DISTINCT pv.size ORDER BY pv.price SEPARATOR ',') as size_list,
                GROUP_CONCAT(DISTINCT pc.color_name ORDER BY pc.price SEPARATOR ',') as color_list,
                COUNT(DISTINCT pc.id) as color_count,
                COUNT(DISTINCT pv.id) as variant_count,
                
                AVG(r.rating) AS avg_rating,
                COUNT(DISTINCT r.id) AS rating_count,
                COALESCE(SUM(si.quantity), 0) AS total_sold,
                
                MAX(rv.viewed_at) as viewed_at
            FROM recent_views rv
            INNER JOIN products p ON rv.product_id = p.id
            LEFT JOIN product_variants pv ON p.id = pv.product_id
            LEFT JOIN product_colors pc ON p.id = pc.product_id
            LEFT JOIN product_ratings r ON r.product_id = p.id
            LEFT JOIN sold_items si ON si.product_id = p.id
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
// 🔥 7. GET RECOMMENDED PRODUCTS (SMART PRICE)
// ====================================
function getRecommendedProducts($conn, $limit = 10)
{
    $sql = "SELECT 
                p.id,
                p.product_name,
                p.main_image,
                p.description,
                p.category_id,
                p.view_count,
                p.unique_view_count,
                p.price as base_price,
                
                -- 🔥 SEPARATE SIZE & COLOR PRICES
                COALESCE(MIN(pv.price), 0) as min_size_price,
                COALESCE(MAX(pv.price), 0) as max_size_price,
                COALESCE(MIN(pc.price), 0) as min_color_price,
                COALESCE(MAX(pc.price), 0) as max_color_price,
                
                COALESCE(
                    NULLIF(MIN(pv.price), 0),
                    NULLIF(MIN(pc.price), 0),
                    p.price
                ) as price,
                
                COALESCE(MIN(pv.percent), 0) as percent,
                COALESCE(MAX(pv.discount), 0) as discount,
                
                GROUP_CONCAT(DISTINCT pv.size ORDER BY pv.price SEPARATOR ',') as size_list,
                GROUP_CONCAT(DISTINCT pc.color_name ORDER BY pc.price SEPARATOR ',') as color_list,
                COUNT(DISTINCT pc.id) as color_count,
                COUNT(DISTINCT pv.id) as variant_count,
                
                AVG(r.rating) AS avg_rating,
                COUNT(DISTINCT r.id) AS rating_count,
                COALESCE(SUM(si.quantity), 0) AS total_sold,
                COUNT(DISTINCT rv.id) as recent_views
            FROM products p
            LEFT JOIN product_variants pv ON p.id = pv.product_id
            LEFT JOIN product_colors pc ON p.id = pc.product_id
            LEFT JOIN product_ratings r ON r.product_id = p.id
            LEFT JOIN sold_items si ON si.product_id = p.id
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
// 🔥 8. GET TRENDING PRODUCTS
// ====================================
function getTrendingProducts($conn, $limit = 10)
{
    $sql = "SELECT 
                p.id,
                p.product_name,
                p.main_image,
                p.category_id,
                p.view_count,
                p.unique_view_count,
                
                COALESCE(MIN(pv.price), 0) as min_size_price,
                COALESCE(MAX(pv.price), 0) as max_size_price,
                COALESCE(MIN(pc.price), 0) as min_color_price,
                COALESCE(MAX(pc.price), 0) as max_color_price,
                
                COALESCE(
                    NULLIF(MIN(pv.price), 0),
                    NULLIF(MIN(pc.price), 0),
                    p.price
                ) as price,
                
                COALESCE(MIN(pv.percent), 0) as percent,
                COALESCE(MAX(pv.discount), 0) as discount,
                
                GROUP_CONCAT(DISTINCT pv.size ORDER BY pv.price SEPARATOR ',') as size_list,
                GROUP_CONCAT(DISTINCT pc.color_name ORDER BY pc.price SEPARATOR ',') as color_list,
                COUNT(DISTINCT pc.id) as color_count,
                COUNT(DISTINCT pv.id) as variant_count,
                
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
// 9. GET SIMILAR VIEWED PRODUCTS
// ====================================
function getSimilarViewedProducts($conn, $product_id, $limit = 10)
{
    $session_id = session_id();

    $sql = "SELECT 
                p.id,
                p.product_name,
                p.main_image,
                p.view_count,
                p.unique_view_count,
                
                COALESCE(MIN(pv.price), 0) as min_size_price,
                COALESCE(MAX(pv.price), 0) as max_size_price,
                COALESCE(MIN(pc.price), 0) as min_color_price,
                COALESCE(MAX(pc.price), 0) as max_color_price,
                
                COALESCE(
                    NULLIF(MIN(pv.price), 0),
                    NULLIF(MIN(pc.price), 0),
                    p.price
                ) as price,
                
                COALESCE(MIN(pv.percent), 0) as percent,
                COALESCE(MAX(pv.discount), 0) as discount,
                
                GROUP_CONCAT(DISTINCT pv.size ORDER BY pv.price SEPARATOR ',') as size_list,
                GROUP_CONCAT(DISTINCT pc.color_name ORDER BY pc.price SEPARATOR ',') as color_list,
                COUNT(DISTINCT pc.id) as color_count,
                COUNT(DISTINCT pv.id) as variant_count,
                
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
function getFlashSaleProducts($conn, $limit = 10)
{
    $sql = "SELECT 
                p.id,
                p.product_name,
                p.main_image,
                p.view_count,
                p.unique_view_count,
                
                COALESCE(MIN(pv.price), 0) as min_size_price,
                COALESCE(MAX(pv.price), 0) as max_size_price,
                COALESCE(MIN(pc.price), 0) as min_color_price,
                COALESCE(MAX(pc.price), 0) as max_color_price,
                
                COALESCE(
                    NULLIF(MIN(pv.price), 0),
                    NULLIF(MIN(pc.price), 0),
                    p.price
                ) as price,
                
                COALESCE(MIN(pv.percent), 0) as percent,
                COALESCE(MAX(pv.discount), 0) as discount,
                
                GROUP_CONCAT(DISTINCT pv.size ORDER BY pv.price SEPARATOR ',') as size_list,
                GROUP_CONCAT(DISTINCT pc.color_name ORDER BY pc.price SEPARATOR ',') as color_list,
                COUNT(DISTINCT pc.id) as color_count,
                COUNT(DISTINCT pv.id) as variant_count
            FROM products p
            LEFT JOIN product_variants pv ON p.id = pv.product_id
            LEFT JOIN product_colors pc ON p.id = pc.product_id
            WHERE (pv.discount > 0 OR pc.price IS NOT NULL)
            GROUP BY p.id
            HAVING COALESCE(MAX(pv.discount), 0) >= 10
            ORDER BY discount DESC, p.view_count DESC
            LIMIT ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();

    return $stmt->get_result();
}

// ====================================
// 11. BATCH UPDATE ALL VIEW COUNTS
// ====================================
function batchUpdateAllViewCounts($conn)
{
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
// 12. GET VIEW STATISTICS
// ====================================
function getViewStatistics($conn)
{
    $stats = [];

    $total_sql = "SELECT COUNT(*) as total FROM recent_views";
    $stats['total_views'] = $conn->query($total_sql)->fetch_assoc()['total'];

    $unique_sql = "SELECT COUNT(DISTINCT COALESCE(user_id, session_id)) as total FROM recent_views";
    $stats['unique_viewers'] = $conn->query($unique_sql)->fetch_assoc()['total'];

    $today_sql = "SELECT COUNT(*) as total FROM recent_views WHERE DATE(viewed_at) = CURDATE()";
    $stats['today_views'] = $conn->query($today_sql)->fetch_assoc()['total'];

    $week_sql = "SELECT COUNT(*) as total FROM recent_views WHERE viewed_at > DATE_SUB(NOW(), INTERVAL 7 DAY)";
    $stats['week_views'] = $conn->query($week_sql)->fetch_assoc()['total'];

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
function getMostViewedProducts($conn, $limit = 10)
{
    $sql = "SELECT 
                p.id,
                p.product_name,
                p.main_image,
                p.description,
                p.view_count,
                p.unique_view_count,
                
                COALESCE(MIN(pv.price), 0) as min_size_price,
                COALESCE(MAX(pv.price), 0) as max_size_price,
                COALESCE(MIN(pc.price), 0) as min_color_price,
                COALESCE(MAX(pc.price), 0) as max_color_price,
                
                COALESCE(
                    NULLIF(MIN(pv.price), 0),
                    NULLIF(MIN(pc.price), 0),
                    p.price
                ) as price,
                
                COALESCE(MIN(pv.percent), 0) as percent,
                COALESCE(MAX(pv.discount), 0) as discount,
                
                GROUP_CONCAT(DISTINCT pv.size ORDER BY pv.price SEPARATOR ',') as size_list,
                GROUP_CONCAT(DISTINCT pc.color_name ORDER BY pc.price SEPARATOR ',') as color_list,
                COUNT(DISTINCT pc.id) as color_count,
                COUNT(DISTINCT pv.id) as variant_count
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

// ====================================
// 🔥 SMART PRICE DISPLAY - CORRECT FORMULA
// Formula: (size_price × markup%) + color_price
// ====================================
function calculateSmartPriceDisplay($product)
{
    $min_size = floatval($product['min_size_price'] ?? 0);
    $max_size = floatval($product['max_size_price'] ?? 0);
    $min_color = floatval($product['min_color_price'] ?? 0);
    $max_color = floatval($product['max_color_price'] ?? 0);
    $base_price = floatval($product['base_price'] ?? $product['price'] ?? 0);

    // ✅ GET DISCOUNT & PERCENT
    $discount = floatval($product['discount'] ?? 0);
    $percent = floatval($product['percent'] ?? 0);

    // ⚠️ IMPORTANT: min_size_price & max_size_price are ALREADY combined (size + color)
    // So we need to apply markup ONLY to the size part
    // Extract individual components first:
    
    // If we're getting combined prices from SQL query:
    // min_size_price = min_variant_price + min_color_price
    // max_size_price = max_variant_price + max_color_price
    
    // We need to separate them to apply markup correctly
    $variant_min = $min_size - $min_color;  // Extract pure variant price
    $variant_max = $max_size - $max_color;  // Extract pure variant price
    
    // Ensure they don't go negative
    if ($variant_min < 0) $variant_min = 0;
    if ($variant_max < 0) $variant_max = 0;

    // 🔥 APPLY MARKUP ONLY TO VARIANT PRICE, THEN ADD COLOR
    // Step 1: Apply markup to variant price
    if ($percent > 0) {
        $variant_min = $variant_min + ($variant_min * $percent / 100);
        $variant_max = $variant_max + ($variant_max * $percent / 100);
    }

    // Step 2: Add color prices (no markup on color)
    $min_final = $variant_min + $min_color;
    $max_final = $variant_max + $max_color;

    $result = [
        'has_range' => false,
        'min_price' => $min_final,
        'max_price' => $max_final,
        'display_price' => '₱' . number_format($min_final, 2),
        'discount' => $discount,
        'percent' => $percent
    ];

    // Has range if min != max
    if ($min_final != $max_final) {
        $result['has_range'] = true;
        $result['display_price'] = '₱' . number_format($min_final, 2) . ' - ₱' . number_format($max_final, 2);
    }

    return $result;
}


// ====================================
// 🔥 RENDER SMART PRICE (HTML) - WITH DISCOUNT BADGE
// ====================================
function renderSmartPrice($priceData, $showBadge = false)
{
    $html = '<div class="product-price flex items-center gap-2">';

    if ($priceData['has_range']) {
        $html .= '<span class="price-range font-bold text-gray-900">' . $priceData['display_price'] . '</span>';
        if ($showBadge) {
            $html .= ' <span class="badge badge-info text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded">Multiple variants</span>';
        }
    } else {
        $html .= '<span class="price-single font-bold text-gray-900">' . $priceData['display_price'] . '</span>';
    }

    // ✅ SHOW DISCOUNT BADGE IF DISCOUNT > 0 (display only)
    if (!empty($priceData['discount']) && floatval($priceData['discount']) > 0) {
        $discountPercent = round(floatval($priceData['discount']));
        $html .= ' <span class="discount-badge text-xs font-bold bg-red-500 text-white px-2 py-1 rounded">-' . $discountPercent . '%</span>';
    }

    $html .= '</div>';

    return $html;
}