<?php
// php/detection/static.php
require_once __DIR__ . '/../auth/middleware.php';

// Officers and above can use static detection
requireOfficer();

$pageTitle = 'Static Criminal Detection';
$detectionResult = null;
$uploadError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $uploadError = 'Security token validation failed.';
    } elseif (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        $uploadError = 'Please select a valid image file.';
    } else {
        $file = $_FILES['image'];
        $allowed = ['image/jpeg', 'image/png', 'image/gif'];
        
        if (!in_array($file['type'], $allowed)) {
            $uploadError = 'Invalid file type. Only JPG, PNG, and GIF are allowed.';
        } elseif ($file['size'] > 5242880) { // 5MB
            $uploadError = 'File size exceeds 5MB limit.';
        } else {
            // Convert image to base64
            $imageData = file_get_contents($file['tmp_name']);
            $base64Image = 'data:' . $file['type'] . ';base64,' . base64_encode($imageData);
            
            // Call Python API for detection
            $threshold = isset($_POST['threshold']) ? floatval($_POST['threshold']) : 55;
            
            $pythonResponse = callPythonAPI('/api/detect', [
                'frame' => $base64Image,
                'threshold' => $threshold,
                'session_token' => $_SESSION['user_id'] ?? null
            ], 'POST');
            
            if (isset($pythonResponse['success']) && $pythonResponse['success']) {
                $detectionResult = $pythonResponse;
                
                // Enrich with database information for each match
                if (!empty($detectionResult['matches'])) {
                    $db = getDB();
                    if ($db) {
                        foreach ($detectionResult['matches'] as &$match) {
                            try {
                                $stmt = $db->prepare("
                                    SELECT c.*, cp.photo_path
                                    FROM criminals c
                                    LEFT JOIN criminal_photos cp ON c.id = cp.criminal_id AND cp.is_primary = 1
                                    WHERE c.id = ?
                                ");
                                $stmt->execute([$match['criminal_id']]);
                                $criminalData = $stmt->fetch(PDO::FETCH_ASSOC);
                                
                                if ($criminalData) {
                                    $match['name'] = $criminalData['first_name'] . ' ' . $criminalData['last_name'];
                                    $match['criminal_code'] = $criminalData['criminal_code'];
                                    $match['crime_type'] = $criminalData['crime_type'];
                                    $match['crime_description'] = $criminalData['crime_description'];
                                    $match['danger_level'] = $criminalData['danger_level'];
                                    $match['status'] = $criminalData['status'];
                                    $match['photo'] = $criminalData['photo_path'] ? BASE_URL . $criminalData['photo_path'] : null;
                                }
                            } catch (Exception $e) {
                                error_log('DB Enrich Error: ' . $e->getMessage());
                            }
                        }
                    }
                }
                
                // Log detection to database
                $db = getDB();
                if ($db) {
                    try {
                        $matchCount = count($detectionResult['matches'] ?? []);
                        $stmt = $db->prepare("
                            INSERT INTO detection_logs (session_id, frames_processed, faces_detected, matches_found, duration, notes)
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $_SESSION['user_id'],
                            1,
                            $detectionResult['faces_detected'] ?? 0,
                            $matchCount,
                            0,
                            'Static image detection - ' . $_FILES['image']['name']
                        ]);
                        
                        // Save matched alerts
                        if ($matchCount > 0) {
                            foreach ($detectionResult['matches'] as $match) {
                                $alertStmt = $db->prepare("
                                    INSERT INTO detection_alerts (criminal_id, detected_by_user, confidence_score, notes, alert_status)
                                    VALUES (?, ?, ?, ?, 'new')
                                ");
                                $alertStmt->execute([
                                    $match['criminal_id'],
                                    $_SESSION['user_id'],
                                    round($match['confidence'], 2),
                                    'Static image detection'
                                ]);
                            }
                        }
                    } catch (Exception $e) {
                        error_log('Detection Log Error: ' . $e->getMessage());
                    }
                }
            } else {
                $uploadError = 'Detection failed: ' . ($pythonResponse['error'] ?? 'Unknown error');
            }
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-images"></i> Static Criminal Detection</h1>
    <p class="text-muted">Upload an image to identify if the person is in the criminal database</p>
</div>

<div class="container-fluid">
    <div class="row">
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header bg-primary">
                    <h5 class="text-white mb-0"><i class="fas fa-upload"></i> Upload Image</h5>
                </div>
                <div class="card-body">
                    <?php if ($uploadError): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle"></i> <?= $uploadError ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data" id="detectionForm">
                        <input type="hidden" name="csrf_token" value="<?= getCSRFToken() ?>">
                        
                        <div class="mb-3">
                            <label for="image" class="form-label">Select Image <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="file" class="form-control" id="image" name="image" accept="image/*" required>
                                <button class="btn btn-outline-secondary" type="button" id="captureBtn">
                                    <i class="fas fa-camera"></i> Capture from Camera
                                </button>
                            </div>
                            <small class="form-text text-muted">Supported formats: JPG, PNG, GIF (Max 5MB)</small>
                        </div>

                        <!-- Image Preview -->
                        <div class="mb-3" id="previewContainer" style="display:none;">
                            <label class="form-label">Preview</label>
                            <img id="imagePreview" src="" alt="Preview" class="img-fluid rounded" style="max-height: 300px;">
                        </div>

                        <div class="mb-3">
                            <label for="threshold" class="form-label">Detection Confidence Threshold: <strong id="thresholdValue">55</strong>%</label>
                            <input type="range" class="form-range" id="threshold" name="threshold" min="40" max="100" value="55" step="5">
                            <small class="form-text text-muted"></small>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg" id="detectBtn">
                                <i class="fas fa-search"></i> Scan & Detect
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header bg-info">
                    <h5 class="text-white mb-0"><i class="fas fa-list-check"></i> Detection Results</h5>
                </div>
                <div class="card-body" id="resultsContainer" style="min-height: 400px;">
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-search fa-3x mb-3"></i>
                        <p>Upload an image and click "Scan & Detect" to see results</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden Camera Capture Modal -->
    <div class="modal fade" id="cameraModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-camera"></i> Capture from Camera</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <video id="cameraVideo" width="100%" style="max-width: 400px; display: block; margin: 0 auto; transform: scaleX(-1);" playsinline></video>
                    <canvas id="cameraCanvas" style="display:none;"></canvas>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="capturePhotoBtn">
                        <i class="fas fa-camera"></i> Capture Photo
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Camera and Image handling JavaScript -->
<script>
// Update threshold display
document.getElementById('threshold').addEventListener('input', function() {
    document.getElementById('thresholdValue').textContent = this.value;
});

// Image preview
document.getElementById('image').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(event) {
            document.getElementById('imagePreview').src = event.target.result;
            document.getElementById('previewContainer').style.display = 'block';
        };
        reader.readAsDataURL(file);
    }
});

