<?php
session_name("nobleadmin");
session_start();
require_once '../role/roleaccount.php';
require_role(['productspecialist', 'superadmin']);
// Check if user is logged in
if (!isset($_SESSION['noble_user'])) {
    // Redirect to login page
    header("Location: ../../loginpage/index.php");
    exit();
}

// Optional: Auto-logout after inactivity (e.g. 10 hrs)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 3600) {
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
            <h1 class="text-3xl font-semibold text-gray-900">Account Management System</h1>
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
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-yellow-100 sm:mx-0 sm:h-10 sm:w-10">
                                <!-- Warning icon -->
                                <svg class="h-6 w-6 text-yellow-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
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
                        <button type="button" id="confirmButton" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                            Confirm
                        </button>
                        <button type="button" id="cancelButton" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
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
                SUM(CASE WHEN is_verified = 1 THEN 1 ELSE 0 END) as verified_users
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
                <div class="mt-1 text-3xl font-semibold text-gray-900"><?= $user_data['verified_users'] ?></div>
            </div>
            
            <div class="bg-white border border-gray-200 rounded-lg p-6">
                <div class="text-sm font-medium text-gray-500 uppercase tracking-wide">Noble Accounts</div>
                <div class="mt-1 text-3xl font-semibold text-gray-900"><?= $noble_data['total_accounts'] ?></div>
            </div>
            
            <div class="bg-white border border-gray-200 rounded-lg p-6">
                <div class="text-sm font-medium text-gray-500 uppercase tracking-wide">Active Accounts</div>
                <div class="mt-1 text-3xl font-semibold text-gray-900"><?= $noble_data['active_accounts'] ?></div>
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
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Verification Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php
                    $sql = "SELECT ud.detail_id, ud.user_id, ud.sex, ud.birthplace, ud.birthdate, ud.occupation, ud.is_verified,
                         u.name, u.email, u.mobile
                      FROM user_details AS ud
                      JOIN users AS u ON u.id = ud.user_id
                      ORDER BY ud.detail_id DESC";
                    $result = mysqli_query($conn, $sql);

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
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($is_verified): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 border border-green-200">
                                            Verified
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 border border-yellow-200">
                                            Pending Verification
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <?php if (!$is_verified): ?>
                                        <button class="approve-btn bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium border border-transparent focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                                            data-detail-id="<?= $detail_id ?>">
                                            Approve Verification
                                        </button>
                                    <?php else: ?>
                                        <span class="text-gray-500 text-sm font-medium">Verification Complete</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                    <?php
                        endwhile;
                    } else {
                        echo '<tr><td colspan="9" class="px-6 py-8 text-center text-gray-500">No user records available</td></tr>';
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
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Account ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email Address</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Full Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Access Level</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Account Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Verification Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supplier ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Created</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Login</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Available Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php
                    $noble_sql = "SELECT * FROM nobleaccount ORDER BY id DESC";
                    $noble_result = mysqli_query($conn, $noble_sql);

                    if ($noble_result && mysqli_num_rows($noble_result) > 0) {
                        while ($noble_row = mysqli_fetch_assoc($noble_result)):
                            $account_id = (int)$noble_row['id'];
                            $account_verified = (int)$noble_row['verified'];
                    ?>
                            <tr id="noble-row-<?= $account_id ?>" class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= $noble_row['id'] ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?= htmlspecialchars($noble_row['email']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($noble_row['fullname']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?= htmlspecialchars($noble_row['lvl']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($noble_row['status'] == 'active'): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 border border-green-200">
                                            Active
                                        </span>
                                    <?php elseif ($noble_row['status'] == 'inactive'): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 border border-red-200">
                                            Inactive
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 border border-gray-200">
                                            <?= htmlspecialchars($noble_row['status']) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($account_verified): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 border border-green-200">
                                            Verified
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 border border-yellow-200">
                                            Pending Verification
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                    <?= $noble_row['supplier_id'] ? htmlspecialchars($noble_row['supplier_id']) : 'Not Assigned' ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                    <?= date('F j, Y', strtotime($noble_row['created_at'])) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                    <?= $noble_row['last_login'] ? date('F j, Y', strtotime($noble_row['last_login'])) : 'Never' ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm space-x-3">
                                    <?php if (!$account_verified): ?>
                                        <button class="approve-noble-btn bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded-md text-sm font-medium border border-transparent focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2"
                                            data-account-id="<?= $account_id ?>">
                                            Verify Account
                                        </button>
                                    <?php else: ?>
                                        <span class="text-gray-500 text-sm">Verification Complete</span>
                                    <?php endif; ?>
                                    
                                    <?php if ($noble_row['status'] == 'active'): ?>
                                        <button class="deactivate-btn bg-red-600 hover:bg-red-700 text-white px-3 py-1.5 rounded-md text-sm font-medium border border-transparent focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                                            data-account-id="<?= $account_id ?>">
                                            Deactivate
                                        </button>
                                    <?php else: ?>
                                        <button class="activate-btn bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-md text-sm font-medium border border-transparent focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                                            data-account-id="<?= $account_id ?>">
                                            Activate
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
    </div>

    <script>
        // Modal functionality
        const modal = document.getElementById('confirmationModal');
        const modalTitle = document.getElementById('modal-title');
        const modalMessage = document.getElementById('modal-message');
        const confirmButton = document.getElementById('confirmButton');
        const cancelButton = document.getElementById('cancelButton');
        const modalOverlay = document.getElementById('modalOverlay');

        let currentAction = null;

        // Function to show modal
        function showConfirmationModal(title, message, callback) {
            modalTitle.textContent = title;
            modalMessage.textContent = message;
            modal.classList.remove('hidden');
            currentAction = callback;
        }

        // Function to hide modal
        function hideModal() {
            modal.classList.add('hidden');
            currentAction = null;
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

        // Handle user verification approval
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.approve-btn');
            if (!btn) return;

            const detailId = btn.dataset.detailId;
            if (!detailId) return;

            showConfirmationModal(
                'Approve User Verification',
                'Are you sure you want to approve verification for this user?',
                () => {
                    btn.disabled = true;
                    btn.textContent = 'Processing...';
                    btn.classList.add('opacity-75');

                    fetch('approve_verification.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                detail_id: detailId
                            })
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                const row = document.getElementById('row-' + detailId);
                                if (row) {
                                    row.querySelector('td:nth-child(8)').innerHTML = '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 border border-green-200">Verified</span>';
                                    row.querySelector('td:nth-child(9)').innerHTML = '<span class="text-gray-500 text-sm">Verification Complete</span>';
                                }
                                showNotification(data.message || 'User verification approved successfully', 'success');
                            } else {
                                btn.disabled = false;
                                btn.textContent = 'Approve Verification';
                                btn.classList.remove('opacity-75');
                                showNotification(data.message || 'Failed to approve verification', 'error');
                            }
                        })
                        .catch(err => {
                            console.error(err);
                            btn.disabled = false;
                            btn.textContent = 'Approve Verification';
                            btn.classList.remove('opacity-75');
                            showNotification('Network error occurred', 'error');
                        });
                }
            );
        });

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
                    () => updateNobleAccount(accountId, 'verify', verifyBtn)
                );
            }
            
            if (activateBtn) {
                const accountId = activateBtn.dataset.accountId;
                showConfirmationModal(
                    'Activate Noble Account',
                    'Are you sure you want to activate this noble account?',
                    () => updateNobleAccount(accountId, 'activate', activateBtn)
                );
            }
            
            if (deactivateBtn) {
                const accountId = deactivateBtn.dataset.accountId;
                showConfirmationModal(
                    'Deactivate Noble Account',
                    'Are you sure you want to deactivate this noble account?',
                    () => updateNobleAccount(accountId, 'deactivate', deactivateBtn)
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
                content.className = 'bg-red-600 text-white px-4 py-3 rounded-md shadow-lg';
            } else if (type === 'warning') {
                content.className = 'bg-yellow-600 text-white px-4 py-3 rounded-md shadow-lg';
            } else {
                content.className = 'bg-green-600 text-white px-4 py-3 rounded-md shadow-lg';
            }
            
            content.innerHTML = `<span>${text}</span>`;
            
            // Show notification
            container.classList.remove('hidden');
            
            // Auto hide after 5 seconds
            setTimeout(() => {
                container.classList.add('hidden');
            }, 5000);
        }

        // Close modal with Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
                hideModal();
            }
        });
    </script>
</body>

</html>