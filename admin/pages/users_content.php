<?php
/**
 * Users Content Page
 */

try {
    $pdo = getDbConnection();

    // Handle Form Submissions
    $success_msg = '';
    $error_msg = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            if ($_POST['action'] === 'create_user') {
                $username = trim($_POST['username'] ?? '');
                $password = $_POST['password'] ?? '';
                $email = trim($_POST['email'] ?? '');
                $role = $_POST['role'] ?? 'Administrator';

                if (empty($username) || empty($password)) {
                    $error_msg = "Username dan Password wajib diisi.";
                } else {
                    // Check if exists
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                    $stmt->execute([$username]);
                    if ($stmt->fetch()) {
                        $error_msg = "Username sudah digunakan.";
                    } else {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("INSERT INTO users (username, password, email, role, status) VALUES (?, ?, ?, ?, 'active')");
                        if ($stmt->execute([$username, $hash, $email, $role])) {
                            $success_msg = "User baru berhasil ditambahkan.";
                            logActivity($pdo, 'USER_CREATE', "Admin membuat user baru: {$username}", 'INFO');
                        } else {
                            $error_msg = "Gagal menyimpan ke database.";
                        }
                    }
                }
            } elseif ($_POST['action'] === 'delete_user') {
                $id = (int)$_POST['user_id'];
                // Prevent self-deletion if they use sessions later, for now just prevent deleting user 1 (admin)
                if ($id === 1) {
                    $error_msg = "Tidak dapat menghapus super admin.";
                } else {
                    // get username for log
                    $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
                    $stmt->execute([$id]);
                    $usr = $stmt->fetch();
                    
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    if ($stmt->execute([$id])) {
                        $success_msg = "User berhasil dihapus.";
                        if ($usr) logActivity($pdo, 'USER_DELETE', "Admin menghapus user: {$usr['username']}", 'WARNING');
                    } else {
                        $error_msg = "Gagal menghapus user.";
                    }
                }
            }
        }
    }

    // Fetch users
    $stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
    $users = $stmt->fetchAll();

} catch (PDOException $e) {
    $db_error = $e->getMessage();
    $users = [];
}
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
    <div>
        <h2 class="page-title" style="margin: 0; display: flex; align-items: center; gap: 8px;">
            <i class="material-icons" style="font-size: 28px; color: var(--primary-color);">people</i>
            Manajemen Pengguna
        </h2>
        <p class="text-secondary" style="margin: 4px 0 0 0; font-size: 14px;">Kelola daftar pengguna sistem dan hak akses.</p>
    </div>
    <div>
        <button class="btn btn-primary" onclick="openModal('modal-add-user')" style="display: inline-flex; align-items: center; gap: 6px;">
            <i class="material-icons" style="font-size: 16px;">add</i>
            Tambah User
        </button>
    </div>
</div>

<?php if (isset($db_error)): ?>
    <div class="alert alert-danger">
        <i class="material-icons tiny">error</i>
        Gagal memuat pengguna: <?php echo htmlspecialchars($db_error); ?>
    </div>
<?php endif; ?>
<?php if ($error_msg): ?>
    <div class="alert alert-danger">
        <i class="material-icons tiny">error</i>
        <?php echo htmlspecialchars($error_msg); ?>
    </div>
<?php endif; ?>
<?php if ($success_msg): ?>
    <div class="alert alert-success">
        <i class="material-icons tiny">check_circle</i>
        <?php echo htmlspecialchars($success_msg); ?>
    </div>
<?php endif; ?>

