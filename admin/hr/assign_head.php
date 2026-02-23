<?php
session_name("nobleadmin");
session_start();
require_once '../../connection/connect.php';
require_once '../role/roleaccount.php';

require_role(['superadmin', 'hr']); // only superadmin can manage heads

// departments to manage (supplier removed)
$departments = ['superadmin', 'sales', 'accountant', 'hr', 'warehouse', 'logistic'];

// Define subroles for each department (you can edit these)
$department_subroles = [
  'sales' => [],
  'accountant' => [
    'document_controller'
  ],
  'hr' => [],
  'warehouse' => [
    'warehouse_receiver',
    'warehouse_staff'
  ],
  'logistic' => [
    'dispatcher'
  ]
];

// Fetch all accounts
$q = "SELECT id, fullname, email, lvl, IFNULL(is_head,0) AS is_head, subrole, IFNULL(commission_rate, 0.00) AS commission_rate, 
      CASE WHEN e_signature IS NOT NULL THEN 1 ELSE 0 END as has_signature
      FROM nobleaccount
      ORDER BY lvl, fullname";
$res = mysqli_query($conn, $q);

// Group members by department and collect heads
$groups = [];
$heads = [];
while ($r = mysqli_fetch_assoc($res)) {
  $lvl = strtolower($r['lvl']);
  if (!isset($groups[$lvl])) $groups[$lvl] = [];
  $groups[$lvl][] = $r;
  if ((int)$r['is_head'] === 1) {
    $heads[] = $r;
  }
}
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Assign Department Head — List</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class=" min-h-screen text-gray-800">
  <?php include '../navbar/top.php'; ?>
  <div class="max-w-7xl mx-auto p-6">
    <!-- Header -->
    <header class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
      <div>
        <h1 class="text-2xl md:text-3xl font-extrabold text-orange-600">Assign Department Head</h1>
        <p class="mt-1 text-sm text-gray-600">Click a department to filter. Superadmin is excluded from assignable heads.</p>
      </div>

      <div class="w-full md:w-96 relative">
        <input id="searchInput" type="search" placeholder="Search members or heads..."
          class="w-full pl-10 pr-4 py-2 rounded-lg border border-orange-200 focus:outline-none focus:ring-2 focus:ring-orange-300" />
        <div class="absolute left-3 top-1/2 -translate-y-1/2 text-orange-500 pointer-events-none">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M10.5 18a7.5 7.5 0 111 0z" />
          </svg>
        </div>
      </div>
    </header>

    <!-- Filter Buttons -->
    <div class="bg-white rounded-xl shadow p-4 mb-6 flex flex-wrap gap-3 items-center">
      <button type="button" class="dept-btn px-4 py-2 rounded-lg bg-orange-600 text-white font-medium" data-filter="">All</button>
      <?php foreach ($departments as $d):
        $c = isset($groups[$d]) ? count($groups[$d]) : 0;
      ?>
        <button type="button" class="dept-btn px-4 py-2 rounded-lg bg-blue border border-orange-100 text-orange-700 hover:bg-orange-50"
          data-filter="<?= htmlspecialchars($d) ?>">
          <?= ucwords($d) ?> <span class="ml-2 inline-block bg-orange-100 text-orange-800 px-2 py-0.5 rounded text-xs"><?= $c ?></span>
        </button>
      <?php endforeach; ?>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- left/middle: departments lists (span 2 columns) -->
      <div class="lg:col-span-2 space-y-4" id="departmentsContainer">
        <?php foreach ($departments as $dept):
          $members = $groups[$dept] ?? [];
        ?>
          <section class="bg-white rounded-2xl shadow p-4 dept-section" data-dept="<?= htmlspecialchars($dept) ?>">
            <div class="flex items-center justify-between mb-3">
              <div>
                <h2 class="text-lg font-semibold text-orange-700 dept-title cursor-pointer"><?= ucwords($dept) ?></h2>
                <p class="text-sm text-gray-500"><?= count($members) ?> member(s)</p>
              </div>
              <div class="text-sm text-gray-500">Dept</div>
            </div>

            <ul class="space-y-2 member-list">
              <?php if (count($members) === 0): ?>
                <li class="text-center text-sm text-gray-400 py-4">No members.</li>
                <?php else: foreach ($members as $m):
                  $id = (int)$m['id'];
                  $is_head = (int)$m['is_head'];
                ?>
                  <li data-id="<?= $id ?>" data-dept="<?= htmlspecialchars($dept) ?>" data-is-head="<?= $is_head ?>"
                    class="flex items-center justify-between bg-orange-50 rounded-lg p-3">
                    <div class="flex items-center gap-3 flex-1">
                      <?php if ((int)$m['has_signature'] === 1): ?>
                        <img src="view_signature.php?id=<?= $id ?>" alt="Signature" class="w-16 h-16 object-contain border border-gray-300 rounded bg-white" />
                      <?php else: ?>
                        <div class="w-16 h-16 flex items-center justify-center border-2 border-dashed border-gray-300 rounded bg-white">
                          <span class="text-xs text-gray-400">No sig</span>
                        </div>
                      <?php endif; ?>

                      <div class="flex-1">
                        <div class="font-medium text-gray-900"><?= htmlspecialchars($m['fullname']) ?></div>
                        <div class="text-xs text-gray-500"><?= htmlspecialchars($m['email']) ?></div>
                        <?php if (!empty($m['subrole'])): ?>
                          <div class="text-xs text-blue-600 mt-1">Role: <?= htmlspecialchars($m['subrole']) ?></div>
                        <?php endif; ?>
                        <?php if ($dept === 'sales' && isset($m['commission_rate'])): ?>
                          <div class="text-xs text-green-600 mt-1 font-semibold">Commission: <?= number_format($m['commission_rate'], 2) ?>%</div>
                        <?php endif; ?>
                      </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                      <?php if ($is_head === 1): ?>
                        <span class="head-badge inline-flex items-center gap-2 bg-orange-200 text-orange-800 px-3 py-1 rounded-full text-xs font-semibold">Head</span>
                        <button type="button" class="remove-head inline-flex items-center gap-2 px-3 py-1 rounded-md bg-red-500 text-white text-sm" data-id="<?= $id ?>">Remove</button>
                      <?php else: ?>
                        <button type="button" class="set-head inline-flex items-center gap-2 px-3 py-1 rounded-md bg-orange-600 text-white text-sm" data-id="<?= $id ?>">Set as Head</button>
                      <?php endif; ?>
                      <button type="button" class="upload-signature inline-flex items-center gap-2 px-3 py-1 rounded-md bg-purple-600 text-white text-sm" data-id="<?= $id ?>">
                        <i class="fas fa-upload"></i>Signature
                      </button>
                     <?php if ($dept === 'sales' || $dept === 'accountant' || $dept === 'hr' || $dept === 'superadmin' || $dept === 'warehouse' || $dept === 'logistic'): ?>
                        <button type="button" class="edit-subrole inline-flex items-center gap-2 px-3 py-1 rounded-md bg-blue-600 text-white text-sm" data-id="<?= $id ?>" data-subrole="<?= htmlspecialchars($m['subrole'] ?? '') ?>">Edit Role</button>
                      <?php else: ?>
                        <button type="button" class="edit-commission inline-flex items-center gap-2 px-3 py-1 rounded-md bg-green-600 text-white text-sm" data-id="<?= $id ?>" data-commission="<?= htmlspecialchars($m['commission_rate'] ?? '0.00') ?>">Edit Commission</button>
                      <?php endif; ?>
                    </div>
                  </li>
              <?php endforeach;
              endif; ?>
            </ul>
          </section>
        <?php endforeach; ?>
      </div>

      <!-- right: all heads list -->
      <aside class="space-y-4">
        <div class="bg-white rounded-2xl shadow p-4">
          <h3 class="text-lg font-semibold text-orange-700 mb-2">All Department Heads</h3>
          <p class="text-sm text-gray-500 mb-3">Quick overview. Click View to jump to member.</p>

          <ul id="headsList" class="space-y-2">
            <?php
            // show heads for departments (include superadmin now, only skip supplier)
            $visible_heads = array_filter($heads, function ($h) {
              $lvl = strtolower($h['lvl']);
              return !in_array($lvl, ['supplier']);
            });
            if (count($visible_heads) === 0): ?>
              <li class="text-sm text-gray-400">No heads assigned yet.</li>
              <?php else:
              foreach ($visible_heads as $h): ?>
                <li data-id="<?= (int)$h['id'] ?>" data-dept="<?= htmlspecialchars(strtolower($h['lvl'])) ?>"
                  class="flex items-center justify-between bg-orange-50 rounded-lg p-3">
                  <div>
                    <div class="font-medium text-gray-900"><?= htmlspecialchars($h['fullname']) ?></div>
                    <div class="text-xs text-gray-500"><?= ucwords(htmlspecialchars($h['lvl'])) ?></div>
                  </div>
                  <div class="flex items-center gap-2">
                    <button type="button" class="goto-member text-sm px-3 py-1 rounded-md bg-white border border-orange-100 text-orange-700" data-id="<?= (int)$h['id'] ?>">View</button>
                    <button type="button" class="remove-head inline-flex items-center gap-2 px-3 py-1 rounded-md bg-red-500 text-white text-sm" data-id="<?= (int)$h['id'] ?>">Remove</button>
                  </div>
                </li>
            <?php endforeach;
            endif; ?>
          </ul>
        </div>

        <div class="bg-white rounded-2xl shadow p-4 text-sm text-gray-600">
          <strong>Note:</strong>
          <p class="mt-2">Assigning a head will remove the previous head for that department. Superadmin cannot be assigned as a department head.</p>
        </div>
      </aside>
    </div>
  </div>

  <!-- Confirm modal (centered & responsive) -->
  <div id="confirmModal" class="fixed inset-0 z-50 hidden bg-black/40 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-md mx-auto p-5">
      <h4 id="confirmTitle" class="text-lg font-semibold text-gray-900">Confirm</h4>
      <p id="confirmMessage" class="mt-2 text-sm text-gray-600">Are you sure?</p>
      <div class="mt-5 flex justify-end gap-3">
        <button id="confirmCancel" type="button" class="px-4 py-2 rounded-md border bg-white">Cancel</button>
        <button id="confirmOk" type="button" class="px-4 py-2 rounded-md bg-orange-600 text-white">Yes, continue</button>
      </div>
    </div>
  </div>

  <!-- Subrole modal -->
  <div id="subroleModal" class="fixed inset-0 z-50 hidden bg-black/40 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-md mx-auto p-5">
      <h4 class="text-lg font-semibold text-gray-900">Edit Subrole</h4>
      <p id="subroleDeptName" class="mt-2 text-sm text-gray-600">Select a role for this member</p>

      <div class="mt-3">
        <label class="block text-sm font-medium text-gray-700 mb-2">Select Role</label>
        <select id="subroleSelect" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
          <option value="">-- No Role --</option>
        </select>
      </div>

      <div class="mt-3">
        <label class="flex items-center gap-2">
          <input type="checkbox" id="subroleCustomToggle" class="rounded border-gray-300">
          <span class="text-sm text-gray-700">Use custom role instead</span>
        </label>
      </div>

      <div id="subroleCustomContainer" class="mt-3 hidden">
        <label class="block text-sm font-medium text-gray-700 mb-2">Custom Role</label>
        <input type="text" id="subroleCustomInput" placeholder="Enter custom role..." class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" />
      </div>

      <div class="mt-5 flex justify-end gap-3">
        <button id="subroleCancel" type="button" class="px-4 py-2 rounded-md border bg-white">Cancel</button>
        <button id="subroleSave" type="button" class="px-4 py-2 rounded-md bg-blue-600 text-white">Save</button>
      </div>
    </div>
  </div>

  <!-- Commission modal (for sales only) -->
  <div id="commissionModal" class="fixed inset-0 z-50 hidden bg-black/40 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-md mx-auto p-5">
      <h4 class="text-lg font-semibold text-gray-900">Edit Commission Rate</h4>
      <p class="mt-2 text-sm text-gray-600">Set commission percentage for this sales member</p>

      <div class="mt-4">
        <label class="block text-sm font-medium text-gray-700 mb-2">Commission Rate (%)</label>
        <div class="relative">
          <input type="number" id="commissionInput" step="0.01" min="0" max="100" placeholder="0.00"
            class="w-full px-3 py-2 pr-8 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500" />
          <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500">%</span>
        </div>
        <p class="mt-1 text-xs text-gray-500">Enter value between 0.00 and 100.00</p>
      </div>

      <div class="mt-5 flex justify-end gap-3">
        <button id="commissionCancel" type="button" class="px-4 py-2 rounded-md border bg-white">Cancel</button>
        <button id="commissionSave" type="button" class="px-4 py-2 rounded-md bg-green-600 text-white">Save</button>
      </div>
    </div>
  </div>

  <!-- Signature Upload Modal -->
  <div id="signatureModal" class="fixed inset-0 z-50 hidden bg-black/40 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-md mx-auto p-5">
      <h4 class="text-lg font-semibold text-gray-900">Upload E-Signature</h4>
      <p class="mt-2 text-sm text-gray-600">Upload an image of the signature (PNG, JPG, or GIF)</p>

      <div class="mt-4">
        <label class="block text-sm font-medium text-gray-700 mb-2">Signature Image</label>
        <input type="file" id="signatureFile" accept="image/png,image/jpeg,image/jpg,image/gif"
          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500" />
        <p class="mt-1 text-xs text-gray-500">Maximum file size: 2MB</p>
      </div>

      <div id="signaturePreview" class="mt-4 hidden">
        <label class="block text-sm font-medium text-gray-700 mb-2">Preview</label>
        <div class="border border-gray-300 rounded-md p-4 bg-gray-50">
          <img id="signaturePreviewImg" src="" alt="Preview" class="max-h-32 mx-auto" />
        </div>
      </div>

      <div class="mt-5 flex justify-end gap-3">
        <button id="signatureCancel" type="button" class="px-4 py-2 rounded-md border bg-white">Cancel</button>
        <button id="signatureSave" type="button" class="px-4 py-2 rounded-md bg-purple-600 text-white" disabled>Upload</button>
      </div>
    </div>
  </div>

  <!-- toast -->
  <div id="toast" class="fixed top-6 right-6 z-50 hidden">
    <div id="toastContent" class="px-4 py-2 rounded-lg shadow bg-green-600 text-white"></div>
  </div>

  <script>
    // Department subroles from PHP
    const departmentSubroles = <?= json_encode($department_subroles) ?>;
    document.addEventListener('DOMContentLoaded', function() {
      const $ = s => document.querySelector(s);
      const $$ = s => Array.from(document.querySelectorAll(s));

      const deptButtons = $$('.dept-btn');
      const deptSections = $$('.dept-section');
      const search = $('#searchInput');
      const headsListEl = $('#headsList');
      const confirmModal = $('#confirmModal');
      const confirmTitle = $('#confirmTitle');
      const confirmMessage = $('#confirmMessage');
      const confirmOk = $('#confirmOk');
      const confirmCancel = $('#confirmCancel');
      const subroleModal = $('#subroleModal');
      const subroleSelect = $('#subroleSelect');
      const subroleCustomToggle = $('#subroleCustomToggle');
      const subroleCustomContainer = $('#subroleCustomContainer');
      const subroleCustomInput = $('#subroleCustomInput');
      const subroleDeptName = $('#subroleDeptName');
      const subroleSave = $('#subroleSave');
      const subroleCancel = $('#subroleCancel');
      const commissionModal = $('#commissionModal');
      const commissionInput = $('#commissionInput');
      const commissionSave = $('#commissionSave');
      const commissionCancel = $('#commissionCancel');
      const signatureModal = $('#signatureModal');
      const signatureFile = $('#signatureFile');
      const signaturePreview = $('#signaturePreview');
      const signaturePreviewImg = $('#signaturePreviewImg');
      const signatureSave = $('#signatureSave');
      const signatureCancel = $('#signatureCancel');
      let currentSignatureId = null;
      const toast = $('#toast');
      const toastContent = $('#toastContent');

      // Helper: show toast
      function showToast(msg, type = 'success') {
        toastContent.textContent = msg;
        toastContent.className = 'px-4 py-2 rounded-lg shadow ' + (type === 'error' ? 'bg-red-600 text-white' : 'bg-green-600 text-white');
        toast.classList.remove('hidden');
        clearTimeout(window._t);
        window._t = setTimeout(() => toast.classList.add('hidden'), 3000);
      }

      // Show/hide sections by dept; persist in URL (dept query param)
      function applyFilter(dept) {
        deptSections.forEach(sec => {
          const d = (sec.dataset.dept || '').toLowerCase();
          sec.style.display = (!dept || d === dept) ? '' : 'none';
        });
        // update active button visuals
        deptButtons.forEach(btn => {
          const f = (btn.dataset.filter || '').toLowerCase();
          if ((dept || '') === f) {
            btn.classList.remove('bg-white', 'text-orange-700');
            btn.classList.add('bg-orange-600', 'text-white');
          } else {
            btn.classList.remove('bg-orange-600', 'text-white');
            btn.classList.add('bg-white', 'text-orange-700');
          }
        });
        // update URL (replace)
        const params = new URLSearchParams(window.location.search);
        if (!dept) params.delete('dept');
        else params.set('dept', dept);
        history.replaceState(null, '', window.location.pathname + (params.toString() ? '?' + params.toString() : ''));
        refreshHeadsList();
      }

      // Build heads list from current DOM department lists (excluding superadmin and supplier)
      function refreshHeadsList() {
        const memberRows = Array.from(document.querySelectorAll('li[data-id]'));
        const headRows = memberRows.filter(r => r.dataset.isHead === '1' && (r.dataset.dept || '').toLowerCase() !== 'supplier');
        if (headRows.length === 0) {
          headsListEl.innerHTML = '<li class="text-sm text-gray-400">No heads assigned yet.</li>';
          return;
        }
        headsListEl.innerHTML = '';
        headRows.forEach(r => {
          const id = r.dataset.id;
          const name = r.querySelector('.font-medium') ? r.querySelector('.font-medium').textContent.trim() : '';
          const dept = (r.dataset.dept || '').toLowerCase();
          const item = document.createElement('li');
          item.dataset.id = id;
          item.dataset.dept = dept;
          item.className = 'flex items-center justify-between bg-orange-50 rounded-lg p-3';
          item.innerHTML = `
        <div>
          <div class="font-medium text-gray-900">${name}</div>
          <div class="text-xs text-gray-500">${dept.replace(/\b\w/g,c=>c.toUpperCase())}</div>
        </div>
        <div class="flex items-center gap-2">
          <button type="button" class="goto-member text-sm px-3 py-1 rounded-md bg-white border border-orange-100 text-orange-700" data-id="${id}">View</button>
          <button type="button" class="remove-head inline-flex items-center gap-2 px-3 py-1 rounded-md bg-red-500 text-white text-sm" data-id="${id}">Remove</button>
        </div>
      `;
          headsListEl.appendChild(item);
        });
      }

      // Send action to backend
      async function sendAction(accountId, action, extraData = {}) {
        let body = `account_id=${encodeURIComponent(accountId)}&action=${encodeURIComponent(action)}`;
        for (const key in extraData) {
          body += `&${encodeURIComponent(key)}=${encodeURIComponent(extraData[key])}`;
        }
        const resp = await fetch('manage_head_account.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
          },
          body
        });
        return resp.json();
      }

      // Confirm modal promise
      function confirmDialog(title, message) {
        confirmTitle.textContent = title;
        confirmMessage.textContent = message;
        confirmModal.classList.remove('hidden');
        return new Promise(resolve => {
          function cleanup() {
            confirmModal.classList.add('hidden');
            confirmOk.removeEventListener('click', ok);
            confirmCancel.removeEventListener('click', cancel);
          }

          function ok() {
            cleanup();
            resolve(true);
          }

          function cancel() {
            cleanup();
            resolve(false);
          }
          confirmOk.addEventListener('click', ok);
          confirmCancel.addEventListener('click', cancel);
        });
      }

      // Signature file change handler
      signatureFile.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
          // Check file size (2MB max)
          if (file.size > 2 * 1024 * 1024) {
            showToast('File size must be less than 2MB', 'error');
            this.value = '';
            signatureSave.disabled = true;
            signaturePreview.classList.add('hidden');
            return;
          }

          // Check file type
          if (!file.type.match('image.*')) {
            showToast('Please select an image file', 'error');
            this.value = '';
            signatureSave.disabled = true;
            signaturePreview.classList.add('hidden');
            return;
          }

          // Show preview
          const reader = new FileReader();
          reader.onload = function(e) {
            signaturePreviewImg.src = e.target.result;
            signaturePreview.classList.remove('hidden');
            signatureSave.disabled = false;
          };
          reader.readAsDataURL(file);
        } else {
          signaturePreview.classList.add('hidden');
          signatureSave.disabled = true;
        }
      });

      // Click handlers (delegate)
      document.addEventListener('click', async (e) => {
        const setBtn = e.target.closest('.set-head');
        const removeBtn = e.target.closest('.remove-head');
        const gotoBtn = e.target.closest('.goto-member');
        const deptBtn = e.target.closest('.dept-btn');

        if (deptBtn) {
          const filter = (deptBtn.dataset.filter || '').toLowerCase();
          applyFilter(filter);
          return;
        }

        // Handle upload signature button
        const uploadSignatureBtn = e.target.closest('.upload-signature');
        if (uploadSignatureBtn) {
          const id = uploadSignatureBtn.dataset.id;
          currentSignatureId = id;

          signatureFile.value = '';
          signaturePreview.classList.add('hidden');
          signatureSave.disabled = true;
          signatureModal.classList.remove('hidden');

          const saveHandler = async () => {
            const file = signatureFile.files[0];
            if (!file) {
              showToast('Please select a file', 'error');
              return;
            }

            signatureSave.disabled = true;
            signatureSave.textContent = 'Uploading...';

            const formData = new FormData();
            formData.append('account_id', currentSignatureId);
            formData.append('action', 'upload_signature');
            formData.append('signature', file);

            try {
              const resp = await fetch('manage_head_account.php', {
                method: 'POST',
                body: formData
              });
              const json = await resp.json();

              if (json && json.success) {
                showToast(json.message || 'Signature uploaded successfully');

                // Update the signature display
                const row = uploadSignatureBtn.closest('li[data-id]');
                if (row) {
                  const signatureContainer = row.querySelector('.w-16.h-16');
                  if (signatureContainer) {
                    signatureContainer.outerHTML = `<img src="view_signature.php?id=${currentSignatureId}&t=${Date.now()}" alt="Signature" class="w-16 h-16 object-contain border border-gray-300 rounded bg-white" />`;
                  }
                }

                cleanup();
              } else {
                showToast((json && json.message) || 'Failed to upload signature', 'error');
              }
            } catch (err) {
              console.error(err);
              showToast('Network error', 'error');
            } finally {
              signatureSave.disabled = false;
              signatureSave.textContent = 'Upload';
            }
          };

          const cancelHandler = () => {
            cleanup();
          };

          const cleanup = () => {
            signatureModal.classList.add('hidden');
            signatureFile.value = '';
            signaturePreview.classList.add('hidden');
            currentSignatureId = null;
            signatureSave.removeEventListener('click', saveHandler);
            signatureCancel.removeEventListener('click', cancelHandler);
          };

          signatureSave.addEventListener('click', saveHandler);
          signatureCancel.addEventListener('click', cancelHandler);
          return;
        }

        // Handle edit commission button (sales only)
        const editCommissionBtn = e.target.closest('.edit-commission');
        if (editCommissionBtn) {
          const id = editCommissionBtn.dataset.id;
          const currentCommission = editCommissionBtn.dataset.commission || '0.00';

          commissionInput.value = currentCommission;
          commissionModal.classList.remove('hidden');
          commissionInput.focus();

          const saveHandler = async () => {
            const newCommission = parseFloat(commissionInput.value) || 0.00;

            if (newCommission < 0 || newCommission > 100) {
              showToast('Commission must be between 0 and 100', 'error');
              return;
            }

            commissionSave.disabled = true;
            commissionSave.textContent = 'Saving...';

            try {
              const json = await sendAction(id, 'update_commission', {
                commission: newCommission.toFixed(2)
              });
              if (json && json.success) {
                showToast(json.message || 'Commission updated');
                // Update the button and display
                editCommissionBtn.dataset.commission = newCommission.toFixed(2);
                const row = editCommissionBtn.closest('li[data-id]');
                if (row) {
                  const existingCommission = row.querySelector('.text-green-600');
                  if (existingCommission) {
                    existingCommission.textContent = 'Commission: ' + newCommission.toFixed(2) + '%';
                  } else {
                    const emailDiv = row.querySelector('.text-xs.text-gray-500');
                    const commissionDiv = document.createElement('div');
                    commissionDiv.className = 'text-xs text-green-600 mt-1 font-semibold';
                    commissionDiv.textContent = 'Commission: ' + newCommission.toFixed(2) + '%';
                    emailDiv.after(commissionDiv);
                  }
                }
                cleanup();
              } else {
                showToast((json && json.message) || 'Failed to update commission', 'error');
              }
            } catch (err) {
              console.error(err);
              showToast('Network error', 'error');
            } finally {
              commissionSave.disabled = false;
              commissionSave.textContent = 'Save';
            }
          };

          const cancelHandler = () => {
            cleanup();
          };

          const cleanup = () => {
            commissionModal.classList.add('hidden');
            commissionSave.removeEventListener('click', saveHandler);
            commissionCancel.removeEventListener('click', cancelHandler);
          };

          commissionSave.addEventListener('click', saveHandler);
          commissionCancel.addEventListener('click', cancelHandler);
          return;
        }

        // Handle edit subrole button
        const editSubroleBtn = e.target.closest('.edit-subrole');
        if (editSubroleBtn) {
          const id = editSubroleBtn.dataset.id;
          const currentSubrole = editSubroleBtn.dataset.subrole || '';
          const row = editSubroleBtn.closest('li[data-id]');
          const dept = row ? row.dataset.dept : '';

          // Populate dropdown with department-specific roles
          subroleSelect.innerHTML = '<option value="">-- No Role --</option>';
          if (dept && departmentSubroles[dept]) {
            departmentSubroles[dept].forEach(role => {
              const opt = document.createElement('option');
              opt.value = role;
              opt.textContent = role;
              if (role === currentSubrole) opt.selected = true;
              subroleSelect.appendChild(opt);
            });
          }

          // Check if current subrole is custom (not in dropdown)
          const isCustom = currentSubrole && dept && departmentSubroles[dept] &&
            !departmentSubroles[dept].includes(currentSubrole);

          if (isCustom) {
            subroleCustomToggle.checked = true;
            subroleCustomContainer.classList.remove('hidden');
            subroleCustomInput.value = currentSubrole;
            subroleSelect.disabled = true;
          } else {
            subroleCustomToggle.checked = false;
            subroleCustomContainer.classList.add('hidden');
            subroleCustomInput.value = '';
            subroleSelect.disabled = false;
          }

          subroleDeptName.textContent = `Select a role for this ${dept.replace(/\b\w/g, c => c.toUpperCase())} member`;
          subroleModal.classList.remove('hidden');

          // Toggle custom input
          const toggleHandler = () => {
            if (subroleCustomToggle.checked) {
              subroleCustomContainer.classList.remove('hidden');
              subroleSelect.disabled = true;
            } else {
              subroleCustomContainer.classList.add('hidden');
              subroleSelect.disabled = false;
              subroleCustomInput.value = '';
            }
          };

          subroleCustomToggle.addEventListener('change', toggleHandler);

          // Save handler
          const saveHandler = async () => {
            const newSubrole = subroleCustomToggle.checked ?
              subroleCustomInput.value.trim() :
              subroleSelect.value;

            subroleSave.disabled = true;
            subroleSave.textContent = 'Saving...';

            try {
              const json = await sendAction(id, 'update_subrole', {
                subrole: newSubrole
              });
              if (json && json.success) {
                showToast(json.message || 'Subrole updated');
                // Update the button and display
                editSubroleBtn.dataset.subrole = newSubrole;
                if (row) {
                  const existingRole = row.querySelector('.text-blue-600');
                  if (newSubrole) {
                    if (existingRole) {
                      existingRole.textContent = 'Role: ' + newSubrole;
                    } else {
                      const emailDiv = row.querySelector('.text-xs.text-gray-500');
                      const roleDiv = document.createElement('div');
                      roleDiv.className = 'text-xs text-blue-600 mt-1';
                      roleDiv.textContent = 'Role: ' + newSubrole;
                      emailDiv.after(roleDiv);
                    }
                  } else {
                    if (existingRole) existingRole.remove();
                  }
                }
                cleanup();
              } else {
                showToast((json && json.message) || 'Failed to update subrole', 'error');
              }
            } catch (err) {
              console.error(err);
              showToast('Network error', 'error');
            } finally {
              subroleSave.disabled = false;
              subroleSave.textContent = 'Save';
            }
          };

          const cancelHandler = () => {
            cleanup();
          };

          const cleanup = () => {
            subroleModal.classList.add('hidden');
            subroleCustomToggle.removeEventListener('change', toggleHandler);
            subroleSave.removeEventListener('click', saveHandler);
            subroleCancel.removeEventListener('click', cancelHandler);
          };

          subroleSave.addEventListener('click', saveHandler);
          subroleCancel.addEventListener('click', cancelHandler);
          return;
        }

        if (gotoBtn) {
          const id = gotoBtn.dataset.id;
          const target = document.querySelector(`li[data-id="${id}"]`);
          if (target) {
            target.scrollIntoView({
              behavior: 'smooth',
              block: 'center'
            });
            target.classList.add('ring', 'ring-orange-300');
            setTimeout(() => target.classList.remove('ring', 'ring-orange-300'), 1400);
          }
          return;
        }

        if (setBtn) {
          const id = setBtn.dataset.id;
          const row = setBtn.closest('li[data-id]');
          if (!row) return;
          const ok = await confirmDialog('Assign Head', 'Assign this member as department head? This will remove any existing head in this department.');
          if (!ok) return;
          setBtn.disabled = true;
          setBtn.textContent = 'Processing...';
          try {
            const json = await sendAction(id, 'set_head');
            if (json && json.success) {
              showToast(json.message || 'Head assigned');
              // clear old head in same department and mark this as head
              const dept = row.dataset.dept;
              document.querySelectorAll(`li[data-dept="${dept}"]`).forEach(it => {
                if (it.dataset.id !== String(id) && it.dataset.isHead === '1') {
                  it.dataset.isHead = '0';
                  const remove = it.querySelector('.remove-head');
                  if (remove) remove.outerHTML = `<button type="button" class="set-head inline-flex items-center gap-2 px-3 py-1 rounded-md bg-orange-600 text-white text-sm" data-id="${it.dataset.id}">Set as Head</button>`;
                  const badge = it.querySelector('.head-badge');
                  if (badge) badge.remove();
                }
              });
              // update current row
              row.dataset.isHead = '1';
              const act = row.querySelector('.set-head');
              if (act) act.outerHTML = `<button type="button" class="remove-head ml-2 inline-flex items-center gap-2 px-3 py-1 rounded-md bg-red-500 text-white text-sm" data-id="${id}">Remove</button>`;
              if (!row.querySelector('.head-badge')) {
                const container = row.querySelector('.flex.items-center.gap-2');
                const badge = document.createElement('span');
                badge.className = 'head-badge inline-flex items-center gap-2 bg-orange-200 text-orange-800 px-3 py-1 rounded-full text-xs font-semibold';
                badge.textContent = 'Head';
                container.insertBefore(badge, container.firstChild);
              }
              refreshHeadsList();
            } else {
              showToast((json && json.message) || 'Failed to assign', 'error');
              setBtn.disabled = false;
              setBtn.textContent = 'Set as Head';
            }
          } catch (err) {
            console.error(err);
            showToast('Network error', 'error');
            setBtn.disabled = false;
            setBtn.textContent = 'Set as Head';
          }
          return;
        }

        if (removeBtn) {
          const id = removeBtn.dataset.id;
          const row = removeBtn.closest('li[data-id]');
          if (!row) return;
          const ok = await confirmDialog('Remove Head', 'Remove head role from this member?');
          if (!ok) return;
          removeBtn.disabled = true;
          removeBtn.textContent = 'Processing...';
          try {
            const json = await sendAction(id, 'remove_head');
            if (json && json.success) {
              // reload so the right-side heads list is refreshed from server
              location.reload();
            } else {
              showToast((json && json.message) || 'Failed to remove', 'error');
              removeBtn.disabled = false;
              removeBtn.textContent = 'Remove';
            }
          } catch (err) {
            console.error(err);
            showToast('Network error', 'error');
            removeBtn.disabled = false;
            removeBtn.textContent = 'Remove';
          }
          return;
        }
      });

      // Apply initial filter from URL
      const params = new URLSearchParams(window.location.search);
      const initialDept = params.get('dept') ? params.get('dept').toLowerCase() : '';
      applyFilter(initialDept);

      // Search: filter members and heads
      search.addEventListener('input', function() {
        const q = (search.value || '').trim().toLowerCase();
        document.querySelectorAll('li[data-id]').forEach(li => {
          const name = (li.querySelector('.font-medium')?.textContent || '').toLowerCase();
          const email = (li.querySelector('.text-xs')?.textContent || '').toLowerCase();
          li.style.display = (!q || name.includes(q) || email.includes(q)) ? '' : 'none';
        });
        refreshHeadsList();
      });

      // initial heads render
      refreshHeadsList();

    }); // DOMContentLoaded
  </script>
</body>

</html>