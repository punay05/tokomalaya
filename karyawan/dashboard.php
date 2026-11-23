<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['id_user'])) {
    header('Location: ../auth/login.php');
    exit;
}

if (($_SESSION['role'] ?? '') !== 'karyawan') {
    header('Location: ../index.php');
    exit;
}

mysqli_set_charset($conn, 'utf8mb4');

$idUser = (int) $_SESSION['id_user'];
$nama = $_SESSION['nama'] ?? 'Karyawan';
$email = $_SESSION['email'] ?? '';
$hariIni = date('Y-m-d');

function e($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function tabelAda(mysqli $conn, string $tabel): bool {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $tabel)) return false;
    $sql = "SHOW TABLES LIKE '" . mysqli_real_escape_string($conn, $tabel) . "'";
    $result = mysqli_query($conn, $sql);
    return $result && mysqli_num_rows($result) > 0;
}

function kolomAda(mysqli $conn, string $tabel, string $kolom): bool {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $tabel) ||
        !preg_match('/^[a-zA-Z0-9_]+$/', $kolom)) return false;

    $sql = "SHOW COLUMNS FROM `{$tabel}` LIKE '" .
        mysqli_real_escape_string($conn, $kolom) . "'";
    $result = mysqli_query($conn, $sql);
    return $result && mysqli_num_rows($result) > 0;
}

function tanggalIndonesia(string $tanggal): string {
    $bulan = [
        1=>'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];

    $ts = strtotime($tanggal);
    if (!$ts) return $tanggal;

    return (int)date('j', $ts) . ' ' .
        $bulan[(int)date('n', $ts)] . ' ' . date('Y', $ts);
}

function jam($value): string {
    return $value ? substr((string)$value, 0, 5) : '—';
}

/* Periksa status akun karyawan. */
if (tabelAda($conn, 'akun_karyawan_status')) {
    $stmt = mysqli_prepare(
        $conn,
        'SELECT aktif FROM akun_karyawan_status WHERE id_user = ? LIMIT 1'
    );

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $idUser);
        mysqli_stmt_execute($stmt);
        $statusAkun = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if ($statusAkun && (int)$statusAkun['aktif'] !== 1) {
            session_unset();
            session_destroy();
            header('Location: ../auth/login.php');
            exit;
        }
    }
}

/* Periksa tabel jadwal. */
$pesanSetup = [];

foreach (['shift', 'jadwal_shift'] as $namaTabel) {
    if (!tabelAda($conn, $namaTabel)) {
        $pesanSetup[] = $namaTabel;
    }
}

/* Ambil profil karyawan. */
$profil = null;

if (tabelAda($conn, 'profil_karyawan')) {
    $stmt = mysqli_prepare(
        $conn,
        'SELECT jabatan, gaji_bulanan, target_hari_bulanan, aktif
         FROM profil_karyawan
         WHERE id_user = ?
         LIMIT 1'
    );

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $idUser);
        mysqli_stmt_execute($stmt);
        $profil = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
    }
}

/* Ambil jadwal hari ini dan jadwal mendatang. */
$jadwalHariIni = null;
$jadwalMendatang = [];
$totalJadwal = 0;

if (!$pesanSetup) {
    $sqlHariIni = "
        SELECT j.id_jadwal, j.tanggal, j.catatan,
               s.nama_shift, s.jam_mulai, s.jam_selesai
        FROM jadwal_shift j
        INNER JOIN shift s ON s.id_shift = j.id_shift
        WHERE j.id_user = ? AND j.tanggal = ?
        LIMIT 1
    ";

    $stmt = mysqli_prepare($conn, $sqlHariIni);

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'is', $idUser, $hariIni);
        mysqli_stmt_execute($stmt);
        $jadwalHariIni = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
    }

    $sqlMendatang = "
        SELECT j.tanggal, j.catatan,
               s.nama_shift, s.jam_mulai, s.jam_selesai
        FROM jadwal_shift j
        INNER JOIN shift s ON s.id_shift = j.id_shift
        WHERE j.id_user = ? AND j.tanggal >= ?
        ORDER BY j.tanggal ASC, s.jam_mulai ASC
        LIMIT 7
    ";

    $stmt = mysqli_prepare($conn, $sqlMendatang);

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'is', $idUser, $hariIni);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        while ($row = mysqli_fetch_assoc($result)) {
            $jadwalMendatang[] = $row;
        }

        mysqli_stmt_close($stmt);
    }

    $stmt = mysqli_prepare(
        $conn,
        'SELECT COUNT(*) AS total
         FROM jadwal_shift
         WHERE id_user = ? AND tanggal >= ?'
    );

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'is', $idUser, $hariIni);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        $totalJadwal = (int)($row['total'] ?? 0);
        mysqli_stmt_close($stmt);
    }
}

