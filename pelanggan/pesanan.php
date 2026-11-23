
<?php
session_start();
require_once "../config/database.php";

if (!isset($_SESSION['id_user']) || ($_SESSION['role'] ?? '') !== 'pelanggan') {
    header("Location: ../auth/login.php");
    exit;
}

$id_user = (int) $_SESSION['id_user'];
$nama = $_SESSION['nama'] ?? 'Pelanggan';

if (empty($_SESSION['csrf_pesanan_selesai'])) {
    $_SESSION['csrf_pesanan_selesai'] = bin2hex(random_bytes(32));
}

function rupiah($angka) {
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

function status_class($status) {
    $classes = [
        'menunggu' => 'menunggu',
        'diproses' => 'diproses',
        'dikirim' => 'dikirim',
        'selesai' => 'selesai',
        'dibatalkan' => 'dibatalkan'
    ];

    return $classes[$status] ?? 'menunggu';
}

function status_label($status) {
    return ucfirst($status);
}

// TANDAI SELESAI
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'tandai_selesai') {

    $csrf = (string)($_POST['csrf_token'] ?? '');
    $id_pesanan_post = filter_input(INPUT_POST, 'id_pesanan', FILTER_VALIDATE_INT);

    if (
        !hash_equals($_SESSION['csrf_pesanan_selesai'], $csrf) ||
        !$id_pesanan_post ||
        $id_pesanan_post < 1
    ) {
        header("Location: pesanan.php?error=" . urlencode("Permintaan tidak valid."));
        exit;
    }

    $stmt = mysqli_prepare($conn, "
        UPDATE pesanan
        SET status = 'selesai'
        WHERE id_pesanan = ?
        AND id_user = ?
        AND status = 'dikirim'
    ");

    mysqli_stmt_bind_param($stmt, "ii", $id_pesanan_post, $id_user);
    mysqli_stmt_execute($stmt);

    $berhasil = mysqli_stmt_affected_rows($stmt) === 1;
    mysqli_stmt_close($stmt);

    header("Location: pesanan.php?" . ($berhasil ? "success=" . urlencode("Pesanan berhasil diselesaikan.") : "error=" . urlencode("Pesanan tidak dapat diperbarui.")));
    exit;
}

// NOTIFIKASI
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

// KERANJANG
$total_keranjang = 0;

$cek_keranjang = mysqli_query($conn, "SHOW TABLES LIKE 'keranjang'");

if ($cek_keranjang && mysqli_num_rows($cek_keranjang) > 0) {
    $stmt = mysqli_prepare($conn, "
        SELECT COALESCE(SUM(jumlah), 0) AS total
        FROM keranjang
        WHERE id_user = ?
    ");

    mysqli_stmt_bind_param($stmt, "i", $id_user);
    mysqli_stmt_execute($stmt);

    $cart = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    $total_keranjang = (int)($cart['total'] ?? 0);

    mysqli_stmt_close($stmt);
}

// DATA PESANAN
$stmt = mysqli_prepare($conn, "
    SELECT
        p.id_pesanan,
        p.tanggal_pesanan,
        p.total_harga,
        p.alamat_pengiriman,
        p.metode_pembayaran,
        p.status,
        COUNT(dp.id_detail) AS jumlah_jenis_barang,
        COALESCE(SUM(dp.jumlah), 0) AS total_barang
    FROM pesanan p
    LEFT JOIN detail_pesanan dp ON p.id_pesanan = dp.id_pesanan
    WHERE p.id_user = ?
    GROUP BY
        p.id_pesanan,
        p.tanggal_pesanan,
        p.total_harga,
        p.alamat_pengiriman,
        p.metode_pembayaran,
        p.status
    ORDER BY p.id_pesanan DESC
");

mysqli_stmt_bind_param($stmt, "i", $id_user);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$pesanan = [];

while ($row = mysqli_fetch_assoc($result)) {
    $pesanan[] = $row;
}

mysqli_stmt_close($stmt);

// CEK TABEL TESTIMONI
$testimoni_exists = false;
$cek_testimoni = mysqli_query($conn, "SHOW TABLES LIKE 'testimoni'");

if ($cek_testimoni && mysqli_num_rows($cek_testimoni) > 0) {
    $testimoni_exists = true;
}

// PESANAN SUDAH DIULAS
$pesanan_sudah_diulas = [];

if ($testimoni_exists) {
    $stmt = mysqli_prepare($conn, "
        SELECT id_pesanan
        FROM testimoni
        WHERE id_user = ?
    ");

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $id_user);
        mysqli_stmt_execute($stmt);

        $result_ulasan = mysqli_stmt_get_result($stmt);

        while ($row = mysqli_fetch_assoc($result_ulasan)) {
            $pesanan_sudah_diulas[] = (int)$row['id_pesanan'];
        }

        mysqli_stmt_close($stmt);
    }
}

// DETAIL PRODUK
$detail_pesanan = [];

if (!empty($pesanan)) {
    $stmt = mysqli_prepare($conn, "
        SELECT
            dp.id_detail,
            dp.id_pesanan,
            dp.jumlah,
            dp.harga,
            dp.subtotal,
            b.nama_barang,
            b.satuan,
            b.gambar
        FROM detail_pesanan dp
        INNER JOIN barang b ON dp.id_barang = b.id_barang
        INNER JOIN pesanan p ON dp.id_pesanan = p.id_pesanan
        WHERE p.id_user = ?
        ORDER BY dp.id_detail ASC
    ");

    mysqli_stmt_bind_param($stmt, "i", $id_user);
    mysqli_stmt_execute($stmt);

    $result_detail = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result_detail)) {
        $detail_pesanan[(int)$row['id_pesanan']][] = $row;
    }

    mysqli_stmt_close($stmt);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Pesanan Saya - Toko Malaya</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #f8fafc;
    color: #1f2937;
}

a {
    text-decoration: none;
}

/* NAVBAR */

.navbar {
    position: sticky;
    top: 0;
    z-index: 10;
    background: white;
    border-bottom: 1px solid #e5e7eb;
}

.navbar-inner {
    max-width: 1200px;
    min-height: 68px;
    margin: auto;
    padding: 0 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
}

.logo {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #153b63;
    font-size: 20px;
    font-weight: bold;
}

.logo-icon {
    width: 40px;
    height: 40px;
    display: grid;
    place-items: center;
    background: #1677d2;
    border-radius: 10px;
}

.logo-icon img {
    width: 35px;
    height: 35px;
    object-fit: contain;
}

.nav-menu {
    display: flex;
    gap: 27px;
    margin-left: auto;
}

.nav-menu a {
    color: #4b5563;
    font-size: 14px;
    font-weight: 600;
}

.nav-menu a:hover,
.nav-menu a.active {
    color: #1677d2;
}

.nav-right {
    display: flex;
    align-items: center;
    gap: 10px;
}

.cart-button {
    position: relative;
    width: 40px;
    height: 40px;
    display: grid;
    place-items: center;
    border-radius: 9px;
    background: #f1f6fc;
    color: #1677d2;
    font-size: 19px;
}

.cart-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    min-width: 18px;
    height: 18px;
    display: grid;
    place-items: center;
    padding: 0 4px;
    background: #dc3545;
    color: white;
    border-radius: 20px;
    font-size: 10px;
}

