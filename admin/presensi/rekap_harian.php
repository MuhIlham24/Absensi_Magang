<?php 
ob_start();
session_start();  

if (!isset($_SESSION["login"])) {
    header("Location: ../../auth/login.php?pesan=belum_login");
    exit;
} else if ($_SESSION["role"] != 'admin') {
    header("Location: ../../auth/login.php?pesan=tolak_akses");
    exit;
}

$judul = 'Rekap Presensi Harian';
include('../layout/header.php'); 
include_once("../../config.php");

// Jika parameter tanggal_dari kosong, ambil semua data; jika tidak, filter berdasarkan tanggal
if (empty($_GET['tanggal_dari'])) {
    $tanggal_hari_ini = (date('Y-m-d'));
    $result = mysqli_query($connection, "SELECT presensi.*, mahasiswa.nama, mahasiswa.lokasi_presensi FROM 
    presensi JOIN mahasiswa ON presensi.id_mahasiswa = mahasiswa.id WHERE tanggal_masuk = 
    '$tanggal_hari_ini' ORDER BY tanggal_masuk DESC");
} else {
    $tanggal_dari = $_GET['tanggal_dari'];
    $tanggal_sampai = $_GET['tanggal_sampai'];
    $result = mysqli_query($connection, "SELECT presensi.*, mahasiswa.nama, mahasiswa.lokasi_presensi FROM 
    presensi JOIN mahasiswa ON presensi.id_mahasiswa = mahasiswa.id WHERE tanggal_masuk BETWEEN 
    '$tanggal_dari' AND '$tanggal_sampai' ORDER BY tanggal_masuk DESC");
}

if (empty($_GET['tanggal_dari'])) {
    $tanggal = date('Y-m-d');
} else {
    $tanggal = $_GET['tanggal_dari'] . '-' . $_GET['tanggal_sampai'];
}

// Buat cache untuk data lokasi agar query tidak dieksekusi berulang
$locationCache = array();

// Jika data lokasi default dari session diperlukan (misalnya untuk export), dapat disimpan di sini
$lokasi_presensi_default = $_SESSION['lokasi_presensi'];
$jam_masuk_kantor_default = null;
$lokasi_default = mysqli_query($connection, "SELECT * FROM lokasi_presensi WHERE nama_lokasi = '$lokasi_presensi_default'");
if ($lokasi_default && $lokasi_result = mysqli_fetch_array($lokasi_default)) {
    // Gunakan format 24-jam (H:i:s) jika diperlukan, atau format sesuai kebutuhan
    $jam_masuk_kantor_default = date('H:i:s', strtotime($lokasi_result['jam_masuk']));
}
?>

<div class="page-body">
    <div class="container-xl">
        <div class="row mb-3">
            <div class="col-md-2">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#exampleModal">
                    Export Excel
                </button>
            </div>
            <div class="col-md-10">
                <form method="GET">
                    <div class="input-group">
                        <input type="date" class="form-control" name="tanggal_dari">
                        <input type="date" class="form-control" name="tanggal_sampai">
                        <button type="submit" class="btn btn-primary">Tampilkan</button>
                    </div>
                </form>
            </div>
        </div>

        <?php if(empty($_GET['tanggal_dari'])) : ?>
            <span>Rekap Presensi Tanggal : <?= date('d F Y') ?></span>
         <?php else : ?>
            <span>Rekap Presensi Tanggal : <?= date('d F Y', strtotime($_GET['tanggal_dari'])) . '
            sampai '. date('d F Y', strtotime($_GET['tanggal_sampai'])) ?></span>
         <?php endif; ?>

        <table class="table table-bordered mt-2">
            <tr class="text-center">
                <th>No.</th>
                <th>Nama</th>
                <th>Tanggal</th>
                <th>Jam Masuk</th>
                <th>Jam Pulang</th>
                <th>Total Jam</th>
                <th>Total Terlambat</th>
            </tr>

            <?php if(mysqli_num_rows($result) === 0){ ?>
                <tr>
                    <td colspan="7">Data Rekap Presensi Masih Kosong</td>
                </tr>
            <?php } else { ?>

            <?php 
            $no = 1; 
            while($rekap = mysqli_fetch_array($result)) : 

                // Perhitungan Total Jam Kerja (menggunakan format 24 jam)
                $jam_tanggal_masuk = date('Y-m-d H:i:s', strtotime($rekap['tanggal_masuk'] . ' ' . 
                $rekap['jam_masuk']));
                $jam_tanggal_keluar = date('Y-m-d H:i:s', strtotime($rekap['tanggal_keluar'] . ' ' . 
                $rekap['jam_keluar']));

                $timestamp_masuk = strtotime($jam_tanggal_masuk);
                $timestamp_keluar = strtotime($jam_tanggal_keluar);

                $selisih = $timestamp_keluar - $timestamp_masuk;
                
                $total_jam_kerja = floor($selisih / 3600);
                $sisa = $selisih - ($total_jam_kerja * 3600);
                $selisih_menit_kerja = floor($sisa / 60);

                // Dapatkan jam masuk kantor untuk lokasi presensi masing-masing mahasiswa
                $lokasi_presensi = $rekap['lokasi_presensi'];
                if (!isset($locationCache[$lokasi_presensi])) {
                    $lokasi_q = mysqli_query($connection, "SELECT * FROM lokasi_presensi WHERE nama_lokasi = '$lokasi_presensi'");
                    $locationCache[$lokasi_presensi] = mysqli_fetch_array($lokasi_q);
                }
                // Jika data lokasi ditemukan, gunakan nilai jam_masuk; jika tidak, gunakan nilai default
                if ($locationCache[$lokasi_presensi] && isset($locationCache[$lokasi_presensi]['jam_masuk'])) {
                    $jam_masuk_kantor = date('H:i:s', strtotime($locationCache[$lokasi_presensi]['jam_masuk']));
                } else {
                    $jam_masuk_kantor = $jam_masuk_kantor_default;
                }

                // Menghitung keterlambatan: selisih antara jam masuk real dan jam masuk kantor
                $jam_masuk_real = date('H:i:s', strtotime($rekap['jam_masuk']));
                $timestamp_jam_masuk_real = strtotime($jam_masuk_real);
                $timestamp_jam_masuk_kantor = strtotime($jam_masuk_kantor);
                $terlambat = $timestamp_jam_masuk_real - $timestamp_jam_masuk_kantor;

                if ($terlambat <= 0) {
                    // Tidak terlambat
                    $terlambat_output = '<span class="badge bg-success">On Time</span>';
                } else {
                    $total_jam_terlambat = floor($terlambat / 3600);
                    $sisa_terlambat = $terlambat - ($total_jam_terlambat * 3600);
                    $selisih_menit_terlambat = floor($sisa_terlambat / 60);
                    $terlambat_output = $total_jam_terlambat . ' Jam ' . $selisih_menit_terlambat . ' Menit';
                }
                ?>
            <tr>
                <td><?= $no++ ?></td>
                <td><?= $rekap['nama'] ?></td>
                <td><?= date('d F Y', strtotime($rekap['tanggal_masuk'])) ?></td>
                <td class="text-center"><?= $rekap['jam_masuk'] ?></td>
                <td class="text-center"><?= $rekap['jam_keluar'] ?></td>
                <td class="text-center">
                    <?php if ($rekap['tanggal_keluar'] == '0000-00-00') : ?>
                        <span>0 Jam 0 Menit</span>
                    <?php else : ?>
                        <?= $total_jam_kerja . ' Jam ' . $selisih_menit_kerja . ' Menit' ?>
                    <?php endif; ?>
                </td>
                <td class="text-center"><?= $terlambat_output ?></td>
            </tr>
            <?php endwhile; ?>
            <?php } ?>
        </table>
    </div>
</div>

<!-- Modal Export Excel -->
<div class="modal" id="exampleModal" tabindex="-1">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Export Excel Rekap Presensi Harian</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="<?= base_url('admin/presensi/rekap_harian_excel.php')?>">
        <div class="modal-body">
        
        <div>
            <label for="">Tanggal Awal</label>
            <input type="date" class="form-control" name="tanggal_dari"?>
        </div>

        <div>
            <label for="">Tanggal Akhir</label>
            <input type="date" class="form-control" name="tanggal_sampai"?>
        </div>
        
        </div>
           <div class="modal-footer">
           <button type="button" class="btn me-auto" data-bs-dismiss="modal">Close</button>
           <button type="submit" class="btn btn-primary" data-bs-dismiss="modal">Export</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include('../layout/footer.php'); ?>