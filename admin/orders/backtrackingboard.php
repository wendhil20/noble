<?php
//backtrackingboard.php
session_name("nobleadmin");
session_start();
include '../../connection/connect.php';
include '../role/roleaccount.php';

require_role(['sales', 'superadmin']);

if (!isset($_SESSION['noble_name']) || !isset($_SESSION['noble_lvl'])) {
    $email = $_SESSION['noble_user'];
    $stmt = $conn->prepare("SELECT fullname, lvl FROM nobleaccount WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->bind_result($name, $lvl);
    if ($stmt->fetch()) {
        $_SESSION['noble_name'] = $name;
        $_SESSION['noble_lvl'] = $lvl;
    } else {
        $_SESSION['noble_name'] = "Unknown User";
        $_SESSION['noble_lvl'] = "guest";
    }
    $stmt->close();
}

if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

// AJAX endpoint — returns JSON when ?fetch=1
if (isset($_GET['fetch'])) {
    header('Content-Type: application/json');
    $inquiries = [];
    $result = $conn->query("SELECT * FROM backtrack ORDER BY created_at DESC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $inquiries[] = $row;
        }
    }
    echo json_encode($inquiries);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backtracking Board</title>
    <?php include '../navbar/top.php'; ?>
</head>

<body class="bg-gray-50 min-h-screen">

    <div class="max-w-6xl mx-auto px-4 py-8">

        <!-- Page Header -->
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-800">Backtracking Board</h1>
                <p class="text-sm text-gray-500 mt-1">Live inquiry records</p>
            </div>
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center gap-1.5 text-xs text-gray-500">
                    <span id="pulse" class="w-2 h-2 rounded-full bg-green-400 animate-pulse"></span>
                    <span id="last-updated">Connecting...</span>
                </span>
                <span class="text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded-lg" id="record-count">0 records</span>
                <a href="backtracking.php"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 hover:bg-gray-50 active:bg-gray-100 text-gray-700 text-sm font-medium rounded-lg transition shadow-sm">
                    + New Inquiry
                </a>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide border-b border-gray-100">
                        <tr>

                            <th class="px-4 py-3">Reference No.</th>
                            <th class="px-4 py-3">Inquiry #</th>
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3">Email</th>
                            <th class="px-4 py-3">Contact</th>
                            <th class="px-4 py-3">Company</th>
                            <th class="px-4 py-3">Company Address</th>
                            <th class="px-4 py-3">Inquiry Date</th>


                        </tr>
                    </thead>
                    <tbody id="inquiry-tbody" class="divide-y divide-gray-100">
                        <tr>
                            <td colspan="9" class="px-4 py-8 text-center text-gray-400 text-sm">Loading...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <script>
        function formatDate(dateStr) {
            if (!dateStr) return '—';
            const d = new Date(dateStr);
            return d.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' });
        }

        function formatTime(timeStr) {
            if (!timeStr) return '—';
            const [h, m] = timeStr.split(':');
            const date = new Date();
            date.setHours(h, m);
            return date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
        }

        function escapeHtml(str) {
            if (!str) return '—';
            return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        function fetchInquiries() {
            fetch('?fetch=1')
                .then(res => res.json())
                .then(data => {
                    const tbody = document.getElementById('inquiry-tbody');
                    const countEl = document.getElementById('record-count');
                    const updatedEl = document.getElementById('last-updated');
                    const pulse = document.getElementById('pulse');

                    countEl.textContent = data.length + ' record' + (data.length !== 1 ? 's' : '');

                    const now = new Date();
                    updatedEl.textContent = 'Updated ' + now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });

                    pulse.classList.remove('bg-red-400');
                    pulse.classList.add('bg-green-400');

                    if (data.length === 0) {
                        tbody.innerHTML = `<tr><td colspan="9" class="px-4 py-8 text-center text-gray-400 text-sm">No inquiries found.</td></tr>`;
                        return;
                    }

                    tbody.innerHTML = data.map((row, i) => `
                        <tr
                            class="hover:bg-blue-50 cursor-pointer transition"
                            onclick="window.location.href='backtracking_view.php?id=${encodeURIComponent(row.id)}'"
                            title="View profile"
                        >
                            <td class="px-4 py-3">
                                <span class="inline-block bg-blue-50 text-blue-700 text-xs font-mono font-medium px-2 py-1 rounded">
                                    ${escapeHtml(row.reference_no)}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center justify-center w-7 h-7 rounded-full text-xs font-bold
                                    ${row.inquiry_number === 1 ? 'bg-gray-100 text-gray-500' : 'bg-red-500 text-white'}">
                                    ${row.inquiry_number}
                                </span>
                            </td>
                            <td class="px-4 py-3 font-medium text-blue-600 hover:underline">${escapeHtml(row.name)}</td>
                            <td class="px-4 py-3 text-gray-600">${escapeHtml(row.email)}</td>
                            <td class="px-4 py-3 text-gray-600">${escapeHtml(row.contact)}</td>
                            <td class="px-4 py-3 text-gray-600">${escapeHtml(row.company_name)}</td>
                            <td class="px-4 py-3 text-gray-600">${escapeHtml(row.company_address)}</td>
                            <td class="px-4 py-3 text-gray-600 whitespace-nowrap">${formatDate(row.inquiry_date)}</td>
                        </tr>
                    `).join('');
                })
                .catch(() => {
                    document.getElementById('pulse').classList.replace('bg-green-400', 'bg-red-400');
                    document.getElementById('last-updated').textContent = 'Connection lost';
                });
        }

        fetchInquiries();
        setInterval(fetchInquiries, 5000);
    </script>

</body>

</html>