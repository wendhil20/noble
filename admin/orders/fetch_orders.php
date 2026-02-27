<?php
//fetch_orders.php
session_name("nobleadmin");
session_start();
header('Content-Type: application/json');
require_once '../../connection/connect.php';

// 1. Make sure the user is logged in
if (!isset($_SESSION['noble_user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'User not logged in']);
    exit;
}

// 2. Lookup this user's emp_id
$email = $_SESSION['noble_user'];
$stmt = $conn->prepare("SELECT id FROM nobleaccount WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->bind_result($emp_id);
$stmt->fetch();
$stmt->close();

if (!$emp_id) {
    http_response_code(403);
    echo json_encode(['error' => 'Employee record not found']);
    exit;
}

// 3. Fetch all orders assigned to this emp_id including payment_status
$stmt = $conn->prepare("
    SELECT 
        id,
        customer_name,
        email,
        mobile,
        address,
        zipcode,
        total,
        created_at,
        COALESCE(status, 'pending')         AS status,
        COALESCE(payment_status, 'pending') AS payment_status,
        COALESCE(delivery_fee, 0)           AS delivery_fee,
        COALESCE(vat_amount, 0)             AS vat_amount,
        COALESCE(subtotal, 0)               AS subtotal
    FROM orders
    WHERE emp_id = ?
    ORDER BY created_at DESC
");
$stmt->bind_param("i", $emp_id);
$stmt->execute();
$orders_result = $stmt->get_result();
$stmt->close();

$orders = [];

while ($order = $orders_result->fetch_assoc()) {
    $order['created_at'] = date('M j, Y g:i A', strtotime($order['created_at']));

    // ✅ Fetch replacement requests WITH full status details per request
    $replaceStmt = $conn->prepare("
        SELECT 
            id,
            order_item_id,
            user_email,
            reason,
            details,
            replacement_quantity,
            po_number,
            qr_code,
            warehouse_location,
            received_status,
            received_by,
            received_at,
            defect_image_overview,
            defect_image_closeup,
            defect_image_detail,
            status,
            admin_notes,
            created_at,
            updated_at,
            delivery_schedule_id,
            receiver_id
        FROM replacement_requests
        WHERE order_id = ?
        ORDER BY created_at DESC
    ");
    $replaceStmt->bind_param("i", $order['id']);
    $replaceStmt->execute();
    $replaceResult = $replaceStmt->get_result();

    $replacements = [];
    $statusSummary = [
        'pending'      => 0,
        'approved'     => 0,
        'processing'   => 0,
        'in_warehouse' => 0,
        'delivered'    => 0,
        'other'        => 0,
    ];

    while ($rep = $replaceResult->fetch_assoc()) {
        // Normalize status key for counting
        $rawStatus = strtolower(trim($rep['status'] ?? 'pending'));
        $normalizedStatus = str_replace(' ', '_', $rawStatus); // "In Warehouse" → "in_warehouse"

        if (array_key_exists($normalizedStatus, $statusSummary)) {
            $statusSummary[$normalizedStatus]++;
        } else {
            $statusSummary['other']++;
        }

        $rep['status_normalized'] = $normalizedStatus;
        $replacements[] = $rep;
    }
    $replaceStmt->close();

    $order['has_replacement_requests'] = count($replacements) > 0;
    $order['replacement_count']        = count($replacements);
    $order['replacements']             = $replacements;         // ✅ Full replacement details
    $order['replacement_status_summary'] = $statusSummary;     // ✅ Count per status

    // ✅ Is everything delivered?
    $totalReplacements = count($replacements);
    $deliveredCount    = $statusSummary['delivered'];
    $order['all_replacements_delivered'] = ($totalReplacements > 0 && $deliveredCount === $totalReplacements);

    // 4. For each order, fetch its items WITH SUPPLIER INFO
    $itemStmt = $conn->prepare("
        SELECT 
            oi.product_name,
            oi.size,
            oi.variant_color,
            oi.codename,
            oi.descrip6,
            oi.descrip7,
            oi.price,
            oi.quantity,
            oi.subtotal,
            oi.origin,
            oi.product_id,
            oi.supplier_id,
            oi.manual_supplier_name,
            oi.delivery_fee_per_item,
            oi.item_total_delivery,
            CASE 
                WHEN oi.supplier_id IS NOT NULL AND oi.supplier_id > 0 THEN sl.business_name
                WHEN oi.manual_supplier_name IS NOT NULL AND oi.manual_supplier_name != '' THEN oi.manual_supplier_name
                ELSE 'Not Assigned'
            END AS supplier_name,
            CASE 
                WHEN oi.supplier_id IS NOT NULL AND oi.supplier_id > 0 THEN sl.primary_contact_name
                ELSE NULL
            END AS primary_contact_name,
            CASE 
                WHEN oi.supplier_id IS NOT NULL AND oi.supplier_id > 0 THEN sl.email_address
                ELSE NULL
            END AS supplier_email,
            CASE 
                WHEN oi.supplier_id IS NOT NULL AND oi.supplier_id > 0 THEN sl.phone_number
                ELSE NULL
            END AS supplier_phone,
            CASE 
                WHEN oi.supplier_id IS NOT NULL AND oi.supplier_id > 0 THEN sl.business_type
                ELSE NULL
            END AS business_type,
            CASE 
                WHEN oi.supplier_id IS NOT NULL AND oi.supplier_id > 0 THEN sl.country_region
                ELSE NULL
            END AS country_region,
            CASE 
                WHEN oi.supplier_id IS NOT NULL AND oi.supplier_id > 0 THEN 'database'
                WHEN oi.manual_supplier_name IS NOT NULL AND oi.manual_supplier_name != '' THEN 'manual'
                ELSE 'none'
            END AS supplier_source
        FROM order_items oi
        LEFT JOIN supplier_list sl ON oi.supplier_id = sl.id AND sl.status = 'active' AND oi.supplier_id > 0
        WHERE oi.order_id = ?
    ");
    $itemStmt->bind_param("i", $order['id']);
    $itemStmt->execute();
    $itemsRes = $itemStmt->get_result();

    $items = [];
    while ($it = $itemsRes->fetch_assoc()) {
        $it['price']                  = number_format((float)$it['price'], 2, '.', '');
        $it['subtotal']               = number_format((float)$it['subtotal'], 2, '.', '');
        $it['delivery_fee_per_item']  = number_format((float)$it['delivery_fee_per_item'], 2, '.', '');
        $it['item_total_delivery']    = number_format((float)$it['item_total_delivery'], 2, '.', '');

        $it['supplier_contact']  = $it['primary_contact_name'] ?? '';
        $it['supplier_email']    = $it['supplier_email'] ?? '';
        $it['supplier_phone']    = $it['supplier_phone'] ?? '';
        $it['supplier_type']     = $it['business_type'] ?? '';
        $it['supplier_location'] = $it['country_region'] ?? '';

        $it['is_manual_supplier']   = ($it['supplier_source'] === 'manual');
        $it['is_database_supplier'] = ($it['supplier_source'] === 'database');
        $it['has_supplier']         = ($it['supplier_source'] !== 'none');

        $items[] = $it;
    }
    $itemStmt->close();

    $order['items'] = $items;
    $orders[] = $order;
}

echo json_encode($orders);
?> 