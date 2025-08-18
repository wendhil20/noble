<?php
include '../../connection/connect.php';

$block_name = $_POST['block_name'];
$sides = ['right', 'left', 'top', 'bottom', 'front', 'back'];
$uploads = [];

foreach ($sides as $side) {
  if (isset($_FILES["side_$side"])) {
    $imageData = file_get_contents($_FILES["side_$side"]["tmp_name"]);
    $uploads[$side] = $imageData;
  } else {
    $uploads[$side] = null;
  }
}

// Insert into database
$stmt = $conn->prepare("INSERT INTO blocks (name, side_right, side_left, side_top, side_bottom, side_front, side_back) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param(
  "sssssss",
  $block_name,
  $uploads['right'],
  $uploads['left'],
  $uploads['top'],
  $uploads['bottom'],
  $uploads['front'],
  $uploads['back']
);
$stmt->execute();
$stmt->close();

echo "<script>alert('Block textures saved!'); window.location.href='3d_viewer.php';</script>";
