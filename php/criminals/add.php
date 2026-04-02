<?php
// php/criminals/add.php
require_once __DIR__ . '/../auth/middleware.php';

// Only investigators and admins can add criminals
requireInvestigator();

$pageTitle = 'Add Criminal Record';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Security token validation failed. Please try again.';
    } else {
        $db = getDB();
    
    try {
        $db->beginTransaction();

        $criminalCode = generateCriminalCode();
        
        $stmt = $db->prepare("INSERT INTO criminals 
            (criminal_code, first_name, last_name, alias_name, date_of_birth, gender, 
             nationality, id_number, phone, address, city, state, crime_type, 
             crime_description, danger_level, status, last_seen_location, last_seen_date, notes, added_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $criminalCode,
            sanitize($_POST['first_name']),
            sanitize($_POST['last_name']),
            sanitize($_POST['alias_name'] ?? ''),
            $_POST['date_of_birth'] ?: null,
            $_POST['gender'],
            sanitize($_POST['nationality'] ?? ''),
            sanitize($_POST['id_number'] ?? ''),
            sanitize($_POST['phone'] ?? ''),
            sanitize($_POST['address'] ?? ''),
            sanitize($_POST['city'] ?? ''),
            sanitize($_POST['state'] ?? ''),
            sanitize($_POST['crime_type']),
            sanitize($_POST['crime_description'] ?? ''),
            $_POST['danger_level'],
            $_POST['status'] ?? 'wanted',
            sanitize($_POST['last_seen_location'] ?? ''),
            $_POST['last_seen_date'] ?: null,
            sanitize($_POST['notes'] ?? ''),
            $_SESSION['user_id']
        ]);
        
        $criminalId = $db->lastInsertId();

        // Handle multiple photo uploads
        $photosUploaded = 0;
        if (!empty($_FILES['photos']['name'][0])) {
            foreach ($_FILES['photos']['name'] as $key => $name) {
                if ($_FILES['photos']['error'][$key] === UPLOAD_ERR_OK) {
                    $file = [
                        'name' => $_FILES['photos']['name'][$key],
                        'tmp_name' => $_FILES['photos']['tmp_name'][$key],
                        'size' => $_FILES['photos']['size'][$key],
                        'error' => $_FILES['photos']['error'][$key]
                    ];
                    
                    $upload = uploadCriminalPhoto($file, $criminalId);
                    
                    if ($upload['success']) {
                        $isPrimary = ($photosUploaded === 0) ? 1 : 0;
                        $stmtPhoto = $db->prepare("INSERT INTO criminal_photos (criminal_id, photo_path, is_primary) VALUES (?, ?, ?)");
                        $stmtPhoto->execute([$criminalId, $upload['path'], $isPrimary]);
                        
                        $photoId = $db->lastInsertId();
                        
                        // Send to Python for face encoding
                        $fullPath = ROOT_PATH . $upload['path'];
                        $encodingResult = callPythonAPI('/api/encode_face', [
                            'criminal_id' => $criminalId,
                            'photo_id' => $photoId
                        ], 'POST', ['image' => $fullPath]);
                        
                        if (!empty($encodingResult['success'])) {
                            $db->prepare("UPDATE criminal_photos SET face_encoding_stored = 1 WHERE id = ?")
                               ->execute([$photoId]);
                        }
                        
                        $photosUploaded++;
                    }
                }
            }
        }

        $db->commit();
        
        logAction('add_criminal', 'criminals', "Added criminal: $criminalCode - {$_POST['first_name']} {$_POST['last_name']}");
        
        $success = "Criminal record added successfully! Code: $criminalCode | Photos uploaded: $photosUploaded";
        
    } catch (Exception $e) {
        $db->rollBack();
        $error = 'Error: ' . $e->getMessage();
    }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-user-plus me-2"></i>Add Criminal Record</h2>
        <a href="<?= BASE_URL ?>php/criminals/list.php" class="btn btn-outline-info">
            <i class="fas fa-list me-1"></i> View All
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><i class="fas fa-times-circle me-2"></i><?= $error ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?= $success ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
        <?php echo getCSRFTokenInput(); ?>
        <div class="row g-4">
            <!-- Personal Info -->
            <div class="col-lg-8">
                <div class="card dark-card">
                    <div class="card-header"><h5><i class="fas fa-id-card me-2"></i>Personal Information</h5></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">First Name *</label>
                                <input type="text" name="first_name" class="form-control dark-input" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Last Name *</label>
                                <input type="text" name="last_name" class="form-control dark-input" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Alias Name</label>
                                <input type="text" name="alias_name" class="form-control dark-input">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Date of Birth</label>
                                <input type="date" name="date_of_birth" class="form-control dark-input">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Gender *</label>
                                <select name="gender" class="form-select dark-input" required>
                                    <option value="">Select</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Nationality</label>
                                <input type="text" name="nationality" class="form-control dark-input">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">ID Number</label>
                                <input type="text" name="id_number" class="form-control dark-input">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Phone</label>
                                <input type="text" name="phone" class="form-control dark-input">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">City</label>
                                <input type="text" name="city" class="form-control dark-input">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">State</label>
                                <input type="text" name="state" class="form-control dark-input">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Address</label>
                                <input type="text" name="address" class="form-control dark-input">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Crime Details -->
                <div class="card dark-card mt-4">
                    <div class="card-header"><h5><i class="fas fa-gavel me-2"></i>Crime Details</h5></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Crime Type *</label>
                                <select name="crime_type" class="form-select dark-input" required>
                                    <option value="">Select Crime</option>
                                    <option value="Murder">Murder</option>
                                    <option value="Robbery">Robbery</option>
                                    <option value="Assault">Assault</option>
                                    <option value="Kidnapping">Kidnapping</option>
                                    <option value="Drug Trafficking">Drug Trafficking</option>
                                    <option value="Fraud">Fraud</option>
                                    <option value="Cybercrime">Cybercrime</option>
                                    <option value="Terrorism">Terrorism</option>
                                    <option value="Human Trafficking">Human Trafficking</option>
                                    <option value="Theft">Theft</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Danger Level *</label>
                                <select name="danger_level" class="form-select dark-input" required>
                                    <option value="low">Low</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="high">High</option>
                                    <option value="critical">Critical</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Status *</label>
                                <select name="status" class="form-select dark-input" required>
                                    <option value="wanted" selected>Wanted</option>
                                    <option value="arrested">Arrested</option>
                                    <option value="released">Released</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Crime Description</label>
                                <textarea name="crime_description" class="form-control dark-input" rows="3"></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Last Seen Location</label>
                                <input type="text" name="last_seen_location" class="form-control dark-input">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Last Seen Date</label>
                                <input type="date" name="last_seen_date" class="form-control dark-input">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Additional Notes</label>
                                <textarea name="notes" class="form-control dark-input" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Photos Upload -->
            <div class="col-lg-4">
                <div class="card dark-card">
                    <div class="card-header"><h5><i class="fas fa-camera me-2"></i>Photos (Face Images)</h5></div>
                    <div class="card-body">
                        <div class="photo-upload-zone" id="photoDropZone">
                            <i class="fas fa-cloud-upload-alt fa-3x mb-3 text-info"></i>
                            <p>Drag & drop photos or click to browse</p>
                            <small class="text-muted">Upload multiple clear face photos for better detection accuracy</small>
                            <input type="file" name="photos[]" id="photoInput" multiple accept="image/*" class="d-none">
                        </div>
                        <div id="photoPreview" class="mt-3 row g-2"></div>
                        
                        <div class="alert alert-info mt-3" style="background:rgba(0,212,255,0.1);border-color:rgba(0,212,255,0.3);color:#8de4ff;font-size:13px;">
                            <i class="fas fa-info-circle me-1"></i>
                            <strong>Tips:</strong><br>
                            • Upload 3-5 photos from different angles<br>
                            • Ensure face is clearly visible<br>
                            • Good lighting improves accuracy<br>
                            • Supported: JPG, PNG, WEBP
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 mt-4 py-3" style="background:linear-gradient(135deg,#00d4ff,#0099ff);border:none;font-size:16px;">
                    <i class="fas fa-save me-2"></i> Save Criminal Record & Train
                </button>
            </div>
        </div>
    </form>
</div>

<script>
// Photo upload drag & drop
const dropZone = document.getElementById('photoDropZone');
const photoInput = document.getElementById('photoInput');
const preview = document.getElementById('photoPreview');

dropZone.onclick = () => photoInput.click();
dropZone.ondragover = (e) => { e.preventDefault(); dropZone.classList.add('dragover'); };
dropZone.ondragleave = () => dropZone.classList.remove('dragover');
dropZone.ondrop = (e) => {
    e.preventDefault();
    dropZone.classList.remove('dragover');
    photoInput.files = e.dataTransfer.files;
    showPreviews();
};
photoInput.onchange = showPreviews;

function showPreviews() {
    preview.innerHTML = '';
    Array.from(photoInput.files).forEach((file, i) => {
        const reader = new FileReader();
        reader.onload = (e) => {
            preview.innerHTML += `
                <div class="col-6">
                    <div class="position-relative">
                        <img src="${e.target.result}" class="img-fluid rounded" 
                             style="height:100px;width:100%;object-fit:cover;border:2px solid rgba(0,212,255,0.3);">
                        <small class="d-block text-center text-muted mt-1">${file.name.substring(0,15)}...</small>
                    </div>
                </div>`;
        };
        reader.readAsDataURL(file);
    });
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>