.profile-button,
.logout-button {
    padding: 9px 12px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    color: #374151;
    font-size: 12px;
    font-weight: 600;
}

/* MAIN */

.main {
    max-width: 1200px;
    margin: auto;
    padding: 25px 24px 40px;
}

.page-header {
    margin-bottom: 20px;
}

.page-header h1 {
    margin: 0 0 6px;
    color: #153b63;
    font-size: 27px;
}

.page-header p {
    margin: 0;
    color: #6b7280;
    font-size: 13px;
}

/* ALERT */

.alert {
    margin-bottom: 15px;
    padding: 11px 14px;
    border-radius: 8px;
    font-size: 13px;
}

.alert-success {
    background: #dcfce7;
    color: #166534;
}

.alert-error {
    background: #fee2e2;
    color: #991b1b;
}

/* ORDER CARD */

.order-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.order-card {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 11px;
    overflow: hidden;
}

.order-header {
    min-height: 52px;
    padding: 11px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid #e5e7eb;
}

.order-number {
    color: #153b63;
    font-size: 14px;
    font-weight: bold;
}

.order-date {
    margin-top: 3px;
    color: #6b7280;
    font-size: 11px;
}

.status {
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: bold;
}

.menunggu {
    background: #fef3c7;
    color: #92400e;
}

.diproses {
    background: #dbeafe;
    color: #1e40af;
}

.dikirim {
    background: #ede9fe;
    color: #5b21b6;
}

.selesai {
    background: #dcfce7;
    color: #166534;
}

.dibatalkan {
    background: #fee2e2;
    color: #991b1b;
}

/* ORDER BODY */

.order-body {
    padding: 13px 16px;
}

.order-info {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px 24px;
    padding-bottom: 11px;
    border-bottom: 1px solid #f0f0f0;
}

.info-item {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
}

.info-label {
    color: #6b7280;
}

.info-value {
    color: #374151;
    font-weight: 600;
}

.address-item {
    width: 100%;
    line-height: 1.5;
}

/* PRODUCT */

.detail-title {
    margin: 12px 0 5px;
    color: #153b63;
    font-size: 13px;
    font-weight: bold;
}

.product-item {
    display: flex;
    align-items: center;
    gap: 11px;
    padding: 8px 0;
}

