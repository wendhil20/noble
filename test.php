<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>MapFind</title>

  <!-- Tailwind CSS CDN -->
  <script src="https://cdn.tailwindcss.com"></script>

  <!-- Leaflet CSS -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet" />

  <style>
    :root {
      --bg: #0d0f14;
      --surface: #161820;
      --surface2: #1e2130;
      --border: #2a2d3e;
      --accent: #4fffb0;
      --accent2: #7b61ff;
      --text: #e8eaf0;
      --muted: #6b7090;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'Space Grotesk', sans-serif;
      background: var(--bg);
      color: var(--text);
      height: 100vh;
      overflow: hidden;
      display: flex;
      flex-direction: column;
    }

    /* Header */
    header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0.75rem 1.25rem;
      background: var(--surface);
      border-bottom: 1px solid var(--border);
      z-index: 1000;
      flex-shrink: 0;
    }

    .logo {
      font-size: 1.1rem;
      font-weight: 700;
      letter-spacing: -0.02em;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .logo-dot {
      width: 8px; height: 8px;
      background: var(--accent);
      border-radius: 50%;
      display: inline-block;
      box-shadow: 0 0 8px var(--accent);
      animation: pulse 2s ease-in-out infinite;
    }

    @keyframes pulse {
      0%, 100% { opacity: 1; transform: scale(1); }
      50% { opacity: 0.6; transform: scale(0.8); }
    }

    /* Search bar wrapper */
    .search-wrapper {
      position: relative;
      width: min(480px, 55vw);
    }

    .search-input {
      width: 100%;
      background: var(--surface2);
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 0.55rem 2.8rem 0.55rem 2.6rem;
      font-family: 'Space Grotesk', sans-serif;
      font-size: 0.875rem;
      color: var(--text);
      outline: none;
      transition: border-color 0.2s, box-shadow 0.2s;
    }

    .search-input::placeholder { color: var(--muted); }

    .search-input:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 3px rgba(79, 255, 176, 0.12);
    }

    .search-icon {
      position: absolute;
      left: 0.75rem;
      top: 50%;
      transform: translateY(-50%);
      color: var(--muted);
      pointer-events: none;
    }

    .search-clear {
      position: absolute;
      right: 0.65rem;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      color: var(--muted);
      cursor: pointer;
      padding: 2px;
      border-radius: 4px;
      display: none;
      align-items: center;
      transition: color 0.2s;
    }

    .search-clear:hover { color: var(--text); }

    /* Autocomplete dropdown */
    .suggestions-box {
      position: absolute;
      top: calc(100% + 6px);
      left: 0; right: 0;
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 16px 40px rgba(0,0,0,0.5);
      z-index: 2000;
      display: none;
    }

    .suggestion-item {
      padding: 0.65rem 1rem;
      cursor: pointer;
      display: flex;
      align-items: flex-start;
      gap: 0.6rem;
      border-bottom: 1px solid var(--border);
      transition: background 0.15s;
    }

    .suggestion-item:last-child { border-bottom: none; }
    .suggestion-item:hover, .suggestion-item.active { background: var(--surface2); }

    .suggestion-icon {
      flex-shrink: 0;
      margin-top: 2px;
      color: var(--accent);
    }

    .suggestion-main {
      font-size: 0.85rem;
      font-weight: 500;
      color: var(--text);
      line-height: 1.3;
    }

    .suggestion-sub {
      font-size: 0.75rem;
      color: var(--muted);
      margin-top: 1px;
    }

    .suggestion-type {
      margin-left: auto;
      flex-shrink: 0;
      font-size: 0.65rem;
      font-family: 'DM Mono', monospace;
      color: var(--accent2);
      background: rgba(123, 97, 255, 0.12);
      padding: 2px 7px;
      border-radius: 20px;
      white-space: nowrap;
      text-transform: uppercase;
      align-self: flex-start;
    }

    .no-results {
      padding: 0.9rem 1rem;
      font-size: 0.85rem;
      color: var(--muted);
      text-align: center;
    }

    .loading-indicator {
      padding: 0.9rem 1rem;
      font-size: 0.8rem;
      color: var(--muted);
      text-align: center;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
    }

    .spinner {
      width: 14px; height: 14px;
      border: 2px solid var(--border);
      border-top-color: var(--accent);
      border-radius: 50%;
      animation: spin 0.7s linear infinite;
    }

    @keyframes spin { to { transform: rotate(360deg); } }

    /* Coords badge */
    .coords-badge {
      font-family: 'DM Mono', monospace;
      font-size: 0.7rem;
      color: var(--muted);
      background: var(--surface2);
      border: 1px solid var(--border);
      padding: 0.3rem 0.7rem;
      border-radius: 20px;
      white-space: nowrap;
    }

    /* Map container */
    #map {
      flex: 1;
      width: 100%;
    }

    /* Leaflet dark overrides */
    .leaflet-container {
      background: #111318 !important;
      font-family: 'Space Grotesk', sans-serif !important;
    }

    .leaflet-tile-pane { filter: brightness(0.85) saturate(0.75); }

    .leaflet-popup-content-wrapper {
      background: var(--surface) !important;
      color: var(--text) !important;
      border: 1px solid var(--border) !important;
      border-radius: 10px !important;
      box-shadow: 0 8px 32px rgba(0,0,0,0.5) !important;
    }

    .leaflet-popup-tip { background: var(--surface) !important; }

    .leaflet-popup-content {
      font-family: 'Space Grotesk', sans-serif !important;
      font-size: 0.85rem !important;
      margin: 10px 14px !important;
    }

    .popup-title {
      font-weight: 600;
      font-size: 0.9rem;
      color: var(--accent);
      margin-bottom: 4px;
    }

    .popup-sub {
      color: var(--muted);
      font-size: 0.78rem;
      line-height: 1.5;
    }

    .leaflet-control-zoom a {
      background: var(--surface) !important;
      color: var(--text) !important;
      border-color: var(--border) !important;
    }

    .leaflet-control-zoom a:hover {
      background: var(--surface2) !important;
      color: var(--accent) !important;
    }

    .leaflet-control-attribution {
      background: rgba(13,15,20,0.7) !important;
      color: var(--muted) !important;
      font-size: 0.6rem !important;
    }

    .leaflet-control-attribution a { color: var(--accent2) !important; }

    /* Pulse marker */
    .pulse-marker {
      width: 16px; height: 16px;
      background: var(--accent);
      border-radius: 50%;
      box-shadow: 0 0 0 4px rgba(79,255,176,0.3);
      animation: markerPulse 1.5s ease-out infinite;
    }

    @keyframes markerPulse {
      0% { box-shadow: 0 0 0 0 rgba(79,255,176,0.5); }
      70% { box-shadow: 0 0 0 10px rgba(79,255,176,0); }
      100% { box-shadow: 0 0 0 0 rgba(79,255,176,0); }
    }

    /* Status bar */
    .statusbar {
      background: var(--surface);
      border-top: 1px solid var(--border);
      padding: 0.35rem 1.25rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-shrink: 0;
    }

    .status-text {
      font-size: 0.72rem;
      color: var(--muted);
      font-family: 'DM Mono', monospace;
    }

    .status-dot {
      width: 6px; height: 6px;
      background: var(--accent);
      border-radius: 50%;
      display: inline-block;
      margin-right: 6px;
    }

    /* Keyboard shortcut hint */
    kbd {
      font-family: 'DM Mono', monospace;
      font-size: 0.65rem;
      background: var(--surface2);
      border: 1px solid var(--border);
      border-radius: 4px;
      padding: 1px 5px;
      color: var(--muted);
    }
  </style>
