<?php
require_once 'config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'siswa') {
    header("Location: index.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$student = $stmt->fetch();
$kelas_siswa = $student['kelas'];
$msg = $_GET['msg'] ?? ''; $err = $_GET['err'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    // Absensi Wajah & Geofencing GPS
    if ($action === 'absen') {
        $mapel = trim($_POST['mapel']);
        $keterangan = $_POST['keterangan'];
        $catatan = trim($_POST['catatan'] ?? '');
        $lat = (float)$_POST['lat'];
        $lng = (float)$_POST['lng'];
        $img_data = $_POST['foto_absen'];

        $srv = getServerLocation($pdo);
        $jarak = getDistance($srv['lat'], $srv['lng'], $lat, $lng);

        if ($jarak > $srv['radius']) {
            header("Location: student_dashboard.php?tab=absensi&err=" . urlencode("Absensi Gagal! Anda berada di luar radius lab (" . round($jarak) . " meter dari titik acuan)."));
            exit;
        }

        // Proses Foto Absen Base64
        $filename = '';
        if ($img_data) {
            $img_data = str_replace('data:image/jpeg;base64,', '', $img_data);
            $img_data = str_replace(' ', '+', $img_data);
            $decoded = base64_decode($img_data);
            $filename = 'absen_' . $user_id . '_' . time() . '.jpg';
            file_put_contents('uploads/tugas/' . $filename, $decoded);
        }

        try {
            $ins = $pdo->prepare("INSERT INTO absensi (id_siswa, mapel, tanggal, keterangan, catatan, foto_absen, lokasi_gps, created_by) VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?)");
            $ins->execute([$user_id, $mapel, $keterangan, $catatan, $filename, "$lat, $lng", $user_id]);
            header("Location: student_dashboard.php?tab=absensi&msg=" . urlencode("Absensi Berhasil Tercatat!"));
            exit;
        } catch (PDOException $e) {
            header("Location: student_dashboard.php?tab=absensi&err=" . urlencode("Anda sudah melakukan absensi untuk mapel dan tanggal ini!"));
            exit;
        }
    }

    // Pengumpulan Tugas (File / Teks)
    if ($action === 'kumpul_tugas') {
        $id_lkpd = (int)$_POST['id_lkpd'];
        $mapel = trim($_POST['mapel']);
        $judul_tugas = trim($_POST['judul_tugas']);
        $tipe_kirim = $_POST['tipe_kirim']; // 'file' atau 'teks'
        
        $file_path = "TIDAK_ADA_FILE";
        $jawaban_teks = null;

        if ($tipe_kirim === 'file') {
            $res = upload_secure_file($_FILES['berkas_tugas'], 'uploads/tugas', 50);
            if (!$res['status']) {
                header("Location: student_dashboard.php?tab=tugas&err=" . urlencode($res['msg']));
                exit;
            }
            $file_path = $res['filename'];
        } else {
            $jawaban_teks = trim($_POST['jawaban_teks']);
            $file_path = "ONLINE_TEXT_ANSWER";
        }

        $stmt = $pdo->prepare("INSERT INTO pengumpulan (id_siswa, id_lkpd, tipe_tugas, judul_tugas, mapel, file_path, jawaban_teks) VALUES (?, ?, 'lkpd', ?, ?, ?, ?)");
        $stmt->execute([$user_id, $id_lkpd, $judul_tugas, $mapel, $file_path, $jawaban_teks]);
        header("Location: student_dashboard.php?tab=tugas&msg=" . urlencode("Tugas Berhasil Dikirim!"));
        exit;
    }
}

$tab = $_GET['tab'] ?? 'materi';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Siswa - LMS Portal Enterprise</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
  <div class="container">
    <a class="navbar-brand fw-bold text-info" href="#">LMS SISWA (<?= e($kelas_siswa) ?>)</a>
    <div class="navbar-nav ms-auto">
        <span class="nav-link text-white">Siswa: <?= e($student['nama_lengkap']) ?></span>
        <a class="btn btn-outline-danger btn-sm" href="logout.php">Logout</a>
    </div>
  </div>
</nav>

<div class="container my-4">
    <ul class="nav nav-pills mb-4 flex-nowrap overflow-auto">
      <li class="nav-item"><a class="nav-link <?= $tab=='materi'?'active':'' ?>" href="?tab=materi">📚 Modul & Materi</a></li>
      <li class="nav-item"><a class="nav-link <?= $tab=='tugas'?'active':'' ?>" href="?tab=tugas">📝 Tugas & LKPD</a></li>
      <li class="nav-item"><a class="nav-link <?= $tab=='absensi'?'active':'' ?>" href="?tab=absensi">📸 Absensi Wajah & GPS</a></li>
      <li class="nav-item"><a class="nav-link <?= $tab=='live'?'active':'' ?>" href="?tab=live">🖥️ Live Screen Guru</a></li>
      <li class="nav-item"><a class="nav-link <?= $tab=='chat'?'active':'' ?>" href="?tab=chat">💬 Live Chat Kelas</a></li>
    </ul>

    <?php if ($msg): ?><div class="alert alert-success alert-dismissible fade show"><?= e($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-danger alert-dismissible fade show"><?= e($err) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

    <!-- TAB 1: MODUL & MATERI -->
    <?php if ($tab === 'materi'): ?>
        <div class="card p-3 shadow-sm border-0">
            <h5>Modul Pembelajaran untuk Kelas <?= e($kelas_siswa) ?></h5>
            <div class="table-responsive mt-3">
                <table class="table table-bordered align-middle">
                    <thead class="table-dark"><tr><th>Mata Pelajaran</th><th>Judul Modul</th><th>Aksi Unduh</th></tr></thead>
                    <tbody>
                        <?php 
                        $stmt = $pdo->prepare("SELECT * FROM modul WHERE tingkat_kelas = ? OR tingkat_kelas = 'Semua' ORDER BY id DESC");
                        $stmt->execute([substr($kelas_siswa, 0, 2)]); // Misal "10" dari "10 TKJ 1"
                        $moduls = $stmt->fetchAll();
                        foreach($moduls as $m): 
                        ?>
                        <tr>
                            <td><?= e($m['mapel']) ?></td>
                            <td><?= e($m['judul']) ?></td>
                            <td><a href="uploads/modul/<?= e($m['file_path']) ?>" class="btn btn-sm btn-primary" download>Unduh Modul</a></td>
                        </tr>
                        <?php endforeach; if(empty($moduls)) echo "<tr><td colspan='3' class='text-center text-muted'>Belum ada modul tersedia untuk tingkat kelas Anda.</td></tr>"; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <!-- TAB 2: TUGAS & LKPD -->
    <?php elseif ($tab === 'tugas'): ?>
        <div class="card p-3 shadow-sm border-0 mb-4">
            <h5>Daftar Tugas & LKPD Kelas <?= e($kelas_siswa) ?></h5>
            <div class="table-responsive mt-3">
                <table class="table table-bordered align-middle">
                    <thead class="table-dark"><tr><th>Mapel</th><th>Pertemuan</th><th>Judul Tugas</th><th>Aksi Pengiriman</th></tr></thead>
                    <tbody>
                        <?php 
                        $stmt = $pdo->prepare("SELECT * FROM lkpd WHERE kelas = ? ORDER BY id DESC");
                        $stmt->execute([$kelas_siswa]);
                        $lkpds = $stmt->fetchAll();
                        foreach($lkpds as $l):
                        ?>
                        <tr>
                            <td><?= e($l['mapel']) ?></td>
                            <td>Ke-<?= e($l['pertemuan_ke']) ?></td>
                            <td><?= e($l['judul']) ?></td>
                            <td>
                                <?php if($l['tipe'] === 'file'): ?>
                                    <a href="uploads/lkpd/<?= e($l['file_or_link']) ?>" class="btn btn-sm btn-secondary mb-1" download>Unduh LKPD</a>
                                <?php else: ?>
                                    <a href="<?= e($l['file_or_link']) ?>" target="_blank" class="btn btn-sm btn-info text-white mb-1">Buka Link External</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card p-3 shadow-sm border-0">
            <h5>Formulir Pengumpulan Tugas</h5>
            <form method="POST" enctype="multipart/form-data" class="mt-3">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="action" value="kumpul_tugas">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Pilih Tugas (LKPD)</label>
                        <select name="id_lkpd" class="form-select" required>
                            <option value="">-- Pilih Tugas --</option>
                            <?php foreach($lkpds as $l): ?>
                                <option value="<?= $l['id'] ?>">[<?= e($l['mapel']) ?>] Pertemuan <?= e($l['pertemuan_ke']) ?> - <?= e($l['judul']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Mata Pelajaran</label>
                        <input type="text" name="mapel" class="form-control" required placeholder="Contoh: ASJ / TLJ">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Judul / Keterangan Tugas</label>
                        <input type="text" name="judul_tugas" class="form-control" required placeholder="Tugas Praktikum 1">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Metode Pengiriman</label>
                    <select name="tipe_kirim" class="form-select" id="tipeKirim" onchange="toggleKirim(this.value)" required>
                        <option value="file">Upload Berkas File / Arsip</option>
                        <option value="teks">Ketik Jawaban / Essay Langsung (Online Text)</option>
                    </select>
                </div>
                <div class="mb-3" id="groupFile">
                    <label class="form-label">Pilih Berkas (PDF, DOCX, ZIP, MP4 max 50MB)</label>
                    <input type="file" name="berkas_tugas" class="form-control">
                </div>
                <div class="mb-3 d-none" id="groupTeks">
                    <label class="form-label">Jawaban Teks / Code Konfigurasi</label>
                    <textarea name="jawaban_teks" class="form-control" rows="5" placeholder="Ketik jawaban Anda di sini..."></textarea>
                </div>
                <button type="submit" class="btn btn-success fw-bold">Kirim Tugas Sekarang</button>
            </form>
        </div>
        <script>
        function toggleKirim(val) {
            document.getElementById('groupFile').classList.toggle('d-none', val !== 'file');
            document.getElementById('groupTeks').classList.toggle('d-none', val !== 'teks');
        }
        </script>

    <!-- TAB 3: ABSENSI WAJAH & GPS -->
    <?php elseif ($tab === 'absensi'): ?>
        <div class="card p-3 shadow-sm border-0">
            <h5>📸 Absensi Kamera Wajah & Geofencing GPS (Radius 30m)</h5>
            <p class="text-muted small">Sistem mendeteksi posisi koordinat GPS Anda dan membubuhkan watermark waktu secara otomatis.</p>
            
            <form method="POST" id="formAbsen" class="mt-3">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="action" value="absen">
                <input type="hidden" name="lat" id="lat">
                <input type="hidden" name="lng" id="lng">
                <input type="hidden" name="foto_absen" id="fotoAbsen">

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Mata Pelajaran</label>
                        <input type="text" name="mapel" class="form-control" required placeholder="Nama Mapel">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Keterangan Kehadiran</label>
                        <select name="keterangan" class="form-select">
                            <option value="Hadir">Hadir</option>
                            <option value="Sakit">Sakit</option>
                            <option value="Izin">Izin</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Catatan (Opsional)</label>
                        <input type="text" name="catatan" class="form-control" placeholder="Keterangan tambahan...">
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6 text-center">
                        <div class="bg-dark rounded overflow-hidden mb-2" style="position: relative; height: 320px;">
                            <video id="videoCam" autoplay playsinline style="width: 100%; height: 100%; object-fit: cover;"></video>
                            <canvas id="canvasCam" class="d-none"></canvas>
                        </div>
                        <button type="button" class="btn btn-primary fw-bold" onclick="ambilFoto()">Ambil Foto & Kunci GPS</button>
                    </div>
                    <div class="col-md-6 text-center">
                        <div class="border rounded bg-white p-3 d-flex align-items-center justify-content-center" style="height: 320px;">
                            <img id="previewFoto" class="img-fluid rounded d-none" style="max-height: 300px; object-fit: contain;">
                            <span id="placeholderFoto" class="text-muted">Pratinjau foto absen dengan watermark akan muncul di sini.</span>
                        </div>
                    </div>
                </div>

                <div id="gpsStatus" class="alert alert-info py-2 small">Mencari koordinat GPS perangkat Anda...</div>
                <button type="submit" id="btnSubmitAbsen" class="btn btn-success w-100 fw-bold" disabled>Kirim Absensi Resmi</button>
            </form>
        </div>

        <script>
        const video = document.getElementById('videoCam');
        navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false })
            .then(stream => { video.srcObject = stream; })
            .catch(err => { alert("Gagal mengakses kamera! Pastikan izin kamera aktif pada browser."); });

        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(pos => {
                document.getElementById('lat').value = pos.coords.latitude;
                document.getElementById('lng').value = pos.coords.longitude;
                document.getElementById('gpsStatus').className = "alert alert-success py-2 small fw-bold";
                document.getElementById('gpsStatus').innerHTML = `✅ GPS Terkunci (Lat: ${pos.coords.latitude.toFixed(4)}, Lng: ${pos.coords.longitude.toFixed(4)})`;
            }, err => {
                document.getElementById('gpsStatus').className = "alert alert-danger py-2 small fw-bold";
                document.getElementById('gpsStatus').innerHTML = "❌ Gagal mendeteksi lokasi GPS. Aktifkan lokasi perangkat Anda.";
            }, { enableHighAccuracy: true });
        }

        function ambilFoto() {
            const canvas = document.getElementById('canvasCam');
            canvas.width = video.videoWidth || 640;
            canvas.height = video.videoHeight || 480;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

            // Watermark Transparan
            ctx.fillStyle = "rgba(0, 0, 0, 0.6)";
            ctx.fillRect(10, canvas.height - 60, canvas.width - 20, 50);
            ctx.fillStyle = "#ffffff";
            ctx.font = "14px Arial";
            ctx.fillText("Siswa: <?= e($student['nama_lengkap']) ?> | Kelas: <?= e($kelas_siswa) ?>", 20, canvas.height - 35);
            ctx.fillText("Waktu: " + new Date().toLocaleString() + " | GPS Verified", 20, canvas.height - 15);

            const dataURL = canvas.toDataURL('image/jpeg', 0.85);
            document.getElementById('fotoAbsen').value = dataURL;
            document.getElementById('previewFoto').src = dataURL;
            document.getElementById('previewFoto').classList.remove('d-none');
            document.getElementById('placeholderFoto').classList.add('d-none');
            document.getElementById('btnSubmitAbsen').removeAttribute('disabled');
        }
        </script>

    <!-- TAB 4: LIVE SCREEN GURU -->
    <?php elseif ($tab === 'live'): ?>
        <div class="card p-3 shadow-sm border-0 text-center">
            <h5>🖥️ Siaran Layar Praktikum (Live Screen Share P2P Zero-Delay)</h5>
            <p class="text-muted small">Tonton siaran langsung layar praktikum yang disiarkan oleh guru secara real-time.</p>
            <div class="bg-dark rounded overflow-hidden mt-3" style="min-height: 450px;">
                <video id="remote-screen" autoplay playsinline style="width: 100%; max-height: 600px; object-fit: contain;"></video>
            </div>
            <button class="btn btn-primary mt-3 fw-bold" onclick="hubungkanLive()">Hubungkan ke Siaran Guru</button>
        </div>
        <script>
        let rpc = new RTCPeerConnection({ iceServers: [{ urls: 'stun:stun.l.google.com:19302' }] });
        function hubungkanLive() {
            rpc.ontrack = e => { document.getElementById('remote-screen').srcObject = e.streams[0]; };
            setInterval(() => {
                fetch('live_stream.php?action=fetch')
                .then(res => res.json())
                .then(msgs => {
                    msgs.forEach(m => {
                        let payload = JSON.parse(m.payload);
                        if (m.type === 'offer') {
                            rpc.setRemoteDescription(new RTCSessionDescription(payload))
                            .then(() => rpc.createAnswer())
                            .then(ans => rpc.setLocalDescription(ans))
                            .then(() => {
                                fetch('live_stream.php?action=send', {
                                    method: 'POST',
                                    headers: {'Content-Type': 'application/json'},
                                    body: JSON.stringify({ type: 'answer', payload: rpc.localDescription })
                                });
                            });
                        }
                    });
                });
            }, 1000);
            alert("Mencoba menghubungkan ke siaran guru...");
        }
        </script>

    <!-- TAB 5: LIVE CHAT KELAS -->
    <?php elseif ($tab === 'chat'): ?>
        <div class="card p-3 shadow-sm border-0">
            <h5>💬 Ruang Diskusi & Live Chat Kelas (<?= e($kelas_siswa) ?>)</h5>
            <p class="text-muted small">Chat terisolasi khusus kelas Anda. Aktif selama sesi pelajaran berlangsung.</p>
            
            <div class="border rounded bg-white p-3 mb-3 overflow-auto" id="chatContainer" style="height: 350px;"></div>
            
            <div class="input-group">
                <input type="text" id="pesanInput" class="form-control" placeholder="Tulis pesan diskusi...">
                <button class="btn btn-primary fw-bold" onclick="kirimPesan()">Kirim Pesan</button>
            </div>
        </div>
        <script>
        function loadChat() {
            fetch('chat_endpoint.php?action=get&kelas=<?= urlencode($kelas_siswa) ?>')
            .then(res => res.json())
            .then(data => {
                let html = '';
                data.forEach(c => {
                    html += `<div><b>${c.nama_lengkap}:</b> ${c.pesan} <small class="text-muted float-end">${c.created_at}</small></div><hr class="my-1">`;
                });
                const container = document.getElementById('chatContainer');
                container.innerHTML = html;
                container.scrollTop = container.scrollHeight;
            });
        }
        setInterval(loadChat, 2000);

        function kirimPesan() {
            let pesan = document.getElementById('pesanInput').value;
            if(!pesan) return;
            fetch('chat_endpoint.php?action=send', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ kelas: '<?= $kelas_siswa ?>', pesan: pesan, csrf_token: '<?= $_SESSION['csrf_token'] ?>' })
            }).then(() => {
                document.getElementById('pesanInput').value = '';
                loadChat();
            });
        }
        </script>
    <?php endif; ?>
</div>
</body>
</html>