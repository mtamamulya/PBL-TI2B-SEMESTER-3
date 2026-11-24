<div id="dokumen-container" data-pengajuan-id="{{ $pengajuanId ?? $id ?? 1 }}">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
        <h4>Daftar Dokumen Lampiran</h4>
        <a id="btn-pdf-final"
           href="/api/pengajuan/{{ $pengajuanId ?? $id ?? 1 }}/surat/pdf-final"
           target="_blank"
           class="btn btn-primary">
            Lihat / Unduh PDF Surat Final
        </a>
    </div>

    <form id="form-upload-dokumen" style="margin-bottom: 16px; display: flex; gap: 8px;">
        <input type="text" name="nama_file" placeholder="Nama dokumen" required class="form-control">
        <input type="file" name="file" required class="form-control">
        <button type="submit" class="btn btn-secondary">Unggah</button>
    </form>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>No</th>
                <th>Jenis</th>
                <th>Nama Dokumen</th>
                <th>Berkas / Tautan</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody id="tbody-lampiran">
            <tr><td colspan="5">Memuat data lampiran...</td></tr>
        </tbody>
    </table>

    <form id="form-ttd" style="margin-top: 24px; padding-top: 16px; border-top: 1px solid #ddd;">
        <h5>Pengesahan & Tanda Tangan Surat</h5>
        <div style="display: flex; gap: 8px; margin-bottom: 8px;">
            <input type="text" name="nomor_sk" placeholder="Nomor SK / Surat" class="form-control">
            <input type="date" name="tanggal_sk" class="form-control">
            <input type="file" name="ttd_file" accept="image/png,image/jpeg" class="form-control">
            <button type="submit" class="btn btn-success">Simpan TTD</button>
        </div>
    </form>
</div>

<script>
const container = document.getElementById('dokumen-container');
const pengajuanId = container.dataset.pengajuanId;
const tbody = document.getElementById('tbody-lampiran');
const token = localStorage.getItem('token');
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

const authHeaders = () => ({
    'Accept': 'application/json',
    'X-CSRF-TOKEN': csrfToken,
    ...(token ? { 'Authorization': `Bearer ${token}` } : {})
});

async function loadLampiran() {
    try {
        const res = await fetch(`/api/kegiatan/${pengajuanId}/dokumen`, {
            headers: authHeaders(),
            credentials: 'same-origin'
        });
        const json = await res.json();
        const items = json.data || [];

        if (items.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center">Belum ada lampiran.</td></tr>';
            return;
        }

        tbody.innerHTML = items.map((doc, idx) => `
            <tr>
                <td>${idx + 1}</td>
                <td>${doc.jenis_dokumen ?? '-'}</td>
                <td>${doc.nama_file ?? '-'}</td>
                <td>${doc.url ? `<a href="${doc.url}" target="_blank">Buka File</a>` : '-'}</td>
                <td>
                    <button type="button" onclick="hapusDokumen(${doc.id_dokumen})" class="btn btn-sm btn-danger">Hapus</button>
                </td>
            </tr>
        `).join('');
    } catch (err) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-danger">Gagal memuat lampiran dari API.</td></tr>';
    }
}

async function hapusDokumen(dokumenId) {
    const res = await fetch(`/api/kegiatan/${pengajuanId}/dokumen/${dokumenId}`, {
        method: 'DELETE',
        headers: authHeaders(),
        credentials: 'same-origin'
    });
    const json = await res.json();
    if (!res.ok) {
        alert(json.message || 'Gagal menghapus dokumen.');
        return;
    }
    loadLampiran();
}

document.getElementById('form-upload-dokumen').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const res = await fetch(`/api/kegiatan/${pengajuanId}/dokumen`, {
        method: 'POST',
        headers: authHeaders(),
        credentials: 'same-origin',
        body: formData
    });
    const json = await res.json();
    if (!res.ok) {
        alert(json.message || 'Gagal mengunggah dokumen.');
        return;
    }
    e.target.reset();
    loadLampiran();
});

document.getElementById('form-ttd').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const res = await fetch(`/api/pengajuan/${pengajuanId}/surat/tanda-tangan`, {
        method: 'POST',
        headers: authHeaders(),
        credentials: 'same-origin',
        body: formData
    });
    const json = await res.json();
    if (res.ok) {
        alert('Pengesahan dan tanda tangan surat berhasil disimpan.');
        e.target.reset();
    } else {
        alert(json.message || 'Gagal menyimpan tanda tangan.');
    }
});

document.addEventListener('DOMContentLoaded', loadLampiran);
</script>