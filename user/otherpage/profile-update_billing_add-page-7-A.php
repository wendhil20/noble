<?php
session_name("nobleuser");
session_start();
include ROOT_PATH . '/connection/connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../google-callback.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? '';
$user_email = $_SESSION['user_email'] ?? null;

// Pre-fill phone from last saved address
$prefill_phone = '';
$stmt_phone = $conn->prepare("SELECT phone FROM billing_addresses WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
if ($stmt_phone) {
    $stmt_phone->bind_param("i", $user_id);
    $stmt_phone->execute();
    $stmt_phone->bind_result($prefill_phone_raw);
    $stmt_phone->fetch();
    $stmt_phone->close();
    if ($prefill_phone_raw) {
        $digits = preg_replace('/\D/', '', $prefill_phone_raw);
        if (substr($digits, 0, 2) === '63')
            $digits = substr($digits, 2);
        if (strlen($digits) === 10)
            $prefill_phone = substr($digits, 0, 4) . ' ' . substr($digits, 4, 3) . ' ' . substr($digits, 7, 3);
    }
}

// ── HANDLE DELETE REQUEST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (isset($input['address_id'])) {
        header('Content-Type: application/json');
        
        $delete_id = (int)$input['address_id'];
        
        // Verify ownership
        $verify = $conn->prepare("SELECT id FROM billing_addresses WHERE id = ? AND user_id = ?");
        $verify->bind_param("ii", $delete_id, $user_id);
        $verify->execute();
        $verify->store_result();
        
        if ($verify->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
            exit;
        }
        $verify->close();
        
        $stmt = $conn->prepare("DELETE FROM billing_addresses WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $delete_id, $user_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Delete failed.']);
        }
        $stmt->close();
        exit;
    }
}

// ── EDIT MODE: Load existing address ──
$edit_id = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$edit_data = null;
$is_edit_mode = false;

if ($edit_id > 0) {
    $stmt_edit = $conn->prepare("SELECT * FROM billing_addresses WHERE id = ? AND user_id = ? LIMIT 1");
    $stmt_edit->bind_param("ii", $edit_id, $user_id);
    $stmt_edit->execute();
    $result_edit = $stmt_edit->get_result();
    if ($result_edit->num_rows > 0) {
        $edit_data = $result_edit->fetch_assoc();
        $is_edit_mode = true;
    }
    $stmt_edit->close();
}

