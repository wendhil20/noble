<?php
//backtracking_view.php
include ROOT_PATH . "/connection/connect.php";
require_once ROOT_PATH . "/admin/authentication/index-admin-role.php";
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

$steps = [
    1 => 'Inquiry',
    2 => 'Sent a Quotation',
    3 => 'P.O Receive / Payment Received',
    4 => 'For Delivery',
    5 => 'Received by Client',
    6 => 'AR/OR Released',
    7 => 'Done',
];

// ─── Upload directory (adjust to your project structure) ───────────────────
define('UPLOAD_DIR', __DIR__ . '/../../uploads/backtrack/');

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$id) {
    header("Location: " . BASE_URL . "/backtrackingview");
    exit();
}

// ─── Handle progress update ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_progress'])) {
    $new_step = (int) $_POST['progress_step'];
    if ($new_step >= 1 && $new_step <= 7) {
        $upd = $conn->prepare("UPDATE backtrack SET progress_step = ? WHERE reference_no = (SELECT reference_no FROM (SELECT reference_no FROM backtrack WHERE id = ?) AS sub)");
        $upd->bind_param("ii", $new_step, $id);
        $upd->execute();
        $upd->close();
        $_SESSION['flash_success'] = "Progress updated to: " . $steps[$new_step];
    }
    header("Location: " . BASE_URL . "/backtrackingview?id=" . $id);
    exit();
}

// ─── Handle file upload ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_file'])) {
    $upload_step = (int) $_POST['upload_step'];
    $reference_no = $_POST['reference_no'] ?? '';
    $uploaded_by = $_SESSION['noble_name'] ?? 'Unknown';

    if ($upload_step >= 1 && $upload_step <= 7 && $reference_no && isset($_FILES['step_file']) && $_FILES['step_file']['error'] === UPLOAD_ERR_OK) {
        if (!is_dir(UPLOAD_DIR)) {
            mkdir(UPLOAD_DIR, 0755, true);
        }

        $original_name = basename($_FILES['step_file']['name']);
        $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        $stored_name = uniqid('bt_', true) . ($ext ? '.' . $ext : '');
        $dest = UPLOAD_DIR . $stored_name;

        if (move_uploaded_file($_FILES['step_file']['tmp_name'], $dest)) {
            $ins = $conn->prepare("INSERT INTO backtrack_files (reference_no, step, original_name, stored_name, uploaded_by) VALUES (?, ?, ?, ?, ?)");
            $ins->bind_param("sisss", $reference_no, $upload_step, $original_name, $stored_name, $uploaded_by);
            $ins->execute();
            $ins->close();
            $_SESSION['flash_success'] = "File uploaded for Step {$upload_step}: " . htmlspecialchars($original_name);
        } else {
            $_SESSION['flash_error'] = "Failed to save the uploaded file.";
        }
    } else {
        $_SESSION['flash_error'] = "Invalid file or step.";
    }

    header("Location: " . BASE_URL . "/backtrackingview?id=" . $id);
    exit();
}

// ─── Handle file delete ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_file'])) {
    $file_id = (int) $_POST['file_id'];
    $ref = $_POST['reference_no'] ?? '';

    $row = null;
    $chk = $conn->prepare("SELECT stored_name FROM backtrack_files WHERE id = ? AND reference_no = ? LIMIT 1");
    $chk->bind_param("is", $file_id, $ref);
    $chk->execute();
    $chk->bind_result($stored_name_del);
    if ($chk->fetch())
        $row = $stored_name_del;
    $chk->close();

    if ($row) {
        $del_stmt = $conn->prepare("DELETE FROM backtrack_files WHERE id = ?");
        $del_stmt->bind_param("i", $file_id);
        $del_stmt->execute();
        $del_stmt->close();

        $path = UPLOAD_DIR . $row;
        if (file_exists($path))
            unlink($path);

        $_SESSION['flash_success'] = "File deleted.";
    }

    header("Location: " . BASE_URL . "/backtrackingview?id=" . $id);
    exit();
}

