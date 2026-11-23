
<?php
session_start();
require_once "../config/database.php";

// ========================================
// CEK LOGIN
// ========================================

if (
    !isset($_SESSION['id_user']) ||
    ($_SESSION['role'] ?? '') !== 'pelanggan'
) {
    header("Location: ../auth/login.php");
    exit;
}

$id_user = (int)$_SESSION['id_user'];
$id_pesanan = filter_input(INPUT_GET, 'id_pesanan', FILTER_VALIDATE_INT);

if (!$id_pesanan || $id_pesanan < 1) {
    header("Location: pesanan.php?error=" . urlencode("Pesanan tidak valid."));
    exit;
}

// ========================================
// CSRF TOKEN
// ========================================

if (empty($_SESSION['csrf_beri_ulasan'])) {
    $_SESSION['csrf_beri_ulasan'] = bin2hex(random_bytes(32));
}

// ========================================
// CEK TABEL TESTIMONI
// ========================================

$cek_tabel = mysqli_query($conn, "SHOW TABLES LIKE 'testimoni'");

if (!$cek_tabel || mysqli_num_rows($cek_tabel) === 0) {
    die("Tabel testimoni belum tersedia. Silakan buat tabel testimoni terlebih dahulu.");
}

// ========================================
// AMBIL DATA PESANAN
// ========================================

$stmt = mysqli_prepare($conn, "
    SELECT
        p.id_pesanan,
        p.tanggal_pesanan,
        p.total_harga,
        p.status
    FROM pesanan p
    WHERE p.id_pesanan = ?
      AND p.id_user = ?
      AND p.status = 'selesai'
    LIMIT 1
");

mysqli_stmt_bind_param($stmt, "ii", $id_pesanan, $id_user);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$pesanan = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$pesanan) {
    header("Location: pesanan.php?error=" . urlencode("Pesanan tidak ditemukan atau belum selesai."));
    exit;
}

// ========================================
// CEK APAKAH SUDAH DIULAS
// ========================================