.product-image {
    width: 43px;
    height: 43px;
    flex-shrink: 0;
    display: grid;
    place-items: center;
    overflow: hidden;
    background: #f8fafc;
    border: 1px solid #f0f0f0;
    border-radius: 6px;
}

.product-image img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.product-info {
    flex: 1;
    min-width: 0;
}

.product-name {
    margin-bottom: 4px;
    color: #1f2937;
    font-size: 12px;
    font-weight: 600;
}

.product-meta {
    color: #6b7280;
    font-size: 11px;
}

.product-subtotal {
    color: #1677d2;
    font-size: 13px;
    font-weight: bold;
    white-space: nowrap;
}

/* ORDER FOOTER */

.order-footer {
    min-height: 59px;
    padding: 10px 16px;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 16px;
    border-top: 1px solid #e5e7eb;
    background: #fafafa;
}

.total-area {
    text-align: right;
}

.total-label {
    color: #6b7280;
    font-size: 11px;
}

.total-value {
    margin-top: 3px;
    color: #1677d2;
    font-size: 16px;
    font-weight: bold;
}

.btn-selesai,
.btn-ulasan,
.btn-sudah-ulasan {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 9px 13px;
    border: none;
    border-radius: 7px;
    font-size: 12px;
    font-weight: bold;
    cursor: pointer;
}

.btn-selesai {
    background: #15803d;
    color: white;
}

.btn-ulasan {
    background: #1677d2;
    color: white;
}

.btn-sudah-ulasan {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #bbf7d0;
}

/* EMPTY */

.empty-state {
    padding: 50px 20px;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 11px;
    text-align: center;
}

.empty-icon {
    font-size: 42px;
    margin-bottom: 10px;
}

.empty-state h2 {
    margin: 0 0 8px;
    color: #153b63;
    font-size: 19px;
}

.empty-state p {
    color: #6b7280;
    font-size: 13px;
}

.btn-shopping {
    display: inline-block;
    margin-top: 10px;
    padding: 10px 16px;
    border-radius: 7px;
    background: #1677d2;
    color: white;
    font-size: 12px;
    font-weight: bold;
}

/* FOOTER */

.footer {
    margin-top: 35px;
    padding: 20px 0;
    border-top: 1px solid #e5e7eb;
    color: #6b7280;
    font-size: 12px;
    text-align: center;
}

/* RESPONSIVE */

@media(max-width: 800px) {
    .nav-menu {
        display: none;
    }

    .main {
        padding: 20px 14px 35px;
    }

    .profile-button {
        display: none;
    }
}

@media(max-width: 500px) {
    .navbar-inner {
        padding: 0 13px;
    }

    .logo {
        font-size: 17px;
    }

    .page-header h1 {
        font-size: 23px;
    }

    .order-info {
        gap: 9px 15px;
    }

    .info-item {
        font-size: 11px;
    }

    .order-footer {
        gap: 10px;
        flex-wrap: wrap;
    }

    .total-area {
        margin-right: auto;
    }

    .btn-selesai,
    .btn-ulasan,
    .btn-sudah-ulasan {
        padding: 9px 10px;
    }
}
</style>
</head>

<body>

<header class="navbar">
    <div class="navbar-inner">

        <a href="dashboard.php" class="logo">
            <span class="logo-icon">
                <img src="../assets/SembakoKu (3).png" alt="Logo">
            </span>
            <span>Toko Malaya</span>
        </a>

        <nav class="nav-menu">
            <a href="dashboard.php">Beranda</a>
            <a href="produk.php">Produk</a>
            <a href="pesanan.php" class="active">Pesanan</a>
        </nav>

        <div class="nav-right">

            <a href="keranjang.php" class="cart-button">
                🛒
                <?php if ($total_keranjang > 0): ?>
                    <span class="cart-badge"><?= $total_keranjang ?></span>
                <?php endif; ?>
            </a>

            <a href="profil.php" class="profile-button">
                👤 <?= htmlspecialchars($nama, ENT_QUOTES, 'UTF-8') ?>
            </a>

            <a href="../auth/logout.php" class="logout-button">
                Logout
            </a>

        </div>
    </div>
</header>