// ─── Handle note add ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_note'])) {
    $note_step = (int) $_POST['note_step'];
    $note_text = trim($_POST['note_text'] ?? '');
    $note_ref = $_POST['reference_no'] ?? '';
    $note_by = $_SESSION['noble_name'] ?? 'Unknown';

    if ($note_step >= 1 && $note_step <= 7 && $note_ref && $note_text !== '') {
        $ins = $conn->prepare("INSERT INTO backtrack_notes (reference_no, step, note_text, added_by) VALUES (?, ?, ?, ?)");
        $ins->bind_param("siss", $note_ref, $note_step, $note_text, $note_by);
        $ins->execute();
        $ins->close();
        $_SESSION['flash_success'] = "Note added.";
    } else {
        $_SESSION['flash_error'] = "Note cannot be empty.";
    }

    header("Location: " . BASE_URL . "/backtrackingview?id=" . $id);
    exit();
}

// ─── Handle note delete ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_note'])) {
    $note_id = (int) $_POST['note_id'];
    $note_ref = $_POST['reference_no'] ?? '';

    $del = $conn->prepare("DELETE FROM backtrack_notes WHERE id = ? AND reference_no = ?");
    $del->bind_param("is", $note_id, $note_ref);
    $del->execute();
    $del->close();

    $_SESSION['flash_success'] = "Note deleted.";
    header("Location: backtracking_view.php?id=" . $id);
    exit();
}