// ── HANDLE EDIT SUBMIT ──
if ($_POST && isset($_POST['edit_address'])) {
    $edit_target_id = (int) ($_POST['edit_id'] ?? 0);

    // Verify ownership
    $verify = $conn->prepare("SELECT id FROM billing_addresses WHERE id = ? AND user_id = ?");
    $verify->bind_param("ii", $edit_target_id, $user_id);
    $verify->execute();
    $verify->store_result();
    $owns = $verify->num_rows > 0;
    $verify->close();

    if (!$owns) {
        $error_message = "Unauthorized action.";
    } else {
        $full_name = trim($_POST['full_name'] ?? '');
        $phone_raw = trim($_POST['phone'] ?? '');
        $phone_valid = true;
        $phone = '';

        if (!empty($phone_raw)) {
            $phone_digits = preg_replace('/\D/', '', $phone_raw);
            $mobile_digits = substr($phone_digits, 0, 2) === '63' ? substr($phone_digits, 2) : $phone_digits;
            $dc = strlen($mobile_digits);
            if ($dc !== 10) {
                $error_message = $dc === 0 ? "Phone number is required."
                    : ($dc < 10 ? "Phone incomplete. Need " . (10 - $dc) . " more digit(s). ({$dc}/10)"
                        : "Phone too long. Use exactly 10 digits after +63. ({$dc}/10)");
                $phone_valid = false;
            } elseif ($mobile_digits[0] !== '9') {
                $error_message = "PH mobile numbers must start with 9.";
                $phone_valid = false;
            } else {
                $phone = '+63 ' . substr($mobile_digits, 0, 4) . ' ' . substr($mobile_digits, 4, 3) . ' ' . substr($mobile_digits, 7, 3);
            }
        }

        if ($phone_valid && !isset($error_message)) {
            foreach (['full_name' => 'Full Name', 'address' => 'Complete Address', 'city' => 'City', 'state' => 'State/Province', 'postal_code' => 'Postal Code'] as $f => $l) {
                if (empty(trim($_POST[$f] ?? ''))) {
                    $error_message = "{$l} is required.";
                    break;
                }
            }

            $latitude = $_POST['latitude'] ?? null;
            $longitude = $_POST['longitude'] ?? null;
            if (empty($latitude) || empty($longitude))
                $error_message = "Please pin your location on the map first.";
        }

        if (!isset($error_message)) {
            $address = trim($_POST['address'] ?? '');
            $city = trim($_POST['city'] ?? '');
            $state = trim($_POST['state'] ?? '');
            $postal_code = trim($_POST['postal_code'] ?? '');
            $country = $_POST['country'] ?? 'Philippines';
            $notes = trim($_POST['notes'] ?? '');

            $sql_upd = "UPDATE billing_addresses 
                        SET full_name=?, phone=?, address=?, city=?, state=?, 
                            postal_code=?, country=?, latitude=?, longitude=?, notes=?
                        WHERE id=? AND user_id=?";
            $stmt_upd = $conn->prepare($sql_upd);
            if ($stmt_upd) {
                $stmt_upd->bind_param(
                    "sssssssddsii",
                    $full_name,
                    $phone,
                    $address,
                    $city,
                    $state,
                    $postal_code,
                    $country,
                    $latitude,
                    $longitude,
                    $notes,
                    $edit_target_id,
                    $user_id
                );
                if ($stmt_upd->execute()) {
                    $success_message = "Address updated successfully!";
                    $redirect_script = '<script>setTimeout(()=>{window.location.href=document.referrer||"index-profilepersonal-page-7.php";},2000);</script>';
                } else {
                    $error_message = "Database error: " . $stmt_upd->error;
                }
                $stmt_upd->close();
            } else {
                $error_message = "Prepare error: " . $conn->error;
            }
        }
    }
}

