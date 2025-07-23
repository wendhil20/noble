<!DOCTYPE html>
<html lang="en">
<head>
  <title>Enhanced TikTok-Style Shipping Calculator</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  
  <!-- Font Awesome for icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <!-- Tailwind CSS -->
  <script src="https://cdn.tailwindcss.com"></script>
  
  <style>
    @keyframes slideDown {
      from {
        opacity: 0;
        transform: translateY(-20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    @keyframes slideUp {
      from {
        opacity: 0;
        transform: translateY(20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    @keyframes pulse {
      0%, 100% {
        opacity: 1;
      }
      50% {
        opacity: 0.5;
      }
    }
    
    .animate-slide-down {
      animation: slideDown 0.6s ease-out;
    }
    
    .animate-slide-up {
      animation: slideUp 0.8s ease-out;
    }
    
    .animate-pulse-custom {
      animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }
    
    #map {
      border-radius: 12px;
    }
    
    .suggestion-item:hover {
      background-color: #f1f5f9;
    }
    
    .input-focus:focus {
      transform: translateY(-1px);
    }
  </style>
</head>
<body class="bg-slate-100 min-h-screen">
  <div class="max-w-4xl mx-auto p-4 sm:p-6">
    <!-- Header -->
    <div class="text-center mb-8 animate-slide-down">
      <h1 class="text-4xl sm:text-5xl font-black text-pink-600 mb-2">
        <i class="fas fa-truck mr-3"></i>Shipping Calculator
      </h1>
      <p class="text-slate-600 text-lg">Calculate delivery costs with Google Maps routing</p>
    </div>
    
    <!-- API Key Setup Notice -->
    <div id="api-notice" class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 mb-6 text-yellow-800">
      <i class="fas fa-key mr-2"></i>
      <strong>Setup Required:</strong> Please add your Google Maps API key in the script section to use this calculator.
      <a href="https://developers.google.com/maps/documentation/javascript/get-api-key" target="_blank" class="underline ml-2">Get API Key</a>
    </div>
    
    <!-- Main Card -->
    <div class="bg-white rounded-2xl shadow-2xl overflow-hidden animate-slide-up">
      <!-- Card Header -->
      <div class="bg-pink-600 text-white p-6">
        <h2 class="text-2xl font-bold flex items-center gap-3">
          <i class="fas fa-map-marked-alt"></i>
          Delivery Information
        </h2>
      </div>
      
      <!-- Form Section -->
      <div class="p-6 space-y-6">
        <!-- Address Search -->
        <div class="space-y-2">
          <label for="address-input" class="block text-sm font-semibold text-slate-700 flex items-center gap-2">
            <i class="fas fa-search text-pink-600"></i>
            Search Delivery Address
          </label>
          <div class="relative">
            <input 
              type="text" 
              id="address-input" 
              placeholder="Enter delivery address..." 
              autocomplete="off"
              class="w-full px-4 py-3 border-2 border-slate-200 rounded-xl bg-slate-50 text-slate-900 placeholder-slate-500 focus:border-pink-600 focus:bg-white focus:outline-none transition-all duration-300 input-focus"
            >
            <div id="suggestions" class="absolute top-full left-0 right-0 bg-white border border-slate-200 rounded-xl mt-1 shadow-lg z-50 hidden"></div>
            <div id="loading" class="hidden mt-2 text-center text-slate-500">
              <i class="fas fa-spinner animate-spin mr-2"></i>
              Searching addresses...
            </div>
          </div>
        </div>
        
        <!-- Selected Address -->
        <div class="space-y-2">
          <label for="selected-address" class="block text-sm font-semibold text-slate-700 flex items-center gap-2">
            <i class="fas fa-map-marker-alt text-teal-600"></i>
            Selected Address
          </label>
          <input 
            type="text" 
            id="selected-address" 
            readonly
            placeholder="No address selected"
            class="w-full px-4 py-3 border-2 border-slate-200 rounded-xl bg-slate-100 text-slate-600 cursor-not-allowed"
          >
        </div>
        
        <!-- Shipping Information Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <!-- Shipping Fee -->
          <div class="space-y-2">
            <label for="shipping-fee" class="block text-sm font-semibold text-slate-700 flex items-center gap-2">
              <i class="fas fa-peso-sign text-green-600"></i>
              Shipping Fee
            </label>
            <input 
              type="text" 
              id="shipping-fee" 
              readonly
              placeholder="₱0"
              class="w-full px-4 py-3 border-2 border-green-200 rounded-xl bg-green-50 text-green-800 font-bold text-lg text-center cursor-not-allowed"
            >
          </div>
          
          <!-- Distance -->
          <div class="space-y-2">
            <label class="block text-sm font-semibold text-slate-700 flex items-center gap-2">
              <i class="fas fa-route text-blue-600"></i>
              Distance
            </label>
            <div class="px-4 py-3 border-2 border-blue-200 rounded-xl bg-blue-50 text-blue-800 font-semibold text-center">
              <span id="distance-display">-- km</span>
            </div>
          </div>
          
          <!-- Duration -->
          <div class="space-y-2">
            <label class="block text-sm font-semibold text-slate-700 flex items-center gap-2">
              <i class="fas fa-clock text-purple-600"></i>
              Estimated Time
            </label>
            <div class="px-4 py-3 border-2 border-purple-200 rounded-xl bg-purple-50 text-purple-800 font-semibold text-center">
              <span id="duration-display">-- min</span>
            </div>
          </div>
        </div>
        
        <!-- Error Message -->
        <div id="error-message" class="hidden bg-red-50 border border-red-200 rounded-xl p-4 text-red-700">
          <i class="fas fa-exclamation-triangle mr-2"></i>
          <span id="error-text"></span>
        </div>
        
        <!-- Success Message -->
        <div id="success-message" class="hidden bg-green-50 border border-green-200 rounded-xl p-4 text-green-700">
          <i class="fas fa-check-circle mr-2"></i>
          <span id="success-text">Shipping calculated successfully!</span>
        </div>
      </div>
      
      <!-- Map Section -->
      <div class="p-6 pt-0">
        <div class="space-y-2 mb-4">
          <label class="block text-sm font-semibold text-slate-700 flex items-center gap-2">
            <i class="fas fa-globe text-indigo-600"></i>
            Interactive Google Map
          </label>
          <p class="text-sm text-slate-500">Click anywhere on the map to set delivery location</p>
        </div>
        <div id="map" class="h-96 sm:h-[500px] border-2 border-slate-200 shadow-lg"></div>
      </div>
      
      <!-- Pricing Info -->
      <div class="bg-slate-50 p-6">
        <h3 class="text-lg font-bold text-slate-800 mb-3 flex items-center gap-2">
          <i class="fas fa-info-circle text-blue-600"></i>
          Pricing Information
        </h3>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
          <div class="bg-white p-4 rounded-xl border border-slate-200">
            <div class="font-semibold text-slate-700">Base Rate</div>
            <div class="text-2xl font-bold text-pink-600">₱10/km</div>
          </div>
          <div class="bg-white p-4 rounded-xl border border-slate-200">
            <div class="font-semibold text-slate-700">Minimum Fee</div>
            <div class="text-2xl font-bold text-green-600">₱50</div>
          </div>
          <div class="bg-white p-4 rounded-xl border border-slate-200">
            <div class="font-semibold text-slate-700">Maximum Fee</div>
            <div class="text-2xl font-bold text-red-600">₱500</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Google Maps JavaScript API -->
  <script async defer src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBfOfHCFxHWPYhpYXJzBu1_tixPV8T_VOc&callback=initMap&region=PH&language=en"></script>

  <script>
    // Configuration
    const config = {
      defaultLocation: { lat: 14.5995, lng: 120.9842 }, // Manila coordinates
      defaultZoom: 12,
      sellerLocation: { lat: 14.5995, lng: 120.9842 }, // Warehouse location
      baseRate: 10, // ₱10 per km
      minFee: 50, // Minimum shipping fee
      maxFee: 500, // Maximum shipping fee
    };
    
    // Global variables
    let map;
    let directionsService;
    let directionsRenderer;
    let autocomplete;
    let placesService;
    let customerMarker;
    let warehouseMarker;
    let geocoder;
    
    // DOM elements
    const addressInput = document.getElementById('address-input');
    const selectedAddress = document.getElementById('selected-address');
    const suggestionsBox = document.getElementById('suggestions');
    const loadingIndicator = document.getElementById('loading');
    const errorMessage = document.getElementById('error-message');
    const errorText = document.getElementById('error-text');
    const successMessage = document.getElementById('success-message');
    const distanceDisplay = document.getElementById('distance-display');
    const durationDisplay = document.getElementById('duration-display');
    const shippingFeeInput = document.getElementById('shipping-fee');
    
    // Initialize Google Maps
    function initMap() {
      // Hide API notice when Google Maps loads
      document.getElementById('api-notice').style.display = 'none';
      
      // Initialize map
      map = new google.maps.Map(document.getElementById('map'), {
        zoom: config.defaultZoom,
        center: config.defaultLocation,
        styles: [
          {
            featureType: 'water',
            elementType: 'geometry',
            stylers: [{ color: '#e9e9e9' }, { lightness: 17 }]
          },
          {
            featureType: 'landscape',
            elementType: 'geometry',
            stylers: [{ color: '#f5f5f5' }, { lightness: 20 }]
          }
        ]
      });
      
      // Initialize services
      directionsService = new google.maps.DirectionsService();
      directionsRenderer = new google.maps.DirectionsRenderer({
        polylineOptions: {
          strokeColor: '#fe2c55',
          strokeWeight: 5,
          strokeOpacity: 0.8
        },
        suppressMarkers: true
      });
      directionsRenderer.setMap(map);
      
      placesService = new google.maps.places.PlacesService(map);
      geocoder = new google.maps.Geocoder();
      
      // Add warehouse marker
      warehouseMarker = new google.maps.Marker({
        position: config.sellerLocation,
        map: map,
        title: 'Warehouse Location',
        icon: {
          url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(`
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40" width="40" height="40">
              <circle cx="20" cy="20" r="18" fill="#db2777" stroke="white" stroke-width="2"/>
              <text x="20" y="26" text-anchor="middle" fill="white" font-size="16" font-family="FontAwesome">&#xf494;</text>
            </svg>
          `),
          scaledSize: new google.maps.Size(40, 40),
          anchor: new google.maps.Point(20, 20)
        }
      });
      
      // Add info window for warehouse
      const warehouseInfoWindow = new google.maps.InfoWindow({
        content: '<div class="font-semibold text-pink-600"><i class="fas fa-warehouse mr-2"></i>Warehouse Location</div>'
      });
      
      warehouseMarker.addListener('click', () => {
        warehouseInfoWindow.open(map, warehouseMarker);
      });
      
      // Initialize autocomplete
      initAutocomplete();
      
      // Add click listener to map
      map.addListener('click', (event) => {
        handleMapClick(event.latLng);
      });
    }
    
    // Initialize Places Autocomplete
    function initAutocomplete() {
      autocomplete = new google.maps.places.Autocomplete(addressInput, {
        componentRestrictions: { country: 'ph' }, // Restrict to Philippines
        fields: ['formatted_address', 'geometry', 'name', 'place_id'],
        types: ['establishment', 'geocode']
      });
      
      autocomplete.addListener('place_changed', () => {
        const place = autocomplete.getPlace();
        if (place.geometry) {
          selectPlace(place);
        }
      });
    }
    
    // Handle place selection
    function selectPlace(place) {
      const location = place.geometry.location;
      const address = place.formatted_address || place.name;
      
      selectedAddress.value = address;
      
      // Update or create customer marker
      if (customerMarker) {
        customerMarker.setMap(null);
      }
      
      customerMarker = new google.maps.Marker({
        position: location,
        map: map,
        title: 'Delivery Location',
        animation: google.maps.Animation.DROP,
        icon: {
          url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(`
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40" width="40" height="40">
              <circle cx="20" cy="20" r="18" fill="#0d9488" stroke="white" stroke-width="2"/>
              <text x="20" y="26" text-anchor="middle" fill="white" font-size="16" font-family="FontAwesome">&#xf015;</text>
            </svg>
          `),
          scaledSize: new google.maps.Size(40, 40),
          anchor: new google.maps.Point(20, 20)
        }
      });
      
      // Add info window for customer location
      const customerInfoWindow = new google.maps.InfoWindow({
        content: '<div class="font-semibold text-teal-600"><i class="fas fa-home mr-2"></i>Delivery Location</div>'
      });
      
      customerMarker.addListener('click', () => {
        customerInfoWindow.open(map, customerMarker);
      });
      
      // Fit map to show both locations
      const bounds = new google.maps.LatLngBounds();
      bounds.extend(config.sellerLocation);
      bounds.extend(location);
      map.fitBounds(bounds, { padding: 50 });
      
      // Calculate shipping
      calculateShipping(config.sellerLocation, location);
    }
    
    // Handle map click
    function handleMapClick(latLng) {
      showLoading();
      addressInput.value = "Getting address...";
      
      // Reverse geocode the clicked location
      geocoder.geocode({ location: latLng }, (results, status) => {
        hideLoading();
        
        if (status === 'OK' && results[0]) {
          const address = results[0].formatted_address;
          addressInput.value = address;
          selectedAddress.value = address;
          
          // Create place object and select it
          const place = {
            formatted_address: address,
            geometry: {
              location: latLng
            }
          };
          
          selectPlace(place);
        } else {
          const coords = `${latLng.lat().toFixed(6)}, ${latLng.lng().toFixed(6)}`;
          addressInput.value = coords;
          selectedAddress.value = coords;
          
          // Create marker anyway
          if (customerMarker) {
            customerMarker.setMap(null);
          }
          
          customerMarker = new google.maps.Marker({
            position: latLng,
            map: map,
            title: 'Selected Location',
            animation: google.maps.Animation.DROP,
            icon: {
              url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(`
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40" width="40" height="40">
                  <circle cx="20" cy="20" r="18" fill="#0d9488" stroke="white" stroke-width="2"/>
                  <text x="20" y="26" text-anchor="middle" fill="white" font-size="14" font-family="FontAwesome">&#xf041;</text>
                </svg>
              `),
              scaledSize: new google.maps.Size(40, 40),
              anchor: new google.maps.Point(20, 20)
            }
          });
          
          calculateShipping(config.sellerLocation, latLng);
        }
      });
    }
    
    // Calculate shipping cost using Google Directions API
    function calculateShipping(origin, destination) {
      shippingFeeInput.value = "Calculating...";
      distanceDisplay.textContent = "...";
      durationDisplay.textContent = "...";
      
      const request = {
        origin: origin,
        destination: destination,
        travelMode: google.maps.TravelMode.DRIVING,
        unitSystem: google.maps.UnitSystem.METRIC,
        avoidHighways: false,
        avoidTolls: false
      };
      
      directionsService.route(request, (result, status) => {
        if (status === 'OK') {
          // Display the route
          directionsRenderer.setDirections(result);
          
          // Extract distance and duration
          const route = result.routes[0];
          const leg = route.legs[0];
          
          const distanceInKm = leg.distance.value / 1000;
          const durationInMinutes = Math.ceil(leg.duration.value / 60);
          
          // Calculate shipping fee
          let fee = Math.ceil(distanceInKm * config.baseRate);
          fee = Math.max(config.minFee, Math.min(config.maxFee, fee));
          
          // Update UI
          shippingFeeInput.value = `₱${fee}`;
          distanceDisplay.textContent = `${distanceInKm.toFixed(1)} km`;
          durationDisplay.textContent = `${durationInMinutes} min`;
          
          showSuccess();
        } else {
          // Fallback to straight-line distance
          console.error('Directions request failed:', status);
          
          const distanceInKm = calculateStraightLineDistance(origin, destination);
          const estimatedDuration = Math.ceil(distanceInKm * 2);
          
          let fee = Math.ceil(distanceInKm * config.baseRate);
          fee = Math.max(config.minFee, Math.min(config.maxFee, fee));
          
          shippingFeeInput.value = `₱${fee} *`;
          distanceDisplay.textContent = `~${distanceInKm.toFixed(1)} km`;
          durationDisplay.textContent = `~${estimatedDuration} min`;
          
          showError("Using approximate distance. Actual route unavailable.");
        }
      });
    }
    
    // Utility functions
    function showError(message) {
      errorText.textContent = message;
      errorMessage.classList.remove('hidden');
      successMessage.classList.add('hidden');
      setTimeout(() => {
        errorMessage.classList.add('hidden');
      }, 5000);
    }
    
    function showSuccess() {
      successMessage.classList.remove('hidden');
      errorMessage.classList.add('hidden');
      setTimeout(() => {
        successMessage.classList.add('hidden');
      }, 3000);
    }
    
    function showLoading() {
      loadingIndicator.classList.remove('hidden');
    }
    
    function hideLoading() {
      loadingIndicator.classList.add('hidden');
    }
    
    // Calculate straight-line distance using Haversine formula
    function calculateStraightLineDistance(point1, point2) {
      const R = 6371; // Earth's radius in kilometers
      
      let lat1, lng1, lat2, lng2;
      
      if (point1.lat && point1.lng) {
        lat1 = point1.lat();
        lng1 = point1.lng();
      } else {
        lat1 = point1.lat;
        lng1 = point1.lng;
      }
      
      if (point2.lat && point2.lng) {
        lat2 = point2.lat();
        lng2 = point2.lng();
      } else {
        lat2 = point2.lat;
        lng2 = point2.lng;
      }
      
      const dLat = toRadians(lat2 - lat1);
      const dLon = toRadians(lng2 - lng1);
      
      const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                Math.cos(toRadians(lat1)) * Math.cos(toRadians(lat2)) *
                Math.sin(dLon / 2) * Math.sin(dLon / 2);
      
      const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
      return R * c;
    }
    
    function toRadians(degrees) {
      return degrees * (Math.PI / 180);
    }
    
    // Handle window errors for better debugging
    window.onerror = function(msg, url, line, col, error) {
      if (msg.includes('Google Maps API')) {
        document.getElementById('api-notice').innerHTML = `
          <i class="fas fa-exclamation-triangle mr-2"></i>
          <strong>Google Maps API Error:</strong> Please check your API key and ensure it has the required permissions.
          <a href="https://developers.google.com/maps/documentation/javascript/get-api-key" target="_blank" class="underline ml-2">Get API Key</a>
        `;
        document.getElementById('api-notice').className = "bg-red-50 border border-red-200 rounded-xl p-4 mb-6 text-red-800";
        document.getElementById('api-notice').style.display = 'block';
      }
    };
    
    // Add keyboard shortcuts
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        addressInput.blur();
      }
    });
  </script>
</body>
</html>