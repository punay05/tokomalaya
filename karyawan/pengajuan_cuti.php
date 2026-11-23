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
$tahunIni = (int) date('Y');
$tanggalHariIni = date('Y-m-d');

function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function tanggalIndonesia(string $tanggal): string
{
    $namaBulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];

    $timestamp = strtotime($tanggal);

    if (!$timestamp) {
        return $tanggal;
    }

    return (int) date('j', $timestamp) . ' '
        . $namaBulan[(int) date('n', $timestamp)] . ' '
        . date('Y', $timestamp);
}

/* Ambil jatah cuti tahunan karyawan. */
function ambilJatahCuti(mysqli $conn, int $idUser, int $tahun): array
{
    $jatah = 12;
    $terpakai = 0;

    $sql = 'SELECT total_jatah, cuti_terpakai
            FROM jatah_cuti_tahunan
            WHERE id_user = ? AND tahun = ?
            LIMIT 1';

    $stmt = mysqli_prepare($conn, $sql);

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $idUser, $tahun);
        mysqli_stmt_execute($stmt);

        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        if ($row) {
            $jatah = (int) $row['total_jatah'];
            $terpakai = (int) $row['cuti_terpakai'];
        }

        mysqli_stmt_close($stmt);
    }

    return [$jatah, $terpakai];
}

/* Hitung cuti yang masih menunggu persetujuan admin. */
function jumlahCutiMenunggu(mysqli $conn, int $idUser, int $tahun): int
{
    $total = 0;

    $sql = "SELECT COALESCE(SUM(jumlah_hari), 0) AS total
            FROM pengajuan_cuti_izin
            WHERE id_user = ?
              AND YEAR(tanggal_mulai) = ?
              AND jenis_pengajuan = 'cuti'
              AND status = 'Menunggu'";

    $stmt = mysqli_prepare($conn, $sql);

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $idUser, $tahun);
        mysqli_stmt_execute($stmt);

        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        $total = (int) ($row['total'] ?? 0);

        mysqli_stmt_close($stmt);
    }

    return $total;
}

