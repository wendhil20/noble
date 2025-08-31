<?php
session_name("nobleadmin");
session_start();
require_once '../role/roleaccount.php';
require_role(['hr', 'superadmin']);
// Check if user is logged in
if (!isset($_SESSION['noble_user'])) {
    // Redirect to login page
    header("Location: ../../loginpage/index.php");
    exit();
}

// Optional: Auto-logout after inactivity (e.g. 10 hrs)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 86400) {
    // Destroy session and redirect to login
    session_unset();
    session_destroy();
    header("Location: ../../loginpage/index.php?timeout=true");
    exit();
}

require_once '../../connection/connect.php'; // make sure this sets $conn (mysqli)
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Account Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>

<body class="bg-gray-50">

    <?php include '../navbar/top.php'; ?>

    <!-- Header -->
    <div class="bg-white border-b border-gray-200">
        <div class="max-w-full mx-auto px-4 py-6">
            <h1 class="text-3xl font-semibold text-orange-400">Account Management System</h1>
            <p class="mt-2 text-gray-600">Manage user accounts and verification processes</p>
        </div>
    </div>

    <div class="max-w-full mx-auto px-4 py-8">

        <!-- Notification -->
        <div id="notification" class="fixed top-4 right-4 z-50 hidden">
            <div id="notification-content" class="bg-green-600 text-white px-4 py-3 rounded-md shadow-lg">
                <span>Operation completed successfully</span>
            </div>
        </div>

        <!-- Custom Confirmation Modal -->
        <div id="confirmationModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <!-- Background overlay -->
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" id="modalOverlay"></div>

                <!-- Modal panel -->
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full sm:mx-0 sm:h-10 sm:w-10" id="modal-icon-container">
                                <!-- Warning icon (default) -->
                                <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true" id="modal-icon">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                </svg>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                    Confirm Action
                                </h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500" id="modal-message">
                                        Are you sure you want to proceed with this action?
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="button" id="confirmButton" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 text-base font-medium text-white focus:outline-none focus:ring-2 focus:ring-offset-2 sm:ml-3 sm:w-auto sm:text-sm">
                            Confirm
                        </button>
                        <button type="button" id="cancelButton" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Rejection Reason Modal -->
        <div id="rejectionModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="rejection-modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <!-- Background overlay -->
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" id="rejectionModalOverlay"></div>

                <!-- Modal panel -->
                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                                <!-- X icon -->
                                <svg class="h-6 w-6 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                <h3 class="text-lg leading-6 font-medium text-gray-900" id="rejection-modal-title">
                                    Reject User Verification
                                </h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500 mb-4">
                                        Please provide a reason for rejecting this user's verification. This message will be sent to the user.
                                    </p>
                                    <textarea id="rejectionReason" rows="4" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 resize-none" placeholder="Enter the reason for rejection (e.g., Invalid ID document, Unclear image quality, Missing information, etc.)"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="button" id="confirmRejectButton" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm">
                            Reject Verification
                        </button>
                        <button type="button" id="cancelRejectButton" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <?php
            // Get stats
            $user_stats = mysqli_query($conn, "SELECT 
                COUNT(*) as total_users,
                SUM(CASE WHEN is_verified = 1 THEN 1 ELSE 0 END) as verified_users,
                SUM(CASE WHEN is_verified = 0 THEN 1 ELSE 0 END) as pending_users
                FROM user_details");
            $user_data = mysqli_fetch_assoc($user_stats);

            $noble_stats = mysqli_query($conn, "SELECT 
                COUNT(*) as total_accounts,
                SUM(CASE WHEN verified = 1 THEN 1 ELSE 0 END) as verified_accounts,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_accounts
                FROM nobleaccount");
            $noble_data = mysqli_fetch_assoc($noble_stats);
            ?>

            <div class="bg-white border border-gray-200 rounded-lg p-6">
                <div class="text-sm font-medium text-gray-500 uppercase tracking-wide">Total Users</div>
                <div class="mt-1 text-3xl font-semibold text-gray-900"><?= $user_data['total_users'] ?></div>
            </div>

            <div class="bg-white border border-gray-200 rounded-lg p-6">
                <div class="text-sm font-medium text-gray-500 uppercase tracking-wide">Verified Users</div>
                <div class="mt-1 text-3xl font-semibold text-green-600"><?= $user_data['verified_users'] ?></div>
            </div>

            <div class="bg-white border border-gray-200 rounded-lg p-6">
                <div class="text-sm font-medium text-gray-500 uppercase tracking-wide">Pending Verification</div>
                <div class="mt-1 text-3xl font-semibold text-yellow-600"><?= $user_data['pending_users'] ?></div>
            </div>

            <div class="bg-white border border-gray-200 rounded-lg p-6">
                <div class="text-sm font-medium text-gray-500 uppercase tracking-wide">Noble Accounts</div>
                <div class="mt-1 text-3xl font-semibold text-gray-900"><?= $noble_data['total_accounts'] ?></div>
            </div>
        </div>

        <!-- User Details Management -->
        <div class="mb-10">
            <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h2 class="text-xl font-semibold text-gray-900">User Details Management</h2>
                </div>
                <!-- Scrollable container with max height -->
                <div class="overflow-auto max-h-96" style="scrollbar-width: thin; scrollbar-color: #cbd5e1 #f1f5f9;">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50 sticky top-0 z-10">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email Address</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mobile Number</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Gender</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Birth Place</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Birth Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Occupation</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Government ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Verification Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php
                            $sql = "SELECT ud.detail_id, ud.user_id, ud.sex, ud.birthplace, ud.birthdate, ud.occupation, ud.is_verified, ud.id_type, ud.government_id_path,
                         u.name, u.email, u.mobile
                      FROM user_details AS ud
                      JOIN users AS u ON u.id = ud.user_id
                      ORDER BY ud.detail_id DESC";
                            $result = mysqli_query($conn, $sql);

                            // Government ID types mapping
                            $id_types = [
                                'drivers_license' => "Driver's License",
                                'passport' => 'Passport',
                                'sss_id' => 'SSS ID',
                                'philhealth_id' => 'PhilHealth ID',
                                'tin_id' => 'TIN ID',
                                'postal_id' => 'Postal ID',
                                'voters_id' => "Voter's ID",
                                'prc_id' => 'PRC ID',
                                'senior_citizen_id' => 'Senior Citizen ID',
                                'pwd_id' => 'PWD ID',
                                'umid' => 'UMID',
                                'national_id' => 'National ID (PhilSys)',
                                'other' => 'Other Valid Government ID'
                            ];

                            if ($result && mysqli_num_rows($result) > 0) {
                                while ($row = mysqli_fetch_assoc($result)):
                                    $detail_id = (int)$row['detail_id'];
                                    $is_verified = (int)$row['is_verified'];
                            ?>
                                    <tr id="row-<?= $detail_id ?>" class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($row['name']) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?= htmlspecialchars($row['email']) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?= htmlspecialchars($row['mobile']) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?= htmlspecialchars($row['sex']) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?= htmlspecialchars($row['birthplace']) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?= htmlspecialchars($row['birthdate']) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?= htmlspecialchars($row['occupation']) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                            <?= !empty($row['id_type']) ? ($id_types[$row['id_type']] ?? ucfirst(str_replace('_', ' ', $row['id_type']))) : 'Not specified' ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                            <?php if (!empty($row['government_id_path'])): ?>
                                                <a href="../../uploads/government_ids/<?= htmlspecialchars($row['government_id_path']) ?>"
                                                    target="_blank"
                                                    class="text-blue-600 hover:text-blue-800 underline">
                                                    View ID
                                                </a>
                                            <?php else: ?>
                                                <span class="text-gray-400">No ID uploaded</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php if ($is_verified === 1): ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 border border-green-200">
                                                     Verified
                                                </span>
                                            <?php elseif ($is_verified === -1): ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 border border-red-200">
                                                     Rejected
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 border border-yellow-200">
                                                     Pending Verification
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <?php if ($is_verified === 0): ?>
                                                <div class="flex space-x-2">
                                                    <button class="approve-btn bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded-md text-sm font-medium border border-transparent focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2"
                                                        data-detail-id="<?= $detail_id ?>">
                                                         Approve
                                                    </button>
                                                    <button class="reject-btn bg-red-600 hover:bg-red-700 text-white px-3 py-1.5 rounded-md text-sm font-medium border border-transparent focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                                                        data-detail-id="<?= $detail_id ?>" data-user-name="<?= htmlspecialchars($row['name']) ?>">
                                                         Reject
                                                    </button>
                                                </div>
                                            <?php elseif ($is_verified === 1): ?>
                                                <span class="text-green-600 text-sm font-medium">✓ Verification Complete</span>
                                            <?php else: ?>
                                                <button class="reset-btn bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-md text-sm font-medium border border-transparent focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                                                    data-detail-id="<?= $detail_id ?>">
                                                    Reset Status
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                            <?php
                                endwhile;
                            } else {
                                echo '<tr><td colspan="10" class="px-6 py-8 text-center text-gray-500">No noble account records available</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>


<!-- Noble Account Management -->
<div class="mb-10">
    <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
        <div class="border-b border-gray-200 px-6 py-4">
            <h2 class="text-xl font-semibold text-gray-900">Noble Account Management</h2>
        </div>
        <!-- Scrollable container with max height -->
        <div class="overflow-auto max-h-96" style="scrollbar-width: thin; scrollbar-color: #cbd5e1 #f1f5f9;">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50 sticky top-0 z-10">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Full Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Level/Role</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supplier ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sales ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Online Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Verification Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Account Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php
                    $noble_sql = "SELECT id, fullname, email, lvl as role, verified, status, created_at, supplier_id, sales_id, is_online, last_login
                                  FROM nobleaccount 
                                  ORDER BY created_at DESC";
                    $noble_result = mysqli_query($conn, $noble_sql);

                    if ($noble_result && mysqli_num_rows($noble_result) > 0) {
                        while ($noble_row = mysqli_fetch_assoc($noble_result)):
                            $account_id = (int)$noble_row['id'];
                            $is_verified = (int)$noble_row['verified'];
                            $account_status = $noble_row['status'];
                    ?>
                            <tr id="noble-row-<?= $account_id ?>" class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?= htmlspecialchars($noble_row['fullname']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                    <?= htmlspecialchars($noble_row['email']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 border border-blue-200">
                                        <?= htmlspecialchars($noble_row['role'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                    <?= $noble_row['supplier_id'] ? htmlspecialchars($noble_row['supplier_id']) : '-' ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                    <?= $noble_row['sales_id'] ? htmlspecialchars($noble_row['sales_id']) : '-' ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($noble_row['is_online']): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 border border-green-200">
                                            ● Online
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 border border-gray-200">
                                            ● Offline
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($is_verified === 1): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 border border-green-200">
                                             Verified
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 border border-yellow-200">
                                             Pending Verification
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($account_status === 'active'): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 border border-green-200">
                                            ● Active
                                        </span>
                                    <?php elseif ($account_status === 'inactive'): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 border border-red-200">
                                            ● Inactive
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 border border-gray-200">
                                            ● <?= ucfirst(htmlspecialchars($account_status)) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <div class="flex space-x-2">
                                        <?php if ($is_verified === 0): ?>
                                            <button class="approve-noble-btn bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded-md text-sm font-medium border border-transparent focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2"
                                                data-account-id="<?= $account_id ?>">
                                                ✓ Verify
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if ($account_status === 'inactive'): ?>
                                            <button class="activate-btn bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-md text-sm font-medium border border-transparent focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                                                data-account-id="<?= $account_id ?>">
                                                ● Activate
                                            </button>
                                        <?php elseif ($account_status === 'active'): ?>
                                            <button class="deactivate-btn bg-red-600 hover:bg-red-700 text-white px-3 py-1.5 rounded-md text-sm font-medium border border-transparent focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                                                data-account-id="<?= $account_id ?>">
                                                ● Deactivate
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                    <?php
                        endwhile;
                    } else {
                        echo '<tr><td colspan="9" class="px-6 py-8 text-center text-gray-500">No noble account records available</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
    </div>

    <script>
        // Modal functionality
        const modal = document.getElementById('confirmationModal');
        const modalTitle = document.getElementById('modal-title');
        const modalMessage = document.getElementById('modal-message');
        const confirmButton = document.getElementById('confirmButton');
        const cancelButton = document.getElementById('cancelButton');
        const modalOverlay = document.getElementById('modalOverlay');
        const modalIconContainer = document.getElementById('modal-icon-container');
        const modalIcon = document.getElementById('modal-icon');

        // Rejection modal elements
        const rejectionModal = document.getElementById('rejectionModal');
        const rejectionReason = document.getElementById('rejectionReason');
        const confirmRejectButton = document.getElementById('confirmRejectButton');
        const cancelRejectButton = document.getElementById('cancelRejectButton');
        const rejectionModalOverlay = document.getElementById('rejectionModalOverlay');

        let currentAction = null;
        let currentRejectAction = null;

        // Function to show confirmation modal
        function showConfirmationModal(title, message, callback, type = 'warning') {
            modalTitle.textContent = title;
            modalMessage.textContent = message;

            // Update modal styling based on type
            if (type === 'approve') {
                modalIconContainer.className = 'mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-green-100 sm:mx-0 sm:h-10 sm:w-10';
                modalIcon.className = 'h-6 w-6 text-green-600';
                modalIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />';
                confirmButton.className = 'w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:ml-3 sm:w-auto sm:text-sm';
            } else if (type === 'danger') {
                modalIconContainer.className = 'mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10';
                modalIcon.className = 'h-6 w-6 text-red-600';
                modalIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />';
                confirmButton.className = 'w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm';
            } else {
                modalIconContainer.className = 'mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-yellow-100 sm:mx-0 sm:h-10 sm:w-10';
                modalIcon.className = 'h-6 w-6 text-yellow-600';
                modalIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />';
                confirmButton.className = 'w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm';
            }

            modal.classList.remove('hidden');
            currentAction = callback;
        }

        // Function to show rejection modal
        function showRejectionModal(callback) {
            rejectionReason.value = '';
            rejectionModal.classList.remove('hidden');
            currentRejectAction = callback;
        }

        // Function to hide modals
        function hideModal() {
            modal.classList.add('hidden');
            currentAction = null;
        }

        function hideRejectionModal() {
            rejectionModal.classList.add('hidden');
            currentRejectAction = null;
            rejectionReason.value = '';
        }

        // Modal event listeners
        confirmButton.addEventListener('click', () => {
            if (currentAction) {
                currentAction();
            }
            hideModal();
        });

        cancelButton.addEventListener('click', hideModal);
        modalOverlay.addEventListener('click', hideModal);

        // Rejection modal event listeners
        confirmRejectButton.addEventListener('click', () => {
            const reason = rejectionReason.value.trim();
            if (!reason) {
                showNotification('Please provide a reason for rejection', 'error');
                return;
            }
            if (currentRejectAction) {
                currentRejectAction(reason);
            }
            hideRejectionModal();
        });

        cancelRejectButton.addEventListener('click', hideRejectionModal);
        rejectionModalOverlay.addEventListener('click', hideRejectionModal);

        // Handle user verification actions
        document.addEventListener('click', (e) => {
            const approveBtn = e.target.closest('.approve-btn');
            const rejectBtn = e.target.closest('.reject-btn');
            const resetBtn = e.target.closest('.reset-btn');

            if (approveBtn) {
                const detailId = approveBtn.dataset.detailId;
                if (!detailId) return;

                showConfirmationModal(
                    'Approve User Verification',
                    'Are you sure you want to approve verification for this user?',
                    () => processUserVerification(detailId, 'approve', approveBtn),
                    'approve'
                );
            }

            if (rejectBtn) {
                const detailId = rejectBtn.dataset.detailId;
                const userName = rejectBtn.dataset.userName;
                if (!detailId) return;

                showRejectionModal((reason) => {
                    processUserVerification(detailId, 'reject', rejectBtn, reason);
                });
            }

            if (resetBtn) {
                const detailId = resetBtn.dataset.detailId;
                if (!detailId) return;

                showConfirmationModal(
                    'Reset Verification Status',
                    'Are you sure you want to reset this user\'s verification status to pending?',
                    () => processUserVerification(detailId, 'reset', resetBtn),
                    'warning'
                );
            }
        });

        // Process user verification
        function processUserVerification(detailId, action, btn, reason = null) {
            const originalText = btn.textContent;
            btn.disabled = true;
            btn.textContent = 'Processing...';
            btn.classList.add('opacity-75');

            const payload = {
                detail_id: detailId,
                action: action
            };

            if (reason) {
                payload.reason = reason;
            }

            fetch('manage_user_verification.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(payload)
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const row = document.getElementById('row-' + detailId);
                        if (row && action !== 'reset') {
                            // Update verification status column
                            const statusCell = row.querySelector('td:nth-child(10)');
                            const actionCell = row.querySelector('td:nth-child(11)');

                            if (action === 'approve') {
                                statusCell.innerHTML = '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 border border-green-200">✓ Verified</span>';
                                actionCell.innerHTML = '<span class="text-green-600 text-sm font-medium">✓ Verification Complete</span>';
                            } else if (action === 'reject') {
                                statusCell.innerHTML = '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 border border-red-200">✗ Rejected</span>';
                                actionCell.innerHTML = '<button class="reset-btn bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-md text-sm font-medium border border-transparent focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2" data-detail-id="' + detailId + '">Reset Status</button>';
                            }
                        }
                        showNotification(data.message || 'Operation completed successfully', 'success');

                        // Reload page after a short delay to update statistics
                        if (action !== 'reset') {
                            setTimeout(() => {
                                location.reload();
                            }, 2000);
                        }
                    } else {
                        btn.disabled = false;
                        btn.textContent = originalText;
                        btn.classList.remove('opacity-75');
                        showNotification(data.message || 'Operation failed', 'error');
                    }
                })
                .catch(err => {
                    console.error(err);
                    btn.disabled = false;
                    btn.textContent = originalText;
                    btn.classList.remove('opacity-75');
                    showNotification('Network error occurred', 'error');
                });
        }

        // Handle noble account actions
        document.addEventListener('click', (e) => {
            const verifyBtn = e.target.closest('.approve-noble-btn');
            const activateBtn = e.target.closest('.activate-btn');
            const deactivateBtn = e.target.closest('.deactivate-btn');

            if (verifyBtn) {
                const accountId = verifyBtn.dataset.accountId;
                showConfirmationModal(
                    'Verify Noble Account',
                    'Are you sure you want to verify this noble account?',
                    () => updateNobleAccount(accountId, 'verify', verifyBtn),
                    'approve'
                );
            }

            if (activateBtn) {
                const accountId = activateBtn.dataset.accountId;
                showConfirmationModal(
                    'Activate Noble Account',
                    'Are you sure you want to activate this noble account?',
                    () => updateNobleAccount(accountId, 'activate', activateBtn),
                    'approve'
                );
            }

            if (deactivateBtn) {
                const accountId = deactivateBtn.dataset.accountId;
                showConfirmationModal(
                    'Deactivate Noble Account',
                    'Are you sure you want to deactivate this noble account?',
                    () => updateNobleAccount(accountId, 'deactivate', deactivateBtn),
                    'danger'
                );
            }
        });

        function updateNobleAccount(accountId, action, btn) {
            const originalText = btn.textContent;
            btn.disabled = true;
            btn.textContent = 'Processing...';
            btn.classList.add('opacity-75');

            fetch('manage_noble_account.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        account_id: accountId,
                        action: action
                    })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        showNotification(data.message || 'Operation completed successfully', 'success');
                        setTimeout(() => {
                            location.reload();
                        }, 2000);
                    } else {
                        btn.disabled = false;
                        btn.textContent = originalText;
                        btn.classList.remove('opacity-75');
                        showNotification(data.message || 'Operation failed', 'error');
                    }
                })
                .catch(err => {
                    console.error(err);
                    btn.disabled = false;
                    btn.textContent = originalText;
                    btn.classList.remove('opacity-75');
                    showNotification('Network error occurred', 'error');
                });
        }

        function showNotification(text, type = 'success') {
            const container = document.getElementById('notification');
            const content = document.getElementById('notification-content');

            // Set styling based on type
            if (type === 'error') {
                content.className = 'bg-red-600 text-white px-4 py-3 rounded-md shadow-lg flex items-center';
                content.innerHTML = `
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span>${text}</span>
                `;
            } else if (type === 'warning') {
                content.className = 'bg-yellow-600 text-white px-4 py-3 rounded-md shadow-lg flex items-center';
                content.innerHTML = `
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                    </svg>
                    <span>${text}</span>
                `;
            } else {
                content.className = 'bg-green-600 text-white px-4 py-3 rounded-md shadow-lg flex items-center';
                content.innerHTML = `
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span>${text}</span>
                `;
            }

            // Show notification
            container.classList.remove('hidden');

            // Auto hide after 5 seconds
            setTimeout(() => {
                container.classList.add('hidden');
            }, 5000);
        }

        // Close modals with Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                if (!modal.classList.contains('hidden')) {
                    hideModal();
                }
                if (!rejectionModal.classList.contains('hidden')) {
                    hideRejectionModal();
                }
            }
        });
    </script>
</body>

</html>