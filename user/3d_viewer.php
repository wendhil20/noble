<?php
include '../connection/connect.php';

// Get all blocks (for selection)
$blocks_result = $conn->query("SELECT id, name FROM blocks");
$blocks = [];
while ($row = $blocks_result->fetch_assoc()) {
  $blocks[] = $row;
}

function getImageBase64($conn, $block_id, $side_column)
{
  $stmt = $conn->prepare("SELECT $side_column FROM blocks WHERE id = ?");
  $stmt->bind_param("i", $block_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $row = $result->fetch_assoc();
  $stmt->close();

  if (empty($row) || empty($row[$side_column])) {
    // Return 1x1 transparent pixel as fallback
    return 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR4nGNgYAAAAAMAASsJTYQAAAAASUVORK5CYII=';
  }

  return base64_encode($row[$side_column]);
}

// Sides
$sides = ['front', 'back', 'left', 'right', 'top', 'bottom'];
$selected = [];
$base64 = [];

foreach ($sides as $side) {
  $selected[$side] = isset($_GET[$side]) ? (int)$_GET[$side] : ($blocks[0]['id'] ?? 0);
  $base64[$side] = getImageBase64($conn, $selected[$side], "side_$side");
}

// Default dimensions with validation
$length = isset($_GET['length']) ? max(100, min(2000, (int)$_GET['length'])) : 600;
$height = isset($_GET['height']) ? max(100, min(2000, (int)$_GET['height'])) : 300;
$thickness = isset($_GET['thickness']) ? max(50, min(1000, (int)$_GET['thickness'])) : 100;
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Customize Block Sides</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- Swiper CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />

  <!-- Swiper JS -->
  <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

  <style>
    canvas {
      width: 100%;
      height: 400px;
      background: #f3f4f6;
      border-radius: 8px;
      display: block;
      max-width: 100%;
    }

    .block-option.selected {
      border-color: #f97316 !important;
      box-shadow: 0 0 10px rgba(249, 115, 22, 0.3);
    }
  </style>
</head>

<body class="bg-gray-100 min-h-screen">
  
<?php include 'navbar/top.php'; ?>

  <div class="bg-white p-6 rounded-lg shadow grid grid-cols-1 md:grid-cols-4 gap-6">

    <!-- LEFT COLUMN: Side Selectors -->
    <div class="md:col-span-1 space-y-6">
      <h2 class="text-lg font-bold text-orange-600">Select Texture</h2>

      <?php foreach ($sides as $side): ?>
        <div>
          <p class="text-sm font-semibold mb-2 capitalize"><?= $side ?> Side</p>

          <!-- Swiper container -->
          <div class="swiper swiper-container-<?= $side ?>" id="container_<?= $side ?>">
            <div class="swiper-wrapper">
              <?php foreach ($blocks as $block): ?>
                <?php
                $imgData = getImageBase64($conn, $block['id'], "side_$side");
                $isSelected = $selected[$side] == $block['id'];
                ?>
                <div class="swiper-slide">
                  <div
                    class="block-option cursor-pointer border <?= $isSelected ? 'border-orange-500 selected' : 'border-gray-300' ?> rounded p-1 hover:border-orange-500 transition"
                    data-side="<?= $side ?>"
                    data-id="<?= $block['id'] ?>"
                    onclick="selectBlock(this)">
                    <img src="data:image/jpeg;base64,<?= $imgData ?>" class="w-full h-20 object-contain rounded mb-1" loading="lazy">
                    <p class="text-xs text-center truncate"><?= htmlspecialchars($block['name']) ?></p>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>

            <!-- Optional: navigation buttons -->
            <div class="swiper-button-prev swiper-button-prev-<?= $side ?>"></div>
            <div class="swiper-button-next swiper-button-next-<?= $side ?>"></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>


    <!-- RIGHT COLUMN: 3D Viewer + Inputs -->
    <div class="md:col-span-3 space-y-4">
      <h2 class="text-xl font-bold text-center text-gray-800">3D ACC Block & FIBER CEMENT BOARD(ECOFLEX)</h2>

      <div class="grid grid-cols-3 gap-4 text-sm">
        <div>
          <label class="block text-gray-700 font-medium mb-1">Length (mm)</label>
          <input id="lengthInput" name="length" type="number" min="100" max="2000" value="<?= htmlspecialchars($length) ?>" class="w-full border rounded px-2 py-1" onchange="updateDimensions()">
        </div>
        <div>
          <label class="block text-gray-700 font-medium mb-1">Height (mm)</label>
          <input id="heightInput" name="height" type="number" min="100" max="2000" value="<?= htmlspecialchars($height) ?>" class="w-full border rounded px-2 py-1" onchange="updateDimensions()">
        </div>
        <div>
          <label class="block text-gray-700 font-medium mb-1">Thickness (mm)</label>
          <input id="thicknessInput" name="thickness" type="number" min="50" max="1000" value="<?= htmlspecialchars($thickness) ?>" class="w-full border rounded px-2 py-1" onchange="updateDimensions()">
        </div>
      </div>

      <div id="canvasContainer" class="relative">
        <canvas id="threeCanvas" class="w-full h-96 bg-gray-200 rounded"></canvas>
        <div id="loadingIndicator" class="absolute inset-0 flex items-center justify-center bg-gray-200 bg-opacity-75 rounded hidden">
          <div class="text-gray-600">Loading...</div>
        </div>
      </div>
      <p class="text-xs text-center text-gray-500">Each face uses a different block texture. Drag to rotate, scroll to zoom.</p>

      
    </div>
  </div>

  <script type="module">
    document.addEventListener('DOMContentLoaded', () => {
      const sides = <?= json_encode($sides) ?>;

      sides.forEach(side => {
        new Swiper(`.swiper-container-${side}`, {
          slidesPerView: 2,
          spaceBetween: 10,
          navigation: {
            nextEl: `.swiper-button-next-${side}`,
            prevEl: `.swiper-button-prev-${side}`
          },
          autoplay: false, // not auto-play
        });
      });
    });

    import * as THREE from 'https://cdn.skypack.dev/three@0.132.2';
    import {
      OrbitControls
    } from 'https://cdn.skypack.dev/three@0.132.2/examples/jsm/controls/OrbitControls.js';

    // Global variables
    let scene, camera, renderer, controls, box, materialMap = {};
    let isInitialized = false;

    const sideToIndex = {
      right: 0,
      left: 1,
      top: 2,
      bottom: 3,
      front: 4,
      back: 5
    };

    // Default textures from PHP (used only initially)
    const textures = {
      right: "data:image/jpeg;base64,<?= $base64['right'] ?>",
      left: "data:image/jpeg;base64,<?= $base64['left'] ?>",
      top: "data:image/jpeg;base64,<?= $base64['top'] ?>",
      bottom: "data:image/jpeg;base64,<?= $base64['bottom'] ?>",
      front: "data:image/jpeg;base64,<?= $base64['front'] ?>",
      back: "data:image/jpeg;base64,<?= $base64['back'] ?>"
    };

    function showLoading() {
      document.getElementById('loadingIndicator').classList.remove('hidden');
    }

    function hideLoading() {
      document.getElementById('loadingIndicator').classList.add('hidden');
    }

    function initThreeJS() {
      const canvas = document.getElementById('threeCanvas');
      if (!canvas) {
        console.error('Canvas element not found');
        return false;
      }

      try {
        renderer = new THREE.WebGLRenderer({
          canvas: canvas,
          antialias: true,
          alpha: true
        });

        const rect = canvas.getBoundingClientRect();
        renderer.setSize(rect.width, rect.height);
        renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));

        scene = new THREE.Scene();
        scene.background = new THREE.Color(0xf8f9fa);

        camera = new THREE.PerspectiveCamera(20, rect.width / rect.height, 0.1, 100);
        camera.position.set(2, 2, 3);

        controls = new OrbitControls(camera, renderer.domElement);
        controls.enableDamping = true;
        controls.dampingFactor = 0.05;
        controls.enableZoom = true;
        controls.enablePan = true;

        scene.add(new THREE.AmbientLight(0xffffff, 0.6));
        const dirLight1 = new THREE.DirectionalLight(0xffffff, 0.4);
        dirLight1.position.set(5, 5, 5);
        scene.add(dirLight1);

        const dirLight2 = new THREE.DirectionalLight(0xffffff, 0.2);
        dirLight2.position.set(-5, -5, -5);
        scene.add(dirLight2);

        createBox();
        restoreSelections(); // Restore previous selections

        isInitialized = true;
        return true;
      } catch (error) {
        console.error('Failed to initialize Three.js:', error);
        return false;
      }
    }

    function createBox() {
      if (box) {
        scene.remove(box);
        box.geometry.dispose();
        box.material.forEach(mat => mat.dispose());
      }

      const loader = new THREE.TextureLoader();
      const materials = [];

      const sideOrder = ['right', 'left', 'top', 'bottom', 'front', 'back'];
      sideOrder.forEach(side => {
        const texture = loader.load(textures[side]);
        texture.wrapS = THREE.RepeatWrapping;
        texture.wrapT = THREE.RepeatWrapping;
        const material = new THREE.MeshStandardMaterial({
          map: texture
        });
        materials.push(material);
        materialMap[side] = material;
      });

      const L = parseFloat(document.getElementById('lengthInput').value) / 1000;
      const H = parseFloat(document.getElementById('heightInput').value) / 1000;
      const T = parseFloat(document.getElementById('thicknessInput').value) / 1000;

      const geometry = new THREE.BoxGeometry(L, H, T);
      box = new THREE.Mesh(geometry, materials);
      scene.add(box);
    }

    function restoreSelections() {
      const savedSelections = JSON.parse(localStorage.getItem("blockSelections") || "{}");

      Object.keys(savedSelections).forEach(side => {
        const id = savedSelections[side];
        const imageUrl = `customise/fetch_block_image.php?side=${side}&id=${id}`;
        const loader = new THREE.TextureLoader();

        loader.load(
          imageUrl,
          (texture) => {
            texture.wrapS = THREE.RepeatWrapping;
            texture.wrapT = THREE.RepeatWrapping;

            const newMat = new THREE.MeshStandardMaterial({
              map: texture
            });
            const index = sideToIndex[side];

            if (box && box.material[index]) {
              box.material[index].dispose();
              box.material[index] = newMat;
              materialMap[side] = newMat;
            }

            // Highlight the selected block visually
            const container = document.getElementById("container_" + side);
            if (container) {
              const el = container.querySelector(`[data-id="${id}"]`);
              if (el) {
                container.querySelectorAll(".block-option").forEach(blockEl => {
                  blockEl.classList.remove("border-orange-500", "selected");
                  blockEl.classList.add("border-gray-300");
                });
                el.classList.remove("border-gray-300");
                el.classList.add("border-orange-500", "selected");
              }
            }
          },
          undefined,
          (error) => console.error(`Failed to restore texture for ${side}`, error)
        );
      });
    }

    function animate() {
      if (!isInitialized) return;
      requestAnimationFrame(animate);
      controls.update();
      renderer.render(scene, camera);
    }

    function handleResize() {
      if (!isInitialized) return;

      const canvas = document.getElementById('threeCanvas');
      const rect = canvas.getBoundingClientRect();

      camera.aspect = rect.width / rect.height;
      camera.updateProjectionMatrix();
      renderer.setSize(rect.width, rect.height);
    }

    // Save selection and update face
    window.selectBlock = function(element) {
      const side = element.getAttribute("data-side");
      const id = element.getAttribute("data-id");

      // Save selected block to localStorage
      const savedSelections = JSON.parse(localStorage.getItem("blockSelections") || "{}");
      savedSelections[side] = id;
      localStorage.setItem("blockSelections", JSON.stringify(savedSelections));

      showLoading();

      const container = document.getElementById("container_" + side);
      container.querySelectorAll(".block-option").forEach(el => {
        el.classList.remove("border-orange-500", "selected");
        el.classList.add("border-gray-300");
      });
      element.classList.remove("border-gray-300");
      element.classList.add("border-orange-500", "selected");

      const imageUrl = `customise/fetch_block_image.php?side=${side}&id=${id}`;
      const loader = new THREE.TextureLoader();

      loader.load(
        imageUrl,
        (texture) => {
          texture.wrapS = THREE.RepeatWrapping;
          texture.wrapT = THREE.RepeatWrapping;

          const newMat = new THREE.MeshStandardMaterial({
            map: texture
          });
          const index = sideToIndex[side];

          if (box && box.material[index]) {
            box.material[index].dispose();
            box.material[index] = newMat;
            materialMap[side] = newMat;
          }

          hideLoading();
        },
        undefined,
        (error) => {
          console.error('Error loading texture:', error);
          hideLoading();
        }
      );
    };

    window.updateDimensions = function() {
      if (!isInitialized) return;

      showLoading();

      setTimeout(() => {
        createBox();
        restoreSelections(); // reapply textures after resizing box
        hideLoading();
      }, 100);
    };

    // Event listeners
    window.addEventListener('resize', handleResize);
    window.addEventListener('load', () => {
      if (initThreeJS()) {
        animate();
        hideLoading();
      } else {
        document.getElementById('threeCanvas').innerHTML = '<div class="flex items-center justify-center h-full text-red-500">Failed to initialize 3D viewer</div>';
      }
    });

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', () => {
        if (initThreeJS()) {
          animate();
          hideLoading();
        }
      });
    } else {
      if (initThreeJS()) {
        animate();
        hideLoading();
      }
    }
  </script>

</body>

</html>