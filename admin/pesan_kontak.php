
<?php
session_start();

require_once '../config/database.php';

// Pastikan hanya admin yang dapat mengakses halaman ini.
if (!isset($_SESSION['id_user']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../auth/login.php');
    exit;
}

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Token keamanan form.
if (empty($_SESSION['csrf_pesan_kontak'])) {
    $_SESSION['csrf_pesan_kontak'] = bin2hex(random_bytes(32));
}

$pesan_sukses = '';
$pesan_error = '';

// Proses perubahan status pesan.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_pesan_kontak'], $token)) {
        $pesan_error = 'Permintaan tidak valid. Silakan muat ulang halaman.';
    } else {
        $id_pesan = filter_input(INPUT_POST, 'id_pesan', FILTER_VALIDATE_INT);
        $status = $_POST['status'] ?? '';

        $status_valid = ['baru', 'dibaca', 'dibalas'];

        if (!$id_pesan || !in_array($status, $status_valid, true)) {
            $pesan_error = 'Data perubahan status tidak valid.';
        } else {
            $stmt = $conn->prepare(
                "UPDATE pesan_kontak SET status = ? WHERE id_pesan = ?"
            );

            if ($stmt) {
                $stmt->bind_param('si', $status, $id_pesan);

                if ($stmt->execute()) {
                    $pesan_sukses = 'Status pesan berhasil diperbarui.';
                } else {
                    $pesan_error = 'Gagal memperbarui status pesan.';
                }

                $stmt->close();
            } else {
                $pesan_error = 'Terjadi kesalahan pada database.';
            }
        }
    }
}

// Ambil daftar pesan.
$daftar_pesan = [];
$query = $conn->query(
    "SELECT id_pesan, nama, email, subjek, pesan, status, tanggal_kirim
     FROM pesan_kontak
     ORDER BY tanggal_kirim DESC"
);

if ($query) {
    while ($row = $query->fetch_assoc()) {
        $daftar_pesan[] = $row;
    }
}

// Hitung jumlah pesan berdasarkan status.
$total_pesan = count($daftar_pesan);
$jumlah_baru = 0;
$jumlah_dibaca = 0;
$jumlah_dibalas = 0;

foreach ($daftar_pesan as $item) {
    if ($item['status'] === 'baru') {
        $jumlah_baru++;
    } elseif ($item['status'] === 'dibaca') {
        $jumlah_dibaca++;
    } elseif ($item['status'] === 'dibalas') {
        $jumlah_dibalas++;
    }
}

