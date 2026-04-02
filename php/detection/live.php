<?php
// php/detection/live.php
require_once __DIR__ . '/../auth/middleware.php';
$pageTitle = 'Live Detection';

$db = getDB();
$sessionToken = generateSessionToken();

// Create detection session
$db->prepare("INSERT INTO detection_sessions (user_id, session_token, start_time) VALUES (?, ?, NOW())")
   ->execute([$_SESSION['user_id'], $sessionToken]);

include __DIR__ . '/../../includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-video me-2"></i>Live Criminal Detection</h2>
        <div class="d-flex gap-2">
            <span class="badge bg-dark px-3 py-2" id="fpsCounter">FPS: 0</span>
            <span class="badge bg-dark px-3 py-2" id="frameCounter">Frames: 0</span>
            <button class="btn btn-success" id="btnStart" onclick="startDetection()">
                <i class="fas fa-play me-1"></i> Start
            </button>
            <button class="btn btn-danger d-none" id="btnStop" onclick="stopDetection()">
                <i class="fas fa-stop me-1"></i> Stop
            </button>
        </div>
    </div>

    <div class="row g-4">
        <!-- Video Feed -->
        <div class="col-lg-8">
            <div class="card dark-card">
                <div class="card-body p-0 position-relative">
                    <div id="videoContainer" class="video-container">
                        <video id="webcam" autoplay muted playsinline class="w-100 rounded"></video>
                        <canvas id="overlay" class="detection-overlay"></canvas>
                        <canvas id="captureCanvas" class="d-none"></canvas>
                        
                        <!-- Status overlay -->
                        <div id="statusOverlay" class="status-overlay">
                            <i class="fas fa-camera fa-3x mb-3"></i>
                            <h4>Camera Feed</h4>
                            <p>Click "Start" to begin live detection</p>
                        </div>

                        <!-- Alert overlay (shown on match) -->
                        <div id="alertOverlay" class="alert-overlay d-none">
                            <div class="alert-content">
                                <i class="fas fa-exclamation-triangle fa-4x text-danger mb-3 blink"></i>
                                <h2 class="text-danger">⚠ CRIMINAL DETECTED ⚠</h2>
                                <div id="alertDetails"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detection Settings -->
            <div class="card dark-card mt-3">
                <div class="card-body py-2">
                    <div class="row align-items-center g-3">
                        <div class="col-md-3">
                            <label class="form-label mb-0 small">Confidence Threshold</label>
                            <div class="d-flex align-items-center gap-2">
                                <input type="range" id="threshold" min="30" max="95" value="45" class="form-range">
                                <span id="thresholdVal" class="badge bg-info">45%</span>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label mb-0 small">Detection Speed</label>
                            <select id="detectionSpeed" class="form-select form-select-sm dark-input">
                                <option value="500">Fast (2 FPS)</option>
                                <option value="1000" selected>Normal (1 FPS)</option>
                                <option value="2000">Slow (0.5 FPS)</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label mb-0 small">Detection Location <span class="text-muted">(Auto-detected)</span></label>
                            <div class="input-group input-group-sm">
                                <input type="text" id="detectionLocation" class="form-control dark-input" placeholder="Detecting location..." value="" style="cursor: help;" data-bs-toggle="tooltip" title="Location detected automatically from GPS or IP address">
                                <button class="btn btn-outline-info" type="button" id="btnRefreshLocation" onclick="getLocationAutomatically().then(() => { document.getElementById('detectionLocation').focus(); })" title="Click to refresh location detection">
                                    <i class="fas fa-map-marker-alt"></i>
                                </button>
                            </div>
                            <small class="text-muted d-block mt-1" id="locationHint">GPS: <span id="locationCoords">--</span></small>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label mb-0 small">Camera</label>
                            <select id="cameraSelect" class="form-select form-select-sm dark-input"></select>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="soundAlert" checked>
                                <label class="form-check-label small" for="soundAlert">Sound Alerts</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detection Results Panel -->
        <div class="col-lg-4">
            <!-- Active Detection Status -->
            <div class="card dark-card">
                <div class="card-header">
                    <h5><i class="fas fa-radar me-2"></i>Detection Status</h5>
                </div>
                <div class="card-body">
                    <div class="detection-stats">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Status</span>
                            <span id="detectionStatus" class="badge bg-secondary">Idle</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Faces Detected</span>
                            <span id="facesCount" class="text-info">0</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Matches Found</span>
                            <span id="matchesCount" class="text-danger fw-bold">0</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Session Duration</span>
                            <span id="sessionDuration" class="text-muted">00:00:00</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Model Status -->
            <div class="card dark-card mt-3" style="background: linear-gradient(135deg, #1a3a52 0%, #0f2438 100%); border: 2px solid #00bfff;">
                <div class="card-body">
                    <h5 class="mb-3"><i class="fas fa-cogs me-2" style="color: #00bfff;"></i>Model Status</h5>
                    <div class="row g-3">
                        <div class="col-md-4 text-center">
                            <small class="text-muted d-block mb-2">Status</small>
                            <span id="modelStatus" class="badge bg-success text-white" style="font-size: 14px; padding: 8px 12px;">Loaded</span>
                        </div>
                        <div class="col-md-4 text-center">
                            <small class="text-muted d-block mb-2">Encodings</small>
                            <span id="modelEncodings" class="text-white fw-bold" style="font-size: 18px;">0</span>
                        </div>
                        <div class="col-md-4 text-center">
                            <small class="text-muted d-block mb-2">Criminals</small>
                            <span id="modelCriminals" class="text-white fw-bold" style="font-size: 18px;">0</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Live Matches -->
            <div class="card dark-card mt-3">
                <div class="card-header d-flex justify-content-between">
                    <h5><i class="fas fa-bell me-2 text-danger"></i>Live Matches</h5>
                    <button class="btn btn-sm btn-outline-secondary" onclick="clearMatches()">Clear</button>
                </div>
                <div class="card-body p-0" style="max-height:500px;overflow-y:auto;">
                    <div id="matchesList">
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-shield-alt fa-2x mb-2"></i>
                            <p>No matches detected yet</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Alert Sound -->
