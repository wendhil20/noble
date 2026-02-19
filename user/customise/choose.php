<?php
include '../../connection/connect.php';
$blocks = $conn->query("SELECT id, name FROM blocks");
?>

<h2>Select a Block to View</h2>
<form method="GET" action="3d_viewer.php">
  <select name="id" required>
    <option value="">-- Select a Block --</option>
    <?php while($row = $blocks->fetch_assoc()): ?>
      <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['name']) ?></option>
    <?php endwhile; ?>
  </select>
  <button type="submit">View Block</button>
</form>
