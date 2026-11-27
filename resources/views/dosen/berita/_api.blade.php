<script>
function beritaUntukTampilan(row) {
    const labels = {draft: 'Draft', revisi: 'Direvisi', direvisi: 'Direvisi', diajukan: 'Diajukan', publish: 'Dipublikasikan', ditolak: 'Ditolak'};
    const isi = new DOMParser().parseFromString(row.isi_berita || '', 'text/html').body.textContent;
    return {...row, id: row.id_berita, judul: row.judul_berita, isi,
        kategori: row.kategori?.nama_kategori || row.kegiatan?.kategori?.nama_kategori || (row.id_prestasi ? 'Prestasi' : 'Mandiri'),
        status: labels[row.status] || row.status, tanggal: row.created_at ? new Date(row.created_at).toLocaleDateString('id-ID') : ''};
}
async function apiBerita(path = '', method = 'GET', data = null) {
    const headers = {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
    };

    // Ambil CSRF token dari meta tag
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || @js(csrf_token());

    // Tambah CSRF token untuk method non-GET
    if (method !== 'GET' && csrfToken) {
        headers['X-CSRF-TOKEN'] = csrfToken;
    }

    let body = null;
    if (data !== null) {
        if (data instanceof FormData) {
            body = data;
            // Jangan set Content-Type untuk FormData, browser akan set otomatis dengan boundary
        } else {
            headers['Content-Type'] = 'application/json';
            body = JSON.stringify(data);
        }
    }

    const response = await fetch('/api/v1/dosen/berita' + path, {
        method,
        headers,
        credentials: 'same-origin',
        body
    });

    const result = await response.json();

    if (!response.ok) {
        // Handle error 401 (belum login) dan 403 (forbidden)
        if (response.status === 401) {
            throw new Error('Belum login atau sesi telah berakhir. Silakan login ulang.');
        }
        if (response.status === 403) {
            throw new Error('Anda tidak memiliki akses untuk melakukan ini.');
        }

        // Handle validation error (422)
        if (response.status === 422 && result.errors) {
            const messages = Object.values(result.errors).flat();
            throw new Error(messages.join(', '));
        }

        throw new Error(result.message || 'Gagal mengakses berita.');
    }

    return result;
}
</script>