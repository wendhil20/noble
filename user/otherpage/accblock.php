<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>3D AAC Block</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    body, html {
      margin: 0;
      padding: 0;
    }
    canvas {
      width: 100%;
      height: 400px;
      display: block;
      background: #f3f4f6;
      border-radius: 0.5rem;
    }
  </style>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">

  <div class="bg-white p-6 rounded-lg shadow w-full space-y-6">
    <h2 class="text-xl font-bold text-center text-orange-600">3D AAC Block Preview</h2>

    <!-- Inputs -->
    <div class="grid grid-cols-3 gap-4 text-sm">
      <div>
        <label>Length (mm)</label>
        <input id="length" type="number" value="600" class="w-full border rounded px-2 py-1">
      </div>
      <div>
        <label>Height (mm)</label>
        <input id="height" type="number" value="300" class="w-full border rounded px-2 py-1">
      </div>
      <div>
        <label>Thickness (mm)</label>
        <input id="thickness" type="number" value="100" class="w-full border rounded px-2 py-1">
      </div>
    </div>

    <!-- Canvas -->
    <div class="border border-gray-300 rounded">
      <canvas id="threeCanvas"></canvas>
    </div>

    <p class="text-center text-xs text-gray-500">Drag to rotate. Scroll to zoom. Dimensions are scaled to meters.</p>
  </div>

  <!-- Three.js + OrbitControls using ES Modules -->
  <script type="module">

import * as THREE from 'https://esm.sh/three@0.158.0';
import { OrbitControls } from 'https://esm.sh/three@0.158.0/examples/jsm/controls/OrbitControls.js';

const textures = {
  right: "data:image/jpeg;base64,<?= base64_encode($block['side_right']) ?>",
  left: "data:image/jpeg;base64,<?= base64_encode($block['side_left']) ?>",
  top: "data:image/jpeg;base64,<?= base64_encode($block['side_top']) ?>",
  bottom: "data:image/jpeg;base64,<?= base64_encode($block['side_bottom']) ?>",
  front: "data:image/jpeg;base64,<?= base64_encode($block['side_front']) ?>",
  back: "data:image/jpeg;base64,<?= base64_encode($block['side_back']) ?>"
};

const loader = new THREE.TextureLoader();
const materials = [
  new THREE.MeshStandardMaterial({ map: loader.load(textures.right) }),
  new THREE.MeshStandardMaterial({ map: loader.load(textures.left) }),
  new THREE.MeshStandardMaterial({ map: loader.load(textures.top) }),
  new THREE.MeshStandardMaterial({ map: loader.load(textures.bottom) }),
  new THREE.MeshStandardMaterial({ map: loader.load(textures.front) }),
  new THREE.MeshStandardMaterial({ map: loader.load(textures.back) })
];

const canvas = document.getElementById('threeCanvas');
const renderer = new THREE.WebGLRenderer({ canvas, antialias: true });
renderer.setSize(canvas.clientWidth, canvas.clientHeight);
renderer.setPixelRatio(window.devicePixelRatio);

const scene = new THREE.Scene();
scene.background = new THREE.Color(0xffffff);

const camera = new THREE.PerspectiveCamera(45, canvas.clientWidth / canvas.clientHeight, 0.1, 100);
camera.position.set(2, 2, 3);

const controls = new OrbitControls(camera, renderer.domElement);
controls.enableDamping = true;

scene.add(new THREE.AmbientLight(0xffffff, 0.8));
const dirLight = new THREE.DirectionalLight(0xffffff, 0.5);
dirLight.position.set(5, 5, 5);
scene.add(dirLight);

const material = new THREE.MeshStandardMaterial({ color: 0xffffff });
let blockMesh, edgesHelper;

const mmToMeters = mm => mm / 1000;

function createBlock(length, height, thickness) {
  if (blockMesh) scene.remove(blockMesh);
  if (edgesHelper) scene.remove(edgesHelper);

  const geometry = new THREE.BoxGeometry(
    mmToMeters(length),
    mmToMeters(height),
    mmToMeters(thickness)
  );

  blockMesh = new THREE.Mesh(geometry, material);
  scene.add(blockMesh);

  // ✅ Add outline
  const edges = new THREE.EdgesGeometry(geometry);
  const lineMaterial = new THREE.LineBasicMaterial({ color: 0x000000 }); // Black lines
  edgesHelper = new THREE.LineSegments(edges, lineMaterial);
  scene.add(edgesHelper);
}

function updateFromInputs() {
  const length = parseFloat(document.getElementById('length').value) || 600;
  const height = parseFloat(document.getElementById('height').value) || 300;
  const thickness = parseFloat(document.getElementById('thickness').value) || 100;
  createBlock(length, height, thickness);
}

document.querySelectorAll('#length, #height, #thickness').forEach(input => {
  input.addEventListener('input', updateFromInputs);
});

updateFromInputs();

function animate() {
  requestAnimationFrame(animate);
  controls.update();
  renderer.render(scene, camera);
}

animate();

  </script>
</body>
</html>
