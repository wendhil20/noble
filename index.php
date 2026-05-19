<?php

define('ROOT_PATH', __DIR__);

$isLocalhost = (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || 
                strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false);

define('BASE_URL', $isLocalhost ? '/noble' : '');

// ✅ ALAMIN MUNA KUNG ANONG SESSION ANG KAILANGAN
$request = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$request = trim($request, '/');
$request = preg_replace('#^noble/?#', '', $request);
$request = trim($request, '/');

if ($request === '' || $request === 'home') {
    $request = 'home';
}

// ✅ ADMIN ROUTES — gumamit ng nobleadmin session
$adminRoutes = [
    'main', 
    'loggingadmin', 
    'logoutadmin',
    'indexcheck',

    //sales
    'ordermain', 
    'generatereferral',
    'savereferral',
    'targetpricemanagement',
    'targetpricemanagementsave',
    'targetpricemanagementdelete',
    'targetpricemanagementgetproduct',
    'targetpricemanagementgettier',
    'targetpricemanagementonandoff',
    'fetchsales',
    'updateorder',
    'replacementrequests',
    'fetchrequests',
    'updatereplacement',
    'customizedwindow',
    'customizereply',                    
    'getcustomizereq',             
    'getcustomizereply',
    'unassignedorder',
    'unassignedorderaccept',
    'backtracking',
    'backtrackingdashboard',

    //accountant
    'accountant',
    'accountantorderdetail',
    'accountantvieworder',
    'accountantsalescommision',
    'accountantdocvieworder',
    'accountantdashboard',
    'fetchdashboardaccountant',
    'updatesalescommision',
    'exportexcel',
    'accountantorderview',
    'accountantviewpo',
    'accountantcommissionrelease',

    // warehouse
    'warehousedashboard',
    'warehousepolling',
    'warehouseassignment',
    'warehouseheadassignmentajax',

    // warehouse staff
    'warehousestaff',
    'warehousestafftrackorder',
    'warehousestaffpomanagement',
    'warehousestaffsupplierassign',
    'warehousestaffbulkassign',
    'warehousestaffgeneratepo',
    'warehouseheadstaffpomanagement',
    'warehousestaffresetponumber',
    'warehousestaffgeneratepoexcel',
    'warehousestaffresolvedefect',
    'warehousestaffgeneratereplacement',
    'warehousestaffassignreceiverreplace',
    'warehousestaffreportdefect',
    'warehousestaffdeliveryschedule',

    // warehouse receiver
    'qrscanner',
    'receiverlistmain',
    'receiverviewpoitems',
    'receivermarkpo',
    'receiverresetqr',
    'receiversaveqr',
    'receiverscanitem',
    'receiverscanreplacement',
    'receiverupdatelocation',
    'receiverupdatetrackingstatus',

    // logistic
    'logistic',
    'logisticdeliverydateorders',
    'logisticdispatcherdashboard',
    'logisticreplacementbook',
    'logisticdeliverybook',
    'logisticdeliverytrack',
    'logisticreplacementitemsview',
    'logisticorderitemsview',
    'logisticprocessreschedule',
    'transpoaddvehicle',
    'transpoeditvehicle',
    'transpolist',
    'logisticdispatcherviewbooking',
    'generateshippingsticker',
    'getstickerprintstatus',
    'downloadstickerpdf',
    'logstickerprint',

    // product specialist
    'addbestseller',
    'addnewproduct',
    'updateproduct',
    'banner',
    'category',
    'managetags',
    'notification',
    'autodeactivatetimer',
    'updateallproduct',                   
    'promotionbanner',                    
    'discountbanner',                     
    'quantitymanagement',                 
    'quantitysave',                       
    'setdescription',                     
    'setsku',                             
    'updateprocess',                      
    'updateproducts',                     
    'uploadprocess',       

    'suppliermanagement',                 
    'suppliercatagalog',                  
    'supplierlist',                       
    'suppliertonggleproduct',             
    'suppliertongglestatus',              
    'supplierlink',                       
    'viewsupplier',      
    'editsupplier',   
    
    //hr
    'account',                            
    'manageheadaccount',                  
    'assignhead',                         
    'approveverification',                
    'verification',                       
    'managenobleaccount',   
    'registration',          
    'accounts',   


    // superadmin
    'ownerdashboard',  
    'approvepurchaseorder', 
    'datainput', 
    'getorderitem', 
    'marktrackingstatus', 
    'ownerorders', 
    'superadminaccountant', 
    'superadmincommisions', 
    'superadminlogistic', 
    'superadminpoapproval', 
    'updatearrival', 
    

];

