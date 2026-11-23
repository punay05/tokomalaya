
<?php
session_start();
require_once "../config/database.php";

// ========================================
// CEK LOGIN
// ========================================
if (
    !isset($_SESSION['id_user']) ||
    $_SESSION['role'] !== 'kasir'
) {
    header("Location: ../auth/login.php");
    exit;
}

$id_user = (int) $_SESSION['id_user'];
$nama = $_SESSION['nama'] ?? 'Kasir';

// ========================================
// FORMAT RUPIAH
// ========================================
function rupiah($angka)
{
    return "Rp " . number_format((float)$angka, 0, ',', '.');
}

// ========================================
// 1. PENJUALAN LANGSUNG KESELURUHAN
// ========================================
$stmt = mysqli_prepare($conn, "
    SELECT
        COUNT(*) AS jumlah_transaksi,
        COALESCE(SUM(total_penjualan), 0) AS total_penjualan
    FROM penjualan
    WHERE id_user = ?
");

mysqli_stmt_bind_param($stmt, "i", $id_user);
mysqli_stmt_execute($stmt);
$data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

$jumlah_penjualan_langsung = (int)($data['jumlah_transaksi'] ?? 0);
$total_penjualan_langsung = (float)($data['total_penjualan'] ?? 0);

// ========================================
// 2. PENJUALAN LANGSUNG HARI INI
// ========================================
$stmt = mysqli_prepare($conn, "
    SELECT
        COUNT(*) AS jumlah,
        COALESCE(SUM(total_penjualan), 0) AS total
    FROM penjualan
    WHERE id_user = ?
    AND DATE(waktu_penjualan) = CURDATE()
");

mysqli_stmt_bind_param($stmt, "i", $id_user);
mysqli_stmt_execute($stmt);
$data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

$transaksi_langsung_hari_ini = (int)($data['jumlah'] ?? 0);
$total_penjualan_hari_ini = (float)($data['total'] ?? 0);

// ========================================
// 3. PESANAN ONLINE
// ========================================
$total_pesanan_online = 0;
$jumlah_pesanan_online = 0;
$pesanan_online_hari_ini = 0;
$total_pesanan_online_hari_ini = 0;

$pesanan_baru = 0;
$pesanan_diproses = 0;
$pesanan_dikirim = 0;
$total_pesanan_online_semua = 0;

$cek_tabel = mysqli_query($conn, "SHOW TABLES LIKE 'pesanan'");

if ($cek_tabel && mysqli_num_rows($cek_tabel) > 0) {

    $sql = "
        SELECT
            COUNT(*) AS jumlah,
            COALESCE(SUM(total_harga), 0) AS total
        FROM pesanan
        WHERE status = 'selesai'
        OR (
            UPPER(COALESCE(metode_pembayaran, '')) = 'QRIS'
            AND LOWER(COALESCE(status_pembayaran, ''))
                IN ('terverifikasi', 'lunas', 'dibayar')
        )
    ";

    $result = mysqli_query($conn, $sql);

    if ($result) {
        $data = mysqli_fetch_assoc($result);
        $jumlah_pesanan_online = (int)($data['jumlah'] ?? 0);
        $total_pesanan_online = (float)($data['total'] ?? 0);
    }

    // Pesanan online hari ini
    $sql = "
        SELECT
            COUNT(*) AS jumlah,
            COALESCE(SUM(total_harga), 0) AS total
        FROM pesanan
        WHERE (
            status = 'selesai'
            OR (
                UPPER(COALESCE(metode_pembayaran, '')) = 'QRIS'
                AND LOWER(COALESCE(status_pembayaran, ''))
                    IN ('terverifikasi', 'lunas', 'dibayar')
            )
        )
        AND DATE(tanggal_pesanan) = CURDATE()
    ";

    $result = mysqli_query($conn, $sql);

    if ($result) {
        $data = mysqli_fetch_assoc($result);
        $pesanan_online_hari_ini = (int)($data['jumlah'] ?? 0);
        $total_pesanan_online_hari_ini = (float)($data['total'] ?? 0);
    }

    // Jumlah pesanan berdasarkan status
    $result = mysqli_query($conn, "
        SELECT
            SUM(CASE WHEN status = 'menunggu' THEN 1 ELSE 0 END) AS baru,
            SUM(CASE WHEN status = 'diproses' THEN 1 ELSE 0 END) AS diproses,
            SUM(CASE WHEN status = 'dikirim' THEN 1 ELSE 0 END) AS dikirim,
            COUNT(*) AS semua
        FROM pesanan
    ");

    if ($result) {
        $data = mysqli_fetch_assoc($result);

        $pesanan_baru = (int)($data['baru'] ?? 0);
        $pesanan_diproses = (int)($data['diproses'] ?? 0);
        $pesanan_dikirim = (int)($data['dikirim'] ?? 0);
        $total_pesanan_online_semua = (int)($data['semua'] ?? 0);
    }
}

// ========================================
// 4. RINGKASAN QRIS
// ========================================
$qris_menunggu_verifikasi = 0;
$total_qris_menunggu_verifikasi = 0;

if ($cek_tabel && mysqli_num_rows($cek_tabel) > 0) {

    $cek_kolom = mysqli_query(
        $conn,
        "SHOW COLUMNS FROM pesanan LIKE 'status_pembayaran'"
    );

    $kolom_pembayaran_ada =
        $cek_kolom && mysqli_num_rows($cek_kolom) > 0;

    $cek_metode = mysqli_query(
        $conn,
        "SHOW COLUMNS FROM pesanan LIKE 'metode_pembayaran'"
    );

    $kolom_metode_ada =
        $cek_metode && mysqli_num_rows($cek_metode) > 0;

    if ($kolom_pembayaran_ada && $kolom_metode_ada) {

        $result = mysqli_query($conn, "
            SELECT
                COUNT(*) AS jumlah,
                COALESCE(SUM(total_harga), 0) AS total
            FROM pesanan
            WHERE UPPER(metode_pembayaran) = 'QRIS'
            AND LOWER(status_pembayaran)
                IN ('menunggu_verifikasi', 'menunggu', 'pending')
        ");

        if ($result) {
            $data = mysqli_fetch_assoc($result);
            $qris_menunggu_verifikasi = (int)($data['jumlah'] ?? 0);
            $total_qris_menunggu_verifikasi = (float)($data['total'] ?? 0);
        }
    }
}

// ========================================
// 5. TOTAL PENGHASILAN KESELURUHAN
// ========================================
$total_penghasilan =
    $total_penjualan_langsung +
    $total_pesanan_online;

// ========================================
// 6. TOTAL PENGHASILAN HARI INI
// ========================================
$total_penghasilan_hari_ini =
    $total_penjualan_hari_ini +
    $total_pesanan_online_hari_ini;

// ========================================
// 7. TOTAL TRANSAKSI
// ========================================
$total_semua_transaksi =
    $jumlah_penjualan_langsung +
    $jumlah_pesanan_online;

$total_transaksi_hari_ini =
    $transaksi_langsung_hari_ini +
    $pesanan_online_hari_ini;

$rata_rata = $total_semua_transaksi > 0
    ? $total_penghasilan / $total_semua_transaksi
    : 0;

$total_pesanan_aktif =
    $pesanan_baru +
    $pesanan_diproses +
    $pesanan_dikirim;

?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Dashboard Kasir - Toko Malaya</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #f5f7fb;
    color: #1f2937;
}

a {
    text-decoration: none;
}

/* TOPBAR */
.topbar {
    height: 68px;
    position: sticky;
    top: 0;
    z-index: 900;
    background: #fff;
    border-bottom: 1px solid #e5e7eb;
}

.topbar-inner {
    height: 100%;
    padding: 0 28px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.topbar-left,
.topbar-brand,
.topbar-right,
.topbar-user {
    display: flex;
    align-items: center;
}

.topbar-left {
    gap: 15px;
}

.menu-button {
    width: 40px;
    height: 40px;
    border: none;
    border-radius: 9px;
    background: #eaf3ff;
    color: #1677d2;
    font-size: 22px;
    cursor: pointer;
}

.topbar-brand {
    gap: 10px;
}

.topbar-logo {
    width: 39px;
    height: 39px;
    background: #1677d2;
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.topbar-logo img {
    width: 33px;
    height: 33px;
    object-fit: contain;
}

.brand-text strong {
    display: block;
    color: #153b63;
    font-size: 17px;
}

.brand-text span {
    display: block;
    color: #94a3b8;
    font-size: 11px;
    margin-top: 3px;
}

.topbar-user {
    gap: 9px;
    background: #f8fafc;
    padding: 6px 12px 6px 6px;
    border: 1px solid #e5e7eb;
    border-radius: 30px;
}

.avatar {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: #1677d2;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}

.user-info strong {
    display: block;
    font-size: 12px;
}

.user-info span {
    display: block;
    font-size: 10px;
    color: #64748b;
    margin-top: 2px;
}

/* MAIN */
.content {
    width: 100%;
    max-width: 1250px;
    margin: auto;
    padding: 30px 25px 45px;
}

/* PAGE HEADER */
.page-header {
    margin-bottom: 25px;
}

.page-header h1 {
    font-size: 25px;
    margin: 0 0 7px;
    color: #1e293b;
}

.page-header p {
    margin: 0;
    font-size: 13px;
    color: #64748b;
}

/* WELCOME */
.welcome {
    padding: 25px 28px;
    border-radius: 14px;
    background: linear-gradient(120deg, #176fc4, #318de2);
    color: white;
    margin-bottom: 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
}

.welcome h2 {
    font-size: 20px;
    margin: 0 0 8px;
}

.welcome p {
    font-size: 13px;
    line-height: 1.6;
    margin: 0;
    opacity: .9;
}

.welcome-icon {
    font-size: 48px;
    opacity: .85;
}

/* SECTION TITLE */
.section-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin: 27px 0 14px;
    gap: 10px;
}

.section-title h2 {
    margin: 0;
    font-size: 17px;
    color: #1e293b;
}

.section-title span {
    font-size: 11px;
    color: #94a3b8;
}

/* SUMMARY */
.summary-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 17px;
}

.summary-card {
    background: white;
    border: 1px solid #edf0f4;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 7px rgba(15,23,42,.035);
}

.summary-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 17px;
}

.summary-label {
    font-size: 12px;
    color: #64748b;
}

.summary-icon {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    background: #eaf3ff;
    font-size: 17px;
}

.summary-value {
    font-size: 23px;
    font-weight: bold;
    color: #1e293b;
    overflow-wrap: anywhere;
}

.summary-note {
    margin-top: 8px;
    font-size: 11px;
    color: #94a3b8;
}

/* TODAY REVENUE */
.today-card {
    background: white;
    border: 1px solid #e5eaf0;
    border-radius: 13px;
    padding: 22px;
    margin-top: 18px;
    box-shadow: 0 2px 7px rgba(15,23,42,.035);
}

.today-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    margin-bottom: 13px;
}

.today-card-header span {
    font-size: 13px;
    color: #64748b;
}

.today-badge {
    font-size: 10px;
    color: #15803d;
    background: #dcfce7;
    border-radius: 20px;
    padding: 6px 10px;
    font-weight: bold;
}

.today-total {
    color: #1677d2;
    font-size: 29px;
    font-weight: bold;
    margin-bottom: 17px;
}

.today-details {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
}

.today-detail {
    padding: 13px;
    border-radius: 9px;
    background: #f8fafc;
}

.today-detail span {
    display: block;
    color: #64748b;
    font-size: 11px;
    margin-bottom: 6px;
}

.today-detail strong {
    font-size: 14px;
    color: #334155;
}

/* ONLINE ORDER */
.order-panel {
    background: white;
    border: 1px solid #edf0f4;
    border-radius: 12px;
    padding: 21px;
    box-shadow: 0 2px 7px rgba(15,23,42,.035);
}

.order-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
}

.order-item {
    padding: 15px;
    border-radius: 9px;
    background: #f8fafc;
}

.order-item span {
    display: block;
    color: #64748b;
    font-size: 11px;
    margin-bottom: 8px;
}

.order-item strong {
    font-size: 22px;
    color: #1e293b;
}

.order-alert {
    margin-top: 15px;
    padding: 12px 14px;
    border-radius: 8px;
    background: #eff6ff;
    color: #1d4ed8;
    font-size: 12px;
}

.order-alert a {
    color: #1d4ed8;
    font-weight: bold;
    margin-left: 5px;
}

/* MENU */
.menu-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
}

.menu-card {
    background: white;
    border: 1px solid #edf0f4;
    border-radius: 11px;
    padding: 18px;
    display: flex;
    align-items: center;
    gap: 13px;
    color: #334155;
    transition: .2s;
    box-shadow: 0 2px 7px rgba(15,23,42,.025);
}

.menu-card:hover {
    border-color: #a9d0fa;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(22,119,210,.08);
}

.menu-icon {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    background: #eaf3ff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 19px;
    flex-shrink: 0;
}

.menu-text strong {
    display: block;
    font-size: 12px;
}

.menu-text span {
    display: block;
    margin-top: 5px;
    color: #94a3b8;
    font-size: 10px;
}

/* FOOTER INFO */
.info-note {
    margin-top: 22px;
    padding: 14px 16px;
    border-radius: 9px;
    background: #f1f5f9;
    color: #64748b;
    font-size: 11px;
    line-height: 1.6;
}

/* RESPONSIVE */
@media (max-width: 900px) {
    .summary-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .menu-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .order-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 600px) {
    .topbar {
        height: 62px;
    }

    .topbar-inner {
        padding: 0 14px;
    }

    .user-info {
        display: none;
    }

    .content {
        padding: 22px 15px 35px;
    }

    .page-header h1 {
        font-size: 22px;
    }

    .welcome {
        padding: 21px;
    }

    .welcome h2 {
        font-size: 17px;
    }

    .welcome-icon {
        font-size: 35px;
    }

    .summary-grid {
        grid-template-columns: 1fr;
        gap: 11px;
    }

    .summary-card {
        padding: 17px;
    }

    .summary-value {
        font-size: 21px;
    }

    .today-total {
        font-size: 24px;
    }

    .today-details {
        grid-template-columns: 1fr;
    }

    .menu-grid {
        grid-template-columns: 1fr;
        gap: 10px;
    }

    .order-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .order-item {
        padding: 13px;
    }

    .section-title {
        margin-top: 23px;
    }
}

@media (max-width: 370px) {
    .brand-text strong {
        font-size: 14px;
    }

    .order-grid {
        grid-template-columns: 1fr;
    }
}
</style>
</head>

<body>

<?php include "sidebar.php"; ?>

<header class="topbar">
    <div class="topbar-inner">

        <div class="topbar-left">
            <button
                type="button"
                class="menu-button"
                onclick="toggleSidebar()"
                aria-label="Buka menu"
            >☰</button>

            <a href="dashboard.php" class="topbar-brand">
                <div class="topbar-logo">
                    <img src="../assets/SembakoKu (1).png" alt="Logo">
                </div>

                <div class="brand-text">
                    <strong>Toko Malaya</strong>
                    <span>Sistem Kasir</span>
                </div>
            </a>
        </div>

        <div class="topbar-right">
            <div class="topbar-user">
                <div class="avatar">
                    <?= htmlspecialchars(strtoupper(substr($nama, 0, 1))); ?>
                </div>

                <div class="user-info">
                    <strong><?= htmlspecialchars($nama); ?></strong>
                    <span>Kasir</span>
                </div>
            </div>
        </div>

    </div>
</header>

<main class="content">

    <!-- HEADER -->
    <div class="page-header">
        <h1>Dashboard Kasir</h1>
        <p>Ringkasan aktivitas penjualan dan pesanan Toko Malaya.</p>
    </div>

    <!-- WELCOME -->
    <div class="welcome">
        <div>
            <h2>Selamat datang, <?= htmlspecialchars($nama); ?> 👋</h2>
            <p>
                Semoga aktivitas penjualan hari ini berjalan lancar.
                Pantau transaksi dan kelola pesanan melalui dashboard ini.
            </p>
        </div>

        <div class="welcome-icon">🛍️</div>
    </div>

    <!-- RINGKASAN KESELURUHAN -->
    <div class="section-title">
        <h2>Ringkasan Penjualan</h2>
        <span>Seluruh periode</span>
    </div>

    <div class="summary-grid">

        <div class="summary-card">
            <div class="summary-top">
                <span class="summary-label">Total Penghasilan</span>
                <div class="summary-icon">💰</div>
            </div>

            <div class="summary-value">
                <?= rupiah($total_penghasilan); ?>
            </div>

            <div class="summary-note">
                Penjualan langsung dan pesanan online
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-top">
                <span class="summary-label">Total Transaksi</span>
                <div class="summary-icon">🧾</div>
            </div>

            <div class="summary-value">
                <?= number_format($total_semua_transaksi); ?>
            </div>

            <div class="summary-note">
                Seluruh transaksi yang tercatat
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-top">
                <span class="summary-label">Rata-rata Transaksi</span>
                <div class="summary-icon">📊</div>
            </div>

            <div class="summary-value">
                <?= rupiah($rata_rata); ?>
            </div>

            <div class="summary-note">
                Rata-rata nilai setiap transaksi
            </div>
        </div>

    </div>

    <!-- PENJUALAN HARI INI -->
    <div class="section-title">
        <h2>Penjualan Hari Ini</h2>
        <span><?= date('d-m-Y'); ?></span>
    </div>

    <div class="today-card">
        <div class="today-card-header">
            <span>Total penghasilan hari ini</span>
            <div class="today-badge">HARI INI</div>
        </div>

        <div class="today-total">
            <?= rupiah($total_penghasilan_hari_ini); ?>
        </div>

        <div class="today-details">
            <div class="today-detail">
                <span>Penjualan Langsung</span>
                <strong><?= rupiah($total_penjualan_hari_ini); ?></strong>
                <div class="summary-note">
                    <?= $transaksi_langsung_hari_ini; ?> transaksi
                </div>
            </div>

            <div class="today-detail">
                <span>Pesanan Online</span>
                <strong><?= rupiah($total_pesanan_online_hari_ini); ?></strong>
                <div class="summary-note">
                    <?= $pesanan_online_hari_ini; ?> pesanan
                </div>
            </div>
        </div>
    </div>

    <!-- PESANAN ONLINE -->
    <div class="section-title">
        <h2>Pesanan Online</h2>
        <span><a href="pesanan.php" style="color:#1677d2;">Lihat semua →</a></span>
    </div>

    <div class="order-panel">
        <div class="order-grid">

            <div class="order-item">
                <span>🟡 Menunggu</span>
                <strong><?= $pesanan_baru; ?></strong>
            </div>

            <div class="order-item">
                <span>🔵 Diproses</span>
                <strong><?= $pesanan_diproses; ?></strong>
            </div>

            <div class="order-item">
                <span>🟣 Dikirim</span>
                <strong><?= $pesanan_dikirim; ?></strong>
            </div>

            <div class="order-item">
                <span>🛒 Total Pesanan</span>
                <strong><?= $total_pesanan_online_semua; ?></strong>
            </div>

        </div>

        <?php if ($pesanan_baru > 0): ?>
            <div class="order-alert">
                Ada <?= $pesanan_baru; ?> pesanan baru yang menunggu diproses.
                <a href="pesanan.php?status=menunggu">Periksa pesanan →</a>
            </div>
        <?php endif; ?>

        <?php if ($qris_menunggu_verifikasi > 0): ?>
            <div class="order-alert">
                Terdapat <?= $qris_menunggu_verifikasi; ?> pembayaran QRIS
                senilai <?= rupiah($total_qris_menunggu_verifikasi); ?>
                yang menunggu verifikasi.
            </div>
        <?php endif; ?>
    </div>

    <!-- MENU KASIR -->
    <div class="section-title">
        <h2>Menu Kasir</h2>
        <span>Akses cepat</span>
    </div>

    <div class="menu-grid">

        <a href="penjualan.php" class="menu-card">
            <div class="menu-icon">🧾</div>
            <div class="menu-text">
                <strong>Transaksi Penjualan</strong>
                <span>Buat transaksi baru</span>
            </div>
        </a>

        <a href="riwayat_penjualan.php" class="menu-card">
            <div class="menu-icon">📋</div>
            <div class="menu-text">
                <strong>Riwayat Transaksi</strong>
                <span>Lihat transaksi sebelumnya</span>
            </div>
        </a>

        <a href="laporan.php" class="menu-card">
            <div class="menu-icon">📊</div>
            <div class="menu-text">
                <strong>Laporan Penjualan</strong>
                <span>Lihat laporan penjualan</span>
            </div>
        </a>

        <a href="pesanan.php" class="menu-card">
            <div class="menu-icon">🛒</div>
            <div class="menu-text">
                <strong>Pesanan Online</strong>
                <span>Kelola pesanan pelanggan</span>
            </div>
        </a>

        <a href="pesanan.php?status=selesai" class="menu-card">
            <div class="menu-icon">✅</div>
            <div class="menu-text">
                <strong>Pesanan Selesai</strong>
                <span>Lihat pesanan yang selesai</span>
            </div>
        </a>

    </div>

    <div class="info-note">
        <strong>Informasi:</strong>
        Total penghasilan merupakan gabungan penjualan langsung dan
        pesanan online yang sudah memenuhi ketentuan pencatatan.
        Pembayaran QRIS yang masih menunggu verifikasi belum dihitung
        sebagai penghasilan.
    </div>

</main>

</body>
</html>
