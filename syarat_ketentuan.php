
<?php
session_start();

$is_login = isset($_SESSION['id_user']);
$nama = $_SESSION['nama'] ?? '';
$role = $_SESSION['role'] ?? '';

function dashboard_url($role)
{
    switch ($role) {
        case 'admin':
            return 'admin/dashboard.php';
        case 'kasir':
            return 'kasir/dashboard.php';
        case 'pemilik':
            return 'pemilik/dashboard.php';
        case 'karyawan':
            return 'karyawan/dashboard.php';
        case 'pelanggan':
            return 'pelanggan/dashboard.php';
        default:
            return 'auth/login.php';
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Syarat dan Ketentuan - Toko Malaya</title>

    <style>

        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f6f8fb;
            color: #1f2937;
        }

        a {
            text-decoration: none;
        }

        /* =========================
           NAVBAR
        ========================= */

        .navbar {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: #ffffff;
            border-bottom: 1px solid #e5e7eb;
            padding: 0 6%;
        }

        .navbar-inner {
            max-width: 1200px;
            height: 72px;
            margin: auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 25px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #153b63;
            flex-shrink: 0;
        }

        .brand-logo {
            width: 42px;
            height: 42px;
            object-fit: contain;
        }

        .brand-name {
            font-size: 20px;
            font-weight: 700;
        }

        .nav-menu {
            display: flex;
            align-items: center;
            gap: 28px;
        }

        .nav-menu a {
            color: #4b5563;
            font-size: 14px;
            font-weight: 500;
            transition: 0.2s;
        }

        .nav-menu a:hover {
            color: #1677d2;
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }

        .user-name {
            color: #374151;
            font-size: 13px;
            font-weight: 600;
            margin-right: 4px;
            max-width: 130px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .btn-login,
        .btn-register,
        .btn-dashboard,
        .btn-logout {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 15px;
            border-radius: 7px;
            font-size: 13px;
            font-weight: 600;
            transition: 0.2s;
        }

        .btn-login {
            border: 1px solid #1677d2;
            color: #1677d2;
            background: #ffffff;
        }

        .btn-login:hover {
            background: #1677d2;
            color: #ffffff;
        }

        .btn-register,
        .btn-dashboard {
            background: #1677d2;
            color: #ffffff;
        }

        .btn-register:hover,
        .btn-dashboard:hover {
            background: #125fa8;
        }

        .btn-logout {
            border: 1px solid #dc3545;
            color: #dc3545;
            background: #ffffff;
        }

        .btn-logout:hover {
            background: #dc3545;
            color: #ffffff;
        }

        /* =========================
           HERO
        ========================= */

        .page-hero {
            position: relative;
            overflow: hidden;
            padding: 65px 20px;
            text-align: center;
            color: #ffffff;
            background: linear-gradient(
                120deg,
                #102a43 0%,
                #153b63 50%,
                #1677d2 100%
            );
        }

        .page-hero::before {
            content: "";
            position: absolute;
            width: 350px;
            height: 350px;
            right: -100px;
            top: -180px;
            border-radius: 50%;
            background: rgba(255,255,255,0.06);
        }

        .page-hero h1 {
            position: relative;
            z-index: 1;
            margin: 0 0 12px;
            font-size: 36px;
        }

        .page-hero p {
            position: relative;
            z-index: 1;
            margin: 0;
            color: #e5edf6;
            font-size: 15px;
            line-height: 1.7;
        }

        /* =========================
           CONTENT
        ========================= */

        .content-wrapper {
            max-width: 1000px;
            margin: auto;
            padding: 55px 25px 70px;
        }

        .intro {
            margin-bottom: 25px;
            padding: 25px;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-left: 4px solid #1677d2;
            border-radius: 9px;
        }

        .intro p {
            margin: 0;
            color: #4b5563;
            font-size: 14px;
            line-height: 1.8;
        }

        .terms-list {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .terms-card {
            padding: 25px 28px;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            transition: 0.2s;
        }

        .terms-card:hover {
            border-color: #cbdff3;
            box-shadow: 0 5px 18px rgba(15, 61, 100, 0.06);
        }

        .terms-card h2 {
            margin: 0 0 13px;
            color: #153b63;
            font-size: 19px;
        }

        .terms-card p {
            margin: 0 0 10px;
            color: #5b6472;
            font-size: 14px;
            line-height: 1.8;
        }

        .terms-card p:last-child {
            margin-bottom: 0;
        }

        .terms-card ul {
            margin: 8px 0 0;
            padding-left: 22px;
            color: #5b6472;
            font-size: 14px;
            line-height: 1.9;
        }

        .last-update {
            margin-top: 25px;
            color: #9ca3af;
            font-size: 12px;
            text-align: center;
        }

        /* =========================
           FOOTER
        ========================= */

        /* FOOTER */
        footer {
            background: #102d4f;
            color: white;
            padding: 45px 7% 20px;
            margin-top: 70px;
        }

        .footer-content {
            max-width: 1200px;
            margin: auto;
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 50px;
            margin-bottom: 30px;
        }

        .footer-content h3 {
            font-size: 16px;
            margin: 0 0 15px;
            color: white;
        }

        .footer-content p {
            color: #cbd5e1;
            font-size: 14px;
            line-height: 1.8;
            margin: 0 0 10px;
        }

        .footer-links,
        .footer-contact {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .footer-links a,
        .footer-contact a {
            color: #cbd5e1;
            font-size: 14px;
            text-decoration: none;
        }

        .footer-links a:hover,
        .footer-contact a:hover {
            color: white;
        }

        .footer-bottom {
            max-width: 1200px;
            margin: auto;
            border-top: 1px solid #36506c;
            padding-top: 20px;
            text-align: center;
            color: #cbd5e1;
            font-size: 13px;
        }

        @media (max-width: 768px) {
            .footer-content {
                grid-template-columns: 1fr;
                gap: 25px;
            }
        }
        /* =========================
           RESPONSIVE
        ========================= */

        @media (max-width: 1000px) {

            .nav-menu {
                gap: 18px;
            }

            .user-name {
                display: none;
            }

        }

        @media (max-width: 900px) {

            .nav-menu {
                display: none;
            }

        }

        @media (max-width: 600px) {

            .navbar {
                padding: 0 15px;
            }

            .navbar-inner {
                height: 64px;
            }

            .brand-name {
                font-size: 17px;
            }

            .brand-logo {
                width: 36px;
                height: 36px;
            }

            .btn-login,
            .btn-register,
            .btn-dashboard,
            .btn-logout {
                padding: 8px 10px;
                font-size: 11px;
            }

            .page-hero {
                padding: 50px 20px;
            }

            .page-hero h1 {
                font-size: 28px;
            }

            .page-hero p {
                font-size: 13px;
            }

            .content-wrapper {
                padding: 35px 15px 50px;
            }

            .intro,
            .terms-card {
                padding: 20px;
            }

            .terms-card h2 {
                font-size: 17px;
            }

            .terms-card p,
            .terms-card ul {
                font-size: 13px;
            }

            .footer-inner {
                padding: 30px 15px;
                flex-direction: column;
                align-items: flex-start;
            }

        }

    </style>

</head>

<body>

<!-- =========================
     NAVBAR
========================= -->

<nav class="navbar">

    <div class="navbar-inner">

        <a href="index.php" class="brand">

            <img
                src="assets/SembakoKu (3).png"
                alt="Logo Toko Malaya"
                class="brand-logo"
            >

            <span class="brand-name">
                Toko Malaya
            </span>

        </a>

        <div class="nav-menu">

            <a href="index.php">
                Beranda
            </a>

            <a href="index.php#keunggulan">
                Keunggulan
            </a>

            <a href="index.php#produk">
                Produk
            </a>

            <a href="tentang_kami.php">
                Tentang Kami
            </a>
            
            <a href="testimoni.php">
                Ulasan
            </a>

        </div>

        <div class="nav-right">

            <?php if (!$is_login): ?>

                <a href="auth/login.php" class="btn-login">
                    Login
                </a>

                <a href="auth/register.php" class="btn-register">
                    Daftar
                </a>

            <?php else: ?>

                <span class="user-name">
                    Halo, <?= htmlspecialchars($nama); ?>
                </span>

                <a href="auth/login.php" class="btn-login">
                    Login
                </a>

                <a
                    href="<?= htmlspecialchars(dashboard_url($role)); ?>"
                    class="btn-dashboard"
                >
                    Dashboard
                </a>

                <a href="auth/logout.php" class="btn-logout">
                    Logout
                </a>

            <?php endif; ?>

        </div>

    </div>

</nav>

<!-- =========================
     HERO
========================= -->

<section class="page-hero">

    <h1>
        Syarat dan Ketentuan
    </h1>

    <p>
        Ketahui ketentuan penggunaan website dan proses
        pemesanan produk di Toko Malaya.
    </p>

</section>

<!-- =========================
     CONTENT
========================= -->

<main class="content-wrapper">

    <div class="intro">

        <p>
            Selamat datang di website Toko Malaya. Halaman ini
            berisi syarat dan ketentuan yang berlaku bagi pengguna
            dalam mengakses website, membuat akun, melihat produk,
            serta melakukan pemesanan. Dengan menggunakan layanan
            Toko Malaya, pengguna dianggap telah membaca dan
            memahami ketentuan yang dijelaskan pada halaman ini.
        </p>

    </div>

    <div class="terms-list">

        <section class="terms-card">

            <h2>1. Ketentuan Umum</h2>

            <p>
                Toko Malaya merupakan website yang menyediakan
                informasi produk kebutuhan sehari-hari serta
                fasilitas pemesanan produk secara online.
            </p>

            <p>
                Pengguna wajib menggunakan website sesuai dengan
                fungsi yang tersedia dan tidak melakukan tindakan
                yang dapat mengganggu keamanan maupun operasional
                sistem Toko Malaya.
            </p>

        </section>

        <section class="terms-card">

            <h2>2. Pendaftaran dan Akun Pengguna</h2>

            <p>
                Pengguna dapat membuat akun pelanggan melalui
                halaman pendaftaran yang tersedia pada website.
                Dalam proses pendaftaran, pengguna diwajibkan
                mengisi nama lengkap, email, dan password.
            </p>

            <ul>
                <li>Nama lengkap wajib diisi dengan benar.</li>
                <li>Email harus menggunakan format yang valid dan belum terdaftar.</li>
                <li>Password minimal terdiri dari 6 karakter.</li>
                <li>Pengguna bertanggung jawab menjaga kerahasiaan password dan akun.</li>
                <li>Pengguna tidak diperbolehkan menggunakan akun milik orang lain tanpa izin.</li>
            </ul>

        </section>

        <section class="terms-card">

            <h2>3. Informasi Produk dan Stok</h2>

            <p>
                Toko Malaya menyediakan informasi produk yang
                meliputi nama barang, kategori, gambar, harga jual,
                dan jumlah stok yang tersedia.
            </p>

            <ul>
                <li>Harga yang digunakan dalam pemesanan mengikuti harga jual yang ditampilkan pada sistem.</li>
                <li>Jumlah stok produk dapat berubah mengikuti transaksi dan pembaruan data barang.</li>
                <li>Pengguna perlu memperhatikan ketersediaan stok sebelum melakukan pemesanan.</li>
                <li>Informasi produk dapat diperbarui oleh pihak Toko Malaya sesuai kebutuhan.</li>
            </ul>

        </section>

        <section class="terms-card">

            <h2>4. Keranjang dan Pemesanan</h2>

            <p>
                Pengguna yang telah login dapat memilih produk,
                memasukkan produk ke keranjang, mengatur jumlah
                pembelian, dan melanjutkan ke halaman checkout.
            </p>

            <ul>
                <li>Pengguna wajib memeriksa kembali produk dan jumlah barang dalam keranjang.</li>
                <li>Pengguna wajib memastikan alamat pengiriman telah diisi dengan benar.</li>
                <li>Pesanan dibuat setelah pengguna menyelesaikan proses checkout.</li>
                <li>Total pembayaran dihitung berdasarkan harga dan jumlah produk yang dipesan.</li>
                <li>Pengguna dapat melihat perkembangan pesanan melalui halaman Pesanan Saya.</li>
            </ul>

        </section>

        <section class="terms-card">

            <h2>5. Metode Pembayaran</h2>

            <p>
                Toko Malaya menyediakan dua metode pembayaran
                yang dapat dipilih pengguna pada saat checkout,
                yaitu COD dan QRIS.
            </p>

            <p>
                <strong>COD (Cash on Delivery)</strong><br>
                Pengguna memilih metode COD dan pembayaran
                dilakukan sesuai proses penyerahan barang.
                Status awal pembayaran pada sistem adalah
                belum bayar.
            </p>

            <p>
                <strong>QRIS</strong><br>
                Pengguna melakukan pembayaran dengan memindai
                kode QRIS resmi Toko Malaya melalui aplikasi
                pembayaran yang mendukung QRIS.
            </p>

        </section>

        <section class="terms-card">

            <h2>6. Ketentuan Pembayaran QRIS</h2>

            <p>
                Pengguna yang memilih pembayaran QRIS wajib
                mengunggah bukti pembayaran setelah menyelesaikan
                transaksi melalui aplikasi pembayaran.
            </p>

            <ul>
                <li>Bukti pembayaran harus berupa gambar JPG, PNG, atau WEBP.</li>
                <li>Ukuran maksimal file bukti pembayaran adalah 3 MB.</li>
                <li>Bukti pembayaran akan diperiksa oleh kasir Toko Malaya.</li>
                <li>Status pembayaran QRIS akan menunggu verifikasi sebelum dinyatakan terkonfirmasi.</li>
                <li>Pesanan QRIS tidak boleh dikirim sebelum pembayaran dikonfirmasi oleh kasir.</li>
            </ul>

            <p>
                Pengguna wajib memastikan bukti pembayaran yang
                diunggah merupakan bukti transaksi yang benar
                dan berhasil.
            </p>

        </section>

        <section class="terms-card">

            <h2>7. Proses dan Status Pesanan</h2>

            <p>
                Setiap pesanan yang dibuat melalui website akan
                memiliki status yang dapat dipantau oleh pelanggan.
                Status pesanan yang digunakan dalam sistem adalah:
            </p>

            <ul>
                <li><strong>Menunggu:</strong> Pesanan telah dibuat dan menunggu proses selanjutnya.</li>
                <li><strong>Diproses:</strong> Pesanan sedang dipersiapkan oleh pihak Toko Malaya.</li>
                <li><strong>Dikirim:</strong> Pesanan telah masuk ke tahap pengiriman.</li>
                <li><strong>Selesai:</strong> Pesanan telah dinyatakan selesai.</li>
                <li><strong>Dibatalkan:</strong> Pesanan telah dibatalkan oleh pihak yang berwenang dalam sistem.</li>
            </ul>

        </section>

        <section class="terms-card">

            <h2>8. Konfirmasi Penerimaan Pesanan</h2>

            <p>
                Ketika status pesanan telah berubah menjadi
                Dikirim, pelanggan dapat melakukan konfirmasi
                penerimaan barang melalui tombol
                <strong>Tandai Selesai</strong> pada halaman
                Pesanan Saya.
            </p>

            <p>
                Pelanggan diharapkan memastikan barang telah
                diterima sebelum menandai pesanan sebagai selesai.
                Setelah dikonfirmasi, status pesanan akan berubah
                menjadi Selesai.
            </p>

        </section>

        <section class="terms-card">

            <h2>9. Pembatalan Pesanan</h2>

            <p>
                Status pesanan dalam sistem dapat berubah menjadi
                Dibatalkan. Pengelolaan status pembatalan dilakukan
                melalui pihak yang memiliki kewenangan pada sistem
                Toko Malaya.
            </p>

            <p>
                Saat ini, halaman Pesanan Saya belum menyediakan
                tombol pembatalan mandiri bagi pelanggan.
                Oleh karena itu, pengguna dapat menghubungi
                Toko Malaya melalui halaman Kontak Kami apabila
                memiliki pertanyaan terkait pesanannya.
            </p>

        </section>

        <section class="terms-card">

            <h2>10. Keamanan dan Penggunaan Data</h2>

            <p>
                Informasi yang diberikan pengguna melalui website
                digunakan untuk mendukung proses pendaftaran,
                pengelolaan akun, pemesanan, dan pelayanan
                Toko Malaya.
            </p>

            <ul>
                <li>Pengguna wajib memberikan informasi yang benar ketika mendaftar dan melakukan pemesanan.</li>
                <li>Pengguna bertanggung jawab menjaga kerahasiaan informasi login.</li>
                <li>Pengguna tidak diperbolehkan menyalahgunakan akun atau fitur website.</li>
            </ul>

        </section>

        <section class="terms-card">

            <h2>11. Perubahan Syarat dan Ketentuan</h2>

            <p>
                Toko Malaya dapat melakukan perubahan terhadap
                syarat dan ketentuan apabila terdapat pembaruan
                fitur, proses transaksi, atau kebijakan layanan.
            </p>

            <p>
                Pengguna disarankan untuk membaca halaman ini
                secara berkala agar mengetahui ketentuan terbaru
                yang berlaku.
            </p>

        </section>

        <section class="terms-card">

            <h2>12. Persetujuan Pengguna</h2>

            <p>
                Dengan melakukan pendaftaran, mengakses, dan
                menggunakan fitur website Toko Malaya, pengguna
                dianggap telah membaca, memahami, dan menyetujui
                syarat dan ketentuan yang tercantum pada halaman ini.
            </p>

        </section>

    </div>

    <div class="last-update">

        Terakhir diperbarui:
        <?= date('d-m-Y'); ?>

    </div>

</main>

<!-- =========================
     FOOTER
========================= -->

<footer>

    <div class="footer-content">

        <!-- KOLOM 1 -->
        <div class="footer-about">

            <h3>Toko Malaya</h3>

            <p>
                Toko Malaya menyediakan berbagai kebutuhan
                sehari-hari untuk membantu memenuhi kebutuhan
                pelanggan dengan pengalaman belanja yang
                praktis, mudah, dan nyaman.
            </p>

        </div>

        <!-- KOLOM 2 -->
        <div>

            <h3>Tautan</h3>

            <div class="footer-links">

                <a href="index.php">Beranda</a>

                <a href="index.php#produk">Produk</a>

                <a href="tentang_kami.php">Tentang Kami</a>

                <a href="testimoni.php">Ulasan</a>
                
                <a href="syarat_ketentuan.php">Syarat dan Ketentuan</a>
                
                <a href="kebijakan_privasi.php">Kebijakan Privasi</a>
                
                <a href="faq.php">FAQ</a>

            </div>

        </div>

        <!-- KOLOM 3 -->
        <div>

            <h3>Kontak</h3>

            <div class="footer-contact">

                <p>📍 Kampung Tengah, DKI Jakarta</p>

                <p>☎ 088590043576</p>

                <p>✉ sembako@tokomalaya</p>

                <p>🕒 07.00 - 19.00 WIB</p>

            </div>

        </div>

    </div>

    <!-- COPYRIGHT -->
    <div class="footer-bottom">

        © <?= date('Y'); ?> Toko Malaya.
        Semua hak dilindungi.

    </div>

</footer>

</body>
</html>
