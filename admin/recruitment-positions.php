<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn() || !hasRole('quản trị viên')) {
    header('Location: ' . BASE_URL . 'login.php');
    exit();
}

$pageTitle = "Quản lý vị trí tuyển dụng";
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($csrf)) {
        $error = 'Yêu cầu không hợp lệ. Vui lòng thử lại.';
    } elseif (isset($_POST['action']) && $_POST['action'] === 'add') {
        $positionName = sanitize($_POST['position_name'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        
        if ($positionName === '') {
            $error = 'Vui lòng nhập tên vị trí.';
        } else {
            try {
                Database::execute(
                    "INSERT INTO recruitment_positions (position_name, description, is_active) VALUES (?, ?, 1)",
                    [$positionName, $description]
                );
                $success = 'Thêm vị trí thành công.';
                logActivity($_SESSION['user_id'], 'add_position', 'Added recruitment position: ' . $positionName);
            } catch (Exception $e) {
                error_log('Add position error: ' . $e->getMessage());
                $error = 'Lỗi khi thêm vị trí. Vui lòng thử lại.';
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'edit') {
        $positionId = (int)($_POST['position_id'] ?? 0);
        $positionName = sanitize($_POST['position_name'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        
        if ($positionId === 0 || $positionName === '') {
            $error = 'Thông tin không hợp lệ.';
        } else {
            try {
                Database::execute(
                    "UPDATE recruitment_positions SET position_name = ?, description = ? WHERE position_id = ?",
                    [$positionName, $description, $positionId]
                );
                $success = 'Cập nhật vị trí thành công.';
                logActivity($_SESSION['user_id'], 'edit_position', 'Updated recruitment position: ' . $positionName);
            } catch (Exception $e) {
                error_log('Edit position error: ' . $e->getMessage());
                $error = 'Lỗi khi cập nhật vị trí. Vui lòng thử lại.';
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'toggle') {
        $positionId = (int)($_POST['position_id'] ?? 0);
        $isActive = (int)($_POST['is_active'] ?? 0);
        
        if ($positionId === 0) {
            $error = 'Vị trí không hợp lệ.';
        } else {
            try {
                Database::execute(
                    "UPDATE recruitment_positions SET is_active = ? WHERE position_id = ?",
                    [$isActive, $positionId]
                );
                $success = $isActive ? 'Kích hoạt vị trí thành công.' : 'Vô hiệu hóa vị trí thành công.';
                logActivity($_SESSION['user_id'], 'toggle_position', 'Toggled position status: ' . $positionId);
            } catch (Exception $e) {
                error_log('Toggle position error: ' . $e->getMessage());
                $error = 'Lỗi khi cập nhật. Vui lòng thử lại.';
            }
        }
    }
}

// Get all positions
$positions = Database::fetchAll("SELECT * FROM recruitment_positions ORDER BY position_name");

include __DIR__ . '/../includes/header.php';
?>

<section class="py-5 mt-5">
    <div class="container">
        <h1 class="fw-bold mb-4">Quản lý vị trí tuyển dụng</h1>

        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success" role="alert">
                <i class="bi bi-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-4 mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title fw-bold mb-3">Thêm vị trí mới</h5>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                            <input type="hidden" name="action" value="add">
                            
                            <div class="mb-3">
                                <label for="position_name" class="form-label">Tên vị trí *</label>
                                <input type="text" class="form-control" id="position_name" name="position_name" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Mô tả</label>
                                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-success w-100">
                                <i class="bi bi-plus-circle me-2"></i>Thêm vị trí
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title fw-bold mb-3">Danh sách vị trí</h5>
                        
                        <?php if (empty($positions)): ?>
                            <p class="text-muted">Chưa có vị trí nào.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Vị trí</th>
                                            <th>Mô tả</th>
                                            <th>Trạng thái</th>
                                            <th>Hành động</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($positions as $position): ?>
                                        <tr>
                                            <td class="fw-bold"><?php echo htmlspecialchars($position['position_name']); ?></td>
                                            <td>
                                                <small class="text-muted"><?php echo htmlspecialchars(substr($position['description'] ?? '', 0, 50)); ?></small>
                                            </td>
                                            <td>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                                                    <input type="hidden" name="action" value="toggle">
                                                    <input type="hidden" name="position_id" value="<?php echo (int)$position['position_id']; ?>">
                                                    <input type="hidden" name="is_active" value="<?php echo $position['is_active'] ? 0 : 1; ?>">
                                                    <span class="badge <?php echo $position['is_active'] ? 'bg-success' : 'bg-secondary'; ?> me-2">
                                                        <?php echo $position['is_active'] ? 'Hoạt động' : 'Vô hiệu'; ?>
                                                    </span>
                                                    <button type="submit" class="btn btn-sm btn-link p-0" title="Thay đổi trạng thái">
                                                        <i class="bi bi-arrow-left-right"></i>
                                                    </button>
                                                </form>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" onclick="editPosition(<?php echo (int)$position['position_id']; ?>, '<?php echo htmlspecialchars($position['position_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($position['description'] ?? '', ENT_QUOTES); ?>')">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Chỉnh sửa vị trí</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="position_id" id="edit_position_id">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_position_name" class="form-label">Tên vị trí *</label>
                        <input type="text" class="form-control" id="edit_position_name" name="position_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Mô tả</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
function editPosition(positionId, positionName, description) {
    document.getElementById('edit_position_id').value = positionId;
    document.getElementById('edit_position_name').value = positionName;
    document.getElementById('edit_description').value = description;
    
    const editModal = new bootstrap.Modal(document.getElementById('editModal'));
    editModal.show();
}
</script>
