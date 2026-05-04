<?php
session_start();
require_once '../koneksi.php';

// Cek apakah user sudah login dan role-nya user
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header('Location: ../auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Cek apakah parameter id ada
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$id_pengaduan = $_GET['id'];

try {
    // Cek apakah pengaduan milik user yang login dan statusnya masih '0'
    $check_stmt = $pdo->prepare("SELECT * FROM pengaduan WHERE id_pengaduan = ? AND id_user = ? AND status = '0'");
    $check_stmt->execute([$id_pengaduan, $user_id]);
    $pengaduan = $check_stmt->fetch();

    if (!$pengaduan) {
        $_SESSION['error'] = 'Pengaduan tidak ditemukan atau tidak dapat dihapus!';
        header('Location: index.php');
        exit();
    }

    // Hapus file foto jika ada
    if (!empty($pengaduan['foto']) && file_exists('../uploads/' . $pengaduan['foto'])) {
        unlink('../uploads/' . $pengaduan['foto']);
    }

    // Hapus pengaduan dari database
    $delete_stmt = $pdo->prepare("DELETE FROM pengaduan WHERE id_pengaduan = ?");
    $delete_stmt->execute([$id_pengaduan]);

    $_SESSION['success'] = 'Pengaduan berhasil dihapus!';
    
} catch (PDOException $e) {
    $_SESSION['error'] = 'Terjadi kesalahan sistem: ' . $e->getMessage();
}

// Redirect kembali ke dashboard
header('Location: index.php');
exit();
?>