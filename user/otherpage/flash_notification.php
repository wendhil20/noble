<?php if (isset($_SESSION['register_success']) || isset($_SESSION['register_error'])): ?>
  <div
    x-data="{ show: true }"
    x-init="setTimeout(() => show = false, 4000)"
    x-show="show"
    x-transition
    class="fixed top-4 left-1/2 transform -translate-x-1/2 z-50 px-5 py-3 rounded-lg shadow-lg
      <?= isset($_SESSION['register_success']) ? 'bg-green-500 text-white' : 'bg-red-500 text-white' ?>">
    
    <p class="text-sm font-semibold">
      <?= $_SESSION['register_success'] ?? $_SESSION['register_error'] ?>
    </p>
  </div>
  <?php
    unset($_SESSION['register_success']);
    unset($_SESSION['register_error']);
  ?>
<?php endif; ?>
