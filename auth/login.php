<?php
session_start();
require_once '../koneksi.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $role = $_POST['role'];

    // Validasi input
    if (empty($username) || empty($password) || empty($role)) {
        $error = 'Semua field wajib diisi!';
    } else {
        try {
            // Cek user di database
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND role = ?");
            $stmt->execute([$username, $role]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // Login berhasil
                $_SESSION['user_id'] = $user['id_user'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['nama'] = $user['nama'];
                $_SESSION['role'] = $user['role'];
                
                // Redirect berdasarkan role
                switch ($user['role']) {
                    case 'admin':
                        header('Location: ../admin/index.php');
                        break;
                    case 'petugas':
                        header('Location: ../petugas/index.php');
                        break;
                    case 'user':
                        header('Location: ../users/index.php');
                        break;
                    default:
                        header('Location: ../index.php');
                }
                exit();
            } else {
                $error = 'Username, password, atau role tidak sesuai!';
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
    <title>Login - Sistem Pengaduan Masyarakat</title>
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
            max-width: 400px;
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
        
        .register-link {
            text-align: center;
            margin-top: 20px;
            color: #7f8c8d;
        }
        
        .register-link a {
            color: #3498db;
            text-decoration: none;
            font-weight: 500;
        }
        
        .register-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Login</h1>
            <p>Masuk ke Sistem Pengaduan Masyarakat</p>
        </div>
        
        <div class="form-container">
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
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
                
                <button type="submit" class="btn">Login</button>
            </form>
            
            <div class="register-link">
                Belum punya akun? <a href="register.php">Daftar di sini</a>
            </div>
        </div>
    </div>
</body>
</html>