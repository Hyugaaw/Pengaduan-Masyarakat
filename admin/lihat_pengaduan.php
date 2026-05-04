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

// Cek apakah ID pengaduan diberikan
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$id_pengaduan = $_GET['id'];

// Query untuk mendapatkan detail pengaduan
$pengaduan_query = $pdo->prepare("
    SELECT p.*, u.nama as nama_pengadu, u.telp as telp_pengadu, u.username as username_pengadu
    FROM pengaduan p 
    JOIN users u ON p.id_user = u.id_user 
    WHERE p.id_pengaduan = ?
");
$pengaduan_query->execute([$id_pengaduan]);
$pengaduan = $pengaduan_query->fetch();

// Jika pengaduan tidak ditemukan
if (!$pengaduan) {
    header('Location: index.php?error=pengaduan_tidak_ditemukan');
    exit();
}

// Query untuk mendapatkan tanggapan
$tanggapan_query = $pdo->prepare("
    SELECT t.*, u.nama as nama_petugas, u.role as role_petugas
    FROM tanggapan t 
    JOIN users u ON t.id_petugas = u.id_user 
    WHERE t.id_pengaduan = ?
    ORDER BY t.tgl_tanggapan DESC
");
$tanggapan_query->execute([$id_pengaduan]);
$tanggapan = $tanggapan_query->fetchAll();
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
        
        .header h1 {
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .header p {
            color: #7f8c8d;
        }
        
        .back-btn {
            background: #3498db;
            color: white;
            padding: 8px 16px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            transition: background 0.3s;
        }
        
        .back-btn:hover {
            background: #2980b9;
        }
        
        /* Detail Pengaduan */
        .detail-section {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .section-title {
            margin-bottom: 25px;
            color: #2c3e50;
            border-bottom: 2px solid #ecf0f1;
            padding-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .section-title h2 {
            font-size: 1.5rem;
        }
        
        /* Status Badge */
        .status-badge {
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 0.9rem;
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
        
        /* Info Grid */
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
            font-size: 1rem;
            margin-bottom: 10px;
        }
        
        .info-card p {
            color: #333;
            font-size: 1.1rem;
            font-weight: 500;
        }
        
        /* Laporan Content */
        .laporan-content {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        .laporan-content h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-size: 1.2rem;
        }
        
        .laporan-content p {
            color: #333;
            white-space: pre-line;
        }
        
        /* Foto Section */
        .foto-section {
            margin-bottom: 30px;
        }
        
        .foto-section h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-size: 1.2rem;
        }
        
        .foto-container {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        
        .foto-container img {
            max-width: 100%;
            max-height: 500px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .no-foto {
            color: #7f8c8d;
            font-style: italic;
            padding: 40px;
        }
        
        /* Hapus Button */
        .btn {
            padding: 12px 24px;
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.3s;
            font-size: 1rem;
        }
        
        .btn:hover {
            background: #c0392b;
        }
        
        .delete-container {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ecf0f1;
        }
        
        /* Tanggapan Section */
        .tanggapan-section {
            margin-bottom: 30px;
        }
        
        .tanggapan-list {
            margin-top: 20px;
        }
        
        .tanggapan-item {
            background: white;
            border-left: 4px solid #27ae60;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .tanggapan-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ecf0f1;
        }
        
        .tanggapan-petugas {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .petugas-role {
            background: #3498db;
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.8rem;
        }
        
        .petugas-nama {
            font-weight: 500;
            color: #2c3e50;
        }
        
        .tanggapan-date {
            color: #7f8c8d;
            font-size: 0.9rem;
        }
        
        .tanggapan-content {
            color: #333;
            line-height: 1.6;
            white-space: pre-line;
        }
        
        .no-tanggapan {
            background: #f8f9fa;
            padding: 40px;
            border-radius: 8px;
            text-align: center;
            color: #7f8c8d;
        }
        
        .no-tanggapan i {
            font-size: 2rem;
            margin-bottom: 10px;
            opacity: 0.5;
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
            width: 400px;
            max-width: 90%;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        }
        
        .modal-content h3 {
            color: #2c3e50;
            margin-bottom: 15px;
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
        
        .btn-confirm {
            background: #e74c3c;
        }
        
        .btn-confirm:hover {
            background: #c0392b;
        }
        
        .btn-cancel {
            background: #95a5a6;
        }
        
        .btn-cancel:hover {
            background: #7f8c8d;
        }
        
        /* Responsive Design */
        @media (max-width: 992px) {
            .sidebar {
                width: 220px;
            }
            
            .main-content {
                margin-left: 220px;
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
            
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .back-btn {
                align-self: flex-start;
            }
            
            .section-title {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .tanggapan-header {
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
                <li><a href="index.php">Dashboard</a></li>
                <li><a href="kelola_user.php">Kelola User</a></li>
                <li><a href="../auth/logout.php" class="logout">Logout</a></li>
            </ul>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="header">
                <div>
                    <h1>Detail Pengaduan</h1>
                    <p>ID Pengaduan: #<?php echo $pengaduan['id_pengaduan']; ?></p>
                </div>
                <a href="index.php" class="back-btn">← Kembali ke Dashboard</a>
            </div>

            <!-- Detail Pengaduan -->
            <div class="detail-section">
                <!-- Section Title dengan Status -->
                <div class="section-title">
                    <h2>Informasi Pengaduan</h2>
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
                    <span class="status-badge <?php echo $status_class; ?>">
                        <?php echo $status_text; ?>
                    </span>
                </div>

                <!-- Grid Informasi -->
                <div class="info-grid">
                    <div class="info-card">
                        <h3>Tanggal Pengaduan</h3>
                        <p><?php echo date('d F Y H:i', strtotime($pengaduan['tgl_pengaduan'])); ?></p>
                    </div>
                    
                    <div class="info-card">
                        <h3>Nama Pengadu</h3>
                        <p><?php echo htmlspecialchars($pengaduan['nama_pengadu']); ?></p>
                    </div>
                    
                    <div class="info-card">
                        <h3>Username</h3>
                        <p><?php echo htmlspecialchars($pengaduan['username_pengadu']); ?></p>
                    </div>
                    
                    <div class="info-card">
                        <h3>Telepon</h3>
                        <p><?php echo htmlspecialchars($pengaduan['telp_pengadu'] ?: '-'); ?></p>
                    </div>
                </div>

                <!-- Isi Laporan -->
                <div class="laporan-content">
                    <h3>Isi Laporan</h3>
                    <p><?php echo htmlspecialchars($pengaduan['isi_laporan']); ?></p>
                </div>

                <!-- Foto -->
                <div class="foto-section">
                    <h3>Bukti Foto</h3>
                    <div class="foto-container">
                        <?php if ($pengaduan['foto']): ?>
                            <?php 
                            $foto_path = '../uploads/' . $pengaduan['foto'];
                            if (file_exists($foto_path)): 
                            ?>
                                <img src="<?php echo $foto_path; ?>" 
                                     alt="Bukti pengaduan #<?php echo $pengaduan['id_pengaduan']; ?>"
                                     onclick="window.open('<?php echo $foto_path; ?>', '_blank')"
                                     style="cursor: pointer;">
                                <p style="margin-top: 10px; color: #666; font-size: 0.9rem;">
                                    Klik gambar untuk melihat ukuran penuh
                                </p>
                            <?php else: ?>
                                <div class="no-foto">
                                    <p>⚠️ File foto tidak ditemukan di server</p>
                                    <p>Nama file: <?php echo htmlspecialchars($pengaduan['foto']); ?></p>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="no-foto">
                                <p>Tidak ada foto yang diunggah</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Hapus Pengaduan -->
                <div class="delete-container">
                    <button onclick="showDeleteModal()" class="btn">Hapus Pengaduan Ini</button>
                </div>
            </div>

            <!-- Tanggapan Section -->
            <div class="detail-section tanggapan-section">
                <div class="section-title">
                    <h2>Tanggapan</h2>
                    <span style="color: #7f8c8d; font-weight: 500;">
                        <?php echo count($tanggapan); ?> Tanggapan
                    </span>
                </div>

                <div class="tanggapan-list">
                    <?php if (empty($tanggapan)): ?>
                        <div class="no-tanggapan">
                            <div>💬</div>
                            <h3>Belum ada tanggapan</h3>
                            <p>Pengaduan ini belum mendapatkan tanggapan dari petugas</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($tanggapan as $t): ?>
                            <div class="tanggapan-item">
                                <div class="tanggapan-header">
                                    <div class="tanggapan-petugas">
                                        <span class="petugas-nama"><?php echo htmlspecialchars($t['nama_petugas']); ?></span>
                                        <span class="petugas-role"><?php echo ucfirst($t['role_petugas']); ?></span>
                                    </div>
                                    <div class="tanggapan-date">
                                        <?php echo date('d F Y H:i', strtotime($t['tgl_tanggapan'])); ?>
                                    </div>
                                </div>
                                <div class="tanggapan-content">
                                    <?php echo htmlspecialchars($t['tanggapan']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Konfirmasi Hapus -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <h3>Konfirmasi Hapus Pengaduan</h3>
            <p>Anda yakin ingin menghapus pengaduan ini?</p>
            <p style="font-style: italic; color: #666; margin: 10px 0;">
                "<?php echo htmlspecialchars(substr($pengaduan['isi_laporan'], 0, 100)); ?>..."
            </p>
            <p style="color: #e74c3c; font-weight: 500; margin: 15px 0;">
                ⚠️ Perhatian: Tindakan ini tidak dapat dibatalkan!
            </p>
            <div class="modal-actions">
                <a href="index.php?hapus=<?php echo $pengaduan['id_pengaduan']; ?>" class="btn btn-confirm">Ya, Hapus</a>
                <button onclick="closeDeleteModal()" class="btn btn-cancel">Batal</button>
            </div>
        </div>
    </div>

    <script>
        // Modal untuk hapus pengaduan
        function showDeleteModal() {
            document.getElementById('deleteModal').style.display = 'flex';
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('deleteModal');
            if (event.target === modal) {
                closeDeleteModal();
            }
        }
    </script>
</body>
</html>