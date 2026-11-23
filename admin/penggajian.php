<?php
date_default_timezone_set('Asia/Jakarta');
session_start();
require_once "../config/database.php";

if (!isset($_SESSION['id_user'])) {
    header("Location: ../auth/login.php");
    exit;
}

if (!in_array($_SESSION['role'] ?? '', ['admin', 'pemilik'], true)) {
    header("Location: ../index.php");
    exit;
}

function e($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function rp($v): string {
    return 'Rp ' . number_format((float)$v, 0, ',', '.');
}

function hasTable(mysqli $c, string $t): bool {
    $t = mysqli_real_escape_string($c, $t);
    $r = mysqli_query($c, "SHOW TABLES LIKE '$t'");
    return $r && mysqli_num_rows($r) > 0;
}

function hasColumn(mysqli $c, string $t, string $f): bool {
    $t = preg_replace('/[^a-zA-Z0-9_]/', '', $t);
    $f = mysqli_real_escape_string($c, $f);
    $r = mysqli_query($c, "SHOW COLUMNS FROM `$t` LIKE '$f'");
    return $r && mysqli_num_rows($r) > 0;
}

/*
|--------------------------------------------------------------------------
| PERHITUNGAN GAJI KARYAWAN
|--------------------------------------------------------------------------
*/

function hitungGaji(
    mysqli $conn,
    int $uid,
    string $awal,
    string $akhir,
    float $gaji
): array {

    $hasil = [
        'total_jadwal' => 0,
        'total_hadir' => 0,
        'total_terlambat' => 0,
        'total_izin' => 0,
        'total_cuti' => 0,
        'total_tidak_hadir' => 0,
        'total_jam_lembur' => 0,
        'total_upah_lembur' => 0,
        'total_potongan_izin' => 0,
        'total_potongan_tidak_hadir' => 0,
        'total_denda' => 0,
        'gaji_bersih' => 0
    ];

    /*
    |--------------------------------------------------------------------------
    | AMBIL PENGAJUAN CUTI DAN IZIN YANG DISETUJUI
    |--------------------------------------------------------------------------
    */

    $pengajuan = [];

    $sql = "SELECT
                jenis_pengajuan,
                tanggal_mulai,
                tanggal_selesai
            FROM pengajuan_cuti_izin
            WHERE id_user = ?
              AND status = 'Disetujui'
              AND tanggal_mulai <= ?
              AND tanggal_selesai >= ?";

    $st = mysqli_prepare($conn, $sql);

    if (!$st) {
        throw new Exception('Gagal membaca pengajuan cuti dan izin.');
    }

    mysqli_stmt_bind_param($st, 'iss', $uid, $akhir, $awal);
    mysqli_stmt_execute($st);

    $rs = mysqli_stmt_get_result($st);

    while ($p = mysqli_fetch_assoc($rs)) {
        $mulai = max($awal, $p['tanggal_mulai']);
        $selesai = min($akhir, $p['tanggal_selesai']);

        $tgl = new DateTime($mulai);
        $batas = new DateTime($selesai);

        while ($tgl <= $batas) {
            $tanggal = $tgl->format('Y-m-d');
            $pengajuan[$tanggal] = $p['jenis_pengajuan'];
            $tgl->modify('+1 day');
        }
    }

    mysqli_stmt_close($st);

    /*
    |--------------------------------------------------------------------------
    | AMBIL JADWAL, SHIFT, DAN ABSENSI
    |--------------------------------------------------------------------------
    */

    $sql = "SELECT
                j.id_jadwal,
                j.tanggal,
                s.jam_mulai,
                s.jam_selesai,
                s.toleransi_menit,
                a.jam_masuk,
                a.jam_pulang,
                a.status_masuk,
                a.status AS status_absensi,
                a.menit_terlambat,
                a.denda
            FROM jadwal_shift j
            JOIN shift s ON s.id_shift = j.id_shift
            LEFT JOIN absensi a ON a.id_jadwal = j.id_jadwal
            WHERE j.id_user = ?
              AND j.tanggal BETWEEN ? AND ?
            ORDER BY j.tanggal";

    $st = mysqli_prepare($conn, $sql);

    if (!$st) {
        throw new Exception('Gagal membaca jadwal karyawan: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($st, 'iss', $uid, $awal, $akhir);
    mysqli_stmt_execute($st);

    $rs = mysqli_stmt_get_result($st);

    while ($r = mysqli_fetch_assoc($rs)) {

        $hasil['total_jadwal']++;

        $tanggal = $r['tanggal'];
        $masuk = $r['jam_masuk'];
        $pulang = $r['jam_pulang'];

        /*
        |--------------------------------------------------------------------------
        | KARYAWAN TIDAK MEMILIKI JAM MASUK
        |--------------------------------------------------------------------------
        */

        if (empty($masuk)) {

            if (($pengajuan[$tanggal] ?? '') === 'cuti') {
                $hasil['total_cuti']++;
            } elseif (($pengajuan[$tanggal] ?? '') === 'izin') {
                $hasil['total_izin']++;
            } else {
                $hasil['total_tidak_hadir']++;
            }

            continue;
        }

        /*
        |--------------------------------------------------------------------------
        | KARYAWAN HADIR
        |--------------------------------------------------------------------------
        */

        $hasil['total_hadir']++;

        /*
         * PERBAIKAN:
         * Keterlambatan diperiksa melalui beberapa kolom.
         * Ini diperlukan karena status_masuk bisa NULL,
         * sedangkan kolom status berisi "Terlambat".
         */

        $statusMasuk = strtolower(trim((string)($r['status_masuk'] ?? '')));
        $statusAbsensi = strtolower(trim((string)($r['status_absensi'] ?? '')));
        $menitTerlambat = (int)($r['menit_terlambat'] ?? 0);

        if (
            $statusMasuk === 'terlambat'
            || $statusAbsensi === 'terlambat'
            || $menitTerlambat > 0
        ) {
            $hasil['total_terlambat']++;
        }

        /*
        |--------------------------------------------------------------------------
        | HITUNG JAM LEMBUR
        |--------------------------------------------------------------------------
        | Lembur dihitung otomatis berdasarkan jam pulang.
        | Pembulatan ke bawah, hanya jam penuh.
        */

        if (!empty($pulang) && !empty($r['jam_selesai'])) {

            $jamMulai = strtotime($r['jam_mulai']);
            $jamSelesai = strtotime($r['jam_selesai']);
            $jamPulang = strtotime($pulang);

            // Shift melewati tengah malam.
            if ($jamSelesai < $jamMulai) {
                $jamSelesai += 86400;
            }

            if ($jamPulang < $jamMulai) {
                $jamPulang += 86400;
            }

            $selisih = $jamPulang - $jamSelesai;

            if ($selisih > 0) {
                $jamLembur = (int)floor($selisih / 3600);
                $hasil['total_jam_lembur'] += $jamLembur;
            }
        }
    }

    mysqli_stmt_close($st);

    /*
    |--------------------------------------------------------------------------
    | PERHITUNGAN NOMINAL GAJI
    |--------------------------------------------------------------------------
    */

    // Denda keterlambatan: 2% dari gaji per kejadian.
    $hasil['total_denda'] =
        $hasil['total_terlambat'] * $gaji * 0.02;

    // Potongan izin: 10% dari gaji per hari kerja.
    $hasil['total_potongan_izin'] =
        $hasil['total_izin'] * $gaji * 0.10;

    // Potongan tidak hadir: 10% dari gaji per hari kerja.
    $hasil['total_potongan_tidak_hadir'] =
        $hasil['total_tidak_hadir'] * $gaji * 0.10;

    // Upah lembur: 2% dari gaji per jam penuh.
    $hasil['total_upah_lembur'] =
        $hasil['total_jam_lembur'] * $gaji * 0.02;

    // Gaji bersih.
    $hasil['gaji_bersih'] = max(
        0,
        $gaji
        + $hasil['total_upah_lembur']
        - $hasil['total_potongan_izin']
        - $hasil['total_potongan_tidak_hadir']
        - $hasil['total_denda']
    );

    return $hasil;
}

/*
|--------------------------------------------------------------------------
| PENGATURAN PERIODE
|--------------------------------------------------------------------------
*/

$id_admin = (int)$_SESSION['id_user'];
$nama_admin = $_SESSION['nama'] ?? 'Admin';

$bulan = filter_input(INPUT_GET, 'bulan', FILTER_VALIDATE_INT);
$tahun = filter_input(INPUT_GET, 'tahun', FILTER_VALIDATE_INT);

$bulan = ($bulan >= 1 && $bulan <= 12)
    ? $bulan : (int)date('n');

$tahun = ($tahun >= 2020 && $tahun <= 2100)
    ? $tahun : (int)date('Y');

$awal = sprintf('%04d-%02d-01', $tahun, $bulan);
$akhir = date('Y-m-t', strtotime($awal));

$namaBulan = [
    1 => 'Januari',
    'Februari',
    'Maret',
    'April',
    'Mei',
    'Juni',
    'Juli',
    'Agustus',
    'September',
    'Oktober',
    'November',
    'Desember'
];

if (empty($_SESSION['csrf_penggajian'])) {
    $_SESSION['csrf_penggajian'] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION['csrf_penggajian'];
$pesan = '';
$jenis = 'success';

$siap = true;

foreach ([
    'users',
    'profil_karyawan',
    'jadwal_shift',
    'shift',
    'absensi',
    'pengajuan_cuti_izin',
    'penggajian'
] as $t) {
    if (!hasTable($conn, $t)) {
        $siap = false;
        break;
    }
}

/*
|--------------------------------------------------------------------------
| PERIKSA KOLOM DATABASE
|--------------------------------------------------------------------------
*/

$kolomSiap = $siap;

if ($kolomSiap) {

    $kolomWajib = [
        'total_izin',
        'total_cuti',
        'total_tidak_hadir',
        'total_jam_lembur',
        'total_upah_lembur',
        'total_potongan_izin',
        'total_potongan_tidak_hadir'
    ];

    foreach ($kolomWajib as $kolom) {
        if (!hasColumn($conn, 'penggajian', $kolom)) {
            $kolomSiap = false;
            break;
        }
    }

    $kolomAbsensi = [
        'status',
        'status_masuk',
        'menit_terlambat',
        'denda'
    ];

    foreach ($kolomAbsensi as $kolom) {
        if (!hasColumn($conn, 'absensi', $kolom)) {
            $kolomSiap = false;
            break;
        }
    }
}

/*
|--------------------------------------------------------------------------
| BUAT PROFIL KARYAWAN OTOMATIS
|--------------------------------------------------------------------------
*/

if ($siap) {
    mysqli_query($conn, "
        INSERT INTO profil_karyawan (id_user, gaji_bulanan)
        SELECT u.id_user, 0
        FROM users u
        LEFT JOIN profil_karyawan p ON p.id_user = u.id_user
        WHERE u.role = 'karyawan'
          AND p.id_user IS NULL
    ");
}

/*
|--------------------------------------------------------------------------
| UBAH GAJI POKOK
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['aksi'] ?? '') === 'ubah_gaji'
) {

    $token = $_POST['csrf_token'] ?? '';
    $uid = filter_var($_POST['id_user'] ?? null, FILTER_VALIDATE_INT);
    $gajiInput = trim((string)($_POST['gaji_bulanan'] ?? ''));

    if (
        !is_string($token)
        || !hash_equals($csrf, $token)
        || !$uid
        || $gajiInput === ''
        || !is_numeric($gajiInput)
        || (float)$gajiInput < 0
        || (float)$gajiInput > 999999999999
    ) {
        $pesan = 'Data perubahan gaji tidak valid.';
        $jenis = 'error';

    } else {

        $gajiBaru = (float)$gajiInput;

        $st = mysqli_prepare(
            $conn,
            "SELECT id_user
             FROM users
             WHERE id_user = ?
               AND role = 'karyawan'
             LIMIT 1"
        );

        mysqli_stmt_bind_param($st, 'i', $uid);
        mysqli_stmt_execute($st);

        $pegawai = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
        mysqli_stmt_close($st);

        if (!$pegawai) {

            $pesan = 'Karyawan tidak ditemukan.';
            $jenis = 'error';

        } else {

            $st = mysqli_prepare(
                $conn,
                "UPDATE profil_karyawan
                 SET gaji_bulanan = ?
                 WHERE id_user = ?"
            );

            mysqli_stmt_bind_param($st, 'di', $gajiBaru, $uid);

            if (mysqli_stmt_execute($st)) {
                $pesan = 'Gaji pokok berhasil diperbarui.';
            } else {
                $pesan = 'Gagal memperbarui gaji pokok.';
                $jenis = 'error';
            }

            mysqli_stmt_close($st);
        }
    }
}

/*
|--------------------------------------------------------------------------
| PROSES PEMBAYARAN GAJI
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['aksi'] ?? '') === 'bayar'
) {

    $token = $_POST['csrf_token'] ?? '';
    $uid = filter_var($_POST['id_user'] ?? null, FILTER_VALIDATE_INT);

    if (
        !is_string($token)
        || !hash_equals($csrf, $token)
        || !$siap
        || !$kolomSiap
        || !$uid
        || (int)($_POST['bulan'] ?? 0) !== $bulan
        || (int)($_POST['tahun'] ?? 0) !== $tahun
    ) {

        $pesan = 'Permintaan tidak valid. Muat ulang halaman.';
        $jenis = 'error';

    } else {

        mysqli_begin_transaction($conn);

        try {

            // Periksa apakah periode sudah dibayar.
            $st = mysqli_prepare(
                $conn,
                "SELECT id_penggajian
                 FROM penggajian
                 WHERE id_user = ?
                   AND bulan = ?
                   AND tahun = ?
                 LIMIT 1
                 FOR UPDATE"
            );

            mysqli_stmt_bind_param($st, 'iii', $uid, $bulan, $tahun);
            mysqli_stmt_execute($st);

            $dataLama = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
            mysqli_stmt_close($st);

            if ($dataLama) {
                throw new Exception(
                    'Data penggajian periode ini sudah tercatat. Tidak dapat dibayar ulang.'
                );
            }

            // Ambil data karyawan.
            $st = mysqli_prepare(
                $conn,
                "SELECT
                    u.id_user,
                    p.gaji_bulanan
                 FROM users u
                 JOIN profil_karyawan p ON p.id_user = u.id_user
                 WHERE u.id_user = ?
                   AND u.role = 'karyawan'
                   AND p.aktif = 1
                 LIMIT 1"
            );

            mysqli_stmt_bind_param($st, 'i', $uid);
            mysqli_stmt_execute($st);

            $pegawai = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
            mysqli_stmt_close($st);

            if (!$pegawai) {
                throw new Exception('Data karyawan tidak ditemukan atau tidak aktif.');
            }

            $gaji = (float)$pegawai['gaji_bulanan'];

            // Hitung seluruh komponen gaji.
            $h = hitungGaji(
                $conn,
                $uid,
                $awal,
                $akhir,
                $gaji
            );

            $sql = "INSERT INTO penggajian (
                id_user,
                bulan,
                tahun,
                gaji_pokok,
                total_jadwal,
                total_hadir,
                total_terlambat,
                total_izin,
                total_cuti,
                total_tidak_hadir,
                total_jam_lembur,
                total_upah_lembur,
                total_potongan_izin,
                total_potongan_tidak_hadir,
                total_denda,
                gaji_bersih,
                status_pembayaran,
                tanggal_bayar,
                dibayar_oleh
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                'Dibayar', NOW(), ?
            )";

            $st = mysqli_prepare($conn, $sql);

            if (!$st) {
                throw new Exception('Gagal menyiapkan penyimpanan penggajian.');
            }

            mysqli_stmt_bind_param(
                $st,
                'iiidiiiiiiddddddi',
                $uid,
                $bulan,
                $tahun,
                $gaji,
                $h['total_jadwal'],
                $h['total_hadir'],
                $h['total_terlambat'],
                $h['total_izin'],
                $h['total_cuti'],
                $h['total_tidak_hadir'],
                $h['total_jam_lembur'],
                $h['total_upah_lembur'],
                $h['total_potongan_izin'],
                $h['total_potongan_tidak_hadir'],
                $h['total_denda'],
                $h['gaji_bersih'],
                $id_admin
            );

            if (!mysqli_stmt_execute($st)) {
                throw new Exception('Gagal menyimpan pembayaran gaji.');
            }

            mysqli_stmt_close($st);
            mysqli_commit($conn);

            $pesan = 'Pembayaran gaji berhasil dicatat dan disimpan sebagai riwayat tetap.';

        } catch (Throwable $ex) {

            mysqli_rollback($conn);
            $pesan = $ex->getMessage();
            $jenis = 'error';
        }
    }
}

/*
|--------------------------------------------------------------------------
| AMBIL REKAP PENGGAJIAN
|--------------------------------------------------------------------------
*/

$rows = [];

$sumGaji = 0;
$sumDenda = 0;
$sumLembur = 0;
$sumPotongan = 0;
$sumPaid = 0;

if ($siap && $kolomSiap) {

    $sql = "SELECT
                u.id_user,
                u.nama,
                COALESCE(p.jabatan, 'Karyawan') AS jabatan,
                COALESCE(p.gaji_bulanan, 0) AS gaji_bulanan,

                pg.id_penggajian,
                pg.status_pembayaran,
                pg.gaji_pokok AS gaji_tersimpan,
                pg.total_jadwal AS jadwal_tersimpan,
                pg.total_hadir AS hadir_tersimpan,
                pg.total_terlambat AS terlambat_tersimpan,
                pg.total_izin AS izin_tersimpan,
                pg.total_cuti AS cuti_tersimpan,
                pg.total_tidak_hadir AS tidak_hadir_tersimpan,
                pg.total_jam_lembur AS jam_lembur_tersimpan,
                pg.total_upah_lembur AS upah_lembur_tersimpan,
                pg.total_potongan_izin AS potongan_izin_tersimpan,
                pg.total_potongan_tidak_hadir AS potongan_tidak_hadir_tersimpan,
                pg.total_denda AS denda_tersimpan,
                pg.gaji_bersih AS bersih_tersimpan,
                pg.tanggal_bayar

            FROM users u

            JOIN profil_karyawan p
                ON p.id_user = u.id_user

            LEFT JOIN penggajian pg
                ON pg.id_user = u.id_user
                AND pg.bulan = ?
                AND pg.tahun = ?

            WHERE u.role = 'karyawan'
              AND p.aktif = 1

            ORDER BY u.nama";

    $st = mysqli_prepare($conn, $sql);

    if ($st) {

        mysqli_stmt_bind_param($st, 'ii', $bulan, $tahun);
        mysqli_stmt_execute($st);

        $rs = mysqli_stmt_get_result($st);

        while ($r = mysqli_fetch_assoc($rs)) {

            $gaji = (float)$r['gaji_bulanan'];

            if ($r['status_pembayaran'] === 'Dibayar') {

                // Gunakan snapshot untuk pembayaran yang sudah dilakukan.
                $r['gaji_bulanan_tampil'] = (float)$r['gaji_tersimpan'];

                $r['total_jadwal'] = (int)$r['jadwal_tersimpan'];
                $r['total_hadir'] = (int)$r['hadir_tersimpan'];
                $r['total_terlambat'] = (int)$r['terlambat_tersimpan'];
                $r['total_izin'] = (int)$r['izin_tersimpan'];
                $r['total_cuti'] = (int)$r['cuti_tersimpan'];
                $r['total_tidak_hadir'] = (int)$r['tidak_hadir_tersimpan'];
                $r['total_jam_lembur'] = (int)$r['jam_lembur_tersimpan'];

                $r['total_upah_lembur'] = (float)$r['upah_lembur_tersimpan'];
                $r['total_potongan_izin'] = (float)$r['potongan_izin_tersimpan'];
                $r['total_potongan_tidak_hadir'] = (float)$r['potongan_tidak_hadir_tersimpan'];
                $r['total_denda'] = (float)$r['denda_tersimpan'];
                $r['gaji_bersih'] = (float)$r['bersih_tersimpan'];

            } else {

                // Perhitungan langsung untuk periode yang belum dibayar.
                $h = hitungGaji(
                    $conn,
                    (int)$r['id_user'],
                    $awal,
                    $akhir,
                    $gaji
                );

                foreach ($h as $key => $value) {
                    $r[$key] = $value;
                }

                $r['gaji_bulanan_tampil'] = $gaji;
            }

            $rows[] = $r;

            $sumGaji += $r['gaji_bulanan_tampil'];
            $sumDenda += $r['total_denda'];
            $sumLembur += $r['total_upah_lembur'];

            $sumPotongan +=
                $r['total_potongan_izin']
                + $r['total_potongan_tidak_hadir']
                + $r['total_denda'];

            if ($r['status_pembayaran'] === 'Dibayar') {
                $sumPaid += $r['gaji_bersih'];
            }
        }

        mysqli_stmt_close($st);

    } else {

        $pesan = 'Gagal membaca rekap penggajian: ' . mysqli_error($conn);
        $jenis = 'error';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Penggajian Karyawan - Toko Malaya</title>

<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,sans-serif;background:#f5f7fb;color:#172033}
body.menu-open{overflow:hidden}
a{text-decoration:none;color:inherit}
button,input,select{font-family:inherit}

.topbar{
    height:72px;background:#fff;border-bottom:1px solid #e5e7eb;
    display:flex;align-items:center;justify-content:space-between;
    padding:0 24px;position:sticky;top:0;z-index:900
}
.topbar-left{display:flex;align-items:center;gap:14px}
.hamburger{
    width:42px;height:42px;border:0;border-radius:10px;
    background:#f1f5f9;color:#1e293b;font-size:23px;cursor:pointer
}
.brand{display:flex;align-items:center;gap:10px}
.logo{width:44px;height:44px;border-radius:10px;background:#1677d2;overflow:hidden}
.logo img{width:100%;height:100%;object-fit:contain}
.brandtext{display:flex;flex-direction:column;gap:3px}
.brandtext strong{font-size:17px}
.brandtext span{font-size:12px;color:#64748b}
.user{display:flex;align-items:center;gap:10px}
.avatar{
    width:38px;height:38px;border-radius:50%;background:#e0efff;
    color:#1265a7;display:flex;align-items:center;justify-content:center;font-weight:800
}
.user-info{display:flex;flex-direction:column;gap:3px}
.user-info strong{font-size:13px}
.user-info span{font-size:11px;color:#64748b}

.menu-overlay{
    position:fixed;inset:0;background:rgba(15,23,42,.45);
    z-index:1000;opacity:0;visibility:hidden;pointer-events:none;transition:.25s
}
.menu-overlay.show{opacity:1;visibility:visible;pointer-events:auto}
.drawer{
    position:fixed;top:0;left:0;width:335px;max-width:90vw;height:100vh;
    background:#fff;z-index:1100;transform:translateX(-100%);
    transition:transform .28s ease;display:flex;flex-direction:column;
    box-shadow:8px 0 30px rgba(15,23,42,.12)
}
.drawer.show{transform:translateX(0)}
.drawer-header{
    min-height:88px;padding:18px 20px;border-bottom:1px solid #e5e7eb;
    display:flex;align-items:center;justify-content:space-between
}
.drawer-brand{display:flex;align-items:center;gap:12px}
.drawer-logo{width:46px;height:46px;border-radius:10px;background:#1677d2;overflow:hidden}
.drawer-logo img{width:100%;height:100%;object-fit:contain}
.drawer-brand-text{display:flex;flex-direction:column;gap:4px}
.drawer-brand-text strong{font-size:18px}
.drawer-brand-text span{font-size:12px;color:#64748b}
.drawer-close{
    width:40px;height:40px;border:0;border-radius:10px;
    background:#f1f5f9;font-size:25px;cursor:pointer
}
.drawer-user{
    margin:20px;padding:14px;border:1px solid #dbe4ee;
    background:#f8fafc;border-radius:14px;display:flex;align-items:center;gap:12px
}
.drawer-avatar{
    width:48px;height:48px;border-radius:50%;background:#e0efff;
    color:#1265a7;display:flex;align-items:center;justify-content:center;font-weight:800
}
.drawer-user-info{display:flex;flex-direction:column;gap:4px}
.drawer-user-info strong{font-size:15px}
.drawer-user-info span{font-size:12px;color:#64748b}
.drawer-menu{padding:0 14px 20px;overflow-y:auto;flex:1}
.menu-label{
    padding:10px 12px;margin-bottom:7px;font-size:11px;
    font-weight:800;color:#94a3b8;letter-spacing:.5px
}
.menu-link{
    min-height:48px;display:flex;align-items:center;gap:14px;
    padding:0 14px;margin-bottom:5px;border-radius:10px;
    color:#475569;font-size:15px;font-weight:700
}
.menu-link:hover{background:#f1f5f9;color:#0879e8}
.menu-link.active{background:#e5f1ff;color:#0879e8;position:relative}
.menu-link.active:before{
    content:"";position:absolute;left:0;top:8px;bottom:8px;
    width:3px;background:#0879e8;border-radius:0 4px 4px 0
}
.menu-icon{width:25px;min-width:25px;text-align:center;font-size:19px}
.drawer-footer{border-top:1px solid #e5e7eb;padding:14px}
.logout-link{
    min-height:48px;padding:0 14px;display:flex;align-items:center;
    gap:14px;border-radius:10px;color:#ef4444;font-size:15px;font-weight:700
}
.logout-link:hover{background:#fef2f2}

.main{max-width:1450px;margin:auto;padding:30px 24px 55px}
h1{font-size:28px;color:#102a43;margin-bottom:7px}
.sub{font-size:14px;color:#64748b;margin-bottom:22px;line-height:1.6}
.notice{padding:13px 15px;border-radius:10px;margin-bottom:17px;font-size:13px;line-height:1.6}
.success{background:#e9f9f2;color:#047857;border:1px solid #b8ead4}
.error{background:#fff0f0;color:#b91c1c;border:1px solid #fecaca}
.warning{background:#fff7df;color:#92400e;border:1px solid #fde68a}

.filters,.card,.stat{
    background:#fff;border:1px solid #e3e9f1;border-radius:14px;
    box-shadow:0 3px 12px #0f172a0a
}
.filters{
    padding:17px;display:flex;align-items:end;gap:12px;
    margin-bottom:20px;flex-wrap:wrap
}
.field{display:flex;flex-direction:column;gap:7px;min-width:150px}
label{font-size:12px;font-weight:700;color:#334155}
select,input[type="number"],input[type="text"]{
    height:42px;padding:9px 11px;border:1px solid #d8e0e9;
    border-radius:8px;background:#fff;width:100%;color:#172033
}
.btn{
    height:42px;border:0;border-radius:8px;padding:0 16px;
    background:#1677d2;color:white;font-weight:700;cursor:pointer
}
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:15px;margin-bottom:20px}
.stat{padding:18px}
.stat span{font-size:12px;color:#64748b;font-weight:700}
.stat strong{display:block;font-size:21px;color:#155fa0;margin-top:10px;overflow-wrap:anywhere}
.card{padding:20px;overflow:hidden}
.card h2{font-size:17px;margin-bottom:6px}
.card p{font-size:12px;color:#64748b;margin-bottom:16px;line-height:1.5}
.tablewrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;min-width:1500px}
th{
    background:#f1f5f9;color:#475569;text-align:left;
    font-size:11px;padding:12px 10px;white-space:nowrap
}
td{
    padding:13px 10px;border-bottom:1px solid #edf0f4;
    font-size:12px;color:#334155;vertical-align:middle
}
.money{font-weight:800;color:#155fa0;white-space:nowrap}
.badge{
    display:inline-block;padding:6px 9px;border-radius:20px;
    font-size:10px;font-weight:700;white-space:nowrap
}
.paid{background:#dcfce7;color:#166534}
.unpaid{background:#fff7df;color:#92400e}
.paybtn,.editbtn{
    border:0;border-radius:7px;color:white;padding:8px 10px;
    font-size:11px;font-weight:700;cursor:pointer;white-space:nowrap
}
.paybtn{background:#1677d2}
.editbtn{background:#0f766e;margin-bottom:5px}
.paybtn:disabled{background:#94a3b8;cursor:not-allowed}
.small{font-size:10px;color:#94a3b8;margin-top:4px}
.note{
    margin-top:15px;background:#f8fafc;border-radius:9px;
    padding:13px;color:#64748b;font-size:11px;line-height:1.8
}
.empty{text-align:center;padding:28px;color:#64748b}

.modal-overlay{
    display:none;position:fixed;inset:0;background:rgba(15,23,42,.55);
    z-index:2000;align-items:center;justify-content:center;padding:15px
}
.modal-overlay.show{display:flex}
.modal{
    width:100%;max-width:430px;background:#fff;border-radius:15px;
    padding:24px;box-shadow:0 20px 60px rgba(15,23,42,.2)
}
.modal h2{font-size:20px;color:#102a43;margin-bottom:8px}
.modal-desc{font-size:13px;color:#64748b;line-height:1.6;margin-bottom:20px}
.modal .field{margin-bottom:16px}
.modal-actions{display:flex;justify-content:flex-end;gap:9px;margin-top:20px}
.btn-cancel{
    height:42px;padding:0 16px;border:1px solid #d8e0e9;
    background:#fff;border-radius:8px;cursor:pointer;font-weight:700
}
.btn-save{
    height:42px;padding:0 17px;border:0;background:#1677d2;
    color:#fff;border-radius:8px;cursor:pointer;font-weight:700
}
.help{font-size:11px;color:#94a3b8;margin-top:6px;line-height:1.5}

@media(max-width:1000px){
    .stats{grid-template-columns:repeat(2,1fr)}
}
@media(max-width:700px){
    .topbar{padding:0 15px}
    .brandtext,.user-info{display:none}
    .main{padding:23px 14px}
    .stats{grid-template-columns:1fr}
    .filters{align-items:stretch;flex-direction:column}
    .field{width:100%}
    .filters button{width:100%}
    .modal{padding:19px}
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
            <?= e(strtoupper(substr($nama_admin, 0, 1))) ?>
        </div>

        <div class="drawer-user-info">
            <strong><?= e($nama_admin) ?></strong>
            <span>Admin / Pemilik</span>
        </div>
    </div>

    <nav class="drawer-menu">

        <div class="menu-label">MENU UTAMA</div>

        <a href="dashboard.php" class="menu-link">
            <span class="menu-icon">🏠</span>Dashboard
        </a>

        <a href="produk.php" class="menu-link">
            <span class="menu-icon">📦</span>Produk dan Kategori
        </a>

        <a href="pengguna.php" class="menu-link">
            <span class="menu-icon">👥</span>Pengguna
        </a>

        <a href="stok.php" class="menu-link">
            <span class="menu-icon">📦</span>Stok & Supplier
        </a>

        <a href="pengeluaran.php" class="menu-link">
            <span class="menu-icon">🧾</span>Pengeluaran
        </a>

        <a href="laporan.php" class="menu-link">
            <span class="menu-icon">📊</span>Laporan
        </a>

        <div class="menu-label">KEPEGAWAIAN</div>

        <a href="jadwal_karyawan.php" class="menu-link">
            <span class="menu-icon">🗓️</span>Jadwal Shift Karyawan
        </a>

        <a href="penggajian.php" class="menu-link active">
            <span class="menu-icon">💰</span>Penggajian Karyawan
        </a>

        <a href="pengajuan_cuti.php" class="menu-link">
            <span class="menu-icon">📝</span>Pengajuan Cuti & Izin
        </a>

        <div class="menu-label">AKUN</div>

        <a href="profil.php" class="menu-link">
            <span class="menu-icon">👤</span>Profil
        </a>

    </nav>

    <div class="drawer-footer">
        <a href="../auth/logout.php" class="logout-link"
           onclick="return confirm('Apakah kamu yakin ingin keluar?')">
            <span class="menu-icon">🚪</span>Keluar
        </a>
    </div>

</aside>

<header class="topbar">

    <div class="topbar-left">

        <button type="button" class="hamburger"
            onclick="toggleMenu()"
            aria-label="Buka menu"
            aria-controls="drawer"
            aria-expanded="false"
            id="menuToggle">☰</button>

        <a href="dashboard.php" class="brand">

            <div class="logo">
                <img src="../assets/SembakoKu (3).png" alt="Toko Malaya">
            </div>

            <div class="brandtext">
                <strong>Toko Malaya</strong>
                <span>Panel Admin</span>
            </div>

        </a>

    </div>

    <div class="user">
        <div class="avatar">
            <?= e(strtoupper(substr($nama_admin, 0, 1))) ?>
        </div>

        <div class="user-info">
            <strong><?= e($nama_admin) ?></strong>
            <span>Admin / Pemilik</span>
        </div>
    </div>

</header>

<main class="main">

    <h1>Penggajian Karyawan</h1>

    <div class="sub">
        Kelola gaji pokok, lembur, keterlambatan, izin, cuti, dan ketidakhadiran karyawan.
    </div>

    <?php if ($pesan !== ''): ?>
        <div class="notice <?= e($jenis) ?>">
            <?= e($pesan) ?>
        </div>
    <?php endif; ?>

    <?php if (!$siap): ?>

        <div class="notice warning">
            Salah satu tabel yang dibutuhkan belum tersedia. Periksa database.
        </div>

    <?php elseif (!$kolomSiap): ?>

        <div class="notice warning">
            Kolom database yang dibutuhkan belum lengkap. Periksa struktur tabel absensi dan penggajian.
        </div>

    <?php endif; ?>

    <form method="GET" class="filters">

        <div class="field">
            <label for="bulan">Bulan</label>

            <select name="bulan" id="bulan">
                <?php foreach ($namaBulan as $n => $nm): ?>
                    <option value="<?= $n ?>" <?= $bulan === $n ? 'selected' : '' ?>>
                        <?= e($nm) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label for="tahun">Tahun</label>

            <select name="tahun" id="tahun">
                <?php for ($y = (int)date('Y') - 2; $y <= (int)date('Y') + 1; $y++): ?>
                    <option value="<?= $y ?>" <?= $tahun === $y ? 'selected' : '' ?>>
                        <?= $y ?>
                    </option>
                <?php endfor; ?>
            </select>
        </div>

        <button class="btn" type="submit">Tampilkan Periode</button>

    </form>

    <section class="stats">

        <div class="stat">
            <span>Total Gaji Pokok</span>
            <strong><?= rp($sumGaji) ?></strong>
        </div>

        <div class="stat">
            <span>Total Upah Lembur</span>
            <strong><?= rp($sumLembur) ?></strong>
        </div>

        <div class="stat">
            <span>Total Potongan</span>
            <strong><?= rp($sumPotongan) ?></strong>
        </div>

        <div class="stat">
            <span>Total Gaji Sudah Dibayar</span>
            <strong><?= rp($sumPaid) ?></strong>
        </div>

    </section>

    <section class="card">

        <h2>Rekap Gaji <?= e($namaBulan[$bulan]) ?> <?= $tahun ?></h2>

        <p>
            Rincian gaji dihitung berdasarkan jadwal shift dan absensi.
            Pembayaran yang sudah dicatat menggunakan data snapshot.
        </p>

        <div class="tablewrap">

            <table>

                <thead>
                    <tr>
                        <th>Nama Karyawan</th>
                        <th>Jabatan</th>
                        <th>Jadwal</th>
                        <th>Hadir</th>
                        <th>Terlambat</th>
                        <th>Izin</th>
                        <th>Cuti</th>
                        <th>Tidak Hadir</th>
                        <th>Jam Lembur</th>
                        <th>Gaji Pokok</th>
                        <th>Upah Lembur</th>
                        <th>Total Potongan</th>
                        <th>Gaji Bersih</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (!$siap || !$kolomSiap): ?>

                    <tr>
                        <td colspan="15" class="empty">
                            Periksa struktur database terlebih dahulu.
                        </td>
                    </tr>

                <?php elseif (!$rows): ?>

                    <tr>
                        <td colspan="15" class="empty">
                            Belum ada karyawan aktif.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($rows as $r): ?>

                        <?php
                        $totalPotongan =
                            $r['total_denda']
                            + $r['total_potongan_izin']
                            + $r['total_potongan_tidak_hadir'];
                        ?>

                        <tr>

                            <td>
                                <strong><?= e($r['nama']) ?></strong>
                            </td>

                            <td><?= e($r['jabatan']) ?></td>

                            <td><?= (int)$r['total_jadwal'] ?></td>

                            <td><?= (int)$r['total_hadir'] ?></td>

                            <td>
                                <?= (int)$r['total_terlambat'] ?>
                            </td>

                            <td><?= (int)$r['total_izin'] ?></td>

                            <td><?= (int)$r['total_cuti'] ?></td>

                            <td><?= (int)$r['total_tidak_hadir'] ?></td>

                            <td>
                                <?= (int)$r['total_jam_lembur'] ?> jam
                            </td>

                            <td class="money">
                                <?= rp($r['gaji_bulanan_tampil']) ?>
                            </td>

                            <td>
                                <?= rp($r['total_upah_lembur']) ?>
                            </td>

                            <td>
                                <?= rp($totalPotongan) ?>
                            </td>

                            <td class="money">
                                <?= rp($r['gaji_bersih']) ?>
                            </td>

                            <td>

                                <span class="badge <?= $r['status_pembayaran'] === 'Dibayar' ? 'paid' : 'unpaid' ?>">
                                    <?= e($r['status_pembayaran'] ?? 'Belum dibayar') ?>
                                </span>

                                <?php if (!empty($r['tanggal_bayar'])): ?>
                                    <div class="small">
                                        <?= e(date('d-m-Y H:i', strtotime($r['tanggal_bayar']))) ?>
                                    </div>
                                <?php endif; ?>

                            </td>

                            <td>

                                <?php if ($r['status_pembayaran'] !== 'Dibayar'): ?>

                                    <button type="button"
                                        class="editbtn btn-edit-gaji"
                                        data-id="<?= (int)$r['id_user'] ?>"
                                        data-nama="<?= e($r['nama']) ?>"
                                        data-gaji="<?= e($r['gaji_bulanan']) ?>">
                                        ✏️ Ubah Gaji
                                    </button>

                                    <form method="POST"
                                        onsubmit="return confirm('Catat pembayaran gaji karyawan ini? Setelah dibayar, data periode ini tidak dapat dihitung ulang.')">

                                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                        <input type="hidden" name="aksi" value="bayar">
                                        <input type="hidden" name="id_user" value="<?= (int)$r['id_user'] ?>">
                                        <input type="hidden" name="bulan" value="<?= $bulan ?>">
                                        <input type="hidden" name="tahun" value="<?= $tahun ?>">

                                        <button class="paybtn" type="submit">
                                            Tandai Dibayar
                                        </button>

                                    </form>

                                <?php else: ?>

                                    <span class="small">Pembayaran tersimpan</span>

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

        <div class="note">

            <strong>Aturan perhitungan penggajian:</strong><br>

            • Gaji pokok diambil dari profil karyawan.<br>
            • Lembur = 2% dari gaji bulanan untuk setiap jam penuh setelah jam selesai shift.<br>
            • Pembulatan lembur menggunakan pembulatan ke bawah. Contoh 1 jam 45 menit dihitung 1 jam.<br>
            • Keterlambatan = potongan 2% dari gaji bulanan setiap kejadian.<br>
            • Keterlambatan dibaca dari status absensi atau menit keterlambatan.<br>
            • Izin yang disetujui = potongan 10% dari gaji bulanan per jadwal kerja yang terkena izin.<br>
            • Tidak hadir tanpa pengajuan yang disetujui = potongan 10% per jadwal kerja.<br>
            • Cuti yang disetujui tidak mendapat potongan gaji.<br>
            • Hari tanpa jadwal tidak dihitung sebagai ketidakhadiran.<br>
            • Data pembayaran yang sudah tersimpan tidak dihitung ulang.

        </div>

    </section>

</main>

<!-- POPUP UBAH GAJI -->

<div class="modal-overlay" id="modalGaji" onclick="closeGajiBackdrop(event)">

    <div class="modal">

        <h2>Ubah Gaji Pokok</h2>

        <div class="modal-desc">
            Perubahan ini digunakan untuk perhitungan periode yang belum dibayar.
        </div>

        <form method="POST">

            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
            <input type="hidden" name="aksi" value="ubah_gaji">
            <input type="hidden" name="id_user" id="gaji_id_user">

            <div class="field">
                <label>Nama Karyawan</label>
                <input type="text" id="gaji_nama" readonly>
            </div>

            <div class="field">
                <label for="gaji_bulanan">Gaji Pokok Bulanan (Rp)</label>

                <input type="number"
                    name="gaji_bulanan"
                    id="gaji_bulanan"
                    min="0"
                    max="999999999999"
                    step="1000"
                    required>

                <div class="help">
                    Masukkan nominal tanpa titik atau tanda Rp.
                </div>
            </div>

            <div class="modal-actions">

                <button type="button" class="btn-cancel" onclick="closeGaji()">
                    Batal
                </button>

                <button type="submit" class="btn-save">
                    Simpan Gaji
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
    if (document.getElementById('drawer').classList.contains('show')) {
        closeMenu();
    } else {
        openMenu();
    }
}

function openGaji(id, nama, gaji) {
    document.getElementById('gaji_id_user').value = id;
    document.getElementById('gaji_nama').value = nama;
    document.getElementById('gaji_bulanan').value = gaji;

    document.getElementById('modalGaji').classList.add('show');
    document.getElementById('gaji_bulanan').focus();
}

document.querySelectorAll('.btn-edit-gaji').forEach(function(button) {

    button.addEventListener('click', function() {

        openGaji(
            this.dataset.id,
            this.dataset.nama,
            this.dataset.gaji
        );

    });

});

function closeGaji() {
    document.getElementById('modalGaji').classList.remove('show');
}

function closeGajiBackdrop(event) {
    if (event.target.id === 'modalGaji') {
        closeGaji();
    }
}

document.addEventListener('keydown', function(event) {

    if (event.key === 'Escape') {
        closeMenu();
        closeGaji();
    }

});
</script>

</body>
</html>