$stmt = mysqli_prepare($conn, "
    SELECT id_testimoni
    FROM testimoni
    WHERE id_pesanan = ?
    LIMIT 1
");

mysqli_stmt_bind_param($stmt, "i", $id_pesanan);
mysqli_stmt_execute($stmt);

$sudah_diulas = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

mysqli_stmt_close($stmt);

if ($sudah_diulas) {
    header("Location: pesanan.php?error=" . urlencode("Pesanan ini sudah pernah diberi ulasan."));
    exit;
}

// ========================================
// AMBIL DETAIL PRODUK
// ========================================

$stmt = mysqli_prepare($conn, "
    SELECT
        dp.id_detail,
        dp.jumlah,
        dp.harga,
        dp.subtotal,
        b.nama_barang,
        b.gambar,
        b.satuan
    FROM detail_pesanan dp
    INNER JOIN barang b ON dp.id_barang = b.id_barang
    WHERE dp.id_pesanan = ?
    ORDER BY dp.id_detail ASC
");

mysqli_stmt_bind_param($stmt, "i", $id_pesanan);
mysqli_stmt_execute($stmt);

$result_produk = mysqli_stmt_get_result($stmt);

$produk = [];

while ($row = mysqli_fetch_assoc($result_produk)) {
    $produk[] = $row;
}

mysqli_stmt_close($stmt);

if (empty($produk)) {
    die("Produk pada pesanan ini tidak ditemukan.");
}

// ========================================
// PROSES SIMPAN ULASAN
// ========================================

$error = '';

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['kirim_ulasan'])
) {

    $token = (string)($_POST['csrf_token'] ?? '');

    if (!hash_equals($_SESSION['csrf_beri_ulasan'], $token)) {

        $error = "Permintaan tidak valid. Silakan muat ulang halaman.";

    } else {

        $rating = filter_input(
            INPUT_POST,
            'rating',
            FILTER_VALIDATE_INT
        );

        $komentar = trim((string)($_POST['komentar'] ?? ''));

        if (!$rating || $rating < 1 || $rating > 5) {

            $error = "Silakan pilih rating bintang 1 sampai 5.";

        } elseif (mb_strlen($komentar) < 10) {

            $error = "Ulasan minimal terdiri dari 10 karakter.";

        } elseif (mb_strlen($komentar) > 1000) {

            $error = "Ulasan maksimal 1000 karakter.";

        } else {

            // CEK ULANG STATUS DAN KEPEMILIKAN PESANAN

            $stmt_cek = mysqli_prepare($conn, "
                SELECT id_pesanan
                FROM pesanan
                WHERE id_pesanan = ?
                  AND id_user = ?
                  AND status = 'selesai'
                LIMIT 1
            ");

            mysqli_stmt_bind_param(
                $stmt_cek,
                "ii",
                $id_pesanan,
                $id_user
            );

            mysqli_stmt_execute($stmt_cek);

            $valid = mysqli_fetch_assoc(
                mysqli_stmt_get_result($stmt_cek)
            );

            mysqli_stmt_close($stmt_cek);

            if (!$valid) {

                $error = "Pesanan tidak valid atau belum selesai.";

            } else {

                $stmt_insert = mysqli_prepare($conn, "
                    INSERT INTO testimoni
                        (id_user, id_pesanan, rating, komentar)
                    VALUES (?, ?, ?, ?)
                ");

                if (!$stmt_insert) {

                    $error = "Gagal menyiapkan penyimpanan ulasan.";

                } else {

                    mysqli_stmt_bind_param(
                        $stmt_insert,
                        "iiis",
                        $id_user,
                        $id_pesanan,
                        $rating,
                        $komentar
                    );

                    if (mysqli_stmt_execute($stmt_insert)) {

                        mysqli_stmt_close($stmt_insert);

                        header("Location: pesanan.php?success=" . urlencode("Terima kasih! Ulasan kamu berhasil dikirim."));

                        exit;

                    } else {

                        $kode_error = mysqli_stmt_errno($stmt_insert);

                        mysqli_stmt_close($stmt_insert);

                        if ($kode_error === 1062) {
                            $error = "Pesanan ini sudah pernah diberi ulasan.";
                        } else {
                            $error = "Ulasan gagal dikirim. Silakan coba kembali.";
                        }
                    }
                }
            }
        }
    }
}

function rupiah($angka) {
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Beri Ulasan - Toko Malaya</title>

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

.back-link {
    color: #1677d2;
    font-size: 13px;
    font-weight: 600;
}

/* MAIN */

.main {
    max-width: 800px;
    margin: auto;
    padding: 30px 20px 50px;
}

.page-heading {
    margin-bottom: 22px;
}

.page-heading h1 {
    margin: 0 0 7px;
    color: #153b63;
    font-size: 26px;
}

.page-heading p {
    margin: 0;
    color: #6b7280;
    font-size: 13px;
    line-height: 1.6;
}

/* CARD */

.review-card {
    overflow: hidden;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    box-shadow: 0 3px 12px #0f172a08;
}

.card-header {
    padding: 16px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #fafafa;
    border-bottom: 1px solid #e5e7eb;
}

.order-number {
    color: #153b63;
    font-size: 14px;
    font-weight: bold;
}

.order-date {
    margin-top: 4px;
    color: #6b7280;
    font-size: 11px;
}

.status {
    padding: 6px 11px;
    border-radius: 20px;
    background: #dcfce7;
    color: #166534;
    font-size: 11px;
    font-weight: bold;
}

/* PRODUK */

.card-body {
    padding: 20px;
}

.section-title {
    margin: 0 0 14px;
    color: #153b63;
    font-size: 14px;
}

.product-list {
    display: flex;
    flex-direction: column;
}

.product-item {
    display: flex;
    align-items: center;
    gap: 13px;
    padding: 11px 0;
    border-bottom: 1px solid #f0f0f0;
}

.product-item:last-child {
    border-bottom: none;
}

.product-image {
    width: 55px;
    height: 55px;
    flex-shrink: 0;
    display: grid;
    place-items: center;
    overflow: hidden;
    background: #f8fafc;
    border: 1px solid #eee;
    border-radius: 7px;
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
    margin-bottom: 5px;
    color: #1f2937;
    font-size: 13px;
    font-weight: bold;
}

.product-meta {
    color: #6b7280;
    font-size: 11px;
}

.product-price {
    color: #1677d2;
    font-size: 13px;
    font-weight: bold;
    white-space: nowrap;
}

.order-total {
    margin-top: 12px;
    padding-top: 13px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-top: 1px solid #e5e7eb;
}

.order-total span:first-child {
    color: #6b7280;
    font-size: 12px;
}

.order-total strong {
    color: #1677d2;
    font-size: 16px;
}

/* FORM */

.review-form {
    margin-top: 22px;
    padding-top: 22px;
    border-top: 1px solid #e5e7eb;
}

.form-label {
    display: block;
    margin-bottom: 12px;
    color: #153b63;
    font-size: 14px;
    font-weight: bold;
}

/* RATING */

.rating-area {
    margin-bottom: 24px;
}

.rating-stars {
    display: flex;
    flex-direction: row-reverse;
    justify-content: flex-end;
    gap: 7px;
}

.rating-stars input {
    display: none;
}

.rating-stars label {
    color: #d1d5db;
    font-size: 36px;
    cursor: pointer;
    transition: .15s;
}

.rating-stars label:hover,
.rating-stars label:hover ~ label,
.rating-stars input:checked ~ label {
    color: #f5a623;
}

.rating-caption {
    margin-top: 8px;
    color: #6b7280;
    font-size: 12px;
}

/* KOMENTAR */

textarea {
    width: 100%;
    min-height: 125px;
    padding: 13px;
    resize: vertical;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    outline: none;
    font-family: inherit;
    font-size: 13px;
    line-height: 1.6;
}

textarea:focus {
    border-color: #1677d2;
    box-shadow: 0 0 0 3px #1677d21a;
}

.char-count {
    margin-top: 6px;
    color: #9ca3af;
    font-size: 11px;
    text-align: right;
}

/* ALERT */

.alert {
    margin-bottom: 16px;
    padding: 12px 14px;
    border-radius: 8px;
    font-size: 13px;
    line-height: 1.5;
}

.alert-error {
    color: #991b1b;
    background: #fee2e2;
    border: 1px solid #fecaca;
}

/* BUTTON */

.form-actions {
    margin-top: 20px;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.btn-cancel {
    padding: 11px 17px;
    border: 1px solid #d1d5db;
    border-radius: 7px;
    color: #4b5563;
    background: white;
    font-size: 12px;
    font-weight: bold;
}

.btn-submit {
    padding: 11px 19px;
    border: none;
    border-radius: 7px;
    background: #1677d2;
    color: white;
    font-size: 12px;
    font-weight: bold;
    cursor: pointer;
}

.btn-submit:hover {
    background: #125fa9;
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

@media(max-width: 550px) {

    .navbar-inner {
        padding: 0 15px;
    }

    .logo {
        font-size: 17px;
    }

    .main {
        padding: 22px 13px 35px;
    }

    .page-heading h1 {
        font-size: 23px;
    }

    .card-header,
    .card-body {
        padding: 15px;
    }

    .product-image {
        width: 47px;
        height: 47px;
    }

    .product-price {
        font-size: 11px;
    }

    .rating-stars label {
        font-size: 32px;
    }

    .form-actions {
        flex-direction: column-reverse;
    }

    .btn-submit,
    .btn-cancel {
        width: 100%;
        text-align: center;
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

        <a href="pesanan.php" class="back-link">
            ← Kembali ke Pesanan
        </a>

    </div>

</header>

<main class="main">

    <div class="page-heading">

        <h1>Beri Ulasan</h1>

        <p>
            Bagikan pengalaman kamu setelah berbelanja di Toko Malaya.
            Ulasan kamu membantu pelanggan lainnya.
        </p>

    </div>

    <div class="review-card">

        <div class="card-header">

            <div>

                <div class="order-number">
                    Pesanan #<?= str_pad(
                        (string)$id_pesanan,
                        5,
                        '0',
                        STR_PAD_LEFT
                    ) ?>
                </div>

                <div class="order-date">
                    <?= date(
                        'd/m/Y H:i',
                        strtotime($pesanan['tanggal_pesanan'])
                    ) ?>
                </div>

            </div>

            <span class="status">
                ✓ Selesai
            </span>

        </div>

        <div class="card-body">

            <h3 class="section-title">
                Produk yang Dibeli
            </h3>

            <div class="product-list">

                <?php foreach ($produk as $item):

                    $gambar = trim((string)($item['gambar'] ?? ''));
                    $path_gambar = "../uploads/" . $gambar;

                ?>

                    <div class="product-item">

                        <div class="product-image">

                            <?php if (
                                $gambar !== '' &&
                                is_file($path_gambar)
                            ): ?>

                                <img
                                    src="<?= htmlspecialchars(
                                        $path_gambar,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                    alt="<?= htmlspecialchars(
                                        $item['nama_barang'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                >

                            <?php else: ?>

                                📦

                            <?php endif; ?>

                        </div>

                        <div class="product-info">

                            <div class="product-name">

                                <?= htmlspecialchars(
                                    $item['nama_barang'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </div>

                            <div class="product-meta">

                                <?= (int)$item['jumlah'] ?>

                                <?= htmlspecialchars(
                                    $item['satuan'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                                × <?= rupiah($item['harga']) ?>

                            </div>

                        </div>

                        <div class="product-price">

                            <?= rupiah($item['subtotal']) ?>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

            <div class="order-total">

                <span>Total Pembayaran</span>

                <strong>
                    <?= rupiah($pesanan['total_harga']) ?>
                </strong>

            </div>

            <!-- FORM ULASAN -->

            <form
                method="POST"
                action="beri_ulasan.php?id_pesanan=<?= $id_pesanan ?>"
                class="review-form"
            >

                <?php if ($error !== ''): ?>

                    <div class="alert alert-error">

                        ⚠ <?= htmlspecialchars(
                            $error,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </div>

                <?php endif; ?>

                <div class="rating-area">

                    <label class="form-label">
                        Bagaimana pengalaman belanja kamu?
                    </label>

                    <div class="rating-stars">

                        <input
                            type="radio"
                            name="rating"
                            id="star5"
                            value="5"
                            required
                        >

                        <label for="star5" title="Sangat puas">★</label>

                        <input
                            type="radio"
                            name="rating"
                            id="star4"
                            value="4"
                        >

                        <label for="star4" title="Puas">★</label>

                        <input
                            type="radio"
                            name="rating"
                            id="star3"
                            value="3"
                        >

                        <label for="star3" title="Cukup">★</label>

                        <input
                            type="radio"
                            name="rating"
                            id="star2"
                            value="2"
                        >

                        <label for="star2" title="Kurang puas">★</label>

                        <input
                            type="radio"
                            name="rating"
                            id="star1"
                            value="1"
                        >

                        <label for="star1" title="Tidak puas">★</label>

                    </div>

                    <div class="rating-caption" id="ratingCaption">
                        Pilih bintang sesuai pengalaman kamu.
                    </div>

                </div>

                <div>

                    <label class="form-label" for="komentar">
                        Tulis Ulasan Kamu
                    </label>

                    <textarea
                        name="komentar"
                        id="komentar"
                        maxlength="1000"
                        minlength="10"
                        placeholder="Ceritakan pengalaman kamu setelah berbelanja di Toko Malaya..."
                        required
                    ></textarea>

                    <div class="char-count">
                        <span id="charCount">0</span>/1000 karakter
                    </div>

                </div>

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(
                        $_SESSION['csrf_beri_ulasan'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >

                <div class="form-actions">

                    <a href="pesanan.php" class="btn-cancel">
                        Batal
                    </a>

                    <button
                        type="submit"
                        name="kirim_ulasan"
                        class="btn-submit"
                    >
                        ★ Kirim Ulasan
                    </button>

                </div>

            </form>

        </div>

    </div>

    <footer class="footer">

        © <?= date('Y') ?> Toko Malaya.
        Belanja kebutuhan sehari-hari dengan mudah.

    </footer>

</main>

<script>

// HITUNG KARAKTER KOMENTAR

const komentar = document.getElementById('komentar');
const charCount = document.getElementById('charCount');

komentar.addEventListener('input', function() {
    charCount.textContent = this.value.length;
});

// KETERANGAN RATING

const ratingInputs = document.querySelectorAll('input[name="rating"]');
const ratingCaption = document.getElementById('ratingCaption');

const ratingText = {
    1: 'Sangat tidak puas',
    2: 'Kurang puas',
    3: 'Cukup puas',
    4: 'Puas',
    5: 'Sangat puas'
};

ratingInputs.forEach(function(input) {

    input.addEventListener('change', function() {

        ratingCaption.textContent = ratingText[this.value];

    });

});

</script>

</body>
</html>