// Camera capture
let cameraModal;
let cameraStream;

document.getElementById('captureBtn').addEventListener('click', function() {
    cameraModal = new bootstrap.Modal(document.getElementById('cameraModal'));
    cameraModal.show();
    
    // Start camera
    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } })
        .then(stream => {
            cameraStream = stream;
            document.getElementById('cameraVideo').srcObject = stream;
        })
        .catch(err => {
            alert('Error accessing camera: ' + err.message);
        });
});

document.getElementById('capturePhotoBtn').addEventListener('click', function() {
    const video = document.getElementById('cameraVideo');
    const canvas = document.getElementById('cameraCanvas');
    const ctx = canvas.getContext('2d');
    
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    
    // Mirror the image (flip horizontally)
    ctx.scale(-1, 1);
    ctx.drawImage(video, -canvas.width, 0, canvas.width, canvas.height);
    
    // Convert to blob and create file input
    canvas.toBlob(blob => {
        const dataTransfer = new DataTransfer();
        const file = new File([blob], 'camera_capture.jpg', { type: 'image/jpeg' });
        dataTransfer.items.add(file);
        document.getElementById('image').files = dataTransfer.files;
        
        // Show preview
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('imagePreview').src = e.target.result;
            document.getElementById('previewContainer').style.display = 'block';
        };
        reader.readAsDataURL(blob);
        
        // Stop camera stream
        if (cameraStream) {
            cameraStream.getTracks().forEach(track => track.stop());
        }
        
        // Close modal
        if (cameraModal) {
            cameraModal.hide();
        }
    }, 'image/jpeg', 0.9);
});

// Handle form submission
document.getElementById('detectionForm').addEventListener('submit', function(e) {
    const detectBtn = document.getElementById('detectBtn');
    detectBtn.disabled = true;
    detectBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
});
</script>

<!-- Display Detection Results -->
<?php if ($detectionResult): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    displayResults(<?= json_encode($detectionResult) ?>);
});

function displayResults(result) {
    const resultsContainer = document.getElementById('resultsContainer');
    let html = '';
    
    if (result.faces_detected === 0) {
        html = `
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> <strong>No faces detected</strong><br>
                Please upload a clear image with a visible face
            </div>
        `;
    } else if (result.matches && result.matches.length > 0) {
        html = '<h6 class="text-danger mb-3"><i class="fas fa-alert-circle"></i> Criminal(s) Found!</h6>';
        
        result.matches.forEach((match, index) => {
            html += `
                <div class="card mb-3 border-danger">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                ${match.photo ? `<img src="${match.photo}" class="img-fluid rounded" alt="Criminal Photo">` : '<div class="bg-light p-3 rounded text-center text-muted">No photo</div>'}
                                <div class="mt-2 text-center">
                                    <span class="badge bg-danger">MATCH FOUND</span>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <h5 class="card-title text-danger">${match.name || 'Unknown'}</h5>
                                <table class="table table-sm table-borderless">
                                    <tr>
                                        <td><strong>Criminal ID:</strong></td>
                                        <td><code>${match.criminal_id}</code></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Criminal Code:</strong></td>
                                        <td><code>${match.criminal_code || 'N/A'}</code></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Crime Type:</strong></td>
                                        <td><strong class="text-danger">${match.crime_type || 'Unknown'}</strong></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Danger Level:</strong></td>
                                        <td>
                                            <span class="badge ${match.danger_level === 'High' ? 'bg-danger' : match.danger_level === 'Medium' ? 'bg-warning' : 'bg-info'}">
                                                ${match.danger_level || 'Unknown'}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Status:</strong></td>
                                        <td>
                                            <span class="badge ${match.status === 'wanted' ? 'bg-danger' : match.status === 'arrested' ? 'bg-secondary' : 'bg-success'}">
                                                ${match.status || 'Unknown'}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Confidence:</strong></td>
                                        <td>
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar bg-danger" style="width: ${match.confidence}%">
                                                    ${Math.round(match.confidence)}%
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                                <div class="mt-3">
                                    ${match.crime_description ? `<p><strong>Description:</strong> ${match.crime_description}</p>` : ''}
                                    <a href="<?= BASE_URL ?>php/criminals/detail.php?id=${match.criminal_id}" class="btn btn-sm btn-primary">
                                        <i class="fas fa-info-circle"></i> View Full Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
    } else {
        html = `
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <strong>No Criminal Record Found</strong><br>
                The person in this image does not match any records in the criminal database.
            </div>
        `;
    }
    
    resultsContainer.innerHTML = html;
}
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
