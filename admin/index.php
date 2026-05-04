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

// Query untuk mendapatkan semua data pengaduan dengan informasi lengkap
$pengaduan_query = $pdo->prepare("
    SELECT p.*, u.nama as nama_pengadu, 
           COUNT(t.id_tanggapan) as jumlah_tanggapan
    FROM pengaduan p 
    JOIN users u ON p.id_user = u.id_user 
    LEFT JOIN tanggapan t ON p.id_pengaduan = t.id_pengaduan 
    GROUP BY p.id_pengaduan 
    ORDER BY p.tgl_pengaduan DESC
");
$pengaduan_query->execute();
$pengaduan = $pengaduan_query->fetchAll();

// Query untuk mendapatkan semua users
$users_query = $pdo->prepare("
    SELECT * FROM users ORDER BY role, nama
");
$users_query->execute();
$users = $users_query->fetchAll();

// Query untuk statistik
$stat_query = $pdo->prepare("
    SELECT 
        COUNT(*) as total_pengaduan,
        SUM(CASE WHEN status = '0' THEN 1 ELSE 0 END) as menunggu,
        SUM(CASE WHEN status = 'proses' THEN 1 ELSE 0 END) as proses,
        SUM(CASE WHEN status = 'selesai' THEN 1 ELSE 0 END) as selesai,
        (SELECT COUNT(*) FROM users WHERE role = 'user') as total_user,
        (SELECT COUNT(*) FROM users WHERE role = 'petugas') as total_petugas,
        (SELECT COUNT(*) FROM users WHERE role = 'admin') as total_admin,
        (SELECT COUNT(*) FROM tanggapan) as total_tanggapan
    FROM pengaduan
");
$stat_query->execute();
$stats = $stat_query->fetch();

// Proses hapus pengaduan jika ada request
if (isset($_GET['hapus']) && is_numeric($_GET['hapus'])) {
    $id_hapus = $_GET['hapus'];
    
    // Cek apakah pengaduan ada
    $cek_query = $pdo->prepare("SELECT * FROM pengaduan WHERE id_pengaduan = ?");
    $cek_query->execute([$id_hapus]);
    $pengaduan_hapus = $cek_query->fetch();
    
    if ($pengaduan_hapus) {
        // Hapus tanggapan terlebih dahulu (karena foreign key constraint)
        $hapus_tanggapan = $pdo->prepare("DELETE FROM tanggapan WHERE id_pengaduan = ?");
        $hapus_tanggapan->execute([$id_hapus]);
        
        // Hapus pengaduan
        $hapus_query = $pdo->prepare("DELETE FROM pengaduan WHERE id_pengaduan = ?");
        if ($hapus_query->execute([$id_hapus])) {
            header('Location: index.php?success=hapus');
            exit();
        } else {
            header('Location: index.php?error=hapus');
            exit();
        }
    }
}

// Proses hapus SEMUA pengaduan
if (isset($_GET['hapus_semua']) && $_GET['hapus_semua'] == 'true') {
    // Cek apakah ada pengaduan
    $cek_total = $pdo->query("SELECT COUNT(*) as total FROM pengaduan")->fetch();
    
    if ($cek_total['total'] > 0) {
        try {
            // Mulai transaksi
            $pdo->beginTransaction();
            
            // Hapus semua tanggapan terlebih dahulu
            $pdo->exec("DELETE FROM tanggapan");
            
            // Hapus semua pengaduan
            $pdo->exec("DELETE FROM pengaduan");
            
            // Commit transaksi
            $pdo->commit();
            
            header('Location: index.php?success=hapus_semua');
            exit();
        } catch (Exception $e) {
            // Rollback jika ada error
            $pdo->rollBack();
            header('Location: index.php?error=hapus_semua');
            exit();
        }
    } else {
        header('Location: index.php?error=tidak_ada_data');
        exit();
    }
}

// Pesan notifikasi
$success_msg = '';
$error_msg = '';
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'hapus':
            $success_msg = 'Pengaduan berhasil dihapus!';
            break;
        case 'hapus_semua':
            $success_msg = 'Semua pengaduan berhasil dihapus!';
            break;
    }
}
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'hapus':
            $error_msg = 'Gagal menghapus pengaduan!';
            break;
        case 'hapus_semua':
            $error_msg = 'Gagal menghapus semua pengaduan!';
            break;
        case 'tidak_ada_data':
            $error_msg = 'Tidak ada data pengaduan untuk dihapus!';
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Sistem Pengaduan Masyarakat</title>
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
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-content h1 {
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .header-content p {
            color: #7f8c8d;
        }
        
        .header-actions {
            display: flex;
            gap: 10px;
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
        
        /* Stats Cards */
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .stat-card h3 {
            font-size: 2.5rem;
            color: #2c3e50;
            margin-bottom: 10px;
            font-weight: 700;
        }
        
        .stat-card p {
            color: #7f8c8d;
            font-size: 1rem;
            font-weight: 500;
        }
        
        /* Tambahkan icon untuk setiap stat card */
        .stat-total h3::before { content: "📊 "; }
        .stat-waiting h3::before { content: "⏳ "; }
        .stat-process h3::before { content: "🔄 "; }
        .stat-done h3::before { content: "✅ "; }
        .stat-users h3::before { content: "👥 "; }
        .stat-petugas h3::before { content: "👮 "; }
        .stat-admin h3::before { content: "👑 "; }
        .stat-tanggapan h3::before { content: "💬 "; }
        
        .stat-waiting { 
            border-left: 4px solid #f39c12;
            background: linear-gradient(to right, #fffaf0, white);
        }
        .stat-process { 
            border-left: 4px solid #3498db;
            background: linear-gradient(to right, #f0f8ff, white);
        }
        .stat-done { 
            border-left: 4px solid #27ae60;
            background: linear-gradient(to right, #f0fff4, white);
        }
        .stat-total { 
            border-left: 4px solid #9b59b6;
            background: linear-gradient(to right, #f8f0ff, white);
        }
        .stat-users { 
            border-left: 4px solid #1abc9c;
            background: linear-gradient(to right, #f0fffb, white);
        }
        .stat-petugas { 
            border-left: 4px solid #e74c3c;
            background: linear-gradient(to right, #fff0f0, white);
        }
        .stat-admin { 
            border-left: 4px solid #8e44ad;
            background: linear-gradient(to right, #f5f0ff, white);
        }
        .stat-tanggapan { 
            border-left: 4px solid #f1c40f;
            background: linear-gradient(to right, #fffdf0, white);
        }
        
        /* Button Styles */
        .btn {
            padding: 10px 20px;
            color: white;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.3s;
            font-size: 0.9rem;
        }
        
        .btn-danger {
            background: #e74c3c;
        }
        
        .btn-danger:hover {
            background: #c0392b;
        }
        
        .btn-secondary {
            background: #95a5a6;
        }
        
        .btn-secondary:hover {
            background: #7f8c8d;
        }
        
        .btn-small {
            padding: 6px 12px;
            font-size: 0.8rem;
            text-decoration: none;
            border-radius: 3px;
            border: none;
            cursor: pointer;
            display: inline-block;
            transition: background-color 0.3s;
        }
        
        .btn-view {
            background: #3498db;
            color: white;
        }
        
        .btn-view:hover {
            background: #2980b9;
        }
        
        .btn-delete {
            background: #e74c3c;
            color: white;
        }
        
        .btn-delete:hover {
            background: #c0392b;
        }
        
        /* Filter Section */
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .filter-section h3 {
            color: #2c3e50;
            margin-bottom: 15px;
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
        
        .filter-group select {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 1rem;
            background: white;
            cursor: pointer;
            transition: border-color 0.3s;
        }
        
        .filter-group select:focus {
            border-color: #3498db;
            outline: none;
        }
        
        /* Pengaduan Table */
        .pengaduan-section {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #ecf0f1;
            padding-bottom: 10px;
        }
        
        .section-title {
            color: #2c3e50;
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
            position: sticky;
            top: 0;
        }
        
        tr:hover {
            background-color: #f9f9f9;
        }
        
        .status {
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 500;
            display: inline-block;
            min-width: 100px;
            text-align: center;
        }
        
        .status-waiting {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .status-process {
            background: #cce7ff;
            color: #004085;
            border: 1px solid #b3d7ff;
        }
        
        .status-done {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
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
        
        .pengaduan-count {
            color: #7f8c8d;
            font-size: 0.9rem;
            margin-bottom: 15px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
        }

        .status-info {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            line-height: 1.5;
        }
        
        .status-info strong {
            color: #856404;
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
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        }
        
        .modal-content h3 {
            color: #2c3e50;
            margin-bottom: 15px;
        }
        
        .modal-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
            font-size: 0.9rem;
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
        
        /* Responsive Design */
        @media (max-width: 1200px) {
            .stats {
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            }
        }
        
        @media (max-width: 992px) {
            .sidebar {
                width: 220px;
            }
            
            .main-content {
                margin-left: 220px;
            }
            
            .stats {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .header-actions {
                align-self: flex-start;
            }
        }
        
        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }
            
            .sidebar {
                position: relative;
                width: 100%;
                height: auto;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .stats {
                grid-template-columns: 1fr;
            }
            
            table {
                display: block;
                overflow-x: auto;
            }
            
            .filter-form {
                flex-direction: column;
            }
            
            .filter-group {
                width: 100%;
            }
            
            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
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
                <li><a href="index.php" class="active">Dashboard</a></li>
                <li><a href="kelola_user.php">Kelola User</a></li>
                <li><a href="../auth/logout.php" class="logout">Logout</a></li>
            </ul>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="header">
                <div class="header-content">
                    <h1>Dashboard Administrator</h1>
                    <p>Selamat datang, <?php echo htmlspecialchars($user_nama); ?>! Anda dapat melihat dan menghapus pengaduan.</p>
                </div>
                <?php if (!empty($pengaduan)): ?>
                    <div class="header-actions">
                        <button onclick="showDeleteAllModal()" class="btn btn-danger">🗑️ Hapus Semua</button>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Notifikasi -->
            <?php if ($success_msg): ?>
                <div class="notification success">
                    <?php echo $success_msg; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error_msg): ?>
                <div class="notification error">
                    <?php echo $error_msg; ?>
                </div>
            <?php endif; ?>

            <!-- Status Information -->
            <div class="status-info">
                <strong>Keterangan Status:</strong><br>
                • <strong>Menunggu (0)</strong> - Pengaduan belum diproses<br>
                • <strong>Diproses (proses)</strong> - Pengaduan sedang ditangani<br>
                • <strong>Selesai (selesai)</strong> - Pengaduan telah selesai ditangani
            </div>
            
            <!-- Statistics -->
            <div class="stats">
                <div class="stat-card stat-total">
                    <h3><?php echo $stats['total_pengaduan']; ?></h3>
                    <p>Total Pengaduan</p>
                </div>
                <div class="stat-card stat-waiting">
                    <h3><?php echo $stats['menunggu']; ?></h3>
                    <p>Menunggu (0)</p>
                </div>
                <div class="stat-card stat-process">
                    <h3><?php echo $stats['proses']; ?></h3>
                    <p>Diproses</p>
                </div>
                <div class="stat-card stat-done">
                    <h3><?php echo $stats['selesai']; ?></h3>
                    <p>Selesai</p>
                </div>
                <div class="stat-card stat-users">
                    <h3><?php echo $stats['total_user']; ?></h3>
                    <p>User Masyarakat</p>
                </div>
                <div class="stat-card stat-petugas">
                    <h3><?php echo $stats['total_petugas']; ?></h3>
                    <p>Petugas</p>
                </div>
                <div class="stat-card stat-admin">
                    <h3><?php echo $stats['total_admin']; ?></h3>
                    <p>Admin</p>
                </div>
                <div class="stat-card stat-tanggapan">
                    <h3><?php echo $stats['total_tanggapan']; ?></h3>
                    <p>Total Tanggapan</p>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <h3>Filter Pengaduan</h3>
                <form method="GET" action="" class="filter-form">
                    <div class="filter-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" onchange="this.form.submit()">
                            <option value="">Semua Status</option>
                            <option value="0" <?php echo (isset($_GET['status']) && $_GET['status'] == '0') ? 'selected' : ''; ?>>Menunggu (0)</option>
                            <option value="proses" <?php echo (isset($_GET['status']) && $_GET['status'] == 'proses') ? 'selected' : ''; ?>>Diproses</option>
                            <option value="selesai" <?php echo (isset($_GET['status']) && $_GET['status'] == 'selesai') ? 'selected' : ''; ?>>Selesai</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="sort">Urutkan</label>
                        <select id="sort" name="sort" onchange="this.form.submit()">
                            <option value="terbaru" <?php echo (isset($_GET['sort']) && $_GET['sort'] == 'terbaru') ? 'selected' : ''; ?>>Terbaru</option>
                            <option value="terlama" <?php echo (isset($_GET['sort']) && $_GET['sort'] == 'terlama') ? 'selected' : ''; ?>>Terlama</option>
                        </select>
                    </div>
                    <?php if (isset($_GET['status']) || isset($_GET['sort'])): ?>
                        <a href="index.php" class="btn btn-secondary">Reset Filter</a>
                    <?php endif; ?>
                </form>
            </div>
            
            <!-- Daftar Pengaduan -->
            <div class="pengaduan-section">
                <!-- Section Header dengan tombol Hapus Semua -->
                <div class="section-header">
                    <h2 class="section-title">Semua Pengaduan Masyarakat</h2>
                    <?php if (!empty($pengaduan)): ?>
                        <button onclick="showDeleteAllModal()" class="btn btn-danger btn-small">
                            🗑️ Hapus Semua Pengaduan
                        </button>
                    <?php endif; ?>
                </div>
                
                <?php
                // Filter berdasarkan status jika ada
                $filtered_pengaduan = $pengaduan;
                
                if (isset($_GET['status']) && $_GET['status'] !== '') {
                    $filtered_pengaduan = array_filter($pengaduan, function($item) {
                        return $item['status'] == $_GET['status'];
                    });
                }
                
                // Sorting
                if (isset($_GET['sort']) && $_GET['sort'] == 'terlama') {
                    $filtered_pengaduan = array_reverse($filtered_pengaduan);
                }
                ?>
                
                <div class="pengaduan-count">
                    Menampilkan <?php echo count($filtered_pengaduan); ?> pengaduan
                    <?php if (!empty($pengaduan)): ?>
                        | Total: <?php echo count($pengaduan); ?> pengaduan
                    <?php endif; ?>
                </div>
                
                <?php if (empty($pengaduan)): ?>
                    <div class="empty-state">
                        <div>📝</div>
                        <h3>Belum ada pengaduan</h3>
                        <p>Tidak ada pengaduan dari masyarakat saat ini</p>
                    </div>
                <?php elseif (empty($filtered_pengaduan)): ?>
                    <div class="empty-state">
                        <div>🔍</div>
                        <h3>Tidak ada hasil</h3>
                        <p>Tidak ada pengaduan dengan filter yang dipilih</p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Pengadu</th>
                                <th>Isi Laporan</th>
                                <th>Status</th>
                                <th>Tanggapan</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($filtered_pengaduan as $p): ?>
                                <tr>
                                    <td><?php echo date('d M Y H:i', strtotime($p['tgl_pengaduan'])); ?></td>
                                    <td><?php echo htmlspecialchars($p['nama_pengadu']); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars(substr($p['isi_laporan'], 0, 80)); ?>...</strong>
                                        <?php if (!empty($p['foto'])): ?>
                                            <br><small style="color: #3498db;">📷 Ada foto</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $status_class = '';
                                        $status_text = '';
                                        switch ($p['status']) {
                                            case '0':
                                                $status_class = 'status-waiting';
                                                $status_text = 'Menunggu (0)';
                                                break;
                                            case 'proses':
                                                $status_class = 'status-process';
                                                $status_text = 'Diproses';
                                                break;
                                            case 'selesai':
                                                $status_class = 'status-done';
                                                $status_text = 'Selesai';
                                                break;
                                        }
                                        ?>
                                        <span class="status <?php echo $status_class; ?>">
                                            <?php echo $status_text; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($p['jumlah_tanggapan'] > 0): ?>
                                            <span style="color: #27ae60; font-weight: 500;">
                                                <?php echo $p['jumlah_tanggapan']; ?> tanggapan
                                            </span>
                                        <?php else: ?>
                                            <span style="color: #7f8c8d;">Belum ada</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="action-buttons">
                                        <a href="lihat_pengaduan.php?id=<?php echo $p['id_pengaduan']; ?>" class="btn-small btn-view">Lihat</a>
                                        <button onclick="showDeleteModal(<?php echo $p['id_pengaduan']; ?>, '<?php echo htmlspecialchars(substr($p['isi_laporan'], 0, 50), ENT_QUOTES); ?>')" class="btn-small btn-delete">Hapus</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Modal Konfirmasi Hapus -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <h3>Konfirmasi Hapus Pengaduan</h3>
            <p>Anda yakin ingin menghapus pengaduan ini?</p>
            <p id="pengaduanText" style="font-style: italic; color: #666; margin: 10px 0;"></p>
            <div class="modal-actions">
                <a href="#" id="confirmDelete" class="btn btn-danger">Ya, Hapus</a>
                <button onclick="closeDeleteModal()" class="btn btn-secondary">Batal</button>
            </div>
        </div>
    </div>
    
    <!-- Modal Konfirmasi Hapus Semua -->
    <div id="deleteAllModal" class="modal">
        <div class="modal-content">
            <h3>⚠️ Konfirmasi Hapus Semua Pengaduan</h3>
            <div class="modal-warning">
                <strong>PERHATIAN:</strong> Anda akan menghapus <strong>SEMUA <?php echo count($pengaduan); ?> PENGADUAN</strong> dari sistem!
            </div>
            <p><strong>Data yang akan dihapus:</strong></p>
            <ul style="color: #666; margin: 10px 0 15px 20px;">
                <li>Semua <?php echo $stats['total_pengaduan']; ?> pengaduan</li>
                <li>Semua <?php echo $stats['total_tanggapan']; ?> tanggapan</li>
                <li>Semua file foto terkait</li>
            </ul>
            <p style="color: #e74c3c; font-weight: 500;">
                ⚠️ Tindakan ini <strong>TIDAK DAPAT DIBATALKAN</strong> dan <strong>BERBAHAYA</strong>!
            </p>
            <div class="modal-actions">
                <a href="index.php?hapus_semua=true" id="confirmDeleteAll" class="btn btn-danger">Ya, Hapus Semua</a>
                <button onclick="closeDeleteAllModal()" class="btn btn-secondary">Batal</button>
            </div>
        </div>
    </div>

    <script>
        // Modal untuk hapus pengaduan
        function showDeleteModal(id, text) {
            document.getElementById('pengaduanText').textContent = '"' + text + '..."';
            document.getElementById('confirmDelete').href = 'index.php?hapus=' + id;
            document.getElementById('deleteModal').style.display = 'flex';
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }
        
        // Modal untuk hapus semua pengaduan
        function showDeleteAllModal() {
            document.getElementById('deleteAllModal').style.display = 'flex';
        }
        
        function closeDeleteAllModal() {
            document.getElementById('deleteAllModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const deleteModal = document.getElementById('deleteModal');
            const deleteAllModal = document.getElementById('deleteAllModal');
            
            if (event.target === deleteModal) {
                closeDeleteModal();
            }
            if (event.target === deleteAllModal) {
                closeDeleteAllModal();
            }
        }
        
        // Auto submit filter form when selections change
        document.addEventListener('DOMContentLoaded', function() {
            const filterSelects = document.querySelectorAll('select[name="status"], select[name="sort"]');
            filterSelects.forEach(select => {
                select.addEventListener('change', function() {
                    this.form.submit();
                });
            });
            
            // Auto hide notification after 5 seconds
            const notifications = document.querySelectorAll('.notification');
            notifications.forEach(notification => {
                setTimeout(() => {
                    notification.style.display = 'none';
                }, 5000);
            });
            
            // Focus protection for delete all button
            const deleteAllBtn = document.getElementById('confirmDeleteAll');
            if (deleteAllBtn) {
                deleteAllBtn.addEventListener('click', function(e) {
                    if (!confirm('Apakah Anda BENAR-BENAR yakin ingin menghapus SEMUA pengaduan? Tindakan ini TIDAK DAPAT DIBATALKAN!')) {
                        e.preventDefault();
                    }
                });
            }
        });
    </script>
</body>
</html>