
<?php
session_start();
require_once "../config/database.php";

/* =========================================================
   AUTH
========================================================= */
if (!isset($_SESSION['id_user'])) {
    header("Location: ../auth/login.php");
    exit;
}

if (($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../index.php");
    exit;
}

/* =========================================================
   HELPER
========================================================= */
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$nama_user = $_SESSION['nama'] ?? 'Admin';
$pesan = '';
$pesan_type = '';

$role_valid = ['admin', 'kasir', 'karyawan', 'pelanggan'];

/*
 * Membuat profil karyawan jika belum ada.
 * Gaji awal selalu Rp0.
 */
function buatProfilKaryawan($conn, $id_user) {
    $cek = mysqli_prepare(
        $conn,
        "SELECT id_user FROM profil_karyawan WHERE id_user = ? LIMIT 1"
    );

    if (!$cek) {
        return false;
    }

    mysqli_stmt_bind_param($cek, "i", $id_user);
    mysqli_stmt_execute($cek);
    $hasil = mysqli_stmt_get_result($cek);
    $sudahAda = mysqli_num_rows($hasil) > 0;
    mysqli_stmt_close($cek);

    if ($sudahAda) {
        return true;
    }

    $insert = mysqli_prepare(
        $conn,
        "INSERT INTO profil_karyawan (id_user, gaji_bulanan)
         VALUES (?, 0)"
    );

    if (!$insert) {
        return false;
    }

    mysqli_stmt_bind_param($insert, "i", $id_user);
    $berhasil = mysqli_stmt_execute($insert);
    mysqli_stmt_close($insert);

    return $berhasil;
}

/* =========================================================
   TAMBAH PENGGUNA
========================================================= */
if (isset($_POST['tambah_pengguna'])) {
    $nama = trim($_POST['nama'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = trim($_POST['role'] ?? '');

    if ($nama === '' || $email === '' || $password === '' || $role === '') {
        $pesan = 'Semua data pengguna wajib diisi.';
        $pesan_type = 'error';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $pesan = 'Format email tidak valid.';
        $pesan_type = 'error';

    } elseif (mb_strlen($nama) > 100) {
        $pesan = 'Nama maksimal 100 karakter.';
        $pesan_type = 'error';

    } elseif (mb_strlen($password) < 6) {
        $pesan = 'Password minimal 6 karakter.';
        $pesan_type = 'error';

    } elseif (!in_array($role, $role_valid, true)) {
        $pesan = 'Role pengguna tidak valid.';
        $pesan_type = 'error';

    } else {
        $stmt = mysqli_prepare(
            $conn,
            "SELECT id_user FROM users WHERE email = ? LIMIT 1"
        );

        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $cek = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($cek) > 0) {
            $pesan = 'Email tersebut sudah digunakan.';
            $pesan_type = 'error';
            mysqli_stmt_close($stmt);

        } else {
            mysqli_stmt_close($stmt);

            // Membuat username unik dari email.
            $bagian_email = strstr($email, '@', true);
            $username_dasar = strtolower((string)$bagian_email);
            $username_dasar = preg_replace('/[^a-z0-9_.-]/', '', $username_dasar);
            $username_dasar = trim(substr($username_dasar ?: 'user', 0, 40), '._-');

            if ($username_dasar === '') {
                $username_dasar = 'user';
            }

            $username = $username_dasar;
            $nomor_username = 1;

            do {
                $cek_username_stmt = mysqli_prepare(
                    $conn,
                    "SELECT id_user FROM users WHERE username = ? LIMIT 1"
                );

                mysqli_stmt_bind_param($cek_username_stmt, "s", $username);
                mysqli_stmt_execute($cek_username_stmt);

                $cek_username = mysqli_stmt_get_result($cek_username_stmt);
                $username_ada = mysqli_num_rows($cek_username) > 0;

                mysqli_stmt_close($cek_username_stmt);

                if ($username_ada) {
                    $suffix = (string)$nomor_username++;
                    $username = substr(
                        $username_dasar,
                        0,
                        50 - strlen($suffix)
                    ) . $suffix;
                }

            } while ($username_ada);

            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            // Gunakan transaksi agar users dan profil karyawan
            // tidak terpisah jika salah satu proses gagal.
            mysqli_begin_transaction($conn);

            try {
                $stmt = mysqli_prepare(
                    $conn,
                    "INSERT INTO users (nama, email, username, password, role)
                     VALUES (?, ?, ?, ?, ?)"
                );

                if (!$stmt) {
                    throw new Exception("Gagal menyiapkan data pengguna.");
                }

                mysqli_stmt_bind_param(
                    $stmt,
                    "sssss",
                    $nama,
                    $email,
                    $username,
                    $password_hash,
                    $role
                );

                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception("Pengguna gagal ditambahkan.");
                }

                $id_user_baru = mysqli_insert_id($conn);
                mysqli_stmt_close($stmt);

                // Khusus role karyawan, buat profil dengan gaji Rp0.
                if ($role === 'karyawan') {
                    if (!buatProfilKaryawan($conn, $id_user_baru)) {
                        throw new Exception(
                            "Profil karyawan gagal dibuat. Periksa struktur tabel profil_karyawan."
                        );
                    }
                }

                mysqli_commit($conn);

                if ($role === 'karyawan') {
                    $pesan = 'Karyawan berhasil ditambahkan. Gaji pokok awal Rp0 dan dapat diatur melalui halaman penggajian.';
                } else {
                    $pesan = 'Pengguna berhasil ditambahkan.';
                }

                $pesan_type = 'success';

            } catch (Throwable $ex) {
                mysqli_rollback($conn);
                $pesan = $ex->getMessage();
                $pesan_type = 'error';
            }
        }
    }
}

/* =========================================================
   EDIT PENGGUNA
========================================================= */
if (isset($_POST['edit_pengguna'])) {
    $id_user = (int)($_POST['id_user'] ?? 0);
    $nama = trim($_POST['nama'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = trim($_POST['role'] ?? '');

    if ($id_user <= 0 || $nama === '' || $email === '' || $role === '') {
        $pesan = 'Data pengguna tidak lengkap.';
        $pesan_type = 'error';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $pesan = 'Format email tidak valid.';
        $pesan_type = 'error';

    } elseif (mb_strlen($nama) > 100) {
        $pesan = 'Nama maksimal 100 karakter.';
        $pesan_type = 'error';

    } elseif ($password !== '' && mb_strlen($password) < 6) {
        $pesan = 'Password baru minimal 6 karakter.';
        $pesan_type = 'error';

    } elseif (!in_array($role, $role_valid, true)) {
        $pesan = 'Role pengguna tidak valid.';
        $pesan_type = 'error';

    } else {
        $stmt = mysqli_prepare(
            $conn,
            "SELECT id_user FROM users
             WHERE email = ? AND id_user <> ?
             LIMIT 1"
        );

        mysqli_stmt_bind_param($stmt, "si", $email, $id_user);
        mysqli_stmt_execute($stmt);
        $cek = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($cek) > 0) {
            $pesan = 'Email tersebut sudah digunakan pengguna lain.';
            $pesan_type = 'error';
            mysqli_stmt_close($stmt);

        } else {
            mysqli_stmt_close($stmt);

            mysqli_begin_transaction($conn);

            try {
                if ($password !== '') {
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);

                    $stmt = mysqli_prepare(
                        $conn,
                        "UPDATE users
                         SET nama = ?, email = ?, password = ?, role = ?
                         WHERE id_user = ?"
                    );

                    mysqli_stmt_bind_param(
                        $stmt,
                        "ssssi",
                        $nama,
                        $email,
                        $password_hash,
                        $role,
                        $id_user
                    );

                } else {
                    $stmt = mysqli_prepare(
                        $conn,
                        "UPDATE users
                         SET nama = ?, email = ?, role = ?
                         WHERE id_user = ?"
                    );

                    mysqli_stmt_bind_param(
                        $stmt,
                        "sssi",
                        $nama,
                        $email,
                        $role,
                        $id_user
                    );
                }

                if (!$stmt || !mysqli_stmt_execute($stmt)) {
                    throw new Exception("Data pengguna gagal diperbarui.");
                }

                mysqli_stmt_close($stmt);

                // Jika role diubah menjadi karyawan,
                // pastikan profil karyawan tersedia.
                if ($role === 'karyawan') {
                    if (!buatProfilKaryawan($conn, $id_user)) {
                        throw new Exception(
                            "Profil karyawan gagal dibuat. Periksa struktur tabel profil_karyawan."
                        );
                    }
                }

                mysqli_commit($conn);

                $pesan = 'Data pengguna berhasil diperbarui.';
                $pesan_type = 'success';

            } catch (Throwable $ex) {
                mysqli_rollback($conn);
                $pesan = $ex->getMessage();
                $pesan_type = 'error';
            }
        }
    }
}

/* =========================================================
   HAPUS PENGGUNA
========================================================= */
if (isset($_GET['hapus'])) {
    $id_hapus = (int)$_GET['hapus'];

    if ($id_hapus <= 0) {
        $pesan = 'Pengguna tidak valid.';
        $pesan_type = 'error';

    } elseif ($id_hapus === (int)$_SESSION['id_user']) {
        $pesan = 'Akun yang sedang digunakan tidak dapat dihapus.';
        $pesan_type = 'error';

    } else {
        $stmt = mysqli_prepare(
            $conn,
            "DELETE FROM users WHERE id_user = ?"
        );

        mysqli_stmt_bind_param($stmt, "i", $id_hapus);

        if (mysqli_stmt_execute($stmt)) {
            if (mysqli_stmt_affected_rows($stmt) > 0) {
                $pesan = 'Pengguna berhasil dihapus.';
                $pesan_type = 'success';
            } else {
                $pesan = 'Pengguna tidak ditemukan.';
                $pesan_type = 'error';
            }
        } else {
            $pesan = 'Pengguna gagal dihapus. Pastikan pengguna tidak masih terhubung dengan data lain.';
            $pesan_type = 'error';
        }

        mysqli_stmt_close($stmt);
    }

    header(
        "Location: pengguna.php?status=" .
        urlencode($pesan_type) .
        "&pesan=" .
        urlencode($pesan)
    );
    exit;
}

/* =========================================================
   PESAN REDIRECT
========================================================= */
if (isset($_GET['pesan'])) {
    $pesan = $_GET['pesan'];
    $pesan_type = $_GET['status'] ?? 'success';
}

/* =========================================================
   FILTER DAN PENCARIAN
========================================================= */
$keyword = trim($_GET['q'] ?? '');
$filter_role = trim($_GET['role'] ?? '');

if (!in_array($filter_role, $role_valid, true)) {
    $filter_role = '';
}

$where = [];
$params = [];
$types = '';

if ($keyword !== '') {
    $where[] = "(nama LIKE ? OR email LIKE ?)";
    $like = '%' . $keyword . '%';

    $params[] = $like;
    $params[] = $like;
    $types .= 'ss';
}

if ($filter_role !== '') {
    $where[] = "role = ?";
    $params[] = $filter_role;
    $types .= 's';
}

$sql = "SELECT id_user, nama, email, role FROM users";

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY
    CASE role
        WHEN 'admin' THEN 1
        WHEN 'kasir' THEN 2
        WHEN 'karyawan' THEN 3
        WHEN 'pelanggan' THEN 4
        ELSE 5
    END,
    nama ASC";

if (!empty($params)) {
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result_pengguna = mysqli_stmt_get_result($stmt);
} else {
    $result_pengguna = mysqli_query($conn, $sql);
}

/* =========================================================
   STATISTIK
========================================================= */
$total_pengguna = 0;
$total_admin = 0;
$total_karyawan = 0;
$total_kasir = 0;
$total_pelanggan = 0;

$q_stat = mysqli_query(
    $conn,
    "SELECT
        COUNT(*) AS total,
        SUM(role = 'admin') AS admin,
        SUM(role = 'karyawan') AS karyawan,
        SUM(role = 'kasir') AS kasir,
        SUM(role = 'pelanggan') AS pelanggan
     FROM users"
);

if ($q_stat) {
    $stat = mysqli_fetch_assoc($q_stat);

    $total_pengguna = (int)($stat['total'] ?? 0);
    $total_admin = (int)($stat['admin'] ?? 0);
    $total_karyawan = (int)($stat['karyawan'] ?? 0);
    $total_kasir = (int)($stat['kasir'] ?? 0);
    $total_pelanggan = (int)($stat['pelanggan'] ?? 0);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pengguna - Toko Malaya</title>

<style>
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: Arial, Helvetica, sans-serif;
    background: #f5f7fb;
    color: #1e293b;
}

body.menu-open {
    overflow: hidden;
}

button, input, select {
    font-family: inherit;
}

/* TOPBAR */
.topbar {
    height: 72px;
    background: #fff;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 24px;
    position: sticky;
    top: 0;
    z-index: 900;
}

.topbar-left {
    display: flex;
    align-items: center;
    gap: 14px;
}

.hamburger {
    width: 42px;
    height: 42px;
    border: none;
    border-radius: 10px;
    background: #f1f5f9;
    color: #1e293b;
    font-size: 23px;
    cursor: pointer;
}

.brand {
    display: flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
    color: #111827;
}

.brand-logo, .drawer-logo {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    background: #1677d2;
    overflow: hidden;
}

.brand-logo img, .drawer-logo img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.brand-text {
    display: flex;
    flex-direction: column;
    line-height: 1.2;
}

.brand-text strong {
    font-size: 17px;
    font-weight: 800;
}

.brand-text span {
    font-size: 12px;
    color: #64748b;
    margin-top: 3px;
}

.top-user {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 6px 10px;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
}

.top-avatar, .drawer-avatar {
    border-radius: 50%;
    background: #e0efff;
    color: #1265a7;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
}

.top-avatar {
    width: 38px;
    height: 38px;
}

.top-user-info, .drawer-user-info {
    display: flex;
    flex-direction: column;
}

.top-user-info strong {
    font-size: 14px;
}

.top-user-info span, .drawer-user-info span {
    font-size: 12px;
    color: #64748b;
    margin-top: 2px;
}

/* DRAWER */
.menu-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15,23,42,.45);
    z-index: 1000;
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    transition: .25s;
}

