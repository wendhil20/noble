<?php
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";

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
    header("Location: " . BASE_URL . "/main");
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
    <?php include ROOT_PATH . '/admin/navbar/top.php'; ?>
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

        <!-- Search Bar -->
        <div class="mb-4">
            <div class="relative max-w-sm">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z" />
                    </svg>
                </div>
                <input type="text" id="search-input" placeholder="Search by name, email, company..."
                    class="w-full pl-9 pr-4 py-2 text-sm border border-gray-200 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition" />
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
        let allData = [];

        function formatDate(dateStr) {
            if (!dateStr) return '—';
            const d = new Date(dateStr);
            return d.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' });
        }

        function escapeHtml(str) {
            if (!str) return '—';
            return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        function renderTable(data) {
            const tbody = document.getElementById('inquiry-tbody');
            const countEl = document.getElementById('record-count');
            countEl.textContent = data.length + ' record' + (data.length !== 1 ? 's' : '');

            if (data.length === 0) {
                tbody.innerHTML = `<tr><td colspan="9" class="px-4 py-8 text-center text-gray-400 text-sm">No inquiries found.</td></tr>`;
                return;
            }

            tbody.innerHTML = data.map((row) => `
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
        }

        function applySearch() {
            const query = document.getElementById('search-input').value.trim().toLowerCase();
            if (!query) {
                renderTable(allData);
                return;
            }
            const filtered = allData.filter(row =>
                (row.name || '').toLowerCase().includes(query) ||
                (row.email || '').toLowerCase().includes(query) ||
                (row.contact || '').toLowerCase().includes(query) ||
                (row.company_name || '').toLowerCase().includes(query) ||
                (row.company_address || '').toLowerCase().includes(query) ||
                (row.reference_no || '').toLowerCase().includes(query)
            );
            renderTable(filtered);
        }

        function fetchInquiries() {
            fetch('?fetch=1')
                .then(res => res.json())
                .then(data => {
                    const updatedEl = document.getElementById('last-updated');
                    const pulse = document.getElementById('pulse');

                    allData = data;

                    const now = new Date();
                    updatedEl.textContent = 'Updated ' + now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });

                    pulse.classList.remove('bg-red-400');
                    pulse.classList.add('bg-green-400');

                    applySearch();
                })
                .catch(() => {
                    document.getElementById('pulse').classList.replace('bg-green-400', 'bg-red-400');
                    document.getElementById('last-updated').textContent = 'Connection lost';
                });
        }

        document.getElementById('search-input').addEventListener('input', applySearch);

        fetchInquiries();
        setInterval(fetchInquiries, 5000);
    </script>
</body>

</html>