</head>
<body>

<!-- Header -->
<header>
  <div class="logo">
    <span class="logo-dot"></span>
    MapFind
  </div>

  <div class="search-wrapper">
    <!-- Search icon -->
    <svg class="search-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
      <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
    </svg>

    <input
      type="text"
      id="searchInput"
      class="search-input"
      placeholder="Hanapin ang lugar..."
      autocomplete="off"
      spellcheck="false"
    />

    <button class="search-clear" id="clearBtn" title="Clear">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
        <path d="M18 6 6 18M6 6l12 12"/>
      </svg>
    </button>

    <!-- Suggestions dropdown -->
    <div class="suggestions-box" id="suggestionsBox"></div>
  </div>

  <div class="coords-badge" id="coordsBadge">0.0000°, 0.0000°</div>
</header>

<!-- Map -->
<div id="map"></div>

<!-- Status bar -->
<div class="statusbar">
  <span class="status-text">
    <span class="status-dot"></span>
    Powered by OpenStreetMap · Nominatim v2
  </span>
  <span class="status-text" id="statusRight">↑↓ para pumili · Enter para hanapin</span>
</div>

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
// ── MAP INIT ──────────────────────────────────────────────────────────────
const map = L.map('map', {
  center: [14.5995, 120.9842], // Manila default
  zoom: 12,
  zoomControl: true,
});

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
  maxZoom: 19,
}).addTo(map);

