<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Philippines Distance Calculator - CesiumJS</title>
  <script src="https://cesium.com/downloads/cesiumjs/releases/1.120/Build/Cesium/Cesium.js"></script>
  <link href="https://cesium.com/downloads/cesiumjs/releases/1.120/Build/Cesium/Widgets/widgets.css" rel="stylesheet">
  <style>
    html, body, #cesiumContainer {
      width: 100%;
      height: 100%;
      margin: 0;
      padding: 0;
      overflow: hidden;
    }
  </style>
</head>
<body>
  <div id="cesiumContainer"></div>

  <script>
    // ✅ Your Cesium Ion token
    Cesium.Ion.defaultAccessToken = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJqdGkiOiI4OGVmMDVhYS02Mzk2LTQ4ZDgtOTIxMS0xYmZlNGExM2E2MjUiLCJpZCI6MzI1NTI2LCJpYXQiOjE3NTM2ODc0MTZ9.oLP-QrmmxpZYUV_wtCUB4lEoYLeMmBGE6sVuumkku2w';

    // ✅ Cesium Viewer Setup
    const viewer = new Cesium.Viewer('cesiumContainer', {
      terrain: Cesium.Terrain.fromWorldTerrain(),
      baseLayerPicker: false,
      timeline: false,
      animation: false
    });

    // ✅ Focus camera on the Philippines area
    viewer.camera.setView({
      destination: Cesium.Rectangle.fromDegrees(116.0, 4.5, 127.0, 21.5)
    });

    // ✅ Track start and end clicks
    let startPoint = null;

    // ✅ Mouse click handler
    viewer.screenSpaceEventHandler.setInputAction(function (click) {
      const cartesian = viewer.scene.pickPosition(click.position);

      if (Cesium.defined(cartesian)) {
        const cartographic = Cesium.Cartographic.fromCartesian(cartesian);
        const longitude = Cesium.Math.toDegrees(cartographic.longitude);
        const latitude = Cesium.Math.toDegrees(cartographic.latitude);

        if (!startPoint) {
          startPoint = { lat: latitude, lon: longitude };

          // Add Start Marker
          viewer.entities.add({
            position: Cesium.Cartesian3.fromDegrees(longitude, latitude),
            point: { pixelSize: 10, color: Cesium.Color.GREEN },
            label: {
              text: "Start",
              font: '14px sans-serif',
              fillColor: Cesium.Color.WHITE,
              style: Cesium.LabelStyle.FILL_AND_OUTLINE
            }
          });

          alert("✅ Start point set. Now click destination.");
        } else {
          const endPoint = { lat: latitude, lon: longitude };

          // Add End Marker
          viewer.entities.add({
            position: Cesium.Cartesian3.fromDegrees(longitude, latitude),
            point: { pixelSize: 10, color: Cesium.Color.RED },
            label: {
              text: "End",
              font: '14px sans-serif',
              fillColor: Cesium.Color.WHITE,
              style: Cesium.LabelStyle.FILL_AND_OUTLINE
            }
          });

          const distanceKm = computeDistance(startPoint, endPoint);
          const ratePerKm = 10; // Example ₱10 per km
          const shippingCost = distanceKm * ratePerKm;

          alert(`📍 Distance: ${distanceKm.toFixed(2)} km\n💰 Shipping Fee: ₱${shippingCost.toFixed(2)}`);

          // Reset
          startPoint = null;
        }
      }
    }, Cesium.ScreenSpaceEventType.LEFT_CLICK);

    // ✅ Haversine formula for distance
    function computeDistance(p1, p2) {
      const R = 6371; // Radius of Earth in km
      const dLat = Cesium.Math.toRadians(p2.lat - p1.lat);
      const dLon = Cesium.Math.toRadians(p2.lon - p1.lon);
      const a =
        Math.sin(dLat / 2) ** 2 +
        Math.cos(Cesium.Math.toRadians(p1.lat)) *
        Math.cos(Cesium.Math.toRadians(p2.lat)) *
        Math.sin(dLon / 2) ** 2;
      const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
      return R * c;
    }
  </script>
</body>
</html>
