<?php
// warehouse_head_assignment_ajax.php - AJAX handlers only
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";
require_role(['superadmin', 'warehouse']);

if (!isset($_SESSION['noble_user'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Validate is_head
$sessionUser = $_SESSION['noble_user'];
$is_head = false;

if (is_array($sessionUser)) {
    $is_head = isset($sessionUser['is_head']) && (int) $sessionUser['is_head'] === 1;
} else {
    $lookup = ctype_digit((string) $sessionUser)
        ? ["id = ?", "i", (int) $sessionUser]
        : ["email = ?", "s", (string) $sessionUser];

    $sql = "SELECT is_head FROM nobleaccount WHERE {$lookup[0]} LIMIT 1";
    if ($s = $conn->prepare($sql)) {
        $s->bind_param($lookup[1], $lookup[2]);
        $s->execute();
        $r = $s->get_result()->fetch_assoc();
        $s->close();
        if ($r) $is_head = (int) $r['is_head'] === 1;
    }
}

if (!$is_head) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Head access only']);
    exit();
}

header('Content-Type: application/json');
$action = $_POST['action'] ?? '';

// ─── assign_warehouse ─────────────────────────────────────────────────────────
if ($action === 'assign_warehouse') {
    $order_id            = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    $warehouse_employee_id = isset($_POST['warehouse_employee_id']) && $_POST['warehouse_employee_id'] !== ''
        ? intval($_POST['warehouse_employee_id'])
        : null;

    if ($order_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
        exit();
    }

    $stmt = $conn->prepare("UPDATE orders SET warehouse_employee_id = ? WHERE id = ?");
    $stmt->bind_param("ii", $warehouse_employee_id, $order_id);

    echo $stmt->execute()
        ? json_encode([
            'success' => true,
            'message' => $warehouse_employee_id ? 'Employee assigned successfully' : 'Order unassigned'
        ])
        : json_encode(['success' => false, 'message' => 'Failed to update assignment']);

    $stmt->close();
    exit();
}

// ─── bulk_unassign ────────────────────────────────────────────────────────────
if ($action === 'bulk_unassign') {
    $order_ids = isset($_POST['order_ids']) ? json_decode($_POST['order_ids'], true) : [];

    if (empty($order_ids) || !is_array($order_ids)) {
        echo json_encode(['success' => false, 'message' => 'No orders selected']);
        exit();
    }

    $ph   = str_repeat('?,', count($order_ids) - 1) . '?';
    $stmt = $conn->prepare("UPDATE orders SET warehouse_employee_id = NULL WHERE id IN ($ph)");
    $stmt->bind_param(str_repeat('i', count($order_ids)), ...$order_ids);

    echo $stmt->execute()
        ? json_encode(['success' => true, 'message' => count($order_ids) . ' orders unassigned'])
        : json_encode(['success' => false, 'message' => 'Failed to unassign orders']);

    $stmt->close();
    exit();
}

// ─── bulk_assign ──────────────────────────────────────────────────────────────
if ($action === 'bulk_assign') {
    $order_ids             = isset($_POST['order_ids']) ? json_decode($_POST['order_ids'], true) : [];
    $warehouse_employee_id = isset($_POST['warehouse_employee_id']) ? intval($_POST['warehouse_employee_id']) : 0;

    if (empty($order_ids) || !is_array($order_ids) || $warehouse_employee_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        exit();
    }

    $ph     = str_repeat('?,', count($order_ids) - 1) . '?';
    $params = array_merge([$warehouse_employee_id], $order_ids);
    $stmt   = $conn->prepare("UPDATE orders SET warehouse_employee_id = ? WHERE id IN ($ph)");
    $stmt->bind_param(str_repeat('i', count($params)), ...$params);

    echo $stmt->execute()
        ? json_encode(['success' => true, 'message' => count($order_ids) . ' orders assigned'])
        : json_encode(['success' => false, 'message' => 'Failed to assign orders']);

    $stmt->close();
    exit();
}

// ─── Unknown action ───────────────────────────────────────────────────────────
echo json_encode(['success' => false, 'message' => 'Unknown action']);