<audio id="alertSound" preload="auto">
    <source src="data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbsGczFTiS3O3ovWY2GkSf5fLps2Y3HEei5/PsijMWLHe36fDvqGI7GTV3pdrYqXZQOUts0u/26b6IUT0iSI/G4tO3eSknM0R2qtXZtIBcOR1IjcPe1LyIYj4kSZPH4tm/" type="audio/wav">
</audio>

<script>
const SESSION_TOKEN = '<?= $sessionToken ?>';
const CSRF_TOKEN = '<?= getCSRFToken() ?>';
const PYTHON_API = '<?= PYTHON_API_URL ?>';
const PYTHON_API_KEY = '<?= getenv('PYTHON_API_KEY') ?>';
const PHP_API = '<?= BASE_URL ?>php/api/bridge.php';

let webcamStream = null;
let detectionInterval = null;
let isDetecting = false;
let frameCount = 0;
let matchCount = 0;
let facesTotal = 0;
let sessionStart = null;
let durationInterval = null;
let lastFPS = 0;
let fpsFrames = 0;
let fpsLastTime = Date.now();
let lastFramePixelData = null;  // Track video frames to detect if stream is frozen
let frozenFrameCount = 0;       // Count consecutive frozen frames

// Populate camera list
async function getCameras() {
    const devices = await navigator.mediaDevices.enumerateDevices();
    const select = document.getElementById('cameraSelect');
    devices.filter(d => d.kind === 'videoinput').forEach((d, i) => {
        select.innerHTML += `<option value="${d.deviceId}">${d.label || 'Camera ' + (i+1)}</option>`;
    });
}
getCameras();

// Fetch and display model status
async function loadModelStatus() {
    try {
        const response = await fetch(PYTHON_API + '/api/status', {
            method: 'GET',
            headers: {
                'X-API-Key': '<?= getenv('PYTHON_API_KEY') ?>'
            }
        });
        
        if (response.ok) {
            const data = await response.json();
            document.getElementById('modelStatus').textContent = data.model_loaded ? 'Loaded' : 'Not Loaded';
            document.getElementById('modelStatus').className = data.model_loaded ? 'badge bg-success text-white' : 'badge bg-danger text-white';
            document.getElementById('modelEncodings').textContent = data.total_encodings || '0';
            document.getElementById('modelCriminals').textContent = data.total_criminals || '0';
        }
    } catch (err) {
        console.warn('Could not load model status:', err);
        document.getElementById('modelStatus').textContent = 'Error';
        document.getElementById('modelStatus').className = 'badge bg-warning text-dark';
    }
}
loadModelStatus();

