<?php
include '../../connection/connect.php';

$orderId = $_GET['id'] ?? 0;

$res = $conn->query("SELECT id, customer_name, address, latitude, longitude FROM orders WHERE id = $orderId");
$order = $res->fetch_assoc();

if (!$order) {
    echo "Order not found."; exit;
}

if (!$order['latitude'] || !$order['longitude']) {
    $encodedAddress = urlencode($order['address']);
    $url = "https://nominatim.openstreetmap.org/search?format=json&q=$encodedAddress";

    $opts = ['http' => ['header' => "User-Agent: noblehome-admin"]];
    $context = stream_context_create($opts);
    $json = file_get_contents($url, false, $context);
    $data = json_decode($json, true);

    if (!empty($data)) {
        $lat = $data[0]['lat'];
        $lng = $data[0]['lon'];
        $stmt = $conn->prepare("UPDATE orders SET latitude = ?, longitude = ? WHERE id = ?");
        $stmt->bind_param("ddi", $lat, $lng, $orderId);
        $stmt->execute();
    } else {
        echo "Address could not be geocoded."; exit;
    }
} else {
    $lat = $order['latitude'];
    $lng = $order['longitude'];
}

$originLat = 14.6571315;
$originLng = 121.0033292;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Order #<?= $orderId ?> Location</title>

  <!-- TailwindCSS CDN -->
  <script src="https://cdn.tailwindcss.com"></script>

  <!-- Leaflet CSS + JS -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

  <!-- Leaflet Routing Machine -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@latest/dist/leaflet-routing-machine.css" />
  <script src="https://unpkg.com/leaflet-routing-machine@latest/dist/leaflet-routing-machine.js"></script>
</head>
<body class="bg-gray-100 font-sans">

  <div class=" py-6 px-4">
    <div class="mb-6">
      <a href="javascript:history.back()" class="text-sm text-blue-600 hover:underline">
        ← Back to Orders
      </a>
    </div>

    <div class="bg-white shadow-xl rounded-2xl overflow-hidden">
      <div class="p-6 border-b">
        <h2 class="text-2xl font-semibold text-gray-800">
           Order #<?= $orderId ?> Route for <span class="text-orange-600"><?= htmlspecialchars($order['customer_name']) ?></span>
        </h2>
        <p class="text-gray-500 mt-1">From MC Premiere ➜ <?= htmlspecialchars($order['address']) ?></p>
      </div>

      <div id="map" class="h-[80vh] w-full"></div>
    </div>
  </div>

  <script>
    const map = L.map('map').setView([<?= $originLat ?>, <?= $originLng ?>], 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: 'Map data © OpenStreetMap contributors'
    }).addTo(map);

    L.Routing.control({
      waypoints: [
        L.latLng(<?= $originLat ?>, <?= $originLng ?>),
        L.latLng(<?= $lat ?>, <?= $lng ?>)
      ],
      routeWhileDragging: false,
      draggableWaypoints: false,
      addWaypoints: false,
      fitSelectedRoutes: true,
      createMarker: function(i, wp) {
        return L.marker(wp.latLng).bindPopup(
          i === 0
            ? "MC Premiere<br>1181 EDSA, Balintawak"
            : "Customer<br><?= htmlspecialchars($order['address']) ?>"
        );
      },
      lineOptions: {
        styles: [{ color: 'blue', weight: 5 }]
      }
    }).addTo(map);
  </script>

</body>
</html>
