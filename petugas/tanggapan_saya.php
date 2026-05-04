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

// Query untuk mendapatkan semua tanggapan yang dibuat oleh petugas ini
$tanggapan_query = $pdo->prepare("
    SELECT t.*, p.isi_laporan, p.status as status_pengaduan, u.nama as nama_pengadu,
           p.id_pengaduan, p.tgl_pengaduan
    FROM tanggapan t
    JOIN pengaduan p ON t.id_pengaduan = p.id_pengaduan
    JOIN users u ON p.id_user = u.id_user
    WHERE t.id_petugas = ?
    ORDER BY t.tgl_tanggapan DESC
");
$tanggapan_query->execute([$user_id]);
$tanggapan_list = $tanggapan_query->fetchAll();

// Hitung statistik tanggapan
$total_tanggapan = count($tanggapan_list);
$tanggapan_selesai = 0;
$tanggapan_proses = 0;
$tanggapan_menunggu = 0;

foreach ($tanggapan_list as $t) {
    if ($t['status_pengaduan'] == 'selesai') {
        $tanggapan_selesai++;
    } elseif ($t['status_pengaduan'] == 'proses') {
        $tanggapan_proses++;
    } elseif ($t['status_pengaduan'] == '0') {
        $tanggapan_menunggu++;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tanggapan Saya - Sistem Pengaduan Masyarakat</title>
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
        
        /* Tanggapan Table */
        .tanggapan-section {
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
            flex-wrap: wrap;
        }
        
        .btn-small {
            padding: 6px 12px;
            font-size: 0.8rem;
            text-decoration: none;
            border-radius: 3px;
            white-space: nowrap;
        }
        
        .btn-lihat {
            background: #3498db;
            color: white;
        }
        
        .btn-lihat:hover {
            background: #2980b9;
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
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #7f8c8d;
        }
        
        .empty-state div {
            font-size: 3rem;
            margin-bottom: 10px;
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

        .tanggapan-count {
            color: #7f8c8d;
            font-size: 0.9rem;
            margin-bottom: 15px;
        }

        .btn {
            padding: 8px 16px;
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
        
        .btn-secondary {
            background: #95a5a6;
        }
        
        .btn-secondary:hover {
            background: #7f8c8d;
        }

        .tanggapan-preview {
            font-size: 0.9rem;
            line-height: 1.4;
            color: #555;
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
                <li><a href="index.php">Dashboard</a></li>
                <li><a href="tanggapan_saya.php" class="active">Tanggapan Saya</a></li>
                <li><a href="../auth/logout.php">Logout</a></li>
            </ul>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <h1>Tanggapan Saya</h1>
                <p>Lihat semua tanggapan yang telah Anda berikan kepada pengaduan masyarakat</p>
            </div>

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
                    <h3><?php echo $total_tanggapan; ?></h3>
                    <p>Total Tanggapan</p>
                </div>
                <div class="stat-card stat-waiting">
                    <h3><?php echo $tanggapan_menunggu; ?></h3>
                    <p>Menunggu (0)</p>
                </div>
                <div class="stat-card stat-process">
                    <h3><?php echo $tanggapan_proses; ?></h3>
                    <p>Diproses</p>
                </div>
                <div class="stat-card stat-done">
                    <h3><?php echo $tanggapan_selesai; ?></h3>
                    <p>Selesai</p>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <h3>Filter Tanggapan</h3>
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
                        <a href="tanggapan_saya.php" class="btn btn-secondary">Reset Filter</a>
                    <?php endif; ?>
                </form>
            </div>
            
            <!-- Daftar Tanggapan -->
            <div class="tanggapan-section">
                <h2 class="section-title">Daftar Tanggapan Saya</h2>
                
                <div class="tanggapan-count">
                    Menampilkan <?php echo count($tanggapan_list); ?> tanggapan
                </div>
                
                <?php if (empty($tanggapan_list)): ?>
                    <div class="empty-state">
                        <div>📋</div>
                        <h3>Belum ada tanggapan</h3>
                        <p>Anda belum memberikan tanggapan ke pengaduan manapun</p>
                    </div>
                <?php else: ?>
                    <?php
                    // Filter berdasarkan status jika ada
                    $filtered_tanggapan = $tanggapan_list;
                    
                    if (isset($_GET['status']) && $_GET['status'] !== '') {
                        $filtered_tanggapan = array_filter($tanggapan_list, function($item) {
                            return $item['status_pengaduan'] == $_GET['status'];
                        });
                    }
                    
                    // Sorting
                    if (isset($_GET['sort']) && $_GET['sort'] == 'terlama') {
                        $filtered_tanggapan = array_reverse($filtered_tanggapan);
                    }
                    ?>
                    
                    <?php if (empty($filtered_tanggapan)): ?>
                        <div class="empty-state">
                            <div>🔍</div>
                            <h3>Tidak ada hasil</h3>
                            <p>Tidak ada tanggapan dengan filter yang dipilih</p>
                        </div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Tanggal Tanggapan</th>
                                    <th>Pengadu</th>
                                    <th>Tanggapan</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($filtered_tanggapan as $t): ?>
                                    <tr>
                                        <td><?php echo date('d M Y H:i', strtotime($t['tgl_tanggapan'])); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($t['nama_pengadu']); ?>
                                        </td>
                                        <td>
                                            <div class="tanggapan-preview">
                                                <strong><?php echo htmlspecialchars(substr($t['tanggapan'], 0, 100)); ?></strong>
                                                <?php if (strlen($t['tanggapan']) > 100): ?>
                                                    ...
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php 
                                            $status_class = '';
                                            $status_text = '';
                                            switch ($t['status_pengaduan']) {
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
                                        <td class="action-buttons">
                                            <a href="lihat_pengaduan.php?id=<?php echo $t['id_pengaduan']; ?>" class="btn-small btn-lihat">Lihat</a>
                                            <?php if ($t['status_pengaduan'] == 'proses'): ?>
                                                <a href="edit_tanggapan.php?id=<?php echo $t['id_pengaduan']; ?>" class="btn-small btn-edit">Edit</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
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
