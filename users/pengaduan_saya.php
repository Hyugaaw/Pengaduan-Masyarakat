<?php
session_start();
require_once '../koneksi.php';

// Cek apakah user sudah login dan role-nya user
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header('Location: ../auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_nama = $_SESSION['nama'];

// Ambil semua pengaduan user tanpa filter untuk menghitung statistik total
$all_query = $pdo->prepare("
    SELECT p.*, COUNT(t.id_tanggapan) as jumlah_tanggapan 
    FROM pengaduan p 
    LEFT JOIN tanggapan t ON p.id_pengaduan = t.id_pengaduan 
    WHERE p.id_user = ? 
    GROUP BY p.id_pengaduan 
    ORDER BY p.tgl_pengaduan DESC
");
$all_query->execute([$user_id]);
$all_pengaduan = $all_query->fetchAll();

// Hitung statistik berdasarkan semua pengaduan (tanpa filter)
$total_pengaduan = count($all_pengaduan);
$pengaduan_selesai = 0;
$pengaduan_proses = 0;
$pengaduan_menunggu = 0;
foreach ($all_pengaduan as $p) {
    if ($p['status'] == 'selesai') {
        $pengaduan_selesai++;
    } elseif ($p['status'] == 'proses') {
        $pengaduan_proses++;
    } elseif ($p['status'] == '0') {
        $pengaduan_menunggu++;
    }
}

// Siapkan filter: status (perhatikan '0') dan sort
$status_filter = (isset($_GET['status']) && $_GET['status'] !== '') ? $_GET['status'] : null;
$sort = (isset($_GET['sort']) && in_array($_GET['sort'], ['terbaru','terlama'])) ? $_GET['sort'] : 'terbaru';

// Tanda apakah ada filter aktif (digunakan untuk menampilkan tombol Reset)
$has_filter = ($status_filter !== null) || isset($_GET['sort']);

// Bangun query dengan filter yang benar
$sql = "
    SELECT p.*, COUNT(t.id_tanggapan) as jumlah_tanggapan
    FROM pengaduan p
    LEFT JOIN tanggapan t ON p.id_pengaduan = t.id_pengaduan
    WHERE p.id_user = ?
";
$params = [$user_id];
if ($status_filter !== null) {
    $sql .= " AND p.status = ?";
    $params[] = $status_filter;
}
$sql .= " GROUP BY p.id_pengaduan ";
$sql .= ($sort == 'terlama') ? " ORDER BY p.tgl_pengaduan ASC" : " ORDER BY p.tgl_pengaduan DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pengaduan = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaduan Saya - Sistem Pengaduan Masyarakat</title>
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
            background: #3498db;
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
        }
        
        .stat-card h3 {
            font-size: 2rem;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .stat-card p {
            color: #7f8c8d;
        }
        
        .stat-waiting { border-left: 4px solid #f39c12; }
        .stat-process { border-left: 4px solid #3498db; }
        .stat-done { border-left: 4px solid #27ae60; }
        .stat-total { border-left: 4px solid #9b59b6; }
        
        /* Actions */
        .actions {
            margin-bottom: 30px;
        }
        
        .btn {
            padding: 12px 24px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.3s;
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
        
        .filter-group select {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 1rem;
        }
        
        /* Pengaduan Table */
        .pengaduan-section {
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
        
        .status {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 500;
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
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .btn-small {
            padding: 6px 12px;
            font-size: 0.8rem;
            text-decoration: none;
            border-radius: 3px;
        }
        
        .btn-edit {
            background: #f39c12;
            color: white;
        }
        
        .btn-edit:hover {
            background: #e67e22;
        }
        
        .btn-delete {
            background: #e74c3c;
            color: white;
        }
        
        .btn-delete:hover {
            background: #c0392b;
        }
        
        .btn-view {
            background: #3498db;
            color: white;
        }
        
        .btn-view:hover {
            background: #2980b9;
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

        .status-info {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }

        .pengaduan-count {
            color: #7f8c8d;
            font-size: 0.9rem;
            margin-bottom: 15px;
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
                <span class="role">User</span>
            </div>
            
            <ul class="nav-links">
                <li><a href="index.php">Dashboard</a></li>
                <li><a href="pengaduan.php">Ajukan Pengaduan</a></li>
                <li><a href="pengaduan_saya.php" class="active">Pengaduan Saya</a></li>
                <li><a href="../auth/logout.php">Logout</a></li>
            </ul>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <h1>Pengaduan Saya</h1>
                <p>Daftar semua pengaduan yang telah Anda ajukan</p>
            </div>

            <!-- Status Information -->
            <div class="status-info">
                <strong>Keterangan Status:</strong><br>
                • <strong>Menunggu (0)</strong> - Pengaduan belum diproses<br>
                • <strong>Diproses (proses)</strong> - Pengaduan sedang ditangani petugas<br>
                • <strong>Selesai (selesai)</strong> - Pengaduan telah selesai ditangani
            </div>
            
            <!-- Statistics -->
            <div class="stats">
                <div class="stat-card stat-total">
                    <h3><?php echo $total_pengaduan; ?></h3>
                    <p>Total Pengaduan</p>
                </div>
                <div class="stat-card stat-waiting">
                    <h3><?php echo $pengaduan_menunggu; ?></h3>
                    <p>Menunggu (0)</p>
                </div>
                <div class="stat-card stat-process">
                    <h3><?php echo $pengaduan_proses; ?></h3>
                    <p>Diproses</p>
                </div>
                <div class="stat-card stat-done">
                    <h3><?php echo $pengaduan_selesai; ?></h3>
                    <p>Selesai</p>
                </div>
            </div>
            
            <!-- Actions -->
            <div class="actions">
                <a href="pengaduan.php" class="btn btn-success">+ Ajukan Pengaduan Baru</a>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <h3>Filter Pengaduan</h3>
                <form method="GET" action="" class="filter-form">
                    <div class="filter-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" onchange="this.form.submit()">
                            <option value="" <?php echo ($status_filter === null) ? 'selected' : ''; ?>>Semua Status</option>
                            <option value="0" <?php echo ($status_filter === '0') ? 'selected' : ''; ?>>Menunggu (0)</option>
                            <option value="proses" <?php echo ($status_filter === 'proses') ? 'selected' : ''; ?>>Diproses</option>
                            <option value="selesai" <?php echo ($status_filter === 'selesai') ? 'selected' : ''; ?>>Selesai</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="sort">Urutkan</label>
                        <select id="sort" name="sort" onchange="this.form.submit()">
                            <option value="terbaru" <?php echo ($sort == 'terbaru') ? 'selected' : ''; ?>>Terbaru</option>
                            <option value="terlama" <?php echo ($sort == 'terlama') ? 'selected' : ''; ?>>Terlama</option>
                        </select>
                    </div>
                    <?php if ($has_filter): ?>
                        <a href="pengaduan_saya.php" class="btn btn-secondary">Reset Filter</a>
                    <?php endif; ?>
                </form>
            </div>
            
            <!-- Daftar Pengaduan -->
            <div class="pengaduan-section">
                <h2 class="section-title">Semua Pengaduan</h2>
                
                <div class="pengaduan-count">
                    Menampilkan <?php echo count($pengaduan); ?> dari <?php echo $total_pengaduan; ?> pengaduan
                </div>
                
                <?php if (empty($pengaduan)): ?>
                    <?php if ($has_filter): ?>
                        <div class="empty-state">
                            <div>🔍</div>
                            <h3>Tidak ada hasil</h3>
                            <p>Tidak ada pengaduan dengan filter yang dipilih</p>
                            <a href="pengaduan_saya.php" class="btn" style="margin-top: 15px;">Reset Filter</a>
                        </div>
                    <?php elseif ($total_pengaduan == 0): ?>
                        <div class="empty-state">
                            <div>📝</div>
                            <h3>Belum ada pengaduan</h3>
                            <p>Ajukan pengaduan pertama Anda untuk memulai</p>
                            <a href="pengaduan.php" class="btn" style="margin-top: 15px;">Ajukan Pengaduan</a>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <div>📝</div>
                            <h3>Tidak ada hasil</h3>
                            <p>Tidak ada pengaduan yang sesuai dengan kriteria pencarian.</p>
                            <a href="pengaduan_saya.php" class="btn" style="margin-top: 15px;">Reset Filter</a>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Isi Laporan</th>
                                <th>Status</th>
                                <th>Tanggapan</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pengaduan as $p): ?>
                                <tr>
                                    <td><?php echo date('d M Y H:i', strtotime($p['tgl_pengaduan'])); ?></td>
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
                                        <?php if ($p['status'] == '0'): ?>
                                            <a href="edit_pengaduan.php?id=<?php echo $p['id_pengaduan']; ?>" class="btn-small btn-edit">Edit</a>
                                            <a href="hapus_pengaduan.php?id=<?php echo $p['id_pengaduan']; ?>" class="btn-small btn-delete" onclick="return confirm('Yakin hapus pengaduan?')">Hapus</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Auto submit filter form when selections change
        document.addEventListener('DOMContentLoaded', function() {
            const filterSelects = document.querySelectorAll('select[name="status"], select[name="sort"]');
            filterSelects.forEach(select => {
                select.addEventListener('change', function() {
                    this.form.submit();
                });
            });
        });
    </script>
</body>
</html>