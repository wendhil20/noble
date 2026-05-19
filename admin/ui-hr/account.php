<?php
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";

require_role(['hr', 'superadmin']);
if (!isset($_SESSION['noble_user'])) {
    header("Location: " . BASE_URL . "/main");
    exit();
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Account Management</title>
    <style>
        .scrollable {
            overflow: auto;
            max-height: 420px;
            scrollbar-width: thin;
            scrollbar-color: #e2e8f0 transparent;
        }

        /* Fade-in rows */
        tbody tr {
            animation: fadeIn 0.2s ease both;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(4px);
            }

            to {
                opacity: 1;
                transform: none;
            }
        }

        /* Subtle hover on table rows */
        tbody tr:hover {
            background-color: #fafafa;
        }

        /* Modal backdrop blur */
        .modal-backdrop {
            backdrop-filter: blur(2px);
        }

        /* Tab styles */
        .tab-btn {
            color: #94a3b8;
            border-color: transparent;
        }

        .tab-btn:hover {
            color: #475569;
            background-color: #f8fafc;
        }

        .tab-btn.active-tab {
            color: #f97316;
            border-color: #f97316;
            background-color: #fff7ed;
        }

        .tab-btn.active-tab span {
            background-color: #fed7aa;
            color: #c2410c;
        }
    </style>
</head>

<body class="bg-slate-50 text-slate-800 antialiased">

    <?php include ROOT_PATH . '/admin/navbar/top.php'; ?>

    <!-- ─── Page Header ─────────────────────────────────────────── -->
    <header class="bg-white border-b border-slate-200">
        <div class="max-w-screen-2xl mx-auto px-6 py-5 flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-orange-500 uppercase tracking-widest mb-1">Admin Panel</p>
                <h1 class="text-2xl font-semibold text-slate-900">Account Management</h1>
                <p class="text-sm text-slate-500 mt-0.5">Manage user accounts and verification processes</p>
            </div>
            <a href="<?= BASE_URL ?>/accounts" target="_blank"
                class="inline-flex items-center gap-2 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Registration Account
            </a>
        </div>
    </header>

    <!-- ─── Toast Notification ───────────────────────────────────── -->
    <div id="notification" class="fixed top-5 right-5 z-50 hidden">
        <div id="notification-content"
            class="flex items-center gap-3 px-4 py-3 rounded-xl shadow-xl text-sm font-medium text-white min-w-64">
            <span></span>
        </div>
    </div>

    <!-- ─── Confirm Modal ─────────────────────────────────────────── -->
    <div id="confirmationModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4">
        <div id="modalOverlay" class="absolute inset-0 bg-slate-900/40 modal-backdrop"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
            <div class="flex items-start gap-4">
                <div id="modal-icon-container"
                    class="flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center">
                    <svg id="modal-icon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
                    </svg>
                </div>
                <div>
                    <h3 id="modal-title" class="text-base font-semibold text-slate-900">Confirm Action</h3>
                    <p id="modal-message" class="text-sm text-slate-500 mt-1">Are you sure you want to proceed?</p>
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-6">
                <button id="cancelButton"
                    class="px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors">
                    Cancel
                </button>
                <button id="confirmButton"
                    class="px-4 py-2 text-sm font-medium text-white rounded-lg transition-colors">
                    Confirm
                </button>
            </div>
        </div>
    </div>

    <!-- ─── Rejection Modal ───────────────────────────────────────── -->
    <div id="rejectionModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4">
        <div id="rejectionModalOverlay" class="absolute inset-0 bg-slate-900/40 modal-backdrop"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
            <div class="flex items-start gap-4 mb-4">
                <div class="flex-shrink-0 w-10 h-10 rounded-full bg-red-50 flex items-center justify-center">
                    <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-base font-semibold text-slate-900">Reject Verification</h3>
                    <p class="text-sm text-slate-500 mt-1">Provide a reason — this will be sent to the user.</p>
                </div>
            </div>
            <textarea id="rejectionReason" rows="4"
                class="w-full p-3 text-sm border border-slate-200 rounded-xl focus:ring-2 focus:ring-red-400 focus:border-transparent resize-none outline-none transition"
                placeholder="e.g. Invalid ID document, unclear image quality…"></textarea>
            <div class="flex justify-end gap-3 mt-4">
                <button id="cancelRejectButton"
                    class="px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors">
                    Cancel
                </button>
                <button id="confirmRejectButton"
                    class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors">
                    Reject
                </button>
            </div>
        </div>
    </div>

    <!-- ─── Main Content ──────────────────────────────────────────── -->
    <main class="max-w-screen-2xl mx-auto px-6 py-8 space-y-8">

        <!-- Summary Stats -->
        <?php
        $user_stats = mysqli_query($conn, "SELECT COUNT(*) as total_users,
            SUM(CASE WHEN is_verified = 1 THEN 1 ELSE 0 END) as verified_users,
            SUM(CASE WHEN is_verified = 0 THEN 1 ELSE 0 END) as pending_users
            FROM user_details");
        $user_data = mysqli_fetch_assoc($user_stats);

        $noble_stats = mysqli_query($conn, "SELECT COUNT(*) as total_accounts,
            SUM(CASE WHEN verified = 1 THEN 1 ELSE 0 END) as verified_accounts,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_accounts
            FROM nobleaccount");
        $noble_data = mysqli_fetch_assoc($noble_stats);

        $stats = [
            ['label' => 'Total Users', 'value' => $user_data['total_users'], 'color' => 'text-slate-900'],
            ['label' => 'Verified Users', 'value' => $user_data['verified_users'], 'color' => 'text-emerald-600'],
            ['label' => 'Pending Verification', 'value' => $user_data['pending_users'], 'color' => 'text-amber-600'],
            ['label' => 'Noble Accounts', 'value' => $noble_data['total_accounts'], 'color' => 'text-slate-900'],
        ];
        ?>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <?php foreach ($stats as $s): ?>
                <div class="bg-white border border-slate-200 rounded-xl px-5 py-4">
                    <p class="text-xs font-medium text-slate-400 uppercase tracking-wide"><?= $s['label'] ?></p>
                    <p class="text-3xl font-semibold mt-1 <?= $s['color'] ?>"><?= $s['value'] ?></p>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- ─── Tabs ─────────────────────────────────────────────── -->
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">

            <!-- Tab Bar -->
            <div class="flex border-b border-slate-200 px-6 pt-4 gap-1">
                <button id="tab-users" onclick="switchTab('users')"
                    class="tab-btn active-tab flex items-center gap-2 px-4 py-2.5 text-sm font-medium rounded-t-lg transition-colors border-b-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    User Details
                    <span class="ml-1 text-xs font-semibold px-1.5 py-0.5 rounded-full bg-slate-100 text-slate-600">
                        <?= $user_data['total_users'] ?>
                    </span>
                </button>
                <button id="tab-noble" onclick="switchTab('noble')"
                    class="tab-btn flex items-center gap-2 px-4 py-2.5 text-sm font-medium rounded-t-lg transition-colors border-b-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0" />
                    </svg>
                    Noble Accounts
                    <span class="ml-1 text-xs font-semibold px-1.5 py-0.5 rounded-full bg-slate-100 text-slate-600">
                        <?= $noble_data['total_accounts'] ?>
                    </span>
                </button>
            </div>

            <!-- ── Tab: User Details ───────────────────────────────── -->
            <div id="panel-users" class="tab-panel">
                <div class="px-6 py-3 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <p class="text-xs text-slate-400">Review and manage ID verifications</p>
                </div>
                <div class="scrollable">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100">
                                <?php foreach (['Name', 'Email', 'Mobile', 'Gender', 'Birthplace', 'Birthdate', 'Occupation', 'ID Type', 'Gov\'t ID', 'Status', 'Actions'] as $h): ?>
                                    <th
                                        class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">
                                        <?= $h ?>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php
                            $sql = "SELECT ud.detail_id, ud.user_id, ud.sex, ud.birthplace, ud.birthdate, ud.occupation, ud.is_verified, ud.id_type, ud.government_id_path,
                                u.name, u.email, u.mobile
                            FROM user_details AS ud
                            JOIN users AS u ON u.id = ud.user_id
                            ORDER BY ud.detail_id DESC";
                            $result = mysqli_query($conn, $sql);

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
                                'other' => 'Other Gov\'t ID'
                            ];

                            if ($result && mysqli_num_rows($result) > 0):
                                while ($row = mysqli_fetch_assoc($result)):
                                    $detail_id = (int) $row['detail_id'];
                                    $is_verified = (int) $row['is_verified'];
                                    ?>
                                    <tr id="row-<?= $detail_id ?>">
                                        <td class="px-5 py-3 font-medium text-slate-900 whitespace-nowrap">
                                            <?= htmlspecialchars($row['name']) ?>
                                        </td>
                                        <td class="px-5 py-3 text-slate-600 whitespace-nowrap">
                                            <?= htmlspecialchars($row['email']) ?>
                                        </td>
                                        <td class="px-5 py-3 text-slate-600 mono whitespace-nowrap">
                                            <?= htmlspecialchars($row['mobile']) ?>
                                        </td>
                                        <td class="px-5 py-3 text-slate-600 whitespace-nowrap">
                                            <?= htmlspecialchars($row['sex']) ?>
                                        </td>
                                        <td class="px-5 py-3 text-slate-600 whitespace-nowrap">
                                            <?= htmlspecialchars($row['birthplace']) ?>
                                        </td>
                                        <td class="px-5 py-3 text-slate-600 mono whitespace-nowrap">
                                            <?= htmlspecialchars($row['birthdate']) ?>
                                        </td>
                                        <td class="px-5 py-3 text-slate-600 whitespace-nowrap">
                                            <?= htmlspecialchars($row['occupation']) ?>
                                        </td>
                                        <td class="px-5 py-3 text-slate-600 whitespace-nowrap">
                                            <?= !empty($row['id_type']) ? ($id_types[$row['id_type']] ?? ucfirst(str_replace('_', ' ', $row['id_type']))) : '<span class="text-slate-400">—</span>' ?>
                                        </td>
                                        <td class="px-5 py-3 whitespace-nowrap">
                                            <?php if (!empty($row['government_id_path'])): ?>
                                                <a href="<?= BASE_URL ?>/uploads/government_ids/<?= htmlspecialchars($row['government_id_path']) ?>"
                                                    target="_blank"
                                                    class="inline-flex items-center gap-1 text-orange-600 hover:text-orange-800 font-medium text-xs underline underline-offset-2">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                                    </svg>
                                                    View ID
                                                </a>
                                            <?php else: ?>
                                                <span class="text-slate-400 text-xs">No ID</span>
                                            <?php endif; ?>
                                        </td>
                                        <!-- Status badge -->
                                        <td class="px-5 py-3 whitespace-nowrap">
                                            <?php if ($is_verified === 1): ?>
                                                <span
                                                    class="inline-flex items-center gap-1 text-xs font-medium px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Verified
                                                </span>
                                            <?php elseif ($is_verified === -1): ?>
                                                <span
                                                    class="inline-flex items-center gap-1 text-xs font-medium px-2.5 py-1 rounded-full bg-red-50 text-red-700 border border-red-200">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span> Rejected
                                                </span>
                                            <?php else: ?>
                                                <span
                                                    class="inline-flex items-center gap-1 text-xs font-medium px-2.5 py-1 rounded-full bg-amber-50 text-amber-700 border border-amber-200">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                                    Pending
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <!-- Actions -->
                                        <td class="px-5 py-3 whitespace-nowrap">
                                            <?php if ($is_verified === 0): ?>
                                                <div class="flex gap-2">
                                                    <button
                                                        class="approve-btn px-3 py-1.5 text-xs font-medium text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg transition-colors"
                                                        data-detail-id="<?= $detail_id ?>">Approve</button>
                                                    <button
                                                        class="reject-btn px-3 py-1.5 text-xs font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors"
                                                        data-detail-id="<?= $detail_id ?>"
                                                        data-user-name="<?= htmlspecialchars($row['name']) ?>">Reject</button>
                                                </div>
                                            <?php elseif ($is_verified === 1): ?>
                                                <span class="text-xs text-emerald-600 font-medium">✓ Complete</span>
                                            <?php else: ?>
                                                <button
                                                    class="reset-btn px-3 py-1.5 text-xs font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors"
                                                    data-detail-id="<?= $detail_id ?>">Reset</button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; else: ?>
                                <tr>
                                    <td colspan="11" class="px-5 py-12 text-center text-slate-400">
                                        <svg class="w-8 h-8 mx-auto mb-2 text-slate-300" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                        </svg>
                                        No records available
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div><!-- end panel-users -->

            <!-- ── Tab: Noble Accounts ────────────────────────────── -->
            <div id="panel-noble" class="tab-panel hidden">
                <div class="px-6 py-3 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <p class="text-xs text-slate-400">Verify and activate noble user accounts</p>
                </div>
                <div class="scrollable">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100">
                                <?php foreach (['Full Name', 'Email', 'Role', 'Supplier ID', 'Sales ID', 'Online', 'Verification', 'Account Status', 'Actions'] as $h): ?>
                                    <th
                                        class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide whitespace-nowrap">
                                        <?= $h ?>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php
                            $noble_sql = "SELECT id, fullname, email, lvl as role, verified, status, supplier_id, sales_id, is_online, last_login, created_at
                            FROM nobleaccount ORDER BY created_at DESC";
                            $noble_result = mysqli_query($conn, $noble_sql);

                            if ($noble_result && mysqli_num_rows($noble_result) > 0):
                                while ($noble_row = mysqli_fetch_assoc($noble_result)):
                                    $account_id = (int) $noble_row['id'];
                                    $is_verified = (int) $noble_row['verified'];
                                    $account_status = $noble_row['status'];
                                    ?>
                                    <tr id="noble-row-<?= $account_id ?>">
                                        <td class="px-5 py-3 font-medium text-slate-900 whitespace-nowrap">
                                            <?= htmlspecialchars($noble_row['fullname']) ?>
                                        </td>
                                        <td class="px-5 py-3 text-slate-600 whitespace-nowrap">
                                            <?= htmlspecialchars($noble_row['email']) ?>
                                        </td>
                                        <td class="px-5 py-3 whitespace-nowrap">
                                            <span
                                                class="text-xs font-medium px-2.5 py-1 rounded-full bg-blue-50 text-blue-700 border border-blue-200">
                                                <?= htmlspecialchars($noble_row['role'] ?? 'N/A') ?>
                                            </span>
                                        </td>
                                        <td class="px-5 py-3 mono text-slate-500 whitespace-nowrap">
                                            <?= $noble_row['supplier_id'] ? htmlspecialchars($noble_row['supplier_id']) : '<span class="text-slate-300">—</span>' ?>
                                        </td>
                                        <td class="px-5 py-3 mono text-slate-500 whitespace-nowrap">
                                            <?= $noble_row['sales_id'] ? htmlspecialchars($noble_row['sales_id']) : '<span class="text-slate-300">—</span>' ?>
                                        </td>
                                        <td class="px-5 py-3 whitespace-nowrap">
                                            <?php if ($noble_row['is_online']): ?>
                                                <span class="inline-flex items-center gap-1 text-xs font-medium text-emerald-700">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                                    Online
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center gap-1 text-xs font-medium text-slate-400">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-slate-300"></span> Offline
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-5 py-3 whitespace-nowrap">
                                            <?php if ($is_verified === 1): ?>
                                                <span
                                                    class="inline-flex items-center gap-1 text-xs font-medium px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Verified
                                                </span>
                                            <?php else: ?>
                                                <span
                                                    class="inline-flex items-center gap-1 text-xs font-medium px-2.5 py-1 rounded-full bg-amber-50 text-amber-700 border border-amber-200">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                                    Pending
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-5 py-3 whitespace-nowrap">
                                            <?php if ($account_status === 'active'): ?>
                                                <span
                                                    class="inline-flex items-center gap-1 text-xs font-medium px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Active
                                                </span>
                                            <?php elseif ($account_status === 'inactive'): ?>
                                                <span
                                                    class="inline-flex items-center gap-1 text-xs font-medium px-2.5 py-1 rounded-full bg-slate-100 text-slate-600 border border-slate-200">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span> Inactive
                                                </span>
                                            <?php else: ?>
                                                <span
                                                    class="inline-flex items-center gap-1 text-xs font-medium px-2.5 py-1 rounded-full bg-slate-100 text-slate-600 border border-slate-200">
                                                    <?= ucfirst(htmlspecialchars($account_status)) ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-5 py-3 whitespace-nowrap">
                                            <div class="flex gap-2">
                                                <?php if ($is_verified === 0): ?>
                                                    <button
                                                        class="approve-noble-btn px-3 py-1.5 text-xs font-medium text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg transition-colors"
                                                        data-account-id="<?= $account_id ?>">Verify</button>
                                                <?php endif; ?>
                                                <?php if ($account_status === 'inactive'): ?>
                                                    <button
                                                        class="activate-btn px-3 py-1.5 text-xs font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors"
                                                        data-account-id="<?= $account_id ?>">Activate</button>
                                                <?php elseif ($account_status === 'active'): ?>
                                                    <button
                                                        class="deactivate-btn px-3 py-1.5 text-xs font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors"
                                                        data-account-id="<?= $account_id ?>">Deactivate</button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; else: ?>
                                <tr>
                                    <td colspan="9" class="px-5 py-12 text-center text-slate-400">
                                        <svg class="w-8 h-8 mx-auto mb-2 text-slate-300" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0" />
                                        </svg>
                                        No noble account records
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div><!-- end panel-noble -->

        </div><!-- end tabs card -->

    </main>

    <script>
        // ── Tab Switcher ───────────────────────────────────────────────
        function switchTab(name) {
            document.querySelectorAll('.tab-panel').forEach(p => p.classList.add('hidden'));
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active-tab'));
            document.getElementById('panel-' + name).classList.remove('hidden');
            document.getElementById('tab-' + name).classList.add('active-tab');
        }

        // ── Modal helpers ──────────────────────────────────────────────
        const modal = document.getElementById('confirmationModal');
        const modalOverlay = document.getElementById('modalOverlay');
        const modalTitle = document.getElementById('modal-title');
        const modalMessage = document.getElementById('modal-message');
        const confirmBtn = document.getElementById('confirmButton');
        const cancelBtn = document.getElementById('cancelButton');
        const iconWrap = document.getElementById('modal-icon-container');
        const iconEl = document.getElementById('modal-icon');

        const rejModal = document.getElementById('rejectionModal');
        const rejOverlay = document.getElementById('rejectionModalOverlay');
        const rejReason = document.getElementById('rejectionReason');
        const confirmRejBtn = document.getElementById('confirmRejectButton');
        const cancelRejBtn = document.getElementById('cancelRejectButton');

        let pendingAction = null;
        let pendingRejAction = null;

        const COLORS = {
            approve: { wrap: 'bg-emerald-50', icon: 'text-emerald-600', btn: 'bg-emerald-600 hover:bg-emerald-700' },
            danger: { wrap: 'bg-red-50', icon: 'text-red-600', btn: 'bg-red-600 hover:bg-red-700' },
            warning: { wrap: 'bg-amber-50', icon: 'text-amber-600', btn: 'bg-blue-600 hover:bg-blue-700' },
        };

        function showModal(title, message, cb, type = 'warning') {
            const c = COLORS[type];
            modalTitle.textContent = title;
            modalMessage.textContent = message;
            iconWrap.className = `flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center ${c.wrap}`;
            iconEl.className = `w-5 h-5 ${c.icon}`;
            confirmBtn.className = `px-4 py-2 text-sm font-medium text-white rounded-lg transition-colors ${c.btn}`;
            modal.classList.remove('hidden');
            pendingAction = cb;
        }

        function hideModal() { modal.classList.add('hidden'); pendingAction = null; }
        function showRejModal(cb) { rejReason.value = ''; rejModal.classList.remove('hidden'); pendingRejAction = cb; }
        function hideRejModal() { rejModal.classList.add('hidden'); pendingRejAction = null; }

        confirmBtn.addEventListener('click', () => { if (pendingAction) pendingAction(); hideModal(); });
        cancelBtn.addEventListener('click', hideModal);
        modalOverlay.addEventListener('click', hideModal);

        confirmRejBtn.addEventListener('click', () => {
            const reason = rejReason.value.trim();
            if (!reason) { showToast('Please provide a rejection reason.', 'error'); return; }
            if (pendingRejAction) pendingRejAction(reason);
            hideRejModal();
        });
        cancelRejBtn.addEventListener('click', hideRejModal);
        rejOverlay.addEventListener('click', hideRejModal);

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') { hideModal(); hideRejModal(); }
        });

        // ── Toast ──────────────────────────────────────────────────────
        function showToast(text, type = 'success') {
            const el = document.getElementById('notification');
            const box = document.getElementById('notification-content');
            const icons = {
                success: '<svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
                error: '<svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
            };
            const bg = type === 'error' ? 'bg-red-600' : 'bg-emerald-600';
            box.className = `flex items-center gap-3 px-4 py-3 rounded-xl shadow-xl text-sm font-medium text-white min-w-64 ${bg}`;
            box.innerHTML = `${icons[type] || ''}<span>${text}</span>`;
            el.classList.remove('hidden');
            setTimeout(() => el.classList.add('hidden'), 4500);
        }

        // ── User Verification Actions ──────────────────────────────────
        document.addEventListener('click', e => {
            const approveBtn = e.target.closest('.approve-btn');
            const rejectBtn = e.target.closest('.reject-btn');
            const resetBtn = e.target.closest('.reset-btn');

            if (approveBtn) {
                const id = approveBtn.dataset.detailId;
                showModal('Approve Verification', 'Approve this user\'s verification?', () => userVerification(id, 'approve', approveBtn), 'approve');
            }
            if (rejectBtn) {
                const id = rejectBtn.dataset.detailId;
                showRejModal(reason => userVerification(id, 'reject', rejectBtn, reason));
            }
            if (resetBtn) {
                const id = resetBtn.dataset.detailId;
                showModal('Reset Status', 'Reset verification status to pending?', () => userVerification(id, 'reset', resetBtn), 'warning');
            }
        });

        function userVerification(detailId, action, btn, reason = null) {
            const orig = btn.textContent;
            btn.disabled = true; btn.textContent = '…'; btn.classList.add('opacity-60');
            const payload = { detail_id: detailId, action };
            if (reason) payload.reason = reason;

            fetch('<?= BASE_URL ?>/verification', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            }).then(r => r.json()).then(data => {
                if (data.success) {
                    showToast(data.message || 'Done!');
                    const row = document.getElementById('row-' + detailId);
                    if (row && action !== 'reset') {
                        const statusCell = row.querySelector('td:nth-child(10)');
                        const actionCell = row.querySelector('td:nth-child(11)');
                        if (action === 'approve') {
                            statusCell.innerHTML = '<span class="inline-flex items-center gap-1 text-xs font-medium px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Verified</span>';
                            actionCell.innerHTML = '<span class="text-xs text-emerald-600 font-medium">✓ Complete</span>';
                        } else if (action === 'reject') {
                            statusCell.innerHTML = '<span class="inline-flex items-center gap-1 text-xs font-medium px-2.5 py-1 rounded-full bg-red-50 text-red-700 border border-red-200"><span class="w-1.5 h-1.5 rounded-full bg-red-500"></span> Rejected</span>';
                            actionCell.innerHTML = '<button class="reset-btn px-3 py-1.5 text-xs font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors" data-detail-id="' + detailId + '">Reset</button>';
                        }
                    }
                    if (action !== 'reset') setTimeout(() => location.reload(), 2000);
                } else {
                    btn.disabled = false; btn.textContent = orig; btn.classList.remove('opacity-60');
                    showToast(data.message || 'Operation failed', 'error');
                }
            }).catch(() => {
                btn.disabled = false; btn.textContent = orig; btn.classList.remove('opacity-60');
                showToast('Network error', 'error');
            });
        }

        // ── Noble Account Actions ──────────────────────────────────────
        document.addEventListener('click', e => {
            const verifyBtn = e.target.closest('.approve-noble-btn');
            const activateBtn = e.target.closest('.activate-btn');
            const deactivateBtn = e.target.closest('.deactivate-btn');

            if (verifyBtn) {
                const id = verifyBtn.dataset.accountId;
                showModal('Verify Noble Account', 'Verify this noble account?', () => nobleAction(id, 'verify', verifyBtn), 'approve');
            }
            if (activateBtn) {
                const id = activateBtn.dataset.accountId;
                showModal('Activate Account', 'Activate this noble account?', () => nobleAction(id, 'activate', activateBtn), 'approve');
            }
            if (deactivateBtn) {
                const id = deactivateBtn.dataset.accountId;
                showModal('Deactivate Account', 'Deactivate this noble account?', () => nobleAction(id, 'deactivate', deactivateBtn), 'danger');
            }
        });

        function nobleAction(accountId, action, btn) {
            const orig = btn.textContent;
            btn.disabled = true; btn.textContent = '…'; btn.classList.add('opacity-60');

            fetch('<?= BASE_URL ?>/managenobleaccount', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ account_id: accountId, action })
            }).then(r => r.json()).then(data => {
                if (data.success) {
                    showToast(data.message || 'Done!');
                    setTimeout(() => location.reload(), 2000);
                } else {
                    btn.disabled = false; btn.textContent = orig; btn.classList.remove('opacity-60');
                    showToast(data.message || 'Operation failed', 'error');
                }
            }).catch(() => {
                btn.disabled = false; btn.textContent = orig; btn.classList.remove('opacity-60');
                showToast('Network error', 'error');
            });
        }
    </script>
</body>

</html>