<main class="main">

    <div class="page-header">
        <h1>Pesanan Saya</h1>
        <p>Lihat dan pantau semua pesanan kamu di Toko Malaya.</p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success">
            ✓ <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error">
            ⚠ <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if (empty($pesanan)): ?>

        <div class="empty-state">
            <div class="empty-icon">📦</div>
            <h2>Belum Ada Pesanan</h2>
            <p>Yuk mulai belanja kebutuhan sehari-hari di Toko Malaya.</p>
            <a href="produk.php" class="btn-shopping">Mulai Belanja</a>
        </div>

    <?php else: ?>

        <div class="order-list">

            <?php foreach ($pesanan as $order):

                $id_pesanan = (int)$order['id_pesanan'];
                $status = (string)$order['status'];

                $tanggal = date(
                    'd/m/Y H:i',
                    strtotime($order['tanggal_pesanan'])
                );

            ?>

                <article class="order-card">

                    <div class="order-header">

                        <div>
                            <div class="order-number">
                                Pesanan #<?= str_pad($id_pesanan, 5, '0', STR_PAD_LEFT) ?>
                            </div>

                            <div class="order-date">
                                <?= htmlspecialchars($tanggal, ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        </div>

                        <span class="status <?= status_class($status) ?>">
                            <?= htmlspecialchars(status_label($status), ENT_QUOTES, 'UTF-8') ?>
                        </span>

                    </div>

                    <div class="order-body">

                        <div class="order-info">

                            <div class="info-item">
                                <span class="info-label">Pembayaran:</span>
                                <span class="info-value">
                                    <?= htmlspecialchars($order['metode_pembayaran'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </div>

                            <div class="info-item">
                                <span class="info-label">Barang:</span>
                                <span class="info-value">
                                    <?= (int)$order['total_barang'] ?> barang
                                    (<?= (int)$order['jumlah_jenis_barang'] ?> jenis)
                                </span>
                            </div>

                            <div class="info-item address-item">
                                <span class="info-label">Alamat:</span>
                                <span class="info-value">
                                    <?= htmlspecialchars($order['alamat_pengiriman'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </div>

                        </div>

                        <div class="detail-title">Detail Barang</div>

                        <?php if (isset($detail_pesanan[$id_pesanan])): ?>

                            <?php foreach ($detail_pesanan[$id_pesanan] as $detail):

                                $gambar = trim((string)($detail['gambar'] ?? ''));
                                $path_gambar = '../uploads/' . $gambar;

                            ?>

                                <div class="product-item">

                                    <div class="product-image">

                                        <?php if ($gambar !== '' && is_file($path_gambar)): ?>
                                            <img
                                                src="<?= htmlspecialchars($path_gambar, ENT_QUOTES, 'UTF-8') ?>"
                                                alt="<?= htmlspecialchars($detail['nama_barang'], ENT_QUOTES, 'UTF-8') ?>"
                                            >
                                        <?php else: ?>
                                            📦
                                        <?php endif; ?>

                                    </div>

                                    <div class="product-info">

                                        <div class="product-name">
                                            <?= htmlspecialchars($detail['nama_barang'], ENT_QUOTES, 'UTF-8') ?>
                                        </div>

                                        <div class="product-meta">
                                            <?= (int)$detail['jumlah'] ?>
                                            <?= htmlspecialchars($detail['satuan'], ENT_QUOTES, 'UTF-8') ?>
                                            × <?= rupiah($detail['harga']) ?>
                                        </div>

                                    </div>

                                    <div class="product-subtotal">
                                        <?= rupiah($detail['subtotal']) ?>
                                    </div>

                                </div>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <div class="product-meta">
                                Detail barang tidak ditemukan.
                            </div>

                        <?php endif; ?>

                    </div>

                    <div class="order-footer">

                        <div class="total-area">
                            <div class="total-label">Total Pembayaran</div>
                            <div class="total-value">
                                <?= rupiah($order['total_harga']) ?>
                            </div>
                        </div>

                        <?php if ($status === 'dikirim'): ?>

                            <form
                                method="POST"
                                action="pesanan.php"
                                onsubmit="return confirm('Pastikan barang sudah diterima. Tandai pesanan selesai?');"
                            >

                                <input type="hidden" name="aksi" value="tandai_selesai">

                                <input
                                    type="hidden"
                                    name="id_pesanan"
                                    value="<?= $id_pesanan ?>"
                                >

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= htmlspecialchars($_SESSION['csrf_pesanan_selesai'], ENT_QUOTES, 'UTF-8') ?>"
                                >

                                <button type="submit" class="btn-selesai">
                                    ✓ Tandai Selesai
                                </button>

                            </form>

                        <?php endif; ?>

                        <?php if ($status === 'selesai'): ?>

                            <?php if (in_array($id_pesanan, $pesanan_sudah_diulas, true)): ?>

                                <span class="btn-sudah-ulasan">
                                    ✓ Sudah Diulas
                                </span>

                            <?php else: ?>

                                <a
                                    href="beri_ulasan.php?id_pesanan=<?= $id_pesanan ?>"
                                    class="btn-ulasan"
                                >
                                    ★ Beri Ulasan
                                </a>

                            <?php endif; ?>

                        <?php endif; ?>

                    </div>

                </article>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

    <footer class="footer">
        © <?= date('Y') ?> Toko Malaya. Belanja kebutuhan sehari-hari dengan mudah.
    </footer>

</main>

</body>
</html>