/* Token keamanan formulir. */
if (empty($_SESSION['csrf_pengajuan'])) {
    $_SESSION['csrf_pengajuan'] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION['csrf_pengajuan'];
$pesan = '';
$jenisPesan = 'info';

/*
 * Buat jatah cuti tahun berjalan secara otomatis.
 * Jatah baru akan dibuat kembali pada tahun berikutnya.
 */
$sqlJatah = "INSERT INTO jatah_cuti_tahunan
                (id_user, tahun, total_jatah, cuti_terpakai)
             VALUES (?, ?, 12, 0)
             ON DUPLICATE KEY UPDATE id_user = VALUES(id_user)";

$stmtJatah = mysqli_prepare($conn, $sqlJatah);

if ($stmtJatah) {
    mysqli_stmt_bind_param($stmtJatah, 'ii', $idUser, $tahunIni);
    mysqli_stmt_execute($stmtJatah);
    mysqli_stmt_close($stmtJatah);
}

/* Proses pengajuan cuti atau izin. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kirim_pengajuan'])) {

    $tokenForm = (string) ($_POST['csrf_pengajuan'] ?? '');

    if (!hash_equals($csrf, $tokenForm)) {
        $pesan = 'Sesi formulir tidak valid. Muat ulang halaman dan coba lagi.';
        $jenisPesan = 'error';

    } else {
        $tanggalMulai = trim((string) ($_POST['tanggal_mulai'] ?? ''));
        $tanggalSelesai = trim((string) ($_POST['tanggal_selesai'] ?? ''));
        $alasan = trim((string) ($_POST['alasan'] ?? ''));

        $formatMulai = DateTime::createFromFormat('!Y-m-d', $tanggalMulai);
        $formatSelesai = DateTime::createFromFormat('!Y-m-d', $tanggalSelesai);

        $tanggalValid = $formatMulai !== false
            && $formatMulai->format('Y-m-d') === $tanggalMulai
            && $formatSelesai !== false
            && $formatSelesai->format('Y-m-d') === $tanggalSelesai;

        if (!$tanggalValid || $alasan === '') {
            $pesan = 'Lengkapi tanggal mulai, tanggal selesai, dan alasan pengajuan.';
            $jenisPesan = 'error';

        } elseif ($tanggalSelesai < $tanggalMulai) {
            $pesan = 'Tanggal selesai tidak boleh lebih awal dari tanggal mulai.';
            $jenisPesan = 'error';

        } elseif (
            (int) substr($tanggalMulai, 0, 4) !== $tahunIni ||
            (int) substr($tanggalSelesai, 0, 4) !== $tahunIni
        ) {
            $pesan = 'Pengajuan harus berada dalam tahun kalender berjalan.';
            $jenisPesan = 'error';

        } elseif ($tanggalMulai < $tanggalHariIni) {
            $pesan = 'Tanggal pengajuan tidak boleh berada di masa lalu.';
            $jenisPesan = 'error';

        } else {
            /*
             * Perhitungan jumlah hari menggunakan hari kalender,
             * termasuk Sabtu dan Minggu.
             */
            $jumlahHari = (int) $formatMulai->diff($formatSelesai)->days + 1;

            mysqli_begin_transaction($conn);

            try {
                /* Kunci data jatah cuti selama transaksi. */
                $sqlLock = 'SELECT total_jatah, cuti_terpakai
                            FROM jatah_cuti_tahunan
                            WHERE id_user = ? AND tahun = ?
                            FOR UPDATE';

                $stmtLock = mysqli_prepare($conn, $sqlLock);

                if (!$stmtLock) {
                    throw new Exception('Data jatah cuti tidak dapat diperiksa.');
                }

                mysqli_stmt_bind_param($stmtLock, 'ii', $idUser, $tahunIni);
                mysqli_stmt_execute($stmtLock);

                $dataJatah = mysqli_fetch_assoc(
                    mysqli_stmt_get_result($stmtLock)
                );

                mysqli_stmt_close($stmtLock);

                $totalJatah = (int) ($dataJatah['total_jatah'] ?? 12);
                $cutiTerpakai = (int) ($dataJatah['cuti_terpakai'] ?? 0);

                $cutiMenunggu = jumlahCutiMenunggu(
                    $conn,
                    $idUser,
                    $tahunIni
                );

                $sisaCutiSaatIni = max(
                    0,
                    $totalJatah - $cutiTerpakai - $cutiMenunggu
                );

                /* Cegah tanggal pengajuan yang bertumpang tindih. */
                $sqlBentrok = "SELECT COUNT(*) AS total
                               FROM pengajuan_cuti_izin
                               WHERE id_user = ?
                                 AND status IN ('Menunggu', 'Disetujui')
                                 AND tanggal_mulai <= ?
                                 AND tanggal_selesai >= ?";

                $stmtBentrok = mysqli_prepare($conn, $sqlBentrok);

                if (!$stmtBentrok) {
                    throw new Exception('Pemeriksaan tanggal pengajuan gagal.');
                }

                mysqli_stmt_bind_param(
                    $stmtBentrok,
                    'iss',
                    $idUser,
                    $tanggalSelesai,
                    $tanggalMulai
                );

                mysqli_stmt_execute($stmtBentrok);

                $dataBentrok = mysqli_fetch_assoc(
                    mysqli_stmt_get_result($stmtBentrok)
                );

                mysqli_stmt_close($stmtBentrok);

                if ((int) ($dataBentrok['total'] ?? 0) > 0) {
                    throw new DomainException(
                        'Tanggal tersebut bertumpang tindih dengan pengajuan yang masih menunggu atau sudah disetujui.'
                    );
                }

                /*
                 * Jika jatah cukup, jenisnya cuti.
                 * Jika jatah tidak cukup, seluruh pengajuan menjadi izin.
                 */
                $jenisPengajuan = ($jumlahHari <= $sisaCutiSaatIni)
                    ? 'cuti'
                    : 'izin';

                $sqlInsert = 'INSERT INTO pengajuan_cuti_izin
                                (id_user, jenis_pengajuan, tanggal_mulai,
                                 tanggal_selesai, jumlah_hari, alasan)
                              VALUES (?, ?, ?, ?, ?, ?)';

                $stmtInsert = mysqli_prepare($conn, $sqlInsert);

                if (!$stmtInsert) {
                    throw new Exception('Formulir pengajuan tidak dapat disiapkan.');
                }

                mysqli_stmt_bind_param(
                    $stmtInsert,
                    'isssis',
                    $idUser,
                    $jenisPengajuan,
                    $tanggalMulai,
                    $tanggalSelesai,
                    $jumlahHari,
                    $alasan
                );

                if (!mysqli_stmt_execute($stmtInsert)) {
                    throw new Exception(mysqli_stmt_error($stmtInsert));
                }

                mysqli_stmt_close($stmtInsert);
                mysqli_commit($conn);

                if ($jenisPengajuan === 'cuti') {
                    $pesan = 'Pengajuan cuti berhasil dikirim dan menunggu persetujuan admin.';
                } else {
                    $pesan = 'Sisa jatah cuti tidak mencukupi. Pengajuan otomatis dicatat sebagai izin dan menunggu persetujuan admin.';
                }

                $jenisPesan = 'success';

            } catch (DomainException $ex) {
                mysqli_rollback($conn);
                $pesan = $ex->getMessage();
                $jenisPesan = 'error';

            } catch (Throwable $ex) {
                mysqli_rollback($conn);
                error_log('[Pengajuan Cuti/Izin] ' . $ex->getMessage());

                $pesan = 'Pengajuan gagal disimpan. Silakan coba kembali atau hubungi admin.';
                $jenisPesan = 'error';
            }
        }
    }
}

