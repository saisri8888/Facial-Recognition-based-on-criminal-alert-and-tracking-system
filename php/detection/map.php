<?php
// php/detection/map.php
require_once __DIR__ . '/../auth/middleware.php';
$pageTitle = 'Crime Location Tracker - Map View';
require_once __DIR__ . '/../../includes/header.php';
?>

<style>
    #crimeMap {
        height: 600px;
        border-radius: 8px;
        margin-bottom: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }

    .map-control-panel {
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        margin-bottom: 20px;
    }

    .filter-group {
        margin-bottom: 15px;
    }

    .filter-group label {
        font-weight: 600;
        margin-bottom: 5px;
        display: block;
    }

    .filter-group input,
    .filter-group select {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
    }

    .filter-buttons {
        display: flex;
        gap: 10px;
        margin-top: 20px;
    }

    .filter-buttons button {
        flex: 1;
        padding: 10px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-weight: 600;
    }

    .btn-apply {
        background: #007bff;
        color: white;
    }

    .btn-reset {
        background: #6c757d;
        color: white;
    }

    .btn-apply:hover {
        background: #0056b3;
    }

    .btn-reset:hover {
        background: #545b62;
    }

    .marker-info {
        font-size: 13px;
    }

    .marker-info strong {
        display: block;
        margin-top: 5px;
    }

    .danger-level {
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 600;
    }

    .danger-critical {
        background: #dc3545;
        color: white;
    }

    .danger-high {
        background: #fd7e14;
        color: white;
    }

    .danger-medium {
        background: #ffc107;
        color: black;
    }

    .danger-low {
        background: #28a745;
        color: white;
    }

    .legend {
        background: white;
        padding: 15px;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        margin-bottom: 20px;
    }

    .legend-item {
        display: flex;
        align-items: center;
        margin-bottom: 8px;
    }

    .legend-color {
        width: 20px;
        height: 20px;
        margin-right: 10px;
        border-radius: 50%;
    }

    .stats-overlay {
        background: white;
        padding: 15px;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }

    .stat-item {
        margin-bottom: 15px;
        padding-bottom: 15px;
        border-bottom: 1px solid #eee;
    }

    .stat-item:last-child {
        border-bottom: none;
        margin-bottom: 0;
        padding-bottom: 0;
    }

    .stat-label {
        font-size: 12px;
        color: #666;
        text-transform: uppercase;
    }

    .stat-value {
        font-size: 24px;
        font-weight: bold;
        color: #007bff;
    }

    .row-controls {
        display: flex;
        gap: 20px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }

    .control-section {
        flex: 1;
        min-width: 200px;
    }

    .toggle-layer {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px;
        background: #f8f9fa;
        border-radius: 4px;
        cursor: pointer;
        margin-bottom: 10px;
    }

    .toggle-layer input[type="checkbox"] {
        width: auto;
        margin: 0;
    }
</style>

<div class="page-header">
    <h1><i class="fas fa-map-marked-alt"></i> Crime Location Tracker</h1>
    <p class="text-muted">Real-time map of detected criminals and crime hotspots</p>
</div>

