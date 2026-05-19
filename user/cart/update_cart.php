<?php

include ROOT_PATH . '/connection/connect.php';

$user_id = $_SESSION['user_id'] ?? null;

// Check if user is logged in
if (!$user_id) {
    // Handle based on request type
    if (isset($_POST['item_id']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')) {
        header('Content-Type: application/json');
        echo json_encode([
            "success" => false,
            "message" => "Please log in to update your cart."
        ]);
        exit;
    } else {
        $_SESSION['checkout_notice'] = "Please log in to update your cart.";
        header("Location: " . BASE_URL . "/cartview");
        exit;
    }
}

// ✅ CASE 1: AJAX auto-save (single item update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['item_id'])) {
    header('Content-Type: application/json');

    $item_id = (int)$_POST['item_id'];
    $quantity = max(1, (int)($_POST['quantity'] ?? 1));

    try {
        $stmt = $conn->prepare("UPDATE user_cart_items SET quantity = ? WHERE id = ? AND user_id = ?");
        $stmt->bind_param("iii", $quantity, $item_id, $user_id);

        if ($stmt->execute() && $stmt->affected_rows > 0) {
            // Get updated cart totals
            $total_stmt = $conn->prepare("SELECT SUM(quantity * price) AS total FROM user_cart_items WHERE user_id = ?");
            $total_stmt->bind_param("i", $user_id);
            $total_stmt->execute();
            $result = $total_stmt->get_result();
            $row = $result->fetch_assoc();
            $total_price = floatval($row['total'] ?? 0);

            echo json_encode([
                "success" => true,
                "message" => "Quantity updated successfully.",
                "total_price" => $total_price,
                "new_quantity" => $quantity
            ]);

            $total_stmt->close();
        } else {
            echo json_encode([
                "success" => false,
                "message" => "Failed to update item or item not found."
            ]);
        }

        $stmt->close();
    } catch (Exception $e) {
        echo json_encode([
            "success" => false,
            "message" => "Database error: " . $e->getMessage()
        ]);
    }

    $conn->close();
    exit;
}

// ✅ CASE 2: Manual bulk update (form submission)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quantities']) && is_array($_POST['quantities'])) {
    $quantities = $_POST['quantities'];
    $updated_items = 0;
    $errors = [];

    if (empty($quantities)) {
        $_SESSION['checkout_notice'] = "No items to update.";
        header("Location: " . BASE_URL . "/cartview");
        exit;
    }

    // Begin transaction for consistency
    $conn->begin_transaction();

    try {
        $stmt = $conn->prepare("UPDATE user_cart_items SET quantity = ? WHERE id = ? AND user_id = ?");
        
        foreach ($quantities as $item_id => $qty) {
            $item_id = (int)$item_id;
            $qty = max(1, (int)$qty);
            
            $stmt->bind_param("iii", $qty, $item_id, $user_id);
            
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $updated_items++;
            } else {
                $errors[] = "Failed to update item ID: $item_id";
            }
        }

        $stmt->close();

        if (empty($errors)) {
            $conn->commit();
            $_SESSION['checkout_notice'] = $updated_items > 0
                ? "Successfully updated $updated_items item(s) in your cart."
                : "No changes were made to your cart.";
        } else {
            $conn->rollback();
            $_SESSION['checkout_notice'] = "Some items could not be updated: " . implode(", ", $errors);
        }

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['checkout_notice'] = "An error occurred while updating your cart. Please try again.";
        error_log("Cart update error: " . $e->getMessage());
    }

    $conn->close();
    header("Location: " . BASE_URL . "/cartview");
    exit;
}

// ✅ Handle invalid requests
$isAjax = isset($_POST['item_id']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode([
        "success" => false,
        "message" => "Invalid AJAX request parameters."
    ]);
} else {
    $_SESSION['checkout_notice'] = "Invalid request method.";
    header("Location: " . BASE_URL . "/cartview");
}

exit;
?>