// Handle form submission
if ($_POST && isset($_POST['add_address'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $phone_raw = trim($_POST['phone'] ?? '');

    $phone_valid = true;
    $phone = '';
    if (!empty($phone_raw)) {
        $phone_digits = preg_replace('/\D/', '', $phone_raw);
        $mobile_digits = substr($phone_digits, 0, 2) === '63' ? substr($phone_digits, 2) : $phone_digits;
        $dc = strlen($mobile_digits);
        if ($dc !== 10) {
            $error_message = $dc === 0 ? "Phone number is required."
                : ($dc < 10 ? "Phone incomplete. Need " . (10 - $dc) . " more digit(s). ({$dc}/10)"
                    : "Phone too long. Use exactly 10 digits after +63. ({$dc}/10)");
            $phone_valid = false;
        } elseif ($mobile_digits[0] !== '9') {
            $error_message = "PH mobile numbers must start with 9.";
            $phone_valid = false;
        } else {
            $phone = '+63 ' . substr($mobile_digits, 0, 4) . ' ' . substr($mobile_digits, 4, 3) . ' ' . substr($mobile_digits, 7, 3);
            if (strlen(preg_replace('/\D/', '', $phone)) !== 12) {
                $error_message = "Error formatting phone. Try again.";
                $phone_valid = false;
            }
        }
    }

    if ($phone_valid && !isset($error_message)) {
        foreach (['full_name' => 'Full Name', 'address' => 'Complete Address', 'city' => 'City', 'state' => 'State/Province', 'postal_code' => 'Postal Code'] as $f => $l)
            if (empty(trim($_POST[$f] ?? ''))) {
                $error_message = "{$l} is required.";
                break;
            }

        $latitude = $_POST['latitude'] ?? null;
        $longitude = $_POST['longitude'] ?? null;
        if (empty($latitude) || empty($longitude))
            $error_message = "Please pin your location on the map first.";
    }

    if (!isset($error_message)) {
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $postal_code = trim($_POST['postal_code'] ?? '');
        $country = $_POST['country'] ?? 'Philippines';
        $notes = trim($_POST['notes'] ?? '');

        $sql = "INSERT INTO billing_addresses (user_id,full_name,phone,address,city,state,postal_code,country,latitude,longitude,notes,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("isssssssdds", $user_id, $full_name, $phone, $address, $city, $state, $postal_code, $country, $latitude, $longitude, $notes);
            if ($stmt->execute()) {
                $success_message = "Address saved successfully!";
                $redirect_script = '<script>setTimeout(()=>{window.location.href=document.referrer||"index-checkout-page-12.php";},2000);</script>';
            } else {
                $error_message = "Database error: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error_message = "Prepare error: " . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1.0" />
    <title><?= $is_edit_mode ? 'Edit Billing Address' : 'Add Billing Address' ?></title>
    <style>
        * {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        #map {
            width: 100%;
            height: 340px;
            border-radius: 0.75rem;
        }

        #suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            z-index: 9999;
        }

        .suggestion-item:hover {
            background-color: #f0fdf4;
        }

        input:focus,
        textarea:focus {
            outline: none;
            border-color: #16a34a;
            box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.15);
        }

        .pin-pulse {
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.15);
            }
        }
    </style>
</head>

<body class="bg-gray-50 min-h-screen">
    <?php include ROOT_PATH . '/user/navbar/top.php'; ?>
    <?php if (isset($redirect_script))
        echo $redirect_script; ?>

    <div class="max-w-2xl mx-auto p-3">

      <div class="mb-8 mt-5">
    <!-- Back Button -->
    <button onclick="history.back()" 
        class="flex items-center gap-2 text-sm text-gray-500 hover:text-gray-800 mb-4 transition-colors group">
        <svg class="w-4 h-4 transition-transform group-hover:-translate-x-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
        </svg>
        Back
    </button>
            <div class="flex items-center gap-3 mb-1">
                <div class="w-9 h-9 rounded-xl bg-green-600 flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </div>
                <h1 class="text-xl font-bold text-gray-900">
                    <?= $is_edit_mode ? 'Edit Billing Address' : 'Add Billing Address' ?>
                </h1>
            </div>
            <p class="text-sm text-gray-500 ml-12">
                <?= $is_edit_mode ? 'Update your pinned location and address details below.' : 'Search your location on the map, then confirm the details below.' ?>
            </p>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="mb-5 flex items-center gap-3 bg-green-50 border border-green-200 rounded-2xl px-5 py-4">
                <svg class="w-5 h-5 text-green-600 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd"
                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                        clip-rule="evenodd" />
                </svg>
                <p class="text-sm font-medium text-green-800"><?= htmlspecialchars($success_message) ?> Redirecting...</p>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="mb-5 flex items-center gap-3 bg-red-50 border border-red-200 rounded-2xl px-5 py-4">
                <svg class="w-5 h-5 text-red-500 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm-1-5h2v2H9v-2zm0-8h2v6H9V5z"
                        clip-rule="evenodd" />
                </svg>
                <p class="text-sm font-medium text-red-800"><?= htmlspecialchars($error_message) ?></p>
            </div>
        <?php endif; ?>
        <form method="POST" id="addressForm">
            <input type="hidden" name="add_address" value="<?= $is_edit_mode ? '' : '1' ?>" />
            <input type="hidden" name="edit_address" value="<?= $is_edit_mode ? '1' : '' ?>" />
            <input type="hidden" name="edit_id" value="<?= $edit_id ?>" />
            <input type="hidden" name="latitude" id="latitude" />
            <input type="hidden" name="longitude" id="longitude" />
            <input type="hidden" name="country" value="Philippines" />

            <!-- MAP SEARCH -->
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 mb-5">
                <p class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
                    <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" stroke-width="2"
                        viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="8" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35" />
                    </svg>
                    Search your location
                </p>
                <div class="relative mb-4" id="searchWrapper">
                    <input id="searchInput" type="text" placeholder="e.g. MC Premiere Building, Jollibee Balintawak..."
                        autocomplete="off"
                        class="w-full border border-gray-300 rounded-xl px-4 py-3 pr-10 text-sm text-gray-800 placeholder-gray-400 transition-all" />
                    <div id="searchSpinner" class="hidden absolute right-3 top-3.5">
                        <svg class="animate-spin w-4 h-4 text-green-600" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                        </svg>
                    </div>
                    <div id="suggestions"
                        class="hidden bg-white border border-gray-200 rounded-xl shadow-lg mt-1 overflow-hidden max-h-72 overflow-y-auto">
                    </div>
                </div>

                <div id="map" class="border border-gray-200"></div>

                <div id="pinStatus"
                    class="hidden mt-3 items-start gap-2 bg-green-50 border border-green-200 rounded-xl px-4 py-3">
                    <svg class="w-4 h-4 text-green-600 mt-0.5 shrink-0 pin-pulse" fill="currentColor"
                        viewBox="0 0 24 24">
                        <path
                            d="M12 2C8.134 2 5 5.134 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.866-3.134-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z" />
                    </svg>
                    <div>
                        <p class="text-xs font-semibold text-green-700">Location pinned!</p>
                        <p id="pinAddress" class="text-xs text-green-600 mt-0.5"></p>
                        <p class="text-xs text-gray-500 mt-1">Lat: <span id="displayLat"
                                class="font-mono font-medium"></span> &nbsp; Lng: <span id="displayLng"
                                class="font-mono font-medium"></span></p>
                    </div>
                </div>
                <p class="text-xs text-gray-400 mt-3">💡 You can also click anywhere on the map to pin your exact
                    location.</p>
            </div>


            <!-- Ilagay ito bago ang formSections div -->
            <div id="formLocked"
                class="bg-white rounded-2xl border border-dashed border-gray-300 p-10 mb-5 text-center">
                <div class="text-4xl mb-3"><i class="fa-solid fa-circle-exclamation"></i></div>
                <p class="font-semibold text-gray-600 text-sm">Pin your location first or search</p>
                <p class="text-xs text-gray-400 mt-1">The form will appear once you've searched or clicked a spot on the
                    map.</p>
            </div>

            <div id="formSections" style="display: none;">
                <!-- PERSONAL INFO -->
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 mb-5">
                    <p class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                        <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" stroke-width="2"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        Personal information
                    </p>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1.5">Full Name <span
                                    class="text-red-500">*</span></label>
                            <input name="full_name" type="text" required
                                value="<?= htmlspecialchars($is_edit_mode ? ($edit_data['full_name'] ?? '') : $user_name) ?>"
                                placeholder="Juan Dela Cruz"
                                class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-gray-800 placeholder-gray-400 transition-all" />
                            <?php if ($user_name): ?>
                                <p class="text-xs text-green-600 mt-1 flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                            clip-rule="evenodd" />
                                    </svg>
                                    Pre-filled from your account
                                </p>
                            <?php endif; ?>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1.5">Phone Number <span
                                    class="text-gray-400 font-normal">(optional)</span></label>
                            <div class="flex">
                                <span
                                    class="flex items-center px-3 bg-gray-100 border border-r-0 border-gray-300 rounded-l-xl text-sm text-gray-500 font-medium">+63</span>
                                <input name="phone" id="phoneInput" type="tel" maxlength="13" value="<?= htmlspecialchars($is_edit_mode ? (function () use ($edit_data) {
                                    $digits = preg_replace('/\D/', '', $edit_data['phone'] ?? '');
                                    if (substr($digits, 0, 2) === '63')
                                        $digits = substr($digits, 2);
                                    return strlen($digits) === 10 ? substr($digits, 0, 4) . ' ' . substr($digits, 4, 3) . ' ' . substr($digits, 7, 3) : '';
                                })() : $prefill_phone) ?>" placeholder="9XX XXX XXXX"
                                    class="flex-1 border border-gray-300 rounded-r-xl px-4 py-2.5 text-sm text-gray-800 placeholder-gray-400 transition-all" />
                            </div>
                            <?php if ($prefill_phone): ?>
                                <p class="text-xs text-green-600 mt-1 flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                            clip-rule="evenodd" />
                                    </svg>
                                    Pre-filled from previous address
                                </p>
                            <?php else: ?>
                                <p class="text-xs text-gray-400 mt-1">Format: 9XX XXX XXXX</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- ADDRESS DETAILS -->
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 mb-5">
                    <p class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                        <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" stroke-width="2"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        Address details
                        <span class="ml-auto text-xs text-gray-400 font-normal">Auto-filled from map</span>
                    </p>
                    <div class="mb-4">
                        <label class="block text-xs font-medium text-gray-600 mb-1.5">Complete Address <span
                                class="text-red-500">*</span></label>
                        <input name="address" id="fieldAddress" type="text" placeholder="Street, Barangay" required
                            class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-gray-800 placeholder-gray-400 transition-all" />
                    </div>
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1.5">City <span
                                    class="text-red-500">*</span></label>
                            <input name="city" id="fieldCity" type="text" placeholder="Quezon City" required
                                class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-gray-800 placeholder-gray-400 transition-all" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1.5">State / Province <span
                                    class="text-red-500">*</span></label>
                            <input name="state" id="fieldState" type="text" placeholder="Enter the state" required
                                class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-gray-800 placeholder-gray-400 transition-all bg-gray-50" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1.5">Postal Code <span
                                    class="text-red-500">*</span></label>
                            <input name="postal_code" id="fieldPostal" type="text" placeholder="Auto-filled from map"
                                required
                                class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-gray-800 placeholder-gray-400 transition-all bg-gray-50" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1.5">Country</label>
                            <input type="text" value="Philippines" readonly
                                class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm text-gray-500 bg-gray-50 cursor-not-allowed" />
                        </div>
                    </div>
                </div>

                <!-- NOTES -->
                <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 mb-6">
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">Delivery Notes <span
                            class="text-gray-400">(optional)</span></label>
                    <textarea name="notes" rows="3" placeholder="e.g. malapit sa sari-sari store, gate color pula..."
                        class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm text-gray-800 placeholder-gray-400 resize-none transition-all"></textarea>
                </div>

                <div id="jsErrorBox"
                    class="hidden mb-4  items-start gap-2 bg-red-50 border border-red-200 rounded-xl px-4 py-3">
                    <svg class="w-4 h-4 text-red-500 mt-0.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm-1-5h2v2H9v-2zm0-8h2v6H9V5z"
                            clip-rule="evenodd" />
                    </svg>
                    <p id="jsErrorMsg" class="text-sm text-red-700"></p>
                </div>

                <button type="submit" id="submitBtn"
                    class="w-full bg-green-600 hover:bg-green-700 active:scale-95 text-white font-semibold text-sm py-3.5 rounded-2xl transition-all flex items-center justify-center gap-2 shadow-md shadow-green-200">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    <?= $is_edit_mode ? 'Update Address' : 'Confirm &amp; Save Address' ?>
                </button>
                <!-- Isara ito pagkatapos ng NOTES card at bago ang submit button -->
            </div>
        </form>
    </div>


    <?php include ROOT_PATH . '/user/navbar/footer.php'; ?>
    <script>

        const MAPBOX_TOKEN = 'pk.eyJ1Ijoid2VuZGhpbCIsImEiOiJjbWx1NmIzMDgwM25kM2RyMnVuOTNuMzhrIn0.45jN2HjKO_iRMlF-8gWcwQ';

        mapboxgl.accessToken = MAPBOX_TOKEN;

        const map = new mapboxgl.Map({
            container: 'map',
            style: 'mapbox://styles/mapbox/streets-v12',
            center: [121.0, 14.55],
            zoom: 11
        });
        map.addControl(new mapboxgl.NavigationControl(), 'top-right');
        map.addControl(new mapboxgl.GeolocateControl({ positionOptions: { enableHighAccuracy: true }, trackUserLocation: false }), 'top-right');

        let marker = null;

        // FIX: added `skipReverseGeocode` param — true when suggestion already provides data
        function placeMarker(lng, lat, skipReverseGeocode = false) {
            if (marker) marker.remove();
            marker = new mapboxgl.Marker({ color: '#16a34a' }).setLngLat([lng, lat]).addTo(map);
            document.getElementById('latitude').value = lat.toFixed(7);
            document.getElementById('longitude').value = lng.toFixed(7);
            document.getElementById('displayLat').textContent = lat.toFixed(6);
            document.getElementById('displayLng').textContent = lng.toFixed(6);
            if (!skipReverseGeocode) {
                reverseGeocode(lng, lat); // only for raw map clicks
            }

            // Unlock the form
            document.getElementById('formLocked').style.display = 'none';
            document.getElementById('formSections').style.display = 'block';
        }

        // Raw map click — DO reverse geocode
        map.on('click', (e) => {
            placeMarker(e.lngLat.lng, e.lngLat.lat, false);
            map.flyTo({ center: [e.lngLat.lng, e.lngLat.lat], zoom: Math.max(map.getZoom(), 15) });
        });

        async function reverseGeocode(lng, lat) {
            try {
                const res = await fetch(`https://api.mapbox.com/geocoding/v5/mapbox.places/${lng},${lat}.json?access_token=${MAPBOX_TOKEN}&country=PH&language=en&types=address,poi,neighborhood,locality,place`);
                const data = await res.json();
                if (data.features?.length) fillFromGeocode(data.features[0]);
            } catch (e) { console.error(e); }
        }

        function fillFromGeocode(feature) {
            const ctx = feature.context || [];
            let neighborhood = '', postcode = '', city = '', region = '';

            ctx.forEach(c => {
                if (c.id.startsWith('postcode')) postcode = c.text;
                if (c.id.startsWith('place')) city = c.text;
                if (c.id.startsWith('region')) region = c.text;
                if (c.id.startsWith('neighborhood') || c.id.startsWith('locality')) neighborhood = c.text;
            });

            // FIX: fallback — extract region from place_name if still empty
            if (!region) {
                const parts = feature.place_name.split(',').map(s => s.trim());
                // Typically: "Street, Barangay, City, Region, Philippines"
                if (parts.length >= 4) region = parts[parts.length - 2]; // second to last before "Philippines"
            }

            let addrParts = [];
            if (feature.address) addrParts.push(feature.address);
            if (feature.text) addrParts.push(feature.text);
            if (neighborhood) addrParts.push(neighborhood);

            const addrStr = addrParts.join(', ') || feature.place_name.split(',')[0];
            setFields(addrStr, city, region, postcode, feature.place_name);
        }

        function setFields(address, city, region, postcode, pinLabel) {
            if (address) document.getElementById('fieldAddress').value = address;
            if (city) document.getElementById('fieldCity').value = city;
            if (region) document.getElementById('fieldState').value = region;
            if (postcode) document.getElementById('fieldPostal').value = postcode;
            const pin = pinLabel || '';
            document.getElementById('pinAddress').textContent = pin.length > 80 ? pin.slice(0, 80) + '...' : pin;
            document.getElementById('pinStatus').classList.remove('hidden');
        }

        // ── SEARCH BOX API v1 ──
        const searchInput = document.getElementById('searchInput');
        const suggestionsEl = document.getElementById('suggestions');
        const spinner = document.getElementById('searchSpinner');
        let debounce = null;
        let sessionToken = (self.crypto?.randomUUID?.()) || (Math.random().toString(36).slice(2));

        searchInput.addEventListener('input', () => {
            clearTimeout(debounce);
            const q = searchInput.value.trim();

            // BAGO: kapag binura ang search, i-lock ulit
            if (q.length === 0) {
                suggestionsEl.classList.add('hidden');

                // Alisin ang marker
                if (marker) { marker.remove(); marker = null; }

                // I-clear ang hidden lat/lng
                document.getElementById('latitude').value = '';
                document.getElementById('longitude').value = '';

                // I-lock ulit ang form
                document.getElementById('pinStatus').classList.add('hidden');
                document.getElementById('formLocked').style.display = '';
                document.getElementById('formSections').style.display = 'none';

                return;
            }

            if (q.length < 2) { suggestionsEl.classList.add('hidden'); return; }
            spinner.classList.remove('hidden');
            debounce = setTimeout(() => doSearch(q), 350);
        });

        async function doSearch(q) {
            try {
                const c = map.getCenter();
                const url = 'https://api.mapbox.com/search/searchbox/v1/suggest'
                    + '?q=' + encodeURIComponent(q)
                    + '&access_token=' + MAPBOX_TOKEN
                    + '&session_token=' + sessionToken
                    + '&country=PH'
                    + '&language=en'
                    + '&limit=7'
                    + '&proximity=' + c.lng.toFixed(6) + ',' + c.lat.toFixed(6);

                const res = await fetch(url);
                const data = await res.json();
                spinner.classList.add('hidden');
                renderSuggestions(data.suggestions || []);
            } catch (e) { spinner.classList.add('hidden'); console.error(e); }
        }

        async function retrievePlace(mapboxId) {
            const res = await fetch(
                'https://api.mapbox.com/search/searchbox/v1/retrieve/' + mapboxId
                + '?access_token=' + MAPBOX_TOKEN
                + '&session_token=' + sessionToken
            );
            sessionToken = (self.crypto?.randomUUID?.()) || (Math.random().toString(36).slice(2));
            const data = await res.json();
            return data.features?.[0] || null;
        }

        function renderSuggestions(suggestions) {
            if (!suggestions.length) {
                suggestionsEl.innerHTML = '<div class="px-4 py-3 text-sm text-gray-400">No results found. Try clicking directly on the map.</div>';
                suggestionsEl.classList.remove('hidden');
                return;
            }
            suggestionsEl.innerHTML = suggestions.map((s) => {
                const main = s.name || '';
                const sub = s.place_formatted || s.full_address || '';
                return '<div class="suggestion-item flex items-start gap-3 px-4 py-3 cursor-pointer border-b border-gray-100 last:border-0 transition-colors" data-id="' + s.mapbox_id + '">'
                    + '<svg class="w-4 h-4 text-green-500 mt-0.5 shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C8.134 2 5 5.134 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.866-3.134-7-7-7z"/></svg>'
                    + '<div class="min-w-0">'
                    + '<p class="text-sm font-medium text-gray-800 truncate">' + main + '</p>'
                    + '<p class="text-xs text-gray-400 mt-0.5 truncate">' + sub + '</p>'
                    + '</div></div>';
            }).join('');
            suggestionsEl.classList.remove('hidden');

            suggestionsEl.querySelectorAll('.suggestion-item').forEach(el => {
                el.addEventListener('click', async () => {
                    suggestionsEl.classList.add('hidden');
                    spinner.classList.remove('hidden');
                    try {
                        const feature = await retrievePlace(el.dataset.id);
                        spinner.classList.add('hidden');
                        if (!feature) return;

                        const [lng, lat] = feature.geometry.coordinates;
                        map.flyTo({ center: [lng, lat], zoom: 17, speed: 1.4 });

                        // FIX: pass true — skip reverse geocode, we already have the data below
                        placeMarker(lng, lat, true);

                        const p = feature.properties || {};
                        const ctx = p.context || {};

                        const poiName = p.name || '';
                        const streetAddr = p.address || '';
                        const neighborhood = ctx.neighborhood?.name || ctx.locality?.name || '';
                        const city = ctx.place?.name || ctx.district?.name || '';
                        const region = ctx.region?.name || '';   // FIX: Search Box API reliably returns region
                        const postcode = ctx.postcode?.name || '';

                        let addrStr = [streetAddr, poiName].filter(Boolean).join(' ');
                        if (neighborhood && !addrStr.toLowerCase().includes(neighborhood.toLowerCase()))
                            addrStr += (addrStr ? ', ' : '') + neighborhood;

                        setFields(addrStr, city, region, postcode, p.full_address || poiName);
                        searchInput.value = p.full_address || poiName;
                    } catch (e) {
                        spinner.classList.add('hidden');
                        console.error(e);
                    }
                });
            });
        }

        document.addEventListener('click', (e) => {
            if (!document.getElementById('searchWrapper').contains(e.target))
                suggestionsEl.classList.add('hidden');
        });

        // Phone auto-format
        document.getElementById('phoneInput').addEventListener('input', function () {
            let val = this.value.replace(/\D/g, '').slice(0, 10);
            if (val.length > 7) val = val.slice(0, 4) + ' ' + val.slice(4, 7) + ' ' + val.slice(7);
            else if (val.length > 4) val = val.slice(0, 4) + ' ' + val.slice(4);
            this.value = val;
        });

        // Submit validation
        document.getElementById('addressForm').addEventListener('submit', function (e) {
            const lat = document.getElementById('latitude').value;
            const lng = document.getElementById('longitude').value;
            const errBox = document.getElementById('jsErrorBox');
            const errMsg = document.getElementById('jsErrorMsg');
            if (!lat || !lng) {
                e.preventDefault();
                errMsg.textContent = 'Please search and pin your location on the map first.';
                errBox.classList.remove('hidden');
                errBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }
            errBox.classList.add('hidden');
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.innerHTML = '<svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/></svg> Saving...';
        });

        // ── AUTO-FILL MAP & FORM IN EDIT MODE ──
        <?php if ($is_edit_mode && $edit_data): ?>
            const editLat = <?= floatval($edit_data['latitude']) ?>;
            const editLng = <?= floatval($edit_data['longitude']) ?>;

            map.on('load', () => {
                map.flyTo({ center: [editLng, editLat], zoom: 16, speed: 1.2 });
                placeMarker(editLng, editLat, true); // true = skip reverse geocode

                // Pre-fill address fields from DB
                document.getElementById('fieldAddress').value = <?= json_encode($edit_data['address'] ?? '') ?>;
                document.getElementById('fieldCity').value = <?= json_encode($edit_data['city'] ?? '') ?>;
                document.getElementById('fieldState').value = <?= json_encode($edit_data['state'] ?? '') ?>;
                document.getElementById('fieldPostal').value = <?= json_encode($edit_data['postal_code'] ?? '') ?>;

                // Pre-fill notes
                document.querySelector('textarea[name="notes"]').value = <?= json_encode($edit_data['notes'] ?? '') ?>;

                // Show pin status with saved address label
                document.getElementById('pinAddress').textContent = <?= json_encode(
                    $edit_data['address'] . ', ' . $edit_data['city'] . ', ' . $edit_data['state']
                ) ?>;
                document.getElementById('pinStatus').classList.remove('hidden');
            });
        <?php endif; ?>

    </script>
</body>

</html>