// Start automatic location detection in background immediately (non-blocking)
// Will use GPS or IP-based geolocation - whichever works best
getLocationAutomatically().catch(err => {
    // Silently fail - location detection is optional, detection proceeds regardless
});

// Threshold slider
document.getElementById('threshold').oninput = function() {
    document.getElementById('thresholdVal').textContent = this.value + '%';
};

async function startDetection() {
    try {
        const cameraId = document.getElementById('cameraSelect').value;
        const constraints = {
            video: cameraId ? { deviceId: { exact: cameraId } } : { facingMode: 'user' },
            audio: false
        };

        // Auto-detect location if not already set
        const locationField = document.getElementById('detectionLocation');
        if (!locationField.value || locationField.value === '') {
            await getLocationAutomatically();
        }

        webcamStream = await navigator.mediaDevices.getUserMedia(constraints);
        const video = document.getElementById('webcam');
        video.srcObject = webcamStream;
        
        // Wait for video metadata to be loaded before starting detection
        await new Promise((resolve) => {
            const onLoadedMetadata = () => {
                video.removeEventListener('loadedmetadata', onLoadedMetadata);
                resolve();
            };
            
            if (video.readyState >= video.HAVE_FUTURE_FRAME) {
                // Metadata already loaded
                resolve();
            } else {
                video.addEventListener('loadedmetadata', onLoadedMetadata);
            }
        });

        // Ensure video plays
        try {
            await video.play();
        } catch (e) {
            console.warn('Video play error (may be auto-play policy):', e);
        }

        // Setup overlay canvas with proper dimensions
        const overlay = document.getElementById('overlay');
        overlay.width = video.videoWidth;
        overlay.height = video.videoHeight;
        
        if (overlay.width === 0 || overlay.height === 0) {
            throw new Error('Video dimensions not available. Camera may not have started properly.');
        }

        document.getElementById('statusOverlay').classList.add('d-none');
        document.getElementById('btnStart').classList.add('d-none');
        document.getElementById('btnStop').classList.remove('d-none');
        document.getElementById('detectionStatus').className = 'badge bg-success';
        document.getElementById('detectionStatus').textContent = 'Active';

        isDetecting = true;
        sessionStart = Date.now();
        
        // ============ START: Camera Disconnect Monitoring ============
        // Listen for stream track end (camera disconnected/turned off)
        webcamStream.getTracks().forEach(track => {
            track.addEventListener('ended', () => {
                console.warn('[CAMERA] Stream track ended - Camera may have been disconnected');
                Swal.fire({
                    title: '📹 Camera Disconnected',
                    text: 'The camera has been turned off or disconnected. Detection has been stopped.',
                    icon: 'warning',
                    background: '#1a1a1a',
                    color: '#fff',
                    confirmButtonColor: '#ffc107'
                });
                if (isDetecting) {
                    stopDetection();
                }
            });
        });

        // Monitor video element for errors
        video.addEventListener('error', (err) => {
            console.error('[CAMERA] Video element error:', err);
            Swal.fire({
                title: '❌ Camera Error',
                text: 'Failed to access camera. The camera may be in use by another application or was disconnected.',
                icon: 'error',
                background: '#1a1a1a',
                color: '#fff',
                confirmButtonColor: '#dc3545'
            });
            if (isDetecting) {
                stopDetection();
            }
        });

        // Periodic health check: Verify stream is still active (every 3 seconds)
        let streamHealthCheckInterval = setInterval(() => {
            if (!isDetecting) {
                clearInterval(streamHealthCheckInterval);
                return;
            }
            
            const activeTracks = webcamStream.getVideoTracks().filter(track => track.readyState === 'live');
            if (activeTracks.length === 0) {
                console.error('[CAMERA] Health check failed - No active video tracks detected');
                Swal.fire({
                    title: '⚠️ Camera Offline',
                    text: 'Camera connection lost. Detection has been stopped.',
                    icon: 'warning',
                    background: '#1a1a1a',
                    color: '#fff',
                    confirmButtonColor: '#ffc107'
                });
                clearInterval(streamHealthCheckInterval);
                if (isDetecting) {
                    stopDetection();
                }
            }
        }, 3000);
        // ============ END: Camera Disconnect Monitoring ============
        
        // Start session timer
        durationInterval = setInterval(updateDuration, 1000);
        
        // Start detection loop
        const speed = parseInt(document.getElementById('detectionSpeed').value);
        detectionInterval = setInterval(processFrame, speed);
        
        // FPS counter
        setInterval(() => {
            document.getElementById('fpsCounter').textContent = 'FPS: ' + fpsFrames;
            fpsFrames = 0;
        }, 1000);

    } catch (err) {
        console.error('Detection error:', err);
        Swal.fire('Camera Error', err.message, 'error');
        isDetecting = false;
    }
}