if (in_array($request, $adminRoutes)) {
    session_name("nobleadmin");
} else {
    session_name("nobleuser");
}

session_start();

// ✅ ROUTES ARRAY — ilagay dito pagkatapos ng session_start()
$routes = [
    'productsubview'                  => 'user/otherpage/allproduct-allproduct_get-page-3-A.php',
    'productsubviews'                 => 'user/otherpage/allproduct-allproductsub_variant-page-3-A.php',
    'product-normal-and-discounted'   => 'user/otherpage/index-allproduct-page-3.php',
    'sale'                            => 'user/otherpage/index-allproductsub-page-5.php',
    'bestsellerview'                  => 'user/otherpage/index-bestseller-detail-B.php',
    'cartview'                        => 'user/otherpage/index-cart_view-page-8.php',
    'chat'                            => 'user/otherpage/index-chat_main-page-9.php',

    // payment
    'checkout22'                      => 'user/otherpage/index-checkout-page-12-2-A.php',
    'checkout2'                       => 'user/otherpage/index-checkout-page-12-2.php',
    'checkout3'                       => 'user/otherpage/index-checkout-page-12-3.php',
    'checkout4'                       => 'user/otherpage/index-checkout-page-12-4.php',
    'checkout'                        => 'user/otherpage/index-checkout-page-12.php',
    'payme0nt1qrph'                   => 'user/otherpage/checkout-qrph-create.php',
    'payment2qrph'                    => 'user/otherpage/checkout-qrph-create-order.php',
    'payment3qrph'                    => 'user/otherpage/checkout-qrph-check-status.php',
    'paymenthook'                     => 'user/otherpage/checkout-webhook-paymongo.php',
    'payment1mongo'                   => 'user/otherpage/checkout-paymongo-create-sessions-page-12-A.php',
    'payment2mongo'                   => 'user/otherpage/checkout-paymongo-success-page-12-A.php',

    '13'                              => 'user/otherpage/index-countdowntimer-page-17.php',
    '14'                              => 'user/otherpage/index-customize_quote_handler-page-4-AA.php',
    'findprofessional'                => 'user/otherpage/index-findpropage-page-10.php',
    '16'                              => 'user/otherpage/index-flash_notification-D.php',
    '17'                              => 'user/otherpage/index-get_stock-page-4-AA.php',
    'inspiration'                     => 'user/otherpage/index-inspirationpage-page-11.php',
    'history'                         => 'user/otherpage/index-order_history-page-13.php',
    'home'                            => 'user/otherpage/index-page-1-A-B-C-D-E.php',
    'productview'                     => 'user/otherpage/index-product_view-page-4-AA.php',
    '22'                              => 'user/otherpage/index-product-related-products.php',
    '23'                              => 'user/otherpage/index-product-windows-customize-modal.php',
    'order'                           => 'user/otherpage/index-profile-page-6.php',
    '25'                              => 'user/otherpage/index-profilefetch_reviews-E.php',
    'profile'                         => 'user/otherpage/index-profilepersonal-page-7.php',
    'promotion'                       => 'user/otherpage/index-promotion-banner-front.php',
    'promotiondiscount'               => 'user/otherpage/index-promotion-discount-front.php',
    'recentview'                      => 'user/otherpage/index-recent_views_handler-page-14.php',
    'shop'                            => 'user/otherpage/index-shop-page-2.php',
    '31'                              => 'user/otherpage/index-shopcompare-C.php',
    'subcategorygrid'                 => 'user/otherpage/index-subcategory_grid_page-14.php',
    'recommended'                     => 'user/otherpage/index-subcategory-recommendations-page-15.php',
    '34'                              => 'user/otherpage/index-view_product-page-16.php',
    '35'                              => 'user/otherpage/indexshopdepartment.php',
    'ordertrack'                      => 'user/otherpage/order_tracking.php',
    '37'                              => 'user/otherpage/order-getorder_reciept-page-13-A.php',
    'address'                         => 'user/otherpage/profile-update_billing_add-page-7-A.php',
    '40'                              => 'user/otherpage/push-notification.php',
    'replacement'                     => 'user/otherpage/replacement_request.php',
    'verificationsettings'            => 'user/otherpage/settings-verification-pending.php',
    'form'                            => 'user/otherpage/settings.php',
    '44'                              => 'user/otherpage/submit_feedback.php',
    '45'                              => 'user/otherpage/update_payment.php',
    '46'                              => 'user/otherpage/validate-referral-code.php',
    'profilerate'                     => 'user/otherpage/profile-profilerate-page-7-A.php',
    'receipt'                         => 'user/otherpage/checkout-order_receipt-page-12-A.php',
    'addcart'                         => 'user/cart/add_to_cart.php',
    'updatecart'                      => 'user/cart/update_cart.php',
    'removecart'                      => 'user/cart/remove_from_cart.php',
    'getmark'                         => 'user/navbar/topcheck_marked.php',
    'clearall'                        => 'user/navbar/topcheck_clearall.php',
    'getnotif'                        => 'user/navbar/topcheck_getnotif.php',
    'refreshcart'                     => 'user/navbar/refresh_cart.php',
    'removetocart'                    => 'user/navbar/remove_from_cart_ajax.php',
    'search'                          => 'user/otherpage/backend-search_ajax-A.php',
    'cartreal'                        => 'user/navbar/cart-sidebar-data.php',

    'terms'                           => 'user/rules/terms.php',
    'policy'                          => 'user/rules/policy.php',
    'about'                           => 'user/about/about.php',
    'customerservices'                => 'user/rules/customer-services.php',

    // user account
    'login'                           => 'user/google-login.php',
    'googlecallback'                  => 'user/google-callback.php',
    'googlepopup'                     => 'user/google-popup-close.php',
    'logout'                          => 'user/logout.php',
    'sendotp'                         => 'user/send_otp.php',
    'verifyotp'                       => 'user/verify_otp.php',
    'logins'                          => 'user/login.php',
    'register'                        => 'user/register.php',
    'forgotpass'                      => 'user/forgot_password.php',
    'resetpass'                       => 'user/reset_password.php',

    // admin authentication
    'main'                            => 'admin/authentication/index-admin-login.php',
    'loggingadmin'                    => 'admin/authentication/index-admin-logging.php',
    'logoutadmin'                     => 'admin/authentication/index-admin-logout.php',
    'indexcheck'                      => 'admin/authentication/index-admin-logincheck.php',

    // admin pages sales
    'ordermain'                       => 'admin/ui-sales/index-order-main.php',
    'fetchsales'                      => 'admin/ui-sales/backend/backend-order/index-order-fetchorders.php',
    'updateorder'                     => 'admin/ui-sales/backend/backend-order/index-order-update.php',
    'generatereferral'                => 'admin/ui-sales/index-generate-main.php',
    'savereferral'                    => 'admin/ui-sales/backend/backend-generate/save_referral_qr.php',

    'targetpricemanagement'           => 'admin/ui-sales/target-price-management.php',
    'targetpricemanagementsave'       => 'admin/ui-sales/backend/backend-targetprice/target-price-management-save.php',
    'targetpricemanagementdelete'     => 'admin/ui-sales/backend/backend-targetprice/target-price-management-delete.php',
    'targetpricemanagementgetproduct' => 'admin/ui-sales/backend/backend-targetprice/target-price-management-get-product-variants.php',
    'targetpricemanagementgettier'    => 'admin/ui-sales/backend/backend-targetprice/target-price-management-get-tier.php',
    'targetpricemanagementonandoff'   => 'admin/ui-sales/backend/backend-targetprice/target-price-management-on-and-off.php',

    'replacementrequests'             => 'admin/ui-sales/index-replacementrequests-main.php',
    'fetchrequests'                   => 'admin/ui-sales/backend/backend-replacement/index-replacement-fetch.php',
    'updatereplacement'               => 'admin/ui-sales/backend/backend-replacement/index-replacement-update.php',

    'customizedwindow'                => 'admin/ui-sales/main-customize-main.php',
    'customizereply'                  => 'admin/ui-sales/backend/backend-customize/main-send-reply-page-1-A.php',
    'getcustomizereq'                 => 'admin/ui-sales/backend/backend-customize/main-get-request-details-page-1-A.php',
    'getcustomizereply'               => 'admin/ui-sales/backend/backend-customize/main-get-quote-replies-page-1-A.php',

    'unassignedorder'                 => 'admin/ui-sales/index-unassignedorder-main.php',
    'unassignedorderaccept'           => 'admin/ui-sales/backend/backend-unassigned/index-unassigned-accept.php',

    'backtracking'                    => 'admin/ui-sales/index-backtracking-main.php',
    'backtrackingdashboard'           => 'admin/ui-sales/index-backtracking-dashboard.php',

    //admin accountant
    'accountant'                      => 'admin/ui-accountant/accountant.php',
    'accountantorderdetail'           => 'admin/ui-accountant/accountant-orderdetails.php',
    'accountantvieworder'             => 'admin/ui-accountant/accountant-approve-po.php',
    'accountantsalescommision'        => 'admin/ui-accountant/accountant-salescommission.php',
    'accountantdocvieworder'          => 'admin/ui-accountant/accountant-documentcontroller-vieworders.php',
    'accountantdashboard'             => 'admin/ui-accountant/accountantdashboard.php',
    'fetchdashboardaccountant'        => 'admin/ui-accountant/backend/backend-dashboard/fetch_revenue.php',
    'updatesalescommision'            => 'admin/ui-accountant/backend/backend-salescommision/update-sales-commision.php',
    'exportexcel'                     => 'admin/ui-accountant/accountantexcel.php',
    'accountantorderview'             => 'admin/ui-accountant/accountant-view-orders.php',
    'accountantviewpo'                => 'admin/ui-accountant/accountant-view-po.php',
    'accountantcommissionrelease'     => 'admin/ui-accountant/accountant-commission-release.php',

    // warehouse
    'warehousedashboard'                  => 'admin/ui-warehouse/warehouse-head-dashboardmain.php',
    'warehousepolling'                    => 'admin/ui-warehouse/backend/backend-warehouse/warehouse-head-dashboard-ajax.php',
    'warehouseassignment'                 => 'admin/ui-warehouse/warehouse-head-assignment-A.php',
    'warehouseheadassignmentajax'         => 'admin/ui-warehouse/backend/backend-warehouse/warehouse-head-assignment-ajax.php',

    //warehouse staff
    'warehousestaff'                      => 'admin/ui-warehouse/warehouse-staff-management-main.php',
    'warehousestafftrackorder'            => 'admin/ui-warehouse/warehouse-staff-order-tracking-C.php',
    'warehousestaffpomanagement'          => 'admin/ui-warehouse/warehouse-staff-po-management-A.php',
    'warehousestaffsupplierassign'        => 'admin/ui-warehouse/backend/backend-warehousestaff/warehouse-staff-assign-supplier-A1&B1.php',
    'warehousestaffbulkassign'            => 'admin/ui-warehouse/backend/backend-warehousestaff/warehouse-staff-bulk-assign-suppliers-A1.php',
    'warehousestaffgeneratepo'            => 'admin/ui-warehouse/warehouse-staff-generate-po-A-B.php',
    'warehouseheadstaffpomanagement'      => 'admin/ui-warehouse/warehouse-head-staff-view-po-files-B.php',
    'warehousestaffresetponumber'         => 'admin/ui-warehouse/backend/backend-warehousestaff/warehouse_staff_reset_po_number_A-B2.php',
    'warehousestaffgeneratepoexcel'       => 'admin/ui-warehouse/warehouse_staff_generate_po_excel_A-B1.php',
    'warehousestaffresolvedefect'         => 'admin/ui-warehouse/backend/backend-warehousestaff/warehouse-staff-resolve-defect-C2.php',
    'warehousestaffgeneratereplacement'   => 'admin/ui-warehouse/backend/backend-warehousestaff/warehouse-staff-generate-replacement-po-C1.php',
    'warehousestaffassignreceiverreplace' => 'admin/ui-warehouse/backend/backend-warehousestaff/warehouse-staff-assign-replacement-receiver-C3.php',
    'warehousestaffreportdefect'          => 'admin/ui-warehouse/backend/backend-warehousestaff/warehouse-staff-report-defect-C-B.php',
    'warehousestaffdeliveryschedule'      => 'admin/ui-warehouse/backend/backend-warehousestaff/warehouse-staff-delivery-schedule-C-A.php',

    //warehouse receiver
    'qrscanner'                          => 'admin/ui-warehouse/qr_scanner.php',
    'receiverlistmain'                   => 'admin/ui-warehouse/receiver_po_list_main.php',
    'receiverviewpoitems'                => 'admin/ui-warehouse/receiver_view_po_items_A.php',
    'receivermarkpo'                     => 'admin/ui-warehouse/backend/backend-warehousereceiver/receiver_mark_po_complete_A5.php',
    'receiverresetqr'                    => 'admin/ui-warehouse/backend/backend-warehousereceiver/receiver_reset_qr_code_A4.php',
    'receiversaveqr'                     => 'admin/ui-warehouse/backend/backend-warehousereceiver/receiver_save_qr_code_A2.php',
    'receiverscanitem'                   => 'admin/ui-warehouse/receiver_scan_item_A1.php',
    'receiverscanreplacement'            => 'admin/ui-warehouse/receiver_scan_replacement_A1.php',
    'receiverupdatelocation'             => 'admin/ui-warehouse/backend/backend-warehousereceiver/receiver_update_location_A3.php',
    'receiverupdatetrackingstatus'       => 'admin/ui-warehouse/backend/backend-warehousereceiver/receiver_update_tracking_status_A1-1.php',

    //logistic
    'logistic'                           => 'admin/ui-logistic/logistic-main-dashboard-page-1.php',
    'logisticdeliverydateorders'         => 'admin/ui-logistic/logistic-delivery-date-orders-page-2.php',
    'logisticdispatcherdashboard'        => 'admin/ui-logistic/logistic-dispatcher-dashboard-page-13.php',
    'logisticreplacementbook'            => 'admin/ui-logistic/logistic-replacement-booking-page-4.php',
    'logisticdeliverybook'               => 'admin/ui-logistic/logistic-delivery-booking-page-3.php',
    'logisticdeliverytrack'              => 'admin/ui-logistic/logistic-delivery-tracking-page-5.php',
    'logisticreplacementitemsview'       => 'admin/ui-logistic/logistic-replacement-items-view-page-6.php',
    'logisticorderitemsview'             => 'admin/ui-logistic/logistic-order-items-view-page-7.php',
    'logisticprocessreschedule'          => 'admin/ui-logistic/logistic-process-reschedule-page-8.php',
    'logistictranspoadd'                 => 'admin/ui-logistic/transpo_add_vehicle.php',
    'logistictranspoedit'                => 'admin/ui-logistic/transpo_edit_vehicle.php',
    'logistictranspolist'                => 'admin/ui-logistic/truck_management.php',
    'logisticdispatcherviewbooking'      => 'admin/ui-logistic/logistic-dispatcher-view-booking-page-12.php',
    'generateshippingsticker'            => 'admin/ui-logistic/generate_shipping_sticker.php',
    'getstickerprintstatus'              => 'admin/ui-logistic/get_sticker_print_status.php',
    'downloadstickerpdf'                 => 'admin/ui-logistic/download_sticker_pdf.php',
    'logstickerprint'                    => 'admin/ui-logistic/log_sticker_print.php',

    // product specialist
    'addbestseller'                      => 'admin/ui-productspecialist/main-add_bestseller-page-4.php',
    'addnewproduct'                      => 'admin/ui-productspecialist/main-adminshop-page-1.php',
    'updateproduct'                      => 'admin/ui-productspecialist/main-adminupdateshop-page-2.php',
    'banner'                             => 'admin/ui-productspecialist/main-banner-page-6.php',
    'category'                           => 'admin/ui-productspecialist/main-category-product-page-3.php',
    'managetags'                         => 'admin/ui-productspecialist/main-manage-tags.php',
    'notification'                       => 'admin/ui-productspecialist/main-notification.php',
    'autodeactivatetimer'                => 'admin/ui-productspecialist/main-update-auto_deactivate_timer-page-2-A.php',
    'updateallproduct'                   => 'admin/ui-productspecialist/main-updateallproduct.php',
    'promotionbanner'                    => 'admin/ui-productspecialist/promotion-banner.php',
    'discountbanner'                     => 'admin/ui-productspecialist/discount-banner.php',
    'quantitymanagement'                 => 'admin/ui-productspecialist/quantity-management.php',
    'quantitysave'                       => 'admin/ui-productspecialist/backend/backend-quantity/quantity-save.php',
    'setdescription'                     => 'admin/ui-productspecialist/set_description-page-5-A.php',
    'setsku'                             => 'admin/ui-productspecialist/set_sku-page-5-B.php',
    'updateprocess'                      => 'admin/ui-productspecialist/backend/backend-product/update_process-page-2-A.php',
    'updateproducts'                     => 'admin/ui-productspecialist/update_product-page-2-A.php',
    'uploadprocess'                      => 'admin/ui-productspecialist/backend/backend-product/upload_process-page-1-A.php',
    'suppliermanagement'                 => 'admin/ui-productspecialist/supplier_management.php',
    'suppliercatagalog'                  => 'admin/ui-productspecialist/supplier_catagalog.php',
    'supplierlist'                       => 'admin/ui-productspecialist/suppliers_list.php',
    'suppliertonggleproduct'             => 'admin/ui-productspecialist/tonggle_product_link.php',
    'suppliertongglestatus'              => 'admin/ui-productspecialist/tonggle_supplier_status.php',
    'supplierlink'                       => 'admin/ui-productspecialist/link_products.php',
    'viewsupplier'                       => 'admin/ui-productspecialist/view_supplier.php',
    'editsupplier'                       => 'admin/ui-productspecialist/edit_supplier.php',

    //hr
    'account'                            => 'admin/ui-hr/account.php',
    'manageheadaccount'                  => 'admin/ui-hr/manage_head_account.php',
    'assignhead'                         => 'admin/ui-hr/assign_head.php',
    'approveverification'                => 'admin/ui-hr/approve_verification.php',
    'verification'                       => 'admin/ui-hr/manage_user_verification.php',
    'managenobleaccount'                 => 'admin/ui-hr/manage_noble_account.php',
    'accounts'                           => 'admin/ui-hr/registration/account.php',
    'registration'                       => 'admin/ui-hr/registration/register.php',

    //superadmin
    'ownerdashboard'                     => 'admin/ui-superadmin/owner_dashboard.php',
    'approvepurchaseorder'               => 'admin/ui-superadmin/approve_purchase_orders.php',
    'datainput'                          => 'admin/ui-superadmin/delivery_data_input.php',
    'getorderitem'                       => 'admin/ui-superadmin/get_order_items.php',
    'marktrackingstatus'                 => 'admin/ui-superadmin/mark_tracking_status.php',
    'ownerorders'                        => 'admin/ui-superadmin/owner_order_view.php',
    'superadminaccountant'               => 'admin/ui-superadmin/superadmin_accountantdashboard.php',
    'superadmincommisions'               => 'admin/ui-superadmin/superadmin_commission_approval.php',
    'superadminlogistic'                 => 'admin/ui-superadmin/superadmin_logistic_dashboard.php',
    'superadminpoapproval'               => 'admin/ui-superadmin/superadmin_po_approval.php',
    'updatearrival'                      => 'admin/ui-superadmin/update_arrival.php',
];

$file = $routes[$request] ?? null;

if ($file === null) {
    http_response_code(404);
    include ROOT_PATH . '/user/otherpage/index-page-1-A-B-C-D-E.php';
    exit;
}

$filepath = ROOT_PATH . '/' . $file;

if (file_exists($filepath)) {
    include $filepath;
} else {
    http_response_code(404);
    echo "File not found: " . htmlspecialchars($file);
}