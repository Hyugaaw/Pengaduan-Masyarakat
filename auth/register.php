<?php
session_start();
require_once '../koneksi.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama = trim($_POST['nama']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $telp = trim($_POST['telp']);
    $role = $_POST['role'];

    // Validasi input
    if (empty($nama) || empty($username) || empty($password) || empty($confirm_password) || empty($role)) {
        $error = 'Semua field wajib diisi!';
    } elseif ($password !== $confirm_password) {
        $error = 'Konfirmasi password tidak sesuai!';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter!';
    } else {
        try {
            // Cek apakah username sudah ada
            $check_stmt = $pdo->prepare("SELECT id_user FROM users WHERE username = ?");
            $check_stmt->execute([$username]);
            
            if ($check_stmt->rowCount() > 0) {
                $error = 'Username sudah digunakan!';
            } else {
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert user baru
                $insert_stmt = $pdo->prepare("INSERT INTO users (nama, username, password, telp, role) VALUES (?, ?, ?, ?, ?)");
                $insert_stmt->execute([$nama, $username, $hashed_password, $telp, $role]);
                
                $success = 'Registrasi berhasil! Silakan login.';
                $_POST = array(); // Clear form
            }
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
    <title>Registrasi - Sistem Pengaduan Masyarakat</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #3498db, #2c3e50);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 450px;
            overflow: hidden;
        }
        
        .header {
            background: #2c3e50;
            color: white;
            padding: 30px 20px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 1.8rem;
            margin-bottom: 5px;
        }
        
        .header p {
            opacity: 0.8;
            font-size: 0.9rem;
        }
        
        .form-container {
            padding: 30px;
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
        
        input, select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        input:focus, select:focus {
            outline: none;
            border-color: #3498db;
        }
        
        select {
            background: white;
            cursor: pointer;
        }
        
        .btn {
            width: 100%;
            padding: 12px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .btn:hover {
            background: #2980b9;
        }
        
        .login-link {
            text-align: center;
            margin-top: 20px;
            color: #7f8c8d;
        }
        
        .login-link a {
            color: #3498db;
            text-decoration: none;
            font-weight: 500;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        .password-requirements {
            font-size: 0.8rem;
            color: #7f8c8d;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Daftar Akun</h1>
            <p>Bergabung dengan Sistem Pengaduan Masyarakat</p>
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
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="nama">Nama Lengkap</label>
                    <input type="text" id="nama" name="nama" value="<?php echo isset($_POST['nama']) ? htmlspecialchars($_POST['nama']) : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="telp">Nomor Telepon</label>
                    <input type="tel" id="telp" name="telp" value="<?php echo isset($_POST['telp']) ? htmlspecialchars($_POST['telp']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="role">Peran (Role)</label>
                    <select id="role" name="role" required>
                        <option value="">Pilih Peran</option>
                        <option value="user" <?php echo (isset($_POST['role']) && $_POST['role'] == 'user') ? 'selected' : ''; ?>>User (Masyarakat)</option>
                        <option value="petugas" <?php echo (isset($_POST['role']) && $_POST['role'] == 'petugas') ? 'selected' : ''; ?>>Petugas</option>
                        <option value="admin" <?php echo (isset($_POST['role']) && $_POST['role'] == 'admin') ? 'selected' : ''; ?>>Admin</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                    <div class="password-requirements">Minimal 6 karakter</div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Konfirmasi Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                
                <button type="submit" class="btn">Daftar</button>
            </form>
            
            <div class="login-link">
                Sudah punya akun? <a href="../auth/login.php">Login di sini</a>
            </div>
        </div>
    </div>

    <script>
        // Real-time password confirmation check
        document.addEventListener('DOMContentLoaded', function() {
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            
            function validatePassword() {
                if (password.value !== confirmPassword.value) {
                    confirmPassword.style.borderColor = '#d63031';
                } else {
                    confirmPassword.style.borderColor = '#00b894';
                }
            }
            
            password.addEventListener('input', validatePassword);
            confirmPassword.addEventListener('input', validatePassword);
        });
    </script>
</body>
</html>