.menu-overlay.show {
    opacity: 1;
    visibility: visible;
    pointer-events: auto;
}

.drawer {
    position: fixed;
    top: 0;
    left: 0;
    width: 335px;
    max-width: 88vw;
    height: 100vh;
    background: #fff;
    z-index: 1100;
    transform: translateX(-100%);
    transition: transform .28s ease;
    display: flex;
    flex-direction: column;
    box-shadow: 8px 0 30px rgba(15,23,42,.12);
}

.drawer.show {
    transform: translateX(0);
}

.drawer-header {
    min-height: 88px;
    padding: 18px 20px;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.drawer-brand {
    display: flex;
    align-items: center;
    gap: 12px;
}

.drawer-logo {
    width: 46px;
    height: 46px;
}

.drawer-brand-text {
    display: flex;
    flex-direction: column;
}

.drawer-brand-text strong {
    font-size: 18px;
}

.drawer-brand-text span {
    font-size: 12px;
    color: #64748b;
    margin-top: 3px;
}

.drawer-close {
    width: 40px;
    height: 40px;
    border: none;
    border-radius: 10px;
    background: #f1f5f9;
    font-size: 25px;
    cursor: pointer;
}

.drawer-user {
    margin: 20px;
    padding: 14px;
    border: 1px solid #dbe4ee;
    background: #f8fafc;
    border-radius: 14px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.drawer-avatar {
    width: 48px;
    height: 48px;
}

.drawer-user-info strong {
    font-size: 15px;
}

.drawer-menu {
    padding: 0 14px 20px;
    overflow-y: auto;
    flex: 1;
}

.menu-label {
    padding: 10px 12px;
    margin-bottom: 7px;
    font-size: 11px;
    font-weight: 800;
    color: #94a3b8;
    letter-spacing: .5px;
}

.menu-link {
    min-height: 45px;
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    margin-bottom: 4px;
    border-radius: 9px;
    color: #475569;
    font-size: 14px;
    font-weight: 700;
    text-decoration: none;
}

.menu-link:hover {
    background: #f1f5f9;
    color: #1677d2;
}

.menu-link.active {
    background: #e8f3ff;
    color: #1677d2;
    font-weight: 800;
    border-left: 3px solid #1677d2;
}

.menu-icon {
    width: 25px;
    min-width: 25px;
    text-align: center;
    font-size: 18px;
}

.drawer-footer {
    border-top: 1px solid #e5e7eb;
    padding: 14px;
}

.logout-link {
    min-height: 45px;
    padding: 10px 12px;
    display: flex;
    align-items: center;
    gap: 12px;
    text-decoration: none;
    border-radius: 9px;
    color: #ef4444;
    font-size: 14px;
    font-weight: 700;
}

.logout-link:hover {
    background: #fef2f2;
}

/* KONTEN */
.main {
    max-width: 1200px;
    margin: auto;
    padding: 30px 24px 50px;
}

.page-header {
    margin-bottom: 24px;
}

.page-header h1 {
    font-size: 28px;
    color: #102a43;
    margin-bottom: 7px;
}

.page-header p {
    color: #64748b;
    font-size: 14px;
}

.stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 22px;
}

.stat-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 3px 12px rgba(15,23,42,.04);
}

