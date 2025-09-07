<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Esri Leaflet Geosearch Example</title>
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
  <link rel="stylesheet" href="https://unpkg.com/esri-leaflet-geocoder@3.1.6/dist/esri-leaflet-geocoder.css" crossorigin="">
  <style>
    #map { height: 400px; }
  </style>
</head>
<body>
  <div id="map"></div>
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
  <script src="https://unpkg.com/esri-leaflet@3.0.19/dist/esri-leaflet.js" crossorigin=""></script>
  <script src="https://unpkg.com/esri-leaflet-geocoder@3.1.6/dist/esri-leaflet-geocoder.js" crossorigin=""></script>
  <script>
    var map = L.map('map').setView([14.6, 121.0], 12);

    // Add basemap
    L.esri.basemapLayer('Topographic').addTo(map);

    // Add geosearch control
    var searchControl = L.esri.Geocoding.geosearch().addTo(map);

    var results = L.layerGroup().addTo(map);

    searchControl.on('results', function(data) {
      results.clearLayers();
      data.results.forEach(function(result) {
        results.addLayer(L.marker(result.latlng));
      });
    });
  </script>
</body>
</html>