/* Ambil ulang informasi jatah cuti. */
[$jatahCuti, $cutiTerpakai] = ambilJatahCuti(
    $conn,
    $idUser,
    $tahunIni
);

$cutiMenunggu = jumlahCutiMenunggu(
    $conn,
    $idUser,
    $tahunIni
);

$sisaCuti = max(
    0,
    $jatahCuti - $cutiTerpakai - $cutiMenunggu
);

/* Ambil riwayat pengajuan karyawan. */
$riwayatPengajuan = [];

$sqlRiwayat = 'SELECT *
               FROM pengajuan_cuti_izin
               WHERE id_user = ?
               ORDER BY tanggal_pengajuan DESC
               LIMIT 30';

$stmtRiwayat = mysqli_prepare($conn, $sqlRiwayat);

if ($stmtRiwayat) {
    mysqli_stmt_bind_param($stmtRiwayat, 'i', $idUser);
    mysqli_stmt_execute($stmtRiwayat);

    $resultRiwayat = mysqli_stmt_get_result($stmtRiwayat);

    while ($row = mysqli_fetch_assoc($resultRiwayat)) {
        $riwayatPengajuan[] = $row;
    }

    mysqli_stmt_close($stmtRiwayat);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengajuan Cuti dan Izin - Toko Malaya</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Inter, "Segoe UI", Arial, sans-serif;
            background: #f4f7fb;
            color: #172033;
        }

        a {
            text-decoration: none;
        }

        .topbar {
            min-height: 72px;
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px max(20px, calc((100% - 1100px) / 2));
            gap: 15px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #15324f;
            font-weight: 800;
        }

        .brand img {
            width: 43px;
            height: 43px;
            object-fit: contain;
            background: #1677d2;
            border-radius: 11px;
            padding: 4px;
        }

        .brand-text {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .brand-text strong {
            font-size: 17px;
        }

        .brand-text span {
            font-size: 12px;
            color: #64748b;
            font-weight: 500;
        }

        .top-actions {
            display: flex;
            gap: 9px;
            align-items: center;
        }

        .top-link {
            padding: 9px 12px;
            border-radius: 9px;
            background: #eff6ff;
            color: #1d4ed8;
            font-size: 13px;
            font-weight: 700;
        }

        .logout {
            background: #fef2f2;
            color: #b91c1c;
        }

        .container {
            max-width: 1100px;
            margin: 28px auto;
            padding: 0 18px;
        }

        .page-heading {
            margin-bottom: 20px;
        }

        .page-heading h1 {
            margin: 0 0 8px;
            font-size: 25px;
            color: #15324f;
        }

        .page-heading p {
            margin: 0;
            color: #64748b;
            line-height: 1.6;
            font-size: 14px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 19px;
        }

        .stat span {
            display: block;
            color: #64748b;
            font-size: 13px;
            margin-bottom: 9px;
        }

        .stat strong {
            font-size: 25px;
            color: #15324f;
        }

        .card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 15px;
            padding: 22px;
            margin-bottom: 18px;
        }

        .card h2 {
            margin: 0 0 17px;
            font-size: 17px;
            color: #15324f;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        label {
            font-size: 13px;
            font-weight: 700;
            color: #334155;
        }

        input, textarea {
            width: 100%;
            border: 1px solid #cbd5e1;
            border-radius: 9px;
            padding: 12px;
            font-family: inherit;
            font-size: 14px;
            outline: none;
            background: #fff;
        }

        input:focus, textarea:focus {
            border-color: #1677d2;
            box-shadow: 0 0 0 3px #1677d21a;
        }

        textarea {
            resize: vertical;
            min-height: 110px;
        }

        .help {
            color: #64748b;
            font-size: 12px;
            line-height: 1.5;
        }

        .btn {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            border: 0;
            border-radius: 9px;
            padding: 12px 17px;
            background: #1677d2;
            color: white;
            font-size: 14px;
            font-weight: 800;
            cursor: pointer;
        }

        .btn:hover {
            background: #125fa8;
        }

        .alert {
            padding: 13px 15px;
            border-radius: 10px;
            margin-bottom: 18px;
            font-size: 14px;
            line-height: 1.6;
        }

        .alert.success {
            color: #166534;
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
        }

        .alert.error {
            color: #991b1b;
            background: #fef2f2;
            border: 1px solid #fecaca;
        }

        .alert.info {
            color: #1e40af;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 750px;
        }

        th, td {
            padding: 12px 10px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
            font-size: 13px;
            vertical-align: top;
        }

        th {
            color: #64748b;
            background: #f8fafc;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        tbody tr:last-child td {
            border-bottom: 0;
        }

        .badge {
            display: inline-block;
            border-radius: 30px;
            padding: 6px 10px;
            font-size: 11px;
            font-weight: 800;
            white-space: nowrap;
        }

        .badge.menunggu {
            color: #92400e;
            background: #fef3c7;
        }

        .badge.disetujui {
            color: #166534;
            background: #dcfce7;
        }

        .badge.ditolak {
            color: #991b1b;
            background: #fee2e2;
        }

        .empty {
            text-align: center;
            padding: 25px 10px;
            color: #64748b;
            font-size: 14px;
        }

        .footer {
            text-align: center;
            color: #94a3b8;
            font-size: 12px;
            padding: 10px 0 28px;
        }

        @media (max-width: 700px) {
            .topbar {
                padding: 12px 14px;
            }

            .top-actions {
                gap: 6px;
            }

            .top-link {
                padding: 8px 9px;
                font-size: 11px;
            }

            .container {
                margin: 20px auto;
                padding: 0 13px;
            }

            .stats {
                grid-template-columns: 1fr;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full {
                grid-column: auto;
            }

            .card {
                padding: 17px;
            }

            .page-heading h1 {
                font-size: 22px;
            }
        }
    </style>
</head>

<body>

<header class="topbar">
    <a href="dashboard.php" class="brand">
        <img src="../assets/logo-toko-malaya-icon.png" alt="Logo Toko Malaya">
        <span class="brand-text">
            <strong>Toko Malaya</strong>
            <span>Portal Karyawan</span>
        </span>
    </a>

    <div class="top-actions">
        <a href="dashboard.php" class="top-link">← Dashboard</a>
        <a href="../auth/logout.php"
           class="top-link logout"
           onclick="return confirm('Yakin ingin keluar dari akun?')">
            Keluar
        </a>
    </div>
</header>

<main class="container">

    <section class="page-heading">
        <h1>Pengajuan Cuti dan Izin</h1>
        <p>
            Halo, <?= e($nama) ?>. Gunakan halaman ini untuk mengajukan
            cuti atau izin kepada admin Toko Malaya.
        </p>
    </section>

    <?php if ($pesan !== ''): ?>
        <div class="alert <?= e($jenisPesan) ?>">
            <?= e($pesan) ?>
        </div>
    <?php endif; ?>

    <section class="stats">
        <article class="stat">
            <span>Jatah cuti tahun <?= $tahunIni ?></span>
            <strong><?= $jatahCuti ?> hari</strong>
        </article>

        <article class="stat">
            <span>Cuti terpakai</span>
            <strong><?= $cutiTerpakai ?> hari</strong>
        </article>

        <article class="stat">
            <span>Sisa cuti tersedia</span>
            <strong><?= $sisaCuti ?> hari</strong>
        </article>
    </section>

    <section class="card">
        <h2>📝 Formulir Pengajuan</h2>

        <div class="alert info">
            <strong>Informasi ketentuan:</strong><br>
            Setiap karyawan memperoleh jatah cuti 12 hari per tahun.
            Cuti yang disetujui tidak dikenakan potongan gaji.
            Jika jumlah hari yang diajukan melebihi sisa jatah cuti,
            seluruh pengajuan akan otomatis dikategorikan sebagai izin.
            Pengajuan izin yang disetujui dikenakan potongan 10% gaji
            bulanan per hari.
        </div>

        <form method="POST" action="">
            <input type="hidden" name="csrf_pengajuan" value="<?= e($csrf) ?>">

            <div class="form-grid">
                <div class="form-group">
                    <label for="tanggal_mulai">Tanggal Mulai</label>
                    <input
                        type="date"
                        id="tanggal_mulai"
                        name="tanggal_mulai"
                        min="<?= e($tanggalHariIni) ?>"
                        max="<?= $tahunIni ?>-12-31"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="tanggal_selesai">Tanggal Selesai</label>
                    <input
                        type="date"
                        id="tanggal_selesai"
                        name="tanggal_selesai"
                        min="<?= e($tanggalHariIni) ?>"
                        max="<?= $tahunIni ?>-12-31"
                        required
                    >
                </div>

                <div class="form-group full">
                    <label for="alasan">Alasan Pengajuan</label>
                    <textarea
                        id="alasan"
                        name="alasan"
                        maxlength="2000"
                        placeholder="Tuliskan alasan cuti atau izin secara jelas..."
                        required
                    ></textarea>
                    <span class="help">
                        Jelaskan alasan pengajuan agar admin dapat
                        mempertimbangkannya.
                    </span>
                </div>

                <div class="form-group full">
                    <button
                        type="submit"
                        name="kirim_pengajuan"
                        value="1"
                        class="btn"
                        onclick="return confirm('Kirim pengajuan cuti/izin ini?')"
                    >
                        Kirim Pengajuan
                    </button>
                </div>
            </div>
        </form>
    </section>

    <section class="card">
        <h2>📋 Riwayat Pengajuan</h2>

        <?php if (!$riwayatPengajuan): ?>
            <div class="empty">
                Anda belum memiliki riwayat pengajuan cuti atau izin.
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Tanggal Pengajuan</th>
                            <th>Jenis</th>
                            <th>Periode</th>
                            <th>Jumlah Hari</th>
                            <th>Alasan</th>
                            <th>Status</th>
                            <th>Catatan Admin</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($riwayatPengajuan as $item): ?>
                        <?php
                        $kelasStatus = 'menunggu';

                        if ($item['status'] === 'Disetujui') {
                            $kelasStatus = 'disetujui';
                        } elseif ($item['status'] === 'Ditolak') {
                            $kelasStatus = 'ditolak';
                        }
                        ?>

                        <tr>
                            <td>
                                <?= e(date(
                                    'd/m/Y H:i',
                                    strtotime($item['tanggal_pengajuan'])
                                )) ?>
                            </td>

                            <td>
                                <?= $item['jenis_pengajuan'] === 'cuti'
                                    ? 'Cuti'
                                    : 'Izin' ?>
                            </td>

                            <td>
                                <?= e(tanggalIndonesia($item['tanggal_mulai'])) ?>
                                s/d
                                <?= e(tanggalIndonesia($item['tanggal_selesai'])) ?>
                            </td>

                            <td><?= (int)$item['jumlah_hari'] ?> hari</td>

                            <td><?= nl2br(e($item['alasan'])) ?></td>

                            <td>
                                <span class="badge <?= e($kelasStatus) ?>">
                                    <?= e($item['status']) ?>
                                </span>
                            </td>

                            <td>
                                <?= e($item['catatan_admin'] ?: '—') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <div class="footer">
        © <?= date('Y') ?> Toko Malaya · Portal Karyawan
    </div>

</main>

<script>
const tanggalMulai = document.getElementById('tanggal_mulai');
const tanggalSelesai = document.getElementById('tanggal_selesai');

tanggalMulai.addEventListener('change', function () {
    tanggalSelesai.min = this.value;

    if (tanggalSelesai.value && tanggalSelesai.value < this.value) {
        tanggalSelesai.value = this.value;
    }
});
</script>

</body>
</html>