// Auto-detect location from browser geolocation
async function getLocationAutomatically() {
    return new Promise((resolve) => {
        if (!navigator.geolocation) {
            getLocationFromIP();
            resolve();
            return;
        }

        navigator.geolocation.getCurrentPosition(
            async (position) => {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                const accuracy = position.coords.accuracy;

                try {
                    // Use reverse geocoding to get location name with detailed address
                    // Try higher zoom level (21) for more detailed/specific location data
                    const response = await fetch(
                        `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=21&addressdetails=1`,
                        { headers: { 'Accept-Language': 'en' } }
                    );
                    const data = await response.json();
                    const addr = data.address || {};
                    
                    // Priority order for MAXIMUM SPECIFICITY: Get the exact village/locality name
                    // Try to find the most specific location component available
                    let locationName = 
                        addr.neighbourhood ||     // Most specific: exact locality/palli name (e.g., "neerugattuvari palli")
                        addr.isolated_dwelling || // Very small settlements
                        addr.village ||           // Specific village 
                        addr.hamlet ||            // Hamlet if village not available
                        addr.locality ||          // Locality within area (if available)
                        addr.town ||              // Town
                        addr.city ||              // City
                        addr.suburb ||            // Suburb of city
                        addr.county ||            // County/District
                        addr.state ||             // State (fallback)
                        addr.country ||           // Country (last resort)
                        `${lat.toFixed(4)}, ${lng.toFixed(4)}`; // Coordinates as final fallback
                    
                    // Build full address for verification
                    const fullAddress = [
                        addr.road ? addr.road : null,
                        locationName,
                        addr.county,
                        addr.state,
                        addr.country
                    ].filter(x => x).join(', ');
                    
                    const locationField = document.getElementById('detectionLocation');
                    locationField.value = locationName;
                    locationField.title = `📍 EXACT LOCATION\n\nDetected: ${locationName}\n\nFull Address:\n${fullAddress}\n\nGPS Coordinates:\n${lat.toFixed(6)}, ${lng.toFixed(6)}\n\nAccuracy: ±${accuracy.toFixed(0)}m`;
                    
                    // Display coordinates
                    document.getElementById('locationCoords').textContent = `${lat.toFixed(4)}, ${lng.toFixed(4)} (±${accuracy.toFixed(0)}m)`;
                    
                    // Change field styling to indicate successful detection
                    locationField.classList.remove('border-warning');
                    locationField.classList.add('border-success');
                    

                } catch (err) {

                    // Fallback to coordinates
                    const fallbackLocation = `${lat.toFixed(4)}, ${lng.toFixed(4)}`;
                    const locationField = document.getElementById('detectionLocation');
                    locationField.value = fallbackLocation;
                    locationField.title = `GPS Coordinates (Accuracy: ${accuracy.toFixed(0)}m)`;
                    document.getElementById('locationCoords').textContent = `${lat.toFixed(4)}, ${lng.toFixed(4)} (±${accuracy.toFixed(0)}m)`;
                    locationField.classList.add('border-warning');
                }
                resolve();
            },
            (error) => {
                console.warn('GPS Geolocation failed, trying IP-based location:', error.message);
                // Fallback to IP-based geolocation (more accurate in India than GPS sometimes)
                getLocationFromIP();
                resolve();
            },
            {
                enableHighAccuracy: true,  // Force high-accuracy GPS
                timeout: 10000,             // Wait up to 10 seconds
                maximumAge: 0               // Don't use cached location
            }
        );
    });
}