// Custom marker icon
function makeIcon() {
  return L.divIcon({
    className: '',
    html: '<div class="pulse-marker"></div>',
    iconSize: [16, 16],
    iconAnchor: [8, 8],
    popupAnchor: [0, -14],
  });
}

let currentMarker = null;

map.on('mousemove', (e) => {
  document.getElementById('coordsBadge').textContent =
    `${e.latlng.lat.toFixed(4)}°, ${e.latlng.lng.toFixed(4)}°`;
});

// ── SEARCH LOGIC ──────────────────────────────────────────────────────────
const searchInput  = document.getElementById('searchInput');
const suggestionsBox = document.getElementById('suggestionsBox');
const clearBtn     = document.getElementById('clearBtn');
const statusRight  = document.getElementById('statusRight');

let debounceTimer   = null;
let activeIndex     = -1;
let currentResults  = [];
let abortController = null;

// Nominatim v2 endpoint (latest stable)
const NOMINATIM = 'https://nominatim.openstreetmap.org/search';

async function fetchSuggestions(query) {
  if (abortController) abortController.abort();
  abortController = new AbortController();

  const params = new URLSearchParams({
    q: query,
    format: 'jsonv2',          // JSON v2 – most accurate
    addressdetails: '1',
    extratags: '1',
    limit: '7',
    'accept-language': 'tl,en',
  });

  const res = await fetch(`${NOMINATIM}?${params}`, {
    signal: abortController.signal,
    headers: { 'Accept-Language': 'tl,en' },
  });

  if (!res.ok) throw new Error('Network error');
  return res.json();
}

function showLoading() {
  suggestionsBox.style.display = 'block';
  suggestionsBox.innerHTML = `
    <div class="loading-indicator">
      <div class="spinner"></div> Naghahanap…
    </div>`;
}

function hideSuggestions() {
  suggestionsBox.style.display = 'none';
  suggestionsBox.innerHTML = '';
  activeIndex = -1;
  currentResults = [];
}

function typeLabel(result) {
  return result.type || result.class || 'place';
}

function buildName(result) {
  // Use display_name parts
  const parts = result.display_name.split(', ');
  const main  = parts.slice(0, 2).join(', ');
  const sub   = parts.slice(2).join(', ');
  return { main, sub };
}

function renderSuggestions(results) {
  activeIndex = -1;
  currentResults = results;
  suggestionsBox.innerHTML = '';

  if (!results.length) {
    suggestionsBox.innerHTML = `<div class="no-results">Walang nahanap na lugar.</div>`;
    suggestionsBox.style.display = 'block';
    return;
  }

  results.forEach((r, i) => {
    const { main, sub } = buildName(r);
    const item = document.createElement('div');
    item.className = 'suggestion-item';
    item.dataset.index = i;
    item.innerHTML = `
      <svg class="suggestion-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
        <path d="M20 10c0 6-8 13-8 13s-8-7-8-13a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>
      </svg>
      <div style="flex:1;min-width:0">
        <div class="suggestion-main">${main}</div>
        ${sub ? `<div class="suggestion-sub">${sub}</div>` : ''}
      </div>
      <span class="suggestion-type">${typeLabel(r)}</span>`;

    item.addEventListener('mouseenter', () => setActive(i));
    item.addEventListener('click', () => selectResult(r));
    suggestionsBox.appendChild(item);
  });

  suggestionsBox.style.display = 'block';
}

