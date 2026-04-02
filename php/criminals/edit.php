<?php
require_once __DIR__ . '/../auth/middleware.php';

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    redirect('php/criminals/list.php');
}

$db = getDB();

// Get criminal
$stmt = $db->prepare("SELECT * FROM criminals WHERE id = ? AND is_active = 1");
$stmt->execute([$id]);
$criminal = $stmt->fetch();

if (!$criminal) {
    $_SESSION['error'] = 'Criminal record not found.';
    redirect('php/criminals/list.php');
}

// Get existing photos
$photos = $db->prepare("SELECT * FROM criminal_photos WHERE criminal_id = ? ORDER BY is_primary DESC");
$photos->execute([$id]);
$existingPhotos = $photos->fetchAll();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();

        $stmt = $db->prepare("UPDATE criminals SET 
            first_name = ?, last_name = ?, alias_name = ?, date_of_birth = ?,
            gender = ?, nationality = ?, id_number = ?, phone = ?,
            address = ?, city = ?, state = ?, crime_type = ?,
            crime_description = ?, danger_level = ?, status = ?,
            last_seen_location = ?, last_seen_date = ?, notes = ?
            WHERE id = ?");
        
        $stmt->execute([
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
            $id
        ]);

        // Handle new photo uploads
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
                    
                    $upload = uploadCriminalPhoto($file, $id);
                    
                    if ($upload['success']) {
                        $hasPrimary = $db->prepare("SELECT COUNT(*) FROM criminal_photos WHERE criminal_id = ? AND is_primary = 1");
                        $hasPrimary->execute([$id]);
                        $isPrimary = ($hasPrimary->fetchColumn() == 0) ? 1 : 0;

                        $stmtPhoto = $db->prepare("INSERT INTO criminal_photos (criminal_id, photo_path, is_primary) VALUES (?, ?, ?)");
                        $stmtPhoto->execute([$id, $upload['path'], $isPrimary]);
                        
                        $photoId = $db->lastInsertId();
                        
                        $fullPath = ROOT_PATH . $upload['path'];
                        $encodingResult = callPythonAPI('/api/encode_face', [
                            'criminal_id' => $id,
                            'photo_id' => $photoId
                        ], 'POST', ['image' => $fullPath]);
                        
                        if (!empty($encodingResult['success'])) {
                            $db->prepare("UPDATE criminal_photos SET face_encoding_stored = 1 WHERE id = ?")->execute([$photoId]);
                        }
                        
                        $photosUploaded++;
                    }
                }
            }
        }

        // Handle photo deletion
        if (!empty($_POST['delete_photos'])) {
            foreach ($_POST['delete_photos'] as $photoId) {
                $photoId = intval($photoId);
                $photoStmt = $db->prepare("SELECT photo_path FROM criminal_photos WHERE id = ? AND criminal_id = ?");
                $photoStmt->execute([$photoId, $id]);
                $photo = $photoStmt->fetch();
                
                if ($photo) {
                    $filePath = ROOT_PATH . $photo['photo_path'];
                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }
                    $db->prepare("DELETE FROM face_encodings WHERE photo_id = ?")->execute([$photoId]);
                    $db->prepare("DELETE FROM criminal_photos WHERE id = ?")->execute([$photoId]);
                }
            }
        }

        $db->commit();
        
        logAction('edit_criminal', 'criminals', "Edited criminal ID: $id, Code: {$criminal['criminal_code']}");
        
        $success = "Criminal record updated successfully!";
        if ($photosUploaded > 0) {
            $success .= " $photosUploaded new photo(s) uploaded.";
        }
        
        // Refresh criminal data
        $stmt = $db->prepare("SELECT * FROM criminals WHERE id = ?");
        $stmt->execute([$id]);
        $criminal = $stmt->fetch();
        
        $photos = $db->prepare("SELECT * FROM criminal_photos WHERE criminal_id = ? ORDER BY is_primary DESC");
        $photos->execute([$id]);
        $existingPhotos = $photos->fetchAll();

    } catch (Exception $e) {
        $db->rollBack();
        $error = 'Error: ' . $e->getMessage();
    }
}

