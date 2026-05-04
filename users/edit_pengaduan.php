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

// Cek apakah parameter id ada
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$id_pengaduan = $_GET['id'];
$error = '';
$success = '';

// Ambil data pengaduan yang akan diedit
try {
    $stmt = $pdo->prepare("SELECT * FROM pengaduan WHERE id_pengaduan = ? AND id_user = ? AND status = '0'");
    $stmt->execute([$id_pengaduan, $user_id]);
    $pengaduan = $stmt->fetch();

    if (!$pengaduan) {
        $_SESSION['error'] = 'Pengaduan tidak ditemukan atau tidak dapat diedit!';
        header('Location: index.php');
        exit();
    }
} catch (PDOException $e) {
    $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $isi_laporan = trim($_POST['isi_laporan']);
    $foto_lama = $pengaduan['foto'];
    $foto_baru = $foto_lama;

    // Handle file upload jika ada foto baru
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $file_type = $_FILES['foto']['type'];
        $file_size = $_FILES['foto']['size'];
        
        if (in_array($file_type, $allowed_types)) {
            if ($file_size <= 5 * 1024 * 1024) { // 5MB max
                $file_extension = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
                $foto_baru = 'pengaduan_' . time() . '_' . $user_id . '.' . $file_extension;
                $upload_path = '../uploads/' . $foto_baru;
                
                if (!is_dir('../uploads')) {
                    mkdir('../uploads', 0777, true);
                }
                
                if (move_uploaded_file($_FILES['foto']['tmp_name'], $upload_path)) {
                    // Hapus foto lama jika ada dan jika foto baru berhasil diupload
                    if (!empty($foto_lama) && file_exists('../uploads/' . $foto_lama)) {
                        unlink('../uploads/' . $foto_lama);
                    }
                } else {
                    $error = 'Gagal mengupload foto.';
                    $foto_baru = $foto_lama; // Kembali ke foto lama
                }
            } else {
                $error = 'Ukuran foto maksimal 5MB.';
            }
        } else {
            $error = 'Format foto tidak didukung. Gunakan JPEG, JPG, PNG, atau GIF.';
        }
    }

    // Handle hapus foto
    if (isset($_POST['hapus_foto']) && $_POST['hapus_foto'] == '1') {
        if (!empty($foto_lama) && file_exists('../uploads/' . $foto_lama)) {
            unlink('../uploads/' . $foto_lama);
        }
        $foto_baru = null;
    }

    if (empty($isi_laporan)) {
        $error = 'Isi laporan wajib diisi!';
    } elseif (strlen($isi_laporan) < 10) {
        $error = 'Isi laporan minimal 10 karakter!';
    } else {
        try {
            // Update pengaduan
            $update_stmt = $pdo->prepare("UPDATE pengaduan SET isi_laporan = ?, foto = ? WHERE id_pengaduan = ?");
            $update_stmt->execute([$isi_laporan, $foto_baru, $id_pengaduan]);
            
            $success = 'Pengaduan berhasil diperbarui!';
            // Refresh data pengaduan
            $stmt->execute([$id_pengaduan, $user_id]);
            $pengaduan = $stmt->fetch();
            
        } catch (PDOException $e) {
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
    <title>Edit Pengaduan - Sistem Pengaduan Masyarakat</title>
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
        
        /* Form Styles */
        .form-container {
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
            min-height: 150px;
            font-family: inherit;
        }
        
        textarea:focus {
            outline: none;
            border-color: #3498db;
        }
        
        .file-upload {
            border: 2px dashed #e0e0e0;
            border-radius: 5px;
            padding: 20px;
            text-align: center;
            transition: border-color 0.3s;
        }
        
        .file-upload:hover {
            border-color: #3498db;
        }
        
        .file-upload input {
            display: none;
        }
        
        .file-upload label {
            cursor: pointer;
            color: #3498db;
            font-weight: 500;
        }
        
        .file-info {
            margin-top: 10px;
            font-size: 0.9rem;
            color: #7f8c8d;
        }
        
        .file-preview {
            margin-top: 10px;
            max-width: 300px;
        }
        
        .file-preview img {
            max-width: 100%;
            border-radius: 5px;
            margin-bottom: 10px;
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
        
        .btn-warning {
            background: #f39c12;
            color: white;
        }
        
        .btn-warning:hover {
            background: #e67e22;
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }
        
        .char-count {
            text-align: right;
            font-size: 0.8rem;
            color: #7f8c8d;
            margin-top: 5px;
        }
        
        .current-photo {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        
        .photo-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 10px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
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
                <li><a href="index.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'edit_pengaduan.php') ? 'active' : ''; ?>">Dashboard</a></li>
                <li><a href="pengaduan.php">Ajukan Pengaduan</a></li>
                <li><a href="pengaduan_saya.php">Pengaduan Saya</a></li>
                <li><a href="../auth/logout.php">Logout</a></li>
            </ul>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <h1>Edit Pengaduan</h1>
                <p>Perbarui informasi pengaduan Anda</p>
            </div>
            
            <div class="form-container">
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
                
                <form method="POST" action="" enctype="multipart/form-data" id="pengaduanForm">
                    <div class="form-group">
                        <label for="isi_laporan">Isi Laporan *</label>
                        <textarea 
                            id="isi_laporan" 
                            name="isi_laporan" 
                            placeholder="Jelaskan keluhan atau masalah yang Anda alami secara detail..." 
                            required
                        ><?php echo htmlspecialchars($pengaduan['isi_laporan']); ?></textarea>
                        <div class="char-count">
                            <span id="charCount"><?php echo strlen($pengaduan['isi_laporan']); ?></span> karakter
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Foto Saat Ini</label>
                        <div class="current-photo">
                            <?php if (!empty($pengaduan['foto']) && file_exists('../uploads/' . $pengaduan['foto'])): ?>
                                <div class="file-preview">
                                    <img src="../uploads/<?php echo htmlspecialchars($pengaduan['foto']); ?>" alt="Foto Pengaduan">
                                    <div class="photo-actions">
                                        <a href="../uploads/<?php echo htmlspecialchars($pengaduan['foto']); ?>" target="_blank" class="btn-small btn-view">Lihat Full</a>
                                        <div class="checkbox-group">
                                            <input type="checkbox" id="hapus_foto" name="hapus_foto" value="1">
                                            <label for="hapus_foto" style="margin: 0; font-weight: normal;">Hapus foto</label>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <p style="color: #7f8c8d; font-style: italic;">Tidak ada foto</p>
                            <?php endif; ?>
                        </div>
                        
                        <label for="foto">Ganti Foto (Opsional)</label>
                        <div class="file-upload">
                            <input type="file" id="foto" name="foto" accept="image/*">
                            <label for="foto">📷 Klik untuk memilih foto baru</label>
                            <div class="file-info">Format: JPEG, JPG, PNG, GIF | Maksimal: 5MB</div>
                            <div class="file-preview" id="filePreview" style="display: none;">
                                <img id="previewImage" src="" alt="Preview">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-success">Update Pengaduan</button>
                        <a href="index.php" class="btn btn-secondary">Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Character count for textarea
        const textarea = document.getElementById('isi_laporan');
        const charCount = document.getElementById('charCount');
        
        textarea.addEventListener('input', function() {
            charCount.textContent = this.value.length;
        });
        
        // File preview for new photo
        const fileInput = document.getElementById('foto');
        const filePreview = document.getElementById('filePreview');
        const previewImage = document.getElementById('previewImage');
        
        fileInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    previewImage.src = e.target.result;
                    filePreview.style.display = 'block';
                }
                
                reader.readAsDataURL(file);
            } else {
                filePreview.style.display = 'none';
            }
        });
        
        // Form validation
        document.getElementById('pengaduanForm').addEventListener('submit', function(e) {
            const isiLaporan = textarea.value.trim();
            
            if (isiLaporan.length < 10) {
                e.preventDefault();
                alert('Isi laporan minimal 10 karakter!');
                textarea.focus();
            }
        });
        
        // Confirm delete photo
        const hapusFotoCheckbox = document.getElementById('hapus_foto');
        if (hapusFotoCheckbox) {
            hapusFotoCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    if (!confirm('Yakin ingin menghapus foto?')) {
                        this.checked = false;
                    }
                }
            });
        }
    </script>
</body>
</html>