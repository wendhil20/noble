<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

define('ROOT_PATH', __DIR__);

$isLocalhost = (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || 
                strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false);

define('BASE_URL', $isLocalhost ? '/noble' : '');


// Get clean path, strip /noble/ prefix
$request = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$request = trim($request, '/');
$request = preg_replace('#^noble/?#', '', $request);
$request = trim($request, '/');


// Handle empty request (root /noble/ or /noble)
if ($request === '' || $request === 'home') {
    $request = 'home'; // normalize to same handler
}


// $request is now clean: 'home', 'product', 'shop', etc.

$routes = [
    'productsubview' => 'user/otherpage/allproduct-allproduct_get-page-3-A.php',
    'productsubviews' => 'user/otherpage/allproduct-allproductsub_variant-page-3-A.php',
    'product-normal-and-discounted' => 'user/otherpage/index-allproduct-page-3.php',
    'sale' => 'user/otherpage/index-allproductsub-page-5.php',
    'bestsellerview' => 'user/otherpage/index-bestseller-detail-B.php',
    'cartview' => 'user/otherpage/index-cart_view-page-8.php',
    'chat' => 'user/otherpage/index-chat_main-page-9.php',
    'checkout22' => 'user/otherpage/index-checkout-page-12-2-A.php',
    'checkout2' => 'user/otherpage/index-checkout-page-12-2.php',
    'checkout3' => 'user/otherpage/index-checkout-page-12-3.php',
    'checkout4' => 'user/otherpage/index-checkout-page-12-4.php',
    'checkout' => 'user/otherpage/index-checkout-page-12.php',
    'payment1qrph' => 'user/otherpage/checkout-qrph-create.php',
    'payment2qrph' => 'user/otherpage/checkout-qrph-create-order.php',
    'payment3qrph' => 'user/otherpage/checkout-qrph-check-status.php',
    'paymenthook' => 'user/otherpage/checkout-webhook-paymongo.php',
    'payment1mongo' => 'user/otherpage/checkout-paymongo-create-sessions-page-12-A.php',
    'payment2mongo' => 'user/otherpage/checkout-paymongo-success-page-12-A.php',
    '13' => 'user/otherpage/index-countdowntimer-page-17.php',
    '14' => 'user/otherpage/index-customize_quote_handler-page-4-AA.php',
    'findprofessional' => 'user/otherpage/index-findpropage-page-10.php',
    '16' => 'user/otherpage/index-flash_notification-D.php',
    '17' => 'user/otherpage/index-get_stock-page-4-AA.php',
    'inspiration' => 'user/otherpage/index-inspirationpage-page-11.php',
    'history' => 'user/otherpage/index-order_history-page-13.php',
    'home' => 'user/otherpage/index-page-1-A-B-C-D-E.php',
    'productview' => 'user/otherpage/index-product_view-page-4-AA.php',
    '22' => 'user/otherpage/index-product-related-products.php',
    '23' => 'user/otherpage/index-product-windows-customize-modal.php',
    'order' => 'user/otherpage/index-profile-page-6.php',
    '25' => 'user/otherpage/index-profilefetch_reviews-E.php',
    'profile' => 'user/otherpage/index-profilepersonal-page-7.php',
    '27' => 'user/otherpage/index-promotion-banner-front.php',
    '28' => 'user/otherpage/index-promotion-discount-front.php',
    '29' => 'user/otherpage/index-recent_views_handler-page-14.php',
    'shop' => 'user/otherpage/index-shop-page-2.php',
    '31' => 'user/otherpage/index-shopcompare-C.php',
    'subcategorygrid' => 'user/otherpage/index-subcategory_grid_page-14.php',
    'recommended' => 'user/otherpage/index-subcategory-recommendations-page-15.php',
    '34' => 'user/otherpage/index-view_product-page-16.php',
    '35' => 'user/otherpage/indexshopdepartment.php',
    'ordertrack' => 'user/otherpage/order_tracking.php',
    '37' => 'user/otherpage/order-getorder_reciept-page-13-A.php',
    'address' => 'user/otherpage/profile-update_billing_add-page-7-A.php',
    '40' => 'user/otherpage/push-notification.php',
    'replacement' => 'user/otherpage/replacement_request.php',
    'verificationsettings' => 'user/otherpage/settings-verification-pending.php',
    'form' => 'user/otherpage/settings.php',
    '44' => 'user/otherpage/submit_feedback.php',
    '45' => 'user/otherpage/update_payment.php',
    '46' => 'user/otherpage/validate-referral-code.php',
    'profilerate' => 'user/otherpage/profile-profilerate-page-7-A.php',
    'receipt' => 'user/otherpage/checkout-order_receipt-page-12-A.php',
    'addcart' => 'user/cart/add_to_cart.php',
    'updatecart' => 'user/cart/update_cart.php',
    'removecart' => 'user/cart/remove_from_cart.php',
    'getmark' => 'user/navbar/topcheck_marked.php',
    'clearall' => 'user/navbar/topcheck_clearall.php',
    'getnotif' => 'user/navbar/topcheck_getnotif.php',
    'refreshcart' => 'user/navbar/refresh_cart.php',
    'removetocart' => 'user/navbar/remove_from_cart_ajax.php',
    'search' => 'user/otherpage/backend-search_ajax-A.php',
    'cartreal' => 'user/navbar/cart-sidebar-data.php',
    'login' => 'user/google-login.php',
    'googlecallback' => 'user/google-callback.php',
    'googlepopup' => 'user/google-popup-close.php',
    'logout' => 'user/logout.php',
    'terms' => 'user/rules/terms.php',
    'policy' => 'user/rules/policy.php',
    'about' => 'user/about/about.php',
    'customerservices' => 'user/rules/customer-services.php',
    'sendotp' => 'user/send_otp.php',
    'verifyotp' => 'user/verify_otp.php',
    'logins' => 'user/login.php',
    'register' => 'user/register.php',
    'forgotpass' => 'user/forgot_password.php',
    'resetpass' => 'user/reset_password.php',
];

$file = $routes[$request] ?? null;

if ($file === null) {
    // 404 - unknown route
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