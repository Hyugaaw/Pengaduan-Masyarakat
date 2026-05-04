<?php
session_start();
require_once '../koneksi.php';

// Pastikan role petugas
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'petugas') {
    header('Location: ../auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// id_pengaduan harus diberikan
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = 'Permintaan tidak valid.';
    header('Location: index.php');
    exit();
}

$id_pengaduan = $_GET['id'];

try {
    // Pastikan tanggapan milik petugas ini ada
    $check = $pdo->prepare("SELECT * FROM tanggapan WHERE id_pengaduan = ? AND id_petugas = ?");
    $check->execute([$id_pengaduan, $user_id]);
    $row = $check->fetch();

    if (!$row) {
        $_SESSION['error'] = 'Tanggapan tidak ditemukan atau Anda tidak memiliki izin untuk menghapusnya.';
        header('Location: lihat_pengaduan.php?id=' . urlencode($id_pengaduan));
        exit();
    }

    // Hapus tanggapan milik petugas ini
    $del = $pdo->prepare("DELETE FROM tanggapan WHERE id_pengaduan = ? AND id_petugas = ?");
    $del->execute([$id_pengaduan, $user_id]);

    $_SESSION['success'] = 'Tanggapan berhasil dihapus.';
    header('Location: lihat_pengaduan.php?id=' . urlencode($id_pengaduan));
    exit();

} catch (PDOException $e) {
    $_SESSION['error'] = 'Terjadi kesalahan sistem: ' . $e->getMessage();
    header('Location: lihat_pengaduan.php?id=' . urlencode($id_pengaduan));
    exit();
}

?>
