<?php
include '../connection/connect.php';

function geocode($address) {
    $url = "https://nominatim.openstreetmap.org/search?format=json&q=" . urlencode($address);
    $opts = ['http' => ['header' => "User-Agent: noblehome-tracker"]];
    $context = stream_context_create($opts);

    $json = file_get_contents($url, false, $context);
    $data = json_decode($json, true);

    if (!empty($data)) {
        return [$data[0]['lat'], $data[0]['lon']];
    }
    return [null, null];
}

$res = $conn->query("SELECT id, address FROM orders WHERE latitude IS NULL OR longitude IS NULL");

while ($row = $res->fetch_assoc()) {
    $id = $row['id'];
    $address = $row['address'];

    list($lat, $lng) = geocode($address);

    if ($lat && $lng) {
        $stmt = $conn->prepare("UPDATE orders SET latitude = ?, longitude = ? WHERE id = ?");
        $stmt->bind_param("ddi", $lat, $lng, $id);
        $stmt->execute();
        echo "Order #$id updated ✔ ($lat, $lng)<br>";
    } else {
        echo "Order #$id ❌ Failed to locate address: $address<br>";
    }

    sleep(1); // Respect Nominatim limit
}
?>