<div class="container-fluid">
    <!-- Map -->
    <!-- Search Bar -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="input-group">
                <input type="text" id="locationSearch" class="form-control form-control-lg" placeholder="Search location (e.g., MG Road, Bus Station)...">
                <button class="btn btn-primary" onclick="performLocationSearch()">
                    <i class="fas fa-search"></i> Search
                </button>
            </div>
            <div id="searchResults" style="position: absolute; background: white; border: 1px solid #ddd; border-radius: 4px; width: 98%; max-height: 200px; overflow-y: auto; display: none; z-index: 1000; margin-top: 5px;"></div>
        </div>
    </div>

    <div id="crimeMap"></div>

    <!-- Control Panel -->
    <div class="row">
        <div class="col-lg-8">
            <div class="map-control-panel">
                <h5 class="mb-3"><i class="fas fa-filter"></i> Filters</h5>
                
                <div class="row">
                    <div class="col-md-2">
                        <div class="filter-group">
                            <label for="filterDateFrom">Date From</label>
                            <input type="date" id="filterDateFrom" value="<?= date('Y-m-d', strtotime('-30 days')) ?>">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="filter-group">
                            <label for="filterDateTo">Date To</label>
                            <input type="date" id="filterDateTo" value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="filter-group">
                            <label for="filterCriminal">Criminal</label>
                            <select id="filterCriminal">
                                <option value="">All Criminals</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="filter-group">
                            <label for="filterCrimeType">Crime Type</label>
                            <select id="filterCrimeType">
                                <option value="">All Crime Types</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="filter-group">
                            <label for="routeCriminal">Track Route</label>
                            <select id="routeCriminal" onchange="trackCriminalRoute(this.value)">
                                <option value="">Select Criminal</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="filter-buttons">
                    <button class="btn-apply" onclick="applyFilters()">
                        <i class="fas fa-search"></i> Apply Filters
                    </button>
                    <button class="btn-reset" onclick="resetFilters()">
                        <i class="fas fa-redo"></i> Reset
                    </button>
                </div>
            </div>
        </div>

        <!-- Legend & Statistics -->
        <div class="col-lg-4">
            <div class="legend">
                <h6 class="mb-3"><i class="fas fa-circle"></i> Marker Legend</h6>
                <div class="legend-item">
                    <div class="legend-color" style="background: #dc3545;"></div>
                    <span>Critical Danger</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: #fd7e14;"></div>
                    <span>High Danger</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: #ffc107;"></div>
                    <span>Medium Danger</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: #28a745;"></div>
                    <span>Low Danger</span>
                </div>
            </div>

            <div class="map-control-panel">
                <h5 class="mb-3"><i class="fas fa-layer-group"></i> Map Layers</h5>
                <label class="toggle-layer">
                    <input type="checkbox" id="layerDetections" checked>
                    <span>Detection Markers</span>
                </label>
                <label class="toggle-layer">
                    <input type="checkbox" id="layerHeatmap">
                    <span>Crime Heatmap</span>
                </label>
                <label class="toggle-layer">
                    <input type="checkbox" id="layerCameras">
                    <span>Camera Locations</span>
                </label>
                <label class="toggle-layer">
                    <input type="checkbox" id="layerRoutes">
                    <span>Movement Routes</span>
                </label>
            </div>

            <div id="statsContainer" class="stats-overlay mt-3" style="display:none;">
                <h6 class="mb-3"><i class="fas fa-chart-bar"></i> Area Statistics</h6>
                <div id="statsContent"></div>
            </div>
        </div>
    </div>
</div>

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css" />
<!-- Leaflet Heatmap -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.heat/0.2.0/leaflet-heat.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>

<script>
const BASE_URL = '<?= BASE_URL ?>';
let map;
let detectionMarkers = [];
let heatmapLayer = null;
let detectionsClusterGroup = null;
let currentFilters = {};

// Initialize map
function initMap() {
    map = L.map('crimeMap').setView([12.9716, 77.5946], 12); // Center on Bangalore
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19
    }).addTo(map);

    loadFiltersData();
    loadDetections();

    // Layer toggle listeners
    document.getElementById('layerDetections').addEventListener('change', toggleDetectionMarkers);
    document.getElementById('layerHeatmap').addEventListener('change', toggleHeatmap);
    document.getElementById('layerCameras').addEventListener('change', toggleCameras);
}

// Load filter options
async function loadFiltersData() {
    try {
        const criminalsRes = await fetch(BASE_URL + 'php/api/map_data.php?action=criminals');
        const criminalsData = await criminalsRes.json();
        
        const crimeTypesRes = await fetch(BASE_URL + 'php/api/map_data.php?action=crime_types');
        const crimeTypesData = await crimeTypesRes.json();
        
        // Populate criminal filter
        const criminalSelect = document.getElementById('filterCriminal');
        const routeSelect = document.getElementById('routeCriminal');
        
        criminalsData.criminals.forEach(criminal => {
            const option = document.createElement('option');
            option.value = criminal.id;
            option.textContent = `${criminal.first_name} ${criminal.last_name} (${criminal.criminal_code})`;
            criminalSelect.appendChild(option);
            
            // Also add to route tracking
            const routeOption = document.createElement('option');
            routeOption.value = criminal.id;
            routeOption.textContent = `${criminal.first_name} ${criminal.last_name} (${criminal.criminal_code})`;
            routeSelect.appendChild(routeOption);
        });
        
        // Populate crime type filter
        const crimeTypeSelect = document.getElementById('filterCrimeType');
        crimeTypesData.crime_types.forEach(type => {
            const option = document.createElement('option');
            option.value = type;
            option.textContent = type;
            crimeTypeSelect.appendChild(option);
        });
    } catch (error) {
        console.error('Error loading filter data:', error);
    }
}

