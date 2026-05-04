<?php
session_start();
require_once '../koneksi.php';

// Cek apakah user sudah login dan role-nya admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_nama = $_SESSION['nama'];

// Query untuk mendapatkan semua users
$users_query = $pdo->prepare("
    SELECT * FROM users ORDER BY role, nama
");
$users_query->execute();
$users = $users_query->fetchAll();

// Proses edit user jika ada form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $id_user = $_POST['id_user'];
    $nama = $_POST['nama'];
    $username = $_POST['username'];
    $telp = $_POST['telp'];
    $role = $_POST['role'];
    
    // Cek apakah username sudah ada (selain user ini)
    $cek_username = $pdo->prepare("SELECT id_user FROM users WHERE username = ? AND id_user != ?");
    $cek_username->execute([$username, $id_user]);
    
    if ($cek_username->rowCount() > 0) {
        $error_msg = "Username sudah digunakan oleh user lain!";
    } else {
        // Update tanpa password
        if (empty($_POST['password'])) {
            $edit_query = $pdo->prepare("
                UPDATE users SET nama = ?, username = ?, telp = ?, role = ? 
                WHERE id_user = ?
            ");
            $edit_query->execute([$nama, $username, $telp, $role, $id_user]);
        } else {
            // Update dengan password baru
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $edit_query = $pdo->prepare("
                UPDATE users SET nama = ?, username = ?, password = ?, telp = ?, role = ? 
                WHERE id_user = ?
            ");
            $edit_query->execute([$nama, $username, $password, $telp, $role, $id_user]);
        }
        
        if ($edit_query->rowCount() > 0) {
            header('Location: kelola_user.php?success=edit');
            exit();
        } else {
            $error_msg = "Gagal mengupdate user!";
        }
    }
}

// Proses hapus user jika ada request
if (isset($_GET['hapus']) && is_numeric($_GET['hapus'])) {
    $id_hapus = $_GET['hapus'];
    
    // Cek apakah user ada dan bukan diri sendiri
    if ($id_hapus != $user_id) {
        $cek_query = $pdo->prepare("SELECT * FROM users WHERE id_user = ?");
        $cek_query->execute([$id_hapus]);
        $user_hapus = $cek_query->fetch();
        
        if ($user_hapus) {
            // Hapus semua pengaduan dan tanggapan user ini terlebih dahulu
            // Hapus tanggapan dari user ini (jika petugas)
            $hapus_tanggapan = $pdo->prepare("DELETE FROM tanggapan WHERE id_petugas = ?");
            $hapus_tanggapan->execute([$id_hapus]);
            
            // Hapus pengaduan dari user ini (jika user biasa)
            $hapus_pengaduan = $pdo->prepare("DELETE FROM pengaduan WHERE id_user = ?");
            $hapus_pengaduan->execute([$id_hapus]);
            
            // Hapus user
            $hapus_query = $pdo->prepare("DELETE FROM users WHERE id_user = ?");
            if ($hapus_query->execute([$id_hapus])) {
                header('Location: kelola_user.php?success=hapus');
                exit();
            } else {
                $error_msg = "Gagal menghapus user!";
            }
        }
    } else {
        $error_msg = "Tidak dapat menghapus akun sendiri!";
    }
}

// Pesan notifikasi
$success_msg = '';
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'edit':
            $success_msg = 'User berhasil diupdate!';
            break;
        case 'hapus':
            $success_msg = 'User berhasil dihapus!';
            break;
    }
}

// Get filter value
$filter_role = isset($_GET['role']) ? $_GET['role'] : '';
$filter_search = isset($_GET['search']) ? $_GET['search'] : '';

