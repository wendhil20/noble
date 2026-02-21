<?php
// checkout-webhook-paymongo.php
// =====================================================================
// ROOT CAUSE FIX:
// PayMongo Checkout Sessions fire "checkout_session.payment.paid"
// NOT "payment.paid" — so stock deduction was NEVER triggered before!
// Also fixed: bind_param type strings for order_items
// =====================================================================

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('log_errors_max_len', 0);

header('Content-Type: application/json');
ob_start();

try {
    require_once '../../connection/connect.php';
    require_once '../../.env.php';

    $payload = file_get_contents('php://input');

    error_log("========================================");
    error_log("WEBHOOK RECEIVED AT: " . date('Y-m-d H:i:s'));
    error_log("Payload length: " . strlen($payload));
    error_log("First 1000 chars: " . substr($payload, 0, 1000));
    error_log("========================================");

    if (empty($payload)) {
        http_response_code(400);
        ob_end_clean();
        echo json_encode(['error' => 'Empty payload']);
        exit;
    }

    $data = json_decode($payload, true);
    if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON DECODE ERROR: " . json_last_error_msg());
        http_response_code(400);
        ob_end_clean();
        echo json_encode(['error' => 'Invalid JSON payload']);
        exit;
    }

    // ── Verify webhook signature ──────────────────────────────────
    $webhook_secret = getenv('PAYMONGO_WEBHOOK_SECRET');
    $raw_signature  = $_SERVER['HTTP_X_PAYMONGO_SIGNATURE'] ?? '';

    if (!empty($raw_signature) && !empty($webhook_secret)) {
        $sig_parts = [];
        foreach (explode(',', $raw_signature) as $part) {
            $split = explode('=', $part, 2);
            if (count($split) === 2) $sig_parts[$split[0]] = $split[1];
        }
        $timestamp = $sig_parts['t']  ?? '';
        $test_sig  = $sig_parts['te'] ?? '';
        $live_sig  = $sig_parts['li'] ?? '';

        if (empty($timestamp)) {
            http_response_code(401);
            ob_end_clean();
            echo json_encode(['error' => 'Invalid signature format']);
            exit;
        }

        $expected_sig = hash_hmac('sha256', $timestamp . '.' . $payload, $webhook_secret);
        if ($expected_sig !== $test_sig && $expected_sig !== $live_sig) {
            error_log("WEBHOOK SIGNATURE INVALID!");
            http_response_code(401);
            ob_end_clean();
            echo json_encode(['error' => 'Invalid signature']);
            exit;
        }
        error_log("Signature verified");
    } else {
        error_log("No signature / no secret — dev/test mode");
    }

    // ── Extract event type ────────────────────────────────────────
    $event_type = $data['data']['attributes']['type'] ?? null;
    error_log("Event type: " . ($event_type ?? 'NULL'));

    if (!$event_type) {
        http_response_code(200);
        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'No event type']);
        exit;
    }

    // ================================================================
    // PAYMENT PAID
    // PayMongo sends DIFFERENT event names per payment method:
    //   "checkout_session.payment.paid"  ← GCash, Card, Maya, GrabPay
    //   "payment.paid"                   ← Direct / QRPh source-based
    // ================================================================
    $is_payment_paid = in_array($event_type, [
        'checkout_session.payment.paid',
        'payment.paid',
    ]);

    if ($is_payment_paid) {
        error_log("PAYMENT PAID EVENT: $event_type");

        $event_obj       = $data['data']['attributes']['data'] ?? [];
        $event_obj_id    = $event_obj['id'] ?? null;
        $event_obj_attrs = $event_obj['attributes'] ?? [];

        // metadata is always at the checkout session / payment attributes level
        $metadata        = $event_obj_attrs['metadata'] ?? [];
        $order_id_direct = intval($metadata['order_id'] ?? 0);
        $temp_ref        = $metadata['temp_ref'] ?? null;

        // payment_id: for checkout_session events, real payment is inside payments[]
        if ($event_type === 'checkout_session.payment.paid') {
            $payments   = $event_obj_attrs['payments'] ?? [];
            $payment_id = !empty($payments) ? ($payments[0]['id'] ?? $event_obj_id) : $event_obj_id;
        } else {
            $payment_id = $event_obj_id;
        }

        error_log("order_id=$order_id_direct | temp_ref=$temp_ref | payment_id=$payment_id");
        error_log("metadata dump: " . json_encode($metadata));

        // ── PATH A: GCash / Card / Maya — order exists, mark paid + deduct ──
        if ($order_id_direct > 0) {
            error_log("PATH A: order #$order_id_direct");

            // Idempotency check
            $chk = $conn->prepare("SELECT payment_status FROM orders WHERE id = ? LIMIT 1");
            if ($chk) {
                $chk->bind_param('i', $order_id_direct);
                $chk->execute();
                $chk_row = $chk->get_result()->fetch_assoc();
                $chk->close();
                if ($chk_row && $chk_row['payment_status'] === 'paid') {
                    error_log("Order #$order_id_direct already paid — skipping");
                    http_response_code(200);
                    ob_end_clean();
                    echo json_encode(['success' => true, 'message' => 'Already processed']);
                    exit;
                }
            }

            $upd = $conn->prepare("
                UPDATE orders
                SET payment_status       = 'paid',
                    paymongo_payment_id  = ?,
                    status               = 'Processing',
                    payment_confirmed_at = NOW()
                WHERE id = ?
            ");
            if ($upd) {
                $upd->bind_param('si', $payment_id, $order_id_direct);
                $upd->execute()
                    ? error_log("Order #$order_id_direct marked paid")
                    : error_log("Order update failed: " . $upd->error);
                $upd->close();
            }

            _deductStockAndClearCart($conn, $order_id_direct);

        // ── PATH B: QRPh — create order from pending session snapshot ──
        } elseif (!empty($temp_ref)) {
            error_log("PATH B: QRPh ref=$temp_ref");

            $ps = $conn->prepare("
                SELECT * FROM qrph_pending_sessions
                WHERE temp_ref = ? AND status = 'pending'
                LIMIT 1
            ");
            if (!$ps) throw new Exception('Prepare failed: ' . $conn->error);
            $ps->bind_param('s', $temp_ref);
            $ps->execute();
            $ps_row = $ps->get_result()->fetch_assoc();
            $ps->close();

            if (!$ps_row) {
                error_log("No pending session for ref=$temp_ref — already processed?");
                http_response_code(200);
                ob_end_clean();
                echo json_encode(['success' => true, 'message' => 'Already processed']);
                exit;
            }

            $snap = json_decode($ps_row['session_data'], true);
            if (!$snap) throw new Exception('Cannot decode session snapshot');

            // Unpack
            $user_id               = intval($snap['user_id']);
            $total_amount          = floatval($snap['amount']);
            $delivery_fee          = floatval($snap['delivery_fee'] ?? 0);
            $customer_name         = $snap['customer_name'] ?? '';
            $email                 = $snap['email'] ?? '';
            $mobile                = $snap['mobile'] ?? '';
            $address               = $snap['address'] ?? '';
            $zipcode               = $snap['zipcode'] ?? '';
            $billing_address_id    = !empty($snap['billing_address_id']) ? intval($snap['billing_address_id']) : null;
            $latitude              = !empty($snap['latitude']) ? floatval($snap['latitude']) : null;
            $longitude             = !empty($snap['longitude']) ? floatval($snap['longitude']) : null;
            $delivery_distance     = floatval($snap['delivery_distance'] ?? 0);
            $delivery_type         = $snap['delivery_type'] ?? 'delivery';
            $assigned_vehicle_id   = !empty($snap['assigned_vehicle_id']) ? intval($snap['assigned_vehicle_id']) : null;
            $assigned_vehicle_type = $snap['assigned_vehicle_type'] ?? null;
            $total_cubic_meters    = floatval($snap['total_cubic_meters'] ?? 0);
            $total_weight_kg       = floatval($snap['total_weight_kg'] ?? 0);
            $total_width           = floatval($snap['total_width'] ?? 0);
            $total_height          = floatval($snap['total_height'] ?? 0);
            $total_length          = floatval($snap['total_length'] ?? 0);
            $cart_items            = $snap['cart_snapshot'] ?? [];
            $sales_info            = $snap['sales_info'] ?? [];

            $sales_referral_code     = $sales_info['sales_referral_code']     ?? null;
            $sales_commission_rate   = floatval($sales_info['sales_commission_rate']   ?? 0);
            $sales_commission_amount = floatval($sales_info['sales_commission_amount'] ?? 0);
            $sales_user_id           = !empty($sales_info['sales_user_id']) ? intval($sales_info['sales_user_id']) : null;

            $subtotal        = ($total_amount - $delivery_fee) / 1.12;
            $vat_amount      = $subtotal * 0.12;
            $discount_amount = 0.00;
            $payment_method  = 'QR Ph';
            $paid_status     = 'paid';
            $proc_status     = 'Processing';
            $reference_no    = $temp_ref;

            if (empty($cart_items)) throw new Exception('No cart items in snapshot: ' . $temp_ref);

            // INSERT ORDER
            // 32 params: isssssdddddsssssisdddddddidsddiis
            // i  user_id
            // s  customer_name, s email, s mobile, s address, s zipcode
            // d  subtotal, d delivery_fee, d total, d vat_amount, d discount
            // s  mode_payment, s payment_status, s reference_no, s status, s delivery_type
            // i  assigned_vehicle_id, s assigned_vehicle_type
            // d  total_cubic_meters, d total_weight_kg, d total_width, d total_height, d total_length
            // d  latitude, d longitude, i billing_address_id, d delivery_distance
            // s  sales_referral_code, d commission_rate, d commission_amount, i sales_user_id
            // i  (count=1 extra) — wait, let me recount:
            // 32 = 1+5+5+5+2+5+4+4+1 = hmm recount carefully below

            $insert_sql = "INSERT INTO orders (
                user_id, customer_name, email, mobile, address, zipcode,
                subtotal, delivery_fee, total, vat_amount, discount,
                mode_payment, payment_status, reference_no, status, delivery_type,
                assigned_vehicle_id, assigned_vehicle_type,
                total_cubic_meters, total_weight_kg, total_width, total_height, total_length,
                latitude, longitude, billing_address_id, delivery_distance,
                sales_referral_code, sales_commission_rate, sales_commission_amount, sales_user_id,
                paymongo_payment_id, payment_confirmed_at
            ) VALUES (
                ?,?,?,?,?,?,
                ?,?,?,?,?,
                ?,?,?,?,?,
                ?,?,
                ?,?,?,?,?,
                ?,?,?,?,
                ?,?,?,?,
                ?, NOW()
            )";

            // Count: 6+5+6+2+5+4+4+1 = 33? Let me list explicitly:
            // 1  i user_id
            // 2  s customer_name
            // 3  s email
            // 4  s mobile
            // 5  s address
            // 6  s zipcode
            // 7  d subtotal
            // 8  d delivery_fee
            // 9  d total
            // 10 d vat_amount
            // 11 d discount
            // 12 s mode_payment
            // 13 s payment_status
            // 14 s reference_no
            // 15 s status
            // 16 s delivery_type
            // 17 i assigned_vehicle_id
            // 18 s assigned_vehicle_type
            // 19 d total_cubic_meters
            // 20 d total_weight_kg
            // 21 d total_width
            // 22 d total_height
            // 23 d total_length
            // 24 d latitude
            // 25 d longitude
            // 26 i billing_address_id
            // 27 d delivery_distance
            // 28 s sales_referral_code
            // 29 d sales_commission_rate
            // 30 d sales_commission_amount
            // 31 i sales_user_id
            // 32 s paymongo_payment_id
            // payment_confirmed_at = NOW() — no param

            $stmt = $conn->prepare($insert_sql);
            if (!$stmt) throw new Exception('Order prepare failed: ' . $conn->error);

            $stmt->bind_param(
                "isssssdddddsssssisdddddddidsddis",
                $user_id,
                $customer_name, $email, $mobile, $address, $zipcode,
                $subtotal, $delivery_fee, $total_amount, $vat_amount, $discount_amount,
                $payment_method, $paid_status, $reference_no, $proc_status, $delivery_type,
                $assigned_vehicle_id, $assigned_vehicle_type,
                $total_cubic_meters, $total_weight_kg, $total_width, $total_height, $total_length,
                $latitude, $longitude, $billing_address_id, $delivery_distance,
                $sales_referral_code, $sales_commission_rate, $sales_commission_amount, $sales_user_id,
                $payment_id
            );

            if (!$stmt->execute()) throw new Exception('Order insert failed: ' . $stmt->error);
            $new_order_id = $conn->insert_id;
            $stmt->close();
            error_log("QRPh Order #$new_order_id created");

            // INSERT ORDER ITEMS + DEDUCT STOCK PER ITEM
            // 15 columns: order_id(i) product_id(i) variant_id(i) color_id(i)
            //             product_name(s) codename(s) type_name(s) variant_color(s) size(s)
            //             price(d) quantity(i) subtotal(d)
            //             descrip6(s) descrip7(s) origin(s)
            // bind_param: "iiiisssssdidsss"  ← 15 chars, CORRECT
            $item_stmt = $conn->prepare("
                INSERT INTO order_items (
                    order_id, product_id, variant_id, color_id,
                    product_name, codename, type_name, variant_color, size,
                    price, quantity, subtotal,
                    descrip6, descrip7, origin
                ) VALUES (?,?,?,?, ?,?,?,?,?, ?,?,?, ?,?,?)
            ");
            if (!$item_stmt) throw new Exception('Item prepare failed: ' . $conn->error);

            foreach ($cart_items as $item) {
                $raw_vid  = intval($item['variant_id'] ?? 0);
                $raw_cid  = intval($item['color_id']   ?? 0);
                $raw_qty  = intval($item['quantity']    ?? 0);
                $raw_price= floatval($item['price']     ?? 0);
                $i_sub    = $raw_price * $raw_qty;

                $db_vid   = $raw_vid > 0 ? $raw_vid : null;
                $db_cid   = $raw_cid > 0 ? $raw_cid : null;
                $pid      = intval($item['product_id'] ?? 0);
                $pname    = !empty($item['product_name']) ? $item['product_name'] : 'Product';
                $color    = $item['color_name'] ?? '';
                $codename = $item['codename']   ?? '';
                $tname    = $item['type_name']  ?? '';
                $size     = $item['size']        ?? '';
                $d6       = $item['descrip6']    ?? '';
                $d7       = $item['descrip7']    ?? '';
                $origin   = $item['origin']      ?? '';

                $item_stmt->bind_param(
                    "iiiisssssdidsss",
                    $new_order_id, $pid, $db_vid, $db_cid,
                    $pname, $codename, $tname, $color, $size,
                    $raw_price, $raw_qty, $i_sub,
                    $d6, $d7, $origin
                );

                if ($item_stmt->execute()) {
                    error_log("Item OK: $pname qty=$raw_qty");
                    _deductStock($conn, $raw_vid, $raw_cid, $raw_qty);
                } else {
                    error_log("Item FAILED: " . $item_stmt->error . " | $pname");
                }
            }
            $item_stmt->close();

            // Mark pending session processed
            $upd = $conn->prepare("UPDATE qrph_pending_sessions SET status='processed', order_id=? WHERE temp_ref=?");
            if ($upd) { $upd->bind_param('is', $new_order_id, $temp_ref); $upd->execute(); $upd->close(); }

            // Clear cart
            $clr = $conn->prepare("DELETE FROM user_cart_items WHERE user_id=?");
            if ($clr) { $clr->bind_param('i', $user_id); $clr->execute(); $clr->close(); }

            error_log("QRPh DONE: order #$new_order_id user #$user_id");

        } else {
            error_log("No order_id and no temp_ref in metadata — nothing to do");
            error_log("Full metadata: " . json_encode($metadata));
        }

    // ================================================================
    // EXPIRED / FAILED / REFUNDED
    // ================================================================
    } elseif (in_array($event_type, ['checkout_session.expired', 'qrph.expired'])) {
        $attrs    = $data['data']['attributes']['data']['attributes'] ?? [];
        $meta     = $attrs['metadata'] ?? [];
        $ref      = $meta['temp_ref'] ?? null;
        $oid      = intval($meta['order_id'] ?? 0);

        if ($ref) {
            $s = $conn->prepare("UPDATE qrph_pending_sessions SET status='expired' WHERE temp_ref=? AND status='pending'");
            if ($s) { $s->bind_param('s', $ref); $s->execute(); $s->close(); }
        }
        if ($oid > 0) {
            $s = $conn->prepare("UPDATE orders SET payment_status='failed' WHERE id=? AND payment_status='pending'");
            if ($s) { $s->bind_param('i', $oid); $s->execute(); $s->close(); }
        }
        error_log("$event_type handled");

    } elseif (in_array($event_type, ['payment.failed', 'checkout_session.payment.failed'])) {
        $attrs = $data['data']['attributes']['data']['attributes'] ?? [];
        $meta  = $attrs['metadata'] ?? [];
        $oid   = intval($meta['order_id'] ?? 0);
        if ($oid > 0) {
            $s = $conn->prepare("UPDATE orders SET payment_status='failed' WHERE id=?");
            if ($s) { $s->bind_param('i', $oid); $s->execute(); $s->close(); }
        }
        error_log("$event_type handled");

    } elseif (in_array($event_type, ['payment.refunded', 'checkout_session.payment.refunded'])) {
        $attrs = $data['data']['attributes']['data']['attributes'] ?? [];
        $meta  = $attrs['metadata'] ?? [];
        $oid   = intval($meta['order_id'] ?? 0);
        if ($oid > 0) {
            $s = $conn->prepare("UPDATE orders SET payment_status='refunded' WHERE id=?");
            if ($s) { $s->bind_param('i', $oid); $s->execute(); $s->close(); }
        }
        error_log("$event_type handled");

    } else {
        error_log("Unhandled event: $event_type");
    }

    http_response_code(200);
    ob_end_clean();
    echo json_encode(['success' => true, 'event_type' => $event_type]);

} catch (Exception $e) {
    error_log("WEBHOOK EXCEPTION: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);
    ob_end_clean();
    echo json_encode(['error' => $e->getMessage(), 'success' => false]);
}

// ── Deduct stock for a single item ───────────────────────────────
function _deductStock($conn, $variant_id, $color_id, $quantity) {
    if ($variant_id <= 0 || $color_id <= 0 || $quantity <= 0) {
        error_log("Skipped stock deduct: variant=$variant_id color=$color_id qty=$quantity");
        return;
    }
    $s = $conn->prepare("
        UPDATE product_variant_colors
        SET stock_quantity = stock_quantity - ?
        WHERE variant_id = ? AND color_id = ?
    ");
    if ($s) {
        $s->bind_param('iii', $quantity, $variant_id, $color_id);
        $s->execute()
            ? error_log("Stock deducted: variant=$variant_id color=$color_id qty=$quantity affected=" . $s->affected_rows)
            : error_log("Stock deduct FAILED: " . $s->error);
        $s->close();
    }
}

// ── Deduct stock for all items in an existing order + clear cart ─
function _deductStockAndClearCart($conn, $order_id) {
    $s = $conn->prepare("SELECT variant_id, color_id, quantity FROM order_items WHERE order_id=?");
    if (!$s) { error_log("_deductStockAndClearCart prepare failed: " . $conn->error); return; }

    $s->bind_param('i', $order_id);
    $s->execute();
    $rows  = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $s->close();
    $count = 0;

    foreach ($rows as $row) {
        _deductStock($conn, intval($row['variant_id']), intval($row['color_id']), intval($row['quantity']));
        $count++;
    }
    error_log("Stock deducted for $count items in order #$order_id");

    // Clear cart
    $us = $conn->prepare("SELECT user_id FROM orders WHERE id=? LIMIT 1");
    if ($us) {
        $us->bind_param('i', $order_id);
        $us->execute();
        $ur = $us->get_result()->fetch_assoc();
        $us->close();
        if ($ur) {
            $uid = intval($ur['user_id']);
            $c   = $conn->prepare("DELETE FROM user_cart_items WHERE user_id=?");
            if ($c) { $c->bind_param('i', $uid); $c->execute(); $c->close(); }
            error_log("Cart cleared for user #$uid");
        }
    }
}