// Load detections and plot markers
async function loadDetections() {
    try {
        const params = new URLSearchParams();
        params.append('action', 'detections');
        params.append('date_from', document.getElementById('filterDateFrom').value);
        params.append('date_to', document.getElementById('filterDateTo').value);
        
        const criminalId = document.getElementById('filterCriminal').value;
        if (criminalId) {
            params.append('criminal_id', criminalId);
        }
        
        const crimeType = document.getElementById('filterCrimeType').value;
        if (crimeType) {
            params.append('crime_type', crimeType);
        }
        
        const response = await fetch(BASE_URL + 'php/api/map_data.php?' + params);
        const data = await response.json();
        
        clearMarkers();
        plotDetectionMarkers(data.detections);
        
        if (document.getElementById('layerHeatmap').checked) {
            loadHeatmap();
        }
        
        loadStatistics();
    } catch (error) {
        console.error('Error loading detections:', error);
    }
}

// Plot detection markers on map
function plotDetectionMarkers(detections) {
    detections.forEach(detection => {
        let coordinates = getCoordinates(detection.display_location);
        
        if (!coordinates) {
            return;
        }
        
        const color = getDangerColor(detection.danger_level);
        
        const marker = L.circleMarker([coordinates.lat, coordinates.lng], {
            radius: 8,
            fillColor: color,
            color: '#fff',
            weight: 2,
            opacity: 1,
            fillOpacity: 0.8
        }).addTo(map);
        
        const popupContent = `
            <div class="marker-info">
                <strong>${detection.first_name} ${detection.last_name}</strong>
                <small>(${detection.criminal_code})</small>
                <br/>
                <strong>Crime:</strong> ${detection.crime_type}
                <br/>
                <strong>Confidence:</strong> ${detection.confidence_score}%
                <br/>
                <strong>Time:</strong> ${new Date(detection.detected_at).toLocaleString()}
                <br/>
                <strong>Location:</strong> ${detection.display_location}
                <br/>
                <span class="danger-level danger-${detection.danger_level}">${detection.danger_level.toUpperCase()}</span>
                <br/><br/>
                <a href="${BASE_URL}php/alerts/detail.php?id=${detection.id}" class="btn btn-sm btn-primary" target="_blank">
                    View Details
                </a>
            </div>
        `;
        
        marker.bindPopup(popupContent);
        detectionMarkers.push(marker);
    });
}

// Get danger color
function getDangerColor(level) {
    const colors = {
        'critical': '#dc3545',
        'high': '#fd7e14',
        'medium': '#ffc107',
        'low': '#28a745'
    };
    return colors[level] || '#6c757d';
}

// Get coordinates from location string
function getCoordinates(location) {
    const locationMap = {
        // Major Areas
        'MG Road': {lat: 12.9352, lng: 77.6149},
        'Indiranagar': {lat: 12.9716, lng: 77.6412},
        'Whitefield': {lat: 12.9698, lng: 77.7499},
        'Koramangala': {lat: 12.9352, lng: 77.6245},
        'City Center': {lat: 12.9716, lng: 77.5946},
        'Bengaluru': {lat: 12.9716, lng: 77.5946},
        'Bus Station': {lat: 12.8396, lng: 77.6245},
        'Railway Station': {lat: 12.9277, lng: 77.5903},
        
        // Karpuram & Surrounding
        'Karpuram': {lat: 12.9350, lng: 77.6100},
        'Karpuram Main': {lat: 12.9350, lng: 77.6100},
        'Karpuram Road': {lat: 12.9350, lng: 77.6100},
        
        // Additional Key Locations
        'Marathahalli': {lat: 12.9698, lng: 77.6979},
        'Sarjapur Road': {lat: 12.9176, lng: 77.6432},
        'Frazer Town': {lat: 12.9667, lng: 77.6094},
        'Vasanth Nagar': {lat: 12.9611, lng: 77.5885},
        'Shivajinagar': {lat: 12.9733, lng: 77.5932},
        'Richmond Town': {lat: 12.9500, lng: 77.6000},
        'Sadashivanagar': {lat: 12.9500, lng: 77.5500},
        'Malleswaram': {lat: 13.0012, lng: 77.5705},
        'JP Nagar': {lat: 12.9352, lng: 77.6149},
        'Jayanagar': {lat: 12.9352, lng: 77.5932},
        'Bannerghatta': {lat: 12.8396, lng: 77.6433},
        'Yeshwanthpur': {lat: 13.0011, lng: 77.5703},
        'Vijaynagar': {lat: 13.0184, lng: 77.5860},
        'Ravi Nagar': {lat: 13.0342, lng: 77.5705},
        'Location Unknown': {lat: 12.9716, lng: 77.5946}
    };
    
    // Try exact match first
    if (locationMap[location]) {
        return locationMap[location];
    }
    
    // Try case-insensitive match
    for (const [key, coords] of Object.entries(locationMap)) {
        if (key.toLowerCase() === location.toLowerCase()) {
            return coords;
        }
    }
    
    // If still not found, return City Center as fallback (not random)
    console.warn('Location not found in map:', location, '- Using City Center');
    return {lat: 12.9716, lng: 77.5946};
}

