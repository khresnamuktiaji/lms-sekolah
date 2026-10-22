<?php
require_once 'config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') { header("Location: index.php"); exit; }
$user_id = (int)$_SESSION['user_id']; $msg = $_GET['msg'] ?? ''; $err = $_GET['err'] ?? '';
$daftar_kelas = getDaftarKelas();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf(); $act = $_POST['action'] ?? '';
    
    if ($act === 'set_lab_gps') {
        $lat = trim($_POST['lat']); $lng = trim($_POST['lng']);
        if(!empty($lat) && !empty($lng)) {
            $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('lab_lat', ?), ('lab_lng', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")->execute([$lat, $lng]);
            header("Location: ?tab=absensi&msg=Lokasi Lab direset!"); exit;
        } else { header("Location: ?tab=absensi&err=Gagal GPS."); exit; }
    }
    elseif ($act === 'toggle_chat_server') {
        $status = $_POST['chat_status'];
        $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('chat_status', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")->execute([$status]);
        if($status === 'inactive') { $pdo->exec("TRUNCATE TABLE chat_messages"); }
        header("Location: ?tab=chat&msg=Chat diperbarui!"); exit;
    }
    elseif ($act === 'export_absensi') {
        header('Content-Type: application/vnd.ms-excel'); header('Content-Disposition: attachment; filename="Rekap_Absensi.xls"');
        echo '<?xml version="1.0"?><?mso-application progid="Excel.Sheet"?><Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
        $dates = $pdo->query("SELECT DISTINCT tanggal FROM absensi ORDER BY tanggal DESC")->fetchAll(PDO::FETCH_COLUMN);
        $students = $pdo->query("SELECT id, nama_lengkap FROM users WHERE role='siswa' ORDER BY nama_lengkap ASC")->fetchAll();
        foreach($dates as $d) {
            echo '<Worksheet ss:Name="'.htmlspecialchars($d, ENT_QUOTES, 'UTF-8').'"><Table>';
            echo '<Row><Cell><Data ss:Type="String">No</Data></Cell><Cell><Data ss:Type="String">Nama</Data></Cell><Cell><Data ss:Type="String">Mapel</Data></Cell><Cell><Data ss:Type="String">Status</Data></Cell></Row>';
            $no = 1; foreach($students as $s) {
                $ab = $pdo->prepare("SELECT mapel FROM absensi WHERE id_siswa=? AND tanggal=?"); $ab->execute([$s['id'], $d]); $dt = $ab->fetch();
                echo '<Row><Cell><Data ss:Type="Number">'.$no++.'</Data></Cell><Cell><Data ss:Type="String">'.htmlspecialchars($s['nama_lengkap'], ENT_QUOTES, 'UTF-8').'</Data></Cell><Cell><Data ss:Type="String">'.htmlspecialchars($dt['mapel']??'-', ENT_QUOTES, 'UTF-8').'</Data></Cell><Cell><Data ss:Type="String">'.($dt?'Hadir':'Tidak').'</Data></Cell></Row>';
            }
            echo '</Table></Worksheet>';
        }
        echo '</Workbook>'; exit;
    }
    elseif ($act === 'export_nilai') {
        header('Content-Type: application/vnd.ms-excel'); header('Content-Disposition: attachment; filename="Rekap_Nilai.xls"');
        echo '<?xml version="1.0"?><?mso-application progid="Excel.Sheet"?><Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
        $mapels = $pdo->query("SELECT DISTINCT mapel FROM pengumpulan")->fetchAll(PDO::FETCH_COLUMN);
        foreach($mapels as $m) {
            echo '<Worksheet ss:Name="'.htmlspecialchars(substr($m,0,30), ENT_QUOTES, 'UTF-8').'"><Table>';
            echo '<Row><Cell><Data ss:Type="String">No</Data></Cell><Cell><Data ss:Type="String">Nama</Data></Cell><Cell><Data ss:Type="String">Tugas</Data></Cell><Cell><Data ss:Type="String">Nilai</Data></Cell></Row>';
            $nl = $pdo->prepare("SELECT p.judul_tugas, p.nilai, u.nama_lengkap FROM pengumpulan p JOIN users u ON p.id_siswa=u.id WHERE p.mapel=?"); $nl->execute([$m]);
            $no = 1; while($n = $nl->fetch()) { echo '<Row><Cell><Data ss:Type="Number">'.$no++.'</Data></Cell><Cell><Data ss:Type="String">'.htmlspecialchars($n['nama_lengkap'], ENT_QUOTES, 'UTF-8').'</Data></Cell><Cell><Data ss:Type="String">'.htmlspecialchars($n['judul_tugas'], ENT_QUOTES, 'UTF-8').'</Data></Cell><Cell><Data ss:Type="String">'.htmlspecialchars($n['nilai']??'Belum', ENT_QUOTES, 'UTF-8').'</Data></Cell></Row>'; }
            echo '</Table></Worksheet>';
        }
        echo '</Workbook>'; exit;
    }
    elseif ($act === 'upload_unified_batch') {
        $tingkat = (int)$_POST['tingkat_kelas']; $mapel = trim($_POST['mapel']); $pertemuan = (int)$_POST['pertemuan_ke']; $deadline = trim($_POST['deadline'] ?? '');

        if(!empty($deadline)) {
            $pdo->prepare("INSERT INTO pertemuan_deadlines (mapel, tingkat_kelas, pertemuan_ke, deadline) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE deadline = VALUES(deadline)")
                ->execute([$mapel, $tingkat, $pertemuan, $deadline]);
        }

        if (!empty($_FILES['file_materi']['name']) && $_FILES['file_materi']['error'] === UPLOAD_ERR_OK) {
            $j_mat = trim($_POST['judul_materi'] ?: "Materi Pertemuan $pertemuan");
            $up = upload_secure_file($_FILES['file_materi'], 'uploads/modul', 15);
            if ($up['status']) { $pdo->prepare("INSERT INTO modul (judul, mapel, tingkat_kelas, pertemuan_ke, file_path, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)")->execute([$j_mat, $mapel, $tingkat, $pertemuan, $up['filename'], $user_id]); }
        }

        $yt_url = trim($_POST['youtube_url'] ?? '');
        if (!empty($yt_url)) {
            $j_vid = trim($_POST['judul_video'] ?: "Video Pertemuan $pertemuan");
            $pdo->prepare("INSERT INTO video_pembelajaran (judul, mapel, tingkat_kelas, pertemuan_ke, tipe_sumber, sumber, uploaded_by) VALUES (?, ?, ?, ?, 'youtube', ?, ?)")->execute([$j_vid, $mapel, $tingkat, $pertemuan, getYoutubeEmbedUrl($yt_url), $user_id]);
        } elseif (!empty($_FILES['file_video']['name']) && $_FILES['file_video']['error'] === UPLOAD_ERR_OK) {
            $j_vid = trim($_POST['judul_video'] ?: "Video Pertemuan $pertemuan");
            $up = upload_secure_file($_FILES['file_video'], 'uploads/modul', 100);
            if ($up['status']) { $pdo->prepare("INSERT INTO video_pembelajaran (judul, mapel, tingkat_kelas, pertemuan_ke, tipe_sumber, sumber, uploaded_by) VALUES (?, ?, ?, ?, 'upload', ?, ?)")->execute([$j_vid, $mapel, $tingkat, $pertemuan, $up['filename'], $user_id]); }
        }

        if (!empty($_FILES['file_tugas_teori']['name']) && $_FILES['file_tugas_teori']['error'] === UPLOAD_ERR_OK) {
            $j_tor = trim($_POST['judul_tugas_teori'] ?: "Tugas Teori Pertemuan $pertemuan");
            $up = upload_secure_file($_FILES['file_tugas_teori'], 'uploads/lkpd', 15);
            if ($up['status']) { $pdo->prepare("INSERT INTO lkpd (judul, mapel, tingkat_kelas, pertemuan_ke, kategori, tipe, file_or_link, uploaded_by) VALUES (?, ?, ?, ?, 'tugas', 'file', ?, ?)")->execute([$j_tor, $mapel, $tingkat, $pertemuan, $up['filename'], $user_id]); }
        }

        if (!empty($_FILES['file_tugas_praktik']['name']) && $_FILES['file_tugas_praktik']['error'] === UPLOAD_ERR_OK) {
            $j_prak = trim($_POST['judul_tugas_praktik'] ?: "Tugas Praktik Pertemuan $pertemuan");
            $up = upload_secure_file($_FILES['file_tugas_praktik'], 'uploads/lkpd', 15);
            if ($up['status']) { $pdo->prepare("INSERT INTO lkpd (judul, mapel, tingkat_kelas, pertemuan_ke, kategori, tipe, file_or_link, uploaded_by) VALUES (?, ?, ?, ?, 'praktik', 'file', ?, ?)")->execute([$j_prak, $mapel, $tingkat, $pertemuan, $up['filename'], $user_id]); }
        }

        header("Location: ?tab=materi&msg=Paket Pembelajaran & Deadline Disimpan!"); exit;
    }
    elseif ($act === 'edit_lkpd') {
        $pdo->prepare("UPDATE lkpd SET judul = ?, mapel = ?, tingkat_kelas = ?, pertemuan_ke = ?, kategori = ? WHERE id = ?")->execute([trim($_POST['judul']), trim($_POST['mapel']), (int)$_POST['tingkat_kelas'], (int)$_POST['pertemuan_ke'], $_POST['kategori'], (int)$_POST['id_lkpd']]);
        header("Location: ?tab=materi&msg=Tugas diperbarui!"); exit;
    }
    elseif ($act === 'delete_lkpd') { $pdo->prepare("DELETE FROM lkpd WHERE id = ?")->execute([(int)$_POST['id_lkpd']]); header("Location: ?tab=materi&msg=Dihapus!"); exit; }
    elseif ($act === 'toggle_status_lkpd') { $pdo->prepare("UPDATE lkpd SET status = IF(status='aktif','nonaktif','aktif') WHERE id = ?")->execute([(int)$_POST['id_lkpd']]); header("Location: ?tab=materi&msg=Status diubah!"); exit; }
    elseif ($act === 'toggle_status_modul') { $pdo->prepare("UPDATE modul SET status = IF(status='aktif','nonaktif','aktif') WHERE id = ?")->execute([(int)$_POST['id_modul']]); header("Location: ?tab=materi&msg=Status diubah!"); exit; }
    elseif ($act === 'delete_modul') { $pdo->prepare("DELETE FROM modul WHERE id = ?")->execute([(int)$_POST['id_modul']]); header("Location: ?tab=materi&msg=Dihapus!"); exit; }
    elseif ($act === 'toggle_status_video') { $pdo->prepare("UPDATE video_pembelajaran SET status = IF(status='aktif','nonaktif','aktif') WHERE id = ?")->execute([(int)$_POST['id_video']]); header("Location: ?tab=materi&msg=Status diubah!"); exit; }
    elseif ($act === 'delete_video') { $pdo->prepare("DELETE FROM video_pembelajaran WHERE id = ?")->execute([(int)$_POST['id_video']]); header("Location: ?tab=materi&msg=Dihapus!"); exit; }
    elseif ($act === 'edit_user') {
        $uid = (int)$_POST['id_user']; $u_nama = trim($_POST['nama_lengkap']); $u_role = $_POST['role']; $u_kelas = trim($_POST['kelas']); $u_telp = trim($_POST['no_telp']); $u_email = trim($_POST['email']); $u_pwd = $_POST['password'];
        if (!empty($u_pwd)) { $pdo->prepare("UPDATE users SET nama_lengkap = ?, role = ?, kelas = ?, no_telp = ?, email = ?, password = ?, failed_attempts = 0, lockout_time = NULL WHERE id = ?")->execute([$u_nama, $u_role, $u_kelas, $u_telp, $u_email, password_hash($u_pwd, PASSWORD_DEFAULT), $uid]); } 
        else { $pdo->prepare("UPDATE users SET nama_lengkap = ?, role = ?, kelas = ?, no_telp = ?, email = ?, failed_attempts = 0, lockout_time = NULL WHERE id = ?")->execute([$u_nama, $u_role, $u_kelas, $u_telp, $u_email, $uid]); }
        header("Location: ?tab=users&msg=User diperbarui!"); exit;
    }
    elseif ($act === 'delete_user') {
        $uid = (int)$_POST['id_user']; if($uid !== $user_id) { $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$uid]); header("Location: ?tab=users&msg=User dihapus!"); }
        header("Location: ?tab=users"); exit;
    }
    elseif ($act === 'edit_absensi') {
        $pdo->prepare("UPDATE absensi SET mapel = ?, tanggal = ?, keterangan = ?, catatan = ? WHERE id = ?")->execute([trim($_POST['mapel']), trim($_POST['tanggal']), $_POST['keterangan'], trim($_POST['catatan']), (int)$_POST['id_absensi']]);
        header("Location: ?tab=absensi&msg=Absen diperbarui!"); exit;
    }
    elseif ($act === 'delete_absensi') { $pdo->prepare("DELETE FROM absensi WHERE id = ?")->execute([(int)$_POST['id_absensi']]); header("Location: ?tab=absensi&msg=Dihapus!"); exit; }
    elseif ($act === 'nilai_tugas') { $pdo->prepare("UPDATE pengumpulan SET nilai = ?, catatan_guru = ? WHERE id = ?")->execute([(int)$_POST['nilai'], trim($_POST['catatan_guru']), (int)$_POST['id_tugas']]); header("Location: ?tab=penilaian&msg=Ternilai!"); exit; }
}
$u = $pdo->prepare("SELECT * FROM users WHERE id = ?"); $u->execute([$user_id]); $user = $u->fetch(); $tab = $_GET['tab'] ?? 'materi';
$server_conf = getServerLocation($pdo);
$selected_chat_kelas = $_GET['chat_kelas'] ?? '10 TKJ 1';
?>
<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Dashboard Guru</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"><style>.vid-box{position:relative;width:100%;padding-bottom:56.25%;background:#111;border-radius:8px;overflow:hidden;}.vid-box video{position:absolute;top:0;left:0;width:100%;height:100%;object-fit:contain;}</style></head>
<body class="bg-light">
<nav class="navbar navbar-dark bg-dark px-3 py-2 flex-wrap"><a class="navbar-brand text-warning fw-bold fs-6" href="#">LMS GURU v11.18</a><div class="text-white small ms-auto d-flex align-items-center gap-2"><span><?=e($user['nama_lengkap'])?></span> <a href="logout.php" class="btn btn-sm btn-danger py-0 px-2">Logout</a></div></nav>
<div class="container-fluid mt-3 px-2 px-md-4">
    <div class="d-flex gap-2 overflow-auto mb-3 pb-2 border-bottom flex-nowrap">
        <a href="?tab=materi" class="btn btn-<?= $tab=='materi'?'primary':'outline-primary'?> btn-sm text-nowrap">📚 Kelola Materi & Tugas</a> 
        <a href="?tab=penilaian" class="btn btn-<?= $tab=='penilaian'?'primary':'outline-primary'?> btn-sm text-nowrap">📝 Penilaian</a> 
        <a href="?tab=screen" class="btn btn-<?= $tab=='screen'?'primary':'outline-primary'?> btn-sm text-nowrap">🖥️ Broadcast</a> 
        <a href="?tab=absensi" class="btn btn-<?= $tab=='absensi'?'primary':'outline-primary'?> btn-sm text-nowrap">📸 Absensi</a>
        <a href="?tab=chat" class="btn btn-<?= $tab=='chat'?'primary':'outline-primary'?> btn-sm text-nowrap">💬 Chat Kelas</a>
        <a href="?tab=users" class="btn btn-<?= $tab=='users'?'primary':'outline-primary'?> btn-sm text-nowrap">👥 Kelola Akun</a>
    </div>
    <?php if($msg) echo "<div class='alert alert-success p-2 small'>".e($msg)."</div>"; if($err) echo "<div class='alert alert-danger p-2 small'>".e($err)."</div>"; ?>

    <?php if($tab === 'materi'): ?>
        <div class="card p-3 border-0 shadow-sm mb-3">
            <h5 class="fw-bold text-primary">📤 Form Upload Terpadu & Tenggat Waktu (Deadline)</h5>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?=$_SESSION['csrf_token']?>"><input type="hidden" name="action" value="upload_unified_batch">
                <div class="row g-3 bg-light p-3 rounded border">
                    <div class="col-md-3"><label class="small fw-bold">Tingkat Kelas:</label><select name="tingkat_kelas" class="form-select select-kelas form-select-sm" required><option value="">Pilih Kelas...</option><option value="10">Kelas 10</option><option value="11">Kelas 11</option><option value="12">Kelas 12</option></select></div>
                    <div class="col-md-3"><label class="small fw-bold">Mata Pelajaran:</label><select name="mapel" class="form-select select-mapel form-select-sm" required><option value="">Pilih Mapel...</option></select></div>
                    <div class="col-md-3"><label class="small fw-bold">Pertemuan Ke:</label><input type="number" name="pertemuan_ke" class="form-control form-control-sm" required min="1" max="10" value="1"></div>
                    <div class="col-md-3"><label class="small fw-bold text-danger">⏰ Tenggat Waktu (Deadline):</label><input type="datetime-local" name="deadline" class="form-control form-control-sm"></div>
                    <hr class="my-2">
                    <div class="col-md-6"><label class="small fw-bold text-success">📚 Materi PDF (Opsional)</label><input type="text" name="judul_materi" class="form-control form-control-sm mb-1" placeholder="Judul Materi..."><input type="file" name="file_materi" class="form-control form-control-sm" accept=".pdf"></div>
                    <div class="col-md-6"><label class="small fw-bold text-danger">🎥 Video / YouTube (Opsional)</label><input type="text" name="judul_video" class="form-control form-control-sm mb-1" placeholder="Judul Video..."><input type="text" name="youtube_url" class="form-control form-control-sm mb-1" placeholder="Link YouTube..."><input type="file" name="file_video" class="form-control form-control-sm" accept=".mp4,.webm"></div>
                    <div class="col-md-6"><label class="small fw-bold text-primary">📝 Tugas Teori (Opsional)</label><input type="text" name="judul_tugas_teori" class="form-control form-control-sm mb-1" placeholder="Judul Tugas Teori..."><input type="file" name="file_tugas_teori" class="form-control form-control-sm"></div>
                    <div class="col-md-6"><label class="small fw-bold text-warning text-dark">⚙️ Tugas Praktik (Opsional)</label><input type="text" name="judul_tugas_praktik" class="form-control form-control-sm mb-1" placeholder="Judul Tugas Praktik..."><input type="file" name="file_tugas_praktik" class="form-control form-control-sm"></div>
                    <div class="col-md-12 mt-3"><button class="btn btn-success btn-sm w-100 fw-bold py-2">🚀 Simpan & Publikasikan Paket Pertemuan</button></div>
                </div>
            </form>
        </div>
        <script>
        const mapelData={'10':['DTJKT','MJT'],'11':['PPJ'],'12':['KPJ','CYBER']}; 
        document.querySelectorAll('.select-kelas').forEach((el, index) => { 
            el.addEventListener('change', function() { 
                let ms = document.querySelectorAll('.select-mapel')[index]; 
                ms.innerHTML = '<option value="">Pilih Mapel...</option>'; 
                if(this.value && mapelData[this.value]) { mapelData[this.value].forEach(m => { let o = document.createElement('option'); o.value=m; o.textContent=m; ms.appendChild(o); }); } 
            }); 
        });
        </script>

        <div class="card p-3 border-0 shadow-sm mt-3"><h5 class="fw-bold">📚 Daftar Materi, Video & Tugas Berjalan</h5><div class="table-responsive mt-2"><table class="table table-bordered small align-middle"><thead class="table-light"><tr><th>Kelas & Mapel</th><th>Pertemuan</th><th>Judul & Tipe</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
        <?php foreach($pdo->query("SELECT id, judul, mapel, tingkat_kelas, pertemuan_ke, status, 'Materi' as tipe FROM modul UNION SELECT id, judul, mapel, tingkat_kelas, pertemuan_ke, status, CONCAT('Video (', UPPER(tipe_sumber), ')') as tipe FROM video_pembelajaran UNION SELECT id, judul, mapel, tingkat_kelas, pertemuan_ke, status, CONCAT('Tugas (', UPPER(kategori), ')') as tipe FROM lkpd ORDER BY tingkat_kelas ASC, mapel ASC, pertemuan_ke ASC") as $row): ?>
        <tr><td><b>Kelas <?=e($row['tingkat_kelas'])?></b> - <?=e($row['mapel'])?></td><td>Pertemuan <?=e($row['pertemuan_ke'])?></td><td><b><?=e($row['tipe'])?>:</b> <?=e($row['judul'])?></td><td><?= $row['status']==='aktif'?'<span class="badge bg-success">Aktif</span>':'<span class="badge bg-secondary">Nonaktif</span>' ?></td><td>
            <div class="d-flex gap-1">
                <?php if(strpos($row['tipe'], 'Tugas')!==false): ?>
                    <button class="btn btn-sm btn-warning py-0 px-2" onclick='editTugas(<?=json_encode($row)?>)'>Edit</button>
                    <form method="POST" class="d-inline"><input type="hidden" name="csrf_token" value="<?=$_SESSION['csrf_token']?>"><input type="hidden" name="action" value="toggle_status_lkpd"><input type="hidden" name="id_lkpd" value="<?=$row['id']?>"><button class="btn btn-sm btn-info py-0 px-2 text-white">Status</button></form>
                    <form method="POST" onsubmit="return confirm('Hapus?');" class="d-inline"><input type="hidden" name="csrf_token" value="<?=$_SESSION['csrf_token']?>"><input type="hidden" name="action" value="delete_lkpd"><input type="hidden" name="id_lkpd" value="<?=$row['id']?>"><button class="btn btn-sm btn-danger py-0 px-2">Hapus</button></form>
                <?php elseif(strpos($row['tipe'], 'Video')!==false): ?>
                    <form method="POST" class="d-inline"><input type="hidden" name="csrf_token" value="<?=$_SESSION['csrf_token']?>"><input type="hidden" name="action" value="toggle_status_video"><input type="hidden" name="id_video" value="<?=$row['id']?>"><button class="btn btn-sm btn-info py-0 px-2 text-white">Status</button></form>
                    <form method="POST" onsubmit="return confirm('Hapus?');" class="d-inline"><input type="hidden" name="csrf_token" value="<?=$_SESSION['csrf_token']?>"><input type="hidden" name="action" value="delete_video"><input type="hidden" name="id_video" value="<?=$row['id']?>"><button class="btn btn-sm btn-danger py-0 px-2">Hapus</button></form>
                <?php else: ?>
                    <form method="POST" class="d-inline"><input type="hidden" name="csrf_token" value="<?=$_SESSION['csrf_token']?>"><input type="hidden" name="action" value="toggle_status_modul"><input type="hidden" name="id_modul" value="<?=$row['id']?>"><button class="btn btn-sm btn-info py-0 px-2 text-white">Status</button></form>
                    <form method="POST" onsubmit="return confirm('Hapus?');" class="d-inline"><input type="hidden" name="csrf_token" value="<?=$_SESSION['csrf_token']?>"><input type="hidden" name="action" value="delete_modul"><input type="hidden" name="id_modul" value="<?=$row['id']?>"><button class="btn btn-sm btn-danger py-0 px-2">Hapus</button></form>
                <?php endif; ?>
            </div>
        </td></tr>
        <?php endforeach; ?></tbody></table></div></div>
        
        <div id="modalEditTugas" class="modal" tabindex="-1" style="display:none; background:rgba(0,0,0,0.5); position:fixed; top:0; left:0; width:100%; height:100%; z-index:1050;"><div class="modal-dialog modal-dialog-centered"><div class="modal-content p-3"><h6 class="fw-bold">Edit Tugas</h6>
        <form method="POST"><input type="hidden" name="csrf_token" value="<?=$_SESSION['csrf_token']?>"><input type="hidden" name="action" value="edit_lkpd"><input type="hidden" name="id_lkpd" id="mod_edit_id_lkpd">
        <label class="small fw-bold">Judul:</label><input type="text" name="judul" id="mod_edit_judul" class="form-control mb-2 form-control-sm" required>
        <label class="small fw-bold">Kelas:</label><input type="number" name="tingkat_kelas" id="mod_edit_tingkat" class="form-control mb-2 form-control-sm" required>
        <label class="small fw-bold">Mapel:</label><input type="text" name="mapel" id="mod_edit_mapel" class="form-control mb-2 form-control-sm" required>
        <label class="small fw-bold">Pertemuan:</label><input type="number" name="pertemuan_ke" id="mod_edit_pertemuan" class="form-control mb-2 form-control-sm" required>
        <label class="small fw-bold">Kategori:</label><select name="kategori" id="mod_edit_kategori" class="form-select mb-3 form-control-sm"><option value="tugas">Teori</option><option value="praktik">Praktik</option></select>
        <div class="d-flex gap-2"><button type="submit" class="btn btn-success w-100 btn-sm">Simpan</button><button type="button" class="btn btn-secondary w-100 btn-sm" onclick="document.getElementById('modalEditTugas').style.display='none'">Batal</button></div></form></div></div></div>
        <script>function editTugas(t){ document.getElementById('mod_edit_id_lkpd').value=t.id; document.getElementById('mod_edit_judul').value=t.judul; document.getElementById('mod_edit_tingkat').value=t.tingkat_kelas; document.getElementById('mod_edit_mapel').value=t.mapel; document.getElementById('mod_edit_pertemuan').value=t.pertemuan_ke; document.getElementById('modalEditTugas').style.display='block'; }</script>

    <?php elseif($tab === 'penilaian'): 
        $fk = $_GET['f_kelas_penil'] ?? ''; $fm = $_GET['f_mapel_penil'] ?? ''; $q = trim($_GET['q_penil'] ?? '');
        $sql = "SELECT p.*, u.nama_lengkap, u.kelas FROM pengumpulan p JOIN users u ON p.id_siswa = u.id WHERE 1=1"; $prms = [];
        if($fk) { $sql .= " AND u.kelas = ?"; $prms[] = $fk; }
        if($fm) { $sql .= " AND p.mapel = ?"; $prms[] = $fm; }
        if($q) { $sql .= " AND (u.nama_lengkap LIKE ? OR p.judul_tugas LIKE ?)"; $prms[] = "%$q%"; $prms[] = "%$q%"; }
        $sql .= " ORDER BY p.id DESC LIMIT 50"; $st = $pdo->prepare($sql); $st->execute($prms);
    ?>
        <div class="card p-3 border-0 shadow-sm"><div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3"><h5 class="fw-bold mb-0">Penilaian Tugas Siswa</h5><form method="POST" target="_blank"><input type="hidden" name="csrf_token" value="<?=$_SESSION['csrf_token']?>"><input type="hidden" name="action" value="export_nilai"><button class="btn btn-success btn-sm fw-bold">📥 Export Excel</button></form></div>
        <form method="GET" class="row g-2 mb-3 bg-light p-2 rounded border"><input type="hidden" name="tab" value="penilaian">
            <div class="col-md-3"><select name="f_kelas_penil" class="form-select form-select-sm" onchange="this.form.submit()"><option value="">Semua Kelas</option><?php foreach($daftar_kelas as $dk): ?><option value="<?=$dk?>" <?=$fk===$dk?'selected':''?>><?=$dk?></option><?php endforeach; ?></select></div>
            <div class="col-md-3"><select name="f_mapel_penil" class="form-select form-select-sm" onchange="this.form.submit()"><option value="">Semua Mapel</option><?php foreach(['DTJKT','MJT','PPJ','KPJ','CYBER'] as $m): ?><option value="<?=$m?>" <?=$fm===$m?'selected':''?>><?=$m?></option><?php endforeach; ?></select></div>
            <div class="col-md-4"><input type="text" name="q_penil" value="<?=e($q)?>" class="form-control form-control-sm" placeholder="Cari siswa/tugas..."></div>
            <div class="col-md-2"><button class="btn btn-primary btn-sm w-100">Cari</button></div>
        </form>
        <div class="table-responsive"><table class="table table-bordered small align-middle"><thead class="table-light"><tr><th>Siswa</th><th>Mapel / Tugas</th><th>Pengiriman</th><th>Nilai</th><th>Aksi</th></tr></thead><tbody>
        <?php while($row = $st->fetch()): 
            $tipe_s = $row['tipe_pengumpulan'] ?? 'file';
        ?>
        <tr>
            <td><b><?=e($row['nama_lengkap'])?></b><br><small><?=e($row['kelas'])?></small></td>
            <td><?=e($row['mapel'])?><br><small><?=e($row['judul_tugas'])?></small></td>
            <td>
                <?php if($tipe_s === 'teks'): ?>
                    <span class="badge bg-info text-dark mb-1">Jawaban Teks</span><br>
                    <button class="btn btn-sm btn-outline-dark py-0 px-2" onclick="bukaModalTeks(<?=htmlspecialchars(json_encode($row['jawaban_teks']), ENT_QUOTES, 'UTF-8')?>, '<?=e($row['nama_lengkap'])?>')">📖 Lihat Jawaban</button>
                <?php else: ?>
                    <span class="badge bg-secondary mb-1">Berkas File</span><br>
                    <a href="uploads/tugas/<?=e($row['file_path'])?>" download class="badge bg-success text-decoration-none">Download File</a>
                <?php endif; ?>
            </td>
            <td><span class="badge bg-<?= $row['nilai']!==null?'success':'warning' ?>"><?= $row['nilai']??'Belum'?></span></td>
            <td><button class="btn btn-sm btn-primary py-0 px-2" onclick="bukaModalNilai(<?=$row['id']?>, '<?=$row['nilai']??''?>', '<?=e($row['catatan_guru'])?>')">Nilai</button></td>
        </tr>
        <?php endwhile; ?></tbody></table></div></div>

        <div id="modalLihatTeks" class="modal" tabindex="-1" style="display:none; background:rgba(0,0,0,0.5); position:fixed; top:0; left:0; width:100%; height:100%; z-index:1050;"><div class="modal-dialog modal-dialog-centered"><div class="modal-content p-3"><h6 class="fw-bold mb-2">Jawaban Teks dari: <span id="lbl_nama_siswa" class="text-primary"></span></h6><div class="p-3 bg-light border rounded mb-3" id="txt_jawaban_siswa" style="max-height:250px; overflow-y:auto; white-space:pre-wrap; font-size:13px;"></div><button type="button" class="btn btn-secondary w-100 btn-sm" onclick="document.getElementById('modalLihatTeks').style.display='none'">Tutup</button></div></div></div>
        <script>
        function bukaModalTeks(teks, nama){
            document.getElementById('lbl_nama_siswa').textContent = nama;
            document.getElementById('txt_jawaban_siswa').textContent = teks;
            document.getElementById('modalLihatTeks').style.display = 'block';
        }
        </script>

        <div id="modalNilai" class="modal" tabindex="-1" style="display:none; background:rgba(0,0,0,0.5); position:fixed; top:0; left:0; width:100%; height:100%; z-index:1050;"><div class="modal-dialog modal-dialog-centered"><div class="modal-content p-3"><h6 class="fw-bold">Input Nilai</h6><form method="POST"><input type="hidden" name="csrf_token" value="<?=$_SESSION['csrf_token']?>"><input type="hidden" name="action" value="nilai_tugas"><input type="hidden" name="id_tugas" id="modal_id_tugas"><input type="number" name="nilai" id="modal_nilai" class="form-control mb-2" required placeholder="0-100"><textarea name="catatan_guru" id="modal_catatan" class="form-control mb-2" placeholder="Catatan"></textarea><div class="d-flex gap-2"><button type="submit" class="btn btn-success w-100">Simpan</button><button type="button" class="btn btn-secondary w-100" onclick="document.getElementById('modalNilai').style.display='none'">Batal</button></div></form></div></div></div>
        <script>function bukaModalNilai(id, n, c){ document.getElementById('modal_id_tugas').value=id; document.getElementById('modal_nilai').value=n; document.getElementById('modal_catatan').value=c; document.getElementById('modalNilai').style.display='block'; }</script>

    <?php elseif($tab === 'screen'): ?>
        <div class="card p-3 border-0 shadow-sm"><h5 class="fw-bold">Broadcast Screen</h5><button class="btn btn-success fw-bold mb-3" onclick="startShare()">Mulai Share</button><div class="vid-box"><video id="local-screen" autoplay playsinline muted></video></div></div>
        <script>let pc = new RTCPeerConnection({ iceServers: [{ urls: 'stun:stun.l.google.com:19302' }] }); function startShare(){ navigator.mediaDevices.getDisplayMedia({video:true,audio:false}).then(s=>{ document.getElementById('local-screen').srcObject=s; s.getTracks().forEach(t=>pc.addTrack(t,s)); return pc.createOffer(); }).then(o=>pc.setLocalDescription(o)).then(()=>{ fetch('live_stream.php?action=send',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({type:'offer',payload:pc.localDescription})}); }); } pc.onicecandidate=e=>{if(e.candidate)fetch('live_stream.php?action=send',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({type:'candidate',payload:e.candidate})});};setInterval(()=>{fetch('live_stream.php?action=fetch').then(r=>r.json()).then(m=>{m.forEach(d=>{let p=JSON.parse(d.payload);if(d.type==='answer')pc.setRemoteDescription(new RTCSessionDescription(p));else if(d.type==='candidate')pc.addIceCandidate(new RTCIceCandidate(p));});});},800);</script>

    <?php elseif($tab === 'absensi'): ?>
        <div class="card p-3 border-0 shadow-sm mb-3"><h6 class="fw-bold text-primary">📍 Reset Koordinat Lab</h6><form method="POST" id="fc"><input type="hidden" name="csrf_token" value="<?=$_SESSION['csrf_token']?>"><input type="hidden" name="action" value="set_lab_gps"><input type="hidden" name="lat" id="slat"><input type="hidden" name="lng" id="slng"><button type="button" class="btn btn-info btn-sm text-white fw-bold" onclick="navigator.geolocation.getCurrentPosition(p=>{document.getElementById('slat').value=p.coords.latitude;document.getElementById('slng').value=p.coords.longitude;document.getElementById('fc').submit();})">📍 Set Lokasi Saat Ini</button></form></div>
        <div class="card p-3 border-0 shadow-sm"><div class="d-flex justify-content-between align-items-center"><h5 class="fw-bold mb-0">Data Absensi</h5><form method="POST" target="_blank"><input type="hidden" name="csrf_token" value="<?=$_SESSION['csrf_token']?>"><input type="hidden" name="action" value="export_absensi"><button class="btn btn-success btn-sm fw-bold">📥 Export Excel</button></form></div>
        <div class="table-responsive mt-3"><table class="table table-bordered small align-middle"><thead class="table-light"><tr><th>Tanggal</th><th>Siswa</th><th>Mapel</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
        <?php foreach($pdo->query("SELECT a.*, u.nama_lengkap, u.kelas FROM absensi a JOIN users u ON a.id_siswa=u.id ORDER BY a.tanggal DESC LIMIT 50") as $ab): ?>
        <tr><td><?=e($ab['tanggal'])?></td><td><b><?=e($ab['nama_lengkap'])?></b><br><small><?=e($ab['kelas'])?></small></td><td><?=e($ab['mapel'])?></td><td><span class="badge bg-success"><?=e($ab['keterangan'])?></span></td><td><div class="d-flex gap-1"><button class="btn btn-sm btn-warning py-0 px-2" onclick='editAbsen(<?=json_encode($ab)?>)'>Edit</button><form method="POST" onsubmit="return confirm('Hapus?');" class="d-inline"><input type="hidden" name="csrf_token" value="<?=$_SESSION['csrf_token']?>"><input type="hidden" name="action" value="delete_absensi"><input type="hidden" name="id_absensi" value="<?=$ab['id']?>"><button class="btn btn-sm btn-danger py-0 px-2">Hapus</button></form></div></td></tr>
        <?php endforeach; ?></tbody></table></div></div>
        <div id="modalAbsen" class="modal" tabindex="-1" style="display:none; background:rgba(0,0,0,0.5); position:fixed; top:0; left:0; width:100%; height:100%; z-index:1050;"><div class="modal-dialog modal-dialog-centered"><div class="modal-content p-3"><h6 class="fw-bold">Edit Absen</h6><form method="POST"><input type="hidden" name="csrf_token" value="<?=$_SESSION['csrf_token']?>"><input type="hidden" name="action" value="edit_absensi"><input type="hidden" name="id_absensi" id="mod_id_abs"><label class="small fw-bold">Tanggal:</label><input type="date" name="tanggal" id="mod_abs_tgl" class="form-control mb-2 form-control-sm" required><label class="small fw-bold">Mapel:</label><input type="text" name="mapel" id="mod_abs_mapel" class="form-control mb-2 form-control-sm" required><label class="small fw-bold">Keterangan:</label><select name="keterangan" id="mod_abs_ket" class="form-select mb-2 form-control-sm"><option value="Hadir">Hadir</option><option value="Sakit">Sakit</option><option value="Izin">Izin</option><option value="Alpha">Alpha</option></select><label class="small fw-bold">Catatan:</label><textarea name="catatan" id="mod_abs_cat" class="form-control mb-3 form-control-sm"></textarea><div class="d-flex gap-2"><button type="submit" class="btn btn-success w-100 btn-sm">Simpan</button><button type="button" class="btn btn-secondary w-100 btn-sm" onclick="document.getElementById('modalAbsen').style.display='none'">Batal</button></div></form></div></div></div>
        <script>function editAbsen(ab){ document.getElementById('mod_id_abs').value=ab.id; document.getElementById('mod_abs_tgl').value=ab.tanggal; document.getElementById('mod_abs_mapel').value=ab.mapel; document.getElementById('mod_abs_ket').value=ab.keterangan; document.getElementById('mod_abs_cat').value=ab.catatan||''; document.getElementById('modalAbsen').style.display='block'; }</script>

    <?php elseif($tab === 'chat'): ?>
        <div class="card p-3 border-0 shadow-sm"><div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2"><h5 class="fw-bold mb-0">💬 Chat Kelas</h5><form method="POST" class="d-flex gap-2 align-items-center"><input type="hidden" name="csrf_token" value="<?=$_SESSION['csrf_token']?>"><input type="hidden" name="action" value="toggle_chat_server"><select name="chat_status" class="form-select form-select-sm" onchange="this.form.submit()"><option value="active" <?=$server_conf['chat_status']==='active'?'selected':''?>>🟢 Aktif</option><option value="inactive" <?=$server_conf['chat_status']==='inactive'?'selected':''?>>🔴 Mati</option></select></form></div>
        <div class="mb-3"><select class="form-select form-select-sm w-auto" onchange="location.href='?tab=chat&chat_kelas='+this.value"><?php foreach($daftar_kelas as$dk): ?><option value="<?=$dk?>" <?=$selected_chat_kelas===$dk?'selected':''?>><?=$dk?></option><?php endforeach; ?></select></div>
        <div id="chat-box" class="border rounded p-3 mb-3 bg-white" style="height:320px; overflow-y:auto; font-size:14px;"></div><div class="input-group"><input type="text" id="chat-input" class="form-control" placeholder="Pesan..."><button class="btn btn-primary" onclick="sendChatTeacher()">Kirim</button></div></div>
        <script>
        let ck = "<?=$selected_chat_kelas?>";
        function fetchChatTeacher() { fetch('chat_endpoint.php?action=fetch&kelas='+encodeURIComponent(ck)).then(r=>r.json()).then(res=>{ let b=document.getElementById('chat-box'); if(res.status==='inactive'){b.innerHTML='<p class="text-danger text-center">Mati.</p>';return;} let h=''; res.msgs.forEach(m=>{let me=m.sender_id==<?=$user_id?>;h+=`<div class="mb-2 ${me?'text-end':''}"><small class="text-muted fw-bold">${m.sender_nama}</small><div class="d-inline-block p-2 rounded ${me?'bg-primary text-white':'bg-light border'} text-start">${m.pesan}</div></div>`;}); b.innerHTML=h||'<p class="text-muted text-center">Kosong.</p>'; b.scrollTop=b.scrollHeight; }); }
        function sendChatTeacher() { let i=document.getElementById('chat-input'), t=i.value.trim(); if(!t)return; fetch('chat_endpoint.php?action=send&kelas='+encodeURIComponent(ck),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({pesan:t})}).then(r=>r.json()).then(res=>{if(res.status==='success'){i.value='';fetchChatTeacher();}}); }
        setInterval(fetchChatTeacher, 2000); fetchChatTeacher();
        </script>

    <?php elseif($tab === 'users'):
        $fr = $_GET['f_role_user'] ?? '';
        $fk = $_GET['f_kelas_user'] ?? '';
        $qu = trim($_GET['q_user'] ?? '');
        $sql = "SELECT * FROM users WHERE 1=1"; 
        $prms = [];
        if($fr) { $sql .= " AND role = ?"; $prms[] = $fr; }
        if($fk) { $sql .= " AND kelas = ?"; $prms[] = $fk; }
        if($qu) { $sql .= " AND (nama_lengkap LIKE ? OR username LIKE ?)"; $prms[] = "%$qu%"; $prms[] = "%$qu%"; }
        $sql .= " ORDER BY role ASC, nama_lengkap ASC"; 
        $st = $pdo->prepare($sql); 
        $st->execute($prms);
    ?>
        <div class="card p-3 border-0 shadow-sm"><h5 class="fw-bold mb-3">Manajemen Akun (Admin)</h5>
        <form method="GET" class="row g-2 mb-3 bg-light p-2 rounded border"><input type="hidden" name="tab" value="users">
            <div class="col-md-3"><select name="f_role_user" class="form-select form-select-sm" onchange="this.form.submit()"><option value="">Semua Role</option><option value="siswa" <?=$fr==='siswa'?'selected':''?>>Siswa</option><option value="guru" <?=$fr==='guru'?'selected':''?>>Guru</option></select></div>
            <div class="col-md-3"><select name="f_kelas_user" class="form-select form-select-sm" onchange="this.form.submit()"><option value="">Semua Kelas</option><?php foreach($daftar_kelas as $dk): ?><option value="<?=$dk?>" <?=$fk===$dk?'selected':''?>><?=$dk?></option><?php endforeach; ?></select></div>
            <div class="col-md-4"><input type="text" name="q_user" value="<?=e($qu)?>" class="form-control form-control-sm" placeholder="Cari nama / username..."></div>
            <div class="col-md-2"><button class="btn btn-primary btn-sm w-100">Cari</button></div>
        </form>
        <div class="table-responsive"><table class="table table-bordered small align-middle"><thead class="table-light"><tr><th>Username</th><th>Nama Lengkap</th><th>Role / Kelas</th><th>Aksi</th></tr></thead><tbody>
        <?php while($ur = $st->fetch()): ?>
        <tr><td><?=e($ur['username'])?></td><td><b><?=e($ur['nama_lengkap'])?></b><br><small><?=e($ur['email'])?></small></td><td><span class="badge bg-secondary"><?=strtoupper($ur['role'])?></span><br><small><?=e($ur['kelas']??'-')?></small></td><td><div class="d-flex gap-1"><button class="btn btn-sm btn-warning py-0 px-2" onclick='editUser(<?=json_encode($ur)?>)'>Edit</button><form method="POST" onsubmit="return confirm('Hapus?');" class="d-inline"><input type="hidden" name="csrf_token" value="<?=$_SESSION['csrf_token']?>"><input type="hidden" name="action" value="delete_user"><input type="hidden" name="id_user" value="<?=$ur['id']?>"><button class="btn btn-sm btn-danger py-0 px-2">Hapus</button></form></div></td></tr>
        <?php endwhile; ?></tbody></table></div></div>
        
        <div id="modalUser" class="modal" tabindex="-1" style="display:none; background:rgba(0,0,0,0.5); position:fixed; top:0; left:0; width:100%; height:100%; z-index:1050;"><div class="modal-dialog modal-dialog-centered"><div class="modal-content p-3"><h6 class="fw-bold">Edit Akun (Admin Panel)</h6>
        <form method="POST"><input type="hidden" name="csrf_token" value="<?=$_SESSION['csrf_token']?>"><input type="hidden" name="action" value="edit_user"><input type="hidden" name="id_user" id="mod_id_user">
        <label class="small fw-bold">Username:</label><input type="text" id="mod_username" class="form-control mb-2 form-control-sm" readonly disabled>
        <label class="small fw-bold">Nama Lengkap (Admin dapat mengubah):</label><input type="text" name="nama_lengkap" id="mod_nama" class="form-control mb-2 form-control-sm" required>
        <label class="small fw-bold">Role:</label><select name="role" id="mod_role" class="form-select mb-2 form-control-sm"><option value="siswa">Siswa</option><option value="guru">Guru</option></select>
        <label class="small fw-bold">Kelas (Admin dapat mengubah):</label><select name="kelas" id="mod_kelas" class="form-select mb-2 form-control-sm"><?php foreach($daftar_kelas as $dk): ?><option value="<?=$dk?>"><?=$dk?></option><?php endforeach; ?></select>
        <label class="small fw-bold">No WhatsApp:</label><input type="text" name="no_telp" id="mod_telp" class="form-control mb-2 form-control-sm">
        <label class="small fw-bold">Email:</label><input type="email" name="email" id="mod_email" class="form-control mb-2 form-control-sm">
        <label class="small fw-bold">Reset Password Baru (Opsional):</label><input type="password" name="password" class="form-control mb-3 form-control-sm" placeholder="Kosongkan jika tidak diubah">
        <div class="d-flex gap-2"><button type="submit" class="btn btn-success w-100 btn-sm">Simpan</button><button type="button" class="btn btn-secondary w-100 btn-sm" onclick="document.getElementById('modalUser').style.display='none'">Batal</button></div></form></div></div></div>
        <script>function editUser(u){ document.getElementById('mod_id_user').value=u.id; document.getElementById('mod_username').value=u.username; document.getElementById('mod_nama').value=u.nama_lengkap; document.getElementById('mod_role').value=u.role; document.getElementById('mod_kelas').value=u.kelas||'10 TKJ 1'; document.getElementById('mod_telp').value=u.no_telp||''; document.getElementById('mod_email').value=u.email||''; document.getElementById('modalUser').style.display='block'; }</script>
    <?php endif; ?>
</div><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script></body></html>