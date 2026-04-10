<?php
session_name("nobleadmin");
session_start();
include '../../connection/connect.php';
include '../role/roleaccount.php';
require_role(['productspecialist', 'superadmin']);

if (!isset($_SESSION['noble_user'])) {
    header("Location: ../../loginpage/index.php");
    exit();
}

// ── UPSERT per stage ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['stage'])) {
    $stage = intval($_POST['stage']);
    $title = trim($_POST['title']);
    $link = trim($_POST['link']);
    $status = $_POST['status'];

    // Check existing
    $check = $conn->prepare("SELECT id, image_path FROM promotion_banners WHERE stage = ? LIMIT 1");
    $check->bind_param("i", $stage);
    $check->execute();
    $existing = $check->get_result()->fetch_assoc();
    $check->close();

    // Image upload
    $image_path = $existing['image_path'] ?? '';
    $file_key = 'image_' . $stage;

    if (!empty($_FILES[$file_key]['name'])) {
        $ext = strtolower(pathinfo($_FILES[$file_key]['name'], PATHINFO_EXTENSION));
        $filename = 'banner_stage' . $stage . '_' . time() . '.' . $ext;
        $upload_dir = '../../uploads/';
        if (move_uploaded_file($_FILES[$file_key]['tmp_name'], $upload_dir . $filename)) {
            if (!empty($existing['image_path']) && file_exists($upload_dir . $existing['image_path'])) {
                @unlink($upload_dir . $existing['image_path']);
            }
            $image_path = $filename;
        }
    }

    if ($existing) {
        $stmt = $conn->prepare("UPDATE promotion_banners SET title=?, link=?, image_path=?, status=?, updated_at=NOW() WHERE stage=?");
        $stmt->bind_param("ssssi", $title, $link, $image_path, $status, $stage);
    } else {
        $stmt = $conn->prepare("INSERT INTO promotion_banners (stage, title, link, image_path, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $stage, $title, $link, $image_path, $status);
    }
    $stmt->execute();
    $stmt->close();

    header("Location: promotion-banner.php?success=" . $stage);
    exit();
}