// Load heatmap
async function loadHeatmap() {
    try {
        const params = new URLSearchParams({
            action: 'heatmap',
            date_from: document.getElementById('filterDateFrom').value,
            date_to: document.getElementById('filterDateTo').value
        });
        
        const response = await fetch(BASE_URL + 'php/api/map_data.php?' + params);
        const data = await response.json();
        
        const heatmapData = data.heatmap_points.map(point => {
            const coords = getCoordinates(point.location);
            return [coords.lat, coords.lng, point.count];
        });
        
        if (heatmapLayer) {
            map.removeLayer(heatmapLayer);
        }
        
        heatmapLayer = L.heatLayer(heatmapData, {
            radius: 40,
            blur: 35,
            maxZoom: 3,
            gradient: {
                0.0: '#28a745',
                0.5: '#ffc107',
                1.0: '#dc3545'
            }
        }).addTo(map);
    } catch (error) {
        console.error('Error loading heatmap:', error);
    }
}

// Load statistics
async function loadStatistics() {
    try {
        const params = new URLSearchParams({
            action: 'statistics',
            date_from: document.getElementById('filterDateFrom').value,
            date_to: document.getElementById('filterDateTo').value
        });
        
        const response = await fetch(BASE_URL + 'php/api/map_data.php?' + params);
        const data = await response.json();
        
        if (data.statistics && data.statistics.length > 0) {
            displayStatistics(data.statistics[0]);
        }
    } catch (error) {
        console.error('Error loading statistics:', error);
    }
}

// Display statistics
function displayStatistics(stats) {
    const container = document.getElementById('statsContainer');
    const content = document.getElementById('statsContent');
    
    content.innerHTML = `
        <div class="stat-item">
            <div class="stat-label">Location</div>
            <div class="stat-value" style="font-size: 16px;">${stats.location}</div>
        </div>
        <div class="stat-item">
            <div class="stat-label">Total Crimes</div>
            <div class="stat-value">${stats.total_crimes}</div>
        </div>
        <div class="stat-item">
            <div class="stat-label">Unique Criminals</div>
            <div class="stat-value">${stats.unique_criminals}</div>
        </div>
        <div class="stat-item">
            <div class="stat-label">By Danger Level</div>
            <small>
                Critical: ${stats.critical_crimes} | 
                High: ${stats.high_crimes} | 
                Medium: ${stats.medium_crimes} | 
                Low: ${stats.low_crimes}
            </small>
        </div>
        <div class="stat-item">
            <div class="stat-label">Most Common Crime</div>
            <div style="font-weight: 600; margin-top: 5px;">${stats.most_common_crime || 'N/A'}</div>
        </div>
    `;
    
    container.style.display = 'block';
}

// Toggle detection markers
function toggleDetectionMarkers() {
    const isChecked = document.getElementById('layerDetections').checked;
    detectionMarkers.forEach(marker => {
        if (isChecked) {
            marker.addTo(map);
        } else {
            map.removeLayer(marker);
        }
    });
}

// Toggle heatmap
function toggleHeatmap() {
    const isChecked = document.getElementById('layerHeatmap').checked;
    if (isChecked) {
        loadHeatmap();
    } else if (heatmapLayer) {
        map.removeLayer(heatmapLayer);
    }
}



// Clear all markers
function clearMarkers() {
    detectionMarkers.forEach(marker => map.removeLayer(marker));
    detectionMarkers = [];
}

// Apply filters
function applyFilters() {
    loadDetections();
}

// Reset filters
function resetFilters() {
    // Calculate dates in local timezone
    const today = new Date();
    const thirtyDaysAgo = new Date(today.getTime() - 30 * 24 * 60 * 60 * 1000);
    
    const formatDate = (date) => {
        return date.getFullYear() + '-' + 
               String(date.getMonth() + 1).padStart(2, '0') + '-' + 
               String(date.getDate()).padStart(2, '0');
    };
    
    document.getElementById('filterDateFrom').value = formatDate(thirtyDaysAgo);
    document.getElementById('filterDateTo').value = formatDate(today);
    document.getElementById('filterCriminal').value = '';
    document.getElementById('filterCrimeType').value = '';
    applyFilters();
}

