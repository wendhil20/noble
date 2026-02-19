<?php
include '../../connection/connect.php';
$side = $_GET['side'] ?? '';
$id = (int) ($_GET['id'] ?? 0);
$allowed = ['front', 'back', 'left', 'right', 'top', 'bottom'];

if (!in_array($side, $allowed)) exit;

$stmt = $conn->prepare("SELECT side_$side FROM blocks WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->bind_result($image);
$stmt->fetch();
$stmt->close();

header("Content-Type: image/jpeg");
echo $image ?: base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR4nGNgYAAAAAMAASsJTYQAAAAASUVORK5CYII=');