// Filter users
$filtered_users = $users;
if ($filter_role) {
    $filtered_users = array_filter($filtered_users, function($item) use ($filter_role) {
        return $item['role'] == $filter_role;
    });
}
if ($filter_search) {
    $filter_search_lower = strtolower($filter_search);
    $filtered_users = array_filter($filtered_users, function($item) use ($filter_search_lower) {
        return strpos(strtolower($item['nama']), $filter_search_lower) !== false ||
               strpos(strtolower($item['username']), $filter_search_lower) !== false ||
               strpos(strtolower($item['telp']), $filter_search_lower) !== false;
    });
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola User - Sistem Pengaduan Masyarakat</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f8f9fa;
            color: #333;
        }
        
        .container {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar Styles */
        .sidebar {
            width: 250px;
            background: #2c3e50;
            color: white;
            padding: 20px 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }
        
        .logo {
            text-align: center;
            padding: 20px;
            border-bottom: 1px solid #34495e;
            margin-bottom: 20px;
        }
        
        .logo h2 {
            font-size: 1.5rem;
        }
        
        .logo span {
            color: #3498db;
        }
        
        .user-info {
            text-align: center;
            padding: 15px;
            border-bottom: 1px solid #34495e;
            margin-bottom: 20px;
        }
        
        .user-info h3 {
            margin-bottom: 5px;
        }
        
        .user-info .role {
            background: #e74c3c;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.8rem;
        }
        
        .nav-links {
            list-style: none;
        }
        
        .nav-links li {
            margin-bottom: 5px;
        }
        
        .nav-links a {
            display: block;
            color: white;
            text-decoration: none;
            padding: 12px 20px;
            transition: background 0.3s;
        }
        
        .nav-links a:hover, .nav-links a.active {
            background: #34495e;
            border-left: 4px solid #3498db;
        }
        
        .nav-links a.logout:hover {
            background: #34495e;
        }
        
        /* Main Content Styles */
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 20px;
        }
        
        .header {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .header h1 {
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        /* Notifikasi */
        .notification {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: none;
        }
        
        .notification.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            display: block;
        }
        
        .notification.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            display: block;
        }
        
        /* Filter Section */
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .filter-form {
            display: flex;
            gap: 15px;
            align-items: end;
            flex-wrap: wrap;
        }
        
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #2c3e50;
        }
        
        .filter-group input, .filter-group select {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 1rem;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.3s;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: #3498db;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2980b9;
        }
        
        .btn-secondary {
            background: #95a5a6;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #7f8c8d;
        }
        
        /* User Table */
        .user-section {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
        }
        
        .section-title {
            margin-bottom: 20px;
            color: #2c3e50;
            border-bottom: 2px solid #ecf0f1;
            padding-bottom: 10px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ecf0f1;
        }
        
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .user-role {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .role-user { background: #d1ecf1; color: #0c5460; }
        .role-petugas { background: #d4edda; color: #155724; }
        .role-admin { background: #f8d7da; color: #721c24; }
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .btn-small {
            padding: 5px 10px;
            font-size: 0.75rem;
            text-decoration: none;
            border-radius: 3px;
            border: none;
            cursor: pointer;
            display: inline-block;
        }
        
        .btn-small-edit {
            background: #f39c12;
            color: white;
        }
        
        .btn-small-edit:hover {
            background: #e67e22;
        }
        
        .btn-small-delete {
            background: #e74c3c;
            color: white;
        }
        
        .btn-small-delete:hover {
            background: #c0392b;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #7f8c8d;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 10px;
            opacity: 0.5;
        }
        
        .user-count {
            color: #7f8c8d;
            font-size: 0.9rem;
            margin-bottom: 15px;
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            width: 500px;
            max-width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .modal-actions .btn {
            flex: 1;
            text-align: center;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #2c3e50;
        }
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 1rem;
        }
        
        .form-group input:focus, .form-group select:focus {
            border-color: #3498db;
            outline: none;
        }
        
        .password-info {
            font-size: 0.8rem;
            color: #7f8c8d;
            margin-top: 5px;
        }
        
        .modal-title {
            margin-bottom: 20px;
            color: #2c3e50;
            padding-bottom: 10px;
            border-bottom: 2px solid #ecf0f1;
        }
        
        .btn-success {
            background: #27ae60;
            color: white;
        }
        
        .btn-success:hover {
            background: #219a52;
        }
        
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c0392b;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="logo">
                <h2>Sistem <span>Pengaduan</span></h2>
            </div>
            
            <div class="user-info">
                <h3><?php echo htmlspecialchars($user_nama); ?></h3>
                <span class="role">Administrator</span>
            </div>
            
            <ul class="nav-links">
                <li><a href="index.php">Dashboard</a></li>
                <li><a href="kelola_user.php" class="active">Kelola User</a></li>
                <li><a href="../auth/logout.php" class="logout">Logout</a></li>
            </ul>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <h1>Kelola User</h1>
                <p>Selamat datang, <?php echo htmlspecialchars($user_nama); ?>! Kelola semua user sistem.</p>
            </div>

            <!-- Notifikasi -->
            <?php if ($success_msg): ?>
                <div class="notification success">
                    <?php echo $success_msg; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_msg)): ?>
                <div class="notification error">
                    <?php echo $error_msg; ?>
                </div>
            <?php endif; ?>
            
            <!-- Filter Section -->
            <div class="filter-section">
                <h3>Filter User</h3>
                <form method="GET" action="" class="filter-form">
                    <div class="filter-group">
                        <label for="search">Cari (Nama/Username/Telepon)</label>
                        <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($filter_search); ?>" placeholder="Cari user...">
                    </div>
                    <div class="filter-group">
                        <label for="role">Role</label>
                        <select id="role" name="role" onchange="this.form.submit()">
                            <option value="">Semua Role</option>
                            <option value="user" <?php echo ($filter_role == 'user') ? 'selected' : ''; ?>>User Masyarakat</option>
                            <option value="petugas" <?php echo ($filter_role == 'petugas') ? 'selected' : ''; ?>>Petugas</option>
                            <option value="admin" <?php echo ($filter_role == 'admin') ? 'selected' : ''; ?>>Admin</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Terapkan Filter</button>
                    <?php if ($filter_role || $filter_search): ?>
                        <a href="kelola_user.php" class="btn btn-secondary">Reset Filter</a>
                    <?php endif; ?>
                </form>
            </div>
            
            <!-- Daftar User -->
            <div class="user-section">
                <h2 class="section-title">Daftar Semua User</h2>
                
                <div class="user-count">
                    Menampilkan <?php echo count($filtered_users); ?> dari <?php echo count($users); ?> user
                </div>
                
                <?php if (empty($users)): ?>
                    <div class="empty-state">
                        <div>👥</div>
                        <h3>Belum ada user</h3>
                        <p>Tidak ada user terdaftar dalam sistem</p>
                    </div>
                <?php elseif (empty($filtered_users)): ?>
                    <div class="empty-state">
                        <div>🔍</div>
                        <h3>Tidak ada hasil</h3>
                        <p>Tidak ada user dengan filter yang dipilih</p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Username</th>
                                <th>Telepon</th>
                                <th>Role</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($filtered_users as $u): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($u['nama']); ?></strong>
                                        <?php if ($u['id_user'] == $user_id): ?>
                                            <br><small style="color: #3498db;">(Anda)</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($u['username']); ?></td>
                                    <td><?php echo htmlspecialchars($u['telp'] ?? '-'); ?></td>
                                    <td>
                                        <span class="user-role role-<?php echo $u['role']; ?>">
                                            <?php echo ucfirst($u['role']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button onclick="showEditModal(
                                                <?php echo $u['id_user']; ?>,
                                                '<?php echo htmlspecialchars($u['nama'], ENT_QUOTES); ?>',
                                                '<?php echo htmlspecialchars($u['username'], ENT_QUOTES); ?>',
                                                '<?php echo htmlspecialchars($u['telp'] ?? '', ENT_QUOTES); ?>',
                                                '<?php echo $u['role']; ?>'
                                            )" class="btn-small btn-small-edit">Edit</button>
                                            
                                            <?php if ($u['id_user'] != $user_id): ?>
                                                <button onclick="showDeleteModal(
                                                    <?php echo $u['id_user']; ?>,
                                                    '<?php echo htmlspecialchars($u['nama'], ENT_QUOTES); ?>',
                                                    '<?php echo ucfirst($u['role']); ?>'
                                                )" class="btn-small btn-small-delete">Hapus</button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Modal Edit User -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <h3 class="modal-title">Edit User</h3>
            <form method="POST" action="">
                <input type="hidden" id="edit_id" name="id_user">
                
                <div class="form-group">
                    <label for="edit_nama">Nama Lengkap *</label>
                    <input type="text" id="edit_nama" name="nama" required>
                </div>
                <div class="form-group">
                    <label for="edit_username">Username *</label>
                    <input type="text" id="edit_username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="edit_password">Password Baru (Kosongkan jika tidak diubah)</label>
                    <input type="password" id="edit_password" name="password">
                    <div class="password-info">Isi hanya jika ingin mengganti password</div>
                </div>
                <div class="form-group">
                    <label for="edit_telp">Nomor Telepon</label>
                    <input type="text" id="edit_telp" name="telp">
                </div>
                <div class="form-group">
                    <label for="edit_role">Role *</label>
                    <select id="edit_role" name="role" required>
                        <option value="user">User Masyarakat</option>
                        <option value="petugas">Petugas</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="modal-actions">
                    <button type="submit" name="edit_user" class="btn btn-success">Update</button>
                    <button type="button" onclick="closeEditModal()" class="btn btn-secondary">Batal</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal Konfirmasi Hapus -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <h3 class="modal-title">Konfirmasi Hapus User</h3>
            <p>Anda yakin ingin menghapus user ini?</p>
            <p><strong>Nama:</strong> <span id="deleteNama"></span></p>
            <p><strong>Role:</strong> <span id="deleteRole"></span></p>
            <p style="color: #e74c3c; font-weight: 500; margin-top: 10px;">
                ⚠️ Semua data pengaduan dan tanggapan user ini akan ikut terhapus!
            </p>
            <div class="modal-actions">
                <a href="#" id="confirmDelete" class="btn btn-danger">Ya, Hapus</a>
                <button onclick="closeDeleteModal()" class="btn btn-secondary">Batal</button>
            </div>
        </div>
    </div>

    <script>
        // Modal Edit User
        function showEditModal(id, nama, username, telp, role) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_nama').value = nama;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_telp').value = telp;
            document.getElementById('edit_role').value = role;
            document.getElementById('edit_password').value = '';
            
            document.getElementById('editModal').style.display = 'flex';
            document.getElementById('edit_nama').focus();
        }
        
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        // Modal Hapus User
        function showDeleteModal(id, nama, role) {
            document.getElementById('deleteNama').textContent = nama;
            document.getElementById('deleteRole').textContent = role;
            document.getElementById('confirmDelete').href = 'kelola_user.php?hapus=' + id;
            document.getElementById('deleteModal').style.display = 'flex';
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = ['editModal', 'deleteModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target === modal) {
                    if (modalId === 'editModal') closeEditModal();
                    if (modalId === 'deleteModal') closeDeleteModal();
                }
            });
        }
        
        // Auto hide notification after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const notifications = document.querySelectorAll('.notification');
            notifications.forEach(notification => {
                setTimeout(() => {
                    notification.style.display = 'none';
                }, 5000);
            });
            
            // Auto submit filter form on role change
            const roleSelect = document.getElementById('role');
            if (roleSelect) {
                roleSelect.addEventListener('change', function() {
                    this.form.submit();
                });
            }
        });
    </script>
</body>
</html>