// Fallback: Get location from IP address (better for India)
async function getLocationFromIP() {
    try {
        // Try ip-api.com first (better coverage for India)
        let data = null;
        try {
            const response = await fetch('https://ip-api.com/json/?fields=status,city,region,country');
            data = await response.json();
            if (data.status !== 'success') {
                throw new Error('ip-api failed, trying backup...');
            }
        } catch (e1) {
            // Fallback to ipapi.co
            const response = await fetch('https://ipapi.co/json/', { headers: { 'Accept-Language': 'en' } });
            data = await response.json();
        }
        
        if (data && data.city) {
            const ipLocation = data.city;
            const ipState = data.region || '';
            const ipCountry = data.country || '';
            
            const locationField = document.getElementById('detectionLocation');
            locationField.value = ipLocation;
            locationField.title = `IP-based Location:\n${ipLocation}, ${ipState}, ${ipCountry}`;
            locationField.classList.remove('border-warning');
            locationField.classList.add('border-success');
            document.getElementById('locationCoords').textContent = `IP: ${ipLocation}`;
        }
    } catch (err) {
        console.warn('[IP-FALLBACK] IP geolocation failed:', err);
        const locationField = document.getElementById('detectionLocation');
        locationField.value = 'Location detection unavailable';
        locationField.title = 'Could not detect location. Please try allowing GPS permission or refresh.';
        locationField.classList.add('border-warning');
    }
}

function stopDetection() {
    isDetecting = false;
    
    if (detectionInterval) clearInterval(detectionInterval);
    if (durationInterval) clearInterval(durationInterval);
    if (webcamStream) webcamStream.getTracks().forEach(t => t.stop());

    document.getElementById('webcam').srcObject = null;
    document.getElementById('statusOverlay').classList.remove('d-none');
    document.getElementById('btnStart').classList.remove('d-none');
    document.getElementById('btnStop').classList.add('d-none');
    document.getElementById('detectionStatus').className = 'badge bg-secondary';
    document.getElementById('detectionStatus').textContent = 'Stopped';

    // End session in DB
    fetch(PHP_API, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action: 'end_session',
            session_token: SESSION_TOKEN,
            csrf_token: CSRF_TOKEN,
            frames_processed: frameCount,
            faces_detected: facesTotal,
            matches_found: matchCount
        })
    });
}