/* Pengaturan keamanan absensi. */
if (empty($_SESSION['csrf_absensi'])) {
    $_SESSION['csrf_absensi'] = bin2hex(random_bytes(32));
}

$csrfAbsensi = $_SESSION['csrf_absensi'];
$pesanAbsensi = '';
$jenisPesanAbsensi = 'info';

/*
 * Struktur absensi yang digunakan halaman ini:
 * id_absensi, id_user, id_jadwal, tanggal, jam_masuk,
 * jam_pulang, status, menit_terlambat, denda,
 * created_at, updated_at.
 */
$absensiTersedia = tabelAda($conn, 'absensi');

$kolomAbsensiWajib = [
    'id_absensi', 'id_user', 'id_jadwal', 'tanggal',
    'jam_masuk', 'jam_pulang', 'status',
    'menit_terlambat', 'denda'
];

if ($absensiTersedia) {
    foreach ($kolomAbsensiWajib as $kolomWajib) {
        if (!kolomAda($conn, 'absensi', $kolomWajib)) {
            $absensiTersedia = false;
            break;
        }
    }
}

/* Proses absen masuk dan pulang. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi_absensi'])) {

    if (!$absensiTersedia) {
        $pesanAbsensi = 'Struktur tabel absensi belum sesuai. Periksa migrasi database.';
        $jenisPesanAbsensi = 'error';

    } elseif (
        !isset($_POST['csrf_absensi']) ||
        !hash_equals($csrfAbsensi, (string)$_POST['csrf_absensi'])
    ) {
        $pesanAbsensi = 'Sesi formulir tidak valid. Muat ulang halaman.';
        $jenisPesanAbsensi = 'error';

    } else {
        $aksiAbsensi = (string)$_POST['aksi_absensi'];

        $now = new DateTimeImmutable(
            'now',
            new DateTimeZone('Asia/Jakarta')
        );

        $tanggalAbsensi = $now->format('Y-m-d');
        $waktuAbsensi = $now->format('H:i:s');

        $stmtJadwal = mysqli_prepare(
            $conn,
            "SELECT j.id_jadwal, j.tanggal,
                    s.jam_mulai, s.jam_selesai
             FROM jadwal_shift j
             INNER JOIN shift s ON s.id_shift = j.id_shift
             WHERE j.id_user = ? AND j.tanggal = ?
             ORDER BY s.jam_mulai ASC
             LIMIT 1"
        );

        $jadwalAbsen = null;

        if ($stmtJadwal) {
            mysqli_stmt_bind_param(
                $stmtJadwal,
                'is',
                $idUser,
                $tanggalAbsensi
            );

            mysqli_stmt_execute($stmtJadwal);
            $jadwalAbsen = mysqli_fetch_assoc(
                mysqli_stmt_get_result($stmtJadwal)
            );
            mysqli_stmt_close($stmtJadwal);
        }

        if (!$jadwalAbsen) {
            $pesanAbsensi = 'Anda belum memiliki jadwal shift hari ini.';
            $jenisPesanAbsensi = 'error';

        } else {
            $idJadwalAbsen = (int)$jadwalAbsen['id_jadwal'];

            $stmtCek = mysqli_prepare(
                $conn,
                'SELECT id_absensi, jam_masuk, jam_pulang, status
                 FROM absensi
                 WHERE id_jadwal = ?
                 LIMIT 1'
            );

            $absenLama = null;

            if ($stmtCek) {
                mysqli_stmt_bind_param($stmtCek, 'i', $idJadwalAbsen);
                mysqli_stmt_execute($stmtCek);
                $absenLama = mysqli_fetch_assoc(
                    mysqli_stmt_get_result($stmtCek)
                );
                mysqli_stmt_close($stmtCek);
            }

            if ($aksiAbsensi === 'masuk') {

                if ($absenLama && !empty($absenLama['jam_masuk'])) {
                    $pesanAbsensi = 'Absen masuk sudah tercatat pada ' .
                        substr($absenLama['jam_masuk'], 0, 5) . '.';
                    $jenisPesanAbsensi = 'error';

                } else {
                    $waktuMulai = new DateTimeImmutable(
                        $tanggalAbsensi . ' ' . $jadwalAbsen['jam_mulai'],
                        new DateTimeZone('Asia/Jakarta')
                    );

                    $selisihDetik = $now->getTimestamp() -
                        $waktuMulai->getTimestamp();

                    $menitTelat = $selisihDetik > 0
                        ? (int)ceil($selisihDetik / 60)
                        : 0;

                    // Tepat 10 menit masih dalam toleransi.
                    $terlambat = $selisihDetik > 600;

                    $statusAbsen = $terlambat
                        ? 'Terlambat'
                        : (($menitTelat > 0)
                            ? 'Hadir (Toleransi)'
                            : 'Hadir');

                    $gajiDasar = (float)($profil['gaji_bulanan'] ?? 0);
                    $dendaAbsen = ($terlambat && $gajiDasar > 0)
                        ? round($gajiDasar * 0.02, 2)
                        : 0;

                    if ($absenLama) {
                        $stmtSimpan = mysqli_prepare(
                            $conn,
                            'UPDATE absensi
                             SET jam_masuk = ?, status = ?,
                                 menit_terlambat = ?, denda = ?,
                                 updated_at = NOW()
                             WHERE id_absensi = ?
                               AND id_user = ?
                               AND id_jadwal = ?'
                        );

                        if ($stmtSimpan) {
                            mysqli_stmt_bind_param(
                                $stmtSimpan,
                                'ssidiii',
                                $waktuAbsensi,
                                $statusAbsen,
                                $menitTelat,
                                $dendaAbsen,
                                $absenLama['id_absensi'],
                                $idUser,
                                $idJadwalAbsen
                            );
                        }

                    } else {
                        $stmtSimpan = mysqli_prepare(
                            $conn,
                            'INSERT INTO absensi
                             (id_user, id_jadwal, tanggal, jam_masuk,
                              status, menit_terlambat, denda,
                              created_at, updated_at)
                             VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
                        );

                        if ($stmtSimpan) {
                            mysqli_stmt_bind_param(
                                $stmtSimpan,
                                'iisssid',
                                $idUser,
                                $idJadwalAbsen,
                                $tanggalAbsensi,
                                $waktuAbsensi,
                                $statusAbsen,
                                $menitTelat,
                                $dendaAbsen
                            );
                        }
                    }

                    if (isset($stmtSimpan) && $stmtSimpan) {
                        if (mysqli_stmt_execute($stmtSimpan)) {
                            $pesanAbsensi = $terlambat
                                ? "Absen masuk berhasil. Anda terlambat {$menitTelat} menit."
                                : 'Absen masuk berhasil dicatat.';

                            $jenisPesanAbsensi = 'success';
                        } else {
                            $pesanAbsensi = 'Gagal menyimpan absensi: ' .
                                mysqli_stmt_error($stmtSimpan);
                            $jenisPesanAbsensi = 'error';
                        }

                        mysqli_stmt_close($stmtSimpan);
                    } else {
                        $pesanAbsensi = 'Query penyimpanan absensi gagal disiapkan.';
                        $jenisPesanAbsensi = 'error';
                    }
                }

            } elseif ($aksiAbsensi === 'pulang') {

                if (!$absenLama || empty($absenLama['jam_masuk'])) {
                    $pesanAbsensi = 'Anda harus absen masuk terlebih dahulu.';
                    $jenisPesanAbsensi = 'error';

                } elseif (!empty($absenLama['jam_pulang'])) {
                    $pesanAbsensi = 'Absen pulang sudah tercatat.';
                    $jenisPesanAbsensi = 'error';

                } else {
                    $idAbsensiLama = (int)$absenLama['id_absensi'];

                    $stmtPulang = mysqli_prepare(
                        $conn,
                        'UPDATE absensi
                         SET jam_pulang = ?, updated_at = NOW()
                         WHERE id_absensi = ?
                           AND id_user = ?
                           AND id_jadwal = ?
                           AND jam_pulang IS NULL'
                    );

                    if ($stmtPulang) {
                        mysqli_stmt_bind_param(
                            $stmtPulang,
                            'siii',
                            $waktuAbsensi,
                            $idAbsensiLama,
                            $idUser,
                            $idJadwalAbsen
                        );

                        if (mysqli_stmt_execute($stmtPulang) &&
                            mysqli_stmt_affected_rows($stmtPulang) > 0) {
                            $pesanAbsensi = 'Absen pulang berhasil dicatat.';
                            $jenisPesanAbsensi = 'success';
                        } else {
                            $pesanAbsensi = 'Gagal menyimpan absen pulang.';
                            $jenisPesanAbsensi = 'error';
                        }

                        mysqli_stmt_close($stmtPulang);
                    }
                }

            } else {
                $pesanAbsensi = 'Aksi absensi tidak dikenal.';
                $jenisPesanAbsensi = 'error';
            }
        }
    }
}

/* Ambil ulang absensi hari ini. */
$absensiHariIni = null;
$totalDendaBulan = 0;
$riwayatAbsensi = [];