$nama_admin = $_SESSION['nama'] ?? 'Admin';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesan Kontak | Admin Toko Malaya</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f7fb;
            color: #1f2937;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 245px;
            height: 100vh;
            padding: 25px 18px;
            background: #153b63;
            color: white;
        }

        .brand {
            margin: 0 0 35px;
            font-size: 22px;
            font-weight: 700;
        }

        .brand span {
            display: block;
            margin-top: 5px;
            color: #b9d8f5;
            font-size: 12px;
            font-weight: 400;
        }

        .menu-label {
            margin: 25px 10px 10px;
            color: #a9c2dc;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .menu a {
            display: block;
            margin: 5px 0;
            padding: 12px 14px;
            border-radius: 8px;
            color: #e5eff9;
            font-size: 14px;
            text-decoration: none;
            transition: .2s;
        }

        .menu a:hover,
        .menu a.active {
            background: #1677d2;
            color: white;
        }

        .main {
            min-height: 100vh;
            margin-left: 245px;
            padding: 30px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 30px;
        }

        .topbar h1 {
            margin: 0;
            color: #153b63;
            font-size: 26px;
        }

        .admin-name {
            color: #64748b;
            font-size: 14px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 25px;
        }

        .stat-card {
            padding: 22px;
            border: 1px solid #e5eaf0;
            border-radius: 12px;
            background: white;
            box-shadow: 0 4px 15px rgba(15, 44, 75, .04);
        }

        .stat-card p {
            margin: 0 0 10px;
            color: #64748b;
            font-size: 13px;
        }

        .stat-card h2 {
            margin: 0;
            color: #153b63;
            font-size: 27px;
        }

        .content-card {
            padding: 24px;
            border: 1px solid #e5eaf0;
            border-radius: 12px;
            background: white;
            box-shadow: 0 4px 15px rgba(15, 44, 75, .04);
        }

        .content-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }

        .content-heading h2 {
            margin: 0;
            color: #153b63;
            font-size: 19px;
        }

        .search-input {
            width: 260px;
            max-width: 100%;
            padding: 10px 13px;
            border: 1px solid #dbe3ec;
            border-radius: 8px;
            outline: none;
        }

        .search-input:focus {
            border-color: #1677d2;
        }

        .alert {
            margin-bottom: 20px;
            padding: 13px 16px;
            border-radius: 8px;
            font-size: 14px;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
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
            padding: 14px 12px;
            border-bottom: 1px solid #edf0f4;
            text-align: left;
            vertical-align: top;
            font-size: 13px;
        }

        th {
            background: #f8fafc;
            color: #475569;
            font-weight: 700;
        }

        td {
            color: #475569;
        }

        .subject {
            color: #153b63;
            font-weight: 700;
        }

        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
        }

        .status-baru {
            background: #fee2e2;
            color: #b91c1c;
        }

        .status-dibaca {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .status-dibalas {
            background: #dcfce7;
            color: #15803d;
        }

        .btn-detail {
            padding: 7px 11px;
            border: none;
            border-radius: 6px;
            background: #1677d2;
            color: white;
            cursor: pointer;
            font-size: 12px;
        }

        .btn-detail:hover {
            background: #105fae;
        }

        .empty {
            padding: 35px;
            color: #94a3b8;
            text-align: center;
        }

        /* Modal detail pesan */
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(15, 23, 42, .55);
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            width: 100%;
            max-width: 560px;
            max-height: 90vh;
            overflow-y: auto;
            padding: 25px;
            border-radius: 14px;
            background: white;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .modal-header h2 {
            margin: 0;
            color: #153b63;
            font-size: 20px;
        }

        .close-modal {
            border: none;
            background: transparent;
            color: #64748b;
            font-size: 25px;
            cursor: pointer;
        }

        .detail-item {
            margin-bottom: 16px;
        }

        .detail-item label {
            display: block;
            margin-bottom: 5px;
            color: #64748b;
            font-size: 12px;
            font-weight: 700;
        }

        .detail-item p {
            margin: 0;
            color: #334155;
            font-size: 14px;
            overflow-wrap: anywhere;
        }

        .message-text {
            padding: 15px;
            border-radius: 8px;
            background: #f8fafc;
            white-space: pre-wrap;
            line-height: 1.7;
        }

        .status-form {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
        }

        .status-form select {
            flex: 1;
            min-width: 0;
            padding: 10px;
            border: 1px solid #dbe3ec;
            border-radius: 7px;
        }

        .btn-save {
            padding: 10px 15px;
            border: none;
            border-radius: 7px;
            background: #1677d2;
            color: white;
            cursor: pointer;
        }

        @media (max-width: 900px) {
            .sidebar {
                width: 200px;
            }

            .main {
                margin-left: 200px;
                padding: 22px;
            }

            .stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 650px) {
            .sidebar {
                position: static;
                width: 100%;
                height: auto;
            }

            .brand {
                margin-bottom: 15px;
            }

            .menu-label {
                margin-top: 12px;
            }

            .main {
                margin-left: 0;
                padding: 20px 14px;
            }

            .topbar {
                align-items: flex-start;
                flex-direction: column;
            }

            .content-heading {
                align-items: flex-start;
                flex-direction: column;
            }

            .search-input {
                width: 100%;
            }
        }

        @media (max-width: 420px) {
            .stats {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }

            .stat-card {
                padding: 15px;
            }

            .stat-card h2 {
                font-size: 22px;
            }
        }
    </style>
</head>

<body>

<aside class="sidebar">
    <div class="brand">
        TOKO MALAYA
        <span>Panel Administrator</span>
    </div>

    <div class="menu-label">Menu Admin</div>

    <nav class="menu">
        <a href="dashboard.php">Dashboard</a>
        <a href="pesan_kontak.php" class="active">Pesan Kontak</a>
        <a href="../index.php" target="_blank">Lihat Website</a>
        <a href="../auth/logout.php">Logout</a>
    </nav>
</aside>

<main class="main">

    <div class="topbar">
        <h1>Pesan Kontak</h1>
        <div class="admin-name">
            Halo, <?= e($nama_admin); ?>
        </div>
    </div>

    <?php if ($pesan_sukses): ?>
        <div class="alert alert-success">
            <?= e($pesan_sukses); ?>
        </div>
    <?php endif; ?>

    <?php if ($pesan_error): ?>
        <div class="alert alert-error">
            <?= e($pesan_error); ?>
        </div>
    <?php endif; ?>

    <section class="stats">
        <div class="stat-card">
            <p>Total Pesan</p>
            <h2><?= $total_pesan; ?></h2>
        </div>

        <div class="stat-card">
            <p>Pesan Baru</p>
            <h2><?= $jumlah_baru; ?></h2>
        </div>

        <div class="stat-card">
            <p>Sudah Dibaca</p>
            <h2><?= $jumlah_dibaca; ?></h2>
        </div>

        <div class="stat-card">
            <p>Sudah Dibalas</p>
            <h2><?= $jumlah_dibalas; ?></h2>
        </div>
    </section>

    <section class="content-card">
        <div class="content-heading">
            <h2>Daftar Pesan dari Pengunjung</h2>

            <input
                type="text"
                id="searchPesan"
                class="search-input"
                placeholder="Cari nama, email, atau subjek..."
                onkeyup="cariPesan()"
            >
        </div>

        <div class="table-wrap">
            <table id="tabelPesan">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Nama Pengirim</th>
                        <th>Email</th>
                        <th>Subjek</th>
                        <th>Tanggal</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (empty($daftar_pesan)): ?>
                        <tr>
                            <td colspan="7" class="empty">
                                Belum ada pesan yang masuk.
                            </td>
                        </tr>
                    <?php else: ?>

                        <?php foreach ($daftar_pesan as $i => $item): ?>
                            <tr>
                                <td><?= $i + 1; ?></td>

                                <td><?= e($item['nama']); ?></td>

                                <td><?= e($item['email']); ?></td>

                                <td class="subject">
                                    <?= e($item['subjek']); ?>
                                </td>

                                <td>
                                    <?= e(date(
                                        'd-m-Y H:i',
                                        strtotime($item['tanggal_kirim'])
                                    )); ?>
                                </td>

                                <td>
                                    <span class="status status-<?= e($item['status']); ?>">
                                        <?= e(ucfirst($item['status'])); ?>
                                    </span>
                                </td>

                                <td>
                                    <button
                                        type="button"
                                        class="btn-detail"
                                        onclick="bukaDetail(
                                            <?= (int) $item['id_pesan']; ?>,
                                            <?= htmlspecialchars(json_encode($item['nama']), ENT_QUOTES, 'UTF-8'); ?>,
                                            <?= htmlspecialchars(json_encode($item['email']), ENT_QUOTES, 'UTF-8'); ?>,
                                            <?= htmlspecialchars(json_encode($item['subjek']), ENT_QUOTES, 'UTF-8'); ?>,
                                            <?= htmlspecialchars(json_encode($item['pesan']), ENT_QUOTES, 'UTF-8'); ?>,
                                            <?= htmlspecialchars(json_encode($item['status']), ENT_QUOTES, 'UTF-8'); ?>,
                                            <?= htmlspecialchars(json_encode($item['tanggal_kirim']), ENT_QUOTES, 'UTF-8'); ?>
                                        )"
                                    >
                                        Lihat Pesan
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

