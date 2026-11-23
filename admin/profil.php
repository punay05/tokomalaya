<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['id_user'])) {
    header('Location: ../auth/login.php');
    exit;
}

$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['admin', 'pemilik'], true)) {
    http_response_code(403);
    exit('Akses ditolak.');
}

$id_user = (int) $_SESSION['id_user'];
$pesan = '';
$error = '';

function e($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

/* Ambil profil pengguna yang sedang login */
$stmt = mysqli_prepare(
    $conn,
    "SELECT id_user, nama, username, role
     FROM users
     WHERE id_user = ?
     LIMIT 1"
);

mysqli_stmt_bind_param($stmt, 'i', $id_user);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$user) {
    session_destroy();
    header('Location: ../auth/login.php');
    exit;
}

/* Proses pembaruan profil */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_baru = trim($_POST['nama'] ?? '');
    $username_baru = trim($_POST['username'] ?? '');

    if ($nama_baru === '' || $username_baru === '') {
        $error = 'Nama dan username wajib diisi.';
    } elseif (mb_strlen($nama_baru) > 100 || mb_strlen($username_baru) > 100) {
        $error = 'Nama atau username terlalu panjang.';
    } else {
        /* Pastikan username tidak dipakai pengguna lain */
        $stmt = mysqli_prepare(
            $conn,
            "SELECT id_user FROM users
             WHERE username = ? AND id_user != ?
             LIMIT 1"
        );
        mysqli_stmt_bind_param($stmt, 'si', $username_baru, $id_user);
        mysqli_stmt_execute($stmt);
        $cek = mysqli_stmt_get_result($stmt);
        $duplikat = mysqli_num_rows($cek) > 0;
        mysqli_stmt_close($stmt);

        if ($duplikat) {
            $error = 'Username sudah digunakan akun lain.';
        } else {
            $stmt = mysqli_prepare(
                $conn,
                "UPDATE users SET nama = ?, username = ?
                 WHERE id_user = ?"
            );
            mysqli_stmt_bind_param(
                $stmt,
                'ssi',
                $nama_baru,
                $username_baru,
                $id_user
            );

            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['nama'] = $nama_baru;
                $pesan = 'Profil berhasil diperbarui.';
                $user['nama'] = $nama_baru;
                $user['username'] = $username_baru;
            } else {
                $error = 'Profil gagal diperbarui. Silakan coba lagi.';
            }

            mysqli_stmt_close($stmt);
        }
    }
}

