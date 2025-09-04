<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>PH Address Selector with Street + Map</title>
  <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
  <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
  <style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    select, input { padding: 5px; margin: 5px 0; width: 250px; }
    #map { height: 400px; margin-top: 20px; border: 2px solid #ccc; }
    #suggestions { list-style: none; padding: 0; margin: 5px 0; 
                   max-height: 150px; overflow: auto; 
                   border: 1px solid #ccc; width: 250px; background: #fff; position: absolute; z-index: 1000; }
    #suggestions li { padding: 5px; cursor: pointer; }
    #suggestions li:hover { background: #eee; }
  </style>
</head>
<body>

  <h2>Philippines Address Selector with Street Search</h2>

  <label>Region:</label>
  <select id="region">
    <option value="">Select Region</option>
  </select><br>

  <label id="provinceLabel">Province:</label>
  <select id="province">
    <option value="">Select Province</option>
  </select><br>

  <label>City/Municipality:</label>
  <select id="city">
    <option value="">Select City</option>
  </select><br>

  <label>Barangay:</label>
  <select id="barangay">
    <option value="">Select Barangay</option>
  </select><br>

  <label>Street / Landmark:</label>
  <input type="text" id="street" placeholder="Enter street or landmark">
  <ul id="suggestions"></ul>

  <div id="map"></div>

  <script>
    // Initialize Leaflet map
    var map = L.map('map').setView([14.5995, 120.9842], 11); // Default Manila
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19
    }).addTo(map);

    let regionSelect = document.getElementById("region");
    let provinceSelect = document.getElementById("province");
    let citySelect = document.getElementById("city");
    let barangaySelect = document.getElementById("barangay");
    let provinceLabel = document.getElementById("provinceLabel");
    let streetInput = document.getElementById("street");
    let suggestions = document.getElementById("suggestions");

    // Load Regions
    async function loadRegions() {
      const res = await fetch("https://psgc.gitlab.io/api/regions/");
      const regions = await res.json();
      regions.forEach(r => {
        regionSelect.innerHTML += `<option value="${r.code}">${r.name}</option>`;
      });
    }

    // Load Provinces or handle NCR
    async function loadProvinces(regionCode) {
      provinceSelect.innerHTML = "<option value=''>Select Province</option>";
      citySelect.innerHTML = "<option value=''>Select City</option>";
      barangaySelect.innerHTML = "<option value=''>Select Barangay</option>";

      if (regionCode === "130000000") { // NCR
        provinceLabel.style.display = "none";
        provinceSelect.style.display = "none";
        loadCities(regionCode, true); // Load NCR cities directly
      } else {
        provinceLabel.style.display = "inline";
        provinceSelect.style.display = "inline";
        const res = await fetch(`https://psgc.gitlab.io/api/regions/${regionCode}/provinces/`);
        const provinces = await res.json();
        provinces.forEach(p => {
          provinceSelect.innerHTML += `<option value="${p.code}">${p.name}</option>`;
        });
      }
    }

    // Load Cities
    async function loadCities(parentCode, isNCR = false) {
      citySelect.innerHTML = "<option value=''>Select City</option>";
      barangaySelect.innerHTML = "<option value=''>Select Barangay</option>";

      let url = isNCR 
        ? `https://psgc.gitlab.io/api/regions/${parentCode}/cities-municipalities/`
        : `https://psgc.gitlab.io/api/provinces/${parentCode}/cities-municipalities/`;

      const res = await fetch(url);
      const cities = await res.json();
      cities.forEach(c => {
        citySelect.innerHTML += `<option value="${c.code}">${c.name}</option>`;
      });
    }

    // Load Barangays
    async function loadBarangays(cityCode) {
      barangaySelect.innerHTML = "<option value=''>Select Barangay</option>";
      const res = await fetch(`https://psgc.gitlab.io/api/cities-municipalities/${cityCode}/barangays/`);
      const brgys = await res.json();
      brgys.forEach(b => {
        barangaySelect.innerHTML += `<option value="${b.code}">${b.name}</option>`;
      });
    }

    // Redirect map using Photon API
    async function redirectToPlace(query) {
      let url = `https://photon.komoot.io/api/?q=${encodeURIComponent(query)}&limit=1`;
      const res = await fetch(url);
      const data = await res.json();

      if (data.features && data.features.length > 0) {
        let coords = data.features[0].geometry.coordinates;
        let lng = coords[0], lat = coords[1];
        map.setView([lat, lng], 18);
        L.marker([lat, lng]).addTo(map).bindPopup(query).openPopup();
      } else {
        alert("Walang nahanap na coordinates para sa: " + query);
      }
    }

    // Event Listeners
    regionSelect.addEventListener("change", function() {
      loadProvinces(this.value);
    });

    provinceSelect.addEventListener("change", function() {
      loadCities(this.value, false);
    });

    citySelect.addEventListener("change", function() {
      loadBarangays(this.value);
    });

    barangaySelect.addEventListener("change", function() {
      let barangay = this.options[this.selectedIndex].text;
      let city = citySelect.options[citySelect.selectedIndex]?.text || "";
      let province = provinceSelect.style.display === "none" ? "" : provinceSelect.options[provinceSelect.selectedIndex]?.text || "";
      let region = regionSelect.options[regionSelect.selectedIndex]?.text || "";

      let fullAddress = `${barangay}, ${city}, ${province}, ${region}, Philippines`;
      redirectToPlace(fullAddress);
    });

    // Autocomplete Street Search
    async function searchStreet(query) {
      if (query.length < 3) {
        suggestions.innerHTML = "";
        return;
      }

      let url = `https://photon.komoot.io/api/?q=${encodeURIComponent(query)} philippines&limit=5`;
      const res = await fetch(url);
      const data = await res.json();

      suggestions.innerHTML = "";
      data.features.forEach(place => {
        let name = place.properties.name || "";
        let city = place.properties.city || "";
        let province = place.properties.state || "";
        let fullText = `${name}, ${city}, ${province}`;

        let li = document.createElement("li");
        li.textContent = fullText;
        li.addEventListener("click", () => {
          let coords = place.geometry.coordinates;
          let lng = coords[0], lat = coords[1];
          map.setView([lat, lng], 18);
          L.marker([lat, lng]).addTo(map).bindPopup(fullText).openPopup();

          streetInput.value = fullText;
          suggestions.innerHTML = "";
        });
        suggestions.appendChild(li);
      });
    }

    streetInput.addEventListener("input", function() {
      searchStreet(this.value);
    });

    // Load initial regions
    loadRegions();
  </script>

</body>
</html>