if ($absensiTersedia) {
    $stmtHari = mysqli_prepare(
        $conn,
        'SELECT a.*, j.id_jadwal, s.nama_shift,
                s.jam_mulai, s.jam_selesai
         FROM absensi a
         INNER JOIN jadwal_shift j ON j.id_jadwal = a.id_jadwal
         INNER JOIN shift s ON s.id_shift = j.id_shift
         WHERE a.id_user = ? AND a.tanggal = ?
         LIMIT 1'
    );

    if ($stmtHari) {
        mysqli_stmt_bind_param($stmtHari, 'is', $idUser, $hariIni);
        mysqli_stmt_execute($stmtHari);
        $absensiHariIni = mysqli_fetch_assoc(
            mysqli_stmt_get_result($stmtHari)
        );
        mysqli_stmt_close($stmtHari);
    }

    $awalBulan = date('Y-m-01');
    $akhirBulan = date('Y-m-t');

    $stmtDenda = mysqli_prepare(
        $conn,
        'SELECT COALESCE(SUM(denda), 0) AS total_denda
         FROM absensi
         WHERE id_user = ? AND tanggal >= ? AND tanggal <= ?'
    );

    if ($stmtDenda) {
        mysqli_stmt_bind_param(
            $stmtDenda,
            'iss',
            $idUser,
            $awalBulan,
            $akhirBulan
        );

        mysqli_stmt_execute($stmtDenda);
        $rowDenda = mysqli_fetch_assoc(
            mysqli_stmt_get_result($stmtDenda)
        );

        $totalDendaBulan = (float)($rowDenda['total_denda'] ?? 0);
        mysqli_stmt_close($stmtDenda);
    }

    $stmtRiwayat = mysqli_prepare(
        $conn,
        'SELECT a.tanggal, a.jam_masuk, a.jam_pulang,
                a.status, a.menit_terlambat, a.denda,
                s.nama_shift
         FROM absensi a
         INNER JOIN jadwal_shift j ON j.id_jadwal = a.id_jadwal
         INNER JOIN shift s ON s.id_shift = j.id_shift
         WHERE a.id_user = ?
         ORDER BY a.tanggal DESC
         LIMIT 10'
    );

    if ($stmtRiwayat) {
        mysqli_stmt_bind_param($stmtRiwayat, 'i', $idUser);
        mysqli_stmt_execute($stmtRiwayat);
        $result = mysqli_stmt_get_result($stmtRiwayat);

        while ($row = mysqli_fetch_assoc($result)) {
            $riwayatAbsensi[] = $row;
        }

        mysqli_stmt_close($stmtRiwayat);
    }
}

