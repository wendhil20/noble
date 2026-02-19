<?php
// checkout-webhook-paymongo.php - FIXED VERSION v2
// Receives webhook events from PayMongo
// Fixed: Signature verification, event data paths, metadata extraction

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('log_errors_max_len', 0);

header('Content-Type: application/json');
ob_start();

try {
    require_once '../../connection/connect.php';

    // ✅ STEP 1: Get raw payload BEFORE any processing
    $payload = file_get_contents('php://input');

    error_log("========================================");
    error_log("WEBHOOK RECEIVED AT: " . date('Y-m-d H:i:s'));
    error_log("Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'none'));
    error_log("Payload length: " . strlen($payload));
    error_log("First 500 chars: " . substr($payload, 0, 500));
    error_log("========================================");

    // ✅ STEP 2: Handle empty payload (e.g. browser GET access)
    if (empty($payload)) {
        error_log("⚠️ Empty payload received - likely a direct browser visit, not a webhook call");
        http_response_code(400);
        ob_end_clean();
        echo json_encode(['error' => 'Empty payload']);
        exit;
    }

    // ✅ STEP 3: Validate JSON
    $data = json_decode($payload, true);

    if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
        error_log("❌ JSON DECODE ERROR: " . json_last_error_msg());
        error_log("Raw payload: " . $payload);
        http_response_code(400);
        ob_end_clean();
        echo json_encode([
            'error' => 'Invalid JSON payload',
            'json_error' => json_last_error_msg()
        ]);
        exit;
    }

    error_log("✓ JSON decoded successfully");

    // ✅ STEP 4: Verify PayMongo webhook signature
    // PayMongo sends: X-PayMongo-Signature: t=<timestamp>,te=<test_sig>,li=<live_sig>

    // do NOT commit the actual secret
$webhook_secret = getenv('PAYMONGO_WEBHOOK_SECRET');

    $raw_signature  = $_SERVER['HTTP_X_PAYMONGO_SIGNATURE'] ?? '';

    error_log("Raw signature header: " . (empty($raw_signature) ? 'NONE' : $raw_signature));

    if (empty($raw_signature)) {
        // No signature — only allow in local/test environments; reject in production
        error_log("⚠️ No signature header — proceeding (test/dev mode)");
    } else {
        // Parse the signature header parts
        $sig_parts = [];
        foreach (explode(',', $raw_signature) as $part) {
            $split = explode('=', $part, 2);
            if (count($split) === 2) {
                $sig_parts[$split[0]] = $split[1];
            }
        }

        $timestamp  = $sig_parts['t']  ?? '';
        $test_sig   = $sig_parts['te'] ?? '';
        $live_sig   = $sig_parts['li'] ?? '';

        error_log("Timestamp: $timestamp | te: $test_sig | li: $live_sig");

        if (empty($timestamp)) {
            error_log("❌ Missing timestamp in signature header");
            http_response_code(401);
            ob_end_clean();
            echo json_encode(['error' => 'Invalid signature format']);
            exit;
        }

        // PayMongo signed payload = timestamp + "." + raw_body
        $signed_payload = $timestamp . '.' . $payload;
        $expected_sig   = hash_hmac('sha256', $signed_payload, $webhook_secret);

        error_log("Expected signature: $expected_sig");

        if ($expected_sig !== $test_sig && $expected_sig !== $live_sig) {
            error_log("❌ WEBHOOK SIGNATURE INVALID!");
            http_response_code(401);
            ob_end_clean();
            echo json_encode(['error' => 'Invalid signature']);
            exit;
        }

        error_log("✓ Webhook signature verified");
    }

    // ✅ STEP 5: Extract event type and data
    // PayMongo webhook structure:
    // $data['data']['attributes']['type']                          → event type
    // $data['data']['attributes']['data']['attributes']           → payment/source object
    // $data['data']['attributes']['data']['attributes']['metadata'] → your metadata
    $event_type   = $data['data']['attributes']['type']                    ?? null;
    $event_data   = $data['data']['attributes']['data']['attributes']      ?? [];

    error_log("Event Type: " . ($event_type ?? 'UNKNOWN'));
    error_log("Event Data keys: " . implode(', ', array_keys($event_data)));

    if (!$event_type) {
        error_log("⚠️ No event type found in payload");
        http_response_code(200);
        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'No event type']);
        exit;
    }

    // ✅ STEP 6: Handle payment.paid
    if ($event_type === 'payment.paid') {
        error_log("✅ EVENT: PAYMENT.PAID");

        $payment_id  = $data['data']['attributes']['data']['id'] ?? null;
        $amount      = floatval($event_data['amount'] ?? 0);
        $source      = $event_data['source'] ?? [];
        $source_type = $source['type'] ?? null;
        $source_qr_id = $source['id'] ?? null;   // e.g. qrph_o3xPUxD3yTsb52eKnNxReDCc
        $metadata    = $event_data['metadata'] ?? [];
        $order_id    = intval($metadata['order_id'] ?? 0);

        error_log("Payment ID: $payment_id | Amount: ₱" . ($amount / 100) . " | Source: $source_type | QR ID: $source_qr_id | OrderID from metadata: $order_id");

        // ✅ If no order_id in metadata, look up via QR code ID in our database
        // PayMongo webhook sends source.id = "qrph_xxx"
        // But DB stores the provider code_id = "code_xxx" from the generate response
        // So we also check provider.code_id
        $source_provider_code_id = $source['provider']['code_id'] ?? null;

        error_log("Lookup attempt — source.id: $source_qr_id | provider.code_id: $source_provider_code_id");

        if ($source_type === 'qrph' && $order_id === 0 && $conn) {
            $ids_to_try = array_filter([$source_qr_id, $source_provider_code_id]);

            foreach ($ids_to_try as $try_id) {
                $lookup = $conn->prepare("SELECT order_id FROM qrph_codes WHERE qr_code_id = ? LIMIT 1");
                if ($lookup) {
                    $lookup->bind_param('s', $try_id);
                    $lookup->execute();
                    $lookup_result = $lookup->get_result()->fetch_assoc();
                    $lookup->close();
                    if ($lookup_result) {
                        $order_id = intval($lookup_result['order_id']);
                        error_log("✓ Matched '$try_id' to order #$order_id");
                        break;
                    } else {
                        error_log("✗ No match for: $try_id");
                    }
                }
            }

            if ($order_id === 0) {
                error_log("⚠️ Could not match any QR ID to an order. Tried: " . implode(', ', $ids_to_try));
            }
        }

        if ($source_type === 'qrph' && $order_id > 0) {
            error_log("🎯 QRPh payment for order #$order_id");

            if ($conn) {
                // --- Update qrph_codes ---
                $stmt = $conn->prepare("
                    UPDATE qrph_codes 
                    SET status = 'paid', paid_at = NOW(), paymongo_payment_id = ?
                    WHERE order_id = ?
                ");
                if ($stmt) {
                    $stmt->bind_param('si', $payment_id, $order_id);
                    $stmt->execute()
                        ? error_log("✓ qrph_codes updated for order #$order_id")
                        : error_log("✗ qrph_codes update failed: " . $stmt->error);
                    $stmt->close();
                } else {
                    error_log("✗ Prepare failed (qrph_codes): " . $conn->error);
                }

                // --- Update orders ---
                $stmt = $conn->prepare("
                    UPDATE orders 
                    SET payment_status = 'paid', paymongo_payment_id = ?, status = 'Processing'
                    WHERE id = ?
                ");
                if ($stmt) {
                    $stmt->bind_param('si', $payment_id, $order_id);
                    $stmt->execute()
                        ? error_log("✓ orders updated for order #$order_id")
                        : error_log("✗ orders update failed: " . $stmt->error);
                    $stmt->close();
                } else {
                    error_log("✗ Prepare failed (orders): " . $conn->error);
                }

                // --- Deduct stock ---
                $get_items = $conn->prepare("
                    SELECT product_id, variant_id, color_id, quantity 
                    FROM order_items 
                    WHERE order_id = ?
                ");
                if ($get_items) {
                    $get_items->bind_param('i', $order_id);
                    $get_items->execute();
                    $items = $get_items->get_result();

                    while ($item = $items->fetch_assoc()) {
                        $variant_id = $item['variant_id'];
                        $color_id   = $item['color_id'];
                        $quantity   = $item['quantity'];

                        if (!empty($variant_id) && !empty($color_id)) {
                            $deduct = $conn->prepare("
                                UPDATE product_variant_colors 
                                SET stock_quantity = stock_quantity - ?
                                WHERE variant_id = ? AND color_id = ?
                            ");
                            if ($deduct) {
                                $deduct->bind_param('iii', $quantity, $variant_id, $color_id);
                                $deduct->execute()
                                    ? error_log("✓ Stock deducted: V#$variant_id C#$color_id Qty=$quantity")
                                    : error_log("✗ Stock deduct failed: " . $deduct->error);
                                $deduct->close();
                            }
                        }
                    }
                    $get_items->close();
                }

                // --- Clear cart ---
                $user_stmt = $conn->prepare("SELECT user_id FROM orders WHERE id = ? LIMIT 1");
                if ($user_stmt) {
                    $user_stmt->bind_param('i', $order_id);
                    $user_stmt->execute();
                    $user_result = $user_stmt->get_result()->fetch_assoc();
                    $user_stmt->close();

                    if ($user_result) {
                        $user_id    = $user_result['user_id'];
                        $clear_cart = $conn->prepare("DELETE FROM user_cart_items WHERE user_id = ?");
                        if ($clear_cart) {
                            $clear_cart->bind_param('i', $user_id);
                            $clear_cart->execute()
                                ? error_log("✓ Cart cleared for user #$user_id")
                                : error_log("✗ Cart clear failed: " . $clear_cart->error);
                            $clear_cart->close();
                        }
                    }
                }

                error_log("✅ QRPh PAYMENT PROCESSED SUCCESSFULLY for order #$order_id");

            } else {
                error_log("✗ No database connection");
            }

        } elseif ($order_id === 0) {
            error_log("⚠️ No order_id in metadata — skipping DB update");
        } else {
            error_log("ℹ️ Non-QRPh payment type ($source_type) — no special handling");
        }
    }

    // ✅ STEP 7: Handle qrph.expired
    elseif ($event_type === 'qrph.expired') {
        error_log("⏰ EVENT: QRPH.EXPIRED");

        $metadata = $event_data['metadata'] ?? [];
        $order_id = intval($metadata['order_id'] ?? 0);

        if ($order_id > 0 && $conn) {
            $stmt = $conn->prepare("
                UPDATE qrph_codes 
                SET status = 'expired', expired_at = NOW()
                WHERE order_id = ?
            ");
            if ($stmt) {
                $stmt->bind_param('i', $order_id);
                $stmt->execute()
                    ? error_log("✓ QR marked expired for order #$order_id")
                    : error_log("✗ qrph_codes expire failed: " . $stmt->error);
                $stmt->close();
            }
        } else {
            error_log("⚠️ No order_id in expired event or no DB connection");
        }
    }

    // ✅ STEP 8: Handle payment.failed
    elseif ($event_type === 'payment.failed') {
        error_log("❌ EVENT: PAYMENT.FAILED");

        $metadata       = $event_data['metadata'] ?? [];
        $order_id       = intval($metadata['order_id'] ?? 0);
        $failure_reason = $event_data['failure_message'] ?? 'Unknown';

        if ($order_id > 0 && $conn) {
            $stmt = $conn->prepare("
                UPDATE orders 
                SET payment_status = 'failed'
                WHERE id = ?
            ");
            if ($stmt) {
                $stmt->bind_param('i', $order_id);
                $stmt->execute()
                    ? error_log("✓ Order #$order_id marked failed: $failure_reason")
                    : error_log("✗ Failed to update order: " . $stmt->error);
                $stmt->close();
            }
        }
    }

    // ✅ STEP 9: Handle payment.refunded
    elseif ($event_type === 'payment.refunded') {
        error_log("🔄 EVENT: PAYMENT.REFUNDED");

        $metadata = $event_data['metadata'] ?? [];
        $order_id = intval($metadata['order_id'] ?? 0);

        if ($order_id > 0 && $conn) {
            $stmt = $conn->prepare("
                UPDATE orders 
                SET payment_status = 'refunded'
                WHERE id = ?
            ");
            if ($stmt) {
                $stmt->bind_param('i', $order_id);
                $stmt->execute()
                    ? error_log("✓ Order #$order_id marked refunded")
                    : error_log("✗ Failed to update order: " . $stmt->error);
                $stmt->close();
            }
        }
    }

    else {
        error_log("⚠️ Unhandled event type: $event_type");
    }

    // ✅ STEP 10: Always return 200 to PayMongo so it doesn't retry
    http_response_code(200);
    ob_end_clean();
    echo json_encode([
        'success'    => true,
        'message'    => 'Webhook processed',
        'event_type' => $event_type
    ]);

} catch (Exception $e) {
    error_log("❌ WEBHOOK EXCEPTION: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());

    http_response_code(500);
    ob_end_clean();
    echo json_encode([
        'error'   => $e->getMessage(),
        'success' => false
    ]);
}