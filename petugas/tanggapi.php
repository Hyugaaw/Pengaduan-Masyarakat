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
$error = '';
$success = '';

// Ambil data pengaduan
try {
    $stmt = $pdo->prepare("
        SELECT p.*, u.nama as nama_pengadu 
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
} catch (PDOException $e) {
    $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tanggapan = trim($_POST['tanggapan']);
    $status = $_POST['status'];

    if (empty($tanggapan)) {
        $error = 'Isi tanggapan wajib diisi!';
    } elseif (strlen($tanggapan) < 10) {
        $error = 'Isi tanggapan minimal 10 karakter!';
    } else {
        try {
            // Mulai transaction
            $pdo->beginTransaction();

            // Insert tanggapan
            $insert_stmt = $pdo->prepare("INSERT INTO tanggapan (id_pengaduan, tanggapan, id_petugas) VALUES (?, ?, ?)");
            $insert_stmt->execute([$id_pengaduan, $tanggapan, $user_id]);
            
            // Update status pengaduan
            $update_stmt = $pdo->prepare("UPDATE pengaduan SET status = ? WHERE id_pengaduan = ?");
            $update_stmt->execute([$status, $id_pengaduan]);
            
            // Commit transaction
            $pdo->commit();
            
            $success = 'Tanggapan berhasil dikirim dan status pengaduan diperbarui!';
            $_POST = array(); // Clear form
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tanggapi Pengaduan - Sistem Pengaduan Masyarakat</title>
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
        
        .alert {
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 0.9rem;
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
        
        .pengaduan-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            border-left: 4px solid #3498db;
        }
        
        .pengaduan-info h3 {
            color: #2c3e50;
            margin-bottom: 15px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
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
        
        .laporan-preview {
            background: #e8f4fd;
            padding: 15px;
            border-radius: 5px;
            margin-top: 10px;
            font-size: 0.9rem;
            line-height: 1.5;
            max-height: 150px;
            overflow-y: auto;
        }
        
        /* Form Styles */
        .form-container {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 8px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #2c3e50;
        }
        
        textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
            resize: vertical;
            min-height: 120px;
            font-family: inherit;
        }
        
        textarea:focus {
            outline: none;
            border-color: #3498db;
        }
        
        select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
            background: white;
        }
        
        select:focus {
            outline: none;
            border-color: #3498db;
        }
        
        .char-count {
            text-align: right;
            font-size: 0.8rem;
            color: #7f8c8d;
            margin-top: 5px;
        }
        
        .status-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        
        .status-option {
            background: white;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .status-option:hover {
            border-color: #3498db;
        }
        
        .status-option.selected {
            border-color: #3498db;
            background: #e8f4fd;
        }
        
        .status-option input {
            display: none;
        }
        
        .status-label {
            display: block;
            font-weight: 500;
            color: #2c3e50;
        }
        
        .status-desc {
            font-size: 0.8rem;
            color: #7f8c8d;
            margin-top: 5px;
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
            font-size: 1rem;
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
        
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 30px;
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
                <li><a href="index.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'tanggapi.php') ? 'active' : ''; ?>">Dashboard</a></li>
                <li><a href="tanggapan_saya.php">Tanggapan Saya</a></li>
                <li><a href="../auth/logout.php">Logout</a></li>
            </ul>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <h1>Tanggapi Pengaduan</h1>
                <p>Berikan tanggapan untuk pengaduan masyarakat</p>
            </div>
            
            <div class="content-container">
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <?php echo htmlspecialchars($success); ?>
                        <br><a href="index.php" class="btn" style="margin-top: 10px; padding: 8px 16px;">Kembali ke Dashboard</a>
                    </div>
                <?php endif; ?>
                
                <!-- Informasi Pengaduan -->
                <div class="pengaduan-info">
                    <h3>Informasi Pengaduan</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Pengadu</span>
                            <span class="info-value"><?php echo htmlspecialchars($pengaduan['nama_pengadu']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Tanggal</span>
                            <span class="info-value"><?php echo date('d F Y H:i', strtotime($pengaduan['tgl_pengaduan'])); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Status Saat Ini</span>
                            <span class="info-value">
                                <?php 
                                $status_text = '';
                                switch ($pengaduan['status']) {
                                    case '0': $status_text = 'Menunggu (0)'; break;
                                    case 'proses': $status_text = 'Diproses'; break;
                                    case 'selesai': $status_text = 'Selesai'; break;
                                }
                                echo $status_text;
                                ?>
                            </span>
                        </div>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Isi Laporan</span>
                        <div class="laporan-preview">
                            <?php echo htmlspecialchars($pengaduan['isi_laporan']); ?>
                        </div>
                    </div>
                </div>
                
                <!-- Form Tanggapan -->
                <div class="form-container">
                    <form method="POST" action="" id="tanggapanForm">
                        <div class="form-group">
                            <label for="tanggapan">Isi Tanggapan *</label>
                            <textarea 
                                id="tanggapan" 
                                name="tanggapan" 
                                placeholder="Tuliskan tanggapan Anda terhadap pengaduan ini..." 
                                required
                            ><?php echo isset($_POST['tanggapan']) ? htmlspecialchars($_POST['tanggapan']) : ''; ?></textarea>
                            <div class="char-count">
                                <span id="charCount">0</span> karakter (minimal 10)
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Perbarui Status Pengaduan *</label>
                            <div class="status-options">
                                <label class="status-option <?php echo (isset($_POST['status']) && $_POST['status'] == 'proses') || (!isset($_POST['status']) && $pengaduan['status'] == '0') ? 'selected' : ''; ?>">
                                    <input type="radio" name="status" value="proses" <?php echo (isset($_POST['status']) && $_POST['status'] == 'proses') || (!isset($_POST['status']) && $pengaduan['status'] == '0') ? 'checked' : ''; ?> required>
                                    <span class="status-label">Diproses</span>
                                    <span class="status-desc">Pengaduan sedang ditangani</span>
                                </label>
                                
                                <label class="status-option <?php echo (isset($_POST['status']) && $_POST['status'] == 'selesai') ? 'selected' : ''; ?>">
                                    <input type="radio" name="status" value="selesai" <?php echo (isset($_POST['status']) && $_POST['status'] == 'selesai') ? 'checked' : ''; ?>>
                                    <span class="status-label">Selesai</span>
                                    <span class="status-desc">Pengaduan telah selesai</span>
                                </label>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-success">Kirim Tanggapan</button>
                            <a href="lihat_pengaduan.php?id=<?php echo $id_pengaduan; ?>" class="btn btn-secondary">Kembali</a>
                            <a href="index.php" class="btn btn-secondary">Batal</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Character count for textarea
        const textarea = document.getElementById('tanggapan');
        const charCount = document.getElementById('charCount');
        
        textarea.addEventListener('input', function() {
            charCount.textContent = this.value.length;
            
            // Change color if below minimum
            if (this.value.length < 10) {
                charCount.style.color = '#e74c3c';
            } else {
                charCount.style.color = '#7f8c8d';
            }
        });
        
        // Initialize character count
        charCount.textContent = textarea.value.length;
        if (textarea.value.length < 10) {
            charCount.style.color = '#e74c3c';
        }
        
        // Status option selection
        const statusOptions = document.querySelectorAll('.status-option');
        statusOptions.forEach(option => {
            option.addEventListener('click', function() {
                const radio = this.querySelector('input[type="radio"]');
                radio.checked = true;
                
                // Remove selected class from all options
                statusOptions.forEach(opt => opt.classList.remove('selected'));
                // Add selected class to clicked option
                this.classList.add('selected');
            });
        });
        
        // Form validation
        document.getElementById('tanggapanForm').addEventListener('submit', function(e) {
            const tanggapan = textarea.value.trim();
            
            if (tanggapan.length < 10) {
                e.preventDefault();
                alert('Isi tanggapan minimal 10 karakter!');
                textarea.focus();
            }
        });
    </script>
</body>
</html>