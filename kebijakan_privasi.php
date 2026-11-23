
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

    <title>Kebijakan Privasi - Toko Malaya</title>

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

        .privacy-list {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .privacy-card {
            padding: 25px 28px;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            transition: 0.2s;
        }

        .privacy-card:hover {
            border-color: #cbdff3;
            box-shadow: 0 5px 18px rgba(15, 61, 100, 0.06);
        }

        .privacy-card h2 {
            margin: 0 0 13px;
            color: #153b63;
            font-size: 19px;
        }

        .privacy-card p {
            margin: 0 0 10px;
            color: #5b6472;
            font-size: 14px;
            line-height: 1.8;
        }

        .privacy-card p:last-child {
            margin-bottom: 0;
        }

        .privacy-card ul {
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
            .privacy-card {
                padding: 20px;
            }

            .privacy-card h2 {
                font-size: 17px;
            }

            .privacy-card p,
            .privacy-card ul {
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
        Kebijakan Privasi
    </h1>

    <p>
        Informasi mengenai bagaimana Toko Malaya mengumpulkan,
        menggunakan, dan menjaga data pengguna.
    </p>

</section>

<!-- =========================
     CONTENT
========================= -->

<main class="content-wrapper">

    <div class="intro">

        <p>
            Toko Malaya menghargai privasi setiap pengguna yang
            mengakses dan menggunakan website ini. Kebijakan
            Privasi ini menjelaskan jenis informasi yang
            dikumpulkan, tujuan penggunaan informasi, serta
            bagaimana informasi tersebut dikelola dalam
            mendukung layanan Toko Malaya. Dengan menggunakan
            website ini, pengguna dianggap telah membaca dan
            memahami Kebijakan Privasi yang berlaku.
        </p>

    </div>

    <div class="privacy-list">

        <section class="privacy-card">

            <h2>1. Informasi yang Dikumpulkan</h2>

            <p>
                Untuk mendukung proses pendaftaran, pengelolaan
                akun, dan transaksi, Toko Malaya mengumpulkan
                beberapa informasi yang diberikan pengguna
                melalui website.
            </p>

            <ul>
                <li>Nama lengkap pengguna.</li>
                <li>Alamat email yang digunakan untuk akun.</li>
                <li>Username internal yang dibuat oleh sistem.</li>
                <li>Password akun yang disimpan dalam bentuk hash.</li>
                <li>Alamat pengiriman yang dimasukkan saat checkout.</li>
                <li>Informasi keranjang dan produk yang dipesan.</li>
                <li>Informasi transaksi, metode pembayaran, dan status pesanan.</li>
                <li>Bukti pembayaran QRIS yang diunggah oleh pelanggan.</li>
            </ul>

        </section>

        <section class="privacy-card">

            <h2>2. Tujuan Penggunaan Informasi</h2>

            <p>
                Informasi pengguna digunakan untuk menjalankan
                layanan dan fitur yang tersedia pada website
                Toko Malaya.
            </p>

            <ul>
                <li>Membuat dan mengelola akun pelanggan.</li>
                <li>Memverifikasi proses login pengguna.</li>
                <li>Menampilkan informasi profil pelanggan.</li>
                <li>Mengelola keranjang belanja dan pemesanan produk.</li>
                <li>Memproses alamat pengiriman pesanan.</li>
                <li>Memeriksa dan memverifikasi pembayaran QRIS.</li>
                <li>Menampilkan riwayat transaksi dan status pesanan.</li>
                <li>Membantu pelayanan pelanggan apabila terdapat kendala transaksi.</li>
            </ul>

        </section>

        <section class="privacy-card">

            <h2>3. Pengelolaan Informasi Akun</h2>

            <p>
                Pengguna dapat mengelola sebagian informasi akun
                melalui halaman Profil yang tersedia setelah
                login.
            </p>

            <ul>
                <li>Pengguna dapat memperbarui nama dan alamat email.</li>
                <li>Pengguna dapat mengubah password dengan memasukkan password lama.</li>
                <li>Password baru harus memenuhi ketentuan minimal 6 karakter.</li>
                <li>Pengguna bertanggung jawab menjaga kerahasiaan informasi login.</li>
            </ul>

            <p>
                Toko Malaya tidak meminta pengguna membagikan
                password kepada pihak lain.
            </p>

        </section>

        <section class="privacy-card">

            <h2>4. Informasi Transaksi dan Pengiriman</h2>

            <p>
                Ketika pengguna melakukan pemesanan, sistem akan
                menyimpan informasi yang diperlukan untuk
                mengelola transaksi.
            </p>

            <ul>
                <li>Produk dan jumlah barang yang dipesan.</li>
                <li>Total harga transaksi.</li>
                <li>Tanggal pemesanan.</li>
                <li>Alamat pengiriman.</li>
                <li>Metode pembayaran yang dipilih.</li>
                <li>Status pesanan dan status pembayaran.</li>
            </ul>

            <p>
                Informasi tersebut digunakan untuk membantu
                pelanggan memantau pesanan serta membantu
                pihak Toko Malaya dalam memproses transaksi.
            </p>

        </section>

        <section class="privacy-card">

            <h2>5. Informasi dan Bukti Pembayaran</h2>

            <p>
                Apabila pelanggan memilih metode pembayaran
                QRIS, pelanggan perlu mengunggah bukti pembayaran
                melalui halaman checkout.
            </p>

            <p>
                Bukti pembayaran digunakan oleh kasir untuk
                melakukan pemeriksaan dan menentukan status
                verifikasi pembayaran.
            </p>

            <p>
                Bukti pembayaran yang diunggah harus berupa
                gambar JPG, PNG, atau WEBP dengan ukuran
                maksimal 3 MB.
            </p>

        </section>

        <section class="privacy-card">

            <h2>6. Penyimpanan dan Keamanan Data</h2>

            <p>
                Data pengguna disimpan dalam database sistem
                Toko Malaya untuk mendukung proses pengelolaan
                akun dan transaksi.
            </p>

            <p>
                Password pengguna diproses menggunakan mekanisme
                hashing sebelum disimpan ke database. Sistem
                juga menggunakan sesi login untuk mengelola
                akses pengguna berdasarkan akun dan perannya.
            </p>

            <p>
                Toko Malaya berupaya menjaga informasi pengguna
                agar tidak digunakan secara tidak semestinya.
                Namun, pengguna juga perlu menjaga keamanan
                akun dan tidak membagikan informasi login
                kepada orang lain.
            </p>

        </section>

        <section class="privacy-card">

            <h2>7. Akses terhadap Informasi Pengguna</h2>

            <p>
                Informasi pengguna dapat diakses oleh pihak
                internal Toko Malaya sesuai dengan kebutuhan
                dan kewenangan dalam menjalankan sistem.
            </p>

            <ul>
                <li>Admin mengelola data pengguna dan operasional sistem sesuai hak aksesnya.</li>
                <li>Kasir mengakses informasi yang diperlukan untuk transaksi dan verifikasi pembayaran.</li>
                <li>Pelanggan dapat melihat informasi akun dan pesanan miliknya melalui halaman pelanggan.</li>
            </ul>

            <p>
                Pengguna tidak diperbolehkan mengakses atau
                menggunakan informasi milik pengguna lain
                tanpa izin.
            </p>

        </section>

        <section class="privacy-card">

            <h2>8. Pembagian Informasi kepada Pihak Lain</h2>

            <p>
                Informasi pengguna dikelola untuk kebutuhan
                layanan Toko Malaya. Pengguna tidak seharusnya
                membagikan data akun, bukti pembayaran, maupun
                informasi transaksi kepada pihak yang tidak
                berkepentingan.
            </p>

            <p>
                Apabila terdapat kebutuhan untuk membagikan
                informasi kepada pihak lain, pengguna akan
                diberi penjelasan mengenai tujuan dan kebutuhan
                pembagian informasi tersebut sesuai ketentuan
                yang berlaku.
            </p>

        </section>

        <section class="privacy-card">

            <h2>9. Hak dan Tanggung Jawab Pengguna</h2>

            <p>
                Pengguna memiliki tanggung jawab untuk menjaga
                kebenaran informasi yang diberikan ketika
                menggunakan layanan Toko Malaya.
            </p>

            <ul>
                <li>Memberikan nama dan email yang benar.</li>
                <li>Menjaga kerahasiaan password akun.</li>
                <li>Memastikan alamat pengiriman sudah benar.</li>
                <li>Mengunggah bukti pembayaran yang sesuai dengan transaksi.</li>
                <li>Menggunakan website sesuai dengan fungsi yang tersedia.</li>
                <li>Menghubungi Toko Malaya apabila menemukan masalah terkait akun atau transaksi.</li>
            </ul>

        </section>

        <section class="privacy-card">

            <h2>10. Perubahan Kebijakan Privasi</h2>

            <p>
                Toko Malaya dapat memperbarui Kebijakan Privasi
                apabila terdapat perubahan pada fitur website,
                proses pengelolaan data, atau kebutuhan layanan.
            </p>

            <p>
                Setiap perubahan akan ditampilkan melalui
                halaman Kebijakan Privasi ini. Pengguna
                disarankan untuk memeriksa halaman ini secara
                berkala.
            </p>

        </section>

        <section class="privacy-card">

            <h2>11. Kontak Toko Malaya</h2>

            <p>
                Apabila pengguna memiliki pertanyaan, kendala,
                atau ingin memperoleh informasi lebih lanjut
                mengenai pengelolaan data pribadi, pengguna
                dapat menghubungi Toko Malaya melalui Kontak Kami.
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

                <p>📍 Bekasi, Jawa Barat</p>

                <p>☎ 081234567890</p>

                <p>✉ halo@tokomalaya.test</p>

                <p>🕒 08.00 - 21.00 WIB</p>

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