function setActive(index) {
  const items = suggestionsBox.querySelectorAll('.suggestion-item');
  items.forEach(el => el.classList.remove('active'));
  activeIndex = index;
  if (index >= 0 && index < items.length) {
    items[index].classList.add('active');
    items[index].scrollIntoView({ block: 'nearest' });
  }
}

function selectResult(result) {
  const lat  = parseFloat(result.lat);
  const lon  = parseFloat(result.lon);
  const bbox = result.boundingbox;
  const { main, sub } = buildName(result);

  hideSuggestions();
  searchInput.value = main;
  clearBtn.style.display = 'flex';

  // Remove old marker
  if (currentMarker) map.removeLayer(currentMarker);

  // Fit bounds if available
  if (bbox) {
    map.fitBounds([
      [parseFloat(bbox[0]), parseFloat(bbox[2])],
      [parseFloat(bbox[1]), parseFloat(bbox[3])],
    ], { maxZoom: 17, padding: [40, 40] });
  } else {
    map.setView([lat, lon], 15);
  }

  // Add marker
  currentMarker = L.marker([lat, lon], { icon: makeIcon() }).addTo(map);
  currentMarker.bindPopup(`
    <div class="popup-title">${main}</div>
    ${sub ? `<div class="popup-sub">${sub}</div>` : ''}
    <div class="popup-sub" style="margin-top:6px;font-family:'DM Mono',monospace;font-size:0.72rem">
      ${lat.toFixed(5)}, ${lon.toFixed(5)}
    </div>`).openPopup();

  statusRight.textContent = `${result.importance ? `Relevance: ${(result.importance * 100).toFixed(0)}%` : ''} · ${typeLabel(result)}`;
}

// ── INPUT EVENTS ──────────────────────────────────────────────────────────
searchInput.addEventListener('input', () => {
  const val = searchInput.value.trim();
  clearBtn.style.display = val ? 'flex' : 'none';

  clearTimeout(debounceTimer);

  if (val.length < 2) {
    hideSuggestions();
    return;
  }

  showLoading();

  debounceTimer = setTimeout(async () => {
    try {
      const results = await fetchSuggestions(val);
      renderSuggestions(results);
    } catch (err) {
      if (err.name !== 'AbortError') {
        suggestionsBox.innerHTML = `<div class="no-results">May error. Subukan ulit.</div>`;
      }
    }
  }, 350); // 350ms debounce – balances speed vs. rate limit
});

searchInput.addEventListener('keydown', (e) => {
  const items = suggestionsBox.querySelectorAll('.suggestion-item');

  if (e.key === 'ArrowDown') {
    e.preventDefault();
    setActive(Math.min(activeIndex + 1, items.length - 1));
  } else if (e.key === 'ArrowUp') {
    e.preventDefault();
    setActive(Math.max(activeIndex - 1, 0));
  } else if (e.key === 'Enter') {
    e.preventDefault();
    if (activeIndex >= 0 && currentResults[activeIndex]) {
      selectResult(currentResults[activeIndex]);
    } else if (currentResults.length > 0) {
      selectResult(currentResults[0]);
    }
  } else if (e.key === 'Escape') {
    hideSuggestions();
    searchInput.blur();
  }
});

clearBtn.addEventListener('click', () => {
  searchInput.value = '';
  clearBtn.style.display = 'none';
  hideSuggestions();
  searchInput.focus();
  if (currentMarker) { map.removeLayer(currentMarker); currentMarker = null; }
  statusRight.textContent = '↑↓ para pumili · Enter para hanapin';
});

// Close suggestions when clicking outside
document.addEventListener('click', (e) => {
  if (!e.target.closest('.search-wrapper')) hideSuggestions();
});

// Keyboard shortcut: / to focus
document.addEventListener('keydown', (e) => {
  if (e.key === '/' && document.activeElement !== searchInput) {
    e.preventDefault();
    searchInput.focus();
    searchInput.select();
  }
});
</script>
</body>
</html>