</main>

<!-- Modal Detail Pesan -->
<div class="modal" id="modalPesan">
    <div class="modal-content">

        <div class="modal-header">
            <h2>Detail Pesan</h2>
            <button type="button" class="close-modal" onclick="tutupDetail()">
                &times;
            </button>
        </div>

        <div class="detail-item">
            <label>Nama Pengirim</label>
            <p id="detailNama"></p>
        </div>

        <div class="detail-item">
            <label>Email</label>
            <p id="detailEmail"></p>
        </div>

        <div class="detail-item">
            <label>Subjek</label>
            <p id="detailSubjek"></p>
        </div>

        <div class="detail-item">
            <label>Tanggal Dikirim</label>
            <p id="detailTanggal"></p>
        </div>

        <div class="detail-item">
            <label>Isi Pesan</label>
            <p class="message-text" id="detailIsi"></p>
        </div>

        <form method="POST" class="status-form">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($_SESSION['csrf_pesan_kontak']); ?>"
            >

            <input type="hidden" name="id_pesan" id="idPesan">

            <select name="status" id="pilihStatus" required>
                <option value="baru">Baru</option>
                <option value="dibaca">Dibaca</option>
                <option value="dibalas">Dibalas</option>
            </select>

            <button type="submit" class="btn-save">
                Simpan Status
            </button>
        </form>

    </div>
</div>

<script>
    function cariPesan() {
        const input = document.getElementById('searchPesan');
        const filter = input.value.toLowerCase();
        const rows = document.querySelectorAll('#tabelPesan tbody tr');

        rows.forEach(function(row) {
            row.style.display = row.innerText.toLowerCase().includes(filter)
                ? ''
                : 'none';
        });
    }

    function bukaDetail(id, nama, email, subjek, pesan, status, tanggal) {
        document.getElementById('idPesan').value = id;
        document.getElementById('detailNama').textContent = nama;
        document.getElementById('detailEmail').textContent = email;
        document.getElementById('detailSubjek').textContent = subjek;
        document.getElementById('detailIsi').textContent = pesan;
        document.getElementById('detailTanggal').textContent = tanggal;
        document.getElementById('pilihStatus').value = status;

        document.getElementById('modalPesan').classList.add('show');
    }

    function tutupDetail() {
        document.getElementById('modalPesan').classList.remove('show');
    }

    document.getElementById('modalPesan').addEventListener('click', function(event) {
        if (event.target === this) {
            tutupDetail();
        }
    });
</script>

</body>
</html>
