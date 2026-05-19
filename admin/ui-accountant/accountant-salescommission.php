<?php
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";
require_role(['accountant']);

// Fetch ONLY sales members
$q = "SELECT id, fullname, email, lvl, IFNULL(commission_rate, 0.00) AS commission_rate
      FROM nobleaccount
      WHERE lvl = 'sales'
      ORDER BY fullname";
$res = mysqli_query($conn, $q);
$sales_members = [];
while ($r = mysqli_fetch_assoc($res)) {
  $sales_members[] = $r;
}
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Sales Commission Management</title>
</head>

<body class="min-h-screen text-gray-800">
  <?php include ROOT_PATH . '/admin/navbar/top.php'; ?>
  <div class="max-w-5xl mx-auto p-6">
    <!-- Header -->
    <header class="mb-6">
      <h1 class="text-3xl font-extrabold text-green-600">Sales Commission Management</h1>
      <p class="mt-2 text-sm text-gray-600">Manage commission rates for all sales members</p>
    </header>

    <!-- Search -->
    <div class="mb-6">
      <div class="relative">
        <input id="searchInput" type="search" placeholder="Search sales member..."
          class="w-full pl-10 pr-4 py-2 rounded-lg border border-green-200 focus:outline-none focus:ring-2 focus:ring-green-300" />
        <div class="absolute left-3 top-1/2 -translate-y-1/2 text-green-500 pointer-events-none">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 111 0z" />
          </svg>
        </div>
      </div>
    </div>

    <!-- Sales Members Table -->
    <div class="bg-white rounded-2xl shadow overflow-hidden">
      <table class="w-full">
        <thead class="bg-green-600 text-white">
          <tr>
            <th class="px-6 py-3 text-left font-semibold">#</th>
            <th class="px-6 py-3 text-left font-semibold">Name</th>
            <th class="px-6 py-3 text-left font-semibold">Email</th>
            <th class="px-6 py-3 text-center font-semibold">Commission Rate (%)</th>
            <th class="px-6 py-3 text-center font-semibold">Action</th>
          </tr>
        </thead>
        <tbody id="membersTableBody">
          <?php if (count($sales_members) === 0): ?>
            <tr>
              <td colspan="5" class="px-6 py-8 text-center text-gray-400">No sales members found</td>
            </tr>
          <?php else: 
            $count = 1;
            foreach ($sales_members as $member): 
              $id = (int)$member['id'];
              $commission = number_format((float)$member['commission_rate'], 2);
          ?>
            <tr class="member-row border-b hover:bg-green-50 transition" data-id="<?= $id ?>" data-name="<?= htmlspecialchars($member['fullname']) ?>">
              <td class="px-6 py-4 text-gray-700"><?= $count++ ?></td>
              <td class="px-6 py-4 font-medium text-gray-900"><?= htmlspecialchars($member['fullname']) ?></td>
              <td class="px-6 py-4 text-sm text-gray-600"><?= htmlspecialchars($member['email']) ?></td>
              <td class="px-6 py-4 text-center">
                <span class="commission-display inline-block bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-semibold">
                  <?= $commission ?>%
                </span>
              </td>
              <td class="px-6 py-4 text-center">
                <button type="button" class="edit-btn inline-flex items-center gap-2 px-4 py-2 rounded-md bg-green-600 text-white text-sm hover:bg-green-700 transition" data-id="<?= $id ?>">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                  </svg>
                  Edit
                </button>
              </td>
            </tr>
          <?php endforeach; 
          endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Edit Commission Modal -->
  <div id="editModal" class="fixed inset-0 z-50 hidden bg-black/40 items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-md mx-auto p-6">
      <h2 class="text-xl font-semibold text-gray-900 mb-2">Edit Commission Rate</h2>
      <p id="memberName" class="text-sm text-gray-600 mb-4">Member: <strong></strong></p>

      <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700 mb-2">Commission Rate (%)</label>
        <div class="relative">
          <input type="number" id="commissionInput" step="0.01" min="0" max="100" placeholder="0.00"
            class="w-full px-4 py-2 pr-8 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500" />
          <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 font-semibold">%</span>
        </div>
        <p class="mt-2 text-xs text-gray-500">Enter value between 0.00 and 100.00</p>
      </div>

      <div class="flex justify-end gap-3">
        <button id="cancelBtn" type="button" class="px-4 py-2 rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 transition">
          Cancel
        </button>
        <button id="saveBtn" type="button" class="px-4 py-2 rounded-md bg-green-600 text-white hover:bg-green-700 transition">
          Save Commission
        </button>
      </div>
    </div>
  </div>

  <!-- Toast Notification -->
  <div id="toast" class="fixed top-6 right-6 z-50 hidden">
    <div id="toastContent" class="px-4 py-3 rounded-lg shadow text-white font-medium"></div>
  </div>

  <script>
    const BASE_URL = "<?= BASE_URL ?>";

    document.addEventListener('DOMContentLoaded', function() {
      const editModal = document.getElementById('editModal');
      const memberNameEl = document.getElementById('memberName');
      const commissionInput = document.getElementById('commissionInput');
      const cancelBtn = document.getElementById('cancelBtn');
      const saveBtn = document.getElementById('saveBtn');
      const searchInput = document.getElementById('searchInput');
      const toast = document.getElementById('toast');
      const toastContent = document.getElementById('toastContent');

      let currentMemberId = null;

      // Show toast
      function showToast(msg, type = 'success') {
        toastContent.textContent = msg;
        toastContent.className = 'px-4 py-3 rounded-lg shadow text-white font-medium ' + 
          (type === 'error' ? 'bg-red-600' : 'bg-green-600');
        toast.classList.remove('hidden');
        setTimeout(() => toast.classList.add('hidden'), 3000);
      }

      // Edit button click
      document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.addEventListener('click', function() {
          const row = this.closest('.member-row');
          const id = row.dataset.id;
          const name = row.dataset.name;
          const commission = row.querySelector('.commission-display').textContent.trim();

          currentMemberId = id;
          memberNameEl.querySelector('strong').textContent = name;
          commissionInput.value = parseFloat(commission);

          editModal.classList.remove('hidden');
          commissionInput.focus();
        });
      });

      // Cancel button
      cancelBtn.addEventListener('click', function() {
        editModal.classList.add('hidden');
        currentMemberId = null;
      });

      // Save button
      saveBtn.addEventListener('click', async function() {
        if (!currentMemberId) return;

        const newCommission = parseFloat(commissionInput.value);

        // Validation
        if (isNaN(newCommission) || newCommission < 0 || newCommission > 100) {
          showToast('Commission must be between 0 and 100', 'error');
          return;
        }

        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving...';

        try {
          const response = await fetch(`${BASE_URL}/updatesalescommision`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `account_id=${encodeURIComponent(currentMemberId)}&commission=${encodeURIComponent(newCommission.toFixed(2))}`
          });

          const result = await response.json();

          if (result.success) {
            showToast(result.message || 'Commission updated successfully');
            
            // Update the table
            const row = document.querySelector(`.member-row[data-id="${currentMemberId}"]`);
            if (row) {
              row.querySelector('.commission-display').textContent = newCommission.toFixed(2) + '%';
            }

            editModal.classList.add('hidden');
            currentMemberId = null;
          } else {
            showToast(result.message || 'Failed to update commission', 'error');
          }
        } catch (err) {
          console.error(err);
          showToast('Network error', 'error');
        } finally {
          saveBtn.disabled = false;
          saveBtn.textContent = 'Save Commission';
        }
      });

      // Search functionality
      searchInput.addEventListener('input', function() {
        const query = this.value.toLowerCase();
        document.querySelectorAll('.member-row').forEach(row => {
          const name = row.dataset.name.toLowerCase();
          const email = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
          row.style.display = (name.includes(query) || email.includes(query)) ? '' : 'none';
        });
      });

      // Close modal on Escape key
      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !editModal.classList.contains('hidden')) {
          editModal.classList.add('hidden');
          currentMemberId = null;
        }
      });
    });
  </script>
</body>

</html>