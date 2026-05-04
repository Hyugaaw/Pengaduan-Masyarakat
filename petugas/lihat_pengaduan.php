<?php
session_start();
require_once '../koneksi.php';

// Cek apakah user sudah login dan role-nya petugas
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'petugas') {
    header('Location: ../auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_nama = $_SESSION['nama'];

// Cek apakah parameter id ada
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$id_pengaduan = $_GET['id'];

// Ambil data pengaduan
try {
    $stmt = $pdo->prepare("
        SELECT p.*, u.nama as nama_pengadu, u.telp as telp_pengadu 
        FROM pengaduan p 
        JOIN users u ON p.id_user = u.id_user 
        WHERE p.id_pengaduan = ?
    ");
    $stmt->execute([$id_pengaduan]);
    $pengaduan = $stmt->fetch();

    if (!$pengaduan) {
        $_SESSION['error'] = 'Pengaduan tidak ditemukan!';
        header('Location: index.php');
        exit();
    }

    // Ambil data tanggapan jika ada
    $tanggapan_stmt = $pdo->prepare("
        SELECT t.*, u.nama as nama_petugas 
        FROM tanggapan t 
        JOIN users u ON t.id_petugas = u.id_user 
        WHERE t.id_pengaduan = ? 
        ORDER BY t.tgl_tanggapan DESC
    ");
    $tanggapan_stmt->execute([$id_pengaduan]);
    $tanggapan = $tanggapan_stmt->fetchAll();

} catch (PDOException $e) {
    $_SESSION['error'] = 'Terjadi kesalahan sistem: ' . $e->getMessage();
    header('Location: index.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pengaduan - Sistem Pengaduan Masyarakat</title>
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
            background: #27ae60;
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
        
        /* Content Styles */
        .content-container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .info-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #3498db;
        }
        
        .info-card h3 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 1rem;
        }
        
        .info-card p {
            color: #555;
            font-size: 1.1rem;
            font-weight: 500;
        }
        
        .status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
            display: inline-block;
        }
        
        .status-waiting {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-process {
            background: #cce7ff;
            color: #004085;
        }
        
        .status-done {
            background: #d4edda;
            color: #155724;
        }
        
        .section {
            margin-bottom: 30px;
        }
        
        .section-title {
            color: #2c3e50;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #ecf0f1;
        }
        
        .laporan-content {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            line-height: 1.6;
            white-space: pre-wrap;
        }
        
        .foto-container {
            text-align: center;
        }
        
        .foto-preview {
            max-width: 100%;
            max-height: 400px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .no-photo {
            background: #f8f9fa;
            padding: 40px;
            border-radius: 8px;
            text-align: center;
            color: #7f8c8d;
            font-style: italic;
        }
        
        .tanggapan-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .tanggapan-item {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #27ae60;
        }
        
        .tanggapan-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .tanggapan-petugas {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .tanggapan-tanggal {
            color: #7f8c8d;
            font-size: 0.9rem;
        }
        
        .tanggapan-isi {
            line-height: 1.6;
            white-space: pre-wrap;
        }
        
        .no-tanggapan {
            background: #f8f9fa;
            padding: 40px;
            border-radius: 8px;
            text-align: center;
            color: #7f8c8d;
        }
        
        .btn {
            padding: 10px 20px;
            background: #3498db;
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
        
        .btn:hover {
            background: #2980b9;
        }
        
        .btn-success {
            background: #27ae60;
        }
        
        .btn-success:hover {
            background: #219a52;
        }
        
        .btn-secondary {
            background: #95a5a6;
        }
        
        .btn-secondary:hover {
            background: #7f8c8d;
        }
        
        .btn-warning {
            background: #f39c12;
            color: white;
        }
        
        .btn-warning:hover {
            background: #e67e22;
        }

        .btn-delete {
            background: #e74c3c;
            color: white;
        }

        .btn-delete:hover {
            background: #c0392b;
        }

        .alert {
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 0.95rem;
        }

        .alert-error {
            background: #ffeaea;
            color: #d63031;
            border: 1px solid #ff7675;
        }

        .alert-success {
            background: #e8f7ee;
            color: #00b894;
            border: 1px solid #55efc4;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .pengadu-detail {
            background: #e8f4fd;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .pengadu-detail h3 {
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .pengadu-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            font-size: 0.8rem;
            color: #7f8c8d;
            margin-bottom: 2px;
        }

        .info-value {
            font-weight: 500;
            color: #2c3e50;
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
                <span class="role">Petugas</span>
            </div>
            
            <ul class="nav-links">
                <li><a href="index.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'lihat_pengaduan.php') ? 'active' : ''; ?>">Dashboard</a></li>
                <li><a href="tanggapan_saya.php">Tanggapan Saya</a></li>
                <li><a href="../auth/logout.php">Logout</a></li>
            </ul>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <h1>Detail Pengaduan</h1>
                <p>Informasi lengkap tentang pengaduan masyarakat</p>
            </div>
            
            <div class="content-container">
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); ?></div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['error']); ?></div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>
                <!-- Informasi Pengadu -->
                <div class="pengadu-detail">
                    <h3>Informasi Pengadu</h3>
                    <div class="pengadu-info">
                        <div class="info-item">
                            <span class="info-label">Nama Lengkap</span>
                            <span class="info-value"><?php echo htmlspecialchars($pengaduan['nama_pengadu']); ?></span>
                        </div>
                        <?php if (!empty($pengaduan['telp_pengadu'])): ?>
                        <div class="info-item">
                            <span class="info-label">Telepon</span>
                            <span class="info-value"><?php echo htmlspecialchars($pengaduan['telp_pengadu']); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="info-item">
                            <span class="info-label">ID Pengaduan</span>
                            <span class="info-value">#<?php echo $pengaduan['id_pengaduan']; ?></span>
                        </div>
                    </div>
                </div>

                <!-- Informasi Pengaduan -->
                <div class="info-grid">
                    <div class="info-card">
                        <h3>Tanggal Pengaduan</h3>
                        <p><?php echo date('d F Y H:i', strtotime($pengaduan['tgl_pengaduan'])); ?></p>
                    </div>
                    
                    <div class="info-card">
                        <h3>Status</h3>
                        <p>
                            <?php 
                            $status_class = '';
                            $status_text = '';
                            switch ($pengaduan['status']) {
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
                        </p>
                    </div>
                    
                    <div class="info-card">
                        <h3>Jumlah Tanggapan</h3>
                        <p><?php echo count($tanggapan); ?> tanggapan</p>
                    </div>
                </div>
                
                <!-- Isi Laporan -->
                <div class="section">
                    <h2 class="section-title">Isi Laporan</h2>
                    <div class="laporan-content">
                        <?php echo htmlspecialchars($pengaduan['isi_laporan']); ?>
                    </div>
                </div>
                
                <!-- Foto Bukti -->
                <div class="section">
                    <h2 class="section-title">Foto Bukti</h2>
                    <div class="foto-container">
                        <?php if (!empty($pengaduan['foto']) && file_exists('../uploads/' . $pengaduan['foto'])): ?>
                            <img src="../uploads/<?php echo htmlspecialchars($pengaduan['foto']); ?>" 
                                 alt="Foto Pengaduan" 
                                 class="foto-preview">
                            <div style="margin-top: 10px;">
                                <a href="../uploads/<?php echo htmlspecialchars($pengaduan['foto']); ?>" 
                                   target="_blank" 
                                   class="btn">Lihat Full Size</a>
                            </div>
                        <?php else: ?>
                            <div class="no-photo">
                                <p>Tidak ada foto yang diupload</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Tanggapan -->
                <div class="section">
                    <h2 class="section-title">Tanggapan</h2>
                    <?php if (!empty($tanggapan)): ?>
                        <div class="tanggapan-list">
                            <?php foreach ($tanggapan as $t): ?>
                                <div class="tanggapan-item">
                                    <div class="tanggapan-header">
                                        <span class="tanggapan-petugas">
                                            <?php echo htmlspecialchars($t['nama_petugas']); ?> (Petugas)
                                        </span>
                                        <span class="tanggapan-tanggal">
                                            <?php echo date('d F Y H:i', strtotime($t['tgl_tanggapan'])); ?>
                                        </span>
                                    </div>
                                    <div class="tanggapan-isi">
                                        <?php echo htmlspecialchars($t['tanggapan']); ?>
                                    </div>
                                    <?php if ($t['id_petugas'] == $user_id): ?>
                                        <div style="margin-top: 10px; display:flex; gap:8px;">
                                            <a href="edit_tanggapan.php?id=<?php echo $pengaduan['id_pengaduan']; ?>" class="btn btn-warning" style="padding: 5px 10px; font-size: 0.8rem;">Edit Tanggapan</a>
                                            <a href="hapus_tanggapan.php?id=<?php echo $pengaduan['id_pengaduan']; ?>" class="btn btn-delete" style="padding: 5px 10px; font-size: 0.8rem;" onclick="return confirm('Yakin hapus tanggapan Anda untuk pengaduan ini?')">Hapus</a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-tanggapan">
                            <p>Belum ada tanggapan dari petugas</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Action Buttons -->
                <div class="action-buttons">
                    <a href="index.php" class="btn btn-secondary">Kembali ke Dashboard</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>