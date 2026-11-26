<script>
async function apiBerita(path = '', method = 'GET', data = null) {
    const headers = { 
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
    };
    
    // Ambil CSRF token langsung dari Blade (100% pasti ada)
    const csrfToken = @js(csrf_token());
    if (method !== 'GET' && csrfToken) {
        headers['X-CSRF-TOKEN'] = csrfToken;
    }
    
    let body = null;
    if (data !== null) {
        if (data instanceof FormData) {
            body = data; // Browser otomatis set multipart/form-data
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
        if (response.status === 401) throw new Error('Sesi berakhir. Silakan login ulang.');
        if (response.status === 403) throw new Error('Anda tidak memiliki akses.');
        if (response.status === 422 && result.errors) {
            throw new Error(Object.values(result.errors).flat().join(', '));
        }
        throw new Error(result.message || 'Gagal mengakses berita.');
    }
    
    return result;
}
</script>