// ── Fetch all 3 stages ────────────────────────────────────────────────────────
$stages = [];
for ($s = 1; $s <= 3; $s++) {
    $q = $conn->prepare("SELECT * FROM promotion_banners WHERE stage = ? LIMIT 1");
    $q->bind_param("i", $s);
    $q->execute();
    $stages[$s] = $q->get_result()->fetch_assoc();
    $q->close();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Promotion Banners — Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap"
        rel="stylesheet">   
    <style>
        body {
            background: #f8fafc;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        
        .card-hover {
            transition: box-shadow .2s, transform .2s;
        }

        .card-hover:hover {
            box-shadow: 0 8px 32px rgba(0, 0, 0, .07);
            transform: translateY(-2px);
        }

        .drop-zone {
            transition: border-color .2s, background .2s;
        }

        .drop-zone.dragover {
            border-color: #ca8a04 !important;
            background: #fefce8;
        }

        ::-webkit-scrollbar {
            width: 5px;
        }

        ::-webkit-scrollbar-thumb {
            background: #e2e8f0;
            border-radius: 4px;
        }
    </style>
</head>

<body class="min-h-screen">

    <?php include '../navbar/top.php'; ?>

    <!-- TOP BAR -->
    <div class="bg-white border-b border-gray-100 sticky top-0 z-20 shadow-sm">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 h-14 flex items-center gap-3">
            <span class="text-yellow-500"><i class="fa-solid fa-rectangle-ad text-lg"></i></span>
            <h1 class="font-bold text-gray-800 text-sm tracking-tight">Promotion Banners</h1>
            <span class="text-gray-300 text-xs">/</span>
            <span class="text-gray-400 text-xs">Manage display slots</span>
        </div>
    </div>

    <!-- TOAST -->
    <?php if (isset($_GET['success'])): ?>
        <div id="toast"
            class="fixed top-5 right-5 z-50 flex items-center gap-3 bg-green-600 text-white text-sm font-semibold px-5 py-3 rounded-2xl shadow-xl">
            <i class="fa-solid fa-circle-check"></i>
            Stage <?= intval($_GET['success']) ?> banner saved!
            <button onclick="this.parentElement.remove()" class="ml-1 opacity-60 hover:opacity-100"><i
                    class="fa-solid fa-xmark"></i></button>
        </div>
        <script>setTimeout(() => { const t = document.getElementById('toast'); if (t) t.remove(); }, 3500);</script>
    <?php endif; ?>

    <!-- PAGE -->
    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-8">

        <div class="mb-7">
            <h2 class="text-xl font-bold text-gray-800">Banner Slots</h2>
            <p class="text-sm text-gray-400 mt-1">One banner per slot. Each slot maps to a position on the front page.
            </p>
        </div>

        <!-- Position hint bar -->
        <div
            class="mb-6 bg-white border border-gray-100 rounded-2xl px-5 py-4 flex flex-wrap items-center gap-x-6 gap-y-2 shadow-sm text-xs text-gray-500">
            <span class="flex items-center gap-1.5"><span
                    class="w-2 h-2 rounded-full bg-yellow-400 inline-block"></span><strong class="text-gray-700">Stage
                    1</strong> — Top</span>
            <span class="text-gray-200">|</span>
            <span class="flex items-center gap-1.5"><span
                    class="w-2 h-2 rounded-full bg-blue-400 inline-block"></span><strong class="text-gray-700">Stage
                    2</strong> — Bottom</span>
            <span class="text-gray-200">|</span>
            <span class="flex items-center gap-1.5"><span
                    class="w-2 h-2 rounded-full bg-emerald-400 inline-block"></span><strong class="text-gray-700">Stage
                    3</strong> — Middle</span>
            <span class="ml-auto text-gray-300 hidden sm:block">Positions are set in your front-end view</span>
        </div>

        <!-- 3 CARDS -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

            <?php
            $meta = [
                1 => ['label' => 'Stage 1', 'pos' => 'Top Position', 'accent_bg' => 'bg-yellow-500', 'light_bg' => 'bg-yellow-50', 'border' => 'border-yellow-200', 'text' => 'text-yellow-600', 'icon' => 'fa-arrow-up'],
                2 => ['label' => 'Stage 2', 'pos' => 'Bottom Position', 'accent_bg' => 'bg-blue-500', 'light_bg' => 'bg-blue-50', 'border' => 'border-blue-200', 'text' => 'text-blue-600', 'icon' => 'fa-arrow-down'],
                3 => ['label' => 'Stage 3', 'pos' => 'Middle Position', 'accent_bg' => 'bg-emerald-500', 'light_bg' => 'bg-emerald-50', 'border' => 'border-emerald-200', 'text' => 'text-emerald-600', 'icon' => 'fa-arrows-up-down'],
            ];

            foreach ($meta as $s => $m):
                $b = $stages[$s];
                $has_img = !empty($b['image_path']);
                $is_active = ($b['status'] ?? 'inactive') === 'active';
                ?>
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm card-hover flex flex-col overflow-hidden">

                    <!-- Color top bar -->
                    <div class="h-1.5 w-full <?= $m['accent_bg'] ?>"></div>

                    <!-- Card header -->
                    <div class="px-5 pt-4 pb-3 flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <div
                                class="<?= $m['light_bg'] ?> <?= $m['border'] ?> border rounded-xl w-9 h-9 flex items-center justify-center <?= $m['text'] ?>">
                                <i class="fa-solid <?= $m['icon'] ?> text-sm"></i>
                            </div>
                            <div>
                                <p class="text-sm font-bold text-gray-800"><?= $m['label'] ?></p>
                                <p class="text-[11px] text-gray-400"><?= $m['pos'] ?></p>
                            </div>
                        </div>
                        <span class="flex items-center gap-1.5 text-[10px] font-semibold px-2.5 py-1 rounded-full
                <?= $is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-400' ?>">
                            <span
                                class="w-1.5 h-1.5 rounded-full <?= $is_active ? 'bg-green-500' : 'bg-gray-400' ?>"></span>
                            <?= $is_active ? 'Active' : 'Inactive' ?>
                        </span>
                    </div>

                    <!-- Image drop zone -->
                    <div class="px-5">
                        <div id="dropZone_<?= $s ?>"
                            class="drop-zone relative w-full h-36 rounded-xl overflow-hidden border-2 border-dashed border-gray-200 bg-gray-50 cursor-pointer group flex items-center justify-center"
                            onclick="document.getElementById('image_<?= $s ?>').click()"
                            ondragover="event.preventDefault(); this.classList.add('dragover')"
                            ondragleave="this.classList.remove('dragover')" ondrop="handleDrop(event, <?= $s ?>)">

                            <img id="preview_<?= $s ?>"
                                src="<?= $has_img ? '../../uploads/' . htmlspecialchars($b['image_path']) : '' ?>"
                                alt="Stage <?= $s ?>"
                                class="absolute inset-0 w-full h-full object-cover <?= $has_img ? '' : 'hidden' ?>">

                            <div
                                class="absolute inset-0 bg-black/0 group-hover:bg-black/25 transition-all z-10 flex items-center justify-center opacity-0 group-hover:opacity-100">
                                <span
                                    class="bg-black/50 text-white text-xs font-semibold px-3 py-1.5 rounded-full flex items-center gap-1.5">
                                    <i class="fa-solid fa-camera"></i> <?= $has_img ? 'Change' : 'Upload' ?> Image
                                </span>
                            </div>

                            <div id="emptyState_<?= $s ?>"
                                class="flex flex-col items-center gap-1.5 z-0 <?= $has_img ? 'hidden' : '' ?>">
                                <i class="fa-solid fa-cloud-arrow-up text-3xl text-gray-200"></i>
                                <p class="text-xs text-gray-300 font-medium">Click or drag & drop</p>
                                <p class="text-[10px] text-gray-200">PNG · JPG · WEBP</p>
                            </div>
                        </div>

                        <p id="fileName_<?= $s ?>" class="text-[10px] text-gray-300 mt-1.5 truncate h-4">
                            <?= $has_img ? htmlspecialchars($b['image_path']) : '' ?>
                        </p>
                    </div>

                    <!-- FORM -->
                    <form method="POST" enctype="multipart/form-data" action="promotion-banner.php"
                        class="px-5 pb-5 pt-2 flex flex-col gap-3 flex-1">
                        <input type="hidden" name="stage" value="<?= $s ?>">
                        <input type="file" name="image_<?= $s ?>" id="image_<?= $s ?>" accept="image/*" class="hidden"
                            onchange="previewImage(event, <?= $s ?>)">

                        <!-- ── TITLE ── -->
                        <div>
                            <label class="text-[11px] font-semibold text-gray-400 uppercase tracking-wide mb-1 block">
                                Banner Title <span class="normal-case font-normal">(optional)</span>
                            </label>
                            <input type="text" name="title" value="<?= htmlspecialchars($b['title'] ?? '') ?>"
                                placeholder="e.g. Summer Sale 2025" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm text-gray-700 outline-none
                              focus:ring-2 focus:ring-yellow-400 bg-white transition placeholder-gray-300">
                        </div>

                        <!-- ── URL ── -->
                        <div>
                            <label class="text-[11px] font-semibold text-gray-400 uppercase tracking-wide mb-1 block">
                                Redirect URL <span class="normal-case font-normal">(optional)</span>
                            </label>
                            <div
                                class="flex items-center border border-gray-200 rounded-xl overflow-hidden focus-within:ring-2 focus-within:ring-yellow-400 bg-white transition">
                                <span class="px-3 py-2.5 text-gray-300 text-xs border-r border-gray-100"><i
                                        class="fa-solid fa-link"></i></span>
                                <input type="text" name="link" value="<?= htmlspecialchars($b['link'] ?? '') ?>"
                                    placeholder="https://"
                                    class="flex-1 px-3 py-2.5 text-sm text-gray-700 outline-none bg-transparent placeholder-gray-300">
                            </div>
                        </div>

                        <!-- ── STATUS TOGGLE ── -->
                        <div class="flex items-center justify-between py-1">
                            <span class="text-[11px] font-semibold text-gray-400 uppercase tracking-wide">Show on
                                Site</span>
                            <div class="flex items-center gap-2">
                                <input type="hidden" name="status" id="statusVal_<?= $s ?>"
                                    value="<?= $is_active ? 'active' : 'inactive' ?>">
                                <button type="button" id="toggleBtn_<?= $s ?>" onclick="toggleStatus(<?= $s ?>)"
                                    class="relative w-11 h-6 rounded-full transition-colors duration-200 focus:outline-none <?= $is_active ? 'bg-green-500' : 'bg-gray-200' ?>">
                                    <span id="toggleThumb_<?= $s ?>"
                                        class="absolute top-1 w-4 h-4 bg-white rounded-full shadow transition-all duration-200 <?= $is_active ? 'left-6' : 'left-1' ?>">
                                    </span>
                                </button>
                                <span id="toggleLabel_<?= $s ?>"
                                    class="text-xs font-medium <?= $is_active ? 'text-green-600' : 'text-gray-400' ?>">
                                    <?= $is_active ? 'Active' : 'Inactive' ?>
                                </span>
                            </div>
                        </div>

                        <!-- ── SAVE BUTTON ── -->
                        <button type="submit"
                            class="w-full py-2.5 rounded-xl text-sm font-bold flex items-center justify-center gap-2 transition
                       <?= $has_img ? $m['accent_bg'] . ' hover:opacity-90 text-white shadow-sm' : 'bg-gray-100 hover:bg-yellow-500 hover:text-white text-gray-500' ?>">
                            <i class="fa-solid <?= $has_img ? 'fa-floppy-disk' : 'fa-plus' ?> text-xs"></i>
                            <?= $has_img ? 'Update Banner' : 'Save Banner' ?>
                        </button>

                        <?php if (!empty($b['updated_at'])): ?>
                            <p class="text-center text-[10px] text-gray-300 -mt-1">
                                <i class="fa-regular fa-clock mr-1"></i><?= date('M d, Y h:i A', strtotime($b['updated_at'])) ?>
                            </p>
                        <?php endif; ?>
                    </form>

                </div>
            <?php endforeach; ?>

        </div><!-- end grid -->
    </div>

    <script>
        function previewImage(e, stage) {
            const file = e.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = ev => {
                const img = document.getElementById('preview_' + stage);
                img.src = ev.target.result;
                img.classList.remove('hidden');
                document.getElementById('emptyState_' + stage).classList.add('hidden');
                document.getElementById('fileName_' + stage).textContent = file.name;
            };
            reader.readAsDataURL(file);
        }

        function handleDrop(e, stage) {
            e.preventDefault();
            e.currentTarget.classList.remove('dragover');
            const file = e.dataTransfer.files[0];
            if (!file || !file.type.startsWith('image/')) return;
            const dt = new DataTransfer();
            dt.items.add(file);
            document.getElementById('image_' + stage).files = dt.files;
            previewImage({ target: { files: [file] } }, stage);
        }

        function toggleStatus(stage) {
            const val = document.getElementById('statusVal_' + stage);
            const btn = document.getElementById('toggleBtn_' + stage);
            const thumb = document.getElementById('toggleThumb_' + stage);
            const lbl = document.getElementById('toggleLabel_' + stage);
            const isOn = val.value === 'active';

            if (isOn) {
                val.value = 'inactive';
                btn.classList.replace('bg-green-500', 'bg-gray-200');
                thumb.classList.replace('left-6', 'left-1');
                lbl.textContent = 'Inactive';
                lbl.className = 'text-xs font-medium text-gray-400';
            } else {
                val.value = 'active';
                btn.classList.replace('bg-gray-200', 'bg-green-500');
                thumb.classList.replace('left-1', 'left-6');
                lbl.textContent = 'Active';
                lbl.className = 'text-xs font-medium text-green-600';
            }
        }
    </script>
</body>

</html>