<div class="surface-panel" style="padding: 0; overflow: hidden;">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 250px;">User</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Last Login</th>
                    <th style="width: 100px; text-align: right;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <div style="width: 36px; height: 36px; border-radius: 50%; background: var(--bg-surface-3); display: flex; align-items: center; justify-content: center; color: var(--text-secondary);">
                                    <i class="material-icons" style="font-size: 20px;">person</i>
                                </div>
                                <div>
                                    <div style="font-weight: 600; color: var(--text-primary); font-size: 14px;"><?php echo htmlspecialchars($user['username']); ?></div>
                                    <div style="color: var(--text-secondary); font-size: 12px;"><?php echo htmlspecialchars($user['email'] ?: '-'); ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span style="font-size: 12px; padding: 4px 8px; border-radius: 6px; background: rgba(59, 130, 246, 0.1); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.2);">
                                <?php echo htmlspecialchars($user['role']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($user['status'] === 'active'): ?>
                                <span style="font-size: 12px; padding: 4px 8px; border-radius: 6px; background: rgba(34, 197, 94, 0.1); color: #22c55e; border: 1px solid rgba(34, 197, 94, 0.2); display: inline-flex; align-items: center; gap: 4px;">
                                    <span style="width: 6px; height: 6px; border-radius: 50%; background: #22c55e;"></span> Active
                                </span>
                            <?php else: ?>
                                <span style="font-size: 12px; padding: 4px 8px; border-radius: 6px; background: rgba(100, 116, 139, 0.1); color: var(--text-secondary); border: 1px solid rgba(100, 116, 139, 0.2); display: inline-flex; align-items: center; gap: 4px;">
                                    <span style="width: 6px; height: 6px; border-radius: 50%; background: var(--text-muted);"></span> <?php echo ucfirst($user['status']); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size: 13px; color: var(--text-secondary);">
                            <?php echo $user['last_login'] ? date('M j, Y H:i', strtotime($user['last_login'])) : 'Belum pernah'; ?>
                        </td>
                        <td style="text-align: right;">
                            <div style="display: flex; gap: 6px; justify-content: flex-end;">
                                <?php if ($user['id'] != 1): ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Anda yakin ingin menghapus user ini?');">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" class="btn btn-tiny btn-outline" style="border-color: rgba(239, 68, 68, 0.3); color: #ef4444;" title="Hapus User">
                                            <i class="material-icons" style="font-size: 16px;">delete</i>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span style="font-size: 11px; color: var(--text-muted); font-style: italic; margin-right: 8px;">Super Admin</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add User Modal -->
<div id="modal-add-user" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(2px);">
    <div class="modal-content" style="background: var(--bg-surface); width: 100%; max-width: 400px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); overflow: hidden; border: 1px solid var(--border-color);">
        <div style="padding: 16px 20px; border-bottom: 1px solid var(--border-color); background: var(--bg-surface-2); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 16px; color: var(--text-primary); display: flex; align-items: center; gap: 8px;">
                <i class="material-icons" style="font-size: 20px; color: var(--primary-color);">person_add</i> Tambah User Baru
            </h3>
            <button type="button" onclick="closeModal('modal-add-user')" style="background: none; border: none; color: var(--text-muted); cursor: pointer; padding: 4px;">
                <i class="material-icons" style="font-size: 20px;">close</i>
            </button>
        </div>
        
        <form method="POST" style="padding: 20px;">
            <input type="hidden" name="action" value="create_user">
            
            <div class="form-group" style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 6px; font-size: 13px; color: var(--text-secondary);">Username <span style="color: #ef4444;">*</span></label>
                <input type="text" name="username" class="form-control" required style="width: 100%;" autocomplete="off">
            </div>
            
            <div class="form-group" style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 6px; font-size: 13px; color: var(--text-secondary);">Password <span style="color: #ef4444;">*</span></label>
                <input type="password" name="password" class="form-control" required style="width: 100%;">
            </div>
            
            <div class="form-group" style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 6px; font-size: 13px; color: var(--text-secondary);">Email</label>
                <input type="email" name="email" class="form-control" style="width: 100%;">
            </div>
            
            <div class="form-group" style="margin-bottom: 24px;">
                <label style="display: block; margin-bottom: 6px; font-size: 13px; color: var(--text-secondary);">Role</label>
                <select name="role" class="form-select" style="width: 100%;">
                    <option value="Administrator">Administrator</option>
                    <option value="Operator">Operator</option>
                    <option value="Viewer">Viewer</option>
                </select>
            </div>
            
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" class="btn btn-outline" onclick="closeModal('modal-add-user')">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan User</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) {
    var modal = document.getElementById(id);
    if(modal) {
        modal.style.display = 'flex';
    }
}
function closeModal(id) {
    var modal = document.getElementById(id);
    if(modal) {
        modal.style.display = 'none';
    }
}
</script>