.stat-title {
    display: block;
    color: #64748b;
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 10px;
}

.stat-number {
    font-size: 26px;
    font-weight: 800;
    color: #155fa0;
}

.stat-desc {
    margin-top: 5px;
    font-size: 12px;
    color: #64748b;
}

.content-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 15px;
    padding: 22px;
    box-shadow: 0 3px 12px rgba(15,23,42,.04);
}

.toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    margin-bottom: 20px;
}

.filters {
    display: flex;
    flex: 1;
    max-width: 620px;
    gap: 8px;
}

.search-form {
    display: flex;
    flex: 1;
    gap: 8px;
}

.search-form input, .search-form select {
    height: 42px;
    border: 1px solid #dbe4ee;
    border-radius: 9px;
    padding: 0 13px;
    outline: none;
    font-size: 13px;
    background: #fff;
}

.search-form input {
    width: 100%;
}

.btn {
    min-height: 42px;
    border: none;
    border-radius: 9px;
    padding: 0 16px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
}

.btn-primary {
    background: #1677d2;
    color: #fff;
}

.btn-light {
    background: #f1f5f9;
    color: #334155;
}

.btn-edit {
    background: #e8f3ff;
    color: #1677d2;
}

.btn-delete {
    background: #fef2f2;
    color: #dc2626;
}