// Location Search
async function performLocationSearch() {
    const query = document.getElementById('locationSearch').value;
    if (query.length < 2) return;
    
    try {
        const response = await fetch(BASE_URL + 'php/api/location_search.php?q=' + encodeURIComponent(query));
        const data = await response.json();
        
        if (data.results && data.results.length > 0) {
            const location = data.results[0];
            map.setView([location.lat, location.lng], 16);
            
            L.circleMarker([location.lat, location.lng], {
                radius: 10,
                fillColor: '#007bff',
                color: '#fff',
                weight: 2,
                opacity: 1,
                fillOpacity: 0.8
            }).addTo(map).bindPopup(`<strong>${location.name}</strong><br>Searched Location`);
            
            document.getElementById('locationSearch').value = '';
        }
    } catch (error) {
        console.error('Error searching location:', error);
    }
}

// Toggle Cameras
let cameraMarkers = [];
async function toggleCameras() {
    const isChecked = document.getElementById('layerCameras').checked;
    
    if (isChecked) {
        try {
            const response = await fetch(BASE_URL + 'php/api/cameras.php');
            const data = await response.json();
            
            data.cameras.forEach(camera => {
                const marker = L.marker([camera.lat, camera.lng], {
                    icon: L.icon({
                        iconUrl: 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%232f7f3f"><path d="M12 8c2.2 0 4 1.8 4 4s-1.8 4-4 4-4-1.8-4-4 1.8-4 4-4m0-2C9.3 6 7 8.3 7 11c0 2.8 2.2 5 5 5s5-2.2 5-5-2.2-5-5-5z"/><circle cx="12" cy="12" r="2.5"/></svg>',
                        iconSize: [30, 30],
                        iconAnchor: [15, 15]
                    })
                }).addTo(map);
                
                marker.bindPopup(`
                    <strong>${camera.name}</strong><br>
                    Camera ID: ${camera.id}<br>
                    Status: <span style="color: ${camera.status === 'active' ? 'green' : 'red'}">${camera.status.toUpperCase()}</span><br>
                    Detections: ${camera.detections}
                `);
                
                cameraMarkers.push(marker);
            });
        } catch (error) {
            console.error('Error loading cameras:', error);
        }
    } else {
        cameraMarkers.forEach(marker => map.removeLayer(marker));
        cameraMarkers = [];
    }
}

// Track Criminal Route
let routePolyline = null;
let routeMarkers = [];
async function trackCriminalRoute(criminalId) {
    if (!criminalId) {
        if (routePolyline) {
            map.removeLayer(routePolyline);
            routePolyline = null;
        }
        routeMarkers.forEach(m => map.removeLayer(m));
        routeMarkers = [];
        return;
    }
    
    try {
        const response = await fetch(BASE_URL + 'php/api/routes.php?criminal_id=' + criminalId);
        const data = await response.json();
        
        if (data.route && data.route.length > 0) {
            // Clear existing route
            if (routePolyline) {
                map.removeLayer(routePolyline);
            }
            routeMarkers.forEach(m => map.removeLayer(m));
            routeMarkers = [];
            
            const routeCoordinates = [];
            data.route.forEach((point, index) => {
                const coords = getCoordinates(point.location);
                routeCoordinates.push([coords.lat, coords.lng]);
                
                const marker = L.circleMarker([coords.lat, coords.lng], {
                    radius: 8,
                    fillColor: index === 0 ? '#28a745' : (index === data.route.length - 1 ? '#dc3545' : '#007bff'),
                    color: '#fff',
                    weight: 2,
                    opacity: 1,
                    fillOpacity: 0.8
                }).addTo(map);
                
                marker.bindPopup(`
                    <strong>${data.criminal_name}</strong><br>
                    Detection ${index + 1} of ${data.route.length}<br>
                    Location: ${point.location}<br>
                    Time: ${new Date(point.time).toLocaleString()}<br>
                    Confidence: ${point.confidence}%
                `);
                
                routeMarkers.push(marker);
            });
            
            // Draw polyline for route
            routePolyline = L.polyline(routeCoordinates, {
                color: '#007bff',
                weight: 3,
                opacity: 0.7,
                dashArray: '10, 5'
            }).addTo(map);
            
            // Fit map to route bounds
            map.fitBounds(routePolyline.getBounds());
        }
    } catch (error) {
        console.error('Error loading route:', error);
    }
}

// Location search listener
document.getElementById('locationSearch').addEventListener('keypress', (e) => {
    if (e.key === 'Enter') {
        performLocationSearch();
    }
});

// Initialize on page load
document.addEventListener('DOMContentLoaded', initMap);
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