async function processFrame() {
    if (!isDetecting) return;

    const video = document.getElementById('webcam');
    
    // Safety check: ensure video has valid dimensions
    if (video.videoWidth === 0 || video.videoHeight === 0) {
        console.warn('Video dimensions not yet available - waiting for metadata:', {
            readyState: video.readyState,
            videoWidth: video.videoWidth,
            videoHeight: video.videoHeight
        });
        return;
    }

    const canvas = document.getElementById('captureCanvas');
    const ctx = canvas.getContext('2d');
    
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    
    // Verify canvas has valid dimensions
    if (canvas.width === 0 || canvas.height === 0) {
        console.warn('Canvas dimensions invalid after setting');
        return;
    }
    
    try {
        ctx.drawImage(video, 0, 0);
    } catch (drawErr) {
        console.error('Error drawing video frame:', drawErr);
        return;
    }

    const frameData = canvas.toDataURL('image/jpeg', 0.8);
    
    // Verify frame data is sufficient
    if (!frameData || frameData.length < 500) {
        console.warn(`Frame data too small: ${frameData ? frameData.length : 0} bytes (minimum 500 required)`);
        return;
    }
    
    // ============ START: Detect Frozen Stream ============
    // Check if frame is identical to previous frame (indicates frozen camera)
    if (lastFramePixelData && lastFramePixelData === frameData) {
        frozenFrameCount++;
        console.warn(`[STREAM] Frozen frame detected (${frozenFrameCount} consecutive)`);
        
        // If 10+ consecutive identical frames, stream is definitely frozen
        if (frozenFrameCount >= 10) {
            console.error('[STREAM] Camera stream is frozen - treating as disconnected');
            Swal.fire({
                title: '🎥 Camera Frozen',
                text: 'The camera stream appears to be frozen. This may indicate the camera is offline or disconnected.',
                icon: 'warning',
                background: '#1a1a1a',
                color: '#fff',
                confirmButtonColor: '#ffc107'
            });
            stopDetection();
            return;
        }
    } else {
        // Frame changed, reset frozen counter
        frozenFrameCount = 0;
    }
    lastFramePixelData = frameData;
    // ============ END: Detect Frozen Stream ============
    
    frameCount++;
    fpsFrames++;
    document.getElementById('frameCounter').textContent = 'Frames: ' + frameCount;

    try {
        const threshold = document.getElementById('threshold').value;
        
        const response = await fetch(PYTHON_API + '/api/detect?t=' + Date.now(), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-API-Key': PYTHON_API_KEY
            },
            body: JSON.stringify({
                frame: frameData,
                threshold: parseFloat(threshold),
                session_token: SESSION_TOKEN
            })
        });

        if (!response.ok) {
            console.error('[PROCESS] API HTTP Error:', {
                status: response.status,
                statusText: response.statusText,
                url: response.url,
                frame: frameCount
            });
            // Update status to show API error
            if (response.status === 0 || response.status === 504 || response.status === 503) {
                document.getElementById('detectionStatus').className = 'badge bg-danger';
                document.getElementById('detectionStatus').textContent = 'API Error (' + response.status + ')';
            }
            try {
                const errorText = await response.text();
                console.error('[PROCESS] Error response body:', errorText);
            } catch (e) {
                console.error('[PROCESS] Could not parse error response');
            }
            return;
        }

        let data;
        try {
            data = await response.json();
        } catch (parseErr) {
            console.error('[PROCESS] Failed to parse API response as JSON:', parseErr);
            const respText = await response.text();
            console.error('[PROCESS] Response was:', respText.substring(0, 200));
            return;
        }
        
        if (!data) {
            console.error('[PROCESS] No data returned from API');
            return;
        }
        
        if (data.error && !data.success) {
            console.warn('[PROCESS] API returned error:', data.error);
            document.getElementById('detectionStatus').className = 'badge bg-warning text-dark';
            document.getElementById('detectionStatus').textContent = 'Detection Error';
            // Don't return - we still want to draw any face locations
        }
        
        // Log detection results
        console.log(`[PROCESS] Frame ${frameCount}: Faces=${data.faces_detected || 0}, Matches=${(data.matches || []).length}`);
        // Draw face boxes on overlay
        drawDetections(data);

        if (data.faces_detected > 0) {
            facesTotal += data.faces_detected;
            document.getElementById('facesCount').textContent = facesTotal;
            // Update detection status
            if (!document.getElementById('detectionStatus').textContent.includes('Match')) {
                document.getElementById('detectionStatus').className = 'badge bg-info';
                document.getElementById('detectionStatus').textContent = 'Scanning...';
            }
        }

        // Handle matches
        if (data.matches && data.matches.length > 0) {
            data.matches.forEach(match => {
                matchCount++;
                document.getElementById('matchesCount').textContent = matchCount;
                
                // Update status to show match
                document.getElementById('detectionStatus').className = 'badge bg-danger';
                document.getElementById('detectionStatus').textContent = 'Match Found!';
                setTimeout(() => {
                    if (isDetecting) {
                        document.getElementById('detectionStatus').className = 'badge bg-success';
                        document.getElementById('detectionStatus').textContent = 'Active';
                    }
                }, 3000);
                
                // Show prominent alert
                showAlert(match);
                
                // Add to live matches list
                addMatchToList(match);
                
                // Save alert to database
                saveAlert(match, frameData);
                
                // Play sound alert
                if (document.getElementById('soundAlert').checked) {
                    playAlertSound();
                }
            });
        } else if (data.faces_detected > 0) {
            // Face detected but no match - show in status
            document.getElementById('detectionStatus').className = 'badge bg-info';
            document.getElementById('detectionStatus').textContent = 'Face Detected - Not in DB';
        }

    } catch (err) {
        console.error('Detection error:', err);
        document.getElementById('detectionStatus').className = 'badge bg-warning text-dark';
        document.getElementById('detectionStatus').textContent = `Error: ${err.message.substring(0, 20)}...`;
    }
}