.alert {
    padding: 13px 15px;
    border-radius: 10px;
    margin-bottom: 18px;
    font-size: 13px;
    font-weight: 600;
}

.alert.success {
    background: #ecfdf5;
    color: #047857;
    border: 1px solid #a7f3d0;
}

.alert.error {
    background: #fef2f2;
    color: #b91c1c;
    border: 1px solid #fecaca;
}

.table-wrap {
    width: 100%;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

thead th {
    background: #f8fafc;
    color: #64748b;
    font-size: 12px;
    font-weight: 800;
    text-align: left;
    padding: 13px 14px;
    border-bottom: 1px solid #e5e7eb;
}

tbody td {
    padding: 14px;
    border-bottom: 1px solid #eef2f7;
    font-size: 13px;
    color: #334155;
    vertical-align: middle;
}

tbody tr:hover {
    background: #fbfdff;
}

.user-name {
    font-weight: 700;
    color: #1e293b;
}

.user-email {
    color: #64748b;
}

.role-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 28px;
    padding: 0 10px;
    border-radius: 8px;
    font-size: 11px;
    font-weight: 800;
}

.role-admin {
    background: #e8f3ff;
    color: #1677d2;
}

.role-kasir {
    background: #fff7ed;
    color: #c2410c;
}

.role-karyawan {
    background: #f0fdf4;
    color: #15803d;
}