$nama = $user['nama'] ?? ($_SESSION['nama'] ?? 'Pengguna');
$role = $user['role'] ?? $role;
$inisial = strtoupper(mb_substr($nama, 0, 1));
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Profil - Toko Malaya</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,sans-serif;background:#f5f7fb;color:#172033}
body.menu-open{overflow:hidden}
a{text-decoration:none}
.overlay{position:fixed;inset:0;background:#0f172a73;z-index:1000;opacity:0;visibility:hidden;transition:.25s}
.overlay.show{opacity:1;visibility:visible}
.drawer{position:fixed;top:0;left:0;width:315px;max-width:88vw;height:100vh;background:#fff;z-index:1100;transform:translateX(-100%);transition:.25s;display:flex;flex-direction:column;box-shadow:8px 0 30px #0f172a1f}
.drawer.show{transform:translateX(0)}
.drawer-header{padding:18px 20px;min-height:82px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #e5e7eb}
.drawer-brand,.brand,.top-user{display:flex;align-items:center;gap:11px}
.logo{width:44px;height:44px;object-fit:contain;border-radius:9px}
.brand-text,.user-info{display:flex;flex-direction:column;gap:4px}
.brand-text strong{font-size:17px}
.brand-text small,.user-info small{color:#64748b;font-size:12px}
.close,.hamburger{border:0;background:#f1f5f9;border-radius:9px;width:40px;height:40px;font-size:22px;cursor:pointer}
.drawer-user{margin:18px;padding:13px;background:#f8fafc;border:1px solid #dbe4ee;border-radius:13px;display:flex;align-items:center;gap:12px}
.avatar{width:44px;height:44px;border-radius:50%;background:#e0efff;color:#1265a7;display:grid;place-items:center;font-weight:bold;flex-shrink:0}
.drawer-menu{padding:0 14px 20px;overflow-y:auto;flex:1}
.menu-label{font-size:11px;font-weight:bold;color:#94a3b8;letter-spacing:.5px;padding:12px}
.menu-link,.logout-link{display:flex;align-items:center;gap:12px;padding:12px;border-radius:9px;margin-bottom:4px;color:#475569;font-size:14px;font-weight:700}
.menu-link:hover{background:#f1f5f9;color:#1677d2}
.menu-link.active{background:#e8f3ff;color:#1677d2;border-left:3px solid #1677d2}
.menu-icon{width:24px;text-align:center;font-size:18px}
.drawer-footer{border-top:1px solid #e5e7eb;padding:14px}
.logout-link{color:#ef4444}
.logout-link:hover{background:#fef2f2}
.topbar{height:72px;background:#fff;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;justify-content:space-between;padding:0 24px;position:sticky;top:0;z-index:900}
.brand{color:#111827}
.top-user{border:1px solid #e2e8f0;padding:6px 10px;border-radius:12px}
.user-info{text-align:right}
.user-info strong{font-size:14px}
.main{max-width:1000px;margin:auto;padding:30px 24px 50px}
.page-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;gap:15px}
h1{font-size:28px;color:#102a43;margin-bottom:7px}
.subtitle{font-size:14px;color:#64748b}
.page-icon{width:52px;height:52px;border-radius:14px;background:#e8f3ff;display:grid;place-items:center;font-size:25px}
.profile-layout{display:grid;grid-template-columns:280px minmax(0,1fr);gap:20px;align-items:start}
.panel{background:#fff;border:1px solid #e3e9f1;border-radius:13px;overflow:hidden;margin-bottom:20px}
.panel-head{padding:18px 20px;border-bottom:1px solid #edf1f5}
.panel-head h2{font-size:17px}
.identity{padding:28px 20px;text-align:center}
.big-avatar{width:88px;height:88px;border-radius:50%;margin:0 auto 15px;background:#e0efff;color:#1265a7;display:grid;place-items:center;font-size:34px;font-weight:bold}
.identity h2{font-size:20px;margin-bottom:7px;overflow-wrap:anywhere}
.identity p{font-size:13px;color:#64748b;margin-bottom:12px;overflow-wrap:anywhere}
.role-pill{display:inline-block;padding:6px 12px;background:#e8f3ff;color:#1677d2;border-radius:20px;font-size:12px;font-weight:bold}
.form-body{padding:22px}
.form-group{margin-bottom:18px}
.form-group label{display:block;font-size:13px;font-weight:bold;color:#475569;margin-bottom:8px}
.form-group input{width:100%;padding:12px 13px;border:1px solid #dbe4ee;border-radius:9px;font-size:14px;outline:none}
.form-group input:focus{border-color:#1677d2;box-shadow:0 0 0 3px #1677d21a}
.form-group small{display:block;margin-top:7px;color:#94a3b8;font-size:12px}
.readonly{background:#f8fafc;color:#64748b}
.btn{display:inline-block;border:0;border-radius:9px;padding:12px 18px;background:#1677d2;color:#fff;font-size:14px;font-weight:bold;cursor:pointer}
.btn:hover{background:#1265b3}
.alert{padding:13px 15px;border-radius:9px;font-size:13px;margin-bottom:18px}
.alert.success{background:#ecfdf5;color:#047857;border:1px solid #a7f3d0}
.alert.error{background:#fef2f2;color:#b91c1c;border:1px solid #fecaca}
.info-list{padding:5px 20px 18px}
.info-row{display:flex;justify-content:space-between;gap:12px;padding:13px 0;border-bottom:1px solid #edf1f5;font-size:13px}
.info-row:last-child{border-bottom:0}
.info-row span{color:#64748b}
.info-row strong{text-align:right;overflow-wrap:anywhere}
@media(max-width:700px){.profile-layout{grid-template-columns:1fr}.main{padding:22px 14px 35px}.topbar{padding:0 14px}.brand-text,.user-info{display:none}h1{font-size:23px}.top-user{border:0}}
</style>
</head>
<body>

<div class="overlay" id="overlay" onclick="closeMenu()"></div>

<aside class="drawer" id="drawer">
    <div class="drawer-header">
        <div class="drawer-brand">
            <img class="logo" src="../assets/SembakoKu (1).png" alt="Logo">
            <div class="brand-text">
                <strong>Toko Malaya</strong>
                <small>Panel Admin / Pemilik</small>
            </div>
        </div>
        <button class="close" onclick="closeMenu()" aria-label="Tutup menu">&times;</button>
    </div>

    <div class="drawer-user">
        <div class="avatar"><?= e($inisial) ?></div>
        <div class="user-info">
            <strong><?= e($nama) ?></strong>
            <small><?= e(ucfirst($role)) ?></small>
        </div>
    </div>

    <nav class="drawer-menu">

        <div class="menu-label">MENU UTAMA</div>

        <a href="dashboard.php" class="menu-link ">
            <span class="menu-icon">🏠</span>
            Dashboard
        </a>

        <a href="produk.php" class="menu-link">
            <span class="menu-icon">📦</span>
            Produk dan Kategori
        </a>

        <a href="pengguna.php" class="menu-link">
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

        <a href="profil.php" class="menu-link active">
            <span class="menu-icon">👤</span>
            Profil
        </a>

    </nav>

    <div class="drawer-footer">
        <a href="../auth/logout.php" class="logout-link" onclick="return confirm('Yakin ingin keluar?')">
            <span class="menu-icon">🚪</span>Keluar
        </a>
    </div>
</aside>

<header class="topbar">
    <div style="display:flex;align-items:center;gap:13px">
        <button class="hamburger" onclick="toggleMenu()" aria-label="Buka menu">☰</button>
        <a href="dashboard.php" class="brand">
            <img class="logo" src="../assets/SembakoKu (1).png" alt="Logo">
            <div class="brand-text"><strong>Toko Malaya</strong><small>Panel Admin</small></div>
        </a>
    </div>
    <div class="top-user">
        <div class="user-info"><strong><?= e($nama) ?></strong><small><?= e(ucfirst($role)) ?></small></div>
        <div class="avatar"><?= e($inisial) ?></div>
    </div>
</header>

<main class="main">
    <div class="page-head">
        <div>
            <h1>Profil Saya</h1>
            <p class="subtitle">Lihat dan perbarui informasi akun Toko Malaya.</p>
        </div>
        <div class="page-icon">👤</div>
    </div>

    <div class="profile-layout">
        <section class="panel">
            <div class="identity">
                <div class="big-avatar"><?= e($inisial) ?></div>
                <h2><?= e($nama) ?></h2>
                <p><?= e($user['username']) ?></p>
                <span class="role-pill"><?= e(ucfirst($role)) ?></span>
            </div>
            <div class="panel-head"><h2>Informasi Akun</h2></div>
            <div class="info-list">
                <div class="info-row"><span>ID Pengguna</span><strong><?= e($user['id_user']) ?></strong></div>
                <div class="info-row"><span>Nama</span><strong><?= e($nama) ?></strong></div>
                <div class="info-row"><span>Username</span><strong><?= e($user['username']) ?></strong></div>
                <div class="info-row"><span>Role</span><strong><?= e(ucfirst($role)) ?></strong></div>
            </div>
        </section>

        <section class="panel">
            <div class="panel-head"><h2>Edit Profil</h2></div>
            <div class="form-body">
                <?php if ($pesan !== ''): ?>
                    <div class="alert success"><?= e($pesan) ?></div>
                <?php endif; ?>

                <?php if ($error !== ''): ?>
                    <div class="alert error"><?= e($error) ?></div>
                <?php endif; ?>

                <form method="post" action="profil.php">
                    <div class="form-group">
                        <label for="nama">Nama lengkap</label>
                        <input type="text" id="nama" name="nama" maxlength="100" required value="<?= e($user['nama']) ?>">
                    </div>

                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" maxlength="100" required value="<?= e($user['username']) ?>">
                        <small>Username harus unik dan digunakan untuk login.</small>
                    </div>

                    <div class="form-group">
                        <label for="role">Role akun</label>
                        <input type="text" id="role" class="readonly" readonly value="<?= e(ucfirst($role)) ?>">
                        <small>Role akun tidak dapat diubah dari halaman profil.</small>
                    </div>

                    <button class="btn" type="submit">Simpan Perubahan</button>
                </form>
            </div>
        </section>
    </div>
</main>

<script>
function openMenu() {
    document.getElementById('drawer').classList.add('show');
    document.getElementById('overlay').classList.add('show');
    document.body.classList.add('menu-open');
}
function closeMenu() {
    document.getElementById('drawer').classList.remove('show');
    document.getElementById('overlay').classList.remove('show');
    document.body.classList.remove('menu-open');
}
function toggleMenu() {
    document.getElementById('drawer').classList.contains('show') ? closeMenu() : openMenu();
}
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') closeMenu();
});
</script>
</body>
</html>