$pageTitle = 'Edit - ' . $criminal['first_name'] . ' ' . $criminal['last_name'];
include __DIR__ . '/../../includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-edit me-2"></i>Edit Criminal Record</h2>
        <div class="d-flex gap-2">
            <a href="<?= BASE_URL ?>php/criminals/detail.php?id=<?= $id ?>" class="btn btn-outline-info">
                <i class="fas fa-eye me-1"></i> View Details
            </a>
            <a href="<?= BASE_URL ?>php/criminals/list.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><i class="fas fa-times-circle me-2"></i><?= $error ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?= $success ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <div class="row g-4">
            <div class="col-lg-8">
                <!-- Personal Info -->
                <div class="card dark-card">
                    <div class="card-header">
                        <h5><i class="fas fa-id-card me-2"></i>Personal Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Criminal Code</label>
                                <input type="text" class="form-control dark-input" value="<?= $criminal['criminal_code'] ?>" disabled>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">First Name *</label>
                                <input type="text" name="first_name" class="form-control dark-input" required
                                       value="<?= sanitize($criminal['first_name']) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Last Name *</label>
                                <input type="text" name="last_name" class="form-control dark-input" required
                                       value="<?= sanitize($criminal['last_name']) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Alias Name</label>
                                <input type="text" name="alias_name" class="form-control dark-input"
                                       value="<?= sanitize($criminal['alias_name']) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Date of Birth</label>
                                <input type="date" name="date_of_birth" class="form-control dark-input"
                                       value="<?= $criminal['date_of_birth'] ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Gender *</label>
                                <select name="gender" class="form-select dark-input" required>
                                    <option value="male" <?= $criminal['gender'] === 'male' ? 'selected' : '' ?>>Male</option>
                                    <option value="female" <?= $criminal['gender'] === 'female' ? 'selected' : '' ?>>Female</option>
                                    <option value="other" <?= $criminal['gender'] === 'other' ? 'selected' : '' ?>>Other</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Nationality</label>
                                <input type="text" name="nationality" class="form-control dark-input"
                                       value="<?= sanitize($criminal['nationality']) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">ID Number</label>
                                <input type="text" name="id_number" class="form-control dark-input"
                                       value="<?= sanitize($criminal['id_number']) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Phone</label>
                                <input type="text" name="phone" class="form-control dark-input"
                                       value="<?= sanitize($criminal['phone']) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">City</label>
                                <input type="text" name="city" class="form-control dark-input"
                                       value="<?= sanitize($criminal['city']) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">State</label>
                                <input type="text" name="state" class="form-control dark-input"
                                       value="<?= sanitize($criminal['state']) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Address</label>
                                <input type="text" name="address" class="form-control dark-input"
                                       value="<?= sanitize($criminal['address']) ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Crime Details -->
                <div class="card dark-card mt-3">
                    <div class="card-header">
                        <h5><i class="fas fa-gavel me-2"></i>Crime Details</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Crime Type *</label>
                                <select name="crime_type" class="form-select dark-input" required>
                                    <?php
                                    $crimeTypes = ['Murder','Robbery','Assault','Kidnapping','Drug Trafficking',
                                                   'Fraud','Cybercrime','Terrorism','Human Trafficking','Theft','Other'];
                                    foreach ($crimeTypes as $type): ?>
                                        <option value="<?= $type ?>" <?= $criminal['crime_type'] === $type ? 'selected' : '' ?>><?= $type ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Danger Level *</label>
                                <select name="danger_level" class="form-select dark-input" required>
                                    <option value="low" <?= $criminal['danger_level'] === 'low' ? 'selected' : '' ?>>Low</option>
                                    <option value="medium" <?= $criminal['danger_level'] === 'medium' ? 'selected' : '' ?>>Medium</option>
                                    <option value="high" <?= $criminal['danger_level'] === 'high' ? 'selected' : '' ?>>High</option>
                                    <option value="critical" <?= $criminal['danger_level'] === 'critical' ? 'selected' : '' ?>>Critical</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Status *</label>
                                <select name="status" class="form-select dark-input" required>
                                    <option value="wanted" <?= $criminal['status'] === 'wanted' ? 'selected' : '' ?>>Wanted</option>
                                    <option value="arrested" <?= $criminal['status'] === 'arrested' ? 'selected' : '' ?>>Arrested</option>
                                    <option value="released" <?= $criminal['status'] === 'released' ? 'selected' : '' ?>>Released</option>
                                    <option value="deceased" <?= $criminal['status'] === 'deceased' ? 'selected' : '' ?>>Deceased</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Crime Description</label>
                                <textarea name="crime_description" class="form-control dark-input" rows="3"><?= sanitize($criminal['crime_description']) ?></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Last Seen Location</label>
                                <input type="text" name="last_seen_location" class="form-control dark-input"
                                       value="<?= sanitize($criminal['last_seen_location']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Last Seen Date</label>
                                <input type="date" name="last_seen_date" class="form-control dark-input"
                                       value="<?= $criminal['last_seen_date'] ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Notes</label>
                                <textarea name="notes" class="form-control dark-input" rows="2"><?= sanitize($criminal['notes']) ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Photos Column -->
            <div class="col-lg-4">
                <!-- Existing Photos -->
                <div class="card dark-card">
                    <div class="card-header">
                        <h5><i class="fas fa-images me-2"></i>Existing Photos</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($existingPhotos)): ?>
                            <p class="text-muted text-center">No photos</p>
                        <?php else: ?>
                            <div class="row g-2">
                                <?php foreach ($existingPhotos as $photo): ?>
                                    <div class="col-6">
                                        <div class="position-relative">
                                            <img src="<?= BASE_URL . $photo['photo_path'] ?>" 
                                                 class="img-fluid rounded"
                                                 style="height:80px;width:100%;object-fit:cover;">
                                            <div class="form-check position-absolute bottom-0 start-0 m-1">
                                                <input class="form-check-input" type="checkbox" 
                                                       name="delete_photos[]" value="<?= $photo['id'] ?>"
                                                       id="delPhoto<?= $photo['id'] ?>">
                                                <label class="form-check-label text-danger small" for="delPhoto<?= $photo['id'] ?>">
                                                    Delete
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Upload New Photos -->
                <div class="card dark-card mt-3">
                    <div class="card-header">
                        <h5><i class="fas fa-upload me-2"></i>Add New Photos</h5>
                    </div>
                    <div class="card-body">
                        <div class="photo-upload-zone" onclick="document.getElementById('photoInput').click()">
                            <i class="fas fa-cloud-upload-alt fa-2x mb-2 text-info"></i>
                            <p class="mb-0">Click to upload new photos</p>
                            <input type="file" name="photos[]" id="photoInput" multiple accept="image/*" class="d-none"
                                   onchange="showPreviews(this)">
                        </div>
                        <div id="photoPreview" class="mt-2 row g-2"></div>
                    </div>
                </div>

                <button type="submit" class="btn btn-warning w-100 mt-3 py-3" style="font-size:16px;">
                    <i class="fas fa-save me-2"></i> Update Criminal Record
                </button>
            </div>
        </div>
    </form>
</div>

<script>
function showPreviews(input) {
    const preview = document.getElementById('photoPreview');
    preview.innerHTML = '';
    Array.from(input.files).forEach(file => {
        const reader = new FileReader();
        reader.onload = (e) => {
            preview.innerHTML += `
                <div class="col-6">
                    <img src="${e.target.result}" class="img-fluid rounded" 
                         style="height:70px;width:100%;object-fit:cover;border:2px solid rgba(0,212,255,0.3);">
                </div>`;
        };
        reader.readAsDataURL(file);
    });
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>