$hari = [
    1=>'Senin', 'Selasa', 'Rabu', 'Kamis',
    'Jumat', 'Sabtu', 'Minggu'
];

$namaHariIni = $hari[(int)date('N')];
$namaJabatan = $profil['jabatan'] ?? 'Karyawan';
$targetHari = isset($profil['target_hari_bulanan'])
    ? (int)$profil['target_hari_bulanan']
    : null;
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard Karyawan - Toko Malaya</title>

<style>
:root {
    --blue:#1677d2;
    --navy:#15324f;
    --bg:#f4f7fb;
    --text:#172033;
    --muted:#64748b;
    --line:#e2e8f0;
    --green:#15803d;
}
* { box-sizing:border-box; }
body {
    margin:0;
    background:var(--bg);
    color:var(--text);
    font-family:Inter,"Segoe UI",Arial,sans-serif;
}
a { color:inherit; }
.topbar {
    min-height:76px;
    background:#fff;
    border-bottom:1px solid var(--line);
    display:flex;
    align-items:center;
    justify-content:space-between;
    padding:12px max(20px,calc((100% - 1160px)/2));
    gap:18px;
}
.brand {
    display:flex;
    align-items:center;
    gap:12px;
    font-weight:800;
    color:var(--navy);
    text-decoration:none;
}
.brand-logo {
    width:44px;
    height:44px;
    background:var(--blue);
    border-radius:12px;
    display:flex;
    align-items:center;
    justify-content:center;
    overflow:hidden;
}
.brand-logo img {
    width:36px;
    height:36px;
    object-fit:contain;
}
.brand-text { display:flex; flex-direction:column; gap:2px; }
.brand-text strong { font-size:18px; color:#102b50; }
.brand-text span { font-size:13px; color:var(--muted); }
.user-area { display:flex; align-items:center; gap:14px; font-size:14px; }
.logout {
    padding:9px 13px;
    border-radius:9px;
    color:#b91c1c;
    background:#fef2f2;
    text-decoration:none;
    font-weight:700;
}
.container {
    max-width:1160px;
    margin:30px auto;
    padding:0 20px;
}
.welcome {
    background:linear-gradient(120deg,#155e9a,#1677d2);
    color:white;
    padding:28px;
    border-radius:18px;
    margin-bottom:20px;
    box-shadow:0 12px 30px #1677d21c;
}
.welcome h1 { font-size:26px; margin:0 0 9px; }
.welcome p { margin:0; color:#e5f1ff; line-height:1.6; }
.today-label {
    display:inline-block;
    margin-top:17px;
    background:#ffffff20;
    border:1px solid #ffffff35;
    padding:8px 11px;
    border-radius:9px;
    font-size:13px;
}
.stats {
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:16px;
    margin-bottom:20px;
}
.stat,.card {
    background:white;
    border:1px solid var(--line);
    border-radius:15px;
}
.stat { padding:20px; }
.stat .label { font-size:13px; color:var(--muted); margin-bottom:10px; }
.stat .value { font-size:22px; font-weight:800; color:var(--navy); }
.stat .note { font-size:12px; color:var(--muted); margin-top:7px; }
.layout {
    display:grid;
    grid-template-columns:minmax(0,1.1fr) minmax(0,1fr);
    gap:18px;
    align-items:start;
}
.card { padding:22px; margin-bottom:18px; }
.card-head {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    margin-bottom:16px;
}
.card h2 { font-size:17px; margin:0; }
.subtle { color:var(--muted); font-size:13px; }
.shift-box {
    border:1px solid #d8e8fb;
    background:#f4f9ff;
    border-radius:13px;
    padding:18px;
}
.shift-title {
    font-weight:800;
    font-size:20px;
    color:var(--navy);
    margin-bottom:8px;
}
.shift-time { font-size:15px; color:#1d4ed8; font-weight:700; }
.pill {
    display:inline-block;
    padding:6px 10px;
    border-radius:99px;
    font-size:12px;
    font-weight:800;
    background:#dcfce7;
    color:#166534;
}
.note {
    margin-top:12px;
    padding:11px 12px;
    background:#f8fafc;
    border-radius:9px;
    color:var(--muted);
    font-size:13px;
    line-height:1.55;
}
.table-wrap { overflow-x:auto; }
table { width:100%; border-collapse:collapse; min-width:460px; }
th,td {
    text-align:left;
    padding:12px 10px;
    border-bottom:1px solid var(--line);
    font-size:13px;
}
th {
    font-size:11px;
    text-transform:uppercase;
    letter-spacing:.04em;
    color:var(--muted);
    background:#f8fafc;
}
tbody tr:last-child td { border-bottom:0; }
.empty {
    text-align:center;
    padding:25px 10px;
    color:var(--muted);
    font-size:14px;
}
.info-list {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:12px;
}
.info-item {
    padding:14px;
    border-radius:11px;
    background:#f8fafc;
}
.info-item span {
    display:block;
    color:var(--muted);
    font-size:12px;
    margin-bottom:6px;
}
.info-item strong { font-size:15px; }
.alert {
    padding:14px 16px;
    border-radius:11px;
    background:#fff7ed;
    color:#9a3412;
    margin-bottom:18px;
    line-height:1.5;
    font-size:14px;
}
.btn {
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding:11px 15px;
    border:0;
    border-radius:9px;
    color:white;
    background:var(--blue);
    font-weight:800;
    text-decoration:none;
    cursor:pointer;
}
.btn-green { background:var(--green); }
.btn-disabled { background:#94a3b8 !important; cursor:not-allowed !important; }
.footer {
    text-align:center;
    color:#94a3b8;
    font-size:12px;
    padding:10px 0 28px;
}
@media(max-width:780px) {
    .topbar { padding:12px 16px; }
    .user-area { gap:8px; }
    .user-name { display:none; }
    .brand-text strong { font-size:16px; }
    .brand-text span { font-size:12px; }
    .container { margin:20px auto; padding:0 14px; }
    .welcome { padding:22px; }
    .welcome h1 { font-size:22px; }
    .stats { grid-template-columns:1fr; }
    .layout { grid-template-columns:1fr; }
    .card { padding:17px; }
    .stat { padding:16px; }
    .stat .value { font-size:20px; }
}
</style>
</head>

<body>
<header class="topbar">
    <a class="brand" href="dashboard.php">
        <span class="brand-logo">
            <img src="../assets/SembakoKu (3).png" alt="Logo Toko Malaya">
        </span>
        <span class="brand-text">
            <strong>Toko Malaya</strong>
            <span>Portal Karyawan</span>
        </span>
    </a>

    <div class="user-area">
        <span class="user-name">Halo, <strong><?= e($nama) ?></strong></span>
        <a class="logout" href="../auth/logout.php"
           onclick="return confirm('Yakin ingin keluar dari akun?')">
            Keluar
        </a>
    </div>
</header>

<main class="container">

    <section class="welcome">
        <h1>Selamat datang, <?= e($nama) ?> 👋</h1>
        <p>Berikut ringkasan jadwal kerja dan informasi karyawan Anda.</p>
        <span class="today-label">
            📅 <?= e($namaHariIni) ?>, <?= e(tanggalIndonesia($hariIni)) ?>
        </span>
    </section>

    <?php if ($pesanSetup): ?>
        <div class="alert">
            <strong>Jadwal belum dapat ditampilkan.</strong><br>
            Tabel <?= e(implode(' dan ', $pesanSetup)) ?> belum ditemukan.
            Pastikan migrasi jadwal shift sudah dijalankan.
        </div>
    <?php endif; ?>

    <section class="stats">
        <article class="stat">
            <div class="label">📅 Jadwal mulai hari ini</div>
            <div class="value"><?= $totalJadwal ?></div>
            <div class="note">Jadwal hari ini dan mendatang</div>
        </article>

        <article class="stat">
            <div class="label">🧑‍💼 Jabatan</div>
            <div class="value"><?= e($namaJabatan) ?></div>
            <div class="note">Berdasarkan profil karyawan</div>
        </article>

        <article class="stat">
            <div class="label">🎯 Target hari kerja</div>
            <div class="value">
                <?= $targetHari !== null ? $targetHari . ' hari/bulan' : 'Belum diatur' ?>
            </div>
            <div class="note">Target dari profil karyawan</div>
        </article>
    </section>

    <div class="layout">
        <div>
            <section class="card">
                <div class="card-head">
                    <h2>🕘 Shift Hari Ini</h2>
                    <span class="subtle"><?= e(date('d/m/Y')) ?></span>
                </div>

                <?php if ($pesanSetup): ?>
                    <div class="empty">Data jadwal belum tersedia.</div>
                <?php elseif ($jadwalHariIni): ?>
                    <div class="shift-box">
                        <div class="shift-title"><?= e($jadwalHariIni['nama_shift']) ?></div>
                        <div class="shift-time">
                            <?= e(jam($jadwalHariIni['jam_mulai'])) ?>
                            – <?= e(jam($jadwalHariIni['jam_selesai'])) ?>
                        </div>
                        <p><span class="pill">Jadwal tersedia</span></p>

                        <?php if (!empty($jadwalHariIni['catatan'])): ?>
                            <div class="note">
                                <strong>Catatan admin:</strong>
                                <?= e($jadwalHariIni['catatan']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="empty">Belum ada shift untuk hari ini.</div>
                    <div class="note">
                        Hubungi admin jika seharusnya Anda mendapat jadwal.
                    </div>
                <?php endif; ?>
            </section>

            <section class="card">
                <div class="card-head">
                    <h2>📆 Jadwal Mendatang</h2>
                    <span class="subtle">Maksimal 7 jadwal</span>
                </div>

                <?php if ($pesanSetup): ?>
                    <div class="empty">Tabel jadwal belum tersedia.</div>
                <?php elseif (!$jadwalMendatang): ?>
                    <div class="empty">Belum ada jadwal mendatang.</div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Shift</th>
                                    <th>Jam</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($jadwalMendatang as $j): ?>
                                <tr>
                                    <td><strong><?= e(tanggalIndonesia($j['tanggal'])) ?></strong></td>
                                    <td>
                                        <?= e($j['nama_shift']) ?>
                                        <?php if (!empty($j['catatan'])): ?>
                                            <div class="subtle"><?= e($j['catatan']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= e(jam($j['jam_mulai'])) ?>–<?= e(jam($j['jam_selesai'])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </div>

        <div>
            <section class="card">
                <div class="card-head">
                    <h2>👤 Informasi Karyawan</h2>
                </div>

                <div class="info-list">
                    <div class="info-item">
                        <span>Nama</span>
                        <strong><?= e($nama) ?></strong>
                    </div>
                    <div class="info-item">
                        <span>Jabatan</span>
                        <strong><?= e($namaJabatan) ?></strong>
                    </div>
                    <div class="info-item" style="grid-column:1/-1">
                        <span>Email</span>
                        <strong><?= e($email !== '' ? $email : 'Belum tersedia') ?></strong>
                    </div>
                </div>

                <div class="note">
                    Jika informasi jabatan atau target hari kerja belum tampil,
                    hubungi admin Toko Malaya.
                </div>
            </section>

            <!-- MENU PENGAJUAN CUTI DAN IZIN -->
            <section class="card">
                <div class="card-head">
                    <h2>📝 Pengajuan Cuti / Izin</h2>
                </div>

                <p class="subtle" style="line-height:1.7">
                    Ajukan cuti atau izin jika Anda tidak dapat masuk kerja.
                    Pengajuan akan diperiksa dan disetujui oleh admin.
                </p>

                <a href="pengajuan_cuti.php" class="btn">
                    Ajukan Cuti / Izin
                </a>
            </section>

            <section class="card">
                <div class="card-head">
                    <h2>🕒 Absensi Hari Ini</h2>
                    <span class="subtle">Toleransi 10 menit</span>
                </div>

                <?php if (!$absensiTersedia): ?>
                    <div class="alert">
                        Tabel absensi belum tersedia atau strukturnya belum sesuai.
                        Periksa migrasi database absensi.
                    </div>

                <?php elseif (!$jadwalHariIni): ?>
                    <div class="empty">
                        Belum ada jadwal shift untuk hari ini.
                    </div>

                <?php else: ?>
                    <div class="shift-box" style="margin-bottom:14px">
                        <div class="shift-title"><?= e($jadwalHariIni['nama_shift']) ?></div>
                        <div class="shift-time">
                            <?= e(jam($jadwalHariIni['jam_mulai'])) ?>
                            – <?= e(jam($jadwalHariIni['jam_selesai'])) ?>
                        </div>
                        <div class="note">
                            Terlambat jika absen lebih dari 10 menit setelah jam mulai.
                        </div>
                    </div>

                    <?php if ($pesanAbsensi !== ''): ?>
                        <div class="alert"
                             style="<?=
                                $jenisPesanAbsensi === 'success'
                                ? 'background:#f0fdf4;color:#166534'
                                : ($jenisPesanAbsensi === 'error'
                                    ? 'background:#fff1f2;color:#9f1239'
                                    : '')
                             ?>">
                            <?= e($pesanAbsensi) ?>
                        </div>
                    <?php endif; ?>

                    <div class="info-list" style="margin-bottom:14px">
                        <div class="info-item">
                            <span>Jam masuk</span>
                            <strong><?= e(jam($absensiHariIni['jam_masuk'] ?? null)) ?></strong>
                        </div>
                        <div class="info-item">
                            <span>Jam pulang</span>
                            <strong><?= e(jam($absensiHariIni['jam_pulang'] ?? null)) ?></strong>
                        </div>
                        <div class="info-item">
                            <span>Status</span>
                            <strong><?= e($absensiHariIni['status'] ?? 'Belum absen') ?></strong>
                        </div>
                        <div class="info-item">
                            <span>Denda hari ini</span>
                            <strong>
                                Rp <?= number_format((float)($absensiHariIni['denda'] ?? 0), 0, ',', '.') ?>
                            </strong>
                        </div>
                    </div>

                    <div style="display:flex;gap:10px;flex-wrap:wrap">
                        <form method="post">
                            <input type="hidden" name="csrf_absensi" value="<?= e($csrfAbsensi) ?>">
                            <input type="hidden" name="aksi_absensi" value="masuk">
                            <button type="submit"
                                class="btn <?= !empty($absensiHariIni['jam_masuk']) ? 'btn-disabled' : '' ?>"
                                <?= !empty($absensiHariIni['jam_masuk']) ? 'disabled' : '' ?>>
                                Absen Masuk
                            </button>
                        </form>

                        <form method="post">
                            <input type="hidden" name="csrf_absensi" value="<?= e($csrfAbsensi) ?>">
                            <input type="hidden" name="aksi_absensi" value="pulang">
                            <button type="submit"
                                class="btn btn-green <?= (empty($absensiHariIni['jam_masuk']) || !empty($absensiHariIni['jam_pulang'])) ? 'btn-disabled' : '' ?>"
                                <?= (empty($absensiHariIni['jam_masuk']) || !empty($absensiHariIni['jam_pulang'])) ? 'disabled' : '' ?>>
                                Absen Pulang
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </section>

            <section class="card">
                <div class="card-head">
                    <h2>💸 Ringkasan Denda Bulan Ini</h2>
                </div>

                <div class="shift-title">
                    Rp <?= number_format($totalDendaBulan, 0, ',', '.') ?>
                </div>

                <p class="subtle" style="line-height:1.6;margin:0">
                    Total denda keterlambatan bulan <?= e(date('F Y')) ?>.
                </p>
            </section>

            <section class="card">
                <div class="card-head">
                    <h2>📋 Riwayat Absensi</h2>
                    <span class="subtle">10 terakhir</span>
                </div>

                <?php if (!$absensiTersedia): ?>
                    <div class="empty">Riwayat absensi belum tersedia.</div>
                <?php elseif (!$riwayatAbsensi): ?>
                    <div class="empty">Belum ada riwayat absensi.</div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table style="min-width:540px">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Shift</th>
                                    <th>Masuk/Pulang</th>
                                    <th>Status</th>
                                    <th>Denda</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($riwayatAbsensi as $riwayat): ?>
                                <tr>
                                    <td><?= e(tanggalIndonesia($riwayat['tanggal'])) ?></td>
                                    <td><?= e($riwayat['nama_shift']) ?></td>
                                    <td>
                                        <?= e(jam($riwayat['jam_masuk'])) ?>
                                        /
                                        <?= e(jam($riwayat['jam_pulang'])) ?>
                                    </td>
                                    <td>
                                        <?= e($riwayat['status']) ?>
                                        <?php if ((int)$riwayat['menit_terlambat'] > 10): ?>
                                            <div class="subtle">
                                                <?= (int)$riwayat['menit_terlambat'] ?> menit
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        Rp <?= number_format((float)$riwayat['denda'], 0, ',', '.') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <section class="card">
                <div class="card-head">
                    <h2>🔐 Keamanan Akun</h2>
                </div>
                <p class="subtle" style="line-height:1.7;margin:0">
                    Jangan bagikan password kepada orang lain. Jika akun
                    tidak bisa digunakan atau informasi jadwal tidak sesuai,
                    hubungi admin Toko Malaya.
                </p>
            </section>
        </div>
    </div>

    <div class="footer">
        © <?= date('Y') ?> Toko Malaya · Portal Karyawan
    </div>
</main>
</body>
</html>