// ─── Flash messages ────────────────────────────────────────────────────────
$success_msg = $_SESSION['flash_success'] ?? '';
$error_msg = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// ─── Fetch record ──────────────────────────────────────────────────────────
$record = null;
$stmt = $conn->prepare("SELECT * FROM backtrack WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
if ($result && $result->num_rows > 0) {
    $record = $result->fetch_assoc();
}
$stmt->close();

if (!$record) {
    header("Location: backtracking_board.php");
    exit();
}

$current_step = (int) ($record['progress_step'] ?? 1);
$reference_no = $record['reference_no'];

// ─── Fetch uploaded files grouped by step ─────────────────────────────────
$files_by_step = [];
$fstmt = $conn->prepare("SELECT * FROM backtrack_files WHERE reference_no = ? ORDER BY step ASC, uploaded_at DESC");
$fstmt->bind_param("s", $reference_no);
$fstmt->execute();
$fres = $fstmt->get_result();
while ($frow = $fres->fetch_assoc()) {
    $files_by_step[$frow['step']][] = $frow;
}
$fstmt->close();

// ─── Fetch notes grouped by step ──────────────────────────────────────────
$notes_by_step = [];
$nstmt = $conn->prepare("SELECT * FROM backtrack_notes WHERE reference_no = ? ORDER BY step ASC, created_at DESC");
$nstmt->bind_param("s", $reference_no);
$nstmt->execute();
$nres = $nstmt->get_result();
while ($nrow = $nres->fetch_assoc()) {
    $notes_by_step[$nrow['step']][] = $nrow;
}
$nstmt->close();

// ─── Fetch inquiry history ─────────────────────────────────────────────────
$history = [];
$stmt2 = $conn->prepare("SELECT * FROM backtrack WHERE reference_no = ? ORDER BY created_at DESC");
$stmt2->bind_param("s", $reference_no);
$stmt2->execute();
$res2 = $stmt2->get_result();
while ($row = $res2->fetch_assoc()) {
    $history[] = $row;
}
$stmt2->close();

// ─── Helper: icon per file extension ──────────────────────────────────────
function file_icon(string $name): string
{
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    return match ($ext) {
        'pdf' => '',
        'doc', 'docx' => '',
        'xls', 'xlsx' => '',
        'ppt', 'pptx' => '',
        'jpg', 'jpeg',
        'png', 'gif',
        'webp', 'svg' => '',
        'zip', 'rar',
        '7z' => '',
        default => '',
    };
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Profile — <?= htmlspecialchars($record['name']) ?></title>
    <?php include ROOT_PATH . '/admin/navbar/top.php'; ?>
    <style>
        .step-connector {
            flex: 1;
            height: 2px;
        }

        /* File upload drag-over highlight */
        .drop-zone.dragover {
            border-color: #3b82f6;
            background: #eff6ff;
        }

        /* Smooth collapse for file panels */
        .files-panel {
            transition: max-height .3s ease, opacity .3s ease;
            overflow: hidden;
        }

        .files-panel.collapsed {
            max-height: 0;
            opacity: 0;
        }

        .files-panel.expanded {
            max-height: 600px;
            opacity: 1;
        }
    </style>
</head>

<body class="bg-gray-50 min-h-screen">

    <div class="max-w-5xl mx-auto px-4 py-8">

        <!-- Page Header -->
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-800">Client Profile</h1>
                <p class="text-sm text-gray-500 mt-1">Inquiry details and history</p>
            </div>
            <a href="<?= BASE_URL ?>/backtrackingdashboard" class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-medium rounded-lg transition shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                Back to Board
            </a>
        </div>

        <!-- Flash messages -->
        <?php if ($success_msg): ?>
            <div
                class="mb-4 flex items-center gap-2 bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3 rounded-lg">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <?= htmlspecialchars($success_msg) ?>
            </div>
        <?php endif; ?>

        <?php if ($error_msg): ?>
            <div
                class="mb-4 flex items-center gap-2 bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-lg">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
                <?= htmlspecialchars($error_msg) ?>
            </div>
        <?php endif; ?>

        <!-- ═══════════════════════════════════════════════════════════════════
         PROGRESS STEPPER CARD
    ════════════════════════════════════════════════════════════════════ -->
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm mb-6">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-base font-medium text-gray-700">Progress</h2>
                <span class="text-xs text-gray-400">Step <?= $current_step ?> of <?= count($steps) ?></span>
            </div>
            <div class="px-6 py-6">

                <!-- Stepper -->
                <div class="flex items-start w-full overflow-x-auto pb-2">
                    <?php foreach ($steps as $num => $label):
                        $is_done = $num < $current_step;
                        $is_current = $num === $current_step;
                        $step_file_count = count($files_by_step[$num] ?? []);
                        ?>
                        <div class="flex flex-col items-center min-w-[80px] flex-1">
                            <!-- Circle + connector row -->
                            <div class="flex items-center w-full justify-center">
                                <?php if ($num > 1): ?>
                                    <div class="step-connector <?= $is_done || $is_current ? 'bg-blue-500' : 'bg-gray-200' ?>">
                                    </div>
                                <?php endif; ?>

                                <!-- Circle (clickable to toggle file panel) -->
                                <button type="button" onclick="togglePanel(<?= $num ?>)" class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center text-xs font-semibold z-10 transition
                                <?php if ($is_done)
                                    echo 'bg-blue-500 text-white hover:bg-blue-600';
                                elseif ($is_current)
                                    echo 'bg-blue-600 text-white ring-4 ring-blue-100 hover:bg-blue-700';
                                else
                                    echo 'bg-gray-100 text-gray-400 border border-gray-200 hover:bg-gray-200'; ?>">
                                    <?php if ($is_done): ?>
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                                d="M5 13l4 4L19 7" />
                                        </svg>
                                    <?php else: ?>
                                        <?= $num ?>
                                    <?php endif; ?>
                                </button>

                                <?php if ($num < count($steps)): ?>
                                    <div class="step-connector <?= $is_done ? 'bg-blue-500' : 'bg-gray-200' ?>"></div>
                                <?php endif; ?>
                            </div>

                            <!-- Label -->
                            <p class="mt-2 text-center text-xs leading-tight px-1 h-10 flex items-start justify-center
    <?php if ($is_current)
        echo 'text-blue-600 font-semibold';
    elseif ($is_done)
        echo 'text-blue-400';
    else
        echo 'text-gray-400'; ?>">
                                <?= htmlspecialchars($label) ?>
                            </p>

                            <!-- File badge -->
                            <?php if ($step_file_count > 0): ?>
                                <span
                                    class="mt-1 inline-flex items-center gap-1 text-[10px] font-medium text-blue-600 bg-blue-50 border border-blue-100 rounded-full px-2 py-0.5">
                                    📎 <?= $step_file_count ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Update Progress Form -->
                <form method="POST" action="" class="mt-6 flex items-center gap-3">
                    <label class="text-sm text-gray-600 font-medium whitespace-nowrap">Move to:</label>
                    <select name="progress_step"
                        class="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <?php foreach ($steps as $num => $label): ?>
                            <option value="<?= $num ?>" <?= $num === $current_step ? 'selected' : '' ?>>
                                Step <?= $num ?> — <?= htmlspecialchars($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" name="update_progress"
                        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition">
                        Update
                    </button>
                </form>

            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════════
         PER-STEP FILE PANELS
    ════════════════════════════════════════════════════════════════════ -->
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm mb-6">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-base font-medium text-gray-700">Step Attachments</h2>
                <span class="text-xs text-gray-400">Click a step circle above or a step tab below to manage files</span>
            </div>

            <!-- Step tabs -->
            <div class="flex overflow-x-auto border-b border-gray-100 px-4 gap-1 pt-2">
                <?php foreach ($steps as $num => $label):
                    $cnt = count($files_by_step[$num] ?? []);
                    ?>
                    <button type="button" id="tab-<?= $num ?>" onclick="showPanel(<?= $num ?>)"
                        class="step-tab flex-shrink-0 px-3 py-2 text-xs font-medium rounded-t-lg border border-b-0 transition whitespace-nowrap
                    <?= $num === 1 ? 'bg-white border-gray-200 text-blue-600' : 'bg-gray-50 border-transparent text-gray-500 hover:text-gray-700' ?>">
                        Step <?= $num ?>
                        <?php if ($cnt > 0): ?>
                            <span
                                class="ml-1 bg-blue-100 text-blue-600 rounded-full px-1.5 py-0.5 text-[10px]"><?= $cnt ?></span>
                        <?php endif; ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <?php foreach ($steps as $num => $label):
                $step_files = $files_by_step[$num] ?? [];
                $step_notes = $notes_by_step[$num] ?? [];
                ?>
                <div id="panel-<?= $num ?>" class="<?= $num === 1 ? '' : 'hidden' ?> px-6 py-5">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-700">Step <?= $num ?>:
                                <?= htmlspecialchars($label) ?>
                            </h3>
                            <p class="text-xs text-gray-400 mt-0.5">
                                <?= count($step_files) ?>
                                file(s)<?= $num === 1 ? ' · ' . count($step_notes) . ' note(s)' : '' ?>
                            </p>
                        </div>
                    </div>

                    <?php if ($num === 1): ?>
                        <!-- ── NOTES SECTION (Step 1 only) ───────────────────────────────── -->
                        <div class="mb-5">
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">📝 Notes</p>

                            <form method="POST" action="" class="flex gap-2 mb-3">
                                <input type="hidden" name="note_step" value="<?= $num ?>">
                                <input type="hidden" name="reference_no" value="<?= htmlspecialchars($reference_no) ?>">
                                <textarea name="note_text" rows="2" placeholder="Write a note for this step…"
                                    class="flex-1 text-sm border border-gray-200 rounded-lg px-3 py-2 resize-none focus:outline-none focus:ring-2 focus:ring-blue-400"
                                    required></textarea>
                                <button type="submit" name="add_note"
                                    class="self-end px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition">
                                    Save
                                </button>
                            </form>

                            <?php if (empty($step_notes)): ?>
                                <p class="text-xs text-gray-400 italic">No notes yet for this step.</p>
                            <?php else: ?>
                                <ul class="space-y-2">
                                    <?php foreach ($step_notes as $note): ?>
                                        <li class="flex gap-3 bg-yellow-50 border border-yellow-100 rounded-lg px-4 py-3">
                                            <span class="text-yellow-400 mt-0.5 shrink-0">📌</span>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm text-gray-700  wrap-break-word">
                                                    <?= htmlspecialchars($note['note_text']) ?>
                                                </p>
                                                <p class="text-xs text-gray-400 mt-1">
                                                    By <?= htmlspecialchars($note['added_by'] ?? '—') ?>
                                                    · <?= date('M d, Y h:i A', strtotime($note['created_at'])) ?>
                                                </p>
                                            </div>
                                            <form method="POST" action="" onsubmit="return confirm('Delete this note?')"
                                                class="flex-shrink-0">
                                                <input type="hidden" name="note_id" value="<?= $note['id'] ?>">
                                                <input type="hidden" name="reference_no" value="<?= htmlspecialchars($reference_no) ?>">
                                                <button type="submit" name="delete_note"
                                                    class="text-red-400 hover:text-red-600 transition" title="Delete note">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </form>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>

                        <hr class="border-gray-100 mb-5">
                    <?php endif; ?>

                    <!-- ── FILES SECTION ─────────────────────────────────────────────── -->
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">📎 Files</p>
                    <form method="POST" action="" enctype="multipart/form-data"
                        class="drop-zone border-2 border-dashed border-gray-200 rounded-lg p-4 mb-4 hover:border-blue-300 transition"
                        ondragover="this.classList.add('dragover')" ondragleave="this.classList.remove('dragover')"
                        ondrop="this.classList.remove('dragover')">
                        <input type="hidden" name="upload_step" value="<?= $num ?>">
                        <input type="hidden" name="reference_no" value="<?= htmlspecialchars($reference_no) ?>">

                        <div class="flex flex-col sm:flex-row items-center gap-3">
                            <label class="flex-1 flex items-center gap-3 cursor-pointer">
                                <div class="w-9 h-9 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600">
                                        <span class="font-medium text-blue-600">Click to browse</span> or drag &amp; drop
                                    </p>
                                    <p class="text-xs text-gray-400">Any file type allowed</p>
                                </div>
                                <input type="file" name="step_file" class="sr-only" required onchange="updateLabel(this)">
                            </label>
                            <button type="submit" name="upload_file"
                                class="flex-shrink-0 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition">
                                Upload
                            </button>
                        </div>
                        <p id="fname-<?= $num ?>" class="mt-2 text-xs text-gray-500 hidden"></p>
                    </form>

                    <?php if (empty($step_files)): ?>
                        <p class="text-sm text-gray-400 text-center py-4 italic">No files attached to this step yet.</p>
                    <?php else: ?>
                        <ul class="divide-y divide-gray-100">
                            <?php foreach ($step_files as $f): ?>
                                <li class="flex items-center justify-between py-2.5 gap-3">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <span class="text-xl flex-shrink-0"><?= file_icon($f['original_name']) ?></span>
                                        <div class="min-w-0">
                                            <p class="text-sm font-medium text-gray-700 truncate">
                                                <?= htmlspecialchars($f['original_name']) ?>
                                            </p>
                                            <p class="text-xs text-gray-400">
                                                By <?= htmlspecialchars($f['uploaded_by'] ?? '—') ?>
                                                · <?= date('M d, Y h:i A', strtotime($f['uploaded_at'])) ?>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 flex-shrink-0">
                                        <a href="../../uploads/backtrack/<?= urlencode($f['stored_name']) ?>"
                                            download="<?= htmlspecialchars($f['original_name']) ?>"
                                            class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                            </svg>
                                            Download
                                        </a>
                                        <form method="POST" action="" onsubmit="return confirm('Delete this file?')">
                                            <input type="hidden" name="file_id" value="<?= $f['id'] ?>">
                                            <input type="hidden" name="reference_no" value="<?= htmlspecialchars($reference_no) ?>">
                                            <button type="submit" name="delete_file"
                                                class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-red-600 bg-red-50 hover:bg-red-100 rounded-lg transition">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════════
         CLIENT INFORMATION CARD
    ════════════════════════════════════════════════════════════════════ -->
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm mb-6">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-base font-medium text-gray-700">Client Information</h2>
                <span
                    class="inline-block bg-blue-50 text-blue-700 text-xs font-mono font-medium px-3 py-1 rounded-full">
                    <?= htmlspecialchars($record['reference_no'] ?? '—') ?>
                </span>
            </div>
            <div class="px-6 py-5 grid grid-cols-1 md:grid-cols-2 gap-5">

                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Full Name</p>
                    <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($record['name']) ?></p>
                </div>

                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Email Address</p>
                    <p class="text-sm text-gray-700"><?= htmlspecialchars($record['email']) ?></p>
                </div>

                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Contact Number</p>
                    <p class="text-sm text-gray-700"><?= htmlspecialchars($record['contact']) ?></p>
                </div>

                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Company Name</p>
                    <p class="text-sm text-gray-700"><?= htmlspecialchars($record['company_name']) ?></p>
                </div>

                <div class="md:col-span-2">
                    <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Company Address</p>
                    <p class="text-sm text-gray-700"><?= htmlspecialchars($record['company_address'] ?: '—') ?></p>
                </div>

                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Inquiry Date</p>
                    <p class="text-sm text-gray-700">
                        <?= $record['inquiry_date'] ? date('F d, Y', strtotime($record['inquiry_date'])) : '—' ?>
                    </p>
                </div>

                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Inquiry Time</p>
                    <p class="text-sm text-gray-700">
                        <?= $record['inquiry_time'] ? date('h:i A', strtotime($record['inquiry_time'])) : '—' ?>
                    </p>
                </div>

                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Submitted By</p>
                    <p class="text-sm text-gray-700"><?= htmlspecialchars($record['submitted_by'] ?? '—') ?></p>
                </div>

                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Date Recorded</p>
                    <p class="text-sm text-gray-700">
                        <?= isset($record['created_at']) ? date('F d, Y h:i A', strtotime($record['created_at'])) : '—' ?>
                    </p>
                </div>

                <div class="md:col-span-2">
                    <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Message</p>
                    <div
                        class="bg-gray-50 border border-gray-100 rounded-lg px-4 py-3 text-sm text-gray-700 min-h-[60px]">
                        <?= !empty($record['message']) ? nl2br(htmlspecialchars($record['message'])) : '<span class="text-gray-400 italic">No message provided.</span>' ?>
                    </div>
                </div>

            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════════
         INQUIRY HISTORY
    ════════════════════════════════════════════════════════════════════ -->
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-base font-medium text-gray-700">Inquiry History</h2>
                <span class="text-xs text-gray-400"><?= count($history) ?> inquiries under this reference</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide border-b border-gray-100">
                        <tr>
                            <th class="px-4 py-3">#</th>
                            <th class="px-4 py-3">Inquiry Date</th>
                            <th class="px-4 py-3">Inquiry Time</th>
                            <th class="px-4 py-3">Company</th>
                            <th class="px-4 py-3">Message</th>
                            <th class="px-4 py-3">Submitted By</th>
                            <th class="px-4 py-3">Recorded At</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (empty($history)): ?>
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-gray-400 text-sm">No history found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($history as $i => $h): ?>
                                <tr class="<?= $h['id'] == $id ? 'bg-blue-50' : 'hover:bg-gray-50' ?> transition">
                                    <td class="px-4 py-3 text-gray-400"><?= $i + 1 ?></td>
                                    <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                                        <?= $h['inquiry_date'] ? date('M d, Y', strtotime($h['inquiry_date'])) : '—' ?>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                                        <?= $h['inquiry_time'] ? date('h:i A', strtotime($h['inquiry_time'])) : '—' ?>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600"><?= htmlspecialchars($h['company_name']) ?></td>
                                    <td class="px-4 py-3 text-gray-600 max-w-xs truncate">
                                        <?= !empty($h['message']) ? htmlspecialchars($h['message']) : '<span class="text-gray-300 italic">—</span>' ?>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600"><?= htmlspecialchars($h['submitted_by'] ?? '—') ?></td>
                                    <td class="px-4 py-3 text-gray-500 whitespace-nowrap text-xs">
                                        <?= isset($h['created_at']) ? date('M d, Y h:i A', strtotime($h['created_at'])) : '—' ?>
                                        <?php if ($h['id'] == $id): ?>
                                            <span class="ml-1 text-blue-500 font-medium">(current)</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <script>
        // ── Tab switching ──────────────────────────────────────────────────────────
        function showPanel(num) {
            // Hide all panels, deactivate all tabs
            document.querySelectorAll('[id^="panel-"]').forEach(p => p.classList.add('hidden'));
            document.querySelectorAll('.step-tab').forEach(t => {
                t.classList.remove('bg-white', 'border-gray-200', 'text-blue-600');
                t.classList.add('bg-gray-50', 'border-transparent', 'text-gray-500');
            });

            // Show target panel, activate target tab
            const panel = document.getElementById('panel-' + num);
            const tab = document.getElementById('tab-' + num);
            if (panel) panel.classList.remove('hidden');
            if (tab) {
                tab.classList.remove('bg-gray-50', 'border-transparent', 'text-gray-500');
                tab.classList.add('bg-white', 'border-gray-200', 'text-blue-600');
            }
        }

        // Clicking a step circle in the stepper also switches the tab
        function togglePanel(num) {
            showPanel(num);
            document.getElementById('panel-' + num)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        // Show selected filename in the drop zone
        function updateLabel(input) {
            const step = input.closest('form').querySelector('[name="upload_step"]').value;
            const label = document.getElementById('fname-' + step);
            if (label) {
                label.textContent = '📎 ' + (input.files[0]?.name ?? '');
                label.classList.remove('hidden');
            }
        }
    </script>

</body>

</html>