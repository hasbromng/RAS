<?php
/**
 * Users Content Page (Simple placeholder for MVP)
 */

// Demo users for MVP
$users = [
    ['id' => 1, 'username' => 'admin', 'email' => 'admin@ras.local', 'role' => 'Administrator', 'status' => 'active', 'last_login' => date('Y-m-d H:i:s')],
];
?>

<div class="card">
    <div class="card-content">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h5 class="card-title" style="margin: 0;">
                <i class="material-icons">people</i>
                Manajemen Pengguna
            </h5>
            <button class="btn btn-primary">
                <i class="material-icons left">add</i>
                Tambah User
            </button>
        </div>

        <div class="alert alert-info">
            <i class="material-icons tiny">info</i>
            Fitur manajemen pengguna lengkap akan tersedia di versi berikutnya.
        </div>

        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <i class="material-icons" style="color: #667eea;">account_circle</i>
                                    <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <span class="status-badge" style="background: #e3f2fd; color: #0277bd;">
                                    <?php echo htmlspecialchars($user['role']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $user['status'] === 'active' ? 'online' : 'offline'; ?>">
                                    <?php echo ucfirst($user['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M j, H:i', strtotime($user['last_login'])); ?></td>
                            <td>
                                <button class="btn btn-sm">
                                    <i class="material-icons tiny">edit</i>
                                </button>
                                <button class="btn btn-sm" style="background: #ff5252;">
                                    <i class="material-icons tiny">delete</i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