function drawDetections(data) {
    const overlay = document.getElementById('overlay');
    const ctx = overlay.getContext('2d');
    const video = document.getElementById('webcam');
    
    overlay.width = video.videoWidth;
    overlay.height = video.videoHeight;
    ctx.clearRect(0, 0, overlay.width, overlay.height);

    // Log detection data for debugging
    if (data && data.face_locations) {
        console.log(`[DRAW] Drawing ${data.face_locations.length} face(s), ${(data.matches || []).length} match(es)`);
    }

    if (!data || !data.face_locations || data.face_locations.length === 0) {
        // No faces detected - clear canvas
        return;
    }

    // Draw each detected face
    data.face_locations.forEach((loc, i) => {
        try {
            const [top, right, bottom, left] = loc;
            const isMatch = data.matches && data.matches.some(m => m.face_index === i);
            
            // Validate coordinates
            if (isNaN(top) || isNaN(right) || isNaN(bottom) || isNaN(left)) {
                console.warn(`[DRAW] Invalid face coordinates at index ${i}:`, loc);
                return;
            }
            
            const width = right - left;
            const height = bottom - top;
            
            // Draw face box
            ctx.strokeStyle = isMatch ? '#ff0000' : '#00ff00';  // Red for match, green for unknown
            ctx.lineWidth = 4;  // Thicker line for visibility
            ctx.strokeRect(left, top, width, height);

            // Draw label background
            const labelHeight = 30;
            ctx.fillStyle = isMatch ? 'rgba(255,0,0,0.8)' : 'rgba(0,255,0,0.8)';
            ctx.fillRect(left, Math.max(0, top - labelHeight), width, labelHeight);
            
            // Draw label text
            ctx.fillStyle = '#fff';
            ctx.font = 'bold 14px Arial';
            ctx.textBaseline = 'middle';
            
            let labelText = 'Unknown';  // Default for unmatched faces
            if (isMatch) {
                const match = data.matches.find(m => m.face_index === i);
                if (match) {
                    labelText = `${match.name} (${match.confidence}%)`;
                    console.log(`[DRAW] Match found: ${labelText}`);
                }
            } else {
                console.log(`[DRAW] Face ${i}: No match in database (Unknown)`);
            }
            
            ctx.fillText(labelText, left + 5, Math.max(labelHeight/2, top - labelHeight/2));
            
        } catch (drawErr) {
            console.error(`[DRAW] Error drawing face ${i}:`, drawErr);
        }
    });
}

function showAlert(match) {
    // Show overlay alert
    const alertOverlay = document.getElementById('alertOverlay');
    document.getElementById('alertDetails').innerHTML = `
        <div class="mt-3 text-white">
            <h3>${match.name}</h3>
            <p class="mb-1"><strong>Code:</strong> ${match.criminal_code}</p>
            <p class="mb-1"><strong>Crime:</strong> ${match.crime_type}</p>
            <p class="mb-1"><strong>Danger:</strong> <span class="badge bg-danger">${match.danger_level}</span></p>
            <p class="mb-1"><strong>Confidence:</strong> ${match.confidence}%</p>
            <p class="mb-1"><strong>Location:</strong> ${document.getElementById('detectionLocation').value || 'Unknown'}</p>
        </div>
    `;
    alertOverlay.classList.remove('d-none');
    
    // Show SweetAlert2 modal as well for better visibility
    Swal.fire({
        title: '🚨 CRIMINAL DETECTED! 🚨',
        html: `
            <div class="text-start">
                <div class="row">
                    <div class="col-md-4">
                        ${match.photo ? `<img src="${match.photo}" class="img-fluid rounded" style="max-height:150px;border:3px solid red;">` : '<div class="bg-secondary rounded p-4">No Photo</div>'}
                    </div>
                    <div class="col-md-8">
                        <h4 class="text-danger mb-2">${match.name}</h4>
                        <p><strong>Criminal Code:</strong> <span class="badge bg-danger">${match.criminal_code}</span></p>
                        <p><strong>Crime Type:</strong> ${match.crime_type}</p>
                        <p><strong>Danger Level:</strong> <span class="badge bg-danger">${match.danger_level}</span></p>
                        <p><strong>Match Confidence:</strong> <span class="badge bg-warning text-dark">${match.confidence}%</span></p>
                        <p><strong>Location Detected:</strong> ${document.getElementById('detectionLocation').value || 'Unknown'}</p>
                        <p><strong>Time:</strong> ${new Date().toLocaleString()}</p>
                    </div>
                </div>
            </div>
        `,
        icon: 'error',
        background: '#1a1a1a',
        color: '#fff',
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'View Criminal Details',
        showCancelButton: true,
        cancelButtonText: 'Close',
        didOpen: () => {
            // Flash screen red
            document.body.style.backgroundColor = 'rgba(220, 53, 69, 0.1)';
            setTimeout(() => {
                document.body.style.backgroundColor = '';
            }, 2000);
        }
    }).then((result) => {
        if (result.isConfirmed) {
            window.open(`<?= BASE_URL ?>php/criminals/detail.php?id=${match.criminal_id}`, '_blank');
        }
    });
    
    // Auto-hide overlay after 8 seconds
    setTimeout(() => alertOverlay.classList.add('d-none'), 8000);
}

