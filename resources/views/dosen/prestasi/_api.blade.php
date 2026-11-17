<script>
    async function apiPrestasi(path = '', method = 'GET', data = null) {
        const headers = { Accept: 'application/json' };
        if (method !== 'GET') headers['X-CSRF-TOKEN'] = @js(csrf_token());
        if (data !== null) headers['Content-Type'] = 'application/json';
        const response = await fetch('/api/v1/dosen/prestasi' + path, {
            method, headers, credentials: 'same-origin', body: data === null ? null : JSON.stringify(data)
        });
        const result = await response.json();
        if (!response.ok) throw new Error(Object.values(result.errors || {}).flat().join(' ') || result.message || 'Gagal mengakses prestasi.');
        return result;
    }
</script>
