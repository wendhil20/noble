DELIMITER $$
CREATE DEFINER=`root`@`localhost` PROCEDURE `record_order_as_sold`(IN `p_order_id` INT)
BEGIN
    DECLARE v_sold_order_id INT;
    DECLARE v_order_exists INT;
    DECLARE v_error_msg VARCHAR(255);
    
    -- NO transaction control here — let caller handle it
    -- or rely on the trigger's implicit transaction
    
    -- Check if order exists and has valid status
    SELECT COUNT(*) INTO v_order_exists 
    FROM orders 
    WHERE id = p_order_id 
    AND status IN ('Delivered', 'Picked Up', 'delivered', 'picked_up');
    
    IF v_order_exists = 0 THEN
        SIGNAL SQLSTATE '45000' 
            SET MESSAGE_TEXT = 'Order not found or not in delivered/picked up status';
    END IF;
    
    -- Check if already recorded as sold
    SELECT COUNT(*) INTO v_order_exists 
    FROM sold_orders 
    WHERE order_id = p_order_id;
    
    IF v_order_exists > 0 THEN
        SIGNAL SQLSTATE '45000' 
            SET MESSAGE_TEXT = 'Order already recorded as sold';
    END IF;
    
    -- Insert into sold_orders
    INSERT INTO sold_orders (
        order_id, user_id, emp_id, warehouse_employee_id,
        customer_name, email, mobile,
        subtotal, discount, shipping_fee, delivery_fee, vat_amount, total, final_total,
        address, zipcode, latitude, longitude, delivery_distance, delivery_type,
        assigned_vehicle_id, assigned_vehicle_type,
        total_cubic_meters, total_weight_kg, total_width, total_height, total_length,
        mode_payment, payment_status,
        reference_no, reference_number, verified_by,
        paymongo_session_id, billing_address_id,
        order_date, confirmed_at, completed_at, delivered_at
    )
    SELECT 
        id, user_id, emp_id, warehouse_employee_id,
        customer_name, email, mobile,
        subtotal, discount, shipping_fee, delivery_fee, vat_amount, total, final_total,
        address, zipcode, latitude, longitude, delivery_distance, delivery_type,
        assigned_vehicle_id, assigned_vehicle_type,
        total_cubic_meters, total_weight_kg, total_width, total_height, total_length,
        mode_payment, payment_status,
        reference_no, reference_number, verified_by,
        paymongo_session_id, billing_address_id,
        created_at, confirmed_at, completed_at, NOW()
    FROM orders
    WHERE id = p_order_id;
    
    SET v_sold_order_id = LAST_INSERT_ID();
    
    -- Insert into sold_items
    INSERT INTO sold_items (
        sold_order_id, original_order_item_id, order_id,
        product_id, product_name, codename, type_name, variant_color, size,
        price, quantity, subtotal, delivery_fee_per_item, item_total_delivery,
        descrip6, descrip7, origin,
        supplier_id, manual_supplier_name, po_number,
        qr_code, warehouse_location,
        lt_from, lt_to
    )
    SELECT 
        v_sold_order_id, id, order_id,
        product_id, product_name, codename, type_name, variant_color, size,
        price, quantity, subtotal, delivery_fee_per_item, item_total_delivery,
        descrip6, descrip7, origin,
        supplier_id, manual_supplier_name, po_number,
        qr_code, warehouse_location,
        lt_from, lt_to
    FROM order_items
    WHERE order_id = p_order_id;

END$$
DELIMITER ;

-- Procedure structure for procedure `update_expired_timer_discounts`
DELIMITER $$
CREATE DEFINER=`root`@`localhost` PROCEDURE `update_expired_timer_discounts`()
BEGIN
    UPDATE product_variants 
    SET timer_discount_active = 0 
    WHERE timer_discount_active = 1 
    AND timer_discount_end < NOW();
END$$
DELIMITER ;