.role-pelanggan {
    background: #ecfdf5;
    color: #047857;
}

.current-user {
    margin-left: 7px;
    color: #64748b;
    font-size: 11px;
    font-weight: 600;
}

.actions {
    display: flex;
    gap: 7px;
}

.action-btn {
    min-height: 34px;
    padding: 0 11px;
    border: none;
    border-radius: 7px;
    cursor: pointer;
    font-size: 12px;
    font-weight: 700;
    text-decoration: none;
}

.empty {
    text-align: center;
    padding: 45px 20px;
    color: #94a3b8;
    font-size: 13px;
}

/* MODAL */
.modal {
    position: fixed;
    inset: 0;
    background: rgba(15,23,42,.48);
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
    z-index: 1200;
}

.modal.show {
    display: flex;
}

.modal-box {
    width: 100%;
    max-width: 500px;
    max-height: calc(100vh - 40px);
    overflow-y: auto;
    background: #fff;
    border-radius: 15px;
    padding: 24px;
    box-shadow: 0 15px 45px rgba(15,23,42,.20);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.modal-header h2 {
    font-size: 19px;
}

.modal-close {
    width: 36px;
    height: 36px;
    border: none;
    border-radius: 8px;
    background: #f1f5f9;
    font-size: 22px;
    cursor: pointer;
}

.form-group {
    margin-bottom: 17px;
}

.form-group label {
    display: block;
    margin-bottom: 7px;
    font-size: 13px;
    font-weight: 700;
    color: #334155;
}

.form-group input, .form-group select {
    width: 100%;
    height: 44px;
    border: 1px solid #dbe4ee;
    border-radius: 9px;
    padding: 0 12px;
    outline: none;
    font-size: 14px;
    background: #fff;
}

.form-group input:focus, .form-group select:focus {
    border-color: #1677d2;
    box-shadow: 0 0 0 3px rgba(22,119,210,.10);
}

.form-help {
    display: block;
    margin-top: 6px;
    font-size: 11px;
    color: #94a3b8;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 9px;
    margin-top: 20px;
}

@media (max-width: 1000px) {
    .stats {
        grid-template-columns: repeat(2, 1fr);
    }

    .toolbar {
        align-items: stretch;
        flex-direction: column;
    }

    .filters {
        max-width: none;
    }
}

@media (max-width: 700px) {
    .topbar {
        padding: 0 15px;
    }

    .brand-text, .top-user-info {
        display: none;
    }

    .top-user {
        border: none;
        padding: 4px;
    }

    .main {
        padding: 24px 15px 40px;
    }

    .page-header h1 {
        font-size: 24px;
    }

    .stats {
        grid-template-columns: 1fr;
    }

    .filters, .search-form {
        flex-direction: column;
    }

    .search-form input, .search-form select, .search-form .btn {
        width: 100%;
    }
}

@media (max-width: 430px) {
    .drawer {
        width: 300px;
    }

    .content-card {
        padding: 15px;
    }

    .modal-box {
        padding: 19px;
    }
}
</style>
</head>

<body>

<div class="menu-overlay" id="menuOverlay" onclick="closeMenu()"></div>

<aside class="drawer" id="drawer">

    <div class="drawer-header">
        <div class="drawer-brand">
            <div class="drawer-logo">
                <img src="../assets/SembakoKu (3).png" alt="Toko Malaya">
            </div>

            <div class="drawer-brand-text">
                <strong>Toko Malaya</strong>
                <span>Panel Admin / Pemilik</span>
            </div>
        </div>

        <button type="button" class="drawer-close" onclick="closeMenu()">×</button>
    </div>

    <div class="drawer-user">
        <div class="drawer-avatar">
            <?= e(strtoupper(substr($nama_user, 0, 1))) ?>
        </div>

        <div class="drawer-user-info">
            <strong><?= e($nama_user) ?></strong>
            <span>Admin / Pemilik</span>
        </div>
    </div>

    <nav class="drawer-menu">

        <div class="menu-label">MENU UTAMA</div>

        <a href="dashboard.php" class="menu-link">
            <span class="menu-icon">🏠</span>
            Dashboard
        </a>

        <a href="produk.php" class="menu-link">
            <span class="menu-icon">📦</span>
            Produk dan Kategori
        </a>

        <a href="pengguna.php" class="menu-link active">
            <span class="menu-icon">👥</span>
            Pengguna
        </a>

        <a href="stok.php" class="menu-link">
            <span class="menu-icon">📦</span>
            Stok & Supplier
        </a>

        <a href="pengeluaran.php" class="menu-link">
            <span class="menu-icon">🧾</span>
            Pengeluaran
        </a>

        <a href="laporan.php" class="menu-link">
            <span class="menu-icon">📊</span>
            Laporan
        </a>

        <div class="menu-label">KEPEGAWAIAN</div>

        <a href="jadwal_karyawan.php" class="menu-link">
            <span class="menu-icon">🗓️</span>
            Jadwal Shift Karyawan
        </a>

        <a href="penggajian.php" class="menu-link">
        <span class="menu-icon">💰</span>
        Penggajian Karyawan
        </a>

        <a href="pengajuan_cuti.php" class="menu-link">
        <span class="menu-icon">📝</span>
        Pengajuan Cuti & Izin
        </a>

        <div class="menu-label">AKUN</div>

        <a href="profil.php" class="menu-link">
            <span class="menu-icon">👤</span>
            Profil
        </a>

    </nav>

    <div class="drawer-footer">
        <a href="../auth/logout.php"
           class="logout-link"
           onclick="return confirm('Apakah kamu yakin ingin keluar?');">
            <span class="menu-icon">🚪</span>Keluar
        </a>
    </div>

</aside>

<header class="topbar">

    <div class="topbar-left">

        <button type="button"
                class="hamburger"
                onclick="toggleMenu()"
                aria-label="Buka menu"
                aria-controls="drawer"
                aria-expanded="false"
                id="menuToggle">☰</button>

        <a href="dashboard.php" class="brand">
            <div class="brand-logo">
                <img src="../assets/SembakoKu (3).png" alt="Toko Malaya">
            </div>

            <div class="brand-text">
                <strong>Toko Malaya</strong>
                <span>Panel Admin</span>
            </div>
        </a>

    </div>

    <div class="top-user">
        <div class="top-avatar">
            <?= e(strtoupper(substr($nama_user, 0, 1))) ?>
        </div>

        <div class="top-user-info">
            <strong><?= e($nama_user) ?></strong>
            <span>Admin</span>
        </div>
    </div>

</header>

<main class="main">

    <div class="page-header">
        <h1>Manajemen Pengguna</h1>
        <p>Kelola akun admin, kasir, karyawan, dan pelanggan Toko Malaya.</p>
    </div>

    <?php if ($pesan !== ''): ?>
        <div class="alert <?= $pesan_type === 'error' ? 'error' : 'success' ?>">
            <?= e($pesan) ?>
        </div>
    <?php endif; ?>

    <section class="stats">

        <div class="stat-card">
            <span class="stat-title">Total Pengguna</span>
            <div class="stat-number"><?= number_format($total_pengguna) ?></div>
            <div class="stat-desc">Semua akun terdaftar</div>
        </div>

        <div class="stat-card">
            <span class="stat-title">Admin / Pemilik</span>
            <div class="stat-number"><?= number_format($total_admin) ?></div>
            <div class="stat-desc">Akun pengelola sistem</div>
        </div>

        <div class="stat-card">
            <span class="stat-title">Kasir</span>
            <div class="stat-number"><?= number_format($total_kasir) ?></div>
            <div class="stat-desc">Akun operasional</div>
        </div>

        <div class="stat-card">
            <span class="stat-title">Karyawan</span>
            <div class="stat-number"><?= number_format($total_karyawan) ?></div>
            <div class="stat-desc">Akun staf toko</div>
        </div>

    </section>

    <section class="content-card">

        <div class="toolbar">

            <div class="filters">

                <form method="GET" class="search-form">

                    <input
                        type="text"
                        name="q"
                        value="<?= e($keyword) ?>"
                        placeholder="Cari nama atau email..."
                    >

                    <select name="role">
                        <option value="">Semua Role</option>

                        <option value="admin" <?= $filter_role === 'admin' ? 'selected' : '' ?>>
                            Admin / Pemilik
                        </option>

                        <option value="kasir" <?= $filter_role === 'kasir' ? 'selected' : '' ?>>
                            Kasir
                        </option>

                        <option value="karyawan" <?= $filter_role === 'karyawan' ? 'selected' : '' ?>>
                            Staff / Karyawan
                        </option>

                        <option value="pelanggan" <?= $filter_role === 'pelanggan' ? 'selected' : '' ?>>
                            Pelanggan
                        </option>
                    </select>

                    <button type="submit" class="btn btn-light">Cari</button>

                    <?php if ($keyword !== '' || $filter_role !== ''): ?>
                        <a href="pengguna.php"
                           class="btn btn-light"
                           style="display:flex;align-items:center;justify-content:center;">
                            Reset
                        </a>
                    <?php endif; ?>

                </form>

            </div>

            <button type="button" class="btn btn-primary" onclick="openTambah()">
                + Tambah Pengguna
            </button>

        </div>

        <div class="table-wrap">

            <table>

                <thead>
                    <tr>
                        <th width="65">No</th>
                        <th>Nama</th>
                        <th>Email</th>
                        <th width="150">Role</th>
                        <th width="190">Aksi</th>
                    </tr>
                </thead>

                <tbody>

                <?php
                $no = 1;

                if ($result_pengguna && mysqli_num_rows($result_pengguna) > 0):
                    while ($row = mysqli_fetch_assoc($result_pengguna)):

                        $role_label = [
                            'admin' => 'Admin / Pemilik',
                            'kasir' => 'Kasir',
                            'karyawan' => 'Staff / Karyawan',
                            'pelanggan' => 'Pelanggan'
                        ][$row['role']] ?? ucfirst($row['role']);

                        $role_class = 'role-' . $row['role'];

                        $is_current =
                            (int)$row['id_user'] === (int)$_SESSION['id_user'];
                ?>

                    <tr>

                        <td><?= $no++ ?></td>

                        <td>
                            <span class="user-name">
                                <?= e($row['nama']) ?>
                            </span>

                            <?php if ($is_current): ?>
                                <span class="current-user">Akun Anda</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <span class="user-email">
                                <?= e($row['email']) ?>
                            </span>
                        </td>

                        <td>
                            <span class="role-badge <?= e($role_class) ?>">
                                <?= e($role_label) ?>
                            </span>
                        </td>

                        <td>
                            <div class="actions">

                                <button
                                    type="button"
                                    class="action-btn btn-edit"
                                    onclick='openEdit(
                                        <?= (int)$row["id_user"] ?>,
                                        <?= json_encode($row["nama"], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>,
                                        <?= json_encode($row["email"], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>,
                                        <?= json_encode($row["role"], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>
                                    )'>
                                    Edit
                                </button>

                                <?php if (!$is_current): ?>
                                    <a
                                        href="pengguna.php?hapus=<?= (int)$row['id_user'] ?>"
                                        class="action-btn btn-delete"
                                        style="display:flex;align-items:center;"
                                        onclick="return confirm('Yakin ingin menghapus pengguna ini?');">
                                        Hapus
                                    </a>
                                <?php endif; ?>

                            </div>
                        </td>

                    </tr>

                <?php
                    endwhile;
                else:
                ?>

                    <tr>
                        <td colspan="5">
                            <div class="empty">
                                <?php if ($keyword !== '' || $filter_role !== ''): ?>
                                    Pengguna yang dicari tidak ditemukan.
                                <?php else: ?>
                                    Belum ada pengguna.
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

</main>

<!-- MODAL TAMBAH -->
<div class="modal" id="modalTambah" onclick="closeModalOutside(event, 'modalTambah')">

    <div class="modal-box">

        <div class="modal-header">
            <h2>Tambah Pengguna</h2>
            <button type="button" class="modal-close" onclick="closeModal('modalTambah')">×</button>
        </div>

        <form method="POST">

            <div class="form-group">
                <label for="nama_tambah">Nama Lengkap</label>
                <input
                    type="text"
                    id="nama_tambah"
                    name="nama"
                    maxlength="100"
                    placeholder="Masukkan nama lengkap"
                    required>
            </div>

            <div class="form-group">
                <label for="email_tambah">Email</label>
                <input
                    type="email"
                    id="email_tambah"
                    name="email"
                    maxlength="150"
                    placeholder="contoh@email.com"
                    required>
            </div>

            <div class="form-group">
                <label for="password_tambah">Password</label>
                <input
                    type="password"
                    id="password_tambah"
                    name="password"
                    minlength="6"
                    placeholder="Minimal 6 karakter"
                    required>
            </div>

            <div class="form-group">
                <label for="role_tambah">Role</label>
                <select id="role_tambah" name="role" required>
                    <option value="">Pilih Role</option>
                    <option value="admin">Admin / Pemilik</option>
                    <option value="kasir">Kasir</option>
                    <option value="karyawan">Staff / Karyawan</option>
                    <option value="pelanggan">Pelanggan</option>
                </select>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light"
                        onclick="closeModal('modalTambah')">
                    Batal
                </button>

                <button type="submit" name="tambah_pengguna"
                        class="btn btn-primary">
                    Simpan
                </button>
            </div>

        </form>

    </div>
</div>

<!-- MODAL EDIT -->
<div class="modal" id="modalEdit" onclick="closeModalOutside(event, 'modalEdit')">

    <div class="modal-box">

        <div class="modal-header">
            <h2>Edit Pengguna</h2>
            <button type="button" class="modal-close" onclick="closeModal('modalEdit')">×</button>
        </div>

        <form method="POST">

            <input type="hidden" name="id_user" id="edit_id_user">

            <div class="form-group">
                <label for="edit_nama">Nama Lengkap</label>
                <input
                    type="text"
                    id="edit_nama"
                    name="nama"
                    maxlength="100"
                    required>
            </div>

            <div class="form-group">
                <label for="edit_email">Email</label>
                <input
                    type="email"
                    id="edit_email"
                    name="email"
                    maxlength="150"
                    required>
            </div>

            <div class="form-group">
                <label for="edit_password">Password Baru</label>
                <input
                    type="password"
                    id="edit_password"
                    name="password"
                    minlength="6"
                    placeholder="Kosongkan jika tidak ingin mengubah">
                <span class="form-help">
                    Kosongkan jika password tetap.
                </span>
            </div>

            <div class="form-group">
                <label for="edit_role">Role</label>
                <select id="edit_role" name="role" required>
                    <option value="admin">Admin / Pemilik</option>
                    <option value="kasir">Kasir</option>
                    <option value="karyawan">Staff / Karyawan</option>
                    <option value="pelanggan">Pelanggan</option>
                </select>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light"
                        onclick="closeModal('modalEdit')">
                    Batal
                </button>

                <button type="submit" name="edit_pengguna"
                        class="btn btn-primary">
                    Simpan Perubahan
                </button>
            </div>

        </form>

    </div>
</div>

<script>
function openMenu() {
    document.getElementById('drawer').classList.add('show');
    document.getElementById('menuOverlay').classList.add('show');
    document.body.classList.add('menu-open');

    document.getElementById('menuToggle')
        .setAttribute('aria-expanded', 'true');
}

function closeMenu() {
    document.getElementById('drawer').classList.remove('show');
    document.getElementById('menuOverlay').classList.remove('show');
    document.body.classList.remove('menu-open');

    document.getElementById('menuToggle')
        .setAttribute('aria-expanded', 'false');
}

function toggleMenu() {
    const drawer = document.getElementById('drawer');

    if (drawer.classList.contains('show')) {
        closeMenu();
    } else {
        openMenu();
    }
}

function openTambah() {
    document.getElementById('modalTambah').classList.add('show');

    setTimeout(function() {
        document.getElementById('nama_tambah').focus();
    }, 100);
}

function openEdit(id, nama, email, role) {
    document.getElementById('edit_id_user').value = id;
    document.getElementById('edit_nama').value = nama;
    document.getElementById('edit_email').value = email;
    document.getElementById('edit_password').value = '';
    document.getElementById('edit_role').value = role;

    document.getElementById('modalEdit').classList.add('show');

    setTimeout(function() {
        document.getElementById('edit_nama').focus();
    }, 100);
}

function closeModal(id) {
    document.getElementById(id).classList.remove('show');
}

function closeModalOutside(event, id) {
    if (event.target.id === id) {
        closeModal(id);
    }
}

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeMenu();
        closeModal('modalTambah');
        closeModal('modalEdit');
    }
});
</script>

</body>
</html>