function addMatchToList(match) {
    const list = document.getElementById('matchesList');
    const firstChild = list.querySelector('.text-center');
    if (firstChild) firstChild.remove();

    const time = new Date().toLocaleTimeString();
    const html = `
        <div class="match-item p-3 border-bottom border-secondary" style="animation:slideIn 0.3s;">
            <div class="d-flex align-items-center gap-3">
                ${match.photo ? `<img src="${match.photo}" class="rounded-circle" width="50" height="50" style="object-fit:cover;border:3px solid red;">` : ''}
                <div class="flex-grow-1">
                    <strong class="text-danger">${match.name}</strong>
                    <div class="small text-muted">${match.criminal_code}</div>
                    <div class="small">${match.crime_type}</div>
                </div>
                <div class="text-end">
                    <div class="badge bg-danger">${match.confidence}%</div>
                    <div class="small text-muted mt-1">${time}</div>
                </div>
            </div>
            <div class="mt-2">
                <a href="<?= BASE_URL ?>php/criminals/detail.php?id=${match.criminal_id}" 
                   class="btn btn-sm btn-outline-danger" target="_blank">
                    <i class="fas fa-eye me-1"></i>View Details
                </a>
            </div>
        </div>`;
    list.insertAdjacentHTML('afterbegin', html);
}

function saveAlert(match, frameData) {
    const location = document.getElementById('detectionLocation').value || 'Unknown Location';
    
    fetch(PHP_API, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action: 'save_alert',
            criminal_id: match.criminal_id,
            confidence_score: match.confidence,
            detection_location: location,
            frame_data: frameData,
            session_token: SESSION_TOKEN,
            csrf_token: CSRF_TOKEN
        })
    })
    .then(res => res.json())
    .then(data => {
        if (!data.success) {
            console.error(`Failed to save alert: ${data.message || 'Unknown error'}`);
        }
    })
    .catch(err => {
        console.error(`Error saving alert: ${err.message}`);
    });
}

function playAlertSound() {
    const audioElement = document.getElementById('alertSound');
    audioElement.currentTime = 0;
    
    // Try to play, with fallback to Web Audio API beep
    audioElement.play().catch(() => {
        // Fallback: Create a simple beep using Web Audio API
        try {
            const context = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = context.createOscillator();
            const gainNode = context.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(context.destination);
            
            oscillator.frequency.value = 800;
            oscillator.type = 'sine';
            
            gainNode.gain.setValueAtTime(0.5, context.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, context.currentTime + 0.5);
            
            oscillator.start(context.currentTime);
            oscillator.stop(context.currentTime + 0.5);
        } catch (e) {
            console.warn('Could not play alert sound:', e);
        }
    });
}

function updateDuration() {
    const elapsed = Math.floor((Date.now() - sessionStart) / 1000);
    const h = String(Math.floor(elapsed / 3600)).padStart(2, '0');
    const m = String(Math.floor((elapsed % 3600) / 60)).padStart(2, '0');
    const s = String(elapsed % 60).padStart(2, '0');
    document.getElementById('sessionDuration').textContent = `${h}:${m}:${s}`;
}

function clearMatches() {
    document.getElementById('matchesList').innerHTML = `
        <div class="text-center text-muted py-5">
            <i class="fas fa-shield-alt fa-2x mb-2"></i>
            <p>No matches detected yet</p>
        </div>`;
}

// Stop detection on page unload
window.addEventListener('beforeunload', () => { if (isDetecting) stopDetection(); });
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>