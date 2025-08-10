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
    <title>User Verification Approvals</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>

<body class="bg-gray-50">

    <?php include '../navbar/top.php'; ?>

    <div class="max-w-7xl mx-auto">
        <h1 class="text-2xl font-bold mb-4">User Details Verification Approvals</h1>

        <div id="notification" class="fixed top-6 right-6 z-50 hidden">
            <div id="notification-content" class="bg-green-500 text-white px-4 py-2 rounded shadow">Saved</div>
        </div>

        <div class="bg-white shadow rounded overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-100">
                    <tr>

                        <th class="px-4 py-2 text-left">Name</th>
                        <th class="px-4 py-2 text-left">Email</th>
                        <th class="px-4 py-2 text-left">Mobile</th>
                        <th class="px-4 py-2 text-left">Sex</th>
                        <th class="px-4 py-2 text-left">Birthplace</th>
                        <th class="px-4 py-2 text-left">Birthdate</th>
                        <th class="px-4 py-2 text-left">Occupation</th>
                        <th class="px-4 py-2 text-left">Verifying</th>
                        <th class="px-4 py-2 text-left">actions</th>
                    </tr>
                </thead>
                <tbody>
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
                            <tr id="row-<?= $detail_id ?>" class="border-b">

                                <td class="px-4 py-2"><?= htmlspecialchars($row['name']) ?></td>
                                <td class="px-4 py-2"><?= htmlspecialchars($row['email']) ?></td>
                                <td class="px-4 py-2"><?= htmlspecialchars($row['mobile']) ?></td>
                                <td class="px-4 py-2"><?= htmlspecialchars($row['sex']) ?></td>
                                <td class="px-4 py-2"><?= htmlspecialchars($row['birthplace']) ?></td>
                                <td class="px-4 py-2"><?= htmlspecialchars($row['birthdate']) ?></td>
                                <td class="px-4 py-2"><?= htmlspecialchars($row['occupation']) ?></td>
                                <td class="px-4 py-2">
                                    <?php if ($is_verified): ?>
                                        <span class="inline-block px-2 py-1 bg-green-100 text-green-700 rounded text-xs">Verified</span>
                                    <?php else: ?>
                                        <span class="inline-block px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-xs">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-2">
                                    <?php if (!$is_verified): ?>
                                        <button class="approve-btn bg-blue-600 text-white px-3 py-1 rounded text-sm"
                                            data-detail-id="<?= $detail_id ?>">Approve</button>
                                    <?php else: ?>
                                        <button class="bg-gray-300 text-gray-700 px-3 py-1 rounded text-sm" disabled>Approved</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                    <?php
                        endwhile;
                    } else {
                        echo '<tr><td colspan="11" class="px-4 py-2 text-red-600">No records found</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.approve-btn');
            if (!btn) return;

            const detailId = btn.dataset.detailId;
            if (!detailId) return;

            if (!confirm('Approve verification for detail_id ' + detailId + '?')) return;

            btn.disabled = true;
            btn.textContent = 'Approving...';

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
                            row.querySelector('td:nth-child(10)').innerHTML = '<span class="inline-block px-2 py-1 bg-green-100 text-green-700 rounded text-xs">Verified</span>';
                            row.querySelector('td:nth-child(11)').innerHTML = '<button class="bg-gray-300 text-gray-700 px-3 py-1 rounded text-sm" disabled>Approved</button>';
                        }
                        showNotification(data.message || 'Verified');
                    } else {
                        btn.disabled = false;
                        btn.textContent = 'Approve';
                        showNotification(data.message || 'Error', true);
                    }
                })
                .catch(err => {
                    console.error(err);
                    btn.disabled = false;
                    btn.textContent = 'Approve';
                    showNotification('Network error', true);
                });
        });

        function showNotification(text, isError = false) {
            const container = document.getElementById('notification');
            const content = document.getElementById('notification-content');
            content.textContent = text;
            if (isError) {
                content.classList.remove('bg-green-500');
                content.classList.add('bg-red-500');
            } else {
                content.classList.remove('bg-red-500');
                content.classList.add('bg-green-500');
            }
            container.classList.remove('hidden');
            container.style.opacity = '1';
            setTimeout(() => {
                container.style.transition = 'opacity 0.4s';
                container.style.opacity = '0';
                setTimeout(() => container.classList.add('hidden'), 400);
            }, 3000);
        }
    </script>
</body>

</html>