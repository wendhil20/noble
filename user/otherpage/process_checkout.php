<?php
// process_checkout.php
session_name("nobleuser");
session_start();
include '../../connection/connect.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['customer_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $zipcode = trim($_POST['zipcode'] ?? '');
    $payment_method = trim($_POST['payment_method'] ?? '');
    $billing_address_id = trim($_POST['billing_address_id'] ?? '');
    $latitude = null;
    $longitude = null;

    // Generate reference number
    function generateReferenceNumber() {
        return 'NH' . mt_rand(9800000, 9899999);
    }
    $reference_no = generateReferenceNumber();

    // Get cart items first
    $cart_items = [];
    $total_price = 0;
    
    $stmt = $conn->prepare("
        SELECT uci.*, COALESCE(pv.origin, '') as origin 
        FROM user_cart_items uci 
        LEFT JOIN product_variants pv ON uci.variant_id = pv.id 
        WHERE uci.user_id = ?
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $cart_items[] = $row;
        $total_price += $row['price'] * $row['quantity'];
    }
    $stmt->close();

    // Validation
    $validation_errors = [];

    if (empty($name)) $validation_errors[] = "Full Name is required";
    if (empty($email)) {
        $validation_errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $validation_errors[] = "Invalid email format";
    }

    if (empty($mobile)) {
        $validation_errors[] = "Mobile number is required";
    } else {
        // Clean the mobile number
        $cleaned_mobile = preg_replace('/[\s\-\(\)\+]/', '', $mobile);
        if (preg_match('/^63([0-9]{10})$/', $cleaned_mobile, $matches)) {
            $cleaned_mobile = '0' . $matches[1];
        }
        if (!preg_match('/^09[0-9]{9}$/', $cleaned_mobile)) {
            $validation_errors[] = "Mobile number must be a valid Philippine mobile number";
        } else {
            $mobile = $cleaned_mobile;
        }
    }

    if (empty($address)) $validation_errors[] = "Address is required";
    if (empty($zipcode)) {
        $validation_errors[] = "ZIP Code is required";
    } elseif (!preg_match('/^[0-9]{4}$/', $zipcode)) {
        $validation_errors[] = "ZIP Code must be exactly 4 digits";
    }

    if (empty($payment_method)) $validation_errors[] = "Payment method is required";
    if (empty($cart_items)) $validation_errors[] = "Your cart is empty";

    // Get coordinates if billing address is selected
    if (!empty($billing_address_id) && is_numeric($billing_address_id)) {
        $stmt = $conn->prepare("SELECT latitude, longitude FROM billing_addresses WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $billing_address_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $billing_data = $result->fetch_assoc();
            $latitude = $billing_data['latitude'];
            $longitude = $billing_data['longitude'];
        }
        $stmt->close();
    }

    // If there are validation errors, return them
    if (!empty($validation_errors)) {
        echo json_encode(['success' => false, 'message' => implode(', ', $validation_errors)]);
        exit;
    }

    try {
        // Reset auto-increment if needed
        $conn->query("ALTER TABLE orders AUTO_INCREMENT = 1");
        $conn->query("ALTER TABLE order_items AUTO_INCREMENT = 1");

        // Save order
        $stmt = $conn->prepare("INSERT INTO orders (customer_name, email, mobile, address, zipcode, mode_payment, total, reference_no, billing_address_id, latitude, longitude, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssdsiddi", $name, $email, $mobile, $address, $zipcode, $payment_method, $total_price, $reference_no, $billing_address_id, $latitude, $longitude, $user_id);

        if (!$stmt->execute()) {
            throw new Exception("Failed to create order: " . $stmt->error);
        }

        $order_id = $stmt->insert_id;
        $stmt->close();

        // Save order items
        $stmt = $conn->prepare("INSERT INTO order_items (
            order_id, product_name, codename, type_name, variant_color, size, price, quantity, subtotal, descrip6, descrip7, origin
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $order_items_data = [];
        foreach ($cart_items as $item) {
            $subtotal = $item['price'] * $item['quantity'];
            $product_name = $item['product_name'] ?? $item['variant_name'];
            $codename = $item['codename'] ?? '';
            $type_name = $item['type_name'] ?? '';
            $variant_color = $item['color_name'] ?: ($item['variant_name'] ?? '');
            $size = $item['size'] ?? '';
            $price = $item['price'];
            $quantity = $item['quantity'];
            $desc6 = $item['descrip6'] ?? '';
            $desc7 = $item['descrip7'] ?? '';
            $origin = $item['origin'] ?? '';

            $stmt->bind_param(
                "isssssiiisss",
                $order_id,
                $product_name,
                $codename,
                $type_name,
                $variant_color,
                $size,
                $price,
                $quantity,
                $subtotal,
                $desc6,
                $desc7,
                $origin
            );

            if (!$stmt->execute()) {
                throw new Exception("Failed to save order item: " . $stmt->error);
            }

            // Prepare item data for response
            $details = [];
            if (!empty($type_name)) $details[] = $type_name;
            if (!empty($size) && trim($size) !== '') $details[] = 'Size: ' . $size;
            if (!empty($variant_color)) $details[] = 'Color: ' . $variant_color;
            if (!empty($codename)) $details[] = 'Code: ' . $codename;
            if (!empty($origin)) $details[] = 'Origin: ' . $origin;
            if (!empty($desc6)) $details[] = $desc6;
            if (!empty($desc7)) $details[] = $desc7;

            $order_items_data[] = [
                'product_name' => $product_name,
                'details' => implode('<br>', array_map('htmlspecialchars', $details)),
                'price' => $price,
                'quantity' => $quantity,
                'subtotal' => $subtotal
            ];
        }
        $stmt->close();

        // Clear cart
        $stmt = $conn->prepare("DELETE FROM user_cart_items WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to clear cart: " . $stmt->error);
        }
        $stmt->close();

        // Prepare success response
        $response = [
            'success' => true,
            'message' => 'Order placed successfully!',
            'order' => [
                'id' => $order_id,
                'reference_no' => $reference_no,
                'customer_name' => $name,
                'email' => $email,
                'mobile' => $mobile,
                'address' => $address,
                'payment_method' => $payment_method,
                'total' => $total_price,
                'items' => $order_items_data
            ]
        ];

        echo json_encode($response);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'An error occurred while processing your order. Please try again.']);
        error_log("Checkout error: " . $e->getMessage());
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>