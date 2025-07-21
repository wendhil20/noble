<?php
include '../../connection/connect.php';

$result = $conn->query("SELECT id, customer_name, address, latitude, longitude FROM orders WHERE latitude IS NOT NULL AND longitude IS NOT NULL");

$orders = [];
while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Order Locations</title>
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
</head>
<body style="margin:0;">
  <h2 style="padding:1rem;">📍 All Customer Locations</h2>
  <div id="map" style="height: 90vh;"></div>

  <script>
    const orders = <?= json_encode($orders) ?>;
    const map = L.map('map').setView([14.5995, 120.9842], 11); // Centered on Manila

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: 'Map data © OpenStreetMap contributors'
    }).addTo(map);

    orders.forEach(order => {
      const marker = L.marker([order.latitude, order.longitude]).addTo(map);
      marker.bindPopup(`<b>Order #${order.id}</b><br>${order.customer_name}<br>${order.address}`);
    });
  </script>
</body>
</html>
