<?php
// php/criminals/list.php
require_once __DIR__ . '/../auth/middleware.php';
$pageTitle = 'Criminal Records';

$db = getDB();
$criminals = $db->query("
    SELECT c.*, 
           cp.photo_path,
           (SELECT COUNT(*) FROM criminal_photos WHERE criminal_id = c.id) as photo_count,
           (SELECT COUNT(*) FROM face_encodings WHERE criminal_id = c.id) as encoding_count,
           (SELECT COUNT(*) FROM detection_alerts WHERE criminal_id = c.id) as alert_count,
           u.full_name as added_by_name
    FROM criminals c
    LEFT JOIN criminal_photos cp ON c.id = cp.criminal_id AND cp.is_primary = 1
    LEFT JOIN users u ON c.added_by = u.id
    WHERE c.is_active = 1
    ORDER BY c.created_at DESC
")->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="main-content">
    <div class="page-header">
        <h2><i class="fas fa-user-secret me-2"></i>Criminal Records</h2>
        <div>
            <a href="<?= BASE_URL ?>php/criminals/add.php" class="btn btn-info">
                <i class="fas fa-plus me-1"></i> Add New
            </a>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="card dark-card mb-4">
        <div class="card-body py-2">
            <div class="row g-2 align-items-center">
                <div class="col-md-3">
                    <select id="filterDanger" class="form-select form-select-sm dark-input">
                        <option value="">All Danger Levels</option>
                        <option value="critical">Critical</option>
                        <option value="high">High</option>
                        <option value="medium">Medium</option>
                        <option value="low">Low</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select id="filterStatus" class="form-select form-select-sm dark-input">
                        <option value="">All Statuses</option>
                        <option value="wanted">Wanted</option>
                        <option value="arrested">Arrested</option>
                        <option value="released">Released</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select id="filterEncoding" class="form-select form-select-sm dark-input">
                        <option value="">All</option>
                        <option value="encoded">Face Encoded</option>
                        <option value="not_encoded">Not Encoded</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card dark-card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-dark table-hover mb-0" id="criminalsTable">
                    <thead>
                        <tr>
                            <th>Photo</th>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Crime</th>
                            <th>Danger</th>
                            <th>Status</th>
                            <th>Photos</th>
                            <th>Encoded</th>
                            <th>Alerts</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($criminals as $c): ?>
                        <tr data-danger="<?= $c['danger_level'] ?>" data-status="<?= $c['status'] ?>" 
                            data-encoding="<?= $c['encoding_count'] > 0 ? 'encoded' : 'not_encoded' ?>">
                            <td>
                                <img src="<?= BASE_URL . ($c['photo_path'] ?? 'assets/images/no-photo.png') ?>" 
                                     class="rounded-circle" width="45" height="45" style="object-fit:cover;">
                            </td>
                            <td><code><?= $c['criminal_code'] ?></code></td>
                            <td>
                                <strong><?= sanitize($c['first_name'] . ' ' . $c['last_name']) ?></strong>
                                <?php if ($c['alias_name']): ?>
                                    <br><small class="text-muted">aka <?= sanitize($c['alias_name']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= sanitize($c['crime_type']) ?></td>
                            <td><?= getDangerBadge($c['danger_level']) ?></td>
                            <td><?= getStatusBadge($c['status']) ?></td>
                            <td><span class="badge bg-secondary"><?= $c['photo_count'] ?></span></td>
                            <td>
                                <?php if ($c['encoding_count'] > 0): ?>
                                    <span class="badge bg-success"><i class="fas fa-check"></i> <?= $c['encoding_count'] ?></span>
                                <?php else: ?>
                                    <span class="badge bg-warning"><i class="fas fa-times"></i></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($c['alert_count'] > 0): ?>
                                    <span class="badge bg-danger"><?= $c['alert_count'] ?></span>
                                <?php else: ?>
                                    <span class="text-muted">0</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="<?= BASE_URL ?>php/criminals/detail.php?id=<?= $c['id'] ?>" 
                                       class="btn btn-outline-info" title="View"><i class="fas fa-eye"></i></a>
                                    <a href="<?= BASE_URL ?>php/criminals/edit.php?id=<?= $c['id'] ?>" 
                                       class="btn btn-outline-warning" title="Edit"><i class="fas fa-edit"></i></a>
                                    <button class="btn btn-outline-danger" onclick="deleteCriminal(<?= $c['id'] ?>)" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    const table = $('#criminalsTable').DataTable({
        pageLength: 25,
        order: [[1, 'desc']],
        language: { search: '', searchPlaceholder: 'Search criminals...' }
    });

    // Custom filters
    ['filterDanger', 'filterStatus', 'filterEncoding'].forEach(id => {
        document.getElementById(id).addEventListener('change', function() {
            const attr = id.replace('filter', '').toLowerCase();
            const val = this.value;
            table.rows().every(function() {
                const row = this.node();
                const match = !val || row.dataset[attr === 'encoding' ? 'encoding' : attr] === val;
                row.style.display = match ? '' : 'none';
            });
        });
    });
});

function deleteCriminal(id) {
    Swal.fire({
        title: 'Delete Criminal Record?',
        text: 'This will remove all associated photos and encodings.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Delete'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('<?= BASE_URL ?>php/criminals/delete.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({id: id})
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Deleted!', data.message, 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error', data.error, 'error');
                }
            });
        }
    });
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>