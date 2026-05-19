<?php
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";
require_role(['productspecialist', 'superadmin']);
include ROOT_PATH . "/user/navbar/main-tag-helpers.php";

if (!isset($_SESSION['noble_user'])) {
  header("Location: " . BASE_URL . "/main");
  exit();
}

$message = "";
$error = "";
if (isset($_SESSION['message'])) {
  $message = $_SESSION['message'];
  unset($_SESSION['message']);
}
if (isset($_SESSION['error'])) {
  $error = $_SESSION['error'];
  unset($_SESSION['error']);
}

if ($_POST) {

  // ── ADD TAG ──────────────────────────────────────────────────────────
  if ($_POST['action'] === 'add_tag') {
    $slug = trim(preg_replace('/[^a-z0-9_]/', '_', strtolower($_POST['tag_slug'] ?? '')));
    $label = trim($_POST['tag_label'] ?? '');
    $bg = trim($_POST['tag_bg'] ?? '#f3f4f6');
    $color = trim($_POST['tag_color'] ?? '#374151');
    $dot = trim($_POST['tag_dot'] ?? '#9ca3af');
    $border = trim($_POST['tag_border'] ?? '#e5e7eb');

    if ($slug === '' || $label === '') {
      $_SESSION['error'] = "Slug and label are required.";
    } elseif ($slug === 'normal') {
      $_SESSION['error'] = "'normal' is reserved.";
    } else {
      $chk = $conn->prepare("SELECT slug FROM tag_config WHERE slug = ?");
      $chk->bind_param('s', $slug);
      $chk->execute();
      if ($chk->get_result()->num_rows > 0) {
        $_SESSION['error'] = "Tag '$slug' already exists.";
      } else {
        $stmt = $conn->prepare("INSERT INTO tag_config (slug, label, bg, color, dot, border) VALUES (?,?,?,?,?,?)");
        $stmt->bind_param('ssssss', $slug, $label, $bg, $color, $dot, $border);
        $ok = $stmt->execute();
        $_SESSION[$ok ? 'message' : 'error'] = $ok ? "Tag '$slug' added!" : "DB error: " . $conn->error;
        $stmt->close();
      }
      $chk->close();
    }
    header("Location: " . BASE_URL . "/managetags");
    exit();
  }

  // ── UPDATE TAG ────────────────────────────────────────────────────────
  if ($_POST['action'] === 'update_tag') {
    $slug = trim($_POST['tag_slug'] ?? '');
    $label = trim($_POST['tag_label'] ?? '');
    $bg = trim($_POST['tag_bg'] ?? '');
    $color = trim($_POST['tag_color'] ?? '');
    $dot = trim($_POST['tag_dot'] ?? '');
    $border = trim($_POST['tag_border'] ?? '');

    if ($slug === '' || $label === '') {
      $_SESSION['error'] = "Slug and label are required.";
    } else {
      $stmt = $conn->prepare("INSERT INTO tag_config (slug, label, bg, color, dot, border) VALUES (?,?,?,?,?,?)");
      $stmt->bind_param('ssssss', $slug, $label, $bg, $color, $dot, $border);
      $ok = $stmt->execute();
      $_SESSION[$ok ? 'message' : 'error'] = $ok ? "Tag '$slug' added!" : "DB error: " . $conn->error;
      $stmt->close();
    }
    header("Location: " . BASE_URL . "/managetags");
    exit();
  }

  // ── DELETE TAG ────────────────────────────────────────────────────────
  if ($_POST['action'] === 'delete_tag') {
    $slug = trim($_POST['tag_slug'] ?? '');
    if ($slug === 'normal') {
      $_SESSION['error'] = "'normal' cannot be deleted.";
    } else {
      // Reset all rows using this tag back to 'normal'
      foreach (['categories', 'product_subcategories', 'product_sub_subcategories'] as $t) {
        $conn->query("UPDATE `$t` SET `tag` = 'normal' WHERE `tag` = '$slug'");
      }
      // ── DELETE TAG ──
      $stmt = $conn->prepare("DELETE FROM tag_config WHERE slug = ?");
      $stmt->bind_param('s', $slug);
      $ok = $stmt->execute();
      $_SESSION[$ok ? 'message' : 'error'] = $ok ? "Tag '$slug' deleted." : "DB error: " . $conn->error;
      $stmt->close();
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
  }
}

// ── Read current tags + usage counts ─────────────────────────────────────
$all_tags = get_tag_config($conn);

$usage = ['normal' => 0];
foreach ($all_tags as $slug => $_)
  $usage[$slug] = 0;
foreach (['categories', 'product_subcategories', 'product_sub_subcategories'] as $t) {
  $r = $conn->query("SELECT tag, COUNT(*) as cnt FROM `$t` GROUP BY tag");
  while ($row = $r->fetch_assoc()) {
    $usage[$row['tag']] = ($usage[$row['tag']] ?? 0) + (int) $row['cnt'];
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Manage Tags</title>
  <style>
    .tag-pill {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      font-size: 11px;
      font-weight: 700;
      padding: 2px 10px;
      border-radius: 99px;
      white-space: nowrap;
    }

    .color-swatch {
      width: 22px;
      height: 22px;
      border-radius: 5px;
      border: 1px solid #e5e7eb;
      display: inline-block;
      vertical-align: middle;
    }

    .edit-row {
      display: none;
    }

    .edit-row.open {
      display: table-row;
    }
  </style>
</head>

<body class="bg-gray-100 font-sans">
  <?php include ROOT_PATH . '/admin/navbar/top.php'; ?>

  <div class="max-w-5xl mx-auto p-6">
    <div class="bg-white rounded-xl shadow-lg p-8">

      <div class="flex items-center justify-between mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800">Manage Tags</h1>
          <p class="text-sm text-gray-500 mt-1">Add, edit, or delete tags. No schema changes needed — just DB rows.</p>
        </div>
        <a href="<?= BASE_URL; ?>/category"
          class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition">←
          Back</a>
      </div>

      <?php if ($message): ?>
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg mb-5">
          <?= htmlspecialchars($message) ?></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-5">
          <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <!-- ADD FORM -->
      <div class="bg-gray-50 border border-gray-200 rounded-xl p-6 mb-8">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Add New Tag</h2>
        <form method="POST" class="grid grid-cols-2 md:grid-cols-3 gap-4">
          <input type="hidden" name="action" value="add_tag">

          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Slug *</label>
            <input type="text" name="tag_slug" placeholder="e.g. flash_sale"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" required
              oninput="this.value=this.value.toLowerCase().replace(/[^a-z0-9_]/g,'')">
            <p class="text-xs text-gray-400 mt-1">Lowercase + underscores only</p>
          </div>

          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Display Label *</label>
            <input type="text" name="tag_label" placeholder="e.g. Flash Sale"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" required>
          </div>

          <?php foreach ([
            ['tag_bg', 'add_bg', '#ffae00', 'Background'],
            ['tag_color', 'add_color', '#ffffff', 'Text Color'],
            ['tag_dot', 'add_dot', '#ffffff', 'Dot Color'],
            ['tag_border', 'add_border', '#ffae00', 'Border Color'],
          ] as [$name, $id, $default, $lbl]): ?>
            <div>
              <label class="block text-xs font-medium text-gray-600 mb-1"><?= $lbl ?></label>
              <div class="flex items-center gap-2">
                <input type="color" name="<?= $name ?>" value="<?= $default ?>" id="<?= $id ?>_picker"
                  class="w-10 h-9 rounded border border-gray-300 cursor-pointer p-0.5"
                  oninput="document.getElementById('<?= $id ?>_text').value=this.value;updateAddPreview()">
                <input type="text" id="<?= $id ?>_text" value="<?= $default ?>"
                  class="flex-1 px-2 py-2 border border-gray-300 rounded-lg text-xs font-mono"
                  oninput="document.getElementById('<?= $id ?>_picker').value=this.value;updateAddPreview()">
              </div>
            </div>
          <?php endforeach; ?>

          <div class="md:col-span-3 flex items-center gap-4 pt-2">
            <button type="submit"
              class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded-lg text-sm font-medium transition">+
              Add Tag</button>
            <span class="text-xs text-gray-400">Preview:</span>
            <span id="add_preview" class="tag-pill" style="background:#ffae00;color:#ffffff;border:1px solid #ffae00;">
              <span id="add_dot_el"
                style="width:5px;height:5px;border-radius:50%;background:#ffffff;display:inline-block;"></span>
              <span id="add_label_el">New Tag</span>
            </span>
          </div>
        </form>
      </div>

      <!-- TAGS TABLE -->
      <h2 class="text-lg font-semibold text-gray-800 mb-4">Current Tags</h2>
      <div class="overflow-x-auto rounded-xl border border-gray-200">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 text-gray-600 text-xs uppercase">
            <tr>
              <th class="px-4 py-3 text-left">Slug</th>
              <th class="px-4 py-3 text-left">Preview</th>
              <th class="px-4 py-3 text-left">Label</th>
              <th class="px-4 py-3 text-center">Colors</th>
              <th class="px-4 py-3 text-center">In Use</th>
              <th class="px-4 py-3 text-center">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">

            <!-- normal row — reserved -->
            <tr class="hover:bg-gray-50">
              <td class="px-4 py-3 font-mono text-xs text-gray-500">normal</td>
              <td class="px-4 py-3"><span class="text-gray-400 italic text-xs">no badge</span></td>
              <td class="px-4 py-3 text-gray-400 italic text-xs">Normal (default)</td>
              <td class="px-4 py-3 text-center text-gray-300">—</td>
              <td class="px-4 py-3 text-center">
                <span
                  class="bg-gray-100 text-gray-500 text-xs font-semibold px-2 py-0.5 rounded-full"><?= $usage['normal'] ?? 0 ?></span>
              </td>
              <td class="px-4 py-3 text-center"><span class="text-xs text-gray-400 italic">reserved</span></td>
            </tr>

            <?php foreach ($all_tags as $slug => $t):
              $label = $t['label'];
              $bg = $t['bg'];
              $color = $t['color'];
              $dot = $t['dot'];
              $border = $t['border'];
              $count = $usage[$slug] ?? 0;
              ?>
              <!-- Display row -->
              <tr class="hover:bg-gray-50" id="row-<?= htmlspecialchars($slug) ?>">
                <td class="px-4 py-3 font-mono text-xs text-gray-700"><?= htmlspecialchars($slug) ?></td>
                <td class="px-4 py-3">
                  <span class="tag-pill"
                    style="background:<?= $bg ?>;color:<?= $color ?>;border:1px solid <?= $border ?>;">
                    <span
                      style="width:5px;height:5px;border-radius:50%;background:<?= $dot ?>;display:inline-block;"></span>
                    <?= htmlspecialchars($label) ?>
                  </span>
                </td>
                <td class="px-4 py-3 text-gray-800"><?= htmlspecialchars($label) ?></td>
                <td class="px-4 py-3 text-center">
                  <div class="flex justify-center gap-1.5">
                    <span class="color-swatch" style="background:<?= $bg ?>;" title="bg"></span>
                    <span class="color-swatch" style="background:<?= $color ?>;" title="text"></span>
                    <span class="color-swatch" style="background:<?= $dot ?>;" title="dot"></span>
                    <span class="color-swatch" style="background:<?= $border ?>;" title="border"></span>
                  </div>
                </td>
                <td class="px-4 py-3 text-center">
                  <span
                    class="<?= $count > 0 ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-400' ?> text-xs font-semibold px-2 py-0.5 rounded-full">
                    <?= $count ?>
                  </span>
                </td>
                <td class="px-4 py-3 text-center">
                  <div class="flex justify-center gap-2">
                    <button onclick="toggleEdit('<?= $slug ?>')"
                      class="bg-yellow-400 hover:bg-yellow-500 text-white px-3 py-1 rounded text-xs font-medium transition">Edit</button>
                    <form method="POST" class="inline"
                      onsubmit="return confirm('Delete tag \'<?= htmlspecialchars($slug) ?>\'? All rows using it reset to normal.')">
                      <input type="hidden" name="action" value="delete_tag">
                      <input type="hidden" name="tag_slug" value="<?= htmlspecialchars($slug) ?>">
                      <button type="submit"
                        class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-xs font-medium transition">Delete</button>
                    </form>
                  </div>
                </td>
              </tr>

              <!-- Inline edit row -->
              <tr class="edit-row bg-indigo-50" id="edit-<?= htmlspecialchars($slug) ?>">
                <td colspan="6" class="px-4 py-4">
                  <form method="POST" class="grid grid-cols-2 md:grid-cols-4 gap-3 items-end">
                    <input type="hidden" name="action" value="update_tag">
                    <input type="hidden" name="tag_slug" value="<?= htmlspecialchars($slug) ?>">

                    <div>
                      <label class="block text-xs font-medium text-gray-600 mb-1">Label</label>
                      <input type="text" name="tag_label" value="<?= htmlspecialchars($label) ?>"
                        class="w-full px-3 py-1.5 border border-gray-300 rounded-lg text-sm" required>
                    </div>

                    <?php foreach ([
                      ['tag_bg', "e_bg_{$slug}", $bg, 'Background'],
                      ['tag_color', "e_color_{$slug}", $color, 'Text Color'],
                      ['tag_dot', "e_dot_{$slug}", $dot, 'Dot Color'],
                      ['tag_border', "e_border_{$slug}", $border, 'Border'],
                    ] as [$fname, $fid, $fval, $flbl]): ?>
                      <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1"><?= $flbl ?></label>
                        <div class="flex items-center gap-1.5">
                          <input type="color" name="<?= $fname ?>" value="<?= $fval ?>" id="<?= $fid ?>_picker"
                            class="w-9 h-8 rounded border border-gray-300 cursor-pointer p-0.5"
                            oninput="document.getElementById('<?= $fid ?>_text').value=this.value">
                          <input type="text" id="<?= $fid ?>_text" value="<?= $fval ?>"
                            class="flex-1 px-2 py-1.5 border border-gray-300 rounded-lg text-xs font-mono"
                            oninput="document.getElementById('<?= $fid ?>_picker').value=this.value">
                        </div>
                      </div>
                    <?php endforeach; ?>

                    <div class="md:col-span-4 flex gap-3 pt-1">
                      <button type="submit"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-1.5 rounded-lg text-sm font-medium transition">Save</button>
                      <button type="button" onclick="toggleEdit('<?= htmlspecialchars($slug) ?>')"
                        class="bg-gray-400 hover:bg-gray-500 text-white px-4 py-1.5 rounded-lg text-sm transition">Cancel</button>
                    </div>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>

          </tbody>
        </table>
      </div>

      <div class="mt-6 bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 text-sm text-amber-800">
        <strong>Note:</strong> Tag columns are now <code class="bg-amber-100 px-1 rounded">VARCHAR(50)</code> — no <code
          class="bg-amber-100 px-1 rounded">ALTER TABLE</code> needed when adding/deleting tags. Just manage rows in
        <code class="bg-amber-100 px-1 rounded">tag_config</code>.
      </div>
    </div>
  </div>

  <script>
    function toggleEdit(slug) {
      document.getElementById('edit-' + slug).classList.toggle('open');
    }

    function updateAddPreview() {
      const bg = document.getElementById('add_bg_picker').value;
      const color = document.getElementById('add_color_picker').value;
      const dot = document.getElementById('add_dot_picker').value;
      const border = document.getElementById('add_border_picker').value;
      const label = document.querySelector('[name=tag_label]').value
        || document.querySelector('[name=tag_slug]').value
        || 'New Tag';

      const preview = document.getElementById('add_preview');
      preview.style.background = bg;
      preview.style.color = color;
      preview.style.borderColor = border;
      document.getElementById('add_dot_el').style.background = dot;
      document.getElementById('add_label_el').textContent = label;
    }

    document.querySelector('[name=tag_label]')?.addEventListener('input', updateAddPreview);
    document.querySelector('[name=tag_slug]')?.addEventListener('input', updateAddPreview);
    updateAddPreview();
  </script>
</body>